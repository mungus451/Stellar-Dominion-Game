<?php
// --- PAGE CONFIGURATION ---
$page_title  = 'Battle – Spy';
$active_page = 'spy.php';

// --- BOOTSTRAP ---
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Services/StateService.php'; // Centralized state
require_once __DIR__ . '/../includes/advisor_hydration.php';

$user_id = (int)($_SESSION['id'] ?? 0);
if ($user_id <= 0) { header('Location: /index.php'); exit; }

// POST -> controller (existing flow)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/SpyController.php';
    exit;
}

// --- PAGE DATA ---
$csrf_intel  = generate_csrf_token('spy_intel');
$csrf_sabo   = generate_csrf_token('spy_sabotage');
$csrf_assas  = generate_csrf_token('spy_assassination');

// current user for sidebar and alliance checks (read-only via StateService)
$me = ss_get_user_state(
    $link,
    $user_id,
    ['id','character_name','level','credits','attack_turns','spies','sentries','last_updated','experience','alliance_id']
);
$my_alliance_id = $me['alliance_id'] ?? null;

// target list (read-only helper adds army_size; excludes self)
$targets = ss_get_targets($link, $user_id, 100);
// Add rivalry flag per target (kept here; app-specific)
foreach ($targets as &$row) {
    $row['is_rival'] = false;
    if ($my_alliance_id && !empty($row['alliance_id']) && $my_alliance_id != $row['alliance_id']) {
        $a1 = (int)$my_alliance_id; $a2 = (int)$row['alliance_id'];
        $sql_rival = "SELECT 1 FROM rivalries
                      WHERE (alliance1_id = ? AND alliance2_id = ?)
                         OR (alliance1_id = ? AND alliance2_id = ?)
                      LIMIT 1";
        if ($stmt_rival = mysqli_prepare($link, $sql_rival)) {
            mysqli_stmt_bind_param($stmt_rival, "iiii", $a1, $a2, $a2, $a1);
            mysqli_stmt_execute($stmt_rival);
            mysqli_stmt_store_result($stmt_rival);
            if (mysqli_stmt_num_rows($stmt_rival) > 0) { $row['is_rival'] = true; }
            mysqli_stmt_close($stmt_rival);
        }
    }
}
unset($row);

// --- HEADER ---
include_once __DIR__ . '/../includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php 
        include_once __DIR__ . '/../includes/advisor.php';
    ?>

    <div class="content-box rounded-lg p-4 stats-container">
        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Spy Stats</h3>
        <ul class="space-y-2 text-sm">
            <li class="flex justify-between"><span>Credits:</span> <span class="text-white font-semibold"><?php echo number_format((int)($me['credits'] ?? 0)); ?></span></li>
            <li class="flex justify-between"><span>Spies:</span> <span class="text-white font-semibold"><?php echo number_format((int)($me['spies'] ?? 0)); ?></span></li>
            <li class="flex justify-between"><span>Sentries:</span> <span class="text-white font-semibold"><?php echo number_format((int)($me['sentries'] ?? 0)); ?></span></li>
            <li class="flex justify-between"><span>Attack Turns:</span> <span class="text-white font-semibold"><?php echo number_format((int)($me['attack_turns'] ?? 0)); ?></span></li>
            <li class="flex justify-between border-t border-gray-600 pt-2 mt-2">
                <span>Next Turn In:</span>
                <span class="text-cyan-300 font-bold"><?php echo sprintf('%02d:%02d', $minutes_until_next_turn, $seconds_remainder); ?></span>
            </li>
        </ul>
    </div>
</aside>

