<?php
// admin/reports.php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__ . '/../config/db.php';

// защита: только админ
if (!is_admin()) {
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}

/**
 * Экспорт данных в Excel (.xlsx) с помощью PhpSpreadsheet
 *
 * @param array  $headers      Заголовки столбцов
 * @param array  $rows         Массив строк: [ [col1, col2, ...], ... ]
 * @param string $filenameBase Имя файла без расширения
 */
function exportExcel(array $headers, array $rows, string $filenameBase): void
{
    require_once __DIR__ . '/../vendor/autoload.php';

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Отчёт');

    // Заголовки
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $sheet->getStyle($col . '1')->getFont()->setBold(true);
        $sheet->getColumnDimension($col)->setAutoSize(true);
        $col++;
    }

    // Данные
    $rowNum = 2;
    foreach ($rows as $row) {
        $col = 'A';
        foreach ($row as $cell) {
            $sheet->setCellValueExplicit(
                $col . $rowNum,
                (string)$cell,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
            $col++;
        }
        $rowNum++;
    }

    // Заголовки ответа
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// =====================
// 1. Фильтры периода + фильм/зал
// =====================

$today = new DateTime();
$defaultTo   = $today->format('Y-m-d');
$defaultFrom = $today->sub(new DateInterval('P30D'))->format('Y-m-d');

$dateFrom = isset($_GET['date_from']) && $_GET['date_from'] !== ''
    ? $_GET['date_from']
    : $defaultFrom;

$dateTo = isset($_GET['date_to']) && $_GET['date_to'] !== ''
    ? $_GET['date_to']
    : $defaultTo;

$fromDateTime = $dateFrom . ' 00:00:00';
$toDateTime   = $dateTo   . ' 23:59:59';

// фильтр по фильму и залу (0 или пусто = "все")
$filterFilmId = isset($_GET['film_id']) && $_GET['film_id'] !== ''
    ? (int)$_GET['film_id']
    : 0;

$filterHallId = isset($_GET['hall_id']) && $_GET['hall_id'] !== ''
    ? (int)$_GET['hall_id']
    : 0;

// списки фильмов и залов для селектов
$filmOptions = $pdo->query("SELECT film_id, film_name FROM films ORDER BY film_name")->fetchAll();
$hallOptions = $pdo->query("SELECT hall_id, hall_name FROM halls ORDER BY hall_name")->fetchAll();

// добавочные условия для WHERE
$whereExtra = '';
$paramsExtra = [];

if ($filterFilmId) {
    $whereExtra .= ' AND s.seance_film_id = :film_id';
    $paramsExtra[':film_id'] = $filterFilmId;
}

if ($filterHallId) {
    $whereExtra .= ' AND s.seance_hall_id = :hall_id';
    $paramsExtra[':hall_id'] = $filterHallId;
}

// базовый набор параметров по периоду
$baseParams = array_merge([
    ':from' => $fromDateTime,
    ':to'   => $toDateTime,
], $paramsExtra);

// =====================
// 2. Продажи: сводка + по дням (по дате продажи)
// =====================

// сводка
$sqlSalesSummary = "
    SELECT 
        COUNT(DISTINCT sales.sale_id) AS sales_count,
        COALESCE(SUM(sales.sale_amount), 0) AS total_revenue
    FROM sales
    JOIN seances s ON s.seance_id = sales.sale_seance_id
    WHERE sales.sale_timestamp BETWEEN :from AND :to
    $whereExtra
";
$stmt = $pdo->prepare($sqlSalesSummary);
$stmt->execute($baseParams);
$salesSummary = $stmt->fetch();

// по дням
$sqlSalesByDay = "
    SELECT 
        DATE(sales.sale_timestamp) AS day,
        COUNT(DISTINCT sales.sale_id) AS sales_count,
        COALESCE(SUM(sales.sale_amount), 0) AS revenue
    FROM sales
    JOIN seances s ON s.seance_id = sales.sale_seance_id
    WHERE sales.sale_timestamp BETWEEN :from AND :to
    $whereExtra
    GROUP BY DATE(sales.sale_timestamp)
    ORDER BY day
";
$stmt = $pdo->prepare($sqlSalesByDay);
$stmt->execute($baseParams);
$salesByDay = $stmt->fetchAll();

// =====================
// 3. Заполняемость залов по сеансам
// =====================

$sqlSeanceSales = "
    SELECT 
        sales.sale_id,
        sales.sale_timestamp,
        sales.sale_configuration,
        sales.sale_amount,
        s.seance_id,
        s.seance_start,
        f.film_name,
        h.hall_name,
        h.hall_rows,
        h.hall_places
    FROM sales
    JOIN seances s ON s.seance_id = sales.sale_seance_id
    JOIN films   f ON f.film_id   = s.seance_film_id
    JOIN halls   h ON h.hall_id   = sales.sale_hall_id
    WHERE sales.sale_timestamp BETWEEN :from AND :to
    $whereExtra
    ORDER BY s.seance_start
";
$stmt = $pdo->prepare($sqlSeanceSales);
$stmt->execute($baseParams);
$rows = $stmt->fetchAll();

$seanceStats = [];
foreach ($rows as $r) {
    $sid = (int)$r['seance_id'];
    if (!isset($seanceStats[$sid])) {
        $capacity = (int)$r['hall_rows'] * (int)$r['hall_places'];
        $seanceStats[$sid] = [
            'seance_id'    => $sid,
            'seance_start' => $r['seance_start'],
            'film_name'    => $r['film_name'],
            'hall_name'    => $r['hall_name'],
            'capacity'     => $capacity,
            'seats_sold'   => 0,
            'revenue'      => 0,
        ];
    }
    $conf = json_decode($r['sale_configuration'], true);
    $countSeats = is_array($conf) ? count($conf) : 0;

    $seanceStats[$sid]['seats_sold'] += $countSeats;
    $seanceStats[$sid]['revenue']    += (float)$r['sale_amount'];
}

$seanceStats = array_values($seanceStats);
usort($seanceStats, function ($a, $b) {
    return strcmp($a['seance_start'], $b['seance_start']);
});

// =====================
// 4. Самые популярные фильмы
// =====================

$sqlPopularFilms = "
    SELECT 
        f.film_id,
        f.film_name,
        COUNT(DISTINCT sales.sale_id) AS sales_count,
        COALESCE(SUM(sales.sale_amount), 0) AS total_revenue
    FROM sales
    JOIN seances s ON s.seance_id = sales.sale_seance_id
    JOIN films   f ON f.film_id   = s.seance_film_id
    WHERE sales.sale_timestamp BETWEEN :from AND :to
    $whereExtra
    GROUP BY f.film_id, f.film_name
    ORDER BY sales_count DESC, total_revenue DESC
    LIMIT 10
";
$stmt = $pdo->prepare($sqlPopularFilms);
$stmt->execute($baseParams);
$popularFilms = $stmt->fetchAll();

// =====================
// 5. ЭКСПОРТ В EXCEL (.xlsx)
// =====================

if (isset($_GET['export']) && $_GET['export'] === 'sales_excel') {
    $headers = ['Дата продажи', 'Кол-во продаж', 'Выручка, ₽'];
    $rowsOut = [];
    foreach ($salesByDay as $d) {
        $rowsOut[] = [
            $d['day'],
            (int)$d['sales_count'],
            number_format($d['revenue'], 2, ',', ' '),
        ];
    }
    exportExcel($headers, $rowsOut, 'sales_by_day_' . $dateFrom . '_' . $dateTo);
}

if (isset($_GET['export']) && $_GET['export'] === 'seances_excel') {
    $headers = ['Дата и время сеанса', 'Фильм', 'Зал', 'Продано мест', 'Вместимость', 'Заполняемость %', 'Выручка, ₽'];
    $rowsOut = [];
    foreach ($seanceStats as $s) {
        $occ = $s['capacity'] > 0 ? ($s['seats_sold'] / $s['capacity']) * 100 : 0;
        $rowsOut[] = [
            (new DateTime($s['seance_start']))->format('d.m.Y H:i'),
            $s['film_name'],
            $s['hall_name'],
            (int)$s['seats_sold'],
            (int)$s['capacity'],
            number_format($occ, 1, ',', ' '),
            number_format($s['revenue'], 2, ',', ' '),
        ];
    }
    exportExcel($headers, $rowsOut, 'seance_occupancy_' . $dateFrom . '_' . $dateTo);
}

// =====================
// 6. Данные для графиков
// =====================

$chartLabels = [];
$chartRevenues = [];
foreach ($salesByDay as $d) {
    $chartLabels[]   = $d['day'];
    $chartRevenues[] = (float)$d['revenue'];
}

$occLabels = [];
$occValues = [];
foreach (array_slice($seanceStats, 0, 10) as $s) {
    $label = (new DateTime($s['seance_start']))->format('d.m') . ' ' . $s['hall_name'];
    $occ   = $s['capacity'] > 0 ? ($s['seats_sold'] / $s['capacity']) * 100 : 0;
    $occLabels[] = $label;
    $occValues[] = round($occ, 1);
}

// =====================
// 7. HTML (шапка + контент)
// =====================

$pageTitle = 'Отчёты — аналитика';
require_once __DIR__ . '/../include/header_admin.php';
?>

<h1>Отчёты и аналитика</h1>

<!-- Фильтры -->
<div class="reports-filters-card">
    <form method="get" class="reports-filters">
        <div class="form-group">
            <label for="date_from">С даты</label>
            <input type="date" id="date_from" name="date_from"
                   value="<?= htmlspecialchars($dateFrom) ?>">
        </div>
        <div class="form-group">
            <label for="date_to">По дату</label>
            <input type="date" id="date_to" name="date_to"
                   value="<?= htmlspecialchars($dateTo) ?>">
        </div>
        <div class="form-group">
            <label for="film_id">Фильм</label>
            <select id="film_id" name="film_id">
                <option value="">Все фильмы</option>
                <?php foreach ($filmOptions as $f): ?>
                    <option value="<?= (int)$f['film_id'] ?>"
                        <?= $filterFilmId === (int)$f['film_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($f['film_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="hall_id">Зал</label>
            <select id="hall_id" name="hall_id">
                <option value="">Все залы</option>
                <?php foreach ($hallOptions as $h): ?>
                    <option value="<?= (int)$h['hall_id'] ?>"
                        <?= $filterHallId === (int)$h['hall_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($h['hall_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit">Показать</button>
    </form>
</div>

<div class="reports-grid">

    <!-- 1. Продажи за период -->
    <section class="reports-card">
        <div class="reports-card__header">
            <h2>Продажи за период</h2>
            <?php if ($salesByDay): ?>
                <a class="btn"
                   href="<?= BASE_URL ?>/admin/reports.php?<?= http_build_query(array_merge($_GET, ['export' => 'sales_excel'])) ?>">
                    Экспорт Excel (дни)
                </a>
            <?php endif; ?>
        </div>

        <div class="reports-summary-row">
            <div class="reports-summary-item">
                <div class="reports-summary-item__label">Период (по дате продажи)</div>
                <div class="reports-summary-item__value">
                    <?= htmlspecialchars($dateFrom) ?> — <?= htmlspecialchars($dateTo) ?>
                </div>
            </div>
            <div class="reports-summary-item">
                <div class="reports-summary-item__label">Кол-во продаж</div>
                <div class="reports-summary-item__value">
                    <?= (int)$salesSummary['sales_count'] ?>
                </div>
            </div>
            <div class="reports-summary-item">
                <div class="reports-summary-item__label">Выручка</div>
                <div class="reports-summary-item__value">
                    <?= number_format($salesSummary['total_revenue'], 2, '.', ' ') ?> ₽
                </div>
            </div>
            <div class="reports-summary-item">
                <div class="reports-summary-item__label">Средний чек</div>
                <div class="reports-summary-item__value">
                    <?php
                    $avg = $salesSummary['sales_count'] > 0
                        ? $salesSummary['total_revenue'] / $salesSummary['sales_count']
                        : 0;
                    ?>
                    <?= number_format($avg, 2, '.', ' ') ?> ₽
                </div>
            </div>
        </div>

        <?php if ($salesByDay): ?>
            <div style="margin-top:20px;">
                <h3>График выручки по дням (дата продажи)</h3>
                <canvas id="salesChart" width="700" height="260"></canvas>
            </div>

            <h3 style="margin-top:20px;">Таблица продаж по дням</h3>
            <table class="table">
                <tr>
                    <th>Дата продажи</th>
                    <th>Кол-во продаж</th>
                    <th>Выручка, ₽</th>
                </tr>
                <?php foreach ($salesByDay as $d): ?>
                    <tr>
                        <td><?= htmlspecialchars($d['day']) ?></td>
                        <td><?= (int)$d['sales_count'] ?></td>
                        <td><?= number_format($d['revenue'], 2, '.', ' ') ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p class="reports-note">За выбранный период продаж нет.</p>
        <?php endif; ?>
    </section>

    <!-- 2. Заполняемость залов по сеансам -->
    <section class="reports-card">
        <div class="reports-card__header">
            <h2>Заполняемость залов</h2>
            <?php if ($seanceStats): ?>
                <a class="btn"
                   href="<?= BASE_URL ?>/admin/reports.php?<?= http_build_query(array_merge($_GET, ['export' => 'seances_excel'])) ?>">
                    Экспорт Excel (сеансы)
                </a>
            <?php endif; ?>
        </div>

        <p class="reports-note">
            Сеансы учитываются, для которых были продажи в выбранный период (по дате продажи).
            Заполняемость считается по проданным местам относительно полной вместимости зала.
        </p>

        <?php if ($seanceStats): ?>
            <?php if ($occLabels): ?>
                <div style="margin-top:15px;">
                    <h3>Топ-10 сеансов по заполняемости</h3>
                    <canvas id="occChart" width="700" height="260"></canvas>
                </div>
            <?php endif; ?>

            <h3 style="margin-top:20px;">Таблица сеансов</h3>
            <table class="table">
                <tr>
                    <th>Дата и время</th>
                    <th>Фильм</th>
                    <th>Зал</th>
                    <th>Продано мест</th>
                    <th>Вместимость</th>
                    <th>Заполняемость</th>
                    <th>Выручка, ₽</th>
                </tr>
                <?php foreach ($seanceStats as $s): ?>
                    <?php
                    $occ = $s['capacity'] > 0
                        ? ($s['seats_sold'] / $s['capacity']) * 100
                        : 0;
                    ?>
                    <tr>
                        <td><?= (new DateTime($s['seance_start']))->format('d.m.Y H:i') ?></td>
                        <td><?= htmlspecialchars($s['film_name']) ?></td>
                        <td><?= htmlspecialchars($s['hall_name']) ?></td>
                        <td><?= (int)$s['seats_sold'] ?></td>
                        <td><?= (int)$s['capacity'] ?></td>
                        <td><?= number_format($occ, 1, '.', ' ') ?> %</td>
                        <td><?= number_format($s['revenue'], 2, '.', ' ') ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p class="reports-note">В выбранный период не было сеансов с продажами.</p>
        <?php endif; ?>
    </section>

    <!-- 3. Самые популярные фильмы -->
    <section class="reports-card reports-card--full">
        <h2>Самые популярные фильмы</h2>
        <p class="reports-note">
            Рейтинг строится по количеству продаж за выбранный период (по датам продаж и с учётом фильтров).
        </p>

        <?php if ($popularFilms): ?>
            <table class="table">
                <tr>
                    <th>#</th>
                    <th>Фильм</th>
                    <th>Кол-во продаж</th>
                    <th>Выручка, ₽</th>
                </tr>
                <?php $i = 1; ?>
                <?php foreach ($popularFilms as $f): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($f['film_name']) ?></td>
                        <td><?= (int)$f['sales_count'] ?></td>
                        <td><?= number_format($f['total_revenue'], 2, '.', ' ') ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p class="reports-note">В выбранный период данных по фильмам нет.</p>
        <?php endif; ?>
    </section>

</div>

<?php require_once __DIR__ . '/../include/footer_admin.php'; ?>

<!-- Chart.js только на этой странице -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const salesLabels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
    const salesData   = <?= json_encode($chartRevenues, JSON_UNESCAPED_UNICODE) ?>;

    if (salesLabels.length && document.getElementById('salesChart')) {
        const ctx = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: salesLabels,
                datasets: [{
                    label: 'Выручка, ₽',
                    data: salesData,
                    fill: false,
                    borderWidth: 2,
                    tension: 0.2
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: true } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    const occLabels = <?= json_encode($occLabels, JSON_UNESCAPED_UNICODE) ?>;
    const occData   = <?= json_encode($occValues, JSON_UNESCAPED_UNICODE) ?>;

    if (occLabels.length && document.getElementById('occChart')) {
        const ctx2 = document.getElementById('occChart').getContext('2d');
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: occLabels,
                datasets: [{
                    label: 'Заполняемость, %',
                    data: occData,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: true } },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    }
</script>
