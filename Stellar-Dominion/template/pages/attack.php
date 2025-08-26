<?php
// --- PAGE CONFIGURATION ---
$page_title  = 'Battle – Attack';
$active_page = 'attack.php';

// --- BOOTSTRAP (router already started session + auth) ---
date_default_timezone_set('UTC');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php';
require_once __DIR__ . '/../../src/Game/GameFunctions.php';

$user_id = (int)($_SESSION['id'] ?? 0);
if ($user_id <= 0) { header('Location: /index.php'); exit; }

// Handle POST via controller (unchanged app flow)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/AttackController.php';
    exit;
}

// Keep user state fresh
process_offline_turns($link, $user_id);

// --- PAGE DATA ---
$csrf_token = generate_csrf_token('attack');

// current user row (for timers/advisor)
// MODIFICATION: Added `alliance_id` to check for rivalries later.
$sql_me = "SELECT id, character_name, level, credits, banked_credits, attack_turns, last_updated, experience, alliance_id
           FROM users WHERE id = ?";
$stmt_me = mysqli_prepare($link, $sql_me);
mysqli_stmt_bind_param($stmt_me, "i", $user_id);
mysqli_stmt_execute($stmt_me);
$me = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_me)) ?: [];
mysqli_stmt_close($stmt_me);
// NEW: Store the current user's alliance ID for comparison.
$my_alliance_id = $me['alliance_id'] ?? null;

// target list (now with alliance tag + avatar)
// MODIFICATION: Added `u.alliance_id` to the SELECT list for the rivalry check.
$sql_targets = "
    SELECT
        u.id, u.character_name, u.level, u.credits, u.avatar_path,
        u.soldiers, u.guards, u.sentries, u.spies, u.alliance_id,
        a.tag   AS alliance_tag
    FROM users u
    LEFT JOIN alliances a ON a.id = u.alliance_id
    WHERE u.id <> ?
    ORDER BY u.level DESC, u.credits DESC
    LIMIT 100
";
$stmt_t = mysqli_prepare($link, $sql_targets);
mysqli_stmt_bind_param($stmt_t, "i", $user_id);
mysqli_stmt_execute($stmt_t);
$targets_rs = mysqli_stmt_get_result($stmt_t);
$targets = [];
while ($row = mysqli_fetch_assoc($targets_rs)) {
    // Show “Army Size” as military only (soldiers + guards + sentries + spies)
    $row['army_size'] = (int)$row['soldiers'] + (int)$row['guards'] + (int)$row['sentries'] + (int)$row['spies'];

    // --- NEW: Rivalry Check Logic ---
    $row['is_rival'] = false;
    // Only perform the check if both the user and the target are in an alliance, and they are not the SAME alliance.
    if ($my_alliance_id && $row['alliance_id'] && $my_alliance_id != $row['alliance_id']) {
        $a1 = $my_alliance_id;
        $a2 = $row['alliance_id'];
        // This query checks if an entry exists in the `rivalries` table for the two alliances.
        $sql_rival = "SELECT 1 FROM rivalries WHERE (alliance1_id = ? AND alliance2_id = ?) OR (alliance1_id = ? AND alliance2_id = ?) LIMIT 1";
        if ($stmt_rival = mysqli_prepare($link, $sql_rival)) {
            mysqli_stmt_bind_param($stmt_rival, "iiii", $a1, $a2, $a2, $a1);
            mysqli_stmt_execute($stmt_rival);
            mysqli_stmt_store_result($stmt_rival);
            if (mysqli_stmt_num_rows($stmt_rival) > 0) {
                $row['is_rival'] = true; // Set the flag to true if a rivalry is found.
            }
            mysqli_stmt_close($stmt_rival);
        }
    }
    // --- END: Rivalry Check ---

    $targets[] = $row;
}
mysqli_stmt_close($stmt_t);

// Timers
$turn_interval_minutes = 10;
$last_updated = new DateTime($me['last_updated'] ?? gmdate('Y-m-d H:i:s'), new DateTimeZone('UTC'));
$now = new DateTime('now', new DateTimeZone('UTC'));
$interval = $turn_interval_minutes * 60;
$elapsed  = $now->getTimestamp() - $last_updated->getTimestamp();
$seconds_until_next_turn = $interval - ($elapsed % $interval);
if ($seconds_until_next_turn < 0) $seconds_until_next_turn = 0;
$minutes_until_next_turn = (int)floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;

