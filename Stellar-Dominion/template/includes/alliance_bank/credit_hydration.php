<?php 
// /template/includes/alliance_bank/credit_hydration.php

$credit_rating_map = [
    'A++' => 50000000, 'A+' => 25000000, 'A' => 10000000,
    'B' => 5000000, 'C' => 1000000, 'D' => 500000, 'F' => 0
];
$max_loan = (int)($credit_rating_map[$user_data['credit_rating']] ?? 0);
?>