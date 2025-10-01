<?php
// template/includes/bank/alerts.php
?>
<?php if (isset($_SESSION['bank_message'])): ?>
    <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
        <?php echo htmlspecialchars($_SESSION['bank_message']); unset($_SESSION['bank_message']); ?>
    </div>
<?php endif; ?>
<?php if (isset($_SESSION['bank_error'])): ?>
    <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
        <?php echo htmlspecialchars($_SESSION['bank_error']); unset($_SESSION['bank_error']); ?>
    </div>
<?php endif; ?>
