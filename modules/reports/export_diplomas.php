<?php
$root = dirname(dirname(dirname(__FILE__)));
require_once $root . '/config/config.php';
require_once $root . '/config/db_connect.php';
require_once $root . '/config/functions.php';
require_once $root . '/vendor/tcpdf/tcpdf.php';
require_once $root . '/vendor/tcpdf/include/tcpdf_fonts.php';
require_once __DIR__ . '/diploma_style.php';
require_once __DIR__ . '/diploma_render.php';

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

// IŠTAISYTA: šis failas skaitydavo SENĄ config/diploma_template.html, o
// administratoriaus redaktorius saugo į config/diploma_layout.json - todėl
// masinis eksportas generuodavo diplomus pagal seną, nebeatnaujinamą šabloną
// (be linksnių ir be naujų kintamųjų). Dabar naudojamas tas pats bendras
// užkrovėjas kaip ir diplomas.php.
$dip_template = diploma_load_template($root);

foreach ($prizininkai as $d) {
    // PERTVARKYTA: naudojama bendra diploma_build_pdf() funkcija - ta pati,
    // kurią naudoja diplomas.php (žr. modules/reports/diploma_render.php),
    // todėl masinis eksportas visada atitinka pavienio diplomo rezultatą.
    $pdf = diploma_build_pdf($d, $dip_template, $font_name, $logo_svg ?? '');
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