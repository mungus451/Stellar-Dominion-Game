<?php
// --- PAGE CONFIGURATION ---
$page_title = 'Training & Fleet Management';
$active_page = 'battle.php';

// --- SESSION AND DATABASE SETUP ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /index.php");
    exit;
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Services/StateService.php'; // Centralized state
require_once __DIR__ . '/../includes/advisor_hydration.php';

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/TrainingController.php';
    exit;
}

date_default_timezone_set('UTC');

$csrf_token = generate_csrf_token();
$user_id = (int)$_SESSION['id'];

// --- DATA FETCHING ---
$needed_fields = [
    'credits','banked_credits','untrained_citizens',
    'soldiers','guards','sentries','spies','workers',
    'charisma_points'
];
// Advisor hydration already processed regen; read-only fetch here
$user_stats = ss_get_user_state($link, $user_id, $needed_fields);

// --- GAME DATA & CALCULATIONS ---
$unit_costs = ['workers' => 100, 'soldiers' => 250, 'guards' => 250, 'sentries' => 500, 'spies' => 1000];
$unit_names = ['workers' => 'Worker', 'soldiers' => 'Soldier', 'guards' => 'Guard', 'sentries' => 'Sentry', 'spies' => 'Spy'];
$unit_descriptions = ['workers' => '+50 Credits per turn', 'soldiers' => '+8-12 Offense Power', 'guards' => '+8-12 Defense Power', 'sentries' => '+10 Fortification', 'spies' => '+10 Infiltration'];
$charisma_discount = 1 - ($user_stats['charisma_points'] * 0.01);

// --- TABS (add "recovery") ---
$current_tab = 'train';
if (isset($_GET['tab'])) {
    $t = $_GET['tab'];
    if ($t === 'disband') $current_tab = 'disband';
    elseif ($t === 'recovery') $current_tab = 'recovery';
}

// --- RECOVERY QUEUE DATA (defensive: only if table/columns exist) ---
$recovery_rows = [];
$has_recovery_schema = false;
$recovery_ready_total = 0;
$recovery_locked_total = 0;

$chk_sql = "SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name   = 'untrained_units'
              AND column_name IN ('user_id','unit_type','quantity','available_at')";
$chk = mysqli_query($link, $chk_sql);
if ($chk && mysqli_num_rows($chk) >= 4) {
    $has_recovery_schema = true;
    mysqli_free_result($chk);

    $sql_q = "SELECT id, unit_type, quantity, available_at,
                     GREATEST(0, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), available_at)) AS sec_remaining
                FROM untrained_units
               WHERE user_id = ?
               ORDER BY available_at ASC, id ASC";
    if ($stmt_q = mysqli_prepare($link, $sql_q)) {
        mysqli_stmt_bind_param($stmt_q, "i", $user_id);
        mysqli_stmt_execute($stmt_q);
        $res_q = mysqli_stmt_get_result($stmt_q);
        while ($row = mysqli_fetch_assoc($res_q)) {
            $row['quantity'] = (int)$row['quantity'];
            $row['sec_remaining'] = (int)$row['sec_remaining'];
            if ($row['sec_remaining'] > 0) $recovery_locked_total += $row['quantity'];
            else $recovery_ready_total += $row['quantity'];
            $recovery_rows[] = $row;
        }
        mysqli_free_result($res_q);
        mysqli_stmt_close($stmt_q);
    }
} else {
    if ($chk) mysqli_free_result($chk);
}

// --- INCLUDE UNIVERSAL HEADER ---
include_once __DIR__ . '/../includes/header.php';
?>

<aside class="lg:col-span-1 space-y-4">
    <?php 
        include_once __DIR__ . '/../includes/advisor.php'; 
    ?>
</aside>

