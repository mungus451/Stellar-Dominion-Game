<?php
// /template/includes/alliance_bank/get_view.php

$csrf_token   = generate_csrf_token();
$user_id      = (int)($_SESSION['id'] ?? 0);
$current_tab  = $_GET['tab'] ?? 'main';

?>