<?php
// /template/includes/armory/post_handler.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once $ROOT . '/src/Controllers/ArmoryController.php';
    exit;
}
?>