<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en" x-data="{ panels: { eco:true, mil:true, pop:true, fleet:true, sec:true, esp:true, structure: true } }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Starlight Dominion - <?php echo htmlspecialchars($page_title ?? 'Game'); ?></title>
    
    <?php // --- ADD THIS BLOCK --- ?>
    <?php if (isset($csrf_token)): ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
    <?php endif; ?>
    <?php // --- END ADD --- ?>

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">
            <?php include_once __DIR__ . '/navigation.php'; ?>
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">