<?php
// --- PAGE CONFIGURATION ---
$page_title = 'Attack';
$active_page = 'attack.php';

// --- SESSION AND DATABASE SETUP ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /index.php");
    exit;
}
require_once __DIR__ . '/../../config/config.php';

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/AttackController.php';
    exit;
}

// --- INITIAL SETUP & CRITICAL DATA FETCH ---
date_default_timezone_set('UTC');
$user_id = $_SESSION['id'] ?? 0;

require_once __DIR__ . '/../../src/Game/GameFunctions.php';
process_offline_turns($link, $user_id);

// **CRITICAL FIX**: Fetch the current user's stats *after* turns are processed.
$sql_user = "SELECT u.*, a.tag as alliance_tag FROM users u LEFT JOIN alliances a ON u.alliance_id = a.id WHERE u.id = ?";
$stmt_user = mysqli_prepare($link, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);

// Determine page mode (target list vs. single target view)
$defender_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$treaty_expiration = null;
$defender_info  = null;
$attacker_info = ['alliance_id' => $user_stats['alliance_id']];

if ($defender_id > 0) {
    if ((int)$user_id === (int)$defender_id) {
        header("location: /attack.php");
        exit;
    }

    $sql_defender = "SELECT u.id, u.character_name, u.alliance_id, a.tag as alliance_tag FROM users u LEFT JOIN alliances a ON u.alliance_id = a.id WHERE u.id = ?";
    if ($stmt_defender = $link->prepare($sql_defender)) {
        $stmt_defender->bind_param("i", $defender_id);
        $stmt_defender->execute();
        $defender_info = $stmt_defender->get_result()->fetch_assoc();
        $stmt_defender->close();
    }

    if (!$defender_info) {
        $_SESSION['attack_error'] = "The specified target does not exist.";
        header("location: /attack.php");
        exit;
    }

    if (!empty($attacker_info['alliance_id']) && !empty($defender_info['alliance_id'])) {
        $alliance1_id = (int)$attacker_info['alliance_id'];
        $alliance2_id = (int)$defender_info['alliance_id'];
        $sql_treaty = "SELECT expiration_date FROM treaties WHERE ((alliance1_id = ? AND alliance2_id = ?) OR (alliance1_id = ? AND alliance2_id = ?)) AND expiration_date > NOW() AND status IN ('proposed', 'active') ORDER BY expiration_date DESC LIMIT 1";
        if ($stmt_treaty = $link->prepare($sql_treaty)) {
            $stmt_treaty->bind_param("iiii", $alliance1_id, $alliance2_id, $alliance2_id, $alliance1_id);
            $stmt_treaty->execute();
            if ($row = $stmt_treaty->get_result()->fetch_assoc()) {
                $treaty_expiration = $row['expiration_date'];
            }
            $stmt_treaty->close();
        }
    }
}

// --- UNIVERSAL HEADER ---
$csrf_token = generate_csrf_token('attack_action');
include_once __DIR__ . '/../includes/header.php';
?>

<?php if ($defender_id > 0 && $defender_info): ?>
    <main class="content-box rounded-lg p-6 max-w-2xl mx-auto mt-4"
          x-data="singleTarget({ treaty: '<?php echo $treaty_expiration ? htmlspecialchars($treaty_expiration, ENT_QUOTES, 'UTF-8') : '' ?>' })"
          x-init="init()">
        <h1 class="font-title text-3xl text-white mb-2">
            Attacking: <?php echo htmlspecialchars($defender_info['character_name']); ?>
            <?php if (isset($defender_info['alliance_tag'])): ?>
                <span class="text-cyan-400">[<?php echo htmlspecialchars($defender_info['alliance_tag']); ?>]</span>
                <?php if ($treaty_expiration): ?>
                    <span id="peace-timer" class="text-yellow-400 text-lg ml-2" x-cloak x-show="hasTreaty" x-text="peaceLabel" title="A peace treaty is active. Attacking will have reduced effectiveness."></span>
                <?php endif; ?>
            <?php endif; ?>
        </h1>

        <?php if(isset($_SESSION['attack_error'])): ?>
            <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4">
                <?php echo htmlspecialchars($_SESSION['attack_error']); unset($_SESSION['attack_error']); ?>
            </div>
        <?php endif; ?>

        <p class="text-sm text-gray-500 mb-4">You are about to launch an assault. Specify the number of attack turns to commit.</p>

        <form action="/attack.php" method="POST" class="space-y-4">
            <input type="hidden" name="defender_id" value="<?php echo (int)$defender_id; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="csrf_action" value="attack_action">
            
            <div>
                <label for="attack_turns_single" class="block text-lg font-title text-cyan-400 mb-2">Attack Turns</label>
                <input id="attack_turns_single" type="number" name="attack_turns" value="1" min="1" max="<?php echo (int)min(10, ($user_stats['attack_turns'] ?? 1)); ?>" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1 text-center" title="Turns to use">
            </div>

            <div class="border-t border-gray-700 pt-4">
                <button type="submit" class="w-full bg-red-700 hover:bg-red-800 text-white font-bold py-3 rounded-lg text-xl font-title tracking-wider">
                    Launch Attack
                </button>
            </div>
        </form>
    </main>
