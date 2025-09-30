<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$mysqli = @new mysqli('localhost','admin','password','users');
if ($mysqli->connect_error) {
    echo "DB FAIL: " . $mysqli->connect_errno . " - " . $mysqli->connect_error;
    exit;
}
echo "DB OK: " . $mysqli->host_info;
