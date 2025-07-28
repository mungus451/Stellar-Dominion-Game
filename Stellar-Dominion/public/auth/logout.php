<?php
session_start();
 
// Unset all of the session variables
$_SESSION = array();
 
// Destroy the session.
session_destroy();
 
// Redirect to login page
header("location: /"); // Corrected to a route handled by the front controller
exit;
?>
