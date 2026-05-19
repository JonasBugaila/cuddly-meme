<?php
$root = dirname(dirname(dirname(__FILE__)));
require_once $root . '/config/config.php';
require_once $root . '/config/db_connect.php';
require_once $root . '/config/functions.php';
require_once $root . '/vendor/tcpdf/tcpdf.php';
require_once $root . '/vendor/tcpdf/include/tcpdf_fonts.php';
require_once __DIR__ . '/diploma_style.php';

if (!is_logged_in() || !is_admin()) die('Prieiga draudžiama');

$konkurso_pav = $_GET['konkursas'] ?? '';
$where = "d.Vieta IN ('I','II','III','laureat.')";
$params = []; $types = '';
if ($konkurso_pav && $konkurso_pav !== 'Visos') {
    $where .= " AND d.konkurso_pav = ?";
    $params[] = $konkurso_pav;
    $types .= 's';
}

$sql = "SELECT d.*, m.pavadinimas AS mokykla FROM dalyviai d LEFT JOIN mokyklos m ON d.var_mokykla = m.pavadinimas WHERE $where ORDER BY d.konkurso_pav, FIELD(d.Vieta, 'I','II','III','laureat.'), d.Balai DESC";
$stmt = db_query($sql, $params, $types);
$prizininkai = db_get_results($stmt);
if (empty($prizininkai)) die('Nėra prizininkų');

$zip_name = ($konkurso_pav && $konkurso_pav !== 'Visos') ? "Diplomas_" . preg_replace('/[^a-zA-Z0-9]/', '_', $konkurso_pav) : "Visi_diplomai_" . date('Y-m-d');
$zip_filename = $zip_name . ".zip";
$zip_path = sys_get_temp_dir() . '/' . $zip_filename;

$zip = new ZipArchive();
if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) die("Nepavyko sukurti ZIP");

$font_file = $root . '/vendor/tcpdf/fonts/DejaVuSans.ttf';
if (!file_exists($font_file)) die('Trūksta šrifto: DejaVuSans.ttf');
$font_name = TCPDF_FONTS::addTTFfont($font_file, 'TrueTypeUnicode', '', 32);

foreach ($prizininkai as $d) {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->SetCreator('Olimpiadų sistema');
    $pdf->SetTitle('Diplomas');
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();

    $year = date('Y', strtotime($d['pil_data']));
    $dip_nr = sprintf("DIP-%d-%03d", $year, $d['reg_id']);
    $vietos = ['I' => 'I vieta', 'II' => 'II vieta', 'III' => 'III vieta', 'laureat.' => 'Laureatas'];
    $vieta = $vietos[$d['Vieta']] ?? 'Dalyvis';
    $data = date('Y m. d d.', strtotime($d['pil_data']));

    $html = '
    <div style="position:relative;padding:60px 80px;text-align:center;font-family:\'' . $font_name . '\';">
        <div style="position:absolute;top:30px;right:40px;font-size:14px;color:#999;font-weight:bold;">' . $dip_nr . '</div>
        ' . $logo_svg . '
        <h1 style="font-size:38px;margin:25px 0;font-weight:300;letter-spacing:1px;">Diplomas</h1>
        <p style="font-size:21px;font-style:italic;color:#555;margin-bottom:45px;">Už pasiekimus respublikinėje olimpiadoje</p>
        <div style="font-size:52px;font-weight:bold;color:#d4af37;margin:35px 0;">' . $vieta . '</div>
        <div style="font-size:44px;font-weight:bold;color:#2c3e50;margin:25px 0;">' . htmlspecialchars($d['1_vardas'] . ' ' . $d['1_pavarde']) . '</div>
        <div style="font-size:26px;color:#444;margin:18px 0;line-height:1.3;">' . htmlspecialchars($d['mokykla'] ?? $d['var_mokykla']) . '</div>
        <div style="font-size:24px;color:#666;margin:35px 0;font-style:italic;">„' . htmlspecialchars($d['konkurso_pav']) . '“</div>
        <div style="font-size:19px;color:#888;margin-top:60px;">' . $data . '</div>
    </div>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf_content = $pdf->Output('', 'S');

    $filename = "Diplomas_{$d['1_vardas']}_{$d['1_pavarde']}_{$d['konkurso_pav']}.pdf";
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
    $zip->addFromString($filename, $pdf_content);
}

$zip->close();
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
header('Content-Length: ' . filesize($zip_path));
readfile($zip_path);
unlink($zip_path);
exit;