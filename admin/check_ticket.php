<?php
// admin/check_ticket.php

require_once __DIR__ . '/../config/db.php';

// доступ только админу
if (!is_admin()) {
    die('Доступ запрещён.');
}

$ticket      = null;
$message     = '';
$messageType = ''; // success / warning / error

/**
 * Общая функция проверки билета
 */
function check_ticket(PDO $pdo, int $ticketId, string $token, ?array &$ticket, string &$message, string &$messageType): void
{
    // проверяем токен
    $expected = ticket_token($ticketId);

    if (!hash_equals($expected, $token)) {
        $message     = 'Неверный токен билета. Возможна подделка QR-кода.';
        $messageType = 'error';
        $ticket      = null;
        return;
    }

    // ищем билет
    $sql = "
        SELECT 
            sales.*,
            u.user_email,
            s.seance_start,
            f.film_name,
            h.hall_name
        FROM sales
        JOIN users   u ON u.user_id   = sales.sale_user_id
        JOIN seances s ON s.seance_id = sales.sale_seance_id
        JOIN films   f ON f.film_id   = s.seance_film_id
        JOIN halls   h ON h.hall_id   = sales.sale_hall_id
        WHERE sales.sale_id = :id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $ticketId]);
    $row = $stmt->fetch();

    if (!$row) {
        $message     = 'Билет не найден.';
        $messageType = 'error';
        $ticket      = null;
        return;
    }

    // есть билет — проверяем статус
    if ($row['sale_status'] === 'used') {
        // уже использован
        $message     = 'Билет уже использован.';
        $messageType = 'warning';
        $ticket      = $row;
        return;
    }

    // помечаем как использованный
    $upd = $pdo->prepare("
        UPDATE sales
        SET sale_status = 'used',
            sale_used_at = NOW()
        WHERE sale_id = :id
    ");
    $upd->execute([':id' => $ticketId]);

    // перечитываем
    $stmt->execute([':id' => $ticketId]);
    $row = $stmt->fetch();

    $ticket      = $row;
    $message     = 'Билет успешно подтверждён. Можно пропускать зрителя.';
    $messageType = 'success';
}

// ==========================
// 1) Проверка из GET (QR-ссылка)
// ==========================
if (isset($_GET['ticket_id'], $_GET['token']) && $_GET['ticket_id'] !== '' && $_GET['token'] !== '') {
    $ticketId = (int)$_GET['ticket_id'];
    $token    = (string)$_GET['token'];

    if ($ticketId > 0) {
        check_ticket($pdo, $ticketId, $token, $ticket, $message, $messageType);
    }
}

// ==========================
// 2) Обработка форм с этой страницы
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Вариант 1: вставили полную ссылку
    if (isset($_POST['mode']) && $_POST['mode'] === 'link') {
        $link = trim($_POST['qr_link'] ?? '');

        if ($link === '') {
            $message     = 'Введите ссылку из QR-кода.';
            $messageType = 'error';
        } else {
            $parts = parse_url($link);
            if (!isset($parts['query'])) {
                $message     = 'Некорректная ссылка.';
                $messageType = 'error';
            } else {
                parse_str($parts['query'], $q);
                $ticketId = isset($q['ticket_id']) ? (int)$q['ticket_id'] : 0;
                $token    = $q['token'] ?? '';

                if ($ticketId <= 0 || $token === '') {
                    $message     = 'В ссылке не найдены параметры ticket_id и token.';
                    $messageType = 'error';
                } else {
                    check_ticket($pdo, $ticketId, $token, $ticket, $message, $messageType);
                }
            }
        }
    }

    // Вариант 2: ввели ID и токен вручную
    if (isset($_POST['mode']) && $_POST['mode'] === 'params') {
        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
        $token    = trim($_POST['token'] ?? '');

        if ($ticketId <= 0 || $token === '') {
            $message     = 'Укажите и номер билета, и токен.';
            $messageType = 'error';
        } else {
            check_ticket($pdo, $ticketId, $token, $ticket, $message, $messageType);
        }
    }
}

$pageTitle = 'Проверка билета';
require_once __DIR__ . '/../include/header_admin.php';
?>

