<?php
// /template/includes/alliance/charter.php

$alliance_charter = '';
if ($alliance) {
    $alliance_charter = (string)($alliance['description'] ?? '');

    if (trim($alliance_charter) === '') {
        $alliance_charter = 'No charter has been set yet.';
    }
}

?>