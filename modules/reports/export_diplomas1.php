<?php
/**
 * Masinis diplomų eksportas (ZIP su PDF) – RANKINIS TCPDF
 */

$root = dirname(dirname(dirname(__FILE__))); // /olimpiada
require_once $root . '/config/config.php';
require_once $root . '/config/db_connect.php';
require_once $root . '/config/functions.php';

// === RANKINIS TCPDF ĮKĖLIMAS ===
require_once $root . '/vendor/tcpdf/tcpdf.php';

if (!is_logged_in() || !is_admin()) {
    die('Prieiga draudžiama');
}

if (!isset($_GET['konkursas'])) {
    die('Pasirinkite olimpiadą');
}

$konkurso_pav = urldecode($_GET['konkursas']);

// Gauti prizininkus
$sql = "SELECT d.reg_id, d.1_vardas, d.1_pavarde, d.Vieta, d.pil_data, m.pavadinimas AS mokykla
        FROM dalyviai d 
        LEFT JOIN mokyklos m ON d.var_mokykla = m.pavadinimas 
        WHERE d.konkurso_pav = ? AND d.Vieta IN ('I','II','III','laureat.')
        ORDER BY d.Vieta, d.Balai DESC";
$stmt = db_query($sql, [$konkurso_pav], 's');
$prizininkai = db_get_results($stmt);

if (empty($prizininkai)) {
    die('Nėra prizininkų');
}

$zip = new ZipArchive();
$zip_filename = "Diplomas_" . preg_replace('/[^a-zA-Z0-9]/', '_', $konkurso_pav) . ".zip";
$zip_path = sys_get_temp_dir() . '/' . $zip_filename;

if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
    die("Nepavyko sukurti ZIP");
}

foreach ($prizininkai as $d) {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->SetCreator('Olimpiadų sistema');
    $pdf->SetTitle('Diplomas');
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();

    // DIP numeris
    $year = date('Y', strtotime($d['pil_data']));
    $dip_nr = sprintf("DIP-%d-%03d", $year, $d['reg_id']);

    // Vieta
    $vietos = ['I' => 'I vieta', 'II' => 'II vieta', 'III' => 'III vieta', 'laureat.' => 'Laureatas'];
    $vieta = $vietos[$d['Vieta']] ?? 'Dalyvis';

    $data = date('Y m. d d.', strtotime($d['pil_data']));

    $html = '
    <div style="position:relative;padding:60px 80px;text-align:center;">
        <div style="position:absolute;top:30px;right:40px;font-size:14px;color:#999;">' . $dip_nr . '</div>
        <div style="width:90px;height:90px;background:linear-gradient(135deg,#007bff,#0056b3);border-radius:50%;color:white;font-weight:bold;font-size:28px;line-height:90px;margin:0 auto 25px;">OL</div>
        <h1 style="font-size:38px;margin:25px 0;">Diplomas</h1>
        <p style="font-size:21px;font-style:italic;color:#555;margin-bottom:45px;">Už pasiekimus respublikinėje olimpiadoje</p>
        <div style="font-size:52px;font-weight:bold;color:#d4af37;margin:35px 0;">' . $vieta . '</div>
        <div style="font-size:44px;font-weight:bold;color:#2c3e50;margin:25px 0;">' . htmlspecialchars($d['1_vardas'] . ' ' . $d['1_pavarde']) . '</div>
        <div style="font-size:26px;color:#444;margin:18px 0;">' . htmlspecialchars($d['mokykla'] ?? 'Mokykla') . '</div>
        <div style="font-size:24px;color:#666;margin:35px 0;font-style:italic;">„' . htmlspecialchars($konkurso_pav) . '“</div>
        <div style="font-size:19px;color:#888;margin-top:60px;">' . $data . '</div>
    </div>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf_content = $pdf->Output('', 'S');

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