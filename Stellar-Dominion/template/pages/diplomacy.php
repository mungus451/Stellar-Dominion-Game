<?php
// ------------------------------
// diplomacy.php (merged file)
// ------------------------------

// (Existing setup code for diplomacy.php)
$active_page = 'diplomacy.php';
require_once __DIR__ . '/../../config/config.php';

// Auth check
$user_id = $_SESSION['id'];
$sql_user = "SELECT u.alliance_id, a.name as alliance_name, ar.order as hierarchy 
             FROM users u 
             JOIN alliance_roles ar ON u.alliance_role_id = ar.id
             JOIN alliances a ON u.alliance_id = a.id
             WHERE u.id = ?";
$stmt_user = $link->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

$is_diplomat = ($user_data && in_array($user_data['hierarchy'], [1, 2]));

// Fetch active wars for treaty proposals
$active_wars = [];
if ($is_diplomat) {
    $sql_wars = "
        SELECT 
            w.id as war_id,
            CASE
                WHEN w.declarer_alliance_id = ? THEN w.declared_against_alliance_id
                ELSE w.declarer_alliance_id
            END as opponent_id,
            a.name as opponent_name,
            a.tag as opponent_tag
        FROM wars w
        JOIN alliances a ON a.id = (CASE WHEN w.declarer_alliance_id = ? THEN w.declared_against_alliance_id ELSE w.declarer_alliance_id END)
        WHERE (w.declarer_alliance_id = ? OR w.declared_against_alliance_id = ?) AND w.status = 'active'
    ";
    $stmt_wars = $link->prepare($sql_wars);
    $stmt_wars->bind_param("iiii", $user_data['alliance_id'], $user_data['alliance_id'], $user_data['alliance_id'], $user_data['alliance_id']);
    $stmt_wars->execute();
    $active_wars = $stmt_wars->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_wars->close();
}

$csrf_token = generate_csrf_token();

// Fetch incoming, active, and outgoing treaties
$incoming_treaties = [];
$active_treaties = [];
$outgoing_treaties = []; // New variable for sent proposals