// --- HEADER ---
include_once __DIR__ . '/../includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php 
        // Values visible to the advisor include
        $user_xp    = (int)($me['experience'] ?? 0);
        $user_level = (int)($me['level'] ?? 1);
        // ($minutes_until_next_turn, $seconds_remainder, $now) already defined above
        include_once __DIR__ . '/../includes/advisor.php';
    ?>
    </aside>

<main class="lg:col-span-3 space-y-4">
    <?php if(isset($_SESSION['attack_message'])): ?>
        <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
            <?php echo htmlspecialchars($_SESSION['attack_message']); unset($_SESSION['attack_message']); ?>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['attack_error'])): ?>
        <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
            <?php echo htmlspecialchars($_SESSION['attack_error']); unset($_SESSION['attack_error']); ?>
        </div>
    <?php endif; ?>

    <?php
        // Help values
        $attack_turns_current = (int)($me['attack_turns'] ?? 0);
        $turns_per_cycle      = 2; // game rule shown in help text
        $turn_interval_min    = (int)$turn_interval_minutes; // 10
    ?>

    <div class="content-box rounded-lg p-4" id="attack-help-card" data-state="expanded">
        <div class="flex items-start justify-between border-b border-gray-600 pb-2 mb-2">
            <h3 class="font-title text-cyan-400 flex items-center gap-2">
                <i data-lucide="info" class="w-5 h-5"></i>
                How Attacks Work
            </h3>
            <button type="button"
                    id="attack-help-toggle"
                    class="text-xs bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-md px-2 py-1">
                Hide
            </button>
        </div>

        <div id="attack-help-content" class="text-sm space-y-2 md:block">
            <p class="text-gray-300">
                Pick a target and the number of <strong>Attack Turns</strong> to spend (1–10). Spending more turns
                generally increases your odds and potential rewards on a victory.
            </p>
            <ul class="text-gray-200 list-disc pl-5 space-y-1">
                <li>
                    <strong>Attack Turns:</strong>
                    You currently have <span class="font-semibold text-white"><?php echo number_format($attack_turns_current); ?></span>.
                    Turns regenerate automatically at a rate of <strong>+<?php echo $turns_per_cycle; ?></strong>
                    every <strong><?php echo $turn_interval_min; ?> minutes</strong> (online or offline).
                </li>
                <li>
                    <strong>Attack Fatigue:</strong>
                    Multiple attacks in a short window may apply temporary fatigue (10 attacks against same target within 1 hour, 11th attack starts receiving casualties from fatigue).
                    Fatigue decays over 1 hour from last attack. 
                </li>
                <li>
                    <strong>Victory & Rewards:</strong>
                    Wins can steal credits and earn XP. Losses still consume turns and may grant your opponent XP.
                </li>
                <li>
                    <strong>Choosing Targets:</strong>
                    Higher-level or well-defended opponents are harder to defeat. Check profiles before committing many turns.
                </li>
            </ul>
        </div>

        <div id="attack-help-mobile-summary" class="md:hidden mt-2">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-gray-300">
                <span><strong>Turns:</strong> <?php echo number_format($attack_turns_current); ?></span>
                <span>•</span>
                <span><strong>Regen:</strong> +<?php echo $turns_per_cycle; ?>/<?php echo $turn_interval_min; ?>m</span>
                <span>•</span>
                <button type="button"
                        id="attack-help-mobile-toggle"
                        class="underline decoration-dotted">
                    Show details
                </button>
            </div>
        </div>
    </div>

    <script>
    (function(){
      const card   = document.getElementById('attack-help-card');
      const body   = document.getElementById('attack-help-content');
      const toggle = document.getElementById('attack-help-toggle');
      const mobileToggle  = document.getElementById('attack-help-mobile-toggle');

      if (!card || !body || !toggle) return;

      const KEY = 'attack_help_collapsed';

      function setCollapsed(collapsed){
        if (collapsed) {
          body.classList.add('hidden');
          toggle.textContent = 'Show';
          card.setAttribute('data-state','collapsed');
        } else {
          body.classList.remove('hidden');
          toggle.textContent = 'Hide';
          card.setAttribute('data-state','expanded');
        }
        try { localStorage.setItem(KEY, collapsed ? '1' : '0'); } catch(e){}
      }

      toggle.addEventListener('click', () => setCollapsed(!body.classList.contains('hidden')));

      if (mobileToggle) {
        mobileToggle.addEventListener('click', () => {
          const collapsed = body.classList.contains('hidden');
          if (collapsed) {
            setCollapsed(false);
            mobileToggle.textContent = 'Hide details';
          } else {
            setCollapsed(true);
            mobileToggle.textContent = 'Show details';
          }
        });
      }

      // Initialize: desktop expanded; mobile collapsed (first load)
      let collapsed = false;
      try { collapsed = localStorage.getItem(KEY) === '1'; } catch(e){}
      const isMobile = window.matchMedia('(max-width: 767px)').matches;
      if (isMobile && localStorage.getItem(KEY) === null) collapsed = true;
      setCollapsed(collapsed);
    })();
    </script>

    <div class="content-box rounded-lg p-4 hidden md:block">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-title text-cyan-400">Target List</h3>
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
                        // MODIFICATION: Apply the .alliance-tag class for styling.
                        $tag = !empty($t['alliance_tag']) ? '<span class="alliance-tag">[' . htmlspecialchars($t['alliance_tag']) . ']</span> ' : '';
                    ?>
                    <tr class="<?php echo ($t['id'] === $user_id) ? 'bg-gray-800/30' : ''; ?>">
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
                                <form action="/attack.php" method="POST" class="flex items-center gap-1">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="csrf_action" value="attack">
                                    <input type="hidden" name="action" value="attack">
                                    <input type="hidden" name="defender_id" value="<?php echo (int)$t['id']; ?>">
                                    <input type="number" name="attack_turns" min="1" max="10" value="1" class="w-12 bg-gray-900 border border-gray-600 rounded text-center p-1 text-xs">
                                    <button type="submit" class="bg-red-700 hover:bg-red-600 text-white text-xs font-semibold py-1 px-2 rounded-md">Attack</button>
                                </form>

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
            <h3 class="font-title text-cyan-400">Targets</h3>
            <div class="text-xs text-gray-400">Showing <?php echo count($targets); ?></div>
        </div>

        <div class="space-y-3">
            <?php
            $rank = 1;
            foreach ($targets as $t):
                $avatar = $t['avatar_path'] ?: '/assets/img/default_avatar.webp';
                // MODIFICATION: Apply the .alliance-tag class for styling.
                $tag = !empty($t['alliance_tag']) ? '<span class="alliance-tag">[' . htmlspecialchars($t['alliance_tag']) . ']</span> ' : '';
            ?>
            <div class="bg-gray-900/60 border border-gray-700 rounded-lg p-3">
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

                <div class="mt-3 flex items-center justify-between gap-2">
                    <form action="/attack.php" method="POST" class="flex items-center gap-2 w-full">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="csrf_action" value="attack">
                        <input type="hidden" name="action" value="attack">
                        <input type="hidden" name="defender_id" value="<?php echo (int)$t['id']; ?>">
                        <input type="number" name="attack_turns" min="1" max="10" value="1" class="flex-1 bg-gray-900 border border-gray-600 rounded text-center p-1 text-xs">
                        <button type="submit" class="bg-red-700 hover:bg-red-600 text-white text-xs font-semibold py-1 px-3 rounded-md">Attack</button>
                    </form>

                    <form action="/view_profile.php" method="GET" class="shrink-0" onsubmit="event.stopPropagation();">
                        <input type="hidden" name="user" value="<?php echo (int)$t['id']; ?>">
                        <input type="hidden" name="id"   value="<?php echo (int)$t['id']; ?>">
                        <button type="submit" class="bg-gray-700 hover:bg-gray-600 text-white text-xs font-semibold py-1 px-3 rounded-md">
                            Profile
                        </button>
                    </form>
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

<?php include_once __DIR__ . '/../includes/footer.php'; ?>