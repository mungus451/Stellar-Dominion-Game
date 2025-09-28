<?php
// template/pages/war_declaration.php
// - Enforces a minimum threshold of 100,000,000 credits (credits_plundered is the only goal).
// - Removes Economic Vassalage AND Revolution from casus belli options.
// - Adds optional Custom War Badge (name/desc/icon) when casus_belli = custom and persists it into wars.* if columns exist.
// - Validates CSRF + permissions; prevents simultaneous active wars; charges war cost in TX.
// - Keeps original UI/layout (slider + number input).

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.html"); exit; }

$active_page = 'war_declaration.php';
$ROOT = dirname(__DIR__, 2);

require_once $ROOT . '/config/config.php';
require_once $ROOT . '/src/Security/CSRFProtection.php';
require_once $ROOT . '/src/Game/GameData.php';
require_once $ROOT . '/src/Game/GameFunctions.php';
require_once $ROOT . '/template/includes/advisor_hydration.php';

const SD_MIN_WAR_THRESHOLD_CREDITS = 100000000; // 100M
const SD_MIN_WAR_COST             = 30000000;   // 30M

// --- helpers ---
function sd_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function sd_get_user_perms(mysqli $db, int $user_id): ?array {
    $sql = "SELECT u.alliance_id, ar.`order` AS hierarchy
            FROM users u
            LEFT JOIN alliance_roles ar ON u.alliance_role_id = ar.id
            WHERE u.id = ?";
    $st = $db->prepare($sql);
    if (!$st) return null;
    $st->bind_param('i', $user_id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function sd_active_war_exists(mysqli $db, int $a, int $b): bool {
    $sql = "SELECT id FROM wars
            WHERE status='active'
              AND ((declarer_alliance_id=? AND declared_against_alliance_id=?)
                OR (declarer_alliance_id=? AND declared_against_alliance_id=?))
            LIMIT 1";
    $st = $db->prepare($sql);
    $st->bind_param('iiii', $a, $b, $b, $a);
    $st->execute();
    $ok = (bool)$st->get_result()->fetch_row();
    $st->close();
    return $ok;
}

function sd_normalize_cb(string $key): string {
    $k = strtolower(trim($key));
    // Economic vassalage and revolution removed
    $allowed = ['humiliation','dignity','custom'];
    return in_array($k, $allowed, true) ? $k : 'humiliation';
}

function sd_normalize_metric(string $m): string {
    // Force credits only
    return 'credits_plundered';
}

function sd_alliance_by_id(mysqli $db, int $id): ?array {
    $st = $db->prepare("SELECT id, name, tag, bank_credits FROM alliances WHERE id=?");
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function sd_column_exists(mysqli $db, string $table, string $column): bool {
    $q = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    if (!$q) return false;
    $q->bind_param('s', $column);
    $q->execute();
    $res = $q->get_result();
    $ok = $res && $res->num_rows > 0;
    $q->close();
    return $ok;
}

// --- state / auth ---
$user_id = (int)$_SESSION['id'];
$me      = sd_get_user_perms($link, $user_id);
if (!$me || !$me['alliance_id']) {
    $_SESSION['war_message'] = 'You must be in an alliance to declare wars.';
    header('Location: /realm_war.php'); exit;
}
$my_alliance_id = (int)$me['alliance_id'];
$my_hierarchy   = (int)($me['hierarchy'] ?? 999);
if (!in_array($my_hierarchy, [1,2], true)) {
    $_SESSION['war_message'] = 'Only alliance leaders or diplomats can declare war.';
    header('Location: /realm_war.php'); exit;
}

$errors = [];
$success = "";

// --- handle POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!CSRFProtection::getInstance()->validateToken($token, 'war_declare')) {
            throw new Exception('Security check failed. Please try again.');
        }

        $war_name   = trim((string)($_POST['war_name'] ?? ''));
        if ($war_name === '') { $war_name = 'Unnamed Conflict'; }
        $target_id  = (int)($_POST['alliance_id'] ?? 0);
        if ($target_id <= 0) { throw new Exception('Please choose a target alliance.'); }
        if ($target_id === $my_alliance_id) { throw new Exception('You cannot declare war on your own alliance.'); }

        $cb_key     = sd_normalize_cb((string)($_POST['casus_belli'] ?? 'humiliation'));
        $cb_custom  = null;
        if ($cb_key === 'custom') {
            $cb_custom = trim((string)($_POST['casus_belli_custom'] ?? ''));
            if ($cb_custom === '') { throw new Exception('Provide a custom casus belli description.'); }
            if (mb_strlen($cb_custom) > 244) $cb_custom = mb_substr($cb_custom, 0, 244);
        }

        // Custom Badge inputs
        $customBadgeName = null;
        $customBadgeDesc = null;
        $customBadgePath = null;

        if ($cb_key === 'custom') {
            $customBadgeName = trim((string)($_POST['custom_badge_name'] ?? ''));
            $customBadgeDesc = trim((string)($_POST['custom_badge_description'] ?? ''));
            if ($customBadgeName !== '') {
                if (mb_strlen($customBadgeName) > 100) $customBadgeName = mb_substr($customBadgeName, 0, 100);
                if (mb_strlen($customBadgeDesc) > 255) $customBadgeDesc = mb_substr($customBadgeDesc, 0, 255);
            }

            if (!empty($_FILES['custom_badge_icon']['name'] ?? '')) {
                $allowed = ['png','jpg','jpeg','gif','avif','webp'];
                $maxSize = 256 * 1024; // 256KB
                $err = (int)($_FILES['custom_badge_icon']['error'] ?? UPLOAD_ERR_OK);
                if ($err !== UPLOAD_ERR_OK) { throw new Exception('Badge icon upload failed.'); }
                if (($_FILES['custom_badge_icon']['size'] ?? 0) > $maxSize) { throw new Exception('Badge icon too large (max 256KB).'); }

                $ext = strtolower(pathinfo($_FILES['custom_badge_icon']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed, true)) { throw new Exception('Invalid badge icon type.'); }

                $uploadDir = $ROOT . '/public/uploads/war_badges/';
                if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
                $safeBase = preg_replace('/[^a-z0-9_-]+/i', '-', $customBadgeName ?: 'custom');
                $fileName = sprintf('war_%d_%s_%d.%s', $my_alliance_id, strtolower($safeBase), time(), $ext);
                $destFs   = $uploadDir . $fileName;
                if (!move_uploaded_file($_FILES['custom_badge_icon']['tmp_name'], $destFs)) {
                    throw new Exception('Could not save badge icon.');
                }
                $customBadgePath = '/uploads/war_badges/' . $fileName;
            }
        }

        // Force credits goal + min
        $metric     = sd_normalize_metric((string)($_POST['goal_metric'] ?? 'credits_plundered'));
        $posted_thr = (int)($_POST['goal_threshold'] ?? 0);
        $threshold  = max(SD_MIN_WAR_THRESHOLD_CREDITS, $posted_thr);

        // Prevent duplicate active wars
        if (sd_active_war_exists($link, $my_alliance_id, $target_id)) {
            throw new Exception('There is already an active war between these alliances.');
        }

        // Compute war cost against current bank (re-check inside TX)
        $myAlliance = sd_alliance_by_id($link, $my_alliance_id);
        if (!$myAlliance) { throw new Exception('Alliance not found.'); }
        $bank = (int)$myAlliance['bank_credits'];
        $war_cost = max(SD_MIN_WAR_COST, (int)ceil($bank * 0.10));
        if ($bank < $war_cost) { throw new Exception('Insufficient alliance funds. Need '.number_format($war_cost).' credits.'); }

        // Begin transaction: lock bank, re-evaluate, deduct, insert war
        $link->begin_transaction();
        try {
            // Lock row
            $st = $link->prepare("SELECT bank_credits FROM alliances WHERE id=? FOR UPDATE");
            $st->bind_param('i', $my_alliance_id);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            $bankNow = (int)($row['bank_credits'] ?? 0);
            $war_cost = max(SD_MIN_WAR_COST, (int)ceil($bankNow * 0.10));
            if ($bankNow < $war_cost) { throw new Exception('Insufficient funds at time of declaration.'); }

            // Duplicate check again inside TX
            if (sd_active_war_exists($link, $my_alliance_id, $target_id)) {
                throw new Exception('An active war already exists between these alliances.');
            }

            // Deduct
            $st = $link->prepare("UPDATE alliances SET bank_credits = bank_credits - ? WHERE id=?");
            $st->bind_param('ii', $war_cost, $my_alliance_id);
            $st->execute();
            $st->close();

            // Insert war row
            $cols = [
                'name','declarer_alliance_id','declared_against_alliance_id',
                'casus_belli_key','casus_belli_custom',
                'status','goal_metric','goal_threshold','start_date'
            ];
            $vals = [
                $war_name, $my_alliance_id, $target_id,
                $cb_key, $cb_custom,
                'active', $metric, $threshold, date('Y-m-d H:i:s')
            ];
            $types = 'siisssiss';

            // Convenience goal column (credits only)
            if (sd_column_exists($link, 'wars', 'goal_credits_plundered')) {
                $cols[] = 'goal_credits_plundered'; $vals[] = $threshold; $types .= 'i';
            }

            // Optional war_cost column
            if (sd_column_exists($link, 'wars', 'war_cost')) {
                $cols[] = 'war_cost'; $vals[] = $war_cost; $types .= 'i';
            }

            // Persist custom badge metadata if present in schema and casus is custom
            if ($cb_key === 'custom') {
                if (sd_column_exists($link, 'wars', 'custom_badge_name'))        { $cols[]='custom_badge_name';        $vals[]=$customBadgeName; $types.='s'; }
                if (sd_column_exists($link, 'wars', 'custom_badge_description')) { $cols[]='custom_badge_description'; $vals[]=$customBadgeDesc; $types.='s'; }
                if (sd_column_exists($link, 'wars', 'custom_badge_icon_path'))   { $cols[]='custom_badge_icon_path';   $vals[]=$customBadgePath; $types.='s'; }
            }

            $placeholder = implode(',', array_fill(0, count($cols), '?'));
            $sql = "INSERT INTO wars (".implode(',', $cols).") VALUES ($placeholder)";
            $st = $link->prepare($sql);
            $st->bind_param($types, ...$vals);
            $st->execute();
            $st->close();

            $link->commit();
            $_SESSION['war_message'] = 'War declared successfully. Cost: '.number_format($war_cost).' credits.';
            header('Location: /realm_war.php'); exit;
        } catch (Throwable $e) {
            $link->rollback();
            throw $e;
        }

    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

// --- page data ---
$alliances = [];
$st = $link->prepare("SELECT id, name, tag FROM alliances WHERE id <> ? ORDER BY name ASC");
$st->bind_param('i', $my_alliance_id);
$st->execute();
$alliances = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

// war cost estimate for UI
$meAlliance = sd_alliance_by_id($link, $my_alliance_id);
$est_cost = 0;
if ($meAlliance) {
    $est_cost = max(SD_MIN_WAR_COST, (int)ceil(((int)$meAlliance['bank_credits']) * 0.10));
}

$page_title = 'Declare War - Starlight Dominion';
include_once $ROOT . '/template/includes/header.php';
?>
<div class="lg:col-span-4 space-y-6">

  <?php if (!empty($errors)): ?>
    <div class="rounded-md border border-red-600 bg-red-900/30 text-red-200 px-3 py-2">
      <?php foreach ($errors as $err): ?>
        <div><?php echo sd_h($err); ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="content-box">
    <h2 class="font-title text-2xl mb-3">Declare a Realm War</h2>
    <p class="text-sm opacity-80 mb-4">
      War cost is <strong>10%</strong> of your alliance bank (minimum <strong><?php echo number_format(SD_MIN_WAR_COST); ?></strong> credits).
      Only <strong>Credits Plundered</strong> is supported as a goal, with a minimum threshold of <strong><?php echo number_format(SD_MIN_WAR_THRESHOLD_CREDITS); ?></strong>.
    </p>

    <form id="warForm" action="/war_declaration.php" method="POST" enctype="multipart/form-data" class="space-y-6">
      <?php echo CSRFProtection::getInstance()->getTokenField('war_declare'); ?>
      <input type="hidden" name="action" value="declare_war" />
      <input type="hidden" name="goal_metric" value="credits_plundered" />

      <div>
        <span class="block mb-2 text-lg font-title text-cyan-400">Step 1: Name the War</span>
        <input type="text" name="war_name" maxlength="100" class="w-full p-2 rounded bg-gray-800 border border-gray-700 text-white"
               placeholder="e.g., The Orion Conflict">
      </div>

      <div>
        <span class="block mb-2 text-lg font-title text-cyan-400">Step 2: Choose Opponent</span>
        <select name="alliance_id" required class="w-full p-2 rounded bg-gray-800 border border-gray-700 text-white">
          <option value="">— Select Alliance —</option>
          <?php foreach ($alliances as $a): ?>
            <option value="<?php echo (int)$a['id']; ?>">[<?php echo sd_h($a['tag']); ?>] <?php echo sd_h($a['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <span class="block mb-2 text-lg font-title text-cyan-400">Step 3: Casus Belli</span>
        <div class="grid md:grid-cols-3 gap-3">
          <label class="flex items-center gap-2 bg-gray-900/60 border border-gray-700 rounded p-3">
            <input type="radio" name="casus_belli" value="humiliation" checked>
            <span class="font-semibold text-white">Humiliation</span>
          </label>
          <label class="flex items-center gap-2 bg-gray-900/60 border border-gray-700 rounded p-3">
            <input type="radio" name="casus_belli" value="dignity">
            <span class="font-semibold text-white">Restore Dignity</span>
          </label>
          <label class="flex items-center gap-2 bg-gray-900/60 border border-gray-700 rounded p-3">
            <input type="radio" name="casus_belli" value="custom">
            <span class="font-semibold text-white">Custom…</span>
          </label>
        </div>

        <div id="customBadgeBox" class="mt-3 hidden">
          <span class="block mb-2 text-lg font-title text-cyan-400">Custom War Badge</span>
          <p class="text-xs opacity-75 mb-2">
            Optional: the loser’s members will receive this badge when the war ends in your victory.
          </p>
          <div class="grid md:grid-cols-2 gap-3">
            <input type="text" name="custom_badge_name" maxlength="100"
                   placeholder="Badge name (e.g., Mark of Orion)"
                   class="w-full p-2 rounded bg-gray-800 border border-gray-700 text-white">
            <input type="text" name="custom_badge_description" maxlength="255"
                   placeholder="Short description shown on profiles"
                   class="w-full p-2 rounded bg-gray-800 border border-gray-700 text-white">
          </div>
          <div class="mt-2">
            <input type="file" name="custom_badge_icon" accept=".png,.jpg,.jpeg,.gif,.avif,.webp"
                   class="block w-full text-sm text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-cyan-600 file:text-white hover:file:bg-cyan-500" />
            <p class="text-xs opacity-60 mt-1">Recommended: square image, ≤ 256 KB.</p>
          </div>
        </div>

        <input type="text" name="casus_belli_custom" id="cb_custom"
               class="mt-2 w-full p-2 rounded bg-gray-800 border border-gray-700 text-white hidden"
               placeholder="Describe your justification…">
      </div>

      <div>
        <span class="block mb-2 text-lg font-title text-cyan-400">Step 4: War Goal</span>
        <p class="text-xs opacity-75 mb-2">
          Only <b>Credits Plundered</b> is supported. Minimum threshold is
          <?php echo number_format(SD_MIN_WAR_THRESHOLD_CREDITS); ?> credits.
        </p>

        <div class="mt-3">
          <label class="block text-sm font-medium text-gray-300">
            Threshold (<span id="goal_threshold_min_label">min <?php echo number_format(SD_MIN_WAR_THRESHOLD_CREDITS); ?></span> for Credits):
            <span id="goal_threshold_value"><?php echo number_format(SD_MIN_WAR_THRESHOLD_CREDITS); ?></span>
          </label>
          <input type="range" id="goal_threshold" name="goal_threshold"
                 min="<?php echo SD_MIN_WAR_THRESHOLD_CREDITS; ?>" max="1000000000" step="1000000" value="<?php echo SD_MIN_WAR_THRESHOLD_CREDITS; ?>"
                 class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer">
          <input type="number" id="goal_threshold_number" name="goal_threshold"
                 class="mt-2 w-full p-2 rounded bg-gray-800 border border-gray-700 text-white"
                 min="<?php echo SD_MIN_WAR_THRESHOLD_CREDITS; ?>" max="1000000000" step="1000000"
                 value="<?php echo SD_MIN_WAR_THRESHOLD_CREDITS; ?>">
          <p class="text-xs text-yellow-300 mt-2">
            Declaring war will cost approximately <strong><?php echo number_format($est_cost); ?></strong> credits (recalculated at submission).
          </p>
        </div>
      </div>

      <div class="pt-2">
        <button type="submit" class="px-4 py-2 rounded bg-cyan-600 hover:bg-cyan-500 text-white font-semibold">
          Declare War
        </button>
      </div>
    </form>
  </div>
</div>

<?php include_once $ROOT . '/template/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Toggle custom fields when casus=custom
  const cbRadios = document.querySelectorAll('input[name="casus_belli"]');
  const cbCustom = document.getElementById('cb_custom');
  const customBadgeBox = document.getElementById('customBadgeBox');

  function updateCB() {
    const checked = document.querySelector('input[name="casus_belli"]:checked');
    const isCustom = checked && checked.value === 'custom';
    cbCustom.classList.toggle('hidden', !isCustom);
    customBadgeBox.classList.toggle('hidden', !isCustom);
    if (isCustom) cbCustom.focus();
  }
  cbRadios.forEach(r => r.addEventListener('change', updateCB));
  updateCB();

  // Goal threshold slider <-> number sync (credits-only)
  const th = document.getElementById('goal_threshold');
  const thNum = document.getElementById('goal_threshold_number');
  const thVal = document.getElementById('goal_threshold_value');
  const minLabel = document.getElementById('goal_threshold_min_label');

  function syncFromSlider() {
    thVal.textContent = parseInt(th.value || '0', 10).toLocaleString();
    thNum.value = th.value;
  }
  function syncFromNumber() {
    th.value = thNum.value;
    thVal.textContent = parseInt(th.value || '0', 10).toLocaleString();
  }
  th.addEventListener('input', syncFromSlider);
  thNum.addEventListener('input', syncFromNumber);

  // Static (credits-only) min label
  minLabel.textContent = 'min ' + (<?php echo SD_MIN_WAR_THRESHOLD_CREDITS; ?>).toLocaleString();
  syncFromSlider();
});
</script>
