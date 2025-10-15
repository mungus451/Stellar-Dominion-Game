<?php
// template/pages/war_declaration.php
// Timed wars UI (Skirmish=24h, War=48h). Alliance-vs-Alliance ONLY (PvP paused).
// - Uses /api/war_declare.php endpoint.
// - Validates with CSRFProtection; forwards same token to API (and sets $_SESSION['csrf_token'] to satisfy API fallback).
// - Preserves overall layout styles; replaces the old goal slider with War Type radios.
// - Optional Custom War Badge when casus_belli=custom (uploads icon here, passes path to API).
// - Prevents duplicate active wars before hitting the API for better UX.

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.html"); exit; }

$active_page = 'war_declaration.php';
$ROOT = dirname(__DIR__, 2);

require_once $ROOT . '/config/config.php';
require_once $ROOT . '/src/Security/CSRFProtection.php';
require_once $ROOT . '/src/Game/GameData.php';
require_once $ROOT . '/src/Game/GameFunctions.php';
require_once $ROOT . '/template/includes/advisor_hydration.php';

function sd_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function sd_get_user_perms(mysqli $db, int $user_id): ?array {
    $sql = "SELECT u.alliance_id, ar.`order` AS hierarchy, u.character_name
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

function sd_alliance_by_id(mysqli $db, int $id): ?array {
    $st = $db->prepare("SELECT id, name, tag FROM alliances WHERE id=?");
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function sd_user_by_id(mysqli $db, int $id): ?array {
    $st = $db->prepare("SELECT id, character_name FROM users WHERE id=?");
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function sd_active_war_exists_alliance(mysqli $db, int $a, int $b): bool {
    $sql = "SELECT id FROM wars
            WHERE status='active' AND scope='alliance'
              AND (
                   (declarer_alliance_id=? AND declared_against_alliance_id=?)
                OR (declarer_alliance_id=? AND declared_against_alliance_id=?)
              )
            LIMIT 1";
    $st = $db->prepare($sql);
    $st->bind_param('iiii', $a, $b, $b, $a);
    $st->execute();
    $ok = (bool)$st->get_result()->fetch_row();
    $st->close();
    return $ok;
}

function sd_active_war_exists_player(mysqli $db, int $u1, int $u2): bool {
    // kept for future PvP re-enable; not used while PvP paused
    $sql = "SELECT id FROM wars
            WHERE status='active' AND scope='player'
              AND (
                   (declarer_user_id=? AND declared_against_user_id=?)
                OR (declarer_user_id=? AND declared_against_user_id=?)
              )
            LIMIT 1";
    $st = $db->prepare($sql);
    $st->bind_param('iiii', $u1, $u2, $u2, $u1);
    $st->execute();
    $ok = (bool)$st->get_result()->fetch_row();
    $st->close();
    return $ok;
}

// --- state / auth ---
$user_id = (int)($_SESSION['id'] ?? 0);
$me      = sd_get_user_perms($link, $user_id);
if (!$me) { $_SESSION['war_message'] = 'User not found.'; header('Location: /realm_war.php'); exit; }

$my_alliance_id = (int)($me['alliance_id'] ?? 0);
$my_hierarchy   = (int)($me['hierarchy'] ?? 999);

$errors = [];
$success = "";

// --- handle POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!CSRFProtection::getInstance()->validateToken($token, 'war_declare')) {
            throw new Exception('Security check failed. Please try again.');
        }

        $war_name = trim((string)($_POST['war_name'] ?? ''));
        if ($war_name === '') { $war_name = 'Unnamed Conflict'; }

        // PvP paused: force alliance scope, reject any 'player'
        $posted_scope = strtolower(trim((string)($_POST['scope'] ?? 'alliance')));
        if ($posted_scope !== 'alliance') {
            throw new Exception('Player-vs-Player declarations are temporarily disabled.');
        }
        $scope = 'alliance';

        $war_type = strtolower(trim((string)($_POST['war_type'] ?? 'skirmish')));
        if (!in_array($war_type, ['skirmish','war'], true)) { throw new Exception('Invalid war type.'); }

        $cb_key   = strtolower(trim((string)($_POST['casus_belli'] ?? 'humiliation')));
        if (!in_array($cb_key, ['humiliation','dignity','custom'], true)) { $cb_key = 'humiliation'; }
        if ($cb_key === 'custom') { throw new Exception('Custom Casus Belli is currently unavailable.'); } // Server-side check
        $cb_custom = null;

        // Alliance perms only (leader/diplomat roles 1 or 2)
        if ($my_alliance_id <= 0) { throw new Exception('You must belong to an alliance to declare an alliance war.'); }
        if (!in_array($my_hierarchy, [1,2], true)) {
            throw new Exception('Only alliance leaders or diplomats can declare alliance wars.');
        }

        // Optional Custom Badge (when casus=custom) — disabled above, but keep code for future
        $customBadgeName = null;
        $customBadgeDesc = null;
        $customBadgePath = null;

        // Opponent selection — Alliance only
        $targetAllianceId = (int)($_POST['alliance_id'] ?? 0);
        if ($targetAllianceId <= 0) { throw new Exception('Please choose a target alliance.'); }
        if ($my_alliance_id > 0 && $targetAllianceId === $my_alliance_id) { throw new Exception('You cannot declare war on your own alliance.'); }

        // Early duplicate check (UX)
        if (sd_active_war_exists_alliance($link, $my_alliance_id, $targetAllianceId)) {
            throw new Exception('There is already an active alliance war between these alliances.');
        }
        // Validate target exists
        if (!sd_alliance_by_id($link, $targetAllianceId)) {
            throw new Exception('Target alliance not found.');
        }

        // Prepare CSRF for API fallback (single-use token in $_SESSION['csrf_token'])
        $_SESSION['csrf_token'] = $token;

        // --- Build API payload (Alliance-only)
        $payload = [
            'action'                   => 'declare_war',
            'csrf_token'               => $token,
            'initiator_user_id'        => $user_id,          // harmless if API derives from session
            'scope'                    => 'alliance',
            'war_type'                 => $war_type,
            'name'                     => $war_name,
            'casus_belli_key'          => $cb_key,
            'casus_belli_custom'       => $cb_custom,
            'custom_badge_name'        => $customBadgeName,
            'custom_badge_description' => $customBadgeDesc,
            'custom_badge_icon_path'   => $customBadgePath,
            'target_alliance_id'       => $targetAllianceId,
            'declared_against_alliance_id' => $targetAllianceId, // compatibility
        ];

        // POST to API with current session
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $url    = $scheme . '://' . $host . '/api/war_declare.php';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Expect:',
            'Accept: application/json'
        ]);
        // Carry PHP session to API
        curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . session_id());
        $resp = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            throw new Exception('API request failed: ' . ($curlErr ?: 'unknown error'));
        }
        $json = json_decode($resp, true);
        if (!is_array($json)) {
            error_log('[war_declaration] Unexpected API response: ' . substr($resp, 0, 500));
            throw new Exception('Unexpected API response.');
        }
        if (empty($json['ok'])) {
            $apiErr = isset($json['error']) ? (string)$json['error'] : 'Declaration failed.';
            error_log('[war_declaration] API error: ' . $apiErr . ' | scope=alliance target=' . $targetAllianceId);
            throw new Exception($apiErr);
        }

        $endHuman = isset($json['end_date']) ? date('Y-m-d H:i', strtotime($json['end_date'] . ' UTC')) . ' UTC' : 'scheduled';
        $_SESSION['war_message'] = 'War declared successfully. Ends: ' . $endHuman . '.';
        header('Location: /realm_war.php'); exit;

    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

