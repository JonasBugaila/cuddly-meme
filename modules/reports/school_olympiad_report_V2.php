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

// === PARAMETRŲ PARUOŠIMAS (bendras visoms užklausoms) ===
$params = [];
$param_types = '';

if (!is_admin()) {
    $params[] = $user_data['var_mokykla'];
    $param_types .= 's';
}
if (!empty($selected_olympiad)) {
    $params[] = $selected_olympiad;
    $param_types .= 's';
}

// === SKAIČIUOJAME BENDRĄ KIEKĮ PUSLAPIAVIMUI ===
$count_sql = "SELECT COUNT(*) as total FROM mokyklos m 
              LEFT JOIN dalyviai d ON m.pavadinimas = d.var_mokykla 
              LEFT JOIN konkursai k ON d.konkurso_pav = k.konkurso_pav";
$where_parts = [];
if (!is_admin()) $where_parts[] = "m.pavadinimas = ?";
if (!empty($selected_olympiad)) $where_parts[] = "d.konkurso_pav = ?";
if (!empty($where_parts)) $count_sql .= " WHERE " . implode(" AND ", $where_parts);

$count_stmt = db_query($count_sql, $params, $param_types);
$total_items = $count_stmt ? (db_get_row($count_stmt)['total'] ?? 0) : 0;
$total_pages = max(1, ceil($total_items / $items_per_page));

// === EKSPORTAS PRIEŠ RODANT PUSLAPĮ ===
if ($export_csv || $export_excel) {
    // Pagrindinė užklausa be LIMIT
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

    $stmt = db_query($export_sql, $params, $param_types);
    $results = $stmt ? db_get_results($stmt) : [];

    if ($export_csv) {
        $filename = str_replace(' ', '_', $page_title) . '_eksportas_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM

        // Antraštė
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

        echo "\xEF\xBB\xBF"; // UTF-8 BOM
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

// === PAGRINDINĖ UŽKLAUSA (su LIMIT tik naršyklėje) ===
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

// Pridedame LIMIT tik jei ne spausdinimas ir ne eksportas
if (!$print_mode && !$export_csv && !$export_excel) {
    $offset = ($current_page - 1) * $items_per_page;
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $items_per_page;
    $params[] = $offset;
    $param_types .= 'ii';
}

$stmt = db_query($sql, $params, $param_types);
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

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h1><?php echo $page_title; ?></h1>
                    <div>
                        <?php if (!empty($grouped_data)): ?>
                            <a href="?olympiad=<?php echo urlencode($selected_olympiad); ?>&export=csv" class="btn btn-success">CSV</a>
                            <a href="?olympiad=<?php echo urlencode($selected_olympiad); ?>&export=excel" class="btn btn-info text-white">Excel</a>
                            <a href="?olympiad=<?php echo urlencode($selected_olympiad); ?>&print=1" class="btn btn-primary" target="_blank">Spausdinti</a>
                        <?php endif; ?>
                        <a href="<?php echo SITE_URL; ?>/modules/reports/index.php" class="btn btn-secondary">Grįžti</a>
                    </div>
                </div>
                <div class="card-body">
                    <form method="get" class="mb-4">
                        <div class="row">
                            <div class="col-md-6">
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
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>