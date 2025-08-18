<?php
// --- SESSION AND DATABASE SETUP ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /index.php");
    exit;
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php'; // For level up definitions

$user_id = $_SESSION['id'];

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Protect against CSRF attacks
    protect_csrf();

    // This action is now for the single "Spend Points" button
    $action = $_POST['action'] ?? '';

    if ($action === 'spend_points') {
        mysqli_begin_transaction($link);
        try {
            // Get user's current level up points
            $sql_user = "SELECT level_up_points FROM users WHERE id = ? FOR UPDATE";
            $stmt_user = mysqli_prepare($link, $sql_user);
            mysqli_stmt_bind_param($stmt_user, "i", $user_id);
            mysqli_stmt_execute($stmt_user);
            $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
            mysqli_stmt_close($stmt_user);

            if (!$user) {
                throw new Exception("Could not retrieve user data.");
            }

            // Points to spend from the form, keys now match input names
            $points_to_spend = [
                'strength_points' => (int)($_POST['strength_points'] ?? 0),
                'constitution_points' => (int)($_POST['constitution_points'] ?? 0),
                'wealth_points' => (int)($_POST['wealth_points'] ?? 0),
                'dexterity_points' => (int)($_POST['dexterity_points'] ?? 0),
                'charisma_points' => (int)($_POST['charisma_points'] ?? 0)
            ];

            $total_points_to_spend = array_sum($points_to_spend);

            if ($total_points_to_spend <= 0) {
                throw new Exception("You did not allocate any points to spend.");
            }

            if ($total_points_to_spend > $user['level_up_points']) {
                throw new Exception("You are trying to spend more points than you have available.");
            }

            // Build the dynamic part of the SQL query
            $sql_parts = [];
            foreach ($points_to_spend as $stat => $points) {
                if ($points > 0) {
                    // The stat name from the array key is already the correct column name
                    $sql_parts[] = "`$stat` = `$stat` + $points";
                }
            }

            if (!empty($sql_parts)) {
                $sql_update_query = "UPDATE users SET " . implode(', ', $sql_parts) . ", `level_up_points` = `level_up_points` - $total_points_to_spend WHERE id = ?";
                $stmt_update = mysqli_prepare($link, $sql_update_query);
                mysqli_stmt_bind_param($stmt_update, "i", $user_id);
                mysqli_stmt_execute($stmt_update);
                mysqli_stmt_close($stmt_update);
                $_SESSION['level_message'] = "Successfully spent " . $total_points_to_spend . " point" . ($total_points_to_spend == 1 ? "" : "s") . "!";
            }

            mysqli_commit($link);

        } catch (Exception $e) {
            mysqli_rollback($link);
            $_SESSION['level_error'] = "Error: " . $e->getMessage();
        }
    }

    // Redirect back to the levels page to prevent form resubmission
    header("Location: levels.php");
    exit;
}
// --- END FORM HANDLING ---


// --- DATA FETCHING FOR PAGE DISPLAY ---
$sql_fetch = "SELECT level, experience, level_up_points, strength_points, constitution_points, wealth_points, dexterity_points, charisma_points, credits, untrained_citizens, attack_turns, last_updated FROM users WHERE id = ?";
$stmt_fetch = mysqli_prepare($link, $sql_fetch);
mysqli_stmt_bind_param($stmt_fetch, "i", $user_id);
mysqli_stmt_execute($stmt_fetch);
$user_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_fetch));
mysqli_stmt_close($stmt_fetch);


// Timer Calculations for Next Turn
$now = new DateTime('now', new DateTimeZone('UTC'));
$turn_interval_minutes = 10;
$last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
$seconds_until_next_turn = ($turn_interval_minutes * 60) - (($now->getTimestamp() - $last_updated->getTimestamp()) % ($turn_interval_minutes * 60));
$minutes_until_next_turn = floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;

