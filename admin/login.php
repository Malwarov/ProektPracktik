<?php
require_once __DIR__ . '/../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'] ?? '';
    $pass  = $_POST['password'] ?? '';

    // Простая пара логин/пароль. Лучше вынести в таблицу admins.
    if ($login === 'admin' && $pass === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        header('Location: ' . BASE_URL . '/admin/index.php');
        exit;
    } else {
        $error = 'Неверный логин или пароль';
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход в админ-панель</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
</head>
<body>
<div class="container">
    <h1>Админ-панель — вход</h1>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" style="margin-top:20px;max-width:320px;">
        <div class="form-group">
            <label for="login">Логин</label>
            <input type="text" name="login" id="login" required>
        </div>

        <div class="form-group">
            <label for="password">Пароль</label>
            <input type="password" name="password" id="password" required>
        </div>

        <button type="submit">Войти</button>
    </form>
</div>
</body>
</html>
