<?php
$active_page = 'war_declaration.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Game/GameData.php'; // For presets

// Fetch user's alliance and role information
$user_id = $_SESSION['id'];
$sql = "SELECT u.alliance_id, ar.order as hierarchy FROM users u JOIN alliance_roles ar ON u.alliance_role_id = ar.id WHERE u.id = ?";
$stmt = $link->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

if (!$user_data || !in_array($user_data['hierarchy'], [1, 2])) {
    $_SESSION['alliance_error'] = "You do not have the required permissions to declare war.";
    header("Location: /alliance");
    exit;
}

// Fetch all alliances except the user's own
$sql_alliances = "SELECT id, name, tag FROM alliances WHERE id != ?";
$stmt_alliances = $link->prepare($sql_alliances);
$stmt_alliances->bind_param("i", $user_data['alliance_id']);
$stmt_alliances->execute();
$alliances = $stmt_alliances->get_result()->fetch_all(MYSQLI_ASSOC);

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Starlight Dominion - War Declaration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">
            
            <?php include_once __DIR__ . '/../includes/navigation.php'; ?>

            <div class="content-box rounded-lg p-6 max-w-4xl mx-auto">
                <h1 class="font-title text-3xl text-white mb-4 border-b border-gray-700 pb-3">Initiate Hostilities</h1>
                
                <form id="warForm" action="/war_declaration.php" method="POST" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="declare_war">

                    <div>
                        <label for="alliance_id" class="block mb-2 text-lg font-title text-cyan-400">Step 1: Select Target</label>
                        <select class="w-full px-3 py-2 text-gray-300 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:border-cyan-500" id="alliance_id" name="alliance_id" required>
                            <option value="" disabled selected>Choose an alliance to declare war upon...</option>
                            <?php foreach ($alliances as $alliance) : ?>
                                <option value="<?= $alliance['id'] ?>">[<?= htmlspecialchars($alliance['tag']) ?>] <?= htmlspecialchars($alliance['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="casus_belli" class="block mb-2 text-lg font-title text-cyan-400">Step 2: Justify Your War (Casus Belli)</label>
                        <select class="w-full px-3 py-2 text-gray-300 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:border-cyan-500" id="casus_belli" name="casus_belli" required>
                            <option value="" disabled selected>Select a reason...</option>
                            <?php foreach ($casus_belli_presets as $key => $details): ?>
                                <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($details['name']); ?> - <?php echo htmlspecialchars($details['description']); ?></option>
                            <?php endforeach; ?>
                            <option value="custom">Custom Reason...</option>
                        </select>
                        <textarea id="custom_casus_belli" name="custom_casus_belli" rows="3" class="hidden w-full px-3 py-2 mt-2 text-gray-300 bg-gray-900 border border-gray-600 rounded-lg" placeholder="Enter your reason for war (5-140 characters)"></textarea>
                    </div>

                    <div>
                        <label for="war_goal" class="block mb-2 text-lg font-title text-cyan-400">Step 3: Define Your War Goal</label>
                        <select class="w-full px-3 py-2 text-gray-300 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:border-cyan-500" id="war_goal" name="war_goal" required>
                             <option value="" disabled selected>Select a primary objective...</option>
                            <?php foreach ($war_goal_presets as $key => $details): ?>
                                <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($details['name']); ?> - <?php echo htmlspecialchars($details['description']); ?></option>
                            <?php endforeach; ?>
                            <option value="custom">Custom Goal...</option>
                        </select>
                        <div id="custom_goal_container" class="hidden mt-2 space-y-2">
                             <input type="text" name="custom_war_goal" class="w-full px-3 py-2 text-gray-300 bg-gray-900 border border-gray-600 rounded-lg" placeholder="Enter custom goal name (5-100 characters)">
                             <select name="custom_goal_metric" class="w-full px-3 py-2 text-gray-300 bg-gray-900 border border-gray-600 rounded-lg">
                                 <option value="" disabled selected>Select metric to track for custom goal...</option>
                                 <option value="credits_plundered">Credits Plundered</option>
                                 <option value="units_killed">Units Killed</option>
                                 <option value="structures_destroyed">Structure Damage</option>
                                 <option value="prestige_change">Prestige Gained</option>
                             </select>
                        </div>
                    </div>

                    <div class="border-t border-gray-700 pt-4 text-center">
                        <button type="submit" class="w-full md:w-auto px-10 py-3 font-bold text-white bg-red-700 rounded-lg hover:bg-red-800 text-xl font-title tracking-wider">Declare War</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const casusBelliSelect = document.getElementById('casus_belli');
            const customCasusBelliText = document.getElementById('custom_casus_belli');
            const warGoalSelect = document.getElementById('war_goal');
            const customGoalContainer = document.getElementById('custom_goal_container');

            casusBelliSelect.addEventListener('change', function() {
                if (this.value === 'custom') {
                    customCasusBelliText.classList.remove('hidden');
                    customCasusBelliText.required = true;
                } else {
                    customCasusBelliText.classList.add('hidden');
                    customCasusBelliText.required = false;
                }
            });

            warGoalSelect.addEventListener('change', function() {
                if (this.value === 'custom') {
                    customGoalContainer.classList.remove('hidden');
                    customGoalContainer.querySelector('input').required = true;
                    customGoalContainer.querySelector('select').required = true;
                } else {
                    customGoalContainer.classList.add('hidden');
                    customGoalContainer.querySelector('input').required = false;
                    customGoalContainer.querySelector('select').required = false;
                }
            });
        });
    </script>
</body>
</html>