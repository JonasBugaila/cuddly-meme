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

// =========================================================================
// ŠABLONO UŽKROVIMAS
//
// IŠTAISYTA KRITINĖ KLAIDA: anksčiau šis failas skaitydavo SENĄ
// "config/diploma_template.html", o administratoriaus redaktorius
// (modules/admin/diploma_template.php) savo pakeitimus išsaugo į NAUJĄ
// "config/diploma_layout.json" - todėl VISI šablono redagavimai buvo
// tyliai ignoruojami, o PDF visada generuodavosi iš seno, nebeatnaujinamo
// failo. Dabar pirmenybė teikiama JSON failui (tam, kurį realiai
// redaguoja administratorius), o senas HTML naudojamas tik kaip atsarginis
// variantas senoms sistemoms.
// =========================================================================
$layout_file   = $root . '/config/diploma_layout.json';
$template_file = $root . '/config/diploma_template.html';

$html_template = '<div style="font-family:\'{FONT_NAME}\'; text-align:center;"><h2>{VARDAS_PAVARDE}</h2><p>{VIETA}</p></div>';
$dip_margins   = ['t' => 0, 'b' => 0, 'l' => 0, 'r' => 0];

if (file_exists($layout_file)) {
    $dip_layout = json_decode(file_get_contents($layout_file), true);
    if (is_array($dip_layout) && !empty($dip_layout['content_html'])) {
        $html_template = $dip_layout['content_html'];
        $dip_margins = [
            't' => (int)($dip_layout['margin_t'] ?? 0),
            'b' => (int)($dip_layout['margin_b'] ?? 0),
            'l' => (int)($dip_layout['margin_l'] ?? 0),
            'r' => (int)($dip_layout['margin_r'] ?? 0),
        ];
    }
} elseif (file_exists($template_file)) {
    $html_template = file_get_contents($template_file);
}

// =========================================================================
// FONO PAVEIKSLĖLIS (įstaigos firminis blankas)
//
// Jei assets/img/ kataloge yra diploma_bg.jpg (arba .png), jis bus
// nupieštas per visą A4 lapą PRIEŠ tekstą - taip diplomas/padėka gauna
// tikrą įstaigos firminį dizainą (spalvas, logotipą, ornamentus), o
// šablone tereikia rašyti patį tekstą.
// =========================================================================
$bg_image = null;
foreach (['diploma_bg.jpg', 'diploma_bg.jpeg', 'diploma_bg.png'] as $bg_candidate) {
    if (file_exists($root . '/assets/img/' . $bg_candidate)) {
        $bg_image = $root . '/assets/img/' . $bg_candidate;
        break;
    }
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
    // PATAISYTA: naudojamos administratoriaus nustatytos paraštės iš šablono
    // (anksčiau jos būdavo išsaugomos, bet niekada nepritaikomos generuojant PDF)
    $pdf->SetMargins($dip_margins['l'], $dip_margins['t'], $dip_margins['r']);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();

    // NAUJA: firminis fonas per visą lapą (jei įkeltas) - piešiamas PIRMAS,
    // kad visas tekstas atsidurtų virš jo
    if ($bg_image !== null) {
        $pdf->Image($bg_image, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
        $pdf->setPageMark();
    }

    $year = date('Y', strtotime($d['pil_data'] ?? date('Y-m-d')));
    $dip_nr = sprintf("DIP-%d-%03d", $year, $d['reg_id']);
    $vietos = ['I' => 'I vieta', 'II' => 'II vieta', 'III' => 'III vieta', 'laureat.' => 'Laureatas'];
    $vieta = $vietos[$d['Vieta']] ?? 'Dalyvis';
    // NAUJA: vieta GALININKU - reikalinga po dalyvio "užėmusiam/užėmusiai"
    // ("užėmusiai I vietą", ne "užėmusiai I vieta")
    $vietos_ka = ['I' => 'I vietą', 'II' => 'II vietą', 'III' => 'III vietą', 'laureat.' => 'laureato vietą'];
    $vieta_ka = $vietos_ka[$d['Vieta']] ?? 'dalyvio vietą';
    $data = date('Y m. d d.', strtotime($d['pil_data'] ?? date('Y-m-d')));

    // =====================================================================
    // NAUJA: automatiškai sulinksniuoti kintamieji (žr. config/functions.php)
    // Leidžia šablone rašyti taisyklinga lietuvių kalba, pvz.:
    //   "{MOKYKLOS} {KLASE} {MOKINIUI} {VARDAS_PAVARDE_KAM} ...
    //    olimpiados Savivaldybės etape {UZEMUSIAM} {VIETA}"
    // =====================================================================
    $vardas_raw   = $d['1_vardas'] ?? '';
    $pavarde_raw  = $d['1_pavarde'] ?? '';
    $mokykla_raw  = $d['mokykla'] ?? $d['var_mokykla'] ?? '';
    $gender       = lt_guess_gender($vardas_raw, $pavarde_raw);

    $vardas_kam   = lt_name_to_dative($vardas_raw, $pavarde_raw);
    $mokyklos     = lt_school_to_genitive($mokykla_raw);
    $mokiniui     = lt_student_word($gender, 'n');   // mokiniui / mokinei
    $mokinys      = lt_student_word($gender, 'v');   // mokinys / mokinė
    $uzemusiam    = lt_participle_uzemus($gender);   // užėmusiam / užėmusiai

    // Šablono kintamųjų pakeitimas realiomis reikšmėmis
    $html = str_replace(
        [
            '{FONT_NAME}', '{DIP_NR}', '{LOGO}', '{VIETA}',
            '{VARDAS_PAVARDE}', '{MOKYKLA}', '{OLIMPIADA}', '{DATA}',
            // Nauji, sulinksniuoti kintamieji:
            '{VARDAS_PAVARDE_KAM}', '{MOKYKLOS}', '{MOKINIUI}', '{MOKINYS}',
            '{UZEMUSIAM}', '{KLASE}', '{MOKYTOJAS}', '{VIETA_KA}', '{OLIMPIADOJE}'
        ],
        [
            $font_name, $dip_nr, $logo_svg ?? '', $vieta,
            htmlspecialchars($vardas_raw . ' ' . $pavarde_raw),
            htmlspecialchars($mokykla_raw),
            htmlspecialchars($d['konkurso_pav']),
            $data,
            htmlspecialchars($vardas_kam),
            htmlspecialchars($mokyklos),
            htmlspecialchars($mokiniui),
            htmlspecialchars($mokinys),
            htmlspecialchars($uzemusiam),
            htmlspecialchars($d['1_klase'] ?? ''),
            htmlspecialchars($d['1_mok'] ?? ''),
            $vieta_ka,
            htmlspecialchars(lt_event_to_locative($d['konkurso_pav'] ?? ''))
        ],
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