
<!-- /template/includes/alliance_bank/messages.php -->

<?php if(isset($_SESSION['alliance_message'])): ?>
        <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
            <?php echo vh($_SESSION['alliance_message']); unset($_SESSION['alliance_message']); ?>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['alliance_error'])): ?>
        <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
            <?php echo vh($_SESSION['alliance_error']); unset($_SESSION['alliance_error']); ?>
        </div>
    <?php endif; ?>

