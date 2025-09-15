<?php
// public/api/get_profile_data.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($profile_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid profile ID']);
    exit;
}

// Fetch profile data
$sql = "SELECT character_name, race, class, level, avatar_path, biography, soldiers, guards, sentries, spies, workers FROM users WHERE id = ?";
if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $profile_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $profile = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($profile) {
        // Calculate derived stats
        $profile['army_size'] = $profile['soldiers'] + $profile['guards'] + $profile['sentries'] + $profile['spies'];
        
        // Optionally include earned badges (pass ?include_badges=1)
        if (isset($_GET['include_badges']) && $_GET['include_badges'] === '1') {
            $badges = [];
            $sqlB = "SELECT b.name, b.description, b.icon_path, ub.earned_at
                     FROM user_badges ub
                     JOIN badges b ON b.id = ub.badge_id
                     WHERE ub.user_id = ?
                     ORDER BY ub.earned_at DESC
                     LIMIT 24";
            if ($stmtB = mysqli_prepare($link, $sqlB)) {
                mysqli_stmt_bind_param($stmtB, "i", $profile_id);
                mysqli_stmt_execute($stmtB);
                $resB = mysqli_stmt_get_result($stmtB);
                while ($rowB = $resB->fetch_assoc()) { $badges[] = $rowB; }
                mysqli_stmt_close($stmtB);
            }
            $profile['badges'] = $badges;
        }

        echo json_encode($profile);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Profile not found']);
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}

mysqli_close($link);
?>