<?php
// config/db.php

// =====================
// ОБЩИЙ КОНФИГ (BASE_URL, session_start и т.д.)
// =====================
require_once __DIR__ . '/config.php';

// =================================================================
// ПОДКЛЮЧЕНИЕ К БАЗЕ ДАННЫХ
// =================================================================

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // В продакшене — логировать
    die('Ошибка подключения к базе данных');
}

// =================================================================
// АВТОРИЗАЦИЯ / СЕССИИ
// =================================================================

/**
 * Авторизован ли пользователь (клиент)
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
}

/**
 * ID текущего авторизованного клиента или null
 */
function current_user_id(): ?int
{
    return is_logged_in() ? (int)$_SESSION['user_id'] : null;
}

/**
 * E-mail текущего авторизованного клиента или null
 */
function current_user_email(): ?string
{
    return $_SESSION['user_email'] ?? null;
}

/**
 * Проверка: администратор ли это (для админ-панели)
 */
function is_admin(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

// =================================================================
// QR / БИЛЕТЫ
// =================================================================

// Секрет для защиты QR-кодов билетов
// ❗️РЕКОМЕНДУЕТСЯ изменить на свой случайный набор
const QR_SECRET = '8c7f9a2e4d4180be6fa34b9b2c0f3e742bf36f2f8cb2a9c72d4b83e06aa5d1c9';

/**
 * Генерация безопасного токена билета для QR
 * Используется при генерации PDF и при проверке билета
 */
function ticket_token(int $saleId): string
{
    return hash_hmac('sha256', (string)$saleId, QR_SECRET);
}
