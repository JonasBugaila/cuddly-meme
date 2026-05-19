<?php
/**
 * Mokyklų olimpiadų ataskaitos puslapis
 * Eksportas į CSV ir Excel (VISI įrašai), puslapiavimas tik naršyklėje
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname, ar vartotojas prisijungęs
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

// Gauname parametrus
$selected_olympiad = isset($_GET['olympiad']) ? trim(sanitize_input($_GET['olympiad'])) : '';
$current_page = isset($_GET['page']) ? max(1, (int)sanitize_input($_GET['page'])) : 1;
$items_per_page = 10;
$print_mode = isset($_GET['print']) && $_GET['print'] == '1';
$export_csv = isset($_GET['export']) && $_GET['export'] == 'csv';
$export_excel = isset($_GET['export']) && $_GET['export'] == 'excel';

// Antraštė
$page_title = !empty($selected_olympiad) ? htmlspecialchars($selected_olympiad) . ' ataskaita' : 'Mokyklų olimpiadų ataskaita';

// Olimpiadų sąrašas filtrui
$sql = "SELECT DISTINCT konkurso_pav FROM konkursai ORDER BY konkurso_pav";
$stmt = db_query($sql);
$olympiads = $stmt ? db_get_results($stmt) : [];

// Vartotojo mokykla (ne admin)
$user_data = null;
if (!is_admin()) {
    $user_sql = "SELECT var_mokykla FROM vartotojas WHERE vart_id = ?";
    $user_stmt = db_query($user_sql, [$_SESSION['user_id']], 's');
    $user_data = db_get_row($user_stmt);
    if (!$user_data || empty($user_data['var_mokykla'])) {
        set_message('Jūsų mokykla nenurodyta. Susisiekite su administratoriumi.', 'error');
        redirect(SITE_URL . '/modules/reports/index.php');
    }
}

// === 1. SKAIČIUOJAME BENDRĄ KIEKĮ PUSLAPIAVIMUI ===
$count_sql = "SELECT COUNT(*) as total FROM mokyklos m LEFT JOIN dalyviai d ON m.pavadinimas = d.var_mokykla LEFT JOIN konkursai k ON d.konkurso_pav = k.konkurso_pav";
$count_params = [];
$count_types = '';

if (!is_admin()) {
    $count_sql .= " WHERE m.pavadinimas = ?";
    $count_params[] = $user_data['var_mokykla'];
    $count_types .= 's';
}
if (!empty($selected_olympiad)) {
    $count_sql .= (strpos($count_sql, 'WHERE') !== false ? " AND" : " WHERE") . " d.konkurso_pav = ?";
    $count_params[] = $selected_olympiad;
    $count_types .= 's';
}

$count_stmt = db_query($count_sql, $count_params, $count_types);
$total_items = $count_stmt ? (db_get_row($count_stmt)['total'] ?? 0) : 0;
$total_pages = max(1, ceil($total_items / $items_per_page));

// === 2. PAGRINDINĖ UŽKLAUSA (su LIMIT tik naršyklėje) ===
$sql = "
    SELECT 
        m.pavadinimas AS mokykla,
        d.konkurso_pav AS olimpiada,
        d.1_vardas, d.1_pavarde, d.1_klase, d.var_mokykla,
        d.1_mok, d.2_mok, d.Balai, d.Vieta
    FROM mokyklos m
    LEFT JOIN dalyviai d ON m.pavadinimas = d.var_mokykla
    LEFT JOIN konkursai k ON d.konkurso_pav = k.konkurso_pav
";

// === PARAMETRŲ IR TIPŲ NUSTATYMAS (visada prieš užklausą) ===
$params = [];
$param_types = '';

if (!is_admin()) {
    $sql .= " WHERE m.pavadinimas = ?";
    $params[] = $user_data['var_mokykla'];
    $param_types .= 's';
}
if (!empty($selected_olympiad)) {
    $sql .= (strpos($sql, 'WHERE') !== false ? " AND" : " WHERE") . " d.konkurso_pav = ?";
    $params[] = $selected_olympiad;
    $param_types .= 's';
}

$sql .= " ORDER BY m.pavadinimas, d.konkurso_pav, d.1_pavarde, d.1_vardas";

// SKIRTINGA UŽKLAUSA EKSPORTUI – BE LIMIT
if ($export_csv || $export_excel) {
    $export_stmt = db_query($sql, $params, $param_types);
    $results = $export_stmt ? db_get_results($export_stmt) : [];
} else {
    // TIK NARŠYKLĖJE – LIMIT (išskyrus spausdinimą)
    if (!$print_mode) {
        $offset = ($current_page - 1) * $items_per_page;
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $items_per_page;
        $params[] = $offset;
        $param_types .= 'ii';
    }

    $stmt = db_query($sql, $params, $param_types);
    $results = $stmt ? db_get_results($stmt) : [];
}

// Grupavimas
$grouped_data = [];
foreach ($results as $row) {
    if (!empty($row['olimpiada'])) {
        $mokykla = $row['mokykla'];
        $olimpiada = $row['olimpiada'];
        $grouped_data[$mokykla][$olimpiada][] = $row;
    }
}

// === 3. EKSPORTAS Į CSV – VISI DUOMENYS ===
if ($export_csv) {
    $filename = str_replace(' ', '_', $page_title) . '_eksportas_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM

    fputcsv($output, [
        'Mokykla', 'Olimpiada', 'Eil. Nr.', 'Vardas', 'Pavardė', 'Klasė',
        'Mokykla (pakartotinai)', 'Mokytojas 1', 'Mokytojas 2', 'Balai', 'Vieta'
    ], ';', '"');

    // Atskirai gauname VISUS duomenis be LIMIT
    $export_sql = str_replace('LIMIT ? OFFSET ?', '', $sql);
    $export_stmt = db_query($export_sql, array_slice($params, 0, -2), substr($param_types, 0, -2));
    $export_results = $export_stmt ? db_get_results($export_stmt) : [];

    $index = 1;
    foreach ($export_results as $row) {
        if (!empty($row['olimpiada'])) {
            fputcsv($output, [
                $row['mokykla'],
                $row['olimpiada'],
                $index++,
                $row['1_vardas'] ?? '',
                $row['1_pavarde'] ?? '',
                $row['1_klase'] ?? '',
                $row['var_mokykla'] ?? '',
                $row['1_mok'] ?? '',
                $row['2_mok'] ?? '',
                $row['Balai'] ?? '',
                $row['Vieta'] ?? ''
            ], ';', '"');
        }
    }
    fclose($output);
    exit;
}

// === 4. EKSPORTAS Į EXCEL (.xls) – VISI DUOMENYS ===
if ($export_excel) {
    $filename = str_replace(' ', '_', $page_title) . '_eksportas_' . date('Y-m-d') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"><style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid black; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .title { font-size: 18px; font-weight: bold; text-align: center; margin: 20px 0; }
        .section { margin: 15px 0; font-weight: bold; }
    </style></head><body>';

    echo '<div class="title">' . htmlspecialchars($page_title) . '</div>';

    // Naudojame tuos pačius visus duomenis
    $export_stmt = db_query(str_replace('LIMIT ? OFFSET ?', '', $sql), array_slice($params, 0, -2), substr($param_types, 0, -2));
    $all_data = $export_stmt ? db_get_results($export_stmt) : [];

    $grouped_export = [];
    foreach ($all_data as $row) {
        if (!empty($row['olimpiada'])) {
            $grouped_export[$row['mokykla']][$row['olimpiada']][] = $row;
        }
    }

    $index = 1;
    foreach ($grouped_export as $mokykla => $olimpiados) {
        foreach ($olimpiados as $olimpiada => $dalyviai) {
            echo '<div class="section">' . htmlspecialchars($olimpiada) . ' – ' . htmlspecialchars($mokykla) . '</div>';
            echo '<table><tr>
                <th>Eil. Nr.</th><th>Vardas</th><th>Pavardė</th><th>Klasė</th>
                <th>Mokykla</th><th>Mokytojas 1</th><th>Mokytojas 2</th><th>Balai</th><th>Vieta</th>
            </tr>';
            foreach ($dalyviai as $dal) {
                echo '<tr>
                    <td>' . $index++ . '</td>
                    <td>' . htmlspecialchars($dal['1_vardas']) . '</td>
                    <td>' . htmlspecialchars($dal['1_pavarde']) . '</td>
                    <td>' . htmlspecialchars($dal['1_klase'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($dal['var_mokykla']) . '</td>
                    <td>' . htmlspecialchars($dal['1_mok'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($dal['2_mok'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($dal['Balai'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($dal['Vieta'] ?? '-') . '</td>
                </tr>';
            }
            echo '</table>';
        }
    }
    echo '</body></html>';
    exit;
}

// === 5. SPAUSDINIMO REŽIMAS ===
if ($print_mode && !empty($grouped_data)) {
    header('Content-Type: text/html; charset=UTF-8');
    $html = '<h1 style="text-align:center;font-size:24px;margin-bottom:20px;">' . $page_title . '</h1>';
    foreach ($grouped_data as $mokykla => $olimpiados) {
        foreach ($olimpiados as $olimpiada => $dalyviai) {
            $headers = ['Eil. Nr.', 'Vardas', 'Pavardė', 'Klasė', 'Mokykla', 'Mokytojas 1', 'Mokytojas 2', 'Balai', 'Vieta'];
            $data = []; $i = 1;
            foreach ($dalyviai as $dal) {
                $data[] = [
                    $i++, htmlspecialchars($dal['1_vardas']), htmlspecialchars($dal['1_pavarde']),
                    htmlspecialchars($dal['1_klase'] ?? '-'), htmlspecialchars($dal['var_mokykla']),
                    htmlspecialchars($dal['1_mok'] ?? '-'), htmlspecialchars($dal['2_mok'] ?? '-'),
                    htmlspecialchars($dal['Balai'] ?? '-'), htmlspecialchars($dal['Vieta'] ?? '-')
                ];
            }
            $html .= '<div style="page-break-before:always;margin-top:30px;">';
            $html .= '<h3 style="text-align:center;">' . htmlspecialchars($olimpiada) . ' – ' . htmlspecialchars($mokykla) . '</h3>';
            $html .= generate_printable_table($olimpiada, $mokykla, $headers, $data, [
                'signature_text' => 'Atsakingo asmens parašas', 'include_back_button' => false
            ]);
            $html .= '</div>';
        }
    }
    echo '<style>@media print {.page-break{page-break-before:always;}}</style>' . $html;
    exit;
}

// === 6. NARŠYKLĖS ATVAIZDAVIMAS ===
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h1><?php echo $page_title; ?></h1>
                <div>
                    <?php if (!empty($grouped_data)): ?>
                        <a href="?olympiad=<?php echo urlencode($selected_olympiad); ?>&print=1" target="_blank" class="btn btn-primary">Spausdinti</a>
                        <a href="?olympiad=<?php echo urlencode($selected_olympiad); ?>&export=csv" class="btn btn-success">CSV</a>
                        <a href="?olympiad=<?php echo urlencode($selected_olympiad); ?>&export=excel" class="btn btn-info">Excel</a>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>/modules/reports/index.php" class="btn btn-secondary">Atgal</a>
                </div>
            </div>
            <div class="card-body">
                <!-- Filtras -->
                <form method="get" class="mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <label>Olimpiada<?php echo is_admin() ? '' : ' (jūsų mokyklai)'; ?></label>
                            <select name="olympiad" class="form-control">
                                <option value="">Visos olimpiados</option>
                                <?php foreach ($olympiads as $o): ?>
                                    <option value="<?php echo htmlspecialchars($o['konkurso_pav']); ?>" <?php echo $selected_olympiad === $o['konkurso_pav'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($o['konkurso_pav']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">Filtruoti</button>
                        </div>
                    </div>
                </form>

                <!-- Duomenys -->
                <?php if (!empty($grouped_data)): ?>
                    <?php foreach ($grouped_data as $mokykla => $olimpiados): ?>
                        <div class="mb-4">
                            <h3><?php echo htmlspecialchars($mokykla); ?></h3>
                            <?php foreach ($olimpiados as $olimpiada => $dalyviai): ?>
                                <h4><?php echo htmlspecialchars($olimpiada); ?></h4>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead><tr>
                                            <th>Eil.</th><th>Vardas</th><th>Pavardė</th><th>Klasė</th>
                                            <th>Mokykla</th><th>Mokyt.1</th><th>Mokyt.2</th><th>Balai</th><th>Vieta</th>
                                        </tr></thead>
                                        <tbody>
                                            <?php $i = 1 + ($current_page - 1) * $items_per_page; ?>
                                            <?php foreach ($dalyviai as $d): ?>
                                                <tr>
                                                    <td><?php echo $i++; ?></td>
                                                    <td><?php echo htmlspecialchars($d['1_vardas']); ?></td>
                                                    <td><?php echo htmlspecialchars($d['1_pavarde']); ?></td>
                                                    <td><?php echo htmlspecialchars($d['1_klase'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($d['var_mokykla']); ?></td>
                                                    <td><?php echo htmlspecialchars($d['1_mok'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($d['2_mok'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($d['Balai'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($d['Vieta'] ?? '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>

                    <!-- Puslapiavimas -->
                    <?php if ($total_pages > 1): ?>
                        <nav>
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?olympiad=<?php echo urlencode($selected_olympiad); ?>&page=<?php echo $current_page - 1; ?>">Ankstesnis</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?olympiad=<?php echo urlencode($selected_olympiad); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?olympiad=<?php echo urlencode($selected_olympiad); ?>&page=<?php echo $current_page + 1; ?>">Kitas</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">Nėra dalyvių pagal pasirinktus filtrus.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>