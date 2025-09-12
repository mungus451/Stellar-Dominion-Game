<?php
// --- PAGE CONFIGURATION ---
$page_title  = 'Battle – Attack';
$active_page = 'attack.php';

// --- BOOTSTRAP (router already started session + auth) ---
date_default_timezone_set('UTC');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Services/StateService.php'; // Centralized state
require_once __DIR__ . '/../includes/advisor_hydration.php';

$user_id = (int)($_SESSION['id'] ?? 0);
if ($user_id <= 0) { header('Location: /index.php'); exit; }

// Handle POST via controller (unchanged app flow)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/AttackController.php';
    exit;
}

// --- PAGINATION (match war_history.php pattern) ---
$allowed_per_page = [10, 20, 50, 100];
$items_per_page = isset($_GET['show']) ? (int)$_GET['show'] : 20;
if (!in_array($items_per_page, $allowed_per_page, true)) { $items_per_page = 20; }
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Total players (include self on this page by excluding id 0)
$total_players = function_exists('ss_count_targets') ? ss_count_targets($link, 0) : 0;
$total_pages   = max(1, (int)ceil(($total_players ?: 1) / $items_per_page));
if ($current_page > $total_pages) { $current_page = $total_pages; }
$offset = ($current_page - 1) * $items_per_page;
$from   = $total_players > 0 ? ($offset + 1) : 0;
$to     = min($offset + $items_per_page, $total_players);

// derived nav helpers
$prev_page = max(1, $current_page - 1);
$next_page = min($total_pages, $current_page + 1);

// windowed page list (max 10 pages shown)
$page_window = 10;
$start_page  = max(1, $current_page - (int)floor($page_window / 2));
$end_page    = min($total_pages, $start_page + $page_window - 1);
$start_page  = max(1, $end_page - $page_window + 1);

// --- PAGE DATA ---
$csrf_token  = generate_csrf_token('attack');
$invite_csrf = generate_csrf_token('invite');

$me = ss_get_user_state($link, $user_id, [
    'id','character_name','level','credits','banked_credits',
    'attack_turns','last_updated','experience','alliance_id','alliance_role_id'
]);

$my_alliance_id = $me['alliance_id'] ?? null;

// Check 'invite members' permission if in an alliance
$can_invite_members = false;
if (!empty($me['alliance_role_id'])) {
    if ($stmt_perm = mysqli_prepare($link, "SELECT can_invite_members FROM alliance_roles WHERE id = ? LIMIT 1")) {
        mysqli_stmt_bind_param($stmt_perm, "i", $me['alliance_role_id']);
        mysqli_stmt_execute($stmt_perm);
        $perm_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_perm)) ?: [];
        mysqli_stmt_close($stmt_perm);
        $can_invite_members = (bool)($perm_row['can_invite_members'] ?? 0);
    }
}

// Target list (include self on this page) via StateService.
// Pass 0 so the WHERE u.id <> ? does not exclude anyone.
$targets = ss_get_targets($link, 0, $items_per_page, $offset);
// NOTE: ss_get_targets already computes army_size

// Prefetch pending alliance invitations/applications for targets to control Invite UI
$pendingInvites = $pendingApps = [];
$targetIds = array_map('intval', array_column($targets, 'id'));
if (!empty($targetIds)) {
    $inList = implode(',', $targetIds);
    // Pending invitations (any alliance)
    $sqlInv = "SELECT invitee_id FROM alliance_invitations WHERE status = 'pending' AND invitee_id IN ($inList)";
    if ($resInv = mysqli_query($link, $sqlInv)) {
        while ($r = mysqli_fetch_assoc($resInv)) { $pendingInvites[(int)$r['invitee_id']] = true; }
        mysqli_free_result($resInv);
    }
    // Pending applications (any alliance)
    $sqlApp = "SELECT user_id FROM alliance_applications WHERE status = 'pending' AND user_id IN ($inList)";
    if ($resApp = mysqli_query($link, $sqlApp)) {
        while ($r = mysqli_fetch_assoc($resApp)) { $pendingApps[(int)$r['user_id']] = true; }
        mysqli_free_result($resApp);
    }
}

