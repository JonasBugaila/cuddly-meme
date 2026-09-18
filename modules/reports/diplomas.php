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
require_once __DIR__ . '/diploma_render.php';

if (!is_logged_in() || !is_admin()) die('Prieiga draudžiama');

// Bendras šablonas (naudojamas ir pavieniam, ir masiniam režimui)
$dip_template = diploma_load_template($root);

// =========================================================================
// PAVIENIO DIPLOMO REŽIMAS (?id=REG_ID)
//
// IŠTAISYTA: winners.php sąraše prie kiekvieno prizininko yra mygtukas
// "PDF", nukreipiantis į diplomas.php?id=513 - bet šis failas parametro
// "id" apskritai NESKAITYDAVO. Be jokių filtrų užklausa grąžindavo VISUS
// prizininkus, ir vietoj vieno diplomo vartotojas gaudavo ZIP archyvą su
// visais. Dabar nurodžius id generuojamas TIK to dalyvio diplomas ir
// atiduodamas tiesiai naršyklei kaip PDF (be ZIP).
// =========================================================================
$single_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($single_id > 0) {
    $sql = "SELECT d.*, m.pavadinimas AS mokykla
            FROM dalyviai d
            LEFT JOIN mokyklos m ON d.var_mokykla = m.pavadinimas
            WHERE d.reg_id = ?";
    $stmt = db_query($sql, [$single_id], 'i');
    $dalyvis = $stmt ? db_get_row($stmt) : null;

    if (!$dalyvis) {
        die('Dalyvis nerastas.');
    }

    $pdf = diploma_build_pdf($dalyvis, $dip_template, $font_name ?? 'dejavusans', $logo_svg ?? '');

    $filename = 'Diplomas_' . $dalyvis['1_vardas'] . '_' . $dalyvis['1_pavarde'] . '_' . $dalyvis['reg_id'] . '.pdf';
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);

    // 'I' = atidaroma naršyklėje (peržiūrai), o ne priverstinai atsisiunčiama
    $pdf->Output($filename, 'I');
    exit;
}

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

// Ciklas generuoja kiekvieną PDF ir deda į ZIP
// PERTVARKYTA: vietoj nukopijuoto kodo naudojama bendra diploma_build_pdf()
// funkcija (modules/reports/diploma_render.php) - ta pati, kurią naudoja ir
// pavienio diplomo režimas bei export_diplomas.php. Taip visi trys generavimo
// keliai visada naudoja TĄ PATĮ šabloną ir tuos pačius sulinksniuotus kintamuosius.
foreach ($prizininkai as $d) {
    $pdf = diploma_build_pdf($d, $dip_template, $font_name, $logo_svg ?? '');

    
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