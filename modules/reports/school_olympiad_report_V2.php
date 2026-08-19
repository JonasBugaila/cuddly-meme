<?php
/**
 * Mokyklų olimpiadų ataskaitos puslapis
 * Eksportas į CSV, Excel ir Spausdinimas (VISI įrašai), puslapiavimas tik naršyklėje
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname, ar vartotojas prisijungęs
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

// SAUGU: ši ataskaita rodo rezultatus (Balai/Vieta), todėl matoma tik administratoriui
if (!is_admin()) {
    log_action('Saugumo pažeidimas', 'Bandyta pasiekti mokyklų olimpiadų ataskaitą be admin teisių.');
    set_message('Neturite teisių peržiūrėti šios ataskaitos.', 'error');
    redirect(SITE_URL);
    exit;
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
    $user_stmt = db_query($user_sql, [$_SESSION['user_id']]);
    $user_data = db_get_row($user_stmt);
    if (!$user_data || empty($user_data['var_mokykla'])) {
        set_message('Jūsų mokykla nenurodyta. Susisiekite su administratoriumi.', 'error');
        redirect(SITE_URL . '/modules/reports/index.php');
    }
}

// === PARAMETRŲ PARUOŠIMAS (bendras visoms užklausoms) ===
$params = [];

if (!is_admin()) {
    $params[] = $user_data['var_mokykla'];
}
if (!empty($selected_olympiad)) {
    $params[] = $selected_olympiad;
}

// === SKAIČIUOJAME BENDRĄ KIEKĮ PUSLAPIAVIMUI ===
$count_sql = "SELECT COUNT(*) as total FROM mokyklos m 
              LEFT JOIN dalyviai d ON m.pavadinimas = d.var_mokykla 
              LEFT JOIN konkursai k ON d.konkurso_pav = k.konkurso_pav";
$where_parts = [];
if (!is_admin()) $where_parts[] = "m.pavadinimas = ?";
if (!empty($selected_olympiad)) $where_parts[] = "d.konkurso_pav = ?";
if (!empty($where_parts)) $count_sql .= " WHERE " . implode(" AND ", $where_parts);

$count_stmt = db_query($count_sql, $params);
$total_items = $count_stmt ? (db_get_row($count_stmt)['total'] ?? 0) : 0;
$total_pages = max(1, ceil($total_items / $items_per_page));

// === EKSPORTAS ===
if ($export_csv || $export_excel) {
    $export_sql = "
        SELECT 
            m.pavadinimas AS mokykla,
            d.konkurso_pav AS olimpiada,
            d.1_vardas, d.1_pavarde, d.1_klase, d.var_mokykla,
            d.1_mok, d.2_mok, d.Balai, d.Vieta
        FROM mokyklos m
        LEFT JOIN dalyviai d ON m.pavadinimas = d.var_mokykla
        LEFT JOIN konkursai k ON d.konkurso_pav = k.konkurso_pav
    ";
    $where_parts = [];
    if (!is_admin()) $where_parts[] = "m.pavadinimas = ?";
    if (!empty($selected_olympiad)) $where_parts[] = "d.konkurso_pav = ?";
    if (!empty($where_parts)) $export_sql .= " WHERE " . implode(" AND ", $where_parts);
    $export_sql .= " ORDER BY m.pavadinimas, d.konkurso_pav, d.1_pavarde, d.1_vardas";

    $stmt = db_query($export_sql, $params);
    $results = $stmt ? db_get_results($stmt) : [];

    if ($export_csv) {
        $filename = str_replace(' ', '_', $page_title) . '_eksportas_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, [
            'Mokykla', 'Olimpiada', 'Vardas', 'Pavardė', 'Klasė',
            'Mokykla (pakartota)', 'Mokyt.1', 'Mokyt.2', 'Balai', 'Vieta'
        ], ';');

        foreach ($results as $row) {
            if (!empty($row['olimpiada'])) {
                fputcsv($output, [
                    $row['mokykla'],
                    $row['olimpiada'],
                    $row['1_vardas'],
                    $row['1_pavarde'],
                    $row['1_klase'] ?? '-',
                    $row['var_mokykla'],
                    $row['1_mok'] ?? '-',
                    $row['2_mok'] ?? '-',
                    $row['Balai'] ?? '-',
                    $row['Vieta'] ?? '-'
                ], ';');
            }
        }
        fclose($output);
        exit;
    }

    if ($export_excel) {
        $filename = str_replace(' ', '_', $page_title) . '_eksportas_' . date('Y-m-d') . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');

        echo "\xEF\xBB\xBF";
        echo '<html><head><meta charset="UTF-8"></head><body>';
        echo '<table border="1">';
        echo '<tr>
                <th>Mokykla</th><th>Olimpiada</th><th>Vardas</th><th>Pavardė</th><th>Klasė</th>
                <th>Mokykla (2)</th><th>Mokyt.1</th><th>Mokyt.2</th><th>Balai</th><th>Vieta</th>
              </tr>';
        foreach ($results as $row) {
            if (!empty($row['olimpiada'])) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['mokykla']) . '</td>';
                echo '<td>' . htmlspecialchars($row['olimpiada']) . '</td>';
                echo '<td>' . htmlspecialchars($row['1_vardas']) . '</td>';
                echo '<td>' . htmlspecialchars($row['1_pavarde']) . '</td>';
                echo '<td>' . htmlspecialchars($row['1_klase'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['var_mokykla']) . '</td>';
                echo '<td>' . htmlspecialchars($row['1_mok'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['2_mok'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['Balai'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['Vieta'] ?? '-') . '</td>';
                echo '</tr>';
            }
        }
        echo '</table></body></html>';
        exit;
    }
}

// === PAGRINDINĖ UŽKLAUSA ===
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

$where_parts = [];
if (!is_admin()) $where_parts[] = "m.pavadinimas = ?";
if (!empty($selected_olympiad)) $where_parts[] = "d.konkurso_pav = ?";
if (!empty($where_parts)) $sql .= " WHERE " . implode(" AND ", $where_parts);

$sql .= " ORDER BY m.pavadinimas, d.konkurso_pav, d.1_pavarde, d.1_vardas";

// Pridedame LIMIT tik jei ne spausdinimas
if (!$print_mode) {
    $offset = ($current_page - 1) * $items_per_page;
    $sql .= " LIMIT " . (int)$items_per_page . " OFFSET " . (int)$offset;
}

$stmt = db_query($sql, $params);
$results = $stmt ? db_get_results($stmt) : [];

// Grupavimas
$grouped_data = [];
foreach ($results as $row) {
    if (!empty($row['olimpiada'])) {
        $mokykla = $row['mokykla'];
        $olimpiada = $row['olimpiada'];
        $grouped_data[$mokykla][$olimpiada][] = $row;
    }
}

// === SPAUSDINIMAS ===
if ($print_mode && !empty($grouped_data)) {
    header('Content-Type: text/html; charset=UTF-8');
    
    // Paruošiame minimalų HTML karkasą spausdinimui
    echo '<!DOCTYPE html><html lang="lt"><head><meta charset="UTF-8"><title>Spausdinimas</title>';
    echo '<style>body{font-family:Arial,sans-serif;} .page-break{page-break-after:always; margin-bottom:30px;}</style>';
    echo '</head><body>';
    
    foreach ($grouped_data as $mokykla => $olimpiados) {
        foreach ($olimpiados as $olimpiada => $dalyviai) {
            $headers = ['Eil.', 'Vardas', 'Pavardė', 'Klasė', 'Mokykla', 'Mokytojai', 'Balai', 'Vieta'];
            $data = [];
            $i = 1;
            foreach ($dalyviai as $d) {
                // Sujungiame abu mokytojus į vieną langelį, kad sutaupytume vietos
                $mokytojai = trim(htmlspecialchars($d['1_mok'] ?? ''));
                if (!empty($d['2_mok'])) {
                    $mokytojai .= ' / ' . htmlspecialchars($d['2_mok']);
                }
                if (empty($mokytojai)) $mokytojai = '-';

                $data[] = [
                    $i++,
                    htmlspecialchars($d['1_vardas'] ?? ''),
                    htmlspecialchars($d['1_pavarde'] ?? ''),
                    htmlspecialchars($d['1_klase'] ?? '-'),
                    htmlspecialchars($d['var_mokykla'] ?? ''),
                    $mokytojai,
                    htmlspecialchars($d['Balai'] ?? '-'),
                    htmlspecialchars($d['Vieta'] ?? '-')
                ];
            }
            echo '<div class="page-break">';
            echo generate_printable_table($mokykla, $olimpiada, $headers, $data, ['include_back_button' => false], 'results');
            echo '</div>';
        }
    }
    
    // Automatiškai iššaukia spausdinimo langą
    echo '<script>window.onload = function() { window.print(); }</script>';
    echo '</body></html>';
    exit;
}

// Įtraukiame antraštę naršyklės vaizdui
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
                    <h1 class="h4 mb-0"><i class="fas fa-chart-bar"></i> <?php echo $page_title; ?></h1>
                    <div class="d-flex gap-2">
                        <?php if (!empty($grouped_data)): ?>
                            <a href="?olympiad=<?php echo urlencode($selected_olympiad); ?>&export=csv" class="btn btn-sm btn-success shadow-sm"><i class="fas fa-file-csv"></i> CSV</a>
                            <a href="?olympiad=<?php echo urlencode($selected_olympiad); ?>&export=excel" class="btn btn-sm btn-info text-white shadow-sm"><i class="fas fa-file-excel"></i> Excel</a>
                            <a href="?olympiad=<?php echo urlencode($selected_olympiad); ?>&print=1" class="btn btn-sm btn-light text-dark fw-bold shadow-sm" target="_blank"><i class="fas fa-print"></i> Spausdinti</a>
                        <?php endif; ?>
                        <a href="<?php echo SITE_URL; ?>/modules/reports/index.php" class="btn btn-sm btn-secondary shadow-sm"><i class="fas fa-arrow-left"></i> Grįžti</a>
                    </div>
                </div>
                <div class="card-body bg-light">
                    <form method="get" class="mb-4 bg-white p-3 border rounded shadow-sm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted mb-1">Pasirinkite olimpiadą</label>
                                <select name="olympiad" class="form-select shadow-sm border-primary">
                                    <option value="">-- Visos olimpiados --</option>
                                    <?php foreach ($olympiads as $o): ?>
                                        <option value="<?php echo htmlspecialchars($o['konkurso_pav']); ?>" <?php echo $selected_olympiad === $o['konkurso_pav'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($o['konkurso_pav']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="fas fa-filter"></i> Filtruoti</button>
                            </div>
                        </div>
                    </form>

                    <!-- Duomenys -->
                    <?php if (!empty($grouped_data)): ?>
                        <?php foreach ($grouped_data as $mokykla => $olimpiados): ?>
                            <div class="mb-5 bg-white p-4 border rounded shadow-sm">
                                <h3 class="mb-3 text-primary border-bottom pb-2"><i class="fas fa-school"></i> <?php echo htmlspecialchars($mokykla); ?></h3>
                                <?php foreach ($olimpiados as $olimpiada => $dalyviai): ?>
                                    <h5 class="mb-3 text-dark fw-bold mt-4"><?php echo htmlspecialchars($olimpiada); ?></h5>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover border align-middle table-sm">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Eil.</th>
                                                    <th>Vardas</th>
                                                    <th>Pavardė</th>
                                                    <th>Klasė</th>
                                                    <th>Mokykla</th>
                                                    <th>Mokyt.1</th>
                                                    <th>Mokyt.2</th>
                                                    <th>Balai</th>
                                                    <th>Vieta</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $i = 1 + ($current_page - 1) * $items_per_page; ?>
                                                <?php foreach ($dalyviai as $d): ?>
                                                    <tr>
                                                        <td class="text-muted"><?php echo $i++; ?></td>
                                                        <td><?php echo htmlspecialchars($d['1_vardas'] ?? ''); ?></td>
                                                        <td><strong><?php echo htmlspecialchars($d['1_pavarde'] ?? ''); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($d['1_klase'] ?? '-'); ?></td>
                                                        <td><?php echo htmlspecialchars($d['var_mokykla'] ?? ''); ?></td>
                                                        <td><?php echo htmlspecialchars($d['1_mok'] ?? '-'); ?></td>
                                                        <td><?php echo htmlspecialchars($d['2_mok'] ?? '-'); ?></td>
                                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($d['Balai'] ?? '-'); ?></span></td>
                                                        <td>
                                                            <?php if (!empty($d['Vieta'])): ?>
                                                                <span class="badge bg-primary"><?php echo htmlspecialchars($d['Vieta']); ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>

                        <!-- Kompaktiškas Puslapiavimas -->
                        <?php if ($total_pages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center flex-wrap shadow-sm">
                                    <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?olympiad=<?php echo urlencode($selected_olympiad); ?>&page=<?php echo $current_page - 1; ?>">Ankstesnis</a>
                                    </li>
                                    
                                    <?php 
                                    $window = 2; // Kiek puslapių rodyti aplink dabartinį
                                    for ($p = 1; $p <= $total_pages; $p++): 
                                        // Rodome pirmą, paskutinį ir kelis aplink dabartinį
                                        if ($p == 1 || $p == $total_pages || ($p >= $current_page - $window && $p <= $current_page + $window)):
                                    ?>
                                        <li class="page-item <?php echo $p == $current_page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?olympiad=<?php echo urlencode($selected_olympiad); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                                        </li>
                                    <?php 
                                        // Dedame daugtaškį jei yra "skylė"
                                        elseif ($p == $current_page - $window - 1 || $p == $current_page + $window + 1): 
                                    ?>
                                        <li class="page-item disabled">
                                            <span class="page-link bg-transparent border-0 text-muted px-2">...</span>
                                        </li>
                                    <?php endif; ?>
                                    <?php endfor; ?>

                                    <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?olympiad=<?php echo urlencode($selected_olympiad); ?>&page=<?php echo $current_page + 1; ?>">Kitas</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info border-0 shadow-sm">
                            <i class="fas fa-info-circle me-2"></i> Nėra dalyvių, atitinkančių pasirinktus filtrus.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>