<?php else: ?>
    <aside class="lg:col-span-1 space-y-4">
        <?php
            $user_xp = $user_stats['experience'] ?? 0;
            $user_level = $user_stats['level'] ?? 1;
            $now = new DateTime('now', new DateTimeZone('UTC'));
            $turn_interval_minutes = 10;
            $last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
            $seconds_until_next_turn = ($turn_interval_minutes * 60) - (($now->getTimestamp() - $last_updated->getTimestamp()) % ($turn_interval_minutes * 60));
            $minutes_until_next_turn = (int)floor($seconds_until_next_turn / 60);
            $seconds_remainder = $seconds_until_next_turn % 60;
            include_once __DIR__ . '/../includes/advisor.php';
        ?>
    </aside>

    <main class="lg:col-span-3">
        <?php if(isset($_SESSION['attack_error'])): ?>
            <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4">
                <?php echo htmlspecialchars($_SESSION['attack_error']); unset($_SESSION['attack_error']); ?>
            </div>
        <?php endif; ?>
        <div class="content-box rounded-lg p-4">
            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Target List</h3>
            <div class="overflow-x-auto">
                <table class="attack-table w-full text-sm text-left">
                    <thead class="bg-gray-800">
                        <tr>
                            <th class="p-2">Rank</th>
                            <th class="p-2">Username</th>
                            <th class="p-2">Credits</th>
                            <th class="p-2">Army Size</th>
                            <th class="p-2">Level</th>
                            <th class="p-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $sql_targets = "SELECT u.*, a.tag as alliance_tag, bl.wins, bl.losses FROM users u LEFT JOIN alliances a ON u.alliance_id = a.id LEFT JOIN (SELECT attacker_id, SUM(CASE WHEN outcome = 'victory' THEN 1 ELSE 0 END) as wins, SUM(CASE WHEN outcome = 'defeat' THEN 1 ELSE 0 END) as losses FROM battle_logs GROUP BY attacker_id) bl ON u.id = bl.attacker_id";
                            $targets_result = mysqli_query($link, $sql_targets);
                            $ranked_targets = [];
                            if ($targets_result) {
                                while ($target = mysqli_fetch_assoc($targets_result)) {
                                    $wins = (int)($target['wins'] ?? 0); $losses = (int)($target['losses'] ?? 0);
                                    $win_loss_ratio = ($losses > 0) ? ($wins / $losses) : $wins;
                                    $army_size = (int)$target['soldiers'] + (int)$target['guards'] + (int)$target['sentries'] + (int)$target['spies'];
                                    $worker_income = (int)$target['workers'] * 50;
                                    $total_base_income = 5000 + $worker_income;
                                    $wealth_bonus = 1 + ((int)$target['wealth_points'] * 0.01);
                                    $income_per_turn = (int)floor($total_base_income * $wealth_bonus);
                                    $target['rank_score'] = (($target['experience'] * 0.1) + ($army_size * 2) + ($win_loss_ratio * 1000) + ($target['workers'] * 5) + ($income_per_turn * 0.05) + ($target['fortification_level'] * 500));
                                    $target['army_size'] = $army_size;
                                    $ranked_targets[] = $target;
                                }
                            }
                            usort($ranked_targets, fn($a, $b) => ($b['rank_score'] ?? 0) <=> ($a['rank_score'] ?? 0));
                            $rank = 1;
                            foreach ($ranked_targets as &$t) $t['rank'] = $rank++;
                            unset($t);

                            foreach ($ranked_targets as $target):
                                $is_ally = (!empty($user_stats['alliance_id']) && $user_stats['alliance_id'] == $target['alliance_id']);
                                $is_self = ($target['id'] == $user_id);
                            ?>
                            <tr class="border-t border-gray-700 hover:bg-gray-700/50 <?php if ($is_self) echo 'bg-cyan-900/30'; ?>">
                                <td class="p-2 font-bold text-cyan-400" data-label="Rank"><?php echo $target['rank']; ?></td>
                                <td class="p-2" data-label="Username">
                                    <div class="flex items-center">
                                        <button type="button" class="profile-modal-trigger flex-shrink-0" data-profile-id="<?php echo (int)$target['id']; ?>">
                                            <img src="<?php echo htmlspecialchars($target['avatar_path'] ?: 'https://via.placeholder.com/40'); ?>" alt="Avatar" class="w-10 h-10 rounded-md object-cover">
                                        </button>
                                        <div class="ml-3 min-w-0">
                                            <p class="font-bold text-white truncate"><?php echo htmlspecialchars($target['character_name']); ?></p>
                                            <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars(strtoupper($target['race'] . ' ' . $target['class'])); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-2" data-label="Credits"><?php echo number_format($target['credits']); ?></td>
                                <td class="p-2" data-label="Army Size"><?php echo number_format($target['army_size']); ?></td>
                                <td class="p-2" data-label="Level"><?php echo $target['level']; ?></td>
                                <td class="p-2 text-right action-cell" data-label="Action">
                                    <?php if ($is_self): ?>
                                        <span class="text-gray-500 text-xs italic">This is you</span>
                                    <?php elseif ($is_ally): ?>
                                        <a href="/alliance_transfer.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-1 px-3 rounded-md text-xs">Transfer</a>
                                    <?php else: ?>
                                        <form action="/attack.php" method="POST" class="flex items-center justify-end space-x-2">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <input type="hidden" name="csrf_action" value="attack_action">
                                            <input type="hidden" name="defender_id" value="<?php echo (int)$target['id']; ?>">
                                            <input type="number" name="attack_turns" value="1" min="1" max="<?php echo (int)min(10, ($user_stats['attack_turns'] ?? 1)); ?>" class="bg-gray-900 border border-gray-600 rounded-md w-16 text-center text-xs p-1" title="Turns to use">
                                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-1 px-3 rounded-md text-xs">Attack</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
