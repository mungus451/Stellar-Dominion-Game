<!-- /template/includes/alliance/messages.php -->

<?php if (isset($_SESSION['alliance_error'])): ?>
        <div class="content-box text-red-200 border-red-600/60 p-3 rounded-md text-center mb-4" style="border-color:rgba(220,38,38,.6)">
            <?= e($_SESSION['alliance_error']); unset($_SESSION['alliance_error']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['alliance_message'])): ?>
        <div class="content-box text-emerald-200 border-emerald-600/60 p-3 rounded-md text-center mb-4" style="border-color:rgba(5,150,105,.6)">
            <?= e($_SESSION['alliance_message']); unset($_SESSION['alliance_message']); ?>
        </div>
    <?php endif; ?>