<main class="lg:col-span-3 space-y-4">
    <?php if(isset($_SESSION['spy_message'])): ?>
        <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
            <?php echo htmlspecialchars($_SESSION['spy_message']); unset($_SESSION['spy_message']); ?>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['spy_error'])): ?>
        <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
            <?php echo htmlspecialchars($_SESSION['spy_error']); unset($_SESSION['spy_error']); ?>
        </div>
    <?php endif; ?>

    <div class="content-box rounded-lg p-4 hidden md:block">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-title text-cyan-400">Operative Targets</h3>
            <div class="text-xs text-gray-400">Showing <?php echo count($targets); ?> players</div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-800/60 text-gray-300">
                    <tr>
                        <th class="px-3 py-2 text-left">Rank</th>
                        <th class="px-3 py-2 text-left">Username</th>
                        <th class="px-3 py-2 text-right">Credits</th>
                        <th class="px-3 py-2 text-right">Army Size</th>
                        <th class="px-3 py-2 text-right">Level</th>
                        <th class="px-3 py-2 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php
                    $rank = 1;
                    foreach ($targets as $t):
                        $avatar = $t['avatar_path'] ?: '/assets/img/default_avatar.webp';
                        $tag = !empty($t['alliance_tag']) ? '<span class="alliance-tag">[' . htmlspecialchars($t['alliance_tag']) . ']</span> ' : '';
                        $is_self = ($t['id'] === $user_id);
                        $is_ally = ($my_alliance_id && $my_alliance_id === $t['alliance_id'] && !$is_self);
                        $cant_attack = $is_self || $is_ally;
                    ?>
                    <tr class="<?php echo $is_self ? 'bg-cyan-900/40' : ''; ?>">
                        <td class="px-3 py-3"><?php echo $rank++; ?></td>
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-3">
                                <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" class="w-8 h-8 rounded-md object-cover">
                                <div class="leading-tight">
                                    <div class="text-white font-semibold">
                                        <?php echo $tag . htmlspecialchars($t['character_name']); ?>
                                        <?php if ($t['is_rival']): ?>
                                            <span class="rival-badge">RIVAL</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-[11px] text-gray-400">ID #<?php echo (int)$t['id']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-right text-white"><?php echo number_format((int)$t['credits']); ?></td>
                        <td class="px-3 py-3 text-right text-white"><?php echo number_format((int)$t['army_size']); ?></td>
                        <td class="px-3 py-3 text-right text-white"><?php echo (int)$t['level']; ?></td>
                        <td class="px-3 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <?php if ($cant_attack): ?>
                                    <span class="text-xs font-semibold <?php echo $is_self ? 'text-cyan-400' : 'text-gray-400'; ?>">
                                        <?php echo $is_self ? 'This is you' : 'Ally'; ?>
                                    </span>
                                <?php else: ?>
                                    <form action="/spy.php" method="POST" class="flex items-center gap-1">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_intel, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="csrf_action" value="spy_intel">
                                        <input type="hidden" name="mission_type" value="intelligence">
                                        <input type="hidden" name="defender_id" value="<?php echo (int)$t['id']; ?>">
                                        <input type="number" name="attack_turns" min="1" max="10" value="1" class="w-12 bg-gray-900 border border-gray-600 rounded text-center p-1 text-xs">
                                        <button type="submit" class="bg-cyan-700 hover:bg-cyan-600 text-white text-xs font-semibold py-1 px-2 rounded-md">Spy</button>
                                    </form>
                                    <form action="/spy.php" method="POST" class="flex items-center gap-1">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_sabo, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="csrf_action" value="spy_sabotage">
                                        <input type="hidden" name="mission_type" value="sabotage">
                                        <input type="hidden" name="defender_id" value="<?php echo (int)$t['id']; ?>">
                                        <input type="number" name="attack_turns" min="1" max="10" value="1" class="w-12 bg-gray-900 border border-gray-600 rounded text-center p-1 text-xs">
                                        <button type="submit" class="bg-amber-700 hover:bg-amber-600 text-white text-xs font-semibold py-1 px-2 rounded-md">Sabotage</button>
                                    </form>
                                    <button type="button"
                                            class="open-assass-modal bg-red-700 hover:bg-red-600 text-white text-xs font-semibold py-1 px-2 rounded-md"
                                            data-defender-id="<?php echo (int)$t['id']; ?>"
                                            data-defender-name="<?php echo htmlspecialchars($t['character_name']); ?>">
                                        Assassinate
                                    </button>
                                <?php endif; ?>
                                <form action="/view_profile.php" method="GET" class="inline-block" onsubmit="event.stopPropagation();">
                                    <input type="hidden" name="user" value="<?php echo (int)$t['id']; ?>">
                                    <input type="hidden" name="id"   value="<?php echo (int)$t['id']; ?>">
                                    <button type="submit" class="bg-gray-700 hover:bg-gray-600 text-white text-xs font-semibold py-1 px-2 rounded-md">
                                        View Profile
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; if (empty($targets)): ?>
                    <tr><td colspan="6" class="px-3 py-6 text-center text-gray-400">No targets found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="content-box rounded-lg p-4 md:hidden">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-title text-cyan-400">Operative Targets</h3>
            <div class="text-xs text-gray-400">Showing <?php echo count($targets); ?> players</div>
        </div>

        <div class="space-y-3">
            <?php
            $rank = 1;
            foreach ($targets as $t):
                $avatar = $t['avatar_path'] ?: '/assets/img/default_avatar.webp';
                $tag = !empty($t['alliance_tag']) ? '<span class="alliance-tag">[' . htmlspecialchars($t['alliance_tag']) . ']</span> ' : '';
                $is_self = ($t['id'] === $user_id);
                $is_ally = ($my_alliance_id && $my_alliance_id === $t['alliance_id'] && !$is_self);
                $cant_attack = $is_self || $is_ally;
                // Unique ID for the turn input to link forms
                $mobile_turns_id = "mobile-turns-" . (int)$t['id'];
            ?>
            <div class="bg-gray-900/60 border border-gray-700 rounded-lg p-3 <?php echo $is_self ? 'border-cyan-700' : ''; ?>">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" class="w-10 h-10 rounded-md object-cover">
                        <div>
                            <div class="text-white font-semibold">
                                <?php echo $tag . htmlspecialchars($t['character_name']); ?>
                                <?php if ($t['is_rival']): ?>
                                    <span class="rival-badge">RIVAL</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-[11px] text-gray-400">
                                Rank <?php echo $rank; ?> • Lvl <?php echo (int)$t['level']; ?>
                            </div>
                        </div>
                    </div>
                    <div class="text-right text-xs text-gray-300">
                        <div><span class="text-gray-400">Credits:</span> <span class="text-white font-semibold"><?php echo number_format((int)$t['credits']); ?></span></div>
                        <div><span class="text-gray-400">Army:</span> <span class="text-white font-semibold"><?php echo number_format((int)$t['army_size']); ?></span></div>
                    </div>
                </div>

                <div class="mt-3">
                    <?php if ($cant_attack): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold <?php echo $is_self ? 'text-cyan-400' : 'text-gray-400'; ?>">
                                <?php echo $is_self ? 'This is you' : 'Ally'; ?>
                            </span>
                            <form action="/view_profile.php" method="GET" class="shrink-0">
                                <input type="hidden" name="user" value="<?php echo (int)$t['id']; ?>">
                                <input type="hidden" name="id"   value="<?php echo (int)$t['id']; ?>">
                                <button type="submit" class="bg-gray-700 hover:bg-gray-600 text-white text-xs font-semibold py-1 px-3 rounded-md">
                                    Profile
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center gap-2">
                            <label for="<?php echo $mobile_turns_id; ?>" class="sr-only">Turns</label>
                            <input type="number" id="<?php echo $mobile_turns_id; ?>" min="1" max="10" value="1" 
                                   class="w-16 bg-gray-900 border border-gray-600 rounded text-center p-1 text-xs"
                                   oninput="document.querySelectorAll('.turns-for-<?php echo (int)$t['id']; ?>').forEach(el => el.value = this.value)">
                            <div class="flex-1 flex items-center justify-end gap-2">
                                <form action="/spy.php" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_intel, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="csrf_action" value="spy_intel">
                                    <input type="hidden" name="mission_type" value="intelligence">
                                    <input type="hidden" name="defender_id" value="<?php echo (int)$t['id']; ?>">
                                    <input type="hidden" name="attack_turns" value="1" class="turns-for-<?php echo (int)$t['id']; ?>">
                                    <button type="submit" class="bg-cyan-700 hover:bg-cyan-600 text-white text-xs font-semibold py-1 px-3 rounded-md">Spy</button>
                                </form>
                                <form action="/spy.php" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_sabo, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="csrf_action" value="spy_sabotage">
                                    <input type="hidden" name="mission_type" value="sabotage">
                                    <input type="hidden" name="defender_id" value="<?php echo (int)$t['id']; ?>">
                                    <input type="hidden" name="attack_turns" value="1" class="turns-for-<?php echo (int)$t['id']; ?>">
                                    <button type="submit" class="bg-amber-700 hover:bg-amber-600 text-white text-xs font-semibold py-1 px-3 rounded-md">Sabotage</button>
                                </form>
                                <button type="button"
                                        class="open-assass-modal bg-red-700 hover:bg-red-600 text-white text-xs font-semibold py-1 px-3 rounded-md"
                                        data-defender-id="<?php echo (int)$t['id']; ?>"
                                        data-defender-name="<?php echo htmlspecialchars($t['character_name']); ?>">
                                    Assassinate
                                </button>
                                <form action="/view_profile.php" method="GET" class="shrink-0">
                                    <input type="hidden" name="user" value="<?php echo (int)$t['id']; ?>">
                                    <input type="hidden" name="id"   value="<?php echo (int)$t['id']; ?>">
                                    <button type="submit" class="bg-gray-700 hover:bg-gray-600 text-white text-xs font-semibold py-1 px-3 rounded-md">
                                        Profile
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $rank++;
            endforeach;
            if (empty($targets)):
            ?>
            <div class="text-center text-gray-400 py-6">No targets found.</div>
            <?php endif; ?>
        </div>
    </div>