<h1>Проверка билета по QR</h1>
<p>
    Отсканируйте QR-код билета телефоном (контролёром) или используйте формы ниже
    для ручной проверки билета.
</p>

<?php if ($message): ?>
    <div style="margin: 15px 0; padding: 10px 15px; border-radius: 6px;
        <?php if ($messageType === 'success'): ?>
            background:#1f3d1f; color:#b7ffb7;
        <?php elseif ($messageType === 'warning'): ?>
            background:#3d301f; color:#ffe0a3;
        <?php else: ?>
            background:#3d1f1f; color:#ffb7b7;
        <?php endif; ?>
    ">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($ticket): ?>
    <?php $dt = new DateTime($ticket['seance_start']); ?>
    <div style="margin-top:15px; padding:15px 20px; border-radius:8px; background:#181818;">
        <h2 style="margin-top:0;">Информация о билете</h2>
        <p><strong>Номер билета:</strong> <?= (int)$ticket['sale_id'] ?></p>
        <p><strong>Фильм:</strong> <?= htmlspecialchars($ticket['film_name']) ?></p>
        <p><strong>Дата и время сеанса:</strong> <?= $dt->format('d.m.Y H:i') ?></p>
        <p><strong>Зал:</strong> <?= htmlspecialchars($ticket['hall_name']) ?></p>
        <p><strong>Покупатель (e-mail):</strong> <?= htmlspecialchars($ticket['user_email']) ?></p>
        <p><strong>Сумма:</strong> <?= number_format($ticket['sale_amount'], 2, '.', ' ') ?> ₽</p>
        <p><strong>Статус:</strong>
            <?php if ($ticket['sale_status'] === 'used'): ?>
                <span style="color:#ff6b6b;">использован</span>
                <?php if ($ticket['sale_used_at']): ?>
                    (<?= (new DateTime($ticket['sale_used_at']))->format('d.m.Y H:i:s') ?>)
                <?php endif; ?>
            <?php else: ?>
                <span style="color:#4cd964;">активен</span>
            <?php endif; ?>
        </p>
    </div>
<?php endif; ?>

<hr style="margin:30px 0; border-color:#333;">

<h2>Ручная проверка билета</h2>

<div style="display:flex; flex-wrap:wrap; gap:30px; margin-top:10px;">

    <!-- Форма 1: вставить ссылку целиком -->
    <div style="flex:1 1 300px; min-width:260px;">
        <h3>Проверка по ссылке из QR</h3>
        <p style="font-size:13px; opacity:0.85;">
            Вставьте сюда ссылку, полученную при сканировании QR-кода
            (например, с телефона или сканера штрих-кодов).
        </p>
        <form method="post">
            <input type="hidden" name="mode" value="link">
            <div style="margin-bottom:10px;">
                <textarea name="qr_link" rows="3" style="width:100%; padding:8px; border-radius:4px; border:1px solid #444; background:#111; color:#eee;"></textarea>
            </div>
            <button type="submit" class="btn">Проверить по ссылке</button>
        </form>
    </div>

    <!-- Форма 2: ID + token -->
    <div style="flex:1 1 260px; min-width:260px;">
        <h3>Проверка по ID и токену</h3>
        <p style="font-size:13px; opacity:0.85;">
            Этот вариант полезен для отладки. Токен можно взять из ссылки,
            которую формирует система в QR-коде.
        </p>
        <form method="post">
            <input type="hidden" name="mode" value="params">
            <div style="margin-bottom:8px;">
                <label style="display:block; font-size:13px; margin-bottom:3px;">Номер билета (ID)</label>
                <input type="number" name="ticket_id"
                       style="width:100%; padding:6px; border-radius:4px; border:1px solid #444; background:#111; color:#eee;">
            </div>
            <div style="margin-bottom:10px;">
                <label style="display:block; font-size:13px; margin-bottom:3px;">Токен</label>
                <input type="text" name="token"
                       style="width:100%; padding:6px; border-radius:4px; border:1px solid #444; background:#111; color:#eee;">
            </div>
            <button type="submit" class="btn">Проверить по ID и токену</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../include/footer_admin.php'; ?>
