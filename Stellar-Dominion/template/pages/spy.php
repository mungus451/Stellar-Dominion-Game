<?php
/**
 * spy.php
 *
 * This page allows players to conduct spy missions against other players.
 */

$active_page = 'spy.php';
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/SpyController.php';
    exit;
}

date_default_timezone_set('UTC');
$csrf_token = generate_csrf_token();
$user_id = $_SESSION['id'] ?? 0;

require_once __DIR__ . '/../../src/Game/GameFunctions.php';
process_offline_turns($link, $_SESSION["id"]);

// Fetch all users for the target list
$sql_targets = "SELECT id, character_name, level, race, class, spies, sentries FROM users WHERE id != ?";
$stmt_targets = mysqli_prepare($link, $sql_targets);
mysqli_stmt_bind_param($stmt_targets, "i", $user_id);
mysqli_stmt_execute($stmt_targets);
$targets_result = mysqli_stmt_get_result($stmt_targets);
$targets = mysqli_fetch_all($targets_result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt_targets);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - Spy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
<div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
    <div class="container mx-auto p-4 md:p-8">
        <?php include_once __DIR__ .  '/../includes/navigation.php'; ?>
        <main class="content-box rounded-lg p-6 max-w-4xl mx-auto mt-4">
            <h1 class="font-title text-3xl text-white mb-4">Spy Missions</h1>
            <?php if(isset($_SESSION['spy_error'])): ?>
                <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center mb-4">
                    <?php echo htmlspecialchars($_SESSION['spy_error']); unset($_SESSION['spy_error']); ?>
                </div>
            <?php endif; ?>
            <div x-data="{
                tab: 'intelligence',
                defenderId: null,
                defenderName: '',
                selectTarget(id, name) {
                    this.defenderId = id;
                    this.defenderName = name;
                    document.getElementById('spy-modal').classList.remove('hidden');
                }
            }">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-800">
                            <tr>
                                <th class="p-2">Commander</th>
                                <th class="p-2">Level</th>
                                <th class="p-2">Race</th>
                                <th class="p-2">Class</th>
                                <th class="p-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($targets as $target): ?>
                            <tr class="border-t border-gray-700 hover:bg-gray-700/50">
                                <td class="p-2 font-bold text-white"><?= htmlspecialchars($target['character_name']) ?></td>
                                <td class="p-2"><?= $target['level'] ?></td>
                                <td class="p-2"><?= htmlspecialchars($target['race']) ?></td>
                                <td class="p-2"><?= htmlspecialchars($target['class']) ?></td>
                                <td class="p-2 text-right">
                                    <button @click="selectTarget(<?= $target['id'] ?>, '<?= htmlspecialchars($target['character_name']) ?>')" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-1 px-3 rounded-md text-xs">Spy</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div id="spy-modal" class="hidden fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center p-4">
                    <div class="bg-gray-800 rounded-lg shadow-2xl w-full max-w-lg mx-auto border border-purple-400/30 relative">
                        <div class="p-6">
                            <h2 class="font-title text-2xl text-white mb-2">Spy on <span x-text="defenderName"></span></h2>
                            <div class="border-b border-gray-600 mb-4">
                                <nav class="-mb-px flex space-x-4" aria-label="Tabs">
                                    <a href="#" @click.prevent="tab = 'intelligence'" :class="tab === 'intelligence' ? 'border-purple-400 text-white' : 'border-transparent text-gray-400 hover:text-white'" class="py-2 px-4 border-b-2 font-medium">Intelligence</a>
                                    <a href="#" @click.prevent="tab = 'assassination'" :class="tab === 'assassination' ? 'border-purple-400 text-white' : 'border-transparent text-gray-400 hover:text-white'" class="py-2 px-4 border-b-2 font-medium">Assassination</a>
                                    <a href="#" @click.prevent="tab = 'sabotage'" :class="tab === 'sabotage' ? 'border-purple-400 text-white' : 'border-transparent text-gray-400 hover:text-white'" class="py-2 px-4 border-b-2 font-medium">Sabotage</a>
                                </nav>
                            </div>

                            <form action="/spy.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="defender_id" :value="defenderId">
                                <input type="hidden" name="mission_type" :value="tab">

                                <div x-show="tab === 'intelligence'">
                                    <p>Gather intelligence on the target's empire. A random 5 data points will be revealed on success.</p>
                                </div>
                                <div x-show="tab === 'assassination'">
                                    <p>Attempt to assassinate a portion of the target's units.</p>
                                    <div class="mt-4">
                                        <label for="assassination_target" class="block text-sm font-medium text-gray-300">Target Unit Type</label>
                                        <select name="assassination_target" id="assassination_target" class="mt-1 block w-full bg-gray-900 border border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-purple-500 focus:border-purple-500 sm:text-sm">
                                            <option value="workers">Workers</option>
                                            <option value="soldiers">Soldiers</option>
                                            <option value="guards">Guards</option>
                                        </select>
                                    </div>
                                </div>
                                <div x-show="tab === 'sabotage'">
                                    <p>Sabotage the target's empire foundation, causing damage.</p>
                                </div>

                                <div class="mt-4">
                                    <label for="attack_turns" class="block text-sm font-medium text-gray-300">Attack Turns (1-10)</label>
                                    <input type="number" name="attack_turns" id="attack_turns" value="1" min="1" max="10" class="mt-1 block w-full bg-gray-900 border border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-purple-500 focus:border-purple-500 sm:text-sm">
                                </div>

                                <div class="mt-6 flex justify-end space-x-4">
                                    <button type="button" @click="document.getElementById('spy-modal').classList.add('hidden')" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">Cancel</button>
                                    <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg">Launch Mission</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>