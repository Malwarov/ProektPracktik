<?php
$pageTitle = 'Админ-панель — сводка';
require_once __DIR__ . '/../include/header_admin.php';

$filmsCount   = $pdo->query("SELECT COUNT(*) AS c FROM films")->fetch()['c'] ?? 0;
$hallsCount   = $pdo->query("SELECT COUNT(*) AS c FROM halls")->fetch()['c'] ?? 0;
$seancesCount = $pdo->query("SELECT COUNT(*) AS c FROM seances")->fetch()['c'] ?? 0;
$salesCount   = $pdo->query("SELECT COUNT(*) AS c FROM sales")->fetch()['c'] ?? 0;
?>
<h1>Сводка</h1>

<table class="table">
    <tr><th>Фильмов</th><td><?= (int)$filmsCount ?></td></tr>
    <tr><th>Залов</th><td><?= (int)$hallsCount ?></td></tr>
    <tr><th>Сеансов</th><td><?= (int)$seancesCount ?></td></tr>
    <tr><th>Продаж</th><td><?= (int)$salesCount ?></td></tr>
</table>

<?php require_once __DIR__ . '/../include/footer_admin.php'; ?>
