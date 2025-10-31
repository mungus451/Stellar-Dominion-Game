<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

global $link;

$user_id = (int)($_SESSION['id'] ?? 0);

if (!$link || !($link instanceof \mysqli) || !@mysqli_ping($link)) {
    // If $link is bad or closed, we must reconnect.
    // We use the constants defined in config/config.php
    $link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

    // Check if the reconnect failed
    if ($link === false) {
        // We can't proceed.
        die("Critical Error: Database connection was closed and could not be re-established.");
    }
    
    // We must set the timezone, just like config.php does
    mysqli_query($link, "SET time_zone = '+00:00'");
}

if ($user_id <= 0) {
    // This prevents errors for logged-out users if this header is
    // accidentally included on a public page that doesn't require login.
    // We'll die here to prevent the fatal error from ss_process_and_get_user_state.
    die('Critical Error: User ID not found in session for header.php.');
}

//--- DATA FETCHING ---
$needed_fields = [
    'credits','level','experience',
    'soldiers','guards','sentries','spies','workers',
    'armory_level','charisma_points', 'gemstones',
    'last_updated','attack_turns','untrained_citizens',
    'strength_points', 'constitution_points', 'wealth_points', 'dexterity_points',
    'fortification_level', 'offense_upgrade_level', 'defense_upgrade_level',
    'economy_upgrade_level', 'population_level'
];
// This line is now safe, as $link is guaranteed to be a live connection.
$user_stats = ss_process_and_get_user_state($link, $user_id, $needed_fields);
?>
<!DOCTYPE html>
<html lang="en" x-data="{ panels: { eco:true, mil:true, pop:true, fleet:true, sec:true, esp:true, structure: true, deposit: true, withdraw: true, transfer: true, history: true } }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Starlight Dominion - <?php echo htmlspecialchars($page_title ?? 'Game'); ?></title>
    
    <?php if (isset($csrf_token)): ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
    <?php endif; ?>

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- Favicon Attachments -->
    <link rel="icon" type="image/avif" href="/assets/img/favicon.avif">
    <link rel="icon" type="image/png" href="/assets/img/favicon.png" sizes="32x32">


    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>[x-cloak]{display:none!important}</style>

<!-- Google Adsense Code -->
<?php include __DIR__ . '/adsense.php'; ?>

</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">

            <?php include_once __DIR__ . '/navigation.php'; ?>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">