<?php endif; ?>

<div id="profile-modal" class="hidden fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center p-4">
    <div id="profile-modal-content" class="bg-dark-translucent backdrop-blur-md rounded-lg shadow-2xl w-full max-w-lg mx-auto border border-cyan-400/30 relative">
        <div class="text-center p-8">Loading profile...</div>
    </div>
</div>

<script>
    /* ---------- Alpine helpers (Complete and Un-omitted) ---------- */
    function fmtMMSS(msRemaining){
        const m = Math.floor((msRemaining % (1000*60*60)) / (1000*60));
        const s = Math.floor((msRemaining % (1000*60)) / 1000);
        return `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    }

    function singleTarget({treaty}) {
        return {
            hasTreaty: Boolean(treaty),
            peaceLabel: '',
            _timer: null,
            init(){
                if(!this.hasTreaty) return;
                const exp = new Date((treaty || '').replace(' ', 'T') + 'Z').getTime();
                const tick = () => {
                    const now = Date.now();
                    const dist = exp - now;
                    if (dist <= 0) {
                        this.hasTreaty = false;
                        clearInterval(this._timer);
                    } else {
                        this.peaceLabel = `Ceasefire: ${fmtMMSS(dist)}`;
                    }
                };
                tick();
                this._timer = setInterval(tick, 1000);
            }
        }
    }

    function unitsForm(){
        return {
            fighters:0, bombers:0, frigates:0, destroyers:0,
            get total(){ return (this.fighters||0)+(this.bombers||0)+(this.frigates||0)+(this.destroyers||0); }
        }
    }
</script>

<?php
// --- INCLUDE UNIVERSAL FOOTER ---
include_once __DIR__ . '/../includes/footer.php';
?>