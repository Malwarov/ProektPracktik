<?php
$pageTitle = 'Мои билеты';
require_once __DIR__ . '/../include/header_client.php';

$userId = current_user_id();

if (!$userId) {
    // если не авторизован — отправим на вход
    header('Location: ' . BASE_URL . '/client/login.php');
    exit;
}

$sql = "
    SELECT sales.*, s.seance_start, f.film_name, h.hall_name
    FROM sales
    JOIN seances s ON s.seance_id = sales.sale_seance_id
    JOIN films f   ON f.film_id   = s.seance_film_id
    JOIN halls h   ON h.hall_id   = sales.sale_hall_id
    WHERE sales.sale_user_id = :uid
    ORDER BY sales.sale_timestamp DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':uid' => $userId]);
$tickets = $stmt->fetchAll();
?>
<h1>Мои билеты</h1>

<?php if (!$tickets): ?>
    <p style="margin-top:10px;">У вас пока нет покупок.</p>
<?php else: ?>
    <table class="table" style="margin-top:15px;">
        <tr>
            <th>№</th>
            <th>Фильм</th>
            <th>Зал</th>
            <th>Дата и время</th>
            <th>Сумма</th>
            <th></th>
        </tr>
        <?php foreach ($tickets as $t): ?>
            <tr>
                <td><?= (int)$t['sale_id'] ?></td>
                <td><?= htmlspecialchars($t['film_name']) ?></td>
                <td><?= htmlspecialchars($t['hall_name']) ?></td>
                <td><?= (new DateTime($t['seance_start']))->format('d.m.Y H:i') ?></td>
                <td><?= number_format($t['sale_amount'], 2, '.', ' ') ?> ₽</td>
                <td>
                    <a class="btn"
                       href="<?= BASE_URL ?>/client/ticket.php?sale_id=<?= (int)$t['sale_id'] ?>">
                        Открыть
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<?php require_once __DIR__ . '/../include/footer_client.php'; ?>
