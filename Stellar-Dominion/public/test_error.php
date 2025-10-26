<?php
// We need to load the config to activate the error handler
require_once __DIR__ . '/../config/config.php';

echo "You should not see this text.";

// This line will intentionally cause a fatal error
this_is_a_test_function_that_does_not_exist();

echo "You also should not see this text.";
?>