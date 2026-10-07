<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

session_unset();
session_destroy();

session_start();
setFlash('info', 'You have been logged out successfully.');
header("Location: " . SITE_URL . "/login.php");
exit;
