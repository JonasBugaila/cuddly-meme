<?php
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

// Nuskaitome admino sukurtą šabloną
$template_file = $root . '/config/diploma_template.html';
if (file_exists($template_file)) {
    $html_template = file_get_contents($template_file);
} else {
    // Jei dar nesukurtas, naudojame numatytąjį
    $html_template = '<div style="position:relative;padding:60px 80px;text-align:center;font-family:\'{FONT_NAME}\';">
        <div style="position:absolute;top:30px;right:40px;font-size:14px;color:#999;font-weight:bold;">{DIP_NR}</div>
        {LOGO}
        <h1 style="font-size:38px;margin:25px 0;font-weight:300;letter-spacing:1px;">Diplomas</h1>
        <p style="font-size:21px;font-style:italic;color:#555;margin-bottom:45px;">Už pasiekimus respublikinėje olimpiadoje</p>
        <div style="font-size:52px;font-weight:bold;color:#d4af37;margin:35px 0;">{VIETA}</div>
        <div style="font-size:44px;font-weight:bold;color:#2c3e50;margin:25px 0;">{VARDAS_PAVARDE}</div>
        <div style="font-size:26px;color:#444;margin:18px 0;line-height:1.3;">{MOKYKLA}</div>
        <div style="font-size:24px;color:#666;margin:35px 0;font-style:italic;">„{OLIMPIADA}“</div>
        <div style="font-size:19px;color:#888;margin-top:60px;">{DATA}</div>
    </div>';
}

$year = date('Y', strtotime($dalyvis['pil_data']));
$dip_nr = sprintf("DIP-%d-%03d", $year, $dalyvis_id);
$vietos = ['I' => 'I vieta', 'II' => 'II vieta', 'III' => 'III vieta', 'laureat.' => 'Laureatas'];
$vieta = $vietos[$dalyvis['Vieta']] ?? 'Dalyvis';
$data = date('Y m. d d.', strtotime($dalyvis['pil_data']));

// Keičiame kintamuosius
$html = str_replace(
    ['{FONT_NAME}', '{DIP_NR}', '{LOGO}', '{VIETA}', '{VARDAS_PAVARDE}', '{MOKYKLA}', '{OLIMPIADA}', '{DATA}'],
    [$font_name, $dip_nr, $logo_svg, $vieta, htmlspecialchars($dalyvis['1_vardas'] . ' ' . $dalyvis['1_pavarde']), htmlspecialchars($dalyvis['mokykla'] ?? $dalyvis['var_mokykla']), htmlspecialchars($dalyvis['konkurso_pav']), $data],
    $html_template
);

// Generuojame PDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->SetCreator('Olimpiadų sistema');
$pdf->SetTitle('Diplomas - ' . htmlspecialchars($dalyvis['1_vardas'] . ' ' . $dalyvis['1_pavarde']));
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();
$pdf->writeHTML($html, true, false, true, false, '');

// 'I' nustatymas sugeneruoja PDF ir iš karto atidaro jį naršyklės lange (nereikia net siųstis ar atskiros HTML formos)
$pdf->Output('Diplomas_' . $dalyvis_id . '.pdf', 'I');