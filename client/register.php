<?php
$pageTitle = 'Регистрация';
require_once __DIR__ . '/../include/header_client.php';

$errors = [];
$email  = '';
$password = '';
$password2 = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Укажите корректный e-mail.';
    }

    if (mb_strlen($password) < 6) {
        $errors[] = 'Пароль должен быть не короче 6 символов.';
    }

    if ($password !== $password2) {
        $errors[] = 'Пароли не совпадают.';
    }

    // Проверяем, нет ли уже такого e-mail
    if (!$errors) {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Пользователь с таким e-mail уже зарегистрирован.';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (user_email, user_password)
            VALUES (:email, :password)
        ");
        $stmt->execute([
            ':email'    => $email,
            ':password' => $hash,
        ]);

        $userId = (int)$pdo->lastInsertId();

        // Авторизуем сразу после регистрации
        $_SESSION['user_id']    = $userId;
        $_SESSION['user_email'] = $email;

        header('Location: ' . BASE_URL . '/client/index.php');
        exit;
    }
}
?>
<h1>Регистрация</h1>

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

    <div class="form-group">
        <label for="password2">Повтор пароля</label>
        <input type="password" name="password2" id="password2" required>
    </div>

    <button type="submit">Зарегистрироваться</button>
</form>

<?php require_once __DIR__ . '/../include/footer_client.php'; ?>
