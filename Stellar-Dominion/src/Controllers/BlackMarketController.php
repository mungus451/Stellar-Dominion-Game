<?php
// This is the file's address, so the computer knows where to find it.
namespace StellarDominion\Controllers;

// We're borrowing tools from other files to use here.
use Exception;
use mysqli;
use Throwable;
use StellarDominion\Services\BlackMarketService;
use StellarDominion\Services\EconomicLoggingService;
use StellarDominion\Services\VaultService;

// We need to make sure all our toolboxes are open and ready.
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Services/BlackMarketService.php';
require_once __DIR__ . '/../Services/EconomicLoggingService.php';
require_once __DIR__ . '/../Services/VaultService.php';
require_once __DIR__ . '/../Security/CSRFProtection.php';

/**
 * BlackMarketController
 * This is the boss of the Black Market. It handles all the secret deals,
 * like playing dice games or trading gems for credits.
 */
class BlackMarketController
{
    // These are the special tools the Black Market Boss needs.
    private mysqli $db; // The tool for talking to the game's big data box.
    private BlackMarketService $blackMarketService; // The expert on how the market games work.
    private EconomicLoggingService $loggingService; // The bookkeeper who writes down all money changes.
    private VaultService $vaultService; // The expert on your money piggy bank (vault) rules.
    private int $userId; // Your special player ID.

