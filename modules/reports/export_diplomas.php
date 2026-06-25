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

$sql = "SELECT d.*, m.pavadinimas AS mokykla FROM dalyviai d LEFT JOIN mokyklos m ON d.var_mokykla = m.pavadinimas WHERE $where ORDER BY d.konkurso_pav, FIELD(d.Vieta, 'I','II','III','laureat.'), CAST(d.Balai AS UNSIGNED) DESC";
$stmt = db_query($sql, $params, $types);
$prizininkai = db_get_results($stmt);
if (empty($prizininkai)) die('Nėra prizininkų');

$zip_name = ($konkurso_pav && $konkurso_pav !== 'Visos') ? "Diplomai_" . preg_replace('/[^a-zA-Z0-9]/', '_', $konkurso_pav) : "Visi_diplomai_" . date('Y-m-d');
$zip_filename = $zip_name . ".zip";
$zip_path = sys_get_temp_dir() . '/' . $zip_filename;

$zip = new ZipArchive();
if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) die("Nepavyko sukurti ZIP");

$font_file = $root . '/vendor/tcpdf/fonts/DejaVuSans.ttf';
if (!file_exists($font_file)) die('Trūksta šrifto: DejaVuSans.ttf');
$font_name = TCPDF_FONTS::addTTFfont($font_file, 'TrueTypeUnicode', '', 32);

// Užkrauname admino redaguotą šabloną
$template_file = $root . '/config/diploma_template.html';
if (file_exists($template_file)) {
    $html_template = file_get_contents($template_file);
} else {
    // Atsarginis variantas
    $html_template = '<div style="font-family:\'{FONT_NAME}\'; text-align:center;"><h2>{VARDAS_PAVARDE}</h2><p>{VIETA}</p></div>';
}

foreach ($prizininkai as $d) {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->SetCreator('Olimpiadų sistema');
    $pdf->SetTitle('Diplomas - ' . $d['1_vardas'] . ' ' . $d['1_pavarde']);
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();

    $year = date('Y', strtotime($d['pil_data']));
    $dip_nr = sprintf("DIP-%d-%03d", $year, $d['reg_id']);
    $vietos = ['I' => 'I vieta', 'II' => 'II vieta', 'III' => 'III vieta', 'laureat.' => 'Laureatas'];
    $vieta = $vietos[$d['Vieta']] ?? 'Dalyvis';
    $data = date('Y m. d d.', strtotime($d['pil_data']));

    // Pakeičiame kintamuosius šablone realiais duomenimis
    $html = str_replace(
        ['{FONT_NAME}', '{DIP_NR}', '{LOGO}', '{VIETA}', '{VARDAS_PAVARDE}', '{MOKYKLA}', '{OLIMPIADA}', '{DATA}'],
        [$font_name, $dip_nr, $logo_svg, $vieta, htmlspecialchars($d['1_vardas'] . ' ' . $d['1_pavarde']), htmlspecialchars($d['mokykla'] ?? $d['var_mokykla']), htmlspecialchars($d['konkurso_pav']), $data],
        $html_template
    );

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf_content = $pdf->Output('', 'S'); // 'S' grąžina kaip String į ZIP archyvą

    $filename = "Diplomas_{$d['1_vardas']}_{$d['1_pavarde']}.pdf";
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