foreach ($targets as &$row) {

    // --- Rivalry Check Logic ---
    $row['is_rival'] = false;
    if ($my_alliance_id && $row['alliance_id'] && $my_alliance_id != $row['alliance_id']) {
        $a1 = $my_alliance_id;
        $a2 = $row['alliance_id'];
        $sql_rival = "SELECT 1 FROM rivalries WHERE (alliance1_id = ? AND alliance2_id = ?) OR (alliance1_id = ? AND alliance2_id = ?) LIMIT 1";
        if ($stmt_rival = mysqli_prepare($link, $sql_rival)) {
            mysqli_stmt_bind_param($stmt_rival, "iiii", $a1, $a2, $a2, $a1);
            mysqli_stmt_execute($stmt_rival);
            mysqli_stmt_store_result($stmt_rival);
            if (mysqli_stmt_num_rows($stmt_rival) > 0) {
                $row['is_rival'] = true;
            }
            mysqli_stmt_close($stmt_rival);
        }
    }
    // --- END: Rivalry Check ---
}

// Timers
$turn_interval_minutes = 10;

// --- HEADER ---
include_once __DIR__ . '/../includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php  
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
                    Your first 5 attacks against a singular player are at full power, use them wisely! attacks 6-10 will have a reduced reward! All attacks over 10 will incur increasing casualties from fatigue, at 50 attacks you will only do structural damage. The window is 12 hours from your first attack!
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
            <div class="text-xs text-gray-400">
                Showing <?php echo number_format($from); ?>–<?php echo number_format($to); ?>
                of <?php echo number_format($total_players); ?> •
                Page <?php echo $current_page; ?>/<?php echo $total_pages; ?>
            </div>
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
                    $rank = $offset + 1;
                    foreach ($targets as $t):
                        $avatar = $t['avatar_path'] ?: '/assets/img/default_avatar.webp';
                        $tag = !empty($t['alliance_tag']) ? '<span class="alliance-tag">[' . htmlspecialchars($t['alliance_tag']) . ']</span> ' : '';
                        
                        // Determine if the target is an ally or the player themselves.
                        $is_self = ($t['id'] === $user_id);
                        $is_ally = ($my_alliance_id && $my_alliance_id === $t['alliance_id'] && !$is_self);
                        $cant_attack = $is_self || $is_ally;

                        // Invite UI conditions
                        $hasPendingInvite = !empty($pendingInvites[(int)$t['id']]);
                        $hasPendingApp    = !empty($pendingApps[(int)$t['id']]);
                        $eligibleForInvite = $my_alliance_id
                            && $can_invite_members
                            && !$is_self
                            && !$is_ally
                            && empty($t['alliance_id'])
                            && !$hasPendingInvite
                            && !$hasPendingApp;
                    ?>
                    <tr class="<?php echo $is_self ? 'bg-cyan-900/40' : ''; // Highlight the player's own row ?>">
                        <td class="px-3 py-3"><?php echo $rank++; ?></td>
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-3">
                                <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" class="w-8 h-8 rounded-md object-cover">
                                <div class="leading-tight">
                                    <div class="text-white font-semibold">
                                        <?php echo $tag . htmlspecialchars($t['character_name']); ?>
                                        <?php if (!empty($t['is_rival'])): ?>
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
                                    <div class="flex items-center gap-1">
                                        <input type="number" value="1" class="w-12 bg-gray-800 border border-gray-700 rounded text-center p-1 text-xs text-gray-500" disabled>
                                        <button type="button" class="bg-gray-600 text-gray-400 text-xs font-semibold py-1 px-2 rounded-md cursor-not-allowed" disabled>
                                            <?php echo $is_self ? 'Self' : 'Ally'; ?>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <form action="/attack.php" method="POST" class="flex items-center gap-1">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="csrf_action" value="attack">
                                        <input type="hidden" name="action" value="attack">
                                        <input type="hidden" name="defender_id" value="<?php echo (int)$t['id']; ?>">
                                        <input type="number" name="attack_turns" min="1" max="10" value="1" class="w-12 bg-gray-900 border border-gray-600 rounded text-center p-1 text-xs">
                                        <button type="submit" class="bg-red-700 hover:bg-red-600 text-white text-xs font-semibold py-1 px-2 rounded-md">Attack</button>
                                    </form>

                                    <?php if ($eligibleForInvite): ?>
                                        <form action="/attack.php" method="POST" class="inline-block" onsubmit="event.stopPropagation();">
                                            <input type="hidden" name="csrf_token"  value="<?php echo htmlspecialchars($invite_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="csrf_action" value="invite">
                                            <input type="hidden" name="action"      value="alliance_invite">
                                            <input type="hidden" name="invitee_id"  value="<?php echo (int)$t['id']; ?>">
                                            <button type="submit" class="bg-indigo-700 hover:bg-indigo-600 text-white text-xs font-semibold py-1 px-2 rounded-md">Invite</button>
                                        </form>
                                    <?php elseif ($my_alliance_id && $can_invite_members && empty($t['alliance_id'])): ?>
                                        <button type="button" class="bg-gray-800 text-gray-400 text-xs font-semibold py-1 px-2 rounded-md cursor-not-allowed" disabled>
                                            <?php echo $hasPendingInvite ? 'Invited' : ($hasPendingApp ? 'Applied' : 'Invite'); ?>
                                        </button>
                                    <?php endif; ?>
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

        <?php if ($total_pages > 1): ?>
        <div class="mt-4 flex flex-wrap justify-center items-center gap-2 text-sm">
            <a href="/attack.php?show=<?php echo $items_per_page; ?>&page=1"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 <?php if ($current_page == 1) echo 'hidden'; ?>">&laquo; First</a>
            <a href="/attack.php?show=<?php echo $items_per_page; ?>&page=<?php echo $prev_page; ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&laquo;</a>
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="/attack.php?show=<?php echo $items_per_page; ?>&page=<?php echo $i; ?>"
                   class="px-3 py-1 <?php echo $i == $current_page ? 'bg-cyan-600 font-bold' : 'bg-gray-700'; ?> rounded-md hover:bg-cyan-600"><?php echo $i; ?></a>
            <?php endfor; ?>
            <a href="/attack.php?show=<?php echo $items_per_page; ?>&page=<?php echo $next_page; ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&raquo;</a>
            <a href="/attack.php?show=<?php echo $items_per_page; ?>&page=<?php echo $total_pages; ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 <?php if ($current_page == $total_pages) echo 'hidden'; ?>">Last &raquo;</a>
            <form method="GET" action="/attack.php" class="inline-flex items-center gap-1">
                <input type="hidden" name="show" value="<?php echo $items_per_page; ?>">
                <input type="number" name="page" min="1" max="<?php echo $total_pages; ?>" value="<?php echo $current_page; ?>"
                       class="bg-gray-900 border border-gray-600 rounded-md w-16 text-center p-1 text-xs">
                <button type="submit" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 text-xs">Go</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <div class="content-box rounded-lg p-4 md:hidden">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-title text-cyan-400">Targets</h3>
            <div class="text-xs text-gray-400">
                Showing <?php echo number_format($from); ?>–<?php echo number_format($to); ?>
                of <?php echo number_format($total_players); ?>
            </div>
        </div>

        <div class="space-y-3">
            <?php
            $rank = $offset + 1;
            foreach ($targets as $t):
                $avatar = $t['avatar_path'] ?: '/assets/img/default_avatar.webp';
                $tag = !empty($t['alliance_tag']) ? '<span class="alliance-tag">[' . htmlspecialchars($t['alliance_tag']) . ']</span> ' : '';

                // Determine if the target is an ally or the player themselves for mobile view.
                $is_self = ($t['id'] === $user_id);
                $is_ally = ($my_alliance_id && $my_alliance_id === $t['alliance_id'] && !$is_self);
                $cant_attack = $is_self || $is_ally;

                // Invite UI conditions
                $hasPendingInvite = !empty($pendingInvites[(int)$t['id']]);
                $hasPendingApp    = !empty($pendingApps[(int)$t['id']]);
                $eligibleForInvite = $my_alliance_id
                    && $can_invite_members
                    && !$is_self
                    && !$is_ally
                    && empty($t['alliance_id'])
                    && !$hasPendingInvite
                    && !$hasPendingApp;
            ?>
            <div class="bg-gray-900/60 border border-gray-700 rounded-lg p-3 <?php echo $is_self ? 'border-cyan-700' : ''; ?>">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" class="w-10 h-10 rounded-md object-cover">
                        <div>
                            <div class="text-white font-semibold">
                                <?php echo $tag . htmlspecialchars($t['character_name']); ?>
                                <?php if (!empty($t['is_rival'])): ?>
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
                    <?php if ($cant_attack): ?>
                        <div class="flex items-center gap-2 w-full">
                           <input type="number" value="1" class="flex-1 bg-gray-800 border border-gray-700 rounded text-center p-1 text-xs text-gray-500" disabled>
                           <button type="button" class="bg-gray-600 text-gray-400 text-xs font-semibold py-1 px-3 rounded-md cursor-not-allowed" disabled>
                               <?php echo $is_self ? 'Self' : 'Ally'; ?>
                           </button>
                        </div>
                    <?php else: ?>
                        <form action="/attack.php" method="POST" class="flex items-center gap-2 w-full">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="csrf_action" value="attack">
                            <input type="hidden" name="action" value="attack">
                            <input type="hidden" name="defender_id" value="<?php echo (int)$t['id']; ?>">
                            <input type="number" name="attack_turns" min="1" max="10" value="1" class="flex-1 bg-gray-900 border border-gray-600 rounded text-center p-1 text-xs">
                            <button type="submit" class="bg-red-700 hover:bg-red-600 text-white text-xs font-semibold py-1 px-3 rounded-md">Attack</button>
                        </form>

                        <?php if ($eligibleForInvite): ?>
                            <form action="/attack.php" method="POST" class="shrink-0" onsubmit="event.stopPropagation();">
                                <input type="hidden" name="csrf_token"  value="<?php echo htmlspecialchars($invite_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="csrf_action" value="invite">
                                <input type="hidden" name="action"      value="alliance_invite">
                                <input type="hidden" name="invitee_id"  value="<?php echo (int)$t['id']; ?>">
                                <button type="submit" class="bg-indigo-700 hover:bg-indigo-600 text-white text-xs font-semibold py-1 px-3 rounded-md">Invite</button>
                            </form>
                        <?php elseif ($my_alliance_id && $can_invite_members && empty($t['alliance_id'])): ?>
                            <button type="button" class="bg-gray-800 text-gray-400 text-xs font-semibold py-1 px-3 rounded-md cursor-not-allowed" disabled>
                                <?php echo $hasPendingInvite ? 'Invited' : ($hasPendingApp ? 'Applied' : 'Invite'); ?>
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>

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

        <?php if ($total_pages > 1): ?>
        <div class="mt-4 flex flex-wrap justify-center items-center gap-2 text-sm">
            <a href="/attack.php?show=<?php echo $items_per_page; ?>&page=1"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 <?php if ($current_page == 1) echo 'hidden'; ?>">&laquo; First</a>
            <a href="/attack.php?show=<?php echo $items_per_page; ?>&page=<?php echo $prev_page; ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&laquo;</a>
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="/attack.php?show=<?php echo $items_per_page; ?>&page=<?php echo $i; ?>"
                   class="px-3 py-1 <?php echo $i == $current_page ? 'bg-cyan-600 font-bold' : 'bg-gray-700'; ?> rounded-md hover:bg-cyan-600"><?php echo $i; ?></a>
            <?php endfor; ?>
            <a href="/attack.php?show=<?php echo $items_per_page; ?>&page=<?php echo $next_page; ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600">&raquo;</a>
            <a href="/attack.php?show=<?php echo $items_per_page; ?>&page=<?php echo $total_pages; ?>"
               class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 <?php if ($current_page == $total_pages) echo 'hidden'; ?>">Last &raquo;</a>
            <form method="GET" action="/attack.php" class="inline-flex items-center gap-1">
                <input type="hidden" name="show" value="<?php echo $items_per_page; ?>">
                <input type="number" name="page" min="1" max="<?php echo $total_pages; ?>" value="<?php echo $current_page; ?>"
                       class="bg-gray-900 border border-gray-600 rounded-md w-16 text-center p-1 text-xs">
                <button type="submit" class="px-3 py-1 bg-gray-700 rounded-md hover:bg-cyan-600 text-xs">Go</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