<main class="lg:col-span-3 space-y-4">
    <?php if(isset($_SESSION['training_message'])): ?>
        <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
            <?php echo htmlspecialchars($_SESSION['training_message']); unset($_SESSION['training_message']); ?>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['training_error'])): ?>
        <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
            <?php echo htmlspecialchars($_SESSION['training_error']); unset($_SESSION['training_error']); ?>
        </div>
    <?php endif; ?>

    <div class="content-box rounded-lg p-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
            <div>
                <p class="text-xs uppercase">Citizens</p>
                <p id="available-citizens" data-amount="<?php echo $user_stats['untrained_citizens']; ?>" class="text-lg font-bold text-white">
                    <?php echo number_format($user_stats['untrained_citizens']); ?>
                </p>
            </div>
            <div>
                <p class="text-xs uppercase">Credits</p>
                <p id="available-credits" data-amount="<?php echo $user_stats['credits']; ?>" class="text-lg font-bold text-white">
                    <?php echo number_format($user_stats['credits']); ?>
                </p>
            </div>
            <div>
                <p class="text-xs uppercase">Total Cost</p>
                <p id="total-build-cost" class="text-lg font-bold text-yellow-400">0</p>
            </div>
            <div>
                <p class="text-xs uppercase">Total Refund</p>
                <p id="total-refund-value" class="text-lg font-bold text-green-400">0</p>
            </div>
        </div>
    </div>
    
    <div class="border-b border-gray-600">
        <nav class="flex space-x-2" aria-label="Tabs">
            <?php
                $train_btn_classes   = ($current_tab === 'train')    ? 'bg-gray-700 text-white font-semibold' : 'bg-gray-800 hover:bg-gray-700 text-gray-400';
                $disband_btn_classes = ($current_tab === 'disband')  ? 'bg-gray-700 text-white font-semibold' : 'bg-gray-800 hover:bg-gray-700 text-gray-400';
                $recovery_btn_classes= ($current_tab === 'recovery') ? 'bg-gray-700 text-white font-semibold' : 'bg-gray-800 hover:bg-gray-700 text-gray-400';
            ?>
            <button id="train-tab-btn" class="tab-btn <?php echo $train_btn_classes; ?> py-3 px-6 rounded-t-lg text-base transition-colors">Train Units</button>
            <button id="disband-tab-btn" class="tab-btn <?php echo $disband_btn_classes; ?> py-3 px-6 rounded-t-lg text-base transition-colors">Disband Units</button>
            <button id="recovery-tab-btn" class="tab-btn <?php echo $recovery_btn_classes; ?> py-3 px-6 rounded-t-lg text-base transition-colors">Recovery Queue</button>
        </nav>
    </div>

    <!-- TRAIN TAB -->
    <div id="train-tab-content" class="<?php if ($current_tab !== 'train') echo 'hidden'; ?>">
        <form id="train-form" action="/battle.php" method="POST" class="space-y-4" data-charisma-discount="<?php echo $charisma_discount; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="train">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach($unit_costs as $unit => $cost): 
                    $discounted_cost = floor($cost * $charisma_discount);
                ?>
                <div class="content-box rounded-lg p-3">
                    <div class="flex items-center space-x-3">
                        <img src="/assets/img/<?php echo strtolower($unit_names[$unit]); ?>.avif" alt="<?php echo $unit_names[$unit]; ?> Icon" class="w-12 h-12 rounded-md flex-shrink-0">
                        <div class="flex-grow">
                            <p class="font-bold text-white"><?php echo $unit_names[$unit]; ?></p>
                            <p class="text-xs text-yellow-400 font-semibold"><?php echo $unit_descriptions[$unit]; ?></p>
                            <p class="text-xs">Cost: <?php echo number_format($discounted_cost); ?> Credits</p>
                            <p class="text-xs">Owned: <?php echo number_format($user_stats[$unit]); ?></p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <input type="number" name="<?php echo $unit; ?>" min="0" placeholder="0" data-cost="<?php echo $cost; ?>" class="unit-input-train bg-gray-900 border border-gray-600 rounded-md w-24 text-center p-1">
                            <button type="button" class="train-max-btn text-xs bg-cyan-800 hover:bg-cyan-700 text-white font-semibold py-1 px-2 rounded-md">Max</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="content-box rounded-lg p-4 text-center">
                <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-8 rounded-lg transition-colors">Train All Selected Units</button>
            </div>
        </form>
    </div>
    
    <!-- DISBAND TAB -->
    <div id="disband-tab-content" class="<?php if ($current_tab !== 'disband') echo 'hidden'; ?>">
        <form id="disband-form" action="/battle.php" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="disband">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach($unit_costs as $unit => $cost): ?>
                <div class="content-box rounded-lg p-3">
                    <div class="flex items-center space-x-3">
                        <img src="/assets/img/<?php echo strtolower($unit_names[$unit]); ?>.avif" alt="<?php echo $unit_names[$unit]; ?> Icon" class="w-12 h-12 rounded-md flex-shrink-0">
                        <div class="flex-grow">
                            <p class="font-bold text-white"><?php echo $unit_names[$unit]; ?></p>
                            <p class="text-xs text-yellow-400 font-semibold"><?php echo $unit_descriptions[$unit]; ?></p>
                            <p class="text-xs">Refund: <?php echo number_format($cost * 0.75); ?> Credits</p>
                            <p class="text-xs">Owned: <?php echo number_format($user_stats[$unit]); ?></p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <input type="number" name="<?php echo $unit; ?>" min="0" max="<?php echo $user_stats[$unit]; ?>" placeholder="0" data-cost="<?php echo $cost; ?>" class="unit-input-disband bg-gray-900 border border-gray-600 rounded-md w-24 text-center p-1">
                            <button type="button" class="disband-max-btn text-xs bg-red-800 hover:bg-red-700 text-white font-semibold py-1 px-2 rounded-md">Max</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="content-box rounded-lg p-4 text-center">
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-8 rounded-lg transition-colors">Disband All Selected Units</button>
            </div>
        </form>
    </div>

    <!-- RECOVERY TAB -->
    <div id="recovery-tab-content" class="<?php if ($current_tab !== 'recovery') echo 'hidden'; ?>">
        <div class="content-box rounded-lg p-4 space-y-3">
            <div class="flex flex-wrap items-center gap-4 text-sm">
                <div class="px-3 py-1 rounded bg-gray-800">
                    Ready now: <span class="font-semibold text-green-400"><?php echo number_format($recovery_ready_total); ?></span>
                </div>
                <div class="px-3 py-1 rounded bg-gray-800">
                    Locked (30m): <span class="font-semibold text-amber-300" id="locked-total"><?php echo number_format($recovery_locked_total); ?></span>
                </div>
            </div>

            <?php if (!$has_recovery_schema): ?>
                <p class="text-sm text-gray-300">No recovery data found.</p>
            <?php else: ?>
                <?php if (empty($recovery_rows)): ?>
                    <p class="text-sm text-gray-300">No pending conversions. You're clear to train.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-300 border-b border-gray-700">
                                    <th class="py-2 pr-4">Batch</th>
                                    <th class="py-2 pr-4">From</th>
                                    <th class="py-2 pr-4">Quantity</th>
                                    <th class="py-2 pr-4">Available (UTC)</th>
                                    <th class="py-2 pr-4">Time Remaining</th>
                                </tr>
                            </thead>
                            <tbody id="recovery-rows">
                                <?php foreach ($recovery_rows as $r): 
                                    $is_ready = ($r['sec_remaining'] <= 0);
                                    $batch_label = '#' . (int)$r['id'];
                                    $from = htmlspecialchars(ucfirst($r['unit_type']));
                                    $qty  = (int)$r['quantity'];
                                    $avail = htmlspecialchars($r['available_at']);
                                    $sec  = (int)$r['sec_remaining'];
                                ?>
                                <tr class="border-b border-gray-800">
                                    <td class="py-2 pr-4"><?php echo $batch_label; ?></td>
                                    <td class="py-2 pr-4"><?php echo $from; ?></td>
                                    <td class="py-2 pr-4 font-semibold text-white"><?php echo number_format($qty); ?></td>
                                    <td class="py-2 pr-4"><?php echo $avail; ?></td>
                                    <td class="py-2 pr-4">
                                        <span
                                            class="inline-block px-2 py-0.5 rounded <?php echo $is_ready ? 'bg-green-900 text-green-300' : 'bg-yellow-900 text-amber-300'; ?>"
                                            data-countdown="<?php echo $sec; ?>"
                                            data-qty="<?php echo $qty; ?>"
                                        >
                                            <?php echo $is_ready ? 'Ready' : '—'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">Tip: This table updates live; when a batch hits 00:00 it will flip to <span class="text-green-300 font-semibold">Ready</span>.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Simple client-side tab toggling (no other files touched)
