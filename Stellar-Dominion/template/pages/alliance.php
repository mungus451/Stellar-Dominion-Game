<?php
/**
 * template/pages/alliance.php — Alliance Hub (+Scout Alliances tab)
 * Uses header/footer, renders in main column, themed with /assets/css/style.css.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header('Location: /index.html'); exit; }

$ROOT = dirname(__DIR__, 2);
require_once $ROOT . '/config/config.php';      // $link (mysqli)
require_once $ROOT . '/src/Game/GameData.php';
require_once $ROOT . '/src/Game/GameFunctions.php';

/* POST dispatcher -> AllianceManagementController */
require_once $ROOT . '/src/Controllers/BaseAllianceController.php';
require_once $ROOT . '/src/Controllers/AllianceManagementController.php';

/* POST HANDLER */

require_once $ROOT . '/template/includes/alliance/alliance_post_handler.php' ;

date_default_timezone_set('UTC');
if (function_exists('process_offline_turns') && isset($_SESSION['id'])) {
    process_offline_turns($link, (int)$_SESSION['id']);
}

/* helpers */
require_once $ROOT . '/template/includes/alliance/alliance_helpers.php'; 

/**
 * Render Join/Cancel button(s) for a public alliance row (not Scout tab).
 * Returns HTML (or empty if applications table missing).
 */
require_once $ROOT . '/template/includes/alliance/join_cancel.php';

/* viewer + alliance */
require_once $ROOT . '/template/includes/alliance/viewer.php' ;

/* viewer permissions (for Approve/Kick buttons) */
require_once $ROOT . '/template/includes/alliance/viewer_permissions.php' ;

/* charter (optional) */
include_once $ROOT . '/template/includes/alliance/charter.php' ;

/* rivalries (for RIVAL badge) */
require_once $ROOT . '/template/includes/alliance/rivalries.php' ;

/* roster — include role and avatar */
require_once $ROOT . '/template/includes/alliance/roster.php' ;

/* applications for leaders to review */
require_once $ROOT . '/template/includes/alliance/applications.php' ;

/* player-side apply/cancel state (when NOT in an alliance) */
require_once $ROOT . '/template/includes/alliance/apply_cancel.php' ;

/* pending invitations for the viewer (when NOT in an alliance) */


/* ---------------- Scout list (always available) ---------------- */
include_once $ROOT . '/template/includes/alliance/scout_list.php' ;

/* page chrome */
$active_page = 'alliance.php';
$page_title  = 'Starlight Dominion - Alliance Hub';
include $ROOT . '/template/includes/header.php';
?>