    /**
     * When the Black Market Boss is created, it gets all its tools ready.
     */
    public function __construct(mysqli $db)
    {
        $this->db = $db;
        $this->blackMarketService = new BlackMarketService($db);
        $this->loggingService = new EconomicLoggingService($db);
        $this->vaultService = new VaultService($db, $this->loggingService);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
            // If you're not logged in, you can't be here!
            header("location: /index.php");
            exit;
        }
        $this->userId = (int)($_SESSION["id"] ?? 0);
        date_default_timezone_set('UTC');
    }

    /**
     * This is the main job. It checks what you want to do at the market
     * and calls the right helper to get it done.
     */
    public function handleRequest(): void
    {
        // We only listen for POST requests, which is like a secret knock.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $response = [];
        try {
            $action = $_POST['action'] ?? '';
            $csrf_action = $_POST['csrf_action'] ?? $action;

            // This is the secret handshake to make sure you're a real player.
            if (!validate_csrf_token($_POST['csrf_token'] ?? '', $csrf_action)) {
                throw new Exception("Security error. Please refresh the page and try again.");
            }

            // Let's see what you want to do...
            switch ($action) {
                case 'cosmic_roll':
                    $response = $this->handleCosmicRoll();
                    break;
                case 'buy_data_dice':
                    $response = $this->handleDataDicePurchase();
                    break;
                case 'convert_currency':
                    $response = $this->handleCurrencyConversion();
                    break;
                default:
                    throw new Exception("Invalid action requested.");
            }
        } catch (Throwable $e) {
            // Uh oh! Something went wrong. We tell you what happened.
            http_response_code(400); // This is a "bad request" signal.
            $response = ['success' => false, 'message' => $e->getMessage()];
        }

        // We send back a message in JSON, which is a language computers love.
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    /**
     * This helper handles the "Cosmic Roll" game.
     */
    private function handleCosmicRoll(): array
    {
        $this->db->begin_transaction(); // Let's do this all at once, or not at all.

        // First, get your current money situation.
        $user = $this->getUserForUpdate($this->userId);
        $balances_before = $this->getCurrentBalances($user);

        // Let the Black Market expert figure out the prize.
        $result = $this->blackMarketService->processCosmicRoll($this->userId, $user);
        $credits_won = $result['credits_won'];

        // Now, let's use the piggy bank (vault) rules!
        $credit_cap = $this->vaultService->get_credit_cap($this->userId);
        $headroom = max(0, $credit_cap - $balances_before['on_hand']);

        // You only get the money that fits in your piggy bank.
        $applied_gain = min($credits_won, $headroom);
        // The rest of the money disappears. Poof!
        $burned_amount = $credits_won - $applied_gain;

        // Update your on-hand credits with the money that fit.
        $new_on_hand_credits = $balances_before['on_hand'] + $applied_gain;
        $stmt = $this->db->prepare("UPDATE users SET credits = ?, gems = gems - ? WHERE id = ?");
        $stmt->bind_param("iii", $new_on_hand_credits, $result['cost'], $this->userId);
        $stmt->execute();
        $stmt->close();
        
        $balances_after_gems = ['on_hand' => $balances_before['on_hand'], 'banked' => $balances_before['banked'], 'gems' => $balances_before['gems'] - $result['cost']];
        $balances_after_credits = $this->getCurrentBalances(['credits' => $new_on_hand_credits] + $user);

        // Write down what happened in our money diary.
        $this->loggingService->log($this->userId, 'cosmic_roll_cost', -$result['cost'], $balances_before, $balances_after_gems, 0, null, [], 'gems');
        $this->loggingService->log($this->userId, 'cosmic_roll_win', $credits_won, $balances_after_gems, $balances_after_credits, $burned_amount);

        $this->db->commit(); // All done! Save our work.

        // Send back a happy message with the results.
        return [
            'success' => true,
            'message' => "You won " . number_format($credits_won) . " credits!",
            'roll_result' => $result['roll_result'],
            'credits_won' => $credits_won,
            'new_credits' => $new_on_hand_credits,
            'new_gems' => $balances_after_gems['gems']
        ];
    }
    
    /**
     * This helper lets you buy the special Data Dice.
     */
    private function handleDataDicePurchase(): array
    {
        $this->db->begin_transaction();

        $user = $this->getUserForUpdate($this->userId);
        $balances_before = $this->getCurrentBalances($user);

        // The Black Market expert handles the purchase.
        $result = $this->blackMarketService->processDataDicePurchase($this->userId, $user);
        $cost = $result['cost'];

        // Update your credits in the database.
        $new_credits = $balances_before['on_hand'] - $cost;
        $stmt = $this->db->prepare("UPDATE users SET credits = ?, data_dice = data_dice + 1 WHERE id = ?");
        $stmt->bind_param("ii", $new_credits, $this->userId);
        $stmt->execute();
        $stmt->close();

        $balances_after = $this->getCurrentBalances(['credits' => $new_credits] + $user);
        
        // Write it down in the money diary.
        $this->loggingService->log($this->userId, 'buy_data_dice', -$cost, $balances_before, $balances_after);

        $this->db->commit();

        return [
            'success' => true,
            'message' => 'Successfully purchased 1 Data Dice!',
            'new_credits' => $new_credits,
            'new_dice_count' => $result['new_dice_count'],
        ];
    }

    /**
     * This helper lets you trade shiny gems for credits.
     */
    private function handleCurrencyConversion(): array
    {
        $gems_to_convert = (int)($_POST['gems_amount'] ?? 0);
        if ($gems_to_convert <= 0) {
            throw new Exception("Please enter a valid amount of gems to convert.");
        }

        $this->db->begin_transaction();

        $user = $this->getUserForUpdate($this->userId);
        $balances_before = $this->getCurrentBalances($user);

        // The Black Market expert does the conversion math.
        $result = $this->blackMarketService->processConversion($this->userId, $user, $gems_to_convert);
        $credits_gained = $result['credits_gained'];

        // Time for the piggy bank (vault) rules again!
        $credit_cap = $this->vaultService->get_credit_cap($this->userId);
        $headroom = max(0, $credit_cap - $balances_before['on_hand']);
        $applied_gain = min($credits_gained, $headroom);
        $burned_amount = $credits_gained - $applied_gain;

        // Update your credits and gems in the database.
        $new_on_hand_credits = $balances_before['on_hand'] + $applied_gain;
        $new_gems = $balances_before['gems'] - $gems_to_convert;

        $stmt = $this->db->prepare("UPDATE users SET credits = ?, gems = ? WHERE id = ?");
        $stmt->bind_param("iii", $new_on_hand_credits, $new_gems, $this->userId);
        $stmt->execute();
        $stmt->close();
        
        $balances_after_gems = ['on_hand' => $balances_before['on_hand'], 'banked' => $balances_before['banked'], 'gems' => $new_gems];
        $balances_after_credits = $this->getCurrentBalances(['credits' => $new_on_hand_credits, 'gems' => $new_gems] + $user);

        // Write both parts of the trade in our money diary.
        $this->loggingService->log($this->userId, 'convert_gems_cost', -$gems_to_convert, $balances_before, $balances_after_gems, 0, null, [], 'gems');
        $this->loggingService->log($this->userId, 'convert_gems_gain', $credits_gained, $balances_after_gems, $balances_after_credits, $burned_amount);

        $this->db->commit();

        return [
            'success' => true,
            'message' => "Successfully converted {$gems_to_convert} gems into " . number_format($credits_gained) . " credits." . ($burned_amount > 0 ? " (" . number_format($burned_amount) . " burned due to vault capacity)." : ""),
            'new_credits' => $new_on_hand_credits,
            'new_gems' => $new_gems,
        ];
    }
    
    /**
     * A little helper to get all the player's info and lock it so nobody else can change it while we work.
     */
    private function getUserForUpdate(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        if (!$data) {
            throw new Exception("Player data not found.");
        }
        return $data;
    }

    /**
     * A little helper to quickly get the player's money situation.
     */
    private function getCurrentBalances(array $user): array
    {
        return [
            'on_hand' => (int)($user['credits'] ?? 0),
            'banked' => (int)($user['banked_credits'] ?? 0),
            'gems' => (int)($user['gems'] ?? 0),
        ];
    }
}

// --- This is where the file starts running. ---
// It creates the Black Market Boss and tells it to handle your request.
$controller = new BlackMarketController($link); // $link comes from config.php
$controller->handleRequest();
