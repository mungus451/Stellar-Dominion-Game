<?php
// --- PAGE CONFIGURATION ---
$page_title = 'Commander Level';
$active_page = 'levels.php';

// --- SESSION AND DATABASE SETUP ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /index.php");
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Services/StateService.php'; // centralized reads/timers
require_once __DIR__ . '/../includes/advisor_hydration.php';

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // protect_csrf() reads 'csrf_token' and 'csrf_action' from the POST data
    protect_csrf(); 

    $action = $_POST['action'] ?? '';

    if ($action === 'spend_points') {
        $user_id = (int)$_SESSION['id'];
        mysqli_begin_transaction($link);
        try {
            $sql_user = "SELECT level_up_points FROM users WHERE id = ? FOR UPDATE";
            $stmt_user = mysqli_prepare($link, $sql_user);
            mysqli_stmt_bind_param($stmt_user, "i", $user_id);
            mysqli_stmt_execute($stmt_user);
            $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
            mysqli_stmt_close($stmt_user);

            if (!$user) {
                throw new Exception("Could not retrieve user data.");
            }

            $points_to_spend = [
                'strength_points'    => (int)($_POST['strength_points'] ?? 0),
                'constitution_points'=> (int)($_POST['constitution_points'] ?? 0),
                'wealth_points'      => (int)($_POST['wealth_points'] ?? 0),
                'dexterity_points'   => (int)($_POST['dexterity_points'] ?? 0),
                'charisma_points'    => (int)($_POST['charisma_points'] ?? 0)
            ];

            $total_points_to_spend = array_sum($points_to_spend);

            if ($total_points_to_spend <= 0) {
                throw new Exception("You did not allocate any points to spend.");
            }
            if ($total_points_to_spend > (int)$user['level_up_points']) {
                throw new Exception("You are trying to spend more points than you have available.");
            }

            $sql_parts = [];
            foreach ($points_to_spend as $stat => $points) {
                if ($points > 0) {
                    $sql_parts[] = "`$stat` = `$stat` + $points";
                }
            }

            if (!empty($sql_parts)) {
                $sql_update_query = "UPDATE users SET " . implode(', ', $sql_parts) . ", `level_up_points` = `level_up_points` - $total_points_to_spend WHERE id = ?";
                $stmt_update = mysqli_prepare($link, $sql_update_query);
                mysqli_stmt_bind_param($stmt_update, "i", $user_id);
                mysqli_stmt_execute($stmt_update);
                mysqli_stmt_close($stmt_update);
                $_SESSION['level_message'] = "Successfully spent " . $total_points_to_spend . " points!";
            }
            mysqli_commit($link);
        } catch (Exception $e) {
            mysqli_rollback($link);
            $_SESSION['level_error'] = "Error: " . $e->getMessage();
        }
    }
    header("Location: levels.php");
    exit;
}
// --- END FORM HANDLING ---

// --- DATA FETCHING AND PREPARATION (via StateService) ---
$user_id = (int)$_SESSION['id'];
$needed_fields = [
    'level_up_points',
    'strength_points','constitution_points','wealth_points','dexterity_points','charisma_points'
];
// Also processes offline turns before reading
$user_stats = ss_process_and_get_user_state($link, $user_id, $needed_fields);

// --- INCLUDE UNIVERSAL HEADER ---
include_once __DIR__ . '/../includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php 
        include_once __DIR__ . '/../includes/advisor.php'; 
    ?>
</aside>
        
