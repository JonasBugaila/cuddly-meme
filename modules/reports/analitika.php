<?php
/**
 * Išplėstinė analitika / reitingai
 *
 * Vienas puslapis su 5 ataskaitos režimais (renkama per "mode" GET parametrą):
 *  - list                    : individualus dalyvių sąrašas su filtrais (mokykla, olimpiada,
 *                               kitas etapas, prizinė vieta)
 *  - prizes                  : dalyviai, laimėję X ar daugiau prizinių vietų (suvestinė)
 *  - most_olympiads          : dalyviai, dalyvavę daugiausiai olimpiadų (suvestinė)
 *  - top_teachers_prizes     : mokytojai, kurių mokiniai laimėjo daugiausiai prizinių vietų
 *  - top_teachers_registrations : mokytojai, užregistravę daugiausiai dalyvių
 *
 * PASTABA DĖL DUOMENŲ MODELIO: DB schemoje nėra atskiro "mokinio" ID - kiekviena
 * `dalyviai` eilutė yra viena registracija vienai olimpiadai. "Tas pats dalyvis"
 * keliose olimpiadose atpažįstamas per (1_vardas, 1_pavarde, var_mokykla) derinį -
 * tai geriausias įmanomas artinys esamoje schemoje.
 *
 * "Prizinė vieta" apibrėžimas suvienodintas su modules/reports/statistics.php:
 * Vieta IN ('I', 'II', 'III').
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
    exit;
}
// SAUGU: ši ataskaita apima rezultatus (prizines vietas, balus), todėl matoma tik administratoriui
if (!is_admin()) {
    log_action('Saugumo pažeidimas', 'Bandyta pasiekti išplėstinę analitiką be admin teisių.');
    set_message('Neturite teisių peržiūrėti šios ataskaitos.', 'error');
    redirect(SITE_URL);
    exit;
}

$allowed_modes = ['list', 'prizes', 'most_olympiads', 'top_teachers_prizes', 'top_teachers_registrations'];
$mode = isset($_GET['mode']) && in_array($_GET['mode'], $allowed_modes, true) ? $_GET['mode'] : 'list';

// ---------------------------------------------------------
// BENDRI FILTRAI
// ---------------------------------------------------------
$filter_school = isset($_GET['school']) ? sanitize_input($_GET['school']) : '';
$filter_olympiad = isset($_GET['olympiad']) ? sanitize_input($_GET['olympiad']) : '';
$filter_next_stage = isset($_GET['next_stage']) ? sanitize_input($_GET['next_stage']) : ''; // '', 'yes', 'no'
$filter_prize_only = isset($_GET['prize_only']) && $_GET['prize_only'] === '1';
$min_prizes = isset($_GET['min_prizes']) ? max(1, (int)$_GET['min_prizes']) : 1;

$schools = db_get_results(db_query("SELECT pavadinimas FROM mokyklos ORDER BY pavadinimas ASC"));
$olympiads = db_get_results(db_query("SELECT DISTINCT konkurso_pav FROM konkursai ORDER BY konkurso_pav ASC"));

// ---------------------------------------------------------
// UŽKLAUSOS SUDARYMAS PAGAL REŽIMĄ
// core_sql   - vidinė užklausa BE LIMIT (naudojama ir COUNT'ui, ir puslapio duomenims)
// params/types - prepared statement parametrai
// headers    - lentelės antraštės (naudojamos ekrane, spausdinime IR CSV)
// row_mapper - closure, paverčiantis vieną DB eilutę į plokščią masyvą pagal headers tvarką
// ---------------------------------------------------------
$where = [];
$params = [];
$types = '';

if ($filter_school !== '' && in_array($mode, ['list', 'prizes', 'most_olympiads', 'top_teachers_prizes', 'top_teachers_registrations'])) {
    $where[] = ($mode === 'top_teachers_registrations') ? 'd.var_mokykla = ?' : 'var_mokykla = ?';
    $params[] = $filter_school;
    $types .= 's';
}
if ($filter_olympiad !== '' && in_array($mode, ['list', 'prizes', 'top_teachers_prizes', 'top_teachers_registrations'])) {
    $where[] = ($mode === 'top_teachers_registrations') ? 'd.konkurso_pav = ?' : 'konkurso_pav = ?';
    $params[] = $filter_olympiad;
    $types .= 's';
}

switch ($mode) {

    case 'prizes':
        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $core_sql = "SELECT 1_vardas, 1_pavarde, var_mokykla,
                        COUNT(CASE WHEN Vieta IN ('I','II','III') THEN 1 END) as prize_count,
                        COUNT(DISTINCT konkurso_pav) as olympiad_count
                     FROM dalyviai $where_sql
                     GROUP BY 1_vardas, 1_pavarde, var_mokykla
                     HAVING prize_count >= ?
                     ORDER BY prize_count DESC, olympiad_count DESC";
        $having_params = array_merge($params, [$min_prizes]);
        $having_types = $types . 'i';
        $headers = ['Vardas Pavardė', 'Mokykla', 'Prizinių vietų sk.', 'Olimpiadų sk.'];
        $row_mapper = function ($r) {
            return [$r['1_vardas'] . ' ' . $r['1_pavarde'], $r['var_mokykla'], $r['prize_count'], $r['olympiad_count']];
        };
        break;

    case 'most_olympiads':
        // Olimpiados filtras čia netaikomas - prasilenktų su pačia metrikos prasme.
        // (Bendrame filtrų bloke aukščiau šiam režimui olimpiados WHERE sąlyga
        // apskritai nepridedama, todėl $where jau savaime jos neturi.)
        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $core_sql = "SELECT 1_vardas, 1_pavarde, var_mokykla,
                        COUNT(DISTINCT konkurso_pav) as olympiad_count,
                        COUNT(CASE WHEN Vieta IN ('I','II','III') THEN 1 END) as prize_count
                     FROM dalyviai $where_sql
                     GROUP BY 1_vardas, 1_pavarde, var_mokykla
                     ORDER BY olympiad_count DESC, prize_count DESC";
        $having_params = $params;
        $having_types = $types;
        $headers = ['Vardas Pavardė', 'Mokykla', 'Olimpiadų sk.', 'Prizinių vietų sk.'];
        $row_mapper = function ($r) {
            return [$r['1_vardas'] . ' ' . $r['1_pavarde'], $r['var_mokykla'], $r['olympiad_count'], $r['prize_count']];
        };
        break;

    case 'top_teachers_prizes':
        $where[] = "1_mok IS NOT NULL AND 1_mok != ''";
        $where_sql = 'WHERE ' . implode(' AND ', $where);
        $core_sql = "SELECT 1_mok as mokytojas,
                        COUNT(CASE WHEN Vieta IN ('I','II','III') THEN 1 END) as prize_count,
                        COUNT(DISTINCT CONCAT(1_vardas,'|',1_pavarde,'|',var_mokykla)) as student_count,
                        COUNT(DISTINCT var_mokykla) as school_count
                     FROM dalyviai $where_sql
                     GROUP BY 1_mok
                     HAVING prize_count > 0
                     ORDER BY prize_count DESC, student_count DESC";
        $having_params = $params;
        $having_types = $types;
        $headers = ['Mokytojas', 'Prizinių vietų sk.', 'Skirtingų mokinių sk.', 'Mokyklų sk.'];
        $row_mapper = function ($r) {
            return [$r['mokytojas'], $r['prize_count'], $r['student_count'], $r['school_count']];
        };
        break;

    case 'top_teachers_registrations':
        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $core_sql = "SELECT d.vart_id, v.var_vardas, v.var_pavarde, v.var_mokykla as account_school,
                        COUNT(*) as registration_count
                     FROM dalyviai d
                     LEFT JOIN vartotojas v ON d.vart_id = v.vart_id
                     $where_sql
                     GROUP BY d.vart_id
                     ORDER BY registration_count DESC";
        $having_params = $params;
        $having_types = $types;
        $headers = ['Vartotojo ID', 'Vardas Pavardė', 'Priskirta mokykla', 'Užregistruota dalyvių'];
        $row_mapper = function ($r) {
            $full_name = trim(($r['var_vardas'] ?? '') . ' ' . ($r['var_pavarde'] ?? ''));
            return [$r['vart_id'], $full_name !== '' ? $full_name : '(paskyra ištrinta)', $r['account_school'] ?? '-', $r['registration_count']];
        };
        break;

    default: // 'list'
        if ($filter_next_stage === 'yes') { $where[] = 'kitas_etapas = 1'; }
        elseif ($filter_next_stage === 'no') { $where[] = 'kitas_etapas = 0'; }
        if ($filter_prize_only) { $where[] = "Vieta IN ('I','II','III')"; }

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $core_sql = "SELECT 1_vardas, 1_pavarde, 1_klase, var_mokykla, konkurso_pav, Balai, Vieta, kitas_etapas, 1_mok
                     FROM dalyviai $where_sql
                     ORDER BY var_mokykla ASC, 1_pavarde ASC";
        $having_params = $params;
        $having_types = $types;
        $headers = ['Vardas Pavardė', 'Klasė', 'Mokykla', 'Olimpiada', 'Balai', 'Vieta', 'Kitas etapas', 'Mokytojas'];
        $row_mapper = function ($r) {
            $etapas = ($r['kitas_etapas'] == 1) ? 'Siunčiamas' : (($r['kitas_etapas'] == 2) ? 'Nesiunčiamas' : '-');
            return [
                $r['1_vardas'] . ' ' . $r['1_pavarde'], $r['1_klase'], $r['var_mokykla'], $r['konkurso_pav'],
                $r['Balai'] ?? '-', $r['Vieta'] ?: '-', $etapas, $r['1_mok'] ?: '-'
            ];
        };
        $mode = 'list';
}

// ---------------------------------------------------------
// EKSPORTAS Į CSV (Excel suderinamas) - viso filtruoto rinkinio, be puslapiavimo, iki 5000 eilučių saugikliui
// ---------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_sql = $core_sql . " LIMIT 5000";
    $rows = db_get_results(db_query($export_sql, $having_params, $having_types));

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ataskaita_' . $mode . '_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM - kad Excel teisingai rodytų lietuviškas raides
    fputcsv($output, $headers, ';');
    foreach ($rows as $r) {
        fputcsv($output, $row_mapper($r), ';');
    }
    fclose($output);
    exit;
}

// ---------------------------------------------------------
// SPAUSDINIMO REŽIMAS - naudoja tą pačią generate_printable_table() kaip kitos ataskaitos
// ---------------------------------------------------------
if (isset($_GET['print']) && $_GET['print'] === '1') {
    header('Content-Type: text/html; charset=UTF-8');
    $print_sql = $core_sql . " LIMIT 1000";
    $rows = db_get_results(db_query($print_sql, $having_params, $having_types));

    $print_data = [];
    foreach ($rows as $r) {
        $print_data[] = array_map('htmlspecialchars', $row_mapper($r));
    }

    $mode_titles = [
        'list' => 'Dalyvių sąrašas',
        'prizes' => 'Dalyviai pagal prizines vietas',
        'most_olympiads' => 'Dalyviai pagal olimpiadų skaičių',
        'top_teachers_prizes' => 'Mokytojai pagal mokinių prizines vietas',
        'top_teachers_registrations' => 'Mokytojai pagal užregistruotų dalyvių skaičių',
    ];

    echo generate_printable_table($mode_titles[$mode], 'Švietimo pagalbos tarnyba', $headers, $print_data, [
        'include_back_button' => true
    ], 'protocol');
    exit;
}

// ---------------------------------------------------------
// PUSLAPIAVIMAS (ekrano peržiūrai)
// ---------------------------------------------------------
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
if (!in_array($limit, [10, 25, 50, 100])) $limit = 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$count_sql = "SELECT COUNT(*) as total FROM ($core_sql) as sub";
$total_items = db_get_row(db_query($count_sql, $having_params, $having_types))['total'] ?? 0;

$page_sql = $core_sql . " LIMIT ?, ?";
$page_rows = db_get_results(db_query($page_sql, array_merge($having_params, [$offset, $limit]), $having_types . 'ii'));

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h1 class="h4 mb-0"><i class="fas fa-chart-bar"></i> Išplėstinė analitika</h1>
                <div class="d-flex gap-2">
                    <a href="<?php echo build_url_with_params(['print' => 1]); ?>" target="_blank" class="btn btn-sm btn-light text-dark fw-bold"><i class="fas fa-print"></i> Spausdinti</a>
                    <a href="<?php echo build_url_with_params(['export' => 'csv']); ?>" class="btn btn-sm btn-success fw-bold"><i class="fas fa-file-excel"></i> Excel (CSV)</a>
                    <a href="<?php echo SITE_URL; ?>/modules/reports/index.php" class="btn btn-sm btn-secondary">Grįžti</a>
                </div>
            </div>
            <div class="card-body">
                <?php display_message(); ?>

                <!-- REŽIMO PASIRINKIMAS -->
                <ul class="nav nav-pills mb-3 flex-wrap">
                    <?php
                    $mode_labels = [
                        'list' => 'Dalyvių sąrašas',
                        'prizes' => 'Pagal prizines vietas',
                        'most_olympiads' => 'Pagal olimpiadų skaičių',
                        'top_teachers_prizes' => 'TOP mokytojai (prizai)',
                        'top_teachers_registrations' => 'TOP mokytojai (registracijos)',
                    ];
                    foreach ($mode_labels as $m_key => $m_label):
                    ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $mode === $m_key ? 'active' : ''; ?>" href="?mode=<?php echo $m_key; ?>"><?php echo $m_label; ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <!-- FILTRAVIMO FORMA -->
                <form method="get" action="" class="bg-light p-3 rounded mb-3 border">
                    <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>">
                    <input type="hidden" name="limit" value="<?php echo (int)$limit; ?>">
                    <div class="row g-2 align-items-end">

                        <?php if (in_array($mode, ['list', 'prizes', 'most_olympiads', 'top_teachers_prizes', 'top_teachers_registrations'])): ?>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Mokykla</label>
                            <select name="school" class="form-select form-select-sm">
                                <option value="">-- Visos mokyklos --</option>
                                <?php foreach ($schools as $sch): ?>
                                    <option value="<?php echo htmlspecialchars($sch['pavadinimas']); ?>" <?php echo $filter_school === $sch['pavadinimas'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sch['pavadinimas']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if (in_array($mode, ['list', 'prizes', 'top_teachers_prizes', 'top_teachers_registrations'])): ?>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Olimpiada</label>
                            <select name="olympiad" class="form-select form-select-sm">
                                <option value="">-- Visos olimpiados --</option>
                                <?php foreach ($olympiads as $o): ?>
                                    <option value="<?php echo htmlspecialchars($o['konkurso_pav']); ?>" <?php echo $filter_olympiad === $o['konkurso_pav'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($o['konkurso_pav']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if ($mode === 'list'): ?>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted mb-1">Kitas etapas</label>
                            <select name="next_stage" class="form-select form-select-sm">
                                <option value="">-- Visi --</option>
                                <option value="yes" <?php echo $filter_next_stage === 'yes' ? 'selected' : ''; ?>>Siunčiami</option>
                                <option value="no" <?php echo $filter_next_stage === 'no' ? 'selected' : ''; ?>>Nesiunčiami</option>
                            </select>
                        </div>
                        <div class="col-md-2 form-check ms-2 mb-1">
                            <input type="checkbox" class="form-check-input" id="prize_only" name="prize_only" value="1" <?php echo $filter_prize_only ? 'checked' : ''; ?>>
                            <label class="form-check-label small fw-bold text-muted" for="prize_only">Tik prizininkai</label>
                        </div>
                        <?php endif; ?>

                        <?php if ($mode === 'prizes'): ?>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted mb-1">Min. prizinių vietų</label>
                            <input type="number" name="min_prizes" min="1" value="<?php echo (int)$min_prizes; ?>" class="form-control form-control-sm">
                        </div>
                        <?php endif; ?>

                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter"></i> Filtruoti</button>
                        </div>
                    </div>
                </form>

                <?php if (!empty($page_rows)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle" style="font-size: 0.9em;">
                            <thead class="table-light">
                                <tr>
                                    <?php foreach ($headers as $h): ?><th><?php echo htmlspecialchars($h); ?></th><?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($page_rows as $r): $cells = $row_mapper($r); ?>
                                    <tr>
                                        <?php foreach ($cells as $i => $cell): ?>
                                            <td<?php echo $i === 0 ? ' class="fw-bold"' : ''; ?>><?php echo htmlspecialchars((string)$cell); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php render_pagination($total_items, $limit, $page); ?>
                <?php else: ?>
                    <div class="alert alert-info">Pagal pasirinktus filtrus rezultatų nerasta.</div>
                <?php endif; ?>

                <div class="alert alert-secondary mt-3 mb-0 small">
                    <i class="fas fa-info-circle"></i> "Prizinė vieta" - Vieta yra I, II arba III. Dalyviai keliose olimpiadose
                    sujungiami pagal vardo, pavardės ir mokyklos derinį (atskiro mokinio ID sistemoje nėra).
                    "Excel" eksportas - CSV formatu (atsidaro Excel programoje), iki 5000 eilučių.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>