$active_page = 'levels.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Starlight Dominion - Commander Level</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">

            <?php include_once __DIR__ . '/../includes/navigation.php'; ?>
            
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
                <aside class="lg:col-span-1 space-y-4">
                    <?php 
                        // Define variables needed by the advisor include file, just like on dashboard.php
                        $user_xp = $user_stats['experience'];
                        $user_level = $user_stats['level'];
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

                    <form action="" method="POST">
                        <?php echo csrf_token_field('spend_points'); ?>
                        <div class="content-box rounded-lg p-4">
                            <p class="text-center text-lg">You currently have <span class="font-bold text-cyan-400"><?php echo $user_stats['level_up_points']; ?></span> proficiency points available.</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <!-- Strength -->
                            <div class="content-box rounded-lg p-4">
                                <h3 class="font-title text-lg text-red-400">Strength (Offense)</h3>
                                <p class="text-sm">Current Bonus: <?php echo $user_stats['strength_points']; ?>%</p>
                                <label for="strength_points" class="block text-xs mt-2">Add:</label>
                                <input type="number" name="strength_points" min="0" value="0" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-2 py-1 text-white focus:outline-none focus:ring-1 focus:ring-cyan-500 point-input">
                            </div>
                            <!-- Constitution -->
                            <div class="content-box rounded-lg p-4">
                                <h3 class="font-title text-lg text-green-400">Constitution (Defense)</h3>
                                <p class="text-sm">Current Bonus: <?php echo $user_stats['constitution_points']; ?>%</p>
                                <label for="constitution_points" class="block text-xs mt-2">Add:</label>
                                <input type="number" name="constitution_points" min="0" value="0" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-2 py-1 text-white focus:outline-none focus:ring-1 focus:ring-cyan-500 point-input">
                            </div>
                            <!-- Wealth -->
                            <div class="content-box rounded-lg p-4">
                                <h3 class="font-title text-lg text-yellow-400">Wealth (Income)</h3>
                                <p class="text-sm">Current Bonus: <?php echo $user_stats['wealth_points']; ?>%</p>
                                <label for="wealth_points" class="block text-xs mt-2">Add:</label>
                                <input type="number" name="wealth_points" min="0" value="0" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-2 py-1 text-white focus:outline-none focus:ring-1 focus:ring-cyan-500 point-input">
                            </div>
                            <!-- Dexterity -->
                            <div class="content-box rounded-lg p-4">
                                <h3 class="font-title text-lg text-blue-400">Dexterity (Sentry/Spy)</h3>
                                <p class="text-sm">Current Bonus: <?php echo $user_stats['dexterity_points']; ?>%</p>
                                <label for="dexterity_points" class="block text-xs mt-2">Add:</label>
                                <input type="number" name="dexterity_points" min="0" value="0" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-2 py-1 text-white focus:outline-none focus:ring-1 focus:ring-cyan-500 point-input">
                            </div>
                            <!-- Charisma -->
                            <div class="content-box rounded-lg p-4 md:col-span-2">
                                <h3 class="font-title text-lg text-purple-400">Charisma (Reduced Prices)</h3>
                                <p class="text-sm">Current Bonus: <?php echo $user_stats['charisma_points']; ?>%</p>
                                <label for="charisma_points" class="block text-xs mt-2">Add:</label>
                                <input type="number" name="charisma_points" min="0" value="0" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-2 py-1 text-white focus:outline-none focus:ring-1 focus:ring-cyan-500 point-input">
                            </div>
                        </div>

                        <div class="content-box rounded-lg p-4 mt-4 flex justify-between items-center">
                            <p>Total Points to Spend: <span id="total-to-spend" class="font-bold text-white">0</span></p>
                            <button type="submit" name="action" value="spend_points" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg <?php if($user_stats['level_up_points'] < 1) echo 'opacity-50 cursor-not-allowed'; ?>" <?php if($user_stats['level_up_points'] < 1) echo 'disabled'; ?>>
                                Spend Points
                            </button>
                        </div>
                    </form>
                </main>
            </div>

        </div>
    </div>
    <script>
        // JavaScript to calculate total points to spend in real-time
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.point-input');
            const totalDisplay = document.getElementById('total-to-spend');
            
            function updateTotal() {
                let total = 0;
                inputs.forEach(input => {
                    total += parseInt(input.value) || 0;
                });
                totalDisplay.textContent = total;
            }

            inputs.forEach(input => {
                input.addEventListener('input', updateTotal);
            });
        });
    </script>
</body>
</html>