</aside><section id="main" class="col-span-9 lg:col-span-10">

    <?php if (isset($_SESSION['alliance_error'])): ?>
        <div class="content-box text-red-200 border-red-600/60 p-3 rounded-md text-center mb-4" style="border-color:rgba(220,38,38,.6)">
            <?= e($_SESSION['alliance_error']); unset($_SESSION['alliance_error']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['alliance_message'])): ?>
        <div class="content-box text-emerald-200 border-emerald-600/60 p-3 rounded-md text-center mb-4" style="border-color:rgba(5,150,105,.6)">
            <?= e($_SESSION['alliance_message']); unset($_SESSION['alliance_message']); ?>
        </div>
    <?php endif; ?>

    <?php if ($alliance): ?>
        <!-- Header Card -->
        <div class="content-box rounded-lg p-5 mb-4">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between">
                <div class="flex items-center gap-4">
                    <img src="<?= e($alliance_avatar) ?>" class="w-20 h-20 rounded-lg border object-cover" alt="Alliance" style="border-color:#374151">
                    <div>
                        <h2 class="font-title text-3xl text-white font-bold">
                            <span style="color:#06b6d4">[<?= e($alliance['tag'] ?? '') ?>]</span> <?= e($alliance['name'] ?? 'Alliance') ?>!
                        </h2>
                        <p class="text-xs text-gray-400">Led by <?= e($alliance['leader_name'] ?? 'Unknown') ?></p>
                    </div>
                </div>
                <div class="text-right mt-4 md:mt-0">
                    <p class="text-xs text-gray-400 uppercase">Alliance Bank</p>
                    <p class="text-2xl font-extrabold" style="color:#facc15">
                        <?= number_format((int)($alliance['bank_credits'] ?? 0)) ?> Credits
                    </p>
                        <?php $user_is_leader = ($alliance && (int)$alliance['leader_id'] === $user_id); ?>
                    <div class="mt-3 space-x-2">
                        <?php if ($user_is_leader): ?>
                            <a href="/edit_alliance" class="inline-block text-white font-semibold text-sm px-4 py-2 rounded-md" style="background:#075985">Edit Alliance</a>
                        <?php else: ?>
                            <form action="/alliance.php" method="post" class="inline-block" onsubmit="return confirm('Are you sure you want to leave this alliance?');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                <input type="hidden" name="csrf_action" value="alliance_hub">
                                <input type="hidden" name="action" value="leave">
                                <button class="text-white font-semibold text-sm px-4 py-2 rounded-md" style="background:#991b1b">Leave Alliance</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charter -->
        <div class="content-box rounded-lg p-4 mb-4">
            <h3 class="text-lg font-semibold text-white mb-2">Alliance Charter</h3>
            <div class="text-sm text-gray-300"><?= nl2br(e($alliance_charter !== '' ? $alliance_charter : 'No charter has been set yet.')) ?></div>
        </div>

        <!-- Rivalries -->
        <?php if (!empty($rivalries)): ?>
            <div class="content-box rounded-lg p-4 mb-4">
                <h3 class="text-lg font-semibold text-white mb-2">Active Rivalries</h3>
                <ul class="list-disc list-inside text-gray-300">
                    <?php foreach ($rivalries as $rv): ?>
                        <li class="flex items-center justify-between">
                            <span><span style="color:#06b6d4">[<?= e($rv['tag'] ?? '') ?>]</span> <?= e($rv['name'] ?? 'Unknown') ?></span>
                            <span class="text-xs text-gray-400">
                                <?php
                                if (isset($rv['status'])) echo e($rv['status']);
                                elseif (isset($rv['heat_level'])) echo 'Heat ' . (int)$rv['heat_level'];
                                else echo 'Active';
                                ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <?php
        // DEFAULT TAB now "roster"
        $tab_in = isset($_GET['tab']) ? (string)$_GET['tab'] : 'roster';
        $current_tab = in_array($tab_in, ['roster','applications','scout'], true) ? $tab_in : 'roster';
        ?>
        <div class="content-box rounded-lg px-4 pt-3 mb-3">
            <nav class="flex gap-6 text-sm">
                <a href="?tab=roster" class="nav-link <?= $current_tab==='roster' ? 'active text-white' : '' ?>">Member Roster</a>
                <a href="?tab=scout" class="nav-link <?= $current_tab==='scout' ? 'active text-white' : '' ?>">Scout Alliances</a>
                <a href="?tab=applications" class="nav-link <?= $current_tab==='applications' ? 'active text-white' : '' ?>">
                    Applications
                    <?php if (!empty($applications)): ?>
                        <span class="ml-2 inline-block rounded-full px-2 py-0.5 text-xs font-bold" style="background:#0e7490;color:#fff"><?= count($applications) ?></span>
                    <?php endif; ?>
                </a>
            </nav>
        </div>

        <!-- Roster -->
        <section id="tab-roster" class="<?= $current_tab === 'roster' ? '' : 'hidden' ?>">
            <div class="content-box rounded-lg p-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead style="background:#111827;color:#9ca3af">
                        <tr>
                            <th class="p-2">Name</th>
                            <th class="p-2">Level</th>
                            <th class="p-2">Role</th>
                            <th class="p-2">Net Worth</th>
                            <th class="p-2">Status</th>
                            <th class="p-2 text-right">Manage</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-300">
                        <?php if (empty($members)): ?>
                            <tr><td colspan="6" class="p-4 text-center text-gray-400">No members found.</td></tr>
                        <?php else: foreach ($members as $m): ?>
                            <tr class="border-t" style="border-color:#374151">
                                <td class="p-2">
                                    <div class="flex items-center gap-2">
                                        <?php if (!empty($m['avatar_url'])): ?>
                                            <img src="<?= e($m['avatar_url']) ?>" alt="" class="w-7 h-7 rounded border object-cover" style="border-color:#374151">
                                        <?php else: ?>
                                            <div class="w-7 h-7 rounded border flex items-center justify-center text-xs font-bold" style="border-color:#374151;background:#1f2937">
                                                <?= e(initial_letter($m['username'] ?? '?')) ?>
                                            </div>
                                        <?php endif; ?>
                                        <span><?= e($m['username'] ?? 'Unknown') ?></span>
                                    </div>
                                </td>
                                <td class="p-2"><?= isset($m['level']) ? (int)$m['level'] : 0 ?></td>
                                <td class="p-2"><?= e($m['role_name'] ?? 'Member') ?></td>
                                <td class="p-2"><?= isset($m['net_worth']) ? number_format((int)$m['net_worth']) : '—' ?></td>
                                <td class="p-2">Offline</td>
                                <td class="p-2 text-right">
                                    <?php
                                    $canKick = !empty($viewer_perms['can_kick_members']);
                                    $isSelf  = (int)$m['id'] === $user_id;
                                    $isLeader= isset($alliance['leader_id']) && (int)$m['id'] === (int)$alliance['leader_id'];
                                    if ($canKick && !$isSelf && !$isLeader): ?>
                                        <form action="/alliance.php" method="post" class="inline-block">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                            <input type="hidden" name="csrf_action" value="alliance_hub">
                                            <input type="hidden" name="action" value="kick">
                                            <input type="hidden" name="member_id" value="<?= (int)$m['id'] ?>">
                                            <button class="text-white text-xs px-3 py-1 rounded-md" style="background:#7f1d1d">Kick</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-500">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Applications -->
        <section id="tab-applications" class="<?= $current_tab === 'applications' ? '' : 'hidden' ?>">
            <div class="content-box rounded-lg p-4 overflow-x-auto">
                <?php if (empty($applications)): ?>
                    <p class="text-gray-300">There are no pending applications.</p>
                <?php else: ?>
                <table class="w-full text-left text-sm">
                    <thead style="background:#111827;color:#9ca3af">
                        <tr><th class="p-2">Name</th><th class="p-2">Level</th><th class="p-2">Reason</th><th class="p-2 text-right">Action</th></tr>
                    </thead>
                    <tbody class="text-gray-300">
                        <?php foreach ($applications as $app): ?>
                            <tr class="border-t" style="border-color:#374151">
                                <td class="p-2"><?= e($app['username'] ?? 'Unknown') ?></td>
                                <td class="p-2"><?= (int)($app['level'] ?? 0) ?></td>
                                <td class="p-2"><?= e($app['reason'] ?? '-') ?></td>
                                <td class="p-2 text-right">
                                    <?php if (!empty($viewer_perms['can_approve_membership'])): ?>
                                        <form action="/alliance.php" method="post" class="inline-block">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                            <input type="hidden" name="csrf_action" value="alliance_hub">
                                            <input type="hidden" name="user_id" value="<?= (int)$app['user_id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button class="text-white text-xs px-3 py-1 rounded-md" style="background:#065f46">Approve</button>
                                        </form>
                                        <form action="/alliance.php" method="post" class="inline-block ml-2">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                            <input type="hidden" name="csrf_action" value="alliance_hub">
                                            <input type="hidden" name="user_id" value="<?= (int)$app['user_id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button class="text-white text-xs px-3 py-1 rounded-md" style="background:#991b1b">Reject</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-500">Leader/Officer only</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </section>

        <!-- Scout Alliances (when in an alliance) -->
        <section id="tab-scout" class="<?= $current_tab === 'scout' ? '' : 'hidden' ?>">
            <div class="content-box rounded-lg p-4 overflow-x-auto">
                <h3 class="text-lg font-semibold text-white mb-3">Scout Opposing Alliances</h3>
                <form method="get" action="/alliance.php" class="mb-3">
                    <input type="hidden" name="tab" value="scout">
                    <div class="flex w-full">
                        <input type="text" name="opp_search" value="<?= e($opp_term) ?>"
                               placeholder="Search name or tag"
                               class="flex-1 bg-gray-900 border text-white px-3 py-2 rounded-l-md focus:outline-none" style="border-color:#374151">
                        <button type="submit" class="text-white font-bold py-2 px-4 rounded-r-md" style="background:#0891b2">Search</button>
                    </div>
                </form>

                <table class="w-full text-left text-sm">
                    <thead style="background:#111827;color:#9ca3af">
                        <tr><th class="p-2">Alliance</th><th class="p-2">Members</th><th class="p-2 text-right">Action</th></tr>
                    </thead>
                    <tbody class="text-gray-300">
                        <?php if (empty($opp_list)): ?>
                            <tr><td colspan="3" class="p-4 text-center text-gray-400">No alliances found.</td></tr>
                        <?php else: foreach ($opp_list as $row): ?>
                            <tr class="border-t" style="border-color:#374151">
                                <td class="p-2">
                                    <div class="flex items-center gap-3">
                                        <img src="<?= e($row['avatar_url']) ?>" alt="" class="w-7 h-7 rounded border" style="border-color:#374151;object-fit:cover">
                                        <div>
                                            <span class="text-white">
                                                <span style="color:#06b6d4">[<?= e($row['tag'] ?? '') ?>]</span>
                                                <?= e($row['name']) ?>
                                            </span>
                                            <?php if (!empty($rivalIds[(int)$row['id']])): ?>
                                                <span class="ml-2 align-middle text-xs px-2 py-0.5 rounded"
                                                      style="background:#7f1d1d;color:#fecaca;border:1px solid #ef4444">RIVAL</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-2"><?= (int)($row['member_count'] ?? 0) ?></td>
                                <td class="p-2 text-right">
                                    <a href="/view_alliance.php?id=<?= (int)$row['id'] ?>"
                                       class="text-white font-bold py-1 px-3 rounded-md text-xs" style="background:#374151">View</a>
                                    <!-- No Join button on Scout tab -->
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <?php if ($opp_pages > 1): ?>
                <div class="mt-3 flex items-center justify-between">
                    <div class="text-xs text-gray-400"><?= number_format($opp_total) ?> alliances</div>
                    <div class="flex items-center gap-2">
                        <?php $prev = $opp_page > 1 ? $opp_page - 1 : 1; $next = $opp_page < $opp_pages ? $opp_page + 1 : $opp_pages; ?>
                        <a class="px-3 py-1 rounded text-xs" style="border:1px solid #374151" href="<?= $base_scout . '&opp_page=' . $prev ?>">Prev</a>
                        <a class="px-3 py-1 rounded text-xs" style="border:1px solid #374151" href="<?= $base_scout . '&opp_page=' . $next ?>">Next</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>

    <?php else: ?>
        <!-- Not in alliance: pending invitations (Accept / Decline) -->
        <?php if (!empty($invitations)): ?>
            <div class="content-box rounded-lg p-4 mb-4">
                <h3 class="text-lg font-semibold text-white mb-2">Alliance Invitations</h3>
                <p class="text-xs text-gray-400 mb-3">
                    You’ve been invited to join the alliances below. Accepting an invite will place you into that alliance immediately.
                    <?php if ($pending_app_id !== null): ?>
                        <br><span class="text-amber-300">Note:</span> You currently have a pending application — cancel it before accepting an invite.
                    <?php endif; ?>
                </p>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead style="background:#111827;color:#9ca3af">
                            <tr>
                                <th class="p-2">Alliance</th>
                                <th class="p-2">Invited By</th>
                                <th class="p-2">When</th>
                                <th class="p-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-300">
                            <?php foreach ($invitations as $inv): ?>
                                <tr class="border-t" style="border-color:#374151">
                                    <td class="p-2">
                                        <span class="text-white">
                                            <span style="color:#06b6d4">[<?= e($inv['alliance_tag'] ?? '') ?>]</span>
                                            <?= e($inv['alliance_name'] ?? 'Alliance') ?>
                                        </span>
                                    </td>
                                    <td class="p-2"><?= e($inv['inviter_name'] ?? 'Unknown') ?></td>
                                    <td class="p-2">
                                        <?php
                                        $ts = isset($inv['created_at']) ? strtotime((string)$inv['created_at']) : false;
                                        echo $ts ? date('Y-m-d H:i', $ts) . ' UTC' : '—';
                                        ?>
                                    </td>
                                    <td class="p-2 text-right">
                                        <?php if ($pending_app_id === null): ?>
                                            <form action="/alliance.php" method="post" class="inline-block">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                <input type="hidden" name="csrf_action" value="alliance_hub">
                                                <input type="hidden" name="action" value="accept_invite">
                                                <input type="hidden" name="invitation_id" value="<?= (int)$inv['id'] ?>">
                                                <input type="hidden" name="alliance_id"   value="<?= (int)$inv['alliance_id'] ?>">
                                                <button class="text-white text-xs px-3 py-1 rounded-md" style="background:#065f46">Accept</button>
                                            </form>
                                        <?php else: ?>
                                            <button class="text-white text-xs px-3 py-1 rounded-md opacity-50 cursor-not-allowed"
                                                    title="Cancel your application before accepting an invite"
                                                    style="background:#065f46">Accept</button>
                                        <?php endif; ?>

                                        <form action="/alliance.php" method="post" class="inline-block ml-2">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                            <input type="hidden" name="csrf_action" value="alliance_hub">
                                            <input type="hidden" name="action" value="decline_invite">
                                            <input type="hidden" name="invitation_id" value="<?= (int)$inv['id'] ?>">
                                            <button class="text-white text-xs px-3 py-1 rounded-md" style="background:#991b1b">Decline</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Not in alliance: public list WITH Join/Cancel -->
        <div class="content-box rounded-lg p-4 overflow-x-auto">
            <h3 class="text-lg font-semibold text-white mb-3">Browse Alliances</h3>
            <div class="mb-3 text-right">
                <a href="/create_alliance" class="inline-block text-white font-bold py-2 px-4 rounded-md" style="background:#065f46">Create Alliance</a>
            </div>
            <form method="get" action="/alliance.php" class="mb-3">
                <div class="flex w-full">
                    <input type="text" name="opp_search" value="<?= e($opp_term) ?>"
                           placeholder="Search name or tag"
                           class="flex-1 bg-gray-900 border text-white px-3 py-2 rounded-l-md focus:outline-none" style="border-color:#374151">
                    <button type="submit" class="text-white font-bold py-2 px-4 rounded-r-md" style="background:#0891b2">Search</button>
                </div>
            </form>

            <table class="w-full text-left text-sm">
                <thead style="background:#111827;color:#9ca3af">
                    <tr><th class="p-2">Alliance</th><th class="p-2">Members</th><th class="p-2 text-right">Action</th></tr>
                </thead>
                <tbody class="text-gray-300">
                    <?php if (empty($opp_list)): ?>
                        <tr><td colspan="3" class="p-4 text-center text-gray-400">No alliances found.</td></tr>
                    <?php else: foreach ($opp_list as $row): ?>
                        <tr class="border-t" style="border-color:#374151">
                            <td class="p-2">
                                <div class="flex items-center gap-3">
                                    <img src="<?= e($row['avatar_url']) ?>" alt="" class="w-7 h-7 rounded border" style="border-color:#374151;object-fit:cover">
                                    <span class="text-white">
                                        <span style="color:#06b6d4">[<?= e($row['tag'] ?? '') ?>]</span>
                                        <?= e($row['name']) ?>
                                    </span>
                                </div>
                            </td>
                            <td class="p-2"><?= (int)($row['member_count'] ?? 0) ?></td>
                            <td class="p-2 text-right">
                                <a href="/view_alliance.php?id=<?= (int)$row['id'] ?>"
                                   class="text-white font-bold py-1 px-3 rounded-md text-xs mr-2" style="background:#374151">View</a>

                                <?php if ($has_app_table): ?>
                                    <?php
                                        echo render_join_action_button($row, $viewer_alliance_id, $has_app_table, $pending_app_id, $pending_alliance_id, $csrf_token);
                                    ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <?php if ($opp_pages > 1): ?>
            <div class="mt-3 flex items-center justify-between">
                <div class="text-xs text-gray-400"><?= number_format($opp_total) ?> alliances</div>
                <div class="flex items-center gap-2">
                    <?php $prev = $opp_page > 1 ? $opp_page - 1 : 1; $next = $opp_page < $opp_pages ? $opp_page + 1 : $opp_pages; ?>
                    <a class="px-3 py-1 rounded text-xs" style="border:1px solid #374151" href="<?= $base_public . ($opp_term !== '' ? '&' : '?') . 'opp_page=' . $prev ?>">Prev</a>
                    <a class="px-3 py-1 rounded text-xs" style="border:1px solid #374151" href="<?= $base_public . ($opp_term !== '' ? '&' : '?') . 'opp_page=' . $next ?>">Next</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</section> <!-- /#main -->

<?php include $ROOT . '/template/includes/footer.php';
