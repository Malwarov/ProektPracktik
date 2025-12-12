<?php
// client/index.php
$pageTitle = 'Афиша — Кинотеатр';
require_once __DIR__ . '/../include/header_client.php';

// фильмы + ближайшие сеансы
$sql = "
    SELECT f.*, s.seance_id, s.seance_start
    FROM films f
    LEFT JOIN seances s ON s.seance_film_id = f.film_id
    WHERE s.seance_start IS NULL OR s.seance_start >= NOW()
    ORDER BY f.film_name, s.seance_start
";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll();

$films = [];
foreach ($rows as $row) {
    $id = $row['film_id'];
    if (!isset($films[$id])) {
        $films[$id] = [
            'info'    => [
                'film_id'          => $row['film_id'],
                'film_name'        => $row['film_name'],
                'film_duration'    => $row['film_duration'],
                'film_description' => $row['film_description'],
                'film_origin'      => $row['film_origin'],
                'film_age_limit'   => $row['film_age_limit'],
            ],
            'seances' => []
        ];
    }
    if (!empty($row['seance_id'])) {
        $films[$id]['seances'][] = [
            'seance_id'    => $row['seance_id'],
            'seance_start' => $row['seance_start'],
        ];
    }
}
?>
<h1>Афиша</h1>

<section class="films">
    <?php foreach ($films as $film): ?>
        <?php $info = $film['info']; ?>
        <article class="film-card">
            <h2 class="film-card__title">
                <?= htmlspecialchars($info['film_name']) ?>
            </h2>
            <div class="film-card__meta">
                <?= (int)$info['film_duration'] ?> мин •
                <?= htmlspecialchars($info['film_origin']) ?>
                <?php if ($info['film_age_limit']): ?>
                    • <?= (int)$info['film_age_limit'] ?>+
                <?php endif; ?>
            </div>
            <?php if (!empty($info['film_description'])): ?>
                <p class="film-card__desc">
                    <?= nl2br(htmlspecialchars(mb_strimwidth($info['film_description'], 0, 160, '...'))) ?>
                </p>
            <?php endif; ?>

            <div class="film-card__seances">
                <?php if (empty($film['seances'])): ?>
                    <span style="font-size:13px;opacity:0.7;">Сеансов пока нет</span>
                <?php else: ?>
                    <?php foreach ($film['seances'] as $seance): ?>
                        <?php
                            $dt = new DateTime($seance['seance_start']);
                            $label = $dt->format('d.m H:i');
                        ?>
                        <a class="seance-badge"
                           href="<?= BASE_URL ?>/client/hall.php?seance_id=<?= (int)$seance['seance_id'] ?>">
                            <?= htmlspecialchars($label) ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<?php require_once __DIR__ . '/../include/footer_client.php'; ?>