</main>

<div id="assass-modal"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-4">
  <div class="w-full max-w-md bg-gray-900 border border-gray-700 rounded-lg shadow-xl">
    <div class="px-4 py-3 border-b border-gray-700 flex items-center justify-between">
      <h3 class="font-title text-cyan-400 text-lg">
        Assassination Mission <span class="text-gray-400 text-sm">→ <span id="assass-player-name">Target</span></span>
      </h3>
      <button type="button" id="assass-close-x" class="text-gray-400 hover:text-white">✕</button>
    </div>

    <form id="assass-form" action="/spy.php" method="POST" class="p-4 space-y-4">
      <input type="hidden" name="csrf_token"  value="<?php echo htmlspecialchars($csrf_assas, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="csrf_action" value="spy_assassination">
      <input type="hidden" name="mission_type" value="assassination">
      <input type="hidden" name="defender_id" value="0" id="assass-defender-id">

      <div>
        <label class="block text-sm text-gray-300 mb-2">Choose a target unit type:</label>
        <div class="grid grid-cols-3 gap-2 text-sm">
          <label class="flex items-center gap-2 bg-gray-800 border border-gray-700 rounded-md p-2 cursor-pointer">
            <input type="radio" name="assassination_target" value="workers" class="accent-cyan-500">
            <span>Workers</span>
          </label>
          <label class="flex items-center gap-2 bg-gray-800 border border-gray-700 rounded-md p-2 cursor-pointer">
            <input type="radio" name="assassination_target" value="soldiers" class="accent-cyan-500" checked>
            <span>Soldiers</span>
          </label>
          <label class="flex items-center gap-2 bg-gray-800 border border-gray-700 rounded-md p-2 cursor-pointer">
            <input type="radio" name="assassination_target" value="guards" class="accent-cyan-500">
            <span>Guards</span>
          </label>
        </div>
      </div>

      <div>
        <label for="assass-turns" class="block text-sm text-gray-300 mb-2">Attack Turns (1–10):</label>
        <input id="assass-turns" name="attack_turns" type="number" min="1" max="10" value="1"
               class="w-28 bg-gray-900 border border-gray-600 rounded-md p-2 text-center">
      </div>

      <div class="flex justify-end gap-2 pt-2 border-t border-gray-700">
        <button type="button" id="assass-cancel"
                class="bg-gray-700 hover:bg-gray-600 text-white text-sm font-semibold py-2 px-3 rounded-md">
          Cancel
        </button>
        <button type="submit"
                class="bg-red-700 hover:bg-red-600 text-white text-sm font-bold py-2 px-3 rounded-md">
          Confirm Assassination
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const modal   = document.getElementById('assass-modal');
  const form    = document.getElementById('assass-form');
  const idInput = document.getElementById('assass-defender-id');
  const nameEl  = document.getElementById('assass-player-name');
  const closeX  = document.getElementById('assass-close-x');
  const cancel  = document.getElementById('assass-cancel');

  function openModal(defenderId, defenderName) {
    idInput.value = defenderId;
    nameEl.textContent = defenderName || 'Target';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.classList.add('overflow-hidden');
  }
  function closeModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
  }

  // Open from each row button
  document.querySelectorAll('.open-assass-modal').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      openModal(btn.dataset.defenderId, btn.dataset.defenderName);
    });
  });

  // Close behaviors
  closeX.addEventListener('click', closeModal);
  cancel.addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(); // click backdrop to close
  });
})();
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>