<?php
// --- SESSION / ENV ---
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set('UTC');

require_once __DIR__ . '/../../config/config.php';

$is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;

// --- SEO and Page Config ---
$page_title       = 'Leaderboards';
$page_description = 'Commanders ranked by level, wealth, population, army size — plus all-time top plunderers and highest fatigue casualties.';
$page_keywords    = 'leaderboards, rankings, plunder, fatigue, wealth, army size, population, level';
$active_page      = 'stats.php';

// Initialize variables for advisor
$user_stats = null;
$minutes_until_next_turn = 0;
$seconds_remainder = 0;
$now = new DateTime('now', new DateTimeZone('UTC'));

if ($is_logged_in) {
    $user_id = (int)$_SESSION['id'];

    // Fetch user stats for sidebar
    $sql_user = "SELECT credits, experience, untrained_citizens, level, attack_turns, last_updated 
                 FROM users WHERE id = ?";
    if ($stmt_user = mysqli_prepare($link, $sql_user)) {
        mysqli_stmt_bind_param($stmt_user, "i", $user_id);
        mysqli_stmt_execute($stmt_user);
        $result_user = mysqli_stmt_get_result($stmt_user);
        $user_stats = mysqli_fetch_assoc($result_user) ?: null;
        mysqli_stmt_close($stmt_user);
    }

    // Turn timer (10 min) for advisor
    if ($user_stats && !empty($user_stats['last_updated'])) {
        $turn_interval_seconds = 600;
        $last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
        $elapsed = max(0, $now->getTimestamp() - $last_updated->getTimestamp());
        $seconds_until_next_turn = $turn_interval_seconds - ($elapsed % $turn_interval_seconds);
        if ($seconds_until_next_turn < 0) { $seconds_until_next_turn = 0; }
        $minutes_until_next_turn = intdiv($seconds_until_next_turn, 60);
        $seconds_remainder       = $seconds_until_next_turn % 60;
    }

    // Expose for advisor include
    $user_xp    = isset($user_stats['experience']) ? (int)$user_stats['experience'] : 0;
    $user_level = isset($user_stats['level']) ? (int)$user_stats['level'] : 0;
}

// --- LEADERBOARD DATA FETCHING ---
$leaderboards = [];

// Top 10 by Level
$sql_level = "SELECT id, character_name, level, experience, race, class, avatar_path 
              FROM users 
              ORDER BY level DESC, experience DESC 
              LIMIT 10";
$result_level = mysqli_query($link, $sql_level);
$leaderboards['Top 10 by Level'] = ['data' => $result_level, 'field' => 'level', 'format' => 'default'];

// Top 10 by Net Worth
$sql_wealth = "SELECT id, character_name, net_worth, race, class, avatar_path 
               FROM users 
               ORDER BY net_worth DESC 
               LIMIT 10";
$result_wealth = mysqli_query($link, $sql_wealth);
$leaderboards['Top 10 Richest Commanders'] = ['data' => $result_wealth, 'field' => 'net_worth', 'format' => 'number'];

// Top 10 by Population
$sql_pop = "SELECT id, character_name, (workers + soldiers + guards + sentries + spies + untrained_citizens) AS population, race, class, avatar_path 
            FROM users 
            ORDER BY population DESC 
            LIMIT 10";
$result_pop = mysqli_query($link, $sql_pop);
$leaderboards['Top 10 by Population'] = ['data' => $result_pop, 'field' => 'population', 'format' => 'number'];

// Top 10 by Army Size
$sql_army = "SELECT id, character_name, (soldiers + guards + sentries + spies) AS army_size, race, class, avatar_path 
             FROM users 
             ORDER BY army_size DESC 
             LIMIT 10";
$result_army = mysqli_query($link, $sql_army);
$leaderboards['Top 10 by Army Size'] = ['data' => $result_army, 'field' => 'army_size', 'format' => 'number'];

// NEW: Top Plunderers (All-Time)
$sql_plunder = "
    SELECT u.id, u.character_name, u.race, u.class, u.avatar_path,
           COALESCE(SUM(b.credits_stolen), 0) AS total_plundered
    FROM battle_logs b
    JOIN users u ON u.id = b.attacker_id
    GROUP BY u.id
    HAVING total_plundered > 0
    ORDER BY total_plundered DESC
    LIMIT 10