(function(){
    const tabs = [
        {btn:'train-tab-btn',    panel:'train-tab-content',    key:'train'},
        {btn:'disband-tab-btn',  panel:'disband-tab-content',  key:'disband'},
        {btn:'recovery-tab-btn', panel:'recovery-tab-content', key:'recovery'}
    ];
    function activate(key){
        tabs.forEach(t=>{
            const b = document.getElementById(t.btn);
            const p = document.getElementById(t.panel);
            if(!b||!p) return;
            const active = (t.key===key);
            p.classList.toggle('hidden', !active);
            b.classList.toggle('bg-gray-700', active);
            b.classList.toggle('text-white', active);
            b.classList.toggle('font-semibold', active);
            b.classList.toggle('bg-gray-800', !active);
            b.classList.toggle('text-gray-400', !active);
        });
        // Update URL param (so reload preserves tab)
        const u = new URL(window.location.href);
        u.searchParams.set('tab', key);
        window.history.replaceState({}, '', u.toString());
    }
    tabs.forEach(t=>{
        const b = document.getElementById(t.btn);
        if(b) b.addEventListener('click', ()=>activate(t.key));
    });
})();

// Live countdown for recovery rows
(function(){
    const nodes = Array.from(document.querySelectorAll('[data-countdown]'));
    if(nodes.length===0) return;
    const lockedTotalEl = document.getElementById('locked-total');

    function fmt(sec){
        if (sec <= 0) return "00:00";
        const h = Math.floor(sec/3600);
        const m = Math.floor((sec%3600)/60);
        const s = sec%60;
        return (h>0?String(h).padStart(2,'0')+':':'')+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
    }

    function tick(){
        let lockedTotal = 0;
        nodes.forEach(el=>{
            let sec = parseInt(el.getAttribute('data-countdown'),10);
            const qty = parseInt(el.getAttribute('data-qty'),10) || 0;
            if (isNaN(sec)) return;

            if (sec <= 0){
                el.textContent = 'Ready';
                el.classList.remove('bg-yellow-900','text-amber-300');
                el.classList.add('bg-green-900','text-green-300');
                return;
            }
            sec -= 1;
            el.textContent = fmt(sec);
            el.setAttribute('data-countdown', sec);
            if (sec > 0) lockedTotal += qty;
        });
        if (lockedTotalEl){
            lockedTotalEl.textContent = lockedTotal.toLocaleString();
        }
    }
    tick();
    setInterval(tick, 1000);
})();
</script>

<?php
// --- INCLUDE UNIVERSAL FOOTER ---
include_once __DIR__ . '/../includes/footer.php';
?>
