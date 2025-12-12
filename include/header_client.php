<?php
// include/header_client.php
require_once __DIR__ . '/../config/db.php';

$userId    = current_user_id();
$userEmail = current_user_email();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Кинотеатр — онлайн билеты' ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
</head>
<body>
<div class="container">
    <header class="header">
        <div class="header__logo">Cinema</div>
        <nav class="header__nav">
            <a href="<?= BASE_URL ?>/client/index.php">Афиша</a>

            <?php if ($userId): ?>
                <a href="<?= BASE_URL ?>/client/my_tickets.php">Мои билеты</a>
                <a href="<?= BASE_URL ?>/client/logout.php">Выход (<?= htmlspecialchars($userEmail) ?>)</a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/client/login.php">Вход</a>
                <a href="<?= BASE_URL ?>/client/register.php">Регистрация</a>
            <?php endif; ?>
        </nav>
    </header>
