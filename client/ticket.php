<?php
// client/ticket.php
$pageTitle = 'Ваш билет';
require_once __DIR__ . '/../include/header_client.php';

// Подключаем библиотеку QR-кодов
require_once __DIR__ . '/../vendor/phpqrcode/qrlib.php';

$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;
if (!$sale_id) {
    die('Не указан номер билета');
}

$sql = "
    SELECT sales.*, u.user_email,
           s.seance_start,
           f.film_name,
           h.hall_name
    FROM sales
    JOIN users u   ON u.user_id   = sales.sale_user_id
    JOIN seances s ON s.seance_id = sales.sale_seance_id
    JOIN films f   ON f.film_id   = s.seance_film_id
    JOIN halls h   ON h.hall_id   = sales.sale_hall_id
    WHERE sales.sale_id = :id
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $sale_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    die('Билет не найден');
}

// можно добавить проверку, что это билет текущего пользователя
if ($ticket['sale_user_id'] !== current_user_id()) {
    die('Доступ запрещён.');
}

$seats = json_decode($ticket['sale_configuration'], true) ?: [];

// =====================================================================
// ГЕНЕРАЦИЯ QR-КОДА
// =====================================================================

$qrDir = __DIR__ . '/../storage/qr/';
if (!is_dir($qrDir)) {
    mkdir($qrDir, 0777, true);
}

$qrFilename = 'ticket_' . (int)$ticket['sale_id'] . '.png';
$qrFilePath = $qrDir . $qrFilename;

// Формируем защищённый URL проверки билета (для контролёра/админа)
$token   = ticket_token((int)$ticket['sale_id']);
$qrUrl   = BASE_URL . '/admin/check_ticket.php?ticket_id=' . (int)$ticket['sale_id'] . '&token=' . $token;

// Данные, которые будут зашиты в QR-код
// ВАЖНО: только URL проверки билета, без лишнего текста
$qrData = $qrUrl;

// Генерируем файл, если его ещё нет
if (!file_exists($qrFilePath)) {
    QRcode::png($qrData, $qrFilePath, QR_ECLEVEL_L, 4);
}

// Относительный путь для <img>
$qrWebPath = BASE_URL . '/storage/qr/' . $qrFilename;
?>
<h1>Ваш билет</h1>

<p style="margin-top:10px;">
    Номер билета: <strong><?= (int)$ticket['sale_id'] ?></strong><br>
    Фильм: <strong><?= htmlspecialchars($ticket['film_name']) ?></strong><br>
    Зал: <?= htmlspecialchars($ticket['hall_name']) ?><br>
    Дата и время: <?= (new DateTime($ticket['seance_start']))->format('d.m.Y H:i') ?><br>
    Места:
    <?php foreach ($seats as $s): ?>
        <strong><?= htmlspecialchars($s) ?></strong>
    <?php endforeach; ?>
    <br>
    Сумма: <?= number_format($ticket['sale_amount'], 2, '.', ' ') ?> ₽<br>
    E-mail: <?= htmlspecialchars($ticket['user_email']) ?>
</p>

<div style="margin-top:20px; display:flex; gap:30px; align-items:center; flex-wrap:wrap;">
    <div>
        <h3>QR-код билета</h3>
        <p style="font-size:13px; opacity:0.8; max-width:260px;">
            Покажите этот QR-код контролёру — он содержит ссылку для проверки билета в системе.
        </p>
        <img src="<?= $qrWebPath ?>" alt="QR-код билета">
    </div>

    <div>
        <a class="btn"
           href="<?= BASE_URL ?>/client/ticket_pdf.php?sale_id=<?= (int)$ticket['sale_id'] ?>"
           target="_blank">
            Скачать билет в PDF
        </a>
    </div>
</div>

<p style="margin-top:20px;">
    Также вы можете просто показать этот экран на входе в зал.
</p>

<?php require_once __DIR__ . '/../include/footer_client.php'; ?>
