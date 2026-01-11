<?php
/**
 * Logout Script - Ethiopian Police University Library Management System
 * Handles user logout and session cleanup
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

// Start secure session
start_secure_session();

// Perform logout
logout_user();

// Redirect to login page with success message
header('Location: index.php?logout=success');
exit();
?>