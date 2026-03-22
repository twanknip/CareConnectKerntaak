<?php
session_start();

// Prevent browser from caching protected pages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Session timeout after 30 minutes
$timeout = 1800;
if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > $timeout) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit;
    }
}
$_SESSION['last_activity'] = time();