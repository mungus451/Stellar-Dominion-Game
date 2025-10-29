<?php
// template/pages/black_market.php
// Black Market (centered main content)
$page_title  = 'Black Market';
$active_page = 'black_market.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("location: /"); exit; }

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Services/StateService.php';
require_once __DIR__ . '/../../src/Security/CSRFProtection.php';


// --- BALANCES ---
$user_id = (int)$_SESSION['id'];
$stmt = mysqli_prepare($link, "SELECT credits, gemstones, reroll_tokens, black_market_reputation, level FROM users WHERE id=?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$me = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [
    'credits'=>0,'gemstones'=>0,'reroll_tokens'=>0,'black_market_reputation'=>0, 'level'=>1
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


    <aside class="lg:col-span-1 space-y-4">
        <?php include_once __DIR__ . '/../includes/advisor.php'; ?>
    </aside>
 

    <main class="lg:col-span-3 space-y-4" x-data="{ activeTab: 'converter' }">

        <nav class="flex space-x-1 rounded-lg bg-gray-800/50 p-1" role="tablist" aria-orientation="horizontal">
            <button @click="activeTab = 'converter'"
                    :class="activeTab === 'converter' ? 'bg-cyan-600 text-white' : 'text-gray-300 hover:bg-gray-700/50'"
                    class="flex-1 px-4 py-2 text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all"
                    role="tab"
                    :aria-selected="activeTab === 'converter'"
                    aria-controls="tab-panel-converter">
                <i class="fa-solid fa-right-left mr-1"></i> Exchange
            </button>
            <button @click="activeTab = 'data_dice'"
                    :class="activeTab === 'data_dice' ? 'bg-cyan-600 text-white' : 'text-gray-300 hover:bg-gray-700/50'"
                    class="flex-1 px-4 py-2 text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all"
                    role="tab"
                    :aria-selected="activeTab === 'data_dice'"
                    aria-controls="tab-panel-data_dice">
                <i class="fa-solid fa-dice mr-1"></i> Data Dice
            </button>
            <button @click="activeTab = 'cosmic_roll'"
                    :class="activeTab === 'cosmic_roll' ? 'bg-cyan-600 text-white' : 'text-gray-300 hover:bg-gray-700/50'"
                    class="flex-1 px-4 py-2 text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all"
                    role="tab"
                    :aria-selected="activeTab === 'cosmic_roll'"
                    aria-controls="tab-panel-cosmic_roll">
                <i class="fa-solid fa-rocket mr-1"></i> Cosmic Roll
            </button>
            <button @click="activeTab = 'quantum_roulette'"
                    :class="activeTab === 'quantum_roulette' ? 'bg-cyan-600 text-white' : 'text-gray-300 hover:bg-gray-700/50'"
                    class="flex-1 px-4 py-2 text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all"
                    role="tab"
                    :aria-selected="activeTab === 'quantum_roulette'"
                    aria-controls="tab-panel-quantum_roulette">
                <i class="fa-solid fa-atom mr-1"></i> Q-Roulette
            </button>
        </nav>

        <div class="space-y-4">
            
            <div id="tab-panel-converter" x-show="activeTab === 'converter'" role="tabpanel" tabindex="0" class="space-y-4">
                <?php include_once __DIR__ . '/../includes/black_market/currency_converter.php'; ?>
                <?php include_once __DIR__ . '/../includes/black_market/converter_style.php'; ?>
                <?php include_once __DIR__ . '/../includes/black_market/converter_logic.php'; ?>
            </div>

            <div id="tab-panel-data_dice" x-show="activeTab === 'data_dice'" role="tabpanel" tabindex="0" x-cloak class="space-y-4">
                <?php include_once __DIR__ . '/../includes/black_market/data_dice.php'; ?>
                <?php include_once __DIR__ . '/../includes/black_market/htp_modal.php'; ?>
                <?php include_once __DIR__ . "/../includes/black_market/htp_modal_logic.php"; ?>
            </div>

            <div id="tab-panel-cosmic_roll" x-show="activeTab === 'cosmic_roll'" role="tabpanel" tabindex="0" x-cloak class="space-y-4">
                <?php include_once __DIR__ . '/../includes/black_market/cosmic_roll.php'; ?>
            </div>

            <div id="tab-panel-quantum_roulette" x-show="activeTab === 'quantum_roulette'" role="tabpanel" tabindex="0" x-cloak class="space-y-4">
                <?php include_once __DIR__ . '/../includes/black_market/quantum_roulette.php'; ?>
                <?php include_once __DIR__ . '/../includes/black_market/quantum_roulette_style.php'; ?>
                <?php include_once __DIR__ . '/../includes/black_market/quantum_roulette_logic.php'; ?>
            </div>

        </div>
    </main>



<?php include_once __DIR__ . '/../includes/footer.php'; ?>







<style>
/* Restore page scroll and normal flow without touching shared CSS files */
html, body { overflow-y: auto !important; height: auto !important; }
body { display: block !important; align-items: normal !important; justify-content: normal !important; }
/* Ensure footer stretches full width even if a parent grid exists above */
footer { width: 100% !important; }

/* Styling for the new quick-convert buttons */
.quick-convert-buttons {
    display: flex;
    gap: 0.5rem;
    margin: 0.75rem 0 0.25rem;
}
.btn-quick {
    flex: 1;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    font-weight: bold;
    color: #9ab;
    background: rgba(100, 120, 140, 0.1);
    border: 1px solid #456;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-quick:hover {
    color: #fff;
    background: rgba(100, 120, 140, 0.2);
    border-color: #678;
    box-shadow: 0 0 5px rgba(100, 180, 255, 0.3);
}

/* Alpine.js cloak style for tabs */
[x-cloak] { 
    display: none !important; 
}
</style>

<script>
/**
 * Auto-fills the converter inputs with a set amount.
 * This function is called by the new 'onclick' buttons.
 *
 * @param {string} type - The input to target ('c2g' or 'g2c')
 * @param {number} amount - The amount to set
 */
function setConvertAmount(type, amount) {
    let inputId = (type === 'c2g') ? 'c2g-input' : 'g2c-input';
    const inputElement = document.getElementById(inputId);
    if (inputElement) {
        inputElement.value = amount;
    }
}
</script>