";
$result_plunder = mysqli_query($link, $sql_plunder);
$leaderboards['Top Plunderers (All-Time)'] = ['data' => $result_plunder, 'field' => 'total_plundered', 'format' => 'number'];

// NEW: Highest Fatigue Casualties (All-Time)
$sql_fatigue = "
    SELECT u.id, u.character_name, u.race, u.class, u.avatar_path,
           COALESCE(SUM(b.attacker_soldiers_lost), 0) AS total_fatigue_lost
    FROM battle_logs b
    JOIN users u ON u.id = b.attacker_id
    GROUP BY u.id
    HAVING total_fatigue_lost > 0
    ORDER BY total_fatigue_lost DESC
    LIMIT 10
";
$result_fatigue = mysqli_query($link, $sql_fatigue);
$leaderboards['Highest Fatigue Casualties (All-Time)'] = ['data' => $result_fatigue, 'field' => 'total_fatigue_lost', 'format' => 'number'];

// Split leaderboards into two columns (alternate)
$lb_titles = array_keys($leaderboards);
$lb_left  = [];
$lb_right = [];
foreach ($lb_titles as $i => $title) {
    if ($i % 2 === 0) { $lb_left[$title]  = $leaderboards[$title]; }
    else              { $lb_right[$title] = $leaderboards[$title]; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Starlight Dominion - Leaderboards</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">

        <?php if ($is_logged_in): ?>
            <div class="container mx-auto p-4 md:p-8">
                <?php include_once __DIR__ . '/../includes/navigation.php'; ?>
        <?php else: ?>
            <?php include_once __DIR__ . '/../includes/public_header.php'; ?>
            <div class="container mx-auto p-4 md:p-8">
        <?php endif; ?>

                <div class="main-bg border border-gray-700 rounded-lg shadow-2xl p-4 mx-auto max-w-7xl">
                    <h2 class="font-title text-2xl text-cyan-400 text-center mb-6">Leaderboards</h2>

                    <?php if ($is_logged_in && $user_stats): ?>
                        <!-- LAYOUT: Sidebar (Advisor) + Main (leaderboards two-column) -->
                        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                            <!-- TRUE SIDEBAR -->
                            <aside class="lg:col-span-1 space-y-6">
                                <?php
                                    // Provide variables expected by advisor:
                                    // $user_stats, $user_xp, $user_level, $minutes_until_next_turn, $seconds_remainder, $now, $active_page
                                    include_once __DIR__ . '/../includes/advisor.php';
                                ?>
                            </aside>

                            <!-- MAIN CONTENT -->
                            <div class="lg:col-span-3">
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    <!-- LEFT column of leaderboards -->
                                    <div class="space-y-6">
                                        <?php foreach ($lb_left as $title => $details): ?>
                                            <div class="content-box rounded-lg p-4">
                                                <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">
                                                    <?php echo htmlspecialchars($title); ?>
                                                </h3>
                                                <div class="overflow-x-auto">
                                                    <table class="w-full text-sm text-left">
                                                        <thead class="bg-gray-800">
                                                            <tr>
                                                                <th class="p-2">Rank</th>
                                                                <th class="p-2">Commander</th>
                                                                <th class="p-2 text-right"><?php echo ucwords(str_replace('_', ' ', htmlspecialchars($details['field']))); ?></th>
                                                                <th class="p-2 text-right">Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            $rank = 1;
                                                            while ($details['data'] && ($row = mysqli_fetch_assoc($details['data']))):
                                                                $avatar = !empty($row['avatar_path']) ? $row['avatar_path'] : 'https://via.placeholder.com/40';
                                                            ?>
                                                            <tr class="border-t border-gray-700 hover:bg-gray-700/50">
                                                                <td class="p-2 font-bold text-cyan-400"><?php echo $rank++; ?></td>
                                                                <td class="p-2">
                                                                    <div class="flex items-center">
                                                                        <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" class="w-10 h-10 rounded-md mr-3 object-cover">
                                                                        <div>
                                                                            <p class="font-bold text-white"><?php echo htmlspecialchars($row['character_name']); ?></p>
                                                                            <?php if (isset($row['race'], $row['class'])): ?>
                                                                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars(strtoupper($row['race'] . ' ' . $row['class'])); ?></p>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td class="p-2 text-right font-semibold text-white">
                                                                    <?php
                                                                        $val = isset($row[$details['field']]) ? $row[$details['field']] : 0;
                                                                        echo ($details['format'] === 'number') ? number_format((int)$val) : htmlspecialchars((string)$val);
                                                                    ?>
                                                                </td>
                                                                <td class="p-2 text-right">
                                                                    <a href="view_profile.php?id=<?php echo (int)$row['id']; ?>" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-1 px-3 rounded-md text-xs">View</a>
                                                                </td>
                                                            </tr>
                                                            <?php endwhile; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- RIGHT column of leaderboards -->
                                    <div class="space-y-6">
                                        <?php foreach ($lb_right as $title => $details): ?>
                                            <div class="content-box rounded-lg p-4">
                                                <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">
                                                    <?php echo htmlspecialchars($title); ?>
                                                </h3>
                                                <div class="overflow-x-auto">
                                                    <table class="w-full text-sm text-left">
                                                        <thead class="bg-gray-800">
                                                            <tr>
                                                                <th class="p-2">Rank</th>
                                                                <th class="p-2">Commander</th>
                                                                <th class="p-2 text-right"><?php echo ucwords(str_replace('_', ' ', htmlspecialchars($details['field']))); ?></th>
                                                                <th class="p-2 text-right">Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            $rank = 1;
                                                            while ($details['data'] && ($row = mysqli_fetch_assoc($details['data']))):
                                                                $avatar = !empty($row['avatar_path']) ? $row['avatar_path'] : 'https://via.placeholder.com/40';
                                                            ?>
                                                            <tr class="border-t border-gray-700 hover:bg-gray-700/50">
                                                                <td class="p-2 font-bold text-cyan-400"><?php echo $rank++; ?></td>
                                                                <td class="p-2">
                                                                    <div class="flex items-center">
                                                                        <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" class="w-10 h-10 rounded-md mr-3 object-cover">
                                                                        <div>
                                                                            <p class="font-bold text-white"><?php echo htmlspecialchars($row['character_name']); ?></p>
                                                                            <?php if (isset($row['race'], $row['class'])): ?>
                                                                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars(strtoupper($row['race'] . ' ' . $row['class'])); ?></p>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td class="p-2 text-right font-semibold text-white">
                                                                    <?php
                                                                        $val = isset($row[$details['field']]) ? $row[$details['field']] : 0;
                                                                        echo ($details['format'] === 'number') ? number_format((int)$val) : htmlspecialchars((string)$val);
                                                                    ?>
                                                                </td>
                                                                <td class="p-2 text-right">
                                                                    <a href="view_profile.php?id=<?php echo (int)$row['id']; ?>" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-1 px-3 rounded-md text-xs">View</a>
                                                                </td>
                                                            </tr>
                                                            <?php endwhile; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- NOT LOGGED IN: full-width main with two-column leaderboards -->
                        <div class="grid grid-cols-1">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div class="space-y-6">
                                    <?php foreach ($lb_left as $title => $details): ?>
                                        <div class="content-box rounded-lg p-4">
                                            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">
                                                <?php echo htmlspecialchars($title); ?>
                                            </h3>
                                            <div class="overflow-x-auto">
                                                <table class="w-full text-sm text-left">
                                                    <thead class="bg-gray-800">
                                                        <tr>
                                                            <th class="p-2">Rank</th>
                                                            <th class="p-2">Commander</th>
                                                            <th class="p-2 text-right"><?php echo ucwords(str_replace('_', ' ', htmlspecialchars($details['field']))); ?></th>
                                                            <th class="p-2 text-right">Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php
                                                        $rank = 1;
                                                        while ($details['data'] && ($row = mysqli_fetch_assoc($details['data']))):
                                                            $avatar = !empty($row['avatar_path']) ? $row['avatar_path'] : 'https://via.placeholder.com/40';
                                                        ?>
                                                        <tr class="border-t border-gray-700 hover:bg-gray-700/50">
                                                            <td class="p-2 font-bold text-cyan-400"><?php echo $rank++; ?></td>
                                                            <td class="p-2">
                                                                <div class="flex items-center">
                                                                    <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" class="w-10 h-10 rounded-md mr-3 object-cover">
                                                                    <div>
                                                                        <p class="font-bold text-white"><?php echo htmlspecialchars($row['character_name']); ?></p>
                                                                        <?php if (isset($row['race'], $row['class'])): ?>
                                                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars(strtoupper($row['race'] . ' ' . $row['class'])); ?></p>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td class="p-2 text-right font-semibold text-white">
                                                                <?php
                                                                    $val = isset($row[$details['field']]) ? $row[$details['field']] : 0;
                                                                    echo ($details['format'] === 'number') ? number_format((int)$val) : htmlspecialchars((string)$val);
                                                                ?>
                                                            </td>
                                                            <td class="p-2 text-right">
                                                                <a href="view_profile.php?id=<?php echo (int)$row['id']; ?>" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-1 px-3 rounded-md text-xs">View</a>
                                                            </td>
                                                        </tr>
                                                        <?php endwhile; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="space-y-6">
                                    <?php foreach ($lb_right as $title => $details): ?>
                                        <div class="content-box rounded-lg p-4">
                                            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">
                                                <?php echo htmlspecialchars($title); ?>
                                            </h3>
                                            <div class="overflow-x-auto">
                                                <table class="w-full text-sm text-left">
                                                    <thead class="bg-gray-800">
                                                        <tr>
                                                            <th class="p-2">Rank</th>
                                                            <th class="p-2">Commander</th>
                                                            <th class="p-2 text-right"><?php echo ucwords(str_replace('_', ' ', htmlspecialchars($details['field']))); ?></th>
                                                            <th class="p-2 text-right">Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php
                                                        $rank = 1;
                                                        while ($details['data'] && ($row = mysqli_fetch_assoc($details['data']))):
                                                            $avatar = !empty($row['avatar_path']) ? $row['avatar_path'] : 'https://via.placeholder.com/40';
                                                        ?>
                                                        <tr class="border-t border-gray-700 hover:bg-gray-700/50">
                                                            <td class="p-2 font-bold text-cyan-400"><?php echo $rank++; ?></td>
                                                            <td class="p-2">
                                                                <div class="flex items-center">
                                                                    <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" class="w-10 h-10 rounded-md mr-3 object-cover">
                                                                    <div>
                                                                        <p class="font-bold text-white"><?php echo htmlspecialchars($row['character_name']); ?></p>
                                                                        <?php if (isset($row['race'], $row['class'])): ?>
                                                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars(strtoupper($row['race'] . ' ' . $row['class'])); ?></p>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td class="p-2 text-right font-semibold text-white">
                                                                <?php
                                                                    $val = isset($row[$details['field']]) ? $row[$details['field']] : 0;
                                                                    echo ($details['format'] === 'number') ? number_format((int)$val) : htmlspecialchars((string)$val);
                                                                ?>
                                                            </td>
                                                            <td class="p-2 text-right">
                                                                <a href="view_profile.php?id=<?php echo (int)$row['id']; ?>" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-1 px-3 rounded-md text-xs">View</a>
                                                            </td>
                                                        </tr>
                                                        <?php endwhile; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <!-- Gentle CTA for guests -->
                                    <div class="content-box rounded-lg p-4">
                                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Join the Dominion</h3>
                                        <p class="text-sm text-gray-300">
                                            Log in to see your advisor and personalized stats alongside the leaderboards.
                                        </p>
                                        <div class="mt-3 flex gap-3">
                                            <a href="/login.php" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-md">Log In</a>
                                            <a href="/register.php" class="bg-gray-700 hover:bg-gray-800 text-white font-bold py-2 px-4 rounded-md">Create Account</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div><!-- /.main-bg -->
            </div><!-- /.container -->
        </div><!-- /.min-h-screen -->

        <?php if ($is_logged_in): ?>
            <script src="/assets/js/main.js" defer></script>
        <?php else: ?>
            <?php include_once __DIR__ . '/../includes/public_footer.php'; ?>
        <?php endif; ?>

    <script>lucide.createIcons();</script>
</body>
</html>
