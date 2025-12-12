<?php
require_once __DIR__ . '/../config/config.php';
$_SESSION['admin_logged_in'] = false;
session_destroy();
header('Location: ' . BASE_URL . '/admin/login.php');
exit;