if ($is_diplomat) {
    $my_alliance_id = $user_data['alliance_id'];
    
    // Fetch incoming treaties
    $sql_incoming = "
        SELECT t.*, a.name as proposer_alliance_name, a.tag as proposer_alliance_tag
        FROM treaties t
        JOIN alliances a ON t.alliance1_id = a.id
        WHERE t.alliance2_id = ? AND t.status = 'proposed'
    ";
    $stmt_incoming = $link->prepare($sql_incoming);
    $stmt_incoming->bind_param("i", $my_alliance_id);
    $stmt_incoming->execute();
    $incoming_treaties = $stmt_incoming->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_incoming->close();

    // Fetch outgoing (pending) treaties
    $sql_outgoing = "
        SELECT t.*, a.name as opponent_alliance_name, a.tag as opponent_alliance_tag
        FROM treaties t
        JOIN alliances a ON t.alliance2_id = a.id
        WHERE t.alliance1_id = ? AND t.status = 'proposed'
    ";
    $stmt_outgoing = $link->prepare($sql_outgoing);
    $stmt_outgoing->bind_param("i", $my_alliance_id);
    $stmt_outgoing->execute();
    $outgoing_treaties = $stmt_outgoing->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_outgoing->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - Diplomacy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">
            <?php include_once __DIR__ . '/../includes/navigation.php'; ?>
            <main class="content-box rounded-lg p-6 max-w-4xl mx-auto mt-4">
                <h1 class="font-title text-3xl text-white mb-4 border-b border-gray-700 pb-3">Diplomacy & Treaties</h1>
                
                <?php if (!$is_diplomat): ?>
                    <p class="text-red-400 text-center">You must be in a high-ranking position within your alliance to access diplomatic functions.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-gray-800 p-4 rounded-lg">
                            <h2 class="font-title text-xl text-cyan-400 mb-3">Propose Peace Treaty</h2>
                            <form action="/war_declaration.php" method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="propose_treaty">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <div>
                                    <label for="opponent_id" class="block text-sm font-bold mb-1">Warring Alliance</label>
                                    <select id="opponent_id" name="opponent_id" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2" required>
                                        <option value="" disabled selected>Select an opponent...</option>
                                        <?php foreach($active_wars as $war): ?>
                                            <option value="<?php echo $war['opponent_id']; ?>">
                                                [<?php echo htmlspecialchars($war['opponent_tag']); ?>] <?php echo htmlspecialchars($war['opponent_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="terms" class="block text-sm font-bold mb-1">Treaty Terms</label>
                                    <textarea id="terms" name="terms" rows="4" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2" required placeholder="e.g., White peace, ceasefire for 7 days."></textarea>
                                </div>
                                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 rounded-lg">Propose Treaty</button>
                            </form>
                        </div>
                        <div class="bg-gray-800 p-4 rounded-lg">
                            <h2 class="font-title text-xl text-cyan-400 mb-3">Active Treaties</h2>
                            <p class="text-sm text-gray-500 italic">This section will display treaties currently in effect. (Functionality to be added).</p>
                        </div>
                    </div>

                    <div class="bg-gray-800 p-4 rounded-lg mt-6">
                        <h2 class="font-title text-xl text-cyan-400 mb-3">Incoming Proposals</h2>
                        <?php if (empty($incoming_treaties)): ?>
                            <p class="text-sm text-gray-500 italic">No pending peace proposals from other alliances.</p>
                        <?php else: ?>
                            <div class="space-y-3">
                            <?php foreach($incoming_treaties as $treaty): ?>
                                <div class="bg-gray-900 p-3 rounded-md border border-gray-700">
                                    <p class="text-sm">Proposal from <strong class="text-white">[<?= htmlspecialchars($treaty['proposer_alliance_tag']) ?>] <?= htmlspecialchars($treaty['proposer_alliance_name']) ?></strong></p>
                                    <blockquote class="text-sm italic border-l-2 border-gray-600 pl-2 my-2">"<?= htmlspecialchars($treaty['terms']) ?>"</blockquote>
                                    <div class="flex justify-end gap-2 mt-2">
                                        <form action="/war_declaration.php" method="POST">
                                            <input type="hidden" name="action" value="decline_treaty">
                                            <input type="hidden" name="treaty_id" value="<?= $treaty['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-1 px-3 text-xs rounded">Decline</button>
                                        </form>
                                        <form action="/war_declaration.php" method="POST">
                                            <input type="hidden" name="action" value="accept_treaty">
                                            <input type="hidden" name="treaty_id" value="<?= $treaty['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-1 px-3 text-xs rounded">Accept</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="bg-gray-800 p-4 rounded-lg mt-6">
                        <h2 class="font-title text-xl text-cyan-400 mb-3">Pending Sent Proposals</h2>
                        <?php if (empty($outgoing_treaties)): ?>
                            <p class="text-sm text-gray-500 italic">You have no pending proposals sent to other alliances.</p>
                        <?php else: ?>
                            <div class="space-y-3">
                            <?php foreach($outgoing_treaties as $treaty): ?>
                                <div class="bg-gray-900 p-3 rounded-md border border-gray-700">
                                    <p class="text-sm">Proposal sent to <strong class="text-white">[<?= htmlspecialchars($treaty['opponent_alliance_name']) ?>] <?= htmlspecialchars($treaty['opponent_alliance_tag']) ?></strong></p>
                                    <blockquote class="text-sm italic border-l-2 border-gray-600 pl-2 my-2">"<?= htmlspecialchars($treaty['terms']) ?>"</blockquote>
                                    <div class="flex justify-end gap-2 mt-2">
                                        <form action="/war_declaration.php" method="POST">
                                            <input type="hidden" name="action" value="cancel_treaty">
                                            <input type="hidden" name="treaty_id" value="<?= $treaty['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-1 px-3 text-xs rounded">Cancel Proposal</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>