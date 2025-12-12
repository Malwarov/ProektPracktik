<?php
$pageTitle = 'Залы — админ';
require_once __DIR__ . '/../include/header_admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hall_id = isset($_POST['hall_id']) ? (int)$_POST['hall_id'] : 0;

    $data = [
        ':name'    => trim($_POST['hall_name'] ?? ''),
        ':rows'    => (int)($_POST['hall_rows'] ?? 0),
        ':places'  => (int)($_POST['hall_places'] ?? 0),
        ':price_s' => (float)($_POST['hall_price_standard'] ?? 300),
        ':price_v' => (float)($_POST['hall_price_vip'] ?? 500),
        ':open'    => isset($_POST['hall_open']) ? 1 : 0,
    ];

    if ($hall_id) {
        $data[':id'] = $hall_id;
        $stmt = $pdo->prepare("
            UPDATE halls
            SET hall_name = :name,
                hall_rows = :rows,
                hall_places = :places,
                hall_price_standard = :price_s,
                hall_price_vip = :price_v,
                hall_open = :open
            WHERE hall_id = :id
        ");
        $stmt->execute($data);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO halls (hall_name, hall_rows, hall_places, hall_price_standard, hall_price_vip, hall_open)
            VALUES (:name, :rows, :places, :price_s, :price_v, :open)
        ");
        $stmt->execute($data);
    }

    header('Location: ' . BASE_URL . '/admin/halls.php');
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM halls WHERE hall_id = :id");
    $stmt->execute([':id' => $id]);
    header('Location: ' . BASE_URL . '/admin/halls.php');
    exit;
}

$halls = $pdo->query("SELECT * FROM halls ORDER BY hall_name")->fetchAll();
$editHall = null;

if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM halls WHERE hall_id = :id");
    $stmt->execute([':id' => $id]);
    $editHall = $stmt->fetch();
}
?>
<h1>Залы</h1>

<h2 style="margin-top:20px;"><?= $editHall ? 'Редактировать' : 'Добавить' ?> зал</h2>
<form method="post" style="max-width:500px;margin-top:10px;">
    <input type="hidden" name="hall_id" value="<?= $editHall['hall_id'] ?? 0 ?>">

    <div class="form-group">
        <label for="hall_name">Название</label>
        <input type="text" id="hall_name" name="hall_name"
               value="<?= htmlspecialchars($editHall['hall_name'] ?? '') ?>" required>
    </div>

    <div class="form-group">
        <label for="hall_rows">Количество рядов</label>
        <input type="number" id="hall_rows" name="hall_rows"
               value="<?= htmlspecialchars($editHall['hall_rows'] ?? 5) ?>" required>
    </div>

    <div class="form-group">
        <label for="hall_places">Мест в ряду</label>
        <input type="number" id="hall_places" name="hall_places"
               value="<?= htmlspecialchars($editHall['hall_places'] ?? 10) ?>" required>
    </div>

    <div class="form-group">
        <label for="hall_price_standard">Цена стандарт, ₽</label>
        <input type="number" step="0.01" id="hall_price_standard" name="hall_price_standard"
               value="<?= htmlspecialchars($editHall['hall_price_standard'] ?? 300) ?>" required>
    </div>

    <div class="form-group">
        <label for="hall_price_vip">Цена VIP, ₽</label>
        <input type="number" step="0.01" id="hall_price_vip" name="hall_price_vip"
               value="<?= htmlspecialchars($editHall['hall_price_vip'] ?? 500) ?>" required>
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="hall_open"
                <?= !empty($editHall['hall_open']) ? 'checked' : '' ?>> Открыт
        </label>
    </div>

    <button type="submit"><?= $editHall ? 'Сохранить' : 'Добавить' ?></button>
</form>

<h2 style="margin-top:30px;">Список залов</h2>
<table class="table">
    <tr>
        <th>ID</th>
        <th>Название</th>
        <th>Рядов</th>
        <th>Мест в ряду</th>
        <th>Открыт</th>
        <th></th>
    </tr>
    <?php foreach ($halls as $h): ?>
        <tr>
            <td><?= (int)$h['hall_id'] ?></td>
            <td><?= htmlspecialchars($h['hall_name']) ?></td>
            <td><?= (int)$h['hall_rows'] ?></td>
            <td><?= (int)$h['hall_places'] ?></td>
            <td><?= $h['hall_open'] ? 'Да' : 'Нет' ?></td>
            <td>
                <a class="btn" href="<?= BASE_URL ?>/admin/halls.php?edit=<?= (int)$h['hall_id'] ?>">Редактировать</a>
                <a class="btn" href="<?= BASE_URL ?>/admin/halls.php?delete=<?= (int)$h['hall_id'] ?>"
                   onclick="return confirm('Удалить зал?')">Удалить</a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<?php require_once __DIR__ . '/../include/footer_admin.php'; ?>
