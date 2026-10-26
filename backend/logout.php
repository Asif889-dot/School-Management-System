<?php
/**
 * Logout Script
 * Destroys session and redirects to login page
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page with logout message
header('Location: ../index.php?message=logged_out');
exit;
