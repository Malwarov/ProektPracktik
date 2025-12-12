<?php
// client/booking_save.php
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/client/index.php');
    exit;
}

$seance_id = isset($_POST['seance_id']) ? (int)$_POST['seance_id'] : 0;
$seats     = isset($_POST['seats']) ? (array)$_POST['seats'] : [];

if (!$seance_id || !count($seats)) {
    die('Не выбраны места или не указан сеанс.');
}

// данные сеанса и зала
$sql = "
    SELECT s.*, h.hall_id, h.hall_price_standard
    FROM seances s
    JOIN halls h ON h.hall_id = s.seance_hall_id
    WHERE s.seance_id = :id
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $seance_id]);
$seance = $stmt->fetch();

if (!$seance) {
    die('Сеанс не найден');
}

// Авторизован ли пользователь?
$userId    = current_user_id();
$userEmail = current_user_email();

if ($userId) {
    // Берём e-mail из БД для надёжности
    $stmt = $pdo->prepare("SELECT user_email FROM users WHERE user_id = :id");
    $stmt->execute([':id' => $userId]);
    $u = $stmt->fetch();
    if ($u) {
        $userEmail = $u['user_email'];
    }
} else {
    // старое поведение: регистрируем по введённому e-mail
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die('Укажите корректный e-mail.');
    }

    // ищем пользователя
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        $passwordPlain = bin2hex(random_bytes(4)); // временный пароль
        $passwordHash  = password_hash($passwordPlain, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (user_email, user_password)
            VALUES (:email, :password)
        ");
        $stmt->execute([
            ':email'    => $email,
            ':password' => $passwordHash,
        ]);
        $userId    = (int)$pdo->lastInsertId();
        $userEmail = $email;

        // можно сразу авторизовать:
        $_SESSION['user_id']    = $userId;
        $_SESSION['user_email'] = $userEmail;
    } else {
        $userId    = (int)$user['user_id'];
        $userEmail = $user['user_email'];
    }
}

// Цена = стандартная * количество мест
$pricePerSeat = (float)$seance['hall_price_standard'];
$totalAmount  = $pricePerSeat * count($seats);

$seatConfigJson = json_encode(array_values($seats), JSON_UNESCAPED_UNICODE);

// сохраняем продажу
$stmt = $pdo->prepare("
    INSERT INTO sales (sale_hall_id, sale_seance_id, sale_user_id, sale_configuration, sale_amount)
    VALUES (:hall_id, :seance_id, :user_id, :config, :amount)
");
$stmt->execute([
    ':hall_id'   => (int)$seance['hall_id'],
    ':seance_id' => $seance_id,
    ':user_id'   => $userId,
    ':config'    => $seatConfigJson,
    ':amount'    => $totalAmount,
]);

$sale_id = (int)$pdo->lastInsertId();

header('Location: ' . BASE_URL . '/client/ticket.php?sale_id=' . $sale_id);
exit;
