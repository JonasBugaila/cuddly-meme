<?php
// Išvalome buferį
while (ob_get_level()) { ob_end_clean(); }
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

$root = dirname(dirname(dirname(__FILE__)));
require_once $root . '/config/config.php';
require_once $root . '/config/db_connect.php';
require_once $root . '/config/functions.php';
require_once $root . '/vendor/tcpdf/tcpdf.php';
require_once $root . '/vendor/tcpdf/include/tcpdf_fonts.php';
require_once __DIR__ . '/diploma_style.php';

if (!is_logged_in()) die('Turite prisijungti');
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) die('Neteisingas ID');

$dalyvis_id = (int)$_GET['id'];
$sql = "SELECT d.*, m.pavadinimas AS mokykla FROM dalyviai d LEFT JOIN mokyklos m ON d.var_mokykla = m.pavadinimas WHERE d.reg_id = ?";
$stmt = db_query($sql, [$dalyvis_id], 'i');
$dalyvis = db_get_row($stmt);
if (!$dalyvis) die('Dalyvis nerastas');

// Užkrauname šriftą
$font_file = $root . '/vendor/tcpdf/fonts/DejaVuSans.ttf';
if (!file_exists($font_file)) die('Trūksta šrifto: DejaVuSans.ttf');
$font_name = TCPDF_FONTS::addTTFfont($font_file, 'TrueTypeUnicode', '', 32);

// Nuskaitome admino sukurtą šabloną su paraštėmis
$config_file = $root . '/config/diploma_layout.json';
$old_html_file = $root . '/config/diploma_template.html';

$margins = ['t' => 0, 'b' => 0, 'l' => 0, 'r' => 0]; // Numatytosios paraštės

if (file_exists($config_file)) {
    $layout = json_decode(file_get_contents($config_file), true);
    $html_template = $layout['content_html'] ?? '';
    $margins = [
        't' => (int)($layout['margin_t'] ?? 0),
        'b' => (int)($layout['margin_b'] ?? 0),
        'l' => (int)($layout['margin_l'] ?? 0),
        'r' => (int)($layout['margin_r'] ?? 0)
    ];
} elseif (file_exists($old_html_file)) {
    $html_template = file_get_contents($old_html_file);
} else {
    $html_template = '<h1 style="text-align:center; font-family:\'{FONT_NAME}\'; mt-5">Diplomas</h1>';
}

$year = date('Y', strtotime($dalyvis['pil_data'] ?? date('Y-m-d')));
$dip_nr = sprintf("DIP-%d-%03d", $year, $dalyvis_id);
$vietos = ['I' => 'I vieta', 'II' => 'II vieta', 'III' => 'III vieta', 'laureat.' => 'Laureatas'];
$vieta = $vietos[$dalyvis['Vieta']] ?? 'Dalyvis';
$data = date('Y m. d d.', strtotime($dalyvis['pil_data'] ?? date('Y-m-d')));
$logo = isset($logo_svg) ? $logo_svg : '';

// Keičiame kintamuosius
$html = str_replace(
    ['{FONT_NAME}', '{DIP_NR}', '{LOGO}', '{VIETA}', '{VARDAS_PAVARDE}', '{MOKYKLA}', '{OLIMPIADA}', '{DATA}'],
    [$font_name, $dip_nr, $logo, $vieta, htmlspecialchars($dalyvis['1_vardas'] . ' ' . $dalyvis['1_pavarde']), htmlspecialchars($dalyvis['mokykla'] ?? $dalyvis['var_mokykla']), htmlspecialchars($dalyvis['konkurso_pav']), $data],
    $html_template
);

// Generuojame PDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->SetCreator('Olimpiadų sistema');
$pdf->SetTitle('Diplomas - ' . htmlspecialchars($dalyvis['1_vardas'] . ' ' . $dalyvis['1_pavarde']));

// Pritaikiame sistemoje nustatytas paraštes
$pdf->SetMargins($margins['l'], $margins['t'], $margins['r']);
$pdf->SetAutoPageBreak(false, $margins['b']); // False nes diplomas telpa viename puslapyje

$pdf->AddPage();
$pdf->writeHTML($html, true, false, true, false, '');

// Išvalome buferį
while (ob_get_level()) { ob_end_clean(); }

// Sugeneruojame PDF
$pdf->Output('Diplomas_' . $dalyvis_id . '.pdf', 'I');
exit;