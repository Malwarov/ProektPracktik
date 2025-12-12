<?php
// client/hall.php

// 1. Сначала задаём заголовок страницы
$pageTitle = 'Выбор мест';

// 2. Подключаем шапку клиента, а через неё — config/db.php и все функции
require_once __DIR__ . '/../include/header_client.php';

// 3. Теперь можно безопасно вызывать current_user_id() / current_user_email()
$userId    = current_user_id();
$userEmail = current_user_email();

// 4. Получаем ID сеанса из GET
$seance_id = isset($_GET['seance_id']) ? (int)$_GET['seance_id'] : 0;
if (!$seance_id) {
    die('Не указан сеанс');
}

// 5. Берём данные по сеансу, фильму и залу
$sql = "
    SELECT s.*, 
           f.film_name, f.film_duration, f.film_age_limit,
           h.hall_name, h.hall_rows, h.hall_places, h.hall_price_standard
    FROM seances s
    JOIN films f ON f.film_id = s.seance_film_id
    JOIN halls h ON h.hall_id = s.seance_hall_id
    WHERE s.seance_id = :id
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $seance_id]);
$seance = $stmt->fetch();

if (!$seance) {
    die('Сеанс не найден');
}

// 6. Собираем уже занятые места (из таблицы sales)
$sqlTaken = "SELECT sale_configuration FROM sales WHERE sale_seance_id = :id";
$stmt = $pdo->prepare($sqlTaken);
$stmt->execute([':id' => $seance_id]);
$taken = [];

while ($row = $stmt->fetch()) {
    $conf = json_decode($row['sale_configuration'], true);
    if (is_array($conf)) {
        $taken = array_merge($taken, $conf);
    }
}

$taken = array_unique($taken);
?>
<h1>Выбор мест</h1>

<p style="margin-top:10px;">
    Фильм: <strong><?= htmlspecialchars($seance['film_name']) ?></strong><br>
    Зал: <?= htmlspecialchars($seance['hall_name']) ?><br>
    Начало: <?= (new DateTime($seance['seance_start']))->format('d.m.Y H:i') ?><br>
    Базовая цена: <?= number_format($seance['hall_price_standard'], 2, '.', ' ') ?> ₽
</p>

<form method="post" action="<?= BASE_URL ?>/client/booking_save.php" style="margin-top:20px;">
    <input type="hidden" name="seance_id" value="<?= (int)$seance_id ?>">

    <div class="hall-grid">
        <?php for ($row = 1; $row <= (int)$seance['hall_rows']; $row++): ?>
            <div class="hall-row">
                <?php for ($place = 1; $place <= (int)$seance['hall_places']; $place++): ?>
                    <?php
                        $seatKey = $row . '-' . $place;      // например "3-8"
                        $isTaken = in_array($seatKey, $taken, true);
                    ?>
                    <div
                        class="seat <?= $isTaken ? 'seat--taken' : '' ?>"
                        data-seat="<?= $seatKey ?>"
                        onclick="toggleSeat(this)">
                        <?= $place ?>
                    </div>

                    <!-- чекбокс, который реально отправится в форму -->
                    <input type="checkbox"
                           id="seat-<?= $seatKey ?>"
                           name="seats[]"
                           value="<?= $seatKey ?>"
                           style="display:none;"
                           <?= $isTaken ? 'disabled' : '' ?>>
                <?php endfor; ?>
            </div>
        <?php endfor; ?>
    </div>

    <div style="margin-top:20px;">
        <?php if ($userId): ?>
            <p>
                Покупка будет оформлена на ваш аккаунт:
                <strong><?= htmlspecialchars($userEmail) ?></strong>
            </p>
        <?php else: ?>
            <div class="form-group">
                <label for="email">Ваш e-mail (для билета):</label>
                <input type="email" name="email" id="email" required>
            </div>
        <?php endif; ?>

        <button type="submit">Купить билеты</button>
    </div>
</form>

<script>
    function toggleSeat(el) {
        if (el.classList.contains('seat--taken')) return; // занято — не трогаем
        el.classList.toggle('seat--selected');

        const seatKey = el.dataset.seat;
        const checkbox = document.getElementById('seat-' + seatKey);

        if (checkbox) {
            checkbox.checked = !checkbox.checked;
        }
    }
</script>

<?php
// Подвал страницы
require_once __DIR__ . '/../include/footer_client.php';
