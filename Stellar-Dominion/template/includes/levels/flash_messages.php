<?php
/**
 * Levels page flash messages.
 * Reads and clears these session keys:
 * - level_up_message, level_up_error
 * - level_message (legacy), level_error (legacy)
 */

declare(strict_types=1);

$flashKeys = [
    // key             => [css box classes, text color classes]
    'level_up_message' => ['bg-cyan-900 border border-cyan-500/50', 'text-cyan-300'],
    'level_up_error'   => ['bg-red-900 border border-red-500/50',   'text-red-300'],
    'level_message'    => ['bg-cyan-900 border border-cyan-500/50', 'text-cyan-300'],
    'level_error'      => ['bg-red-900 border border-red-500/50',   'text-red-300'],
];

foreach ($flashKeys as $key => [$boxClass, $textClass]) {
    if (!empty($_SESSION[$key])): ?>
        <div class="<?php echo $boxClass; ?> <?php echo $textClass; ?> p-3 rounded-md text-center">
            <?php
                echo htmlspecialchars((string)$_SESSION[$key], ENT_QUOTES, 'UTF-8');
                unset($_SESSION[$key]);
            ?>
        </div>
    <?php endif;
}
