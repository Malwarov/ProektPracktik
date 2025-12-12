<?php
$pageTitle = 'Сеансы — админ';
require_once __DIR__ . '/../include/header_admin.php';

// получаем фильмы и залы для select
$films = $pdo->query("SELECT film_id, film_name FROM films ORDER BY film_name")->fetchAll();
$halls = $pdo->query("SELECT hall_id, hall_name FROM halls ORDER BY hall_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seance_id  = isset($_POST['seance_id']) ? (int)$_POST['seance_id'] : 0;
    $film_id    = (int)($_POST['seance_film_id'] ?? 0);
    $hall_id    = (int)($_POST['seance_hall_id'] ?? 0);
    $start      = $_POST['seance_start'] ?? '';
    $end        = $_POST['seance_end'] ?? '';
    $base_price = (float)($_POST['seance_base_price'] ?? 300);

    if ($seance_id) {
        $stmt = $pdo->prepare("
            UPDATE seances
            SET seance_hall_id = :hall_id,
                seance_film_id = :film_id,
                seance_start = :start,
                seance_end = :end,
                seance_base_price = :price
            WHERE seance_id = :id
        ");
        $stmt->execute([
            ':hall_id' => $hall_id,
            ':film_id' => $film_id,
            ':start'   => $start,
            ':end'     => $end,
            ':price'   => $base_price,
            ':id'      => $seance_id,
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO seances (seance_hall_id, seance_film_id, seance_start, seance_end, seance_base_price)
            VALUES (:hall_id, :film_id, :start, :end, :price)
        ");
        $stmt->execute([
            ':hall_id' => $hall_id,
            ':film_id' => $film_id,
            ':start'   => $start,
            ':end'     => $end,
            ':price'   => $base_price,
        ]);
    }

    header('Location: ' . BASE_URL . '/admin/seances.php');
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM seances WHERE seance_id = :id");
    $stmt->execute([':id' => $id]);
    header('Location: ' . BASE_URL . '/admin/seances.php');
    exit;
}

$seances = $pdo->query("
    SELECT s.*, f.film_name, h.hall_name
    FROM seances s
    JOIN films f ON f.film_id = s.seance_film_id
    JOIN halls h ON h.hall_id = s.seance_hall_id
    ORDER BY s.seance_start DESC
")->fetchAll();

$editSeance = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM seances WHERE seance_id = :id");
    $stmt->execute([':id' => $id]);
    $editSeance = $stmt->fetch();
}
?>
<h1>Сеансы</h1>

<h2 style="margin-top:20px;"><?= $editSeance ? 'Редактировать' : 'Добавить' ?> сеанс</h2>
<form method="post" style="max-width:500px;margin-top:10px;">
    <input type="hidden" name="seance_id" value="<?= $editSeance['seance_id'] ?? 0 ?>">

    <div class="form-group">
        <label for="seance_film_id">Фильм</label>
        <select id="seance_film_id" name="seance_film_id" required>
            <option value="">-- выберите фильм --</option>
            <?php foreach ($films as $f): ?>
                <option value="<?= (int)$f['film_id'] ?>"
                    <?= !empty($editSeance) && $editSeance['seance_film_id'] == $f['film_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($f['film_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="seance_hall_id">Зал</label>
        <select id="seance_hall_id" name="seance_hall_id" required>
            <option value="">-- выберите зал --</option>
            <?php foreach ($halls as $h): ?>
                <option value="<?= (int)$h['hall_id'] ?>"
                    <?= !empty($editSeance) && $editSeance['seance_hall_id'] == $h['hall_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($h['hall_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="seance_start">Начало</label>
        <input type="datetime-local" id="seance_start" name="seance_start"
               value="<?= !empty($editSeance) ? date('Y-m-d\TH:i', strtotime($editSeance['seance_start'])) : '' ?>"
               required>
    </div>

    <div class="form-group">
        <label for="seance_end">Окончание</label>
        <input type="datetime-local" id="seance_end" name="seance_end"
               value="<?= !empty($editSeance) ? date('Y-m-d\TH:i', strtotime($editSeance['seance_end'])) : '' ?>"
               required>
    </div>

    <div class="form-group">
        <label for="seance_base_price">Базовая цена, ₽</label>
        <input type="number" step="0.01" id="seance_base_price" name="seance_base_price"
               value="<?= htmlspecialchars($editSeance['seance_base_price'] ?? 300) ?>" required>
    </div>

    <button type="submit"><?= $editSeance ? 'Сохранить' : 'Добавить' ?></button>
</form>

<h2 style="margin-top:30px;">Список сеансов</h2>
<table class="table">
    <tr>
        <th>ID</th>
        <th>Фильм</th>
        <th>Зал</th>
        <th>Начало</th>
        <th>Цена</th>
        <th></th>
    </tr>
    <?php foreach ($seances as $s): ?>
        <tr>
            <td><?= (int)$s['seance_id'] ?></td>
            <td><?= htmlspecialchars($s['film_name']) ?></td>
            <td><?= htmlspecialchars($s['hall_name']) ?></td>
            <td><?= (new DateTime($s['seance_start']))->format('d.m.Y H:i') ?></td>
            <td><?= number_format($s['seance_base_price'], 2, '.', ' ') ?> ₽</td>
            <td>
                <a class="btn" href="<?= BASE_URL ?>/admin/seances.php?edit=<?= (int)$s['seance_id'] ?>">Редактировать</a>
                <a class="btn" href="<?= BASE_URL ?>/admin/seances.php?delete=<?= (int)$s['seance_id'] ?>"
                   onclick="return confirm('Удалить сеанс?')">Удалить</a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<?php require_once __DIR__ . '/../include/footer_admin.php'; ?>
