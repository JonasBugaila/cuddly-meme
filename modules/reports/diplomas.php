<?php
// =========================================================================
// SAUGUMO IR SERVERIO LIMITŲ OPTIMIZAVIMAS MASINIAM EKSPORTUI
// =========================================================================
ini_set('memory_limit', '1024M'); // Leidžiame naudoti daugiau atminties PDF'ams
set_time_limit(0); // Išjungiame maksimalaus vykdymo laiko limitą (kad serveris nepakibtų)

// Išvalome šiukšles buferyje, kad ZIP failas nesugestų
if (ob_get_length()) ob_end_clean();

$root = dirname(dirname(dirname(__FILE__)));
require_once $root . '/config/config.php';
require_once $root . '/config/db_connect.php';
require_once $root . '/config/functions.php';
require_once $root . '/vendor/tcpdf/tcpdf.php';
require_once __DIR__ . '/diploma_style.php';

if (!is_logged_in() || !is_admin()) die('Prieiga draudžiama');

// =========================================================================
// 1. FILTRAVIMAS (Atitinka winners.php siunčiamus parametrus)
// =========================================================================
$olympiad = isset($_GET['olympiad']) ? sanitize_input($_GET['olympiad']) : '';
$school = isset($_GET['school']) ? sanitize_input($_GET['school']) : '';

$where_clauses = ["d.Vieta IN ('I','II','III','laureat.')"];
$params = [];
$types = '';

if (!empty($olympiad)) {
    $where_clauses[] = "d.konkurso_pav = ?";
    $params[] = $olympiad;
    $types .= 's';
}
if (!empty($school)) {
    $where_clauses[] = "d.var_mokykla = ?";
    $params[] = $school;
    $types .= 's';
}

$where = implode(' AND ', $where_clauses);

// Gauname prizininkus pagal filtrą
$sql = "SELECT d.*, m.pavadinimas AS mokykla 
        FROM dalyviai d 
        LEFT JOIN mokyklos m ON d.var_mokykla = m.pavadinimas 
        WHERE $where 
        ORDER BY d.konkurso_pav, FIELD(d.Vieta, 'I','II','III','laureat.'), CAST(d.Balai AS UNSIGNED) DESC";
        
$stmt = db_query($sql, $params, $types);
$prizininkai = $stmt ? db_get_results($stmt) : [];

if (empty($prizininkai)) {
    die('Pagals jūsų pasirinktus filtrus nerasta jokių prizininkų.');
}

// =========================================================================
// 2. ZIP ARCHYVO PARUOŠIMAS
// =========================================================================
$zip_name = "Diplomai_" . date('Ymd_His');
if (!empty($olympiad)) {
    $zip_name = "Diplomai_" . preg_replace('/[^a-zA-Z0-9]/', '_', $olympiad);
}

$zip_filename = $zip_name . ".zip";
$zip_path = sys_get_temp_dir() . '/' . $zip_filename;

$zip = new ZipArchive();
if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("Nepavyko sukurti ZIP archyvo laikinajame serverio aplanke.");
}

// =========================================================================
// 3. PDF GENERAVIMAS (Optimizuotas ciklas)
// =========================================================================
// Nenaudojame addTTFfont, nes TCPDF jau turi dejavusans šriftą. Tai be galo pagreitina sistemą!
$font_name = 'dejavusans';

// Užkrauname admino redaguotą šabloną
$template_file = $root . '/config/diploma_template.html';
if (file_exists($template_file)) {
    $html_template = file_get_contents($template_file);
} else {
    $html_template = '<div style="font-family:\'{FONT_NAME}\'; text-align:center;"><h2>{VARDAS_PAVARDE}</h2><p>{VIETA}</p></div>';
}

// Ciklas generuoja kiekvieną PDF ir deda į ZIP
foreach ($prizininkai as $d) {
    // Kuriamas TCPDF objektas be disko cache, kas šiek tiek pagreitina vykdymą
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false); 
    $pdf->SetCreator('Olimpiadų sistema');
    $pdf->SetTitle('Diplomas - ' . $d['1_vardas'] . ' ' . $d['1_pavarde']);
    
    // Išjungiame antraštes/paraštes, kad neeikvotų atminties
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();

    $year = date('Y', strtotime($d['pil_data'] ?? date('Y-m-d')));
    $dip_nr = sprintf("DIP-%d-%03d", $year, $d['reg_id']);
    $vietos = ['I' => 'I vieta', 'II' => 'II vieta', 'III' => 'III vieta', 'laureat.' => 'Laureatas'];
    $vieta = $vietos[$d['Vieta']] ?? 'Dalyvis';
    $data = date('Y m. d d.', strtotime($d['pil_data'] ?? date('Y-m-d')));

    // Šablono kintamųjų pakeitimas realiomis reikšmėmis
    $html = str_replace(
        ['{FONT_NAME}', '{DIP_NR}', '{LOGO}', '{VIETA}', '{VARDAS_PAVARDE}', '{MOKYKLA}', '{OLIMPIADA}', '{DATA}'],
        [$font_name, $dip_nr, $logo_svg ?? '', $vieta, htmlspecialchars($d['1_vardas'] . ' ' . $d['1_pavarde']), htmlspecialchars($d['mokykla'] ?? $d['var_mokykla']), htmlspecialchars($d['konkurso_pav']), $data],
        $html_template
    );

    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Gauname PDF failą tiesiai į serverio RAM (neprašome išsaugoti į diską)
    $pdf_content = $pdf->Output('', 'S'); 

    // Failo pavadinimas su ID, kad bendravardžiai neperrašytų vienas kito
    $filename = "Diplomas_{$d['1_vardas']}_{$d['1_pavarde']}_{$d['reg_id']}.pdf";
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
    
    $zip->addFromString($filename, $pdf_content);
    
    // SVARBU: Išlaisviname serverio RAM atmintį po kiekvieno diplomo!
    unset($pdf); 
}

$zip->close();

// =========================================================================
// 4. ARCHYVO PARSIUNTIMAS VARTOTOJUI
// =========================================================================
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
header('Content-Length: ' . filesize($zip_path));
header('Pragma: no-cache');
header('Expires: 0');

readfile($zip_path);

// Ištriname laikiną ZIP failą iš serverio po atsiuntimo
unlink($zip_path); 
exit;