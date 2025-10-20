<?php 
// /template/includes/auto_recruiter/post_handler.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once $ROOT . '/src/Controllers/RecruitmentController.php';
    exit;
}
?>