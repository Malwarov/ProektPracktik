<?php
$pageTitle = 'Фильмы — админ';
require_once __DIR__ . '/../include/header_admin.php';

// добавление / редактирование
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $film_id = isset($_POST['film_id']) ? (int)$_POST['film_id'] : 0;

    $data = [
        ':name'        => trim($_POST['film_name'] ?? ''),
        ':duration'    => (int)($_POST['film_duration'] ?? 0),
        ':origin'      => trim($_POST['film_origin'] ?? ''),
        ':age_limit'   => (int)($_POST['film_age_limit'] ?? 0),
        ':descr'       => trim($_POST['film_description'] ?? ''),
    ];

    if ($film_id) {
        $data[':id'] = $film_id;
        $stmt = $pdo->prepare("
            UPDATE films
            SET film_name = :name,
                film_duration = :duration,
                film_origin = :origin,
                film_age_limit = :age_limit,
                film_description = :descr
            WHERE film_id = :id
        ");
        $stmt->execute($data);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO films (film_name, film_duration, film_origin, film_age_limit, film_description)
            VALUES (:name, :duration, :origin, :age_limit, :descr)
        ");
        $stmt->execute($data);
    }

    header('Location: ' . BASE_URL . '/admin/films.php');
    exit;
}

// удаление
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM films WHERE film_id = :id");
    $stmt->execute([':id' => $id]);
    header('Location: ' . BASE_URL . '/admin/films.php');
    exit;
}

$films = $pdo->query("SELECT * FROM films ORDER BY film_name")->fetchAll();
$editFilm = null;

if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM films WHERE film_id = :id");
    $stmt->execute([':id' => $id]);
    $editFilm = $stmt->fetch();
}
?>
<h1>Фильмы</h1>

<h2 style="margin-top:20px;"><?= $editFilm ? 'Редактировать' : 'Добавить' ?> фильм</h2>
<form method="post" style="max-width:500px;margin-top:10px;">
    <input type="hidden" name="film_id" value="<?= $editFilm['film_id'] ?? 0 ?>">

    <div class="form-group">
        <label for="film_name">Название</label>
        <input type="text" id="film_name" name="film_name"
               value="<?= htmlspecialchars($editFilm['film_name'] ?? '') ?>" required>
    </div>

    <div class="form-group">
        <label for="film_duration">Длительность (мин)</label>
        <input type="number" id="film_duration" name="film_duration"
               value="<?= htmlspecialchars($editFilm['film_duration'] ?? 90) ?>" required>
    </div>

    <div class="form-group">
        <label for="film_origin">Страна</label>
        <input type="text" id="film_origin" name="film_origin"
               value="<?= htmlspecialchars($editFilm['film_origin'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="film_age_limit">Возрастное ограничение</label>
        <input type="number" id="film_age_limit" name="film_age_limit"
               value="<?= htmlspecialchars($editFilm['film_age_limit'] ?? 0) ?>">
    </div>

    <div class="form-group">
        <label for="film_description">Описание</label>
        <textarea id="film_description" name="film_description" rows="4">
<?= htmlspecialchars($editFilm['film_description'] ?? '') ?>
        </textarea>
    </div>

    <button type="submit"><?= $editFilm ? 'Сохранить' : 'Добавить' ?></button>
</form>

<h2 style="margin-top:30px;">Список фильмов</h2>
<table class="table">
    <tr>
        <th>ID</th>
        <th>Название</th>
        <th>Длительность</th>
        <th>Страна</th>
        <th>Возраст</th>
        <th></th>
    </tr>
    <?php foreach ($films as $f): ?>
        <tr>
            <td><?= (int)$f['film_id'] ?></td>
            <td><?= htmlspecialchars($f['film_name']) ?></td>
            <td><?= (int)$f['film_duration'] ?> мин</td>
            <td><?= htmlspecialchars($f['film_origin']) ?></td>
            <td><?= (int)$f['film_age_limit'] ?>+</td>
            <td>
                <a class="btn" href="<?= BASE_URL ?>/admin/films.php?edit=<?= (int)$f['film_id'] ?>">Редактировать</a>
                <a class="btn" href="<?= BASE_URL ?>/admin/films.php?delete=<?= (int)$f['film_id'] ?>"
                   onclick="return confirm('Удалить фильм?')">Удалить</a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<?php require_once __DIR__ . '/../include/footer_admin.php'; ?>