// --- page data ---
$alliances = [];
if ($my_alliance_id > 0) {
    $st = $link->prepare("SELECT id, name, tag FROM alliances WHERE id <> ? ORDER BY name ASC");
    $st->bind_param('i', $my_alliance_id);
    $st->execute();
    $alliances = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
}

$page_title = 'Declare War - Starlight Dominion';
include_once $ROOT . '/template/includes/header.php';
?>
<div class="lg:col-span-4 space-y-6">

    <?php if (!empty($errors)): ?>
    <div class="rounded-lg border border-rose-500/30 bg-rose-950 text-rose-200 p-4">
        <h3 class="font-medium text-white">Declaration Failed</h3>
        <div class="mt-2 text-sm text-rose-200">
            <ul role="list" class="list-disc space-y-1 pl-5">
            <?php foreach ($errors as $err): ?>
                <li><?php echo sd_h($err); ?></li>
            <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <div class="bg-slate-900 rounded-xl shadow-lg border border-slate-700">
        <div class="p-4 sm:p-6 border-b border-slate-700">
            <h2 class="font-title text-2xl text-white">Declare a Realm War</h2>
            <p class="text-slate-400 mt-1">
                Initiate a timed conflict. Victory is determined by a composite score, with defenders receiving a 3% advantage.
            </p>
        </div>

        <form id="warForm" action="/war_declaration.php" method="POST" enctype="multipart/form-data">
            <div class="p-4 sm:p-6 space-y-8">
                <?php echo CSRFProtection::getInstance()->getTokenField('war_declare'); ?>
                <input type="hidden" name="action" value="declare_war" />

                <div class="space-y-2">
                    <h3 class="text-lg font-title text-sky-400">Step 1: Name the War</h3>
                    <input type="text" name="war_name" maxlength="100" class="block w-full rounded-md border-0 bg-slate-800 py-2 px-3 text-white shadow-sm ring-1 ring-inset ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-sky-500" placeholder="e.g., The Orion Conflict">
                </div>

                <div class="space-y-4 border-t border-slate-700/60 pt-6">
                    <h3 class="text-lg font-title text-sky-400">Step 2: Choose Scope & Opponent</h3>
                    <fieldset>
                        <legend class="sr-only">War Scope</legend>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label for="scope_alliance" class="relative flex cursor-pointer rounded-lg border bg-slate-800/50 p-4 shadow-sm focus:outline-none ring-2 ring-transparent peer-checked:ring-sky-500 border-slate-700 hover:bg-slate-800">
                                <input type="radio" name="scope" value="alliance" id="scope_alliance" class="sr-only peer" checked>
                                <span class="flex-1 flex">
                                    <span class="flex flex-col">
                                        <span class="block text-sm font-medium text-white">Alliance vs Alliance</span>
                                        <span class="mt-1 flex items-center text-xs text-slate-400">Declare war on another alliance.</span>
                                    </span>
                                </span>
                            </label>
                            <label for="scope_player" class="relative flex cursor-not-allowed rounded-lg border bg-slate-800/50 p-4 shadow-sm border-slate-700 opacity-60">
                                <input type="radio" name="scope" value="player" id="scope_player" class="sr-only peer" disabled>
                                <span class="flex-1 flex">
                                    <span class="flex flex-col">
                                        <span class="block text-sm font-medium text-white">Player vs Player</span>
                                        <span class="mt-1 flex items-center text-xs text-slate-400">Temporarily unavailable.</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    <div id="scope_alliance_box">
                        <select name="alliance_id" class="block w-full rounded-md border-0 bg-slate-800 py-2 px-3 text-white shadow-sm ring-1 ring-inset ring-slate-700 focus:ring-2 focus:ring-inset focus:ring-sky-500">
                            <option value="">— Select Target Alliance —</option>
                            <?php foreach ($alliances as $a): ?>
                            <option value="<?php echo (int)$a['id']; ?>">[<?php echo sd_h($a['tag']); ?>] <?php echo sd_h($a['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-500 mt-2">Requires Leader or Diplomat role in your alliance.</p>
                    </div>

                    <div id="scope_player_box" class="hidden">
                        <input type="number" name="target_user_id" placeholder="Target Player ID (e.g., 12345)" class="block w-full rounded-md border-0 bg-slate-800 py-2 px-3 text-white shadow-sm ring-1 ring-inset ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-sky-500" disabled>
                        <p class="text-xs text-slate-500 mt-2">Player vs Player is temporarily unavailable.</p>
                    </div>
                </div>

                <div class="space-y-4 border-t border-slate-700/60 pt-6">
                    <h3 class="text-lg font-title text-sky-400">Step 3: Justification (Casus Belli)</h3>
                     <fieldset>
                        <legend class="sr-only">Casus Belli</legend>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <label for="cb_humiliation" class="relative flex cursor-pointer rounded-lg border bg-slate-800/50 p-3 shadow-sm focus:outline-none ring-2 ring-transparent peer-checked:ring-sky-500 border-slate-700 hover:bg-slate-800 text-center justify-center">
                                <input type="radio" name="casus_belli" value="humiliation" id="cb_humiliation" class="sr-only peer" checked>
                                <span class="text-sm font-medium text-white">Humiliation</span>
                            </label>
                             <label for="cb_dignity" class="relative flex cursor-pointer rounded-lg border bg-slate-800/50 p-3 shadow-sm focus:outline-none ring-2 ring-transparent peer-checked:ring-sky-500 border-slate-700 hover:bg-slate-800 text-center justify-center">
                                <input type="radio" name="casus_belli" value="dignity" id="cb_dignity" class="sr-only peer">
                                <span class="text-sm font-medium text-white">Restore Dignity</span>
                            </label>
                             <label for="cb_custom_radio" class="relative flex rounded-lg border bg-slate-800/50 p-3 shadow-sm border-slate-700 text-center justify-center opacity-60 cursor-not-allowed">
                                <input type="radio" name="casus_belli" value="custom" id="cb_custom_radio" class="sr-only peer" disabled>
                                <span class="text-sm font-medium text-white">Custom…</span>
                            </label>
                        </div>
                    </fieldset>

                    <input type="text" name="casus_belli_custom" id="cb_custom" class="hidden w-full rounded-md border-0 bg-slate-800 py-2 px-3 text-white shadow-sm ring-1 ring-inset ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-sky-500" placeholder="Describe your justification…">

                    <div id="customBadgeBox" class="hidden space-y-4 pt-4 border-t border-slate-700/60">
                        <h4 class="text-md font-medium text-white">Optional: Custom War Badge</h4>
                        <p class="text-sm text-slate-400">If you win, the loser’s members will receive this badge. A mark of their defeat.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                             <input type="text" name="custom_badge_name" maxlength="100" placeholder="Badge Name (e.g., Mark of Orion)" class="block w-full rounded-md border-0 bg-slate-800 py-2 px-3 text-white shadow-sm ring-1 ring-inset ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-sky-500">
                             <input type="text" name="custom_badge_description" maxlength="255" placeholder="Short Description" class="block w-full rounded-md border-0 bg-slate-800 py-2 px-3 text-white shadow-sm ring-1 ring-inset ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-sky-500">
                        </div>
                        <div>
                             <input type="file" name="custom_badge_icon" accept=".png,.jpg,.jpeg,.gif,.avif,.webp" class="block w-full text-sm text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm font-semibold file:bg-sky-600/50 file:text-sky-200 hover:file:bg-sky-600/80" />
                            <p class="text-xs text-slate-500 mt-2">Recommended: square image, 256KB max.</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 border-t border-slate-700/60 pt-6">
                     <h3 class="text-lg font-title text-sky-400">Step 4: War Type</h3>
                     <fieldset>
                        <legend class="sr-only">War Type</legend>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label for="type_skirmish" class="relative flex cursor-pointer rounded-lg border bg-slate-800/50 p-4 shadow-sm focus:outline-none ring-2 ring-transparent peer-checked:ring-sky-500 border-slate-700 hover:bg-slate-800">
                                <input type="radio" name="war_type" value="skirmish" id="type_skirmish" class="sr-only peer" checked>
                                <span class="flex-1 flex">
                                    <span class="flex flex-col">
                                        <span class="block text-sm font-medium text-white">Skirmish</span>
                                        <span class="mt-1 flex items-center text-xs text-slate-400">A brief, 24-hour conflict.</span>
                                    </span>
                                </span>
                            </label>
                            <label for="type_war" class="relative flex cursor-pointer rounded-lg border bg-slate-800/50 p-4 shadow-sm focus:outline-none ring-2 ring-transparent peer-checked:ring-sky-500 border-slate-700 hover:bg-slate-800">
                                <input type="radio" name="war_type" value="war" id="type_war" class="sr-only peer">
                                <span class="flex-1 flex">
                                    <span class="flex flex-col">
                                        <span class="block text-sm font-medium text-white">War</span>
                                        <span class="mt-1 flex items-center text-xs text-slate-400">A full-scale, 48-hour engagement.</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                     </fieldset>
                </div>
            </div>

            <div class="flex items-center justify-end gap-x-6 border-t border-slate-700 p-4 sm:px-6">
                <button type="submit" class="rounded-md bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600">
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
    const cbCustomInput = document.getElementById('cb_custom');
    const customBadgeBox = document.getElementById('customBadgeBox');

    function updateCB() {
        const checked = document.querySelector('input[name="casus_belli"]:checked');
        const isCustom = checked && checked.value === 'custom';
        cbCustomInput.classList.toggle('hidden', !isCustom);
        customBadgeBox.classList.toggle('hidden', !isCustom);
        cbCustomInput.required = !!isCustom;
    }
    cbRadios.forEach(r => r.addEventListener('change', updateCB));
    updateCB();

    // Scope: lock to Alliance (PvP paused)
    const scopeAlliance = document.getElementById('scope_alliance');
    const scopePlayer   = document.getElementById('scope_player');
    const boxAlliance   = document.getElementById('scope_alliance_box');
    const boxPlayer     = document.getElementById('scope_player_box');
    const allianceSelect = boxAlliance.querySelector('select');
    const playerInput    = boxPlayer.querySelector('input');

    if (scopePlayer) scopePlayer.disabled = true;
    if (scopeAlliance && !scopeAlliance.checked) scopeAlliance.checked = true;

    function updateScope() {
        const theIsAlliance = true; // force alliance view
        boxAlliance.classList.toggle('hidden', !theIsAlliance);
        boxPlayer.classList.toggle('hidden', theIsAlliance);
        allianceSelect.required = true;
        if (playerInput) playerInput.required = false;
    }

    // Keep listeners (DOM unchanged), but selection can't move off alliance
    document.querySelectorAll('input[name="scope"]').forEach(r => r.addEventListener('change', updateScope));
    updateScope();
});
</script>
