<?php
// include/header_admin.php
require_once __DIR__ . '/../config/db.php';

$currentPage = basename($_SERVER['PHP_SELF']);

// защита: если не админ и не login.php — редирект
if (!is_admin() && $currentPage !== 'login.php') {
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}

// флаг: сейчас страница отчётов?
$isReportsPage = ($currentPage === 'reports.php');
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Admin — Cinema' ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
</head>
<body>
<div class="container<?= $isReportsPage ? ' container--wide' : '' ?>">
    <header class="header">
        <div class="header__logo">Cinema — Admin</div>
        <nav class="header__nav">
            <a href="<?= BASE_URL ?>/admin/index.php">Главная</a>
            <a href="<?= BASE_URL ?>/admin/films.php">Фильмы</a>
            <a href="<?= BASE_URL ?>/admin/halls.php">Залы</a>
            <a href="<?= BASE_URL ?>/admin/seances.php">Сеансы</a>
            <a href="<?= BASE_URL ?>/admin/check_ticket.php">Проверка билета</a>
            <a href="<?= BASE_URL ?>/admin/reports.php">Отчёты</a>
            <a href="<?= BASE_URL ?>/admin/logout.php">Выход</a>
        </nav>
    </header>
