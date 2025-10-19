<?php
// /template/includes/alliance_transfer/post_handler.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../src/Controllers/AllianceTransferController.php';
    exit;
}
?>