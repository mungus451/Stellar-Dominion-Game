<?php
// /template/includes/alliance_bank/helpers.php

function vh($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_amount_signed($type, $amount) {
    $pos = in_array($type, ['deposit','tax','loan_repaid','interest_yield'], true);
    return ($pos ? '+' : '-') . number_format((int)$amount);
}

?>