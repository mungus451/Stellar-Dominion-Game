<?php
// template/pages/black_market.php
// Black Market (centered main content)
$page_title  = 'Black Market';
$active_page = 'black_market.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("location: /"); exit; }

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Services/StateService.php';

// --- BALANCES ---
$user_id = (int)$_SESSION['id'];
$stmt = mysqli_prepare($link, "SELECT credits, gemstones, reroll_tokens, black_market_reputation FROM users WHERE id=?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$me = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [
    'credits'=>0,'gemstones'=>0,'reroll_tokens'=>0,'black_market_reputation'=>0
];
mysqli_stmt_close($stmt);

// --- HOUSE (GEMSTONES) ---
$res = mysqli_query($link, "SELECT gemstones_collected FROM black_market_house_totals WHERE id=1");
$house = $res ? mysqli_fetch_assoc($res) : ['gemstones_collected'=>0];

// --- CSRF ---
$csrf_action = 'black_market';
$csrf_token  = generate_csrf_token($csrf_action);

include_once __DIR__ . '/../includes/header.php';
?>


    <!-- LEFT SIDEBAR -->
    <aside class="lg:col-span-1 space-y-4">
        <?php include_once __DIR__ . '/../includes/advisor.php'; ?>
    </aside>
 

    <!-- MAIN CONTENT -->
    <main class="lg:col-span-3 space-y-4">
        <!-- Converter -->
        <?php include_once __DIR__ . '/../includes/black_market/currency_converter.php'; ?>

        <!-- Minigame -->
        <?php include_once __DIR__ . '/../includes/black_market/data_dice.php'; ?>

        <!-- How to Play — Modal -->
        <?php include_once __DIR__ . '/../includes/black_market/htp_modal.php'; ?>

        <!-- Mini Game 2 Cosmic Roll -->
        <?php include_once __DIR__ . '/../includes/black_market/cosmic_roll.php'; ?>
    </main>



<?php include_once __DIR__ . '/../includes/footer.php'; ?>

<!-- Exciting styling (kept exactly as you had it) -->
<?php include_once __DIR__ . '/../includes/black_market/converter_style.php'; ?>
<?php include_once __DIR__ . '/../includes/black_market/converter_logic.php'; ?>
<!-- Modal Logic -->
<?php include_once __DIR__ . "/../includes/black_market/htp_modal_logic.php"; ?>

<!-- Page-local overrides to neutralize any global rules from the converter CSS -->
<style>
/* Restore page scroll and normal flow without touching shared CSS files */
html, body { overflow-y: auto !important; height: auto !important; }
body { display: block !important; align-items: normal !important; justify-content: normal !important; }
/* Ensure footer stretches full width even if a parent grid exists above */
footer { width: 100% !important; }
</style>
