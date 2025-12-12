<?php
$pageTitle = 'Вход';
require_once __DIR__ . '/../include/header_client.php';

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Укажите корректный e-mail.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['user_password'])) {
            $errors[] = 'Неверный e-mail или пароль.';
        } else {
            // Успешный вход
            $_SESSION['user_id']    = (int)$user['user_id'];
            $_SESSION['user_email'] = $user['user_email'];

            // можно сделать redirect обратно на афишу или "мои билеты"
            header('Location: ' . BASE_URL . '/client/index.php');
            exit;
        }
    }
}
?>
<h1>Вход</h1>

<?php if ($errors): ?>
    <div class="error" style="margin-top:10px;">
        <?php foreach ($errors as $e): ?>
            <div><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" style="margin-top:20px;max-width:350px;">
    <div class="form-group">
        <label for="email">E-mail</label>
        <input type="email" name="email" id="email"
               value="<?= htmlspecialchars($email) ?>" required>
    </div>

    <div class="form-group">
        <label for="password">Пароль</label>
        <input type="password" name="password" id="password" required>
    </div>

    <button type="submit">Войти</button>
</form>

<p style="margin-top:10px;">
    Нет аккаунта? <a href="<?= BASE_URL ?>/client/register.php">Зарегистрироваться</a>
</p>

<?php require_once __DIR__ . '/../include/footer_client.php'; ?>
