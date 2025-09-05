<?php
// template/pages/war_declaration.php

if (session_status() === PHP_SESSION_NONE) session_start();

$active_page = 'war_declaration.php';
$ROOT = dirname(__DIR__, 2);

require_once $ROOT . '/config/config.php';
require_once $ROOT . '/src/Game/GameData.php';
require_once $ROOT . '/src/Game/GameFunctions.php';

// ── Authz: leaders/officers only (hierarchy 1 or 2)
$user_id = (int)($_SESSION['id'] ?? 0);
if ($user_id <= 0) { header('Location: /index.html'); exit; }

$sql = "SELECT u.alliance_id, ar.`order` as hierarchy
        FROM users u
        JOIN alliance_roles ar ON u.alliance_role_id = ar.id
        WHERE u.id = ?";
$stmt = $link->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result    = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

if (!$user_data || !in_array((int)$user_data['hierarchy'], [1, 2], true)) {
    $_SESSION['alliance_error'] = "You do not have the required permissions to declare war.";
    header("Location: /alliance");
    exit;
}

// ── Alliances list (exclude own)
$sql_alliances = "SELECT id, name, tag FROM alliances WHERE id != ?";
$stmt_alliances = $link->prepare($sql_alliances);
$stmt_alliances->bind_param("i", $user_data['alliance_id']);
$stmt_alliances->execute();
$alliances = $stmt_alliances->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_alliances->close();

// CSRF
$csrf_token_war     = generate_csrf_token('war_declare');
$csrf_token_rivalry = generate_csrf_token('rivalry_declare');

// Page chrome
$page_title = 'Starlight Dominion - War Declaration';
include $ROOT . '/template/includes/header.php';
?>

<!-- NOTE: We rely on the 4-col grid from header.php.
     Use col spans to position sidebar and main. -->

<!-- Sidebar (left) -->
<aside class="lg:col-span-1 space-y-4">
  <?php $advisor = $ROOT . '/template/includes/advisor.php'; if (is_file($advisor)) include $advisor; ?>
</aside>

