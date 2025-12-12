<?php
// client/ticket_pdf.php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/fpdf/tfpdf.php';
require_once __DIR__ . '/../vendor/phpqrcode/qrlib.php';

// только авторизованный пользователь
if (!is_logged_in()) {
    header('Location: ' . BASE_URL . '/client/login.php');
    exit;
}

// id билета (sale_id) из GET
$saleId = 0;
if (isset($_GET['sale_id'])) {
    $saleId = (int)$_GET['sale_id'];
} elseif (isset($_GET['id'])) {
    $saleId = (int)$_GET['id'];
}

if ($saleId <= 0) {
    die('Некорректный идентификатор билета.');
}

// грузим данные билета
$sql = "
    SELECT 
        sales.*,
        u.user_email,
        s.seance_start,
        f.film_name,
        h.hall_name,
        h.hall_rows,
        h.hall_places
    FROM sales
    JOIN users   u ON u.user_id   = sales.sale_user_id
    JOIN seances s ON s.seance_id = sales.sale_seance_id
    JOIN films   f ON f.film_id   = s.seance_film_id
    JOIN halls   h ON h.hall_id   = sales.sale_hall_id
    WHERE sales.sale_id = :sale_id
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':sale_id' => $saleId]);
$sale = $stmt->fetch();

if (!$sale) {
    die('Билет не найден.');
}

// не даём скачивать чужие билеты
if ($sale['sale_user_id'] !== current_user_id()) {
    die('Доступ запрещён.');
}

// список мест
$places = json_decode($sale['sale_configuration'], true);
if (!is_array($places)) {
    $places = [];
}

// ====== ГЕНЕРАЦИЯ QR-КОДА ДЛЯ ПРОВЕРКИ БИЛЕТА ======

// токен для защиты
$token = ticket_token($saleId);

// ссылка, которая будет зашита в QR
// ВАЖНО: ведёт на admin/check_ticket.php
$qrContent = BASE_URL . '/admin/check_ticket.php?ticket_id=' . $saleId . '&token=' . $token;

// временная папка и файл
$qrDir = __DIR__ . '/../tmp';
if (!is_dir($qrDir)) {
    mkdir($qrDir, 0777, true);
}
$qrFile = $qrDir . '/ticket_qr_' . $saleId . '.png';

// генерируем PNG с QR
QRcode::png($qrContent, $qrFile, QR_ECLEVEL_L, 4);

// ====== СОЗДАЁМ PDF ======

// tFPDF, а не FPDF
class TicketPDF extends tFPDF
{
    function Header() {}
    function Footer() {}
}

$pdf = new TicketPDF();
$pdf->AddPage();

// шрифт DejaVu для русского
// убедись, что DejaVuSans.ttf лежит в vendor/fpdf/font/unifont/DejaVuSans.ttf
$pdf->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
$pdf->SetFont('DejaVu', '', 14);

$pdf->Cell(0, 10, 'Билет в кинотеатр', 0, 1);

$pdf->SetFont('DejaVu', '', 12);

$pdf->Cell(0, 8, 'Номер билета: ' . $saleId, 0, 1);
$pdf->Cell(0, 8, 'Фильм: ' . $sale['film_name'], 0, 1);

$dt = new DateTime($sale['seance_start']);
$pdf->Cell(0, 8, 'Дата и время сеанса: ' . $dt->format('d.m.Y H:i'), 0, 1);

$pdf->Cell(0, 8, 'Зал: ' . $sale['hall_name'], 0, 1);

// выводим места
if ($places) {
    $positions = [];
    foreach ($places as $p) {
        if (is_array($p) && isset($p['row'], $p['place'])) {
            $positions[] = $p['row'] . '-' . $p['place'];
        } elseif (is_string($p)) {
            $positions[] = $p;
        }
    }
    $pdf->Cell(0, 8, 'Места: ' . implode(', ', $positions), 0, 1);
}

$pdf->Cell(0, 8, 'Стоимость: ' . number_format($sale['sale_amount'], 2, '.', ' ') . ' ₽', 0, 1);
$pdf->Cell(0, 8, 'E-mail: ' . $sale['user_email'], 0, 1);
$pdf->Ln(5);

$pdf->MultiCell(
    0,
    7,
    "Предъявите этот билет и QR-код контролёру при входе в зал.\n"
    . "При первом сканировании билет будет помечен как использованный.",
    0,
    1
);
$pdf->Ln(5);

$pdf->Cell(0, 8, 'QR-код билета:', 0, 1);
$pdf->Image($qrFile, $pdf->GetX(), $pdf->GetY(), 40, 40);

// отправляем PDF в браузер
$pdf->Output('I', 'ticket_' . $saleId . '.pdf');

// удаляем временный файл QR
if (file_exists($qrFile)) {
    unlink($qrFile);
}
