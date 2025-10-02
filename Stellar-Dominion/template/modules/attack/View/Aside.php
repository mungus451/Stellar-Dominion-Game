<?php
declare(strict_types=1);

/** @var array $state provided by entry.php */
$q = '';
if (isset($_GET['search_user'])) {
    $q = (string)$_GET['search_user'];
} elseif (!empty($ctx['q'])) {
    $q = (string)$ctx['q'];
}
?>
<aside class="lg:col-span-1 space-y-4">
    <!-- Player quick search -->
    <div class="content-box rounded-lg p-3">
        <form method="GET" action="/attack.php" class="space-y-2">
            <label for="search_user" class="block text-xs text-gray-300">Find Player by Username</label>
            <div class="flex items-center gap-2">
                <input id="search_user" name="search_user" type="text" placeholder="Enter username"
                       class="flex-1 bg-gray-900 border border-gray-600 rounded-md px-2 py-1 text-sm text-white"
                       maxlength="64" autocomplete="off"
                       value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit"
                        class="bg-cyan-700 hover:bg-cyan-600 text-white text-xs font-semibold py-1 px-2 rounded-md">
                    Search
                </button>
            </div>
            <p class="text-[11px] text-gray-400">Exact username works best. Partial is OK.</p>
        </form>
    </div>

    <?php
    // Try common locations from project root
    $candidates = [
        dirname(__DIR__, 3) . '/pages/includes/advisor.php',
        dirname(__DIR__, 3) . '/includes/advisor.php',
        dirname(__DIR__, 4) . '/pages/includes/advisor.php',
    ];
    foreach ($candidates as $p) {
        if (is_file($p)) { include_once $p; break; }
    }
    ?>
</aside>