<!-- Main (center column = spans 3 of 4 cols) -->
<div class="lg:col-span-3">
  <?php if (!empty($_SESSION['alliance_error'])): ?>
    <div class="content-box text-red-200 border-red-600/60 p-3 rounded-md text-center mb-4">
      <?= htmlspecialchars($_SESSION['alliance_error']); unset($_SESSION['alliance_error']); ?>
    </div>
  <?php endif; ?>

  <!-- Center the card inside the wide main column -->
  <div class="content-box rounded-lg p-6 w-full max-w-4xl mx-auto">
    <h1 class="font-title text-3xl text-white mb-4 border-b border-gray-700 pb-3">Initiate Hostilities</h1>

    <form id="warForm" action="/war_declaration.php" method="POST" class="space-y-6">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_war) ?>">
      <input type="hidden" name="csrf_action" value="war_declare">
      <input type="hidden" name="action" value="declare_war">

      <!-- Step 1: Name -->
      <div>
        <label for="war_name" class="block mb-2 text-lg font-title text-cyan-400">Step 1: Name Your War</label>
        <input type="text" id="war_name" name="war_name"
               class="w-full px-3 py-2 text-gray-300 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:border-cyan-500"
               placeholder="e.g., The Great Expansion" required>
      </div>

      <!-- Step 2: Target -->
      <div>
        <label for="alliance_id" class="block mb-2 text-lg font-title text-cyan-400">Step 2: Select Target</label>
        <select id="alliance_id" name="alliance_id" required
                class="w-full px-3 py-2 text-gray-300 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:border-cyan-500">
          <option value="" disabled selected>Choose an alliance to declare war upon...</option>
          <?php foreach ($alliances as $alliance): ?>
            <option value="<?= (int)$alliance['id'] ?>">
              [<?= htmlspecialchars($alliance['tag']) ?>] <?= htmlspecialchars($alliance['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Step 3: Casus Belli -->
      <div>
        <span class="block mb-2 text-lg font-title text-cyan-400">Step 3: Justify Your War (Casus Belli)</span>
        <div class="grid md:grid-cols-2 gap-3">
          <label class="flex items-start gap-2 bg-gray-900/60 border border-gray-700 rounded p-3">
            <input type="radio" name="casus_belli" value="humiliation" required>
            <span>
              <span class="font-semibold text-white">Humiliation</span><br>
              <span class="text-xs text-gray-400">Losing alliance is humiliated; loss posted on public profile until they win a war to remove it.</span>
            </span>
          </label>

          <label class="flex items-start gap-2 bg-gray-900/60 border border-gray-700 rounded p-3">
            <input type="radio" name="casus_belli" value="dignity">
            <span>
              <span class="font-semibold text-white">Dignity</span><br>
              <span class="text-xs text-gray-400">Erases Humiliation if you win; if you lose, an additional loss is posted to your profile.</span>
            </span>
          </label>

          <label class="flex items-start gap-2 bg-gray-900/60 border border-gray-700 rounded p-3">
            <input type="radio" name="casus_belli" value="economic_vassal">
            <span>
              <span class="font-semibold text-white">Economic Vassal</span><br>
              <span class="text-xs text-gray-400">Losing alliance pays half of their battle tax to winner until a successful Revolution.</span>
            </span>
          </label>

          <label class="flex items-start gap-2 bg-gray-900/60 border border-gray-700 rounded p-3">
            <input type="radio" name="casus_belli" value="revolution">
            <span>
              <span class="font-semibold text-white">Revolution</span><br>
              <span class="text-xs text-gray-400">War to end Economic Vassalage.</span>
            </span>
          </label>

          <label class="flex items-start gap-2 bg-gray-900/60 border border-gray-700 rounded p-3 md:col-span-2">
            <input id="cb-custom" type="radio" name="casus_belli" value="custom">
            <span class="w-full">
              <span class="font-semibold text-white">Custom</span><br>
              <span class="text-xs text-gray-400">Leader-entered reason; if opponent loses, a permanent badge with this text appears on their profile.</span>
              <textarea id="custom_casus_belli" name="custom_casus_belli" rows="2" maxlength="244"
                        class="hidden mt-2 w-full px-3 py-2 text-gray-300 bg-gray-900 border border-gray-600 rounded-lg"
                        placeholder="Enter up to 244 characters"></textarea>
            </span>
          </label>
        </div>
      </div>

      <!-- Step 4: Goals (composite allowed; sliders) -->
      <div>
        <span class="block mb-2 text-lg font-title text-cyan-400">Step 4: Define Your War Goals</span>
        <p class="text-xs text-gray-500 mb-3">You can set any combination. A war marked as composite requires meeting <em>all</em> non-zero thresholds to claim victory.</p>
        <div class="space-y-4">
          <div>
            <label for="goal_credits_plundered" class="block text-sm font-medium text-gray-300">
              Credits Plundered: <span id="goal_credits_plundered_value">0</span>
            </label>
            <input type="range" id="goal_credits_plundered" name="goal_credits_plundered"
                   min="0" max="1000000000" step="1000000" value="0"
                   class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer">
          </div>

          <div>
            <label for="goal_units_killed" class="block text-sm font-medium text-gray-300">
              Guards/Units Killed: <span id="goal_units_killed_value">0</span>
            </label>
            <input type="range" id="goal_units_killed" name="goal_units_killed"
                   min="0" max="100000" step="100" value="0"
                   class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer">
          </div>

          <div>
            <label for="goal_units_assassinated" class="block text-sm font-medium text-gray-300">
              Units Assassinated: <span id="goal_units_assassinated_value">0</span>
            </label>
            <input type="range" id="goal_units_assassinated" name="goal_units_assassinated"
                   min="0" max="100000" step="10" value="0"
                   class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer">
          </div>

          <div>
            <label for="goal_structure_damage" class="block text-sm font-medium text-gray-300">
              Total Structure Damage: <span id="goal_structure_damage_value">0</span>
            </label>
            <input type="range" id="goal_structure_damage" name="goal_structure_damage"
                   min="0" max="10000000" step="1000" value="0"
                   class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer">
          </div>

          <div>
            <label for="goal_prestige_change" class="block text-sm font-medium text-gray-300">
              Prestige Gained: <span id="goal_prestige_change_value">0</span>
            </label>
            <input type="range" id="goal_prestige_change" name="goal_prestige_change"
                   min="0" max="5000" step="10" value="0"
                   class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer">
          </div>
        </div>
      </div>

      <div class="border-t border-gray-700 pt-4 text-center">
        <button type="submit"
                class="w-full md:w-auto px-10 py-3 font-bold text-white bg-red-700 rounded-lg hover:bg-red-800 text-xl font-title tracking-wider">
          Declare War
        </button>
      </div>
    </form>

    <!-- Rivalry -->
    <h1 class="font-title text-3xl text-white mb-4 border-b border-gray-700 pb-3 mt-8">Declare Rivalry</h1>
    <form id="rivalryForm" action="/war_declaration.php" method="POST" class="space-y-6">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_rivalry) ?>">
      <input type="hidden" name="csrf_action" value="rivalry_declare">
      <input type="hidden" name="action" value="declare_rivalry">

      <div>
        <label for="rival_alliance_id" class="block mb-2 text-lg font-title text-cyan-400">Select Target Alliance</label>
        <select id="rival_alliance_id" name="alliance_id" required
                class="w-full px-3 py-2 text-gray-300 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:border-cyan-500">
          <option value="" disabled selected>Choose an alliance to declare rivalry upon...</option>
          <?php foreach ($alliances as $alliance): ?>
            <option value="<?= (int)$alliance['id'] ?>">
              [<?= htmlspecialchars($alliance['tag']) ?>] <?= htmlspecialchars($alliance['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="border-t border-gray-700 pt-4 text-center">
        <button type="submit"
                class="w-full md:w-auto px-10 py-3 font-bold text-white bg-yellow-700 rounded-lg hover:bg-yellow-800 text-xl font-title tracking-wider">
          Declare Rivalry
        </button>
      </div>
    </form>
  </div>
</div>

<?php include $ROOT . '/template/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Show/hide custom casus belli
  const customText  = document.getElementById('custom_casus_belli');
  document.querySelectorAll('input[name="casus_belli"]').forEach(r => {
    r.addEventListener('change', () => {
      if (r.value === 'custom' && r.checked) {
        customText.classList.remove('hidden');
        customText.required = true;
      } else if (r.checked) {
        customText.classList.add('hidden');
        customText.required = false;
        customText.value = '';
      }
    });
  });

  // Slider value mirrors
  const sliders = [
    { id: 'goal_credits_plundered',   valueId: 'goal_credits_plundered_value' },
    { id: 'goal_units_killed',        valueId: 'goal_units_killed_value' },
    { id: 'goal_units_assassinated',  valueId: 'goal_units_assassinated_value' },
    { id: 'goal_structure_damage',    valueId: 'goal_structure_damage_value' },
    { id: 'goal_prestige_change',     valueId: 'goal_prestige_change_value' },
  ];
  sliders.forEach(({id, valueId}) => {
    const el = document.getElementById(id);
    const out = document.getElementById(valueId);
    const sync = () => out.textContent = parseInt(el.value || '0', 10).toLocaleString();
    if (el) { el.addEventListener('input', sync); sync(); }
  });
});
</script>
