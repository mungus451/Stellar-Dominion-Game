<?php
// template/pages/view_alliance.php
$active_page = 'view_alliance.php';
require_once __DIR__ . '/../../config/config.php';

// Safe HTML-escape for nullable values
function e($v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

// Get the alliance ID from the URL
$alliance_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($alliance_id <= 0) {
    header("Location: /alliance.php");
    exit;
}

// Fetch the alliance's information (only fields we render)
$sql = "SELECT id, name, tag, description, avatar_path FROM alliances WHERE id = ? LIMIT 1";
$stmt = $link->prepare($sql);
$stmt->bind_param("i", $alliance_id);
$stmt->execute();
$result   = $stmt->get_result();
$alliance = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$alliance) {
    header("Location: /alliance.php");
    exit;
}

// Fetch the alliance's members (default role to 'Member' to avoid NULLs)
$sql = "
    SELECT 
        u.id,
        u.character_name AS username,
        COALESCE(ar.name, 'Member') AS role_name
    FROM users u
    LEFT JOIN alliance_roles ar 
        ON ar.id = u.alliance_role_id
       AND ar.alliance_id = u.alliance_id
    WHERE u.alliance_id = ?
    ORDER BY (ar.`order` IS NULL), ar.`order` ASC, u.character_name ASC
";
$stmt = $link->prepare($sql);
$stmt->bind_param("i", $alliance_id);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$avatar = $alliance['avatar_path'] ?: '/assets/img/default_alliance.avif';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Starlight Dominion - <?= e($alliance['name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
            <!-- Google Adsense Code -->
<?php include __DIR__ . '/../includes/adsense.php'; ?>
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">
            
            <?php include_once __DIR__ . '/../includes/navigation.php'; ?>

            <div class="content-box rounded-lg p-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <h1 class="font-title text-3xl text-white">
                            <?= e($alliance['name']) ?>
                            <span class="text-cyan-400">[<?= e($alliance['tag']) ?>]</span>
                        </h1>
                        <p class="mt-2 text-gray-300"><?= e($alliance['description'] ?? '—') ?></p>
                    </div>
                    <div class="md:col-span-1">
                        <img src="<?= e($avatar) ?>" alt="Alliance Avatar" class="w-full h-auto rounded-lg border-2 border-gray-600">
                    </div>
                </div>

                <hr class="my-4 border-gray-700">

                <div>
                    <h2 class="font-title text-2xl text-white">Members</h2>
                    <div class="mt-2">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="border-b border-gray-700">
                                    <th class="p-2 font-semibold text-cyan-400">Username</th>
                                    <th class="p-2 font-semibold text-cyan-400">Role</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($members)): ?>
                                    <tr><td colspan="2" class="p-2 text-center text-gray-500">No members found.</td></tr>
                                <?php else: foreach ($members as $member): ?>
                                    <tr class="border-b border-gray-800 hover:bg-gray-700/50">
                                        <td class="p-2">
                                            <a href="/view_profile.php?id=<?= (int)$member['id'] ?>" class="text-white hover:text-cyan-300">
                                                <?= e($member['username']) ?>
                                            </a>
                                        </td>
                                        <td class="p-2"><?= e($member['role_name'] ?? 'Member') ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="/assets/js/main.js" defer></script>
</body>
</html>