<main class="lg:col-span-3 space-y-4">
    <?php if(isset($_SESSION['level_message'])): ?>
        <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
            <?php echo htmlspecialchars($_SESSION['level_message']); unset($_SESSION['level_message']); ?>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['level_error'])): ?>
        <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
            <?php echo htmlspecialchars($_SESSION['level_error']); unset($_SESSION['level_error']); ?>
        </div>
    <?php endif; ?>

    <form action="/levels.php" method="POST"
            x-data="{
                max: <?php echo (int)$user_stats['level_up_points']; ?>,
                s: 0, c: 0, w: 0, d: 0, ch: 0,
                get total(){ return (Number(this.s)||0)+(Number(this.c)||0)+(Number(this.w)||0)+(Number(this.d)||0)+(Number(this.ch)||0); }
            }">
        
        <?php echo csrf_token_field('spend_points'); ?>
        
        <div class="content-box rounded-lg p-4">
            <p class="text-center text-lg">
                You currently have
                <span class="font-bold text-cyan-400"><?php echo (int)$user_stats['level_up_points']; ?></span>
                proficiency points available.
            </p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div class="content-box rounded-lg p-4">
                <h3 class="font-title text-lg text-red-400">Strength (Offense)</h3>
                <p class="text-sm">Current Bonus: <?php echo (int)$user_stats['strength_points']; ?>%</p>
                <label for="strength_points" class="block text-xs mt-2">Add:</label>
                <input type="number" name="strength_points" min="0" value="0" x-model.number="s" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-2 py-1 text-white focus:outline-none focus:ring-1 focus:ring-cyan-500 point-input">
            </div>
            <div class="content-box rounded-lg p-4">
                <h3 class="font-title text-lg text-green-400">Constitution (Defense)</h3>
                <p class="text-sm">Current Bonus: <?php echo (int)$user_stats['constitution_points']; ?>%</p>
                <label for="constitution_points" class="block text-xs mt-2">Add:</label>
                <input type="number" name="constitution_points" min="0" value="0" x-model.number="c" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-2 py-1 text-white focus:outline-none focus:ring-1 focus:ring-cyan-500 point-input">
            </div>
            <div class="content-box rounded-lg p-4">
                <h3 class="font-title text-lg text-yellow-400">Wealth (Income)</h3>
                <p class="text-sm">Current Bonus: <?php echo (int)$user_stats['wealth_points']; ?>%</p>
                <label for="wealth_points" class="block text-xs mt-2">Add:</label>
                <input type="number" name="wealth_points" min="0" value="0" x-model.number="w" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-2 py-1 text-white focus:outline-none focus:ring-1 focus:ring-cyan-500 point-input">
            </div>
            <div class="content-box rounded-lg p-4">
                <h3 class="font-title text-lg text-blue-400">Dexterity (Sentry/Spy)</h3>
                <p class="text-sm">Current Bonus: <?php echo (int)$user_stats['dexterity_points']; ?>%</p>
                <label for="dexterity_points" class="block text-xs mt-2">Add:</label>
                <input type="number" name="dexterity_points" min="0" value="0" x-model.number="d" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-2 py-1 text-white focus:outline-none focus:ring-1 focus:ring-cyan-500 point-input">
            </div>
            <div class="content-box rounded-lg p-4 md:col-span-2">
                <h3 class="font-title text-lg text-purple-400">Charisma (Reduced Prices)</h3>
                <p class="text-sm">Current Bonus: <?php echo (int)$user_stats['charisma_points']; ?>%</p>
                <label for="charisma_points" class="block text-xs mt-2">Add:</label>
                <input type="number" name="charisma_points" min="0" value="0" x-model.number="ch" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-2 py-1 text-white focus:outline-none focus:ring-1 focus:ring-cyan-500 point-input">
            </div>
        </div>

        <div class="content-box rounded-lg p-4 mt-4 flex justify-between items-center">
            <p>
                Total Points to Spend:
                <span id="total-to-spend" class="font-bold text-white" x-text="total">0</span>
            </p>
            <button type="submit" name="action" value="spend_points"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg <?php if($user_stats['level_up_points'] < 1) echo 'opacity-50 cursor-not-allowed'; ?>"
                    <?php if($user_stats['level_up_points'] < 1) echo 'disabled'; ?>
                    x-bind:disabled="total <= 0 || total > max">
                Spend Points
            </button>
        </div>
    </form>
</main>

<?php
// Include the universal footer
include_once __DIR__ . '/../includes/footer.php';
?>
