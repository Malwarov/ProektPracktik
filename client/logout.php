<?php
require_once __DIR__ . '/../config/config.php';

// Сбрасываем только клиентскую авторизацию
unset($_SESSION['user_id'], $_SESSION['user_email']);

header('Location: ' . BASE_URL . '/client/index.php');
exit;
