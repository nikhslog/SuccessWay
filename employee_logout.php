<?php
// employee_logout.php
require_once 'config.php';

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: employee_login.php");
exit;
?>