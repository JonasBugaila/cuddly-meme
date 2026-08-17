<?php
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in()) { redirect(SITE_URL . '/modules/auth/login.php'); }

// 1. FILTRŲ REIKŠMIŲ GAVIMAS
$f_olympiad = isset($_GET['olympiad']) ? sanitize_input($_GET['olympiad']) : '';
$f_school   = isset($_GET['school']) ? sanitize_input($_GET['school']) : '';
$f_class    = isset($_GET['class']) ? sanitize_input($_GET['class']) : '';
$f_teacher  = isset($_GET['teacher']) ? sanitize_input($_GET['teacher']) : '';
$f_min_wins = isset($_GET['min_wins']) ? (int)$_GET['min_wins'] : 0;

// Puslapiavimo nustatymai
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
if (!in_array($limit, [10, 25, 50, 100])) $limit = 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Rikiavimo nustatymai
$allowed_sort = ['1_vardas', '1_pavarde', '1_klase', 'konkurso_pav', 'var_mokykla', 'Vieta', 'kitas_etapas'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort) ? $_GET['sort'] : '1_pavarde';
$dir = isset($_GET['dir']) && $_GET['dir'] === 'DESC' ? 'DESC' : 'ASC';

// 2. SQL WHERE SĄLYGŲ STRUKTŪRIZAVIMAS
$where_clauses = ["1=1"];
$params = [];
$types = '';

// Teisių ribojimas paprastiems vartotojams (jie mato tik savo mokyklą)
if (!is_admin()) {
    $user_school = db_get_row(db_query("SELECT var_mokykla FROM vartotojas WHERE vart_id = ?", [$_SESSION['user_id']], 's'))['var_mokykla'] ?? '';
    $where_clauses[] = "d.var_mokykla = ?";
    $params[] = $user_school;
    $types .= 's';
    $f_school = $user_school; // Užrakiname mokyklos filtrą vizualiai
} elseif (!empty($f_school)) {
    $where_clauses[] = "d.var_mokykla = ?";
    $params[] = $f_school;
    $types .= 's';
}

if (!empty($f_olympiad)) {
    $where_clauses[] = "d.konkurso_pav = ?";
    $params[] = $f_olympiad;
    $types .= 's';
}

if (!empty($f_class)) {
    $where_clauses[] = "d.1_klase = ?";
    $params[] = $f_class;
    $types .= 's';
}

if (!empty($f_teacher)) {
    // Ieškome tiek pirmame, tiek antrame mokytojuje
    $where_clauses[] = "(d.1_mok = ? OR d.2_mok = ?)";
    $params[] = $f_teacher;
    $params[] = $f_teacher;
    $types .= 'ss';
}

// Prizinių vietų kiekio filtras (Subužklausa, skaičiuojanti to paties mokinio prizines vietas kitoje lentelėje)
if ($f_min_wins > 0) {
    $where_clauses[] = "(
        SELECT COUNT(*) 
        FROM dalyviai w 
        WHERE w.1_vardas = d.1_vardas 
          AND w.1_pavarde = d.1_pavarde 
          AND w.var_mokykla = d.var_mokykla 
          AND w.Vieta IN ('I', 'II', 'III', 'laureat.')
    ) >= ?";
    $params[] = $f_min_wins;
    $types .= 'i';
}

$where_sql = implode(' AND ', $where_clauses);

// 3. DUOMENŲ BAZĖS UŽKLAUSOS KIEKIUI IR SĄRAŠUI
$total_items = db_get_row(db_query("SELECT COUNT(*) as total FROM dalyviai d WHERE $where_sql", $params, $types))['total'] ?? 0;

// PRIDĖTAS JOIN SU KONKURSAIS: Ištraukiame ar olimpiada yra ŠMSM patvirtinta
$sql = "SELECT d.*, m.pavadinimas AS mokykla_pilna, k.smsm_patvirtintas 
        FROM dalyviai d 
        LEFT JOIN mokyklos m ON d.var_mokykla = m.pavadinimas 
        LEFT JOIN konkursai k ON d.konkurso_pav = k.konkurso_pav
        WHERE $where_sql 
        ORDER BY d.$sort $dir 
        LIMIT ?, ?";
$stmt = db_query($sql, array_merge($params, [$offset, $limit]), $types . 'ii');
$participants = $stmt ? db_get_results($stmt) : [];

// Patikriname, ar bent vienas įrašas sąraše priklauso ŠMSM olimpiadai
$show_smsm_column = false;
foreach ($participants as $p) {
    if (isset($p['smsm_patvirtintas']) && $p['smsm_patvirtintas'] == 1) {
        $show_smsm_column = true;
        break;
    }
}

// 4. DUOMENYS FILTRŲ SĄRAŠAMS GENERUOTI
$olympiads_list = db_get_results(db_query("SELECT DISTINCT konkurso_pav FROM konkursai ORDER BY konkurso_pav ASC"));
$schools_list   = db_get_results(db_query("SELECT DISTINCT pavadinimas FROM mokyklos ORDER BY pavadinimas ASC"));
$classes_list   = db_get_results(db_query("SELECT DISTINCT 1_klase FROM dalyviai WHERE 1_klase IS NOT NULL AND 1_klase != '' ORDER BY CAST(1_klase AS UNSIGNED) ASC, 1_klase ASC"));
$teachers_list  = db_get_results(db_query("SELECT DISTINCT 1_mok FROM dalyviai WHERE 1_mok IS NOT NULL AND 1_mok != '' UNION SELECT DISTINCT 2_mok FROM dalyviai WHERE 2_mok IS NOT NULL AND 2_mok != '' ORDER BY 1_mok ASC"));

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-primary text-white">
        <h1 class="h4 mb-0"><i class="fas fa-users"></i> Visapusiška dalyvių ir rezultatų ataskaita</h1>
    </div>
    <div class="card-body bg-light">
        <form method="get" action="" class="row g-3">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Olimpiada / Konkursas</label>
                <select name="olympiad" class="form-select shadow-sm">
                    <option value="">-- Visos olimpiados --</option>
                    <?php foreach ($olympiads_list as $o): ?>
                        <option value="<?php echo htmlspecialchars($o['konkurso_pav']); ?>" <?php echo $f_olympiad === $o['konkurso_pav'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($o['konkurso_pav']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Mokykla</label>
                <select name="school" class="form-select shadow-sm" <?php echo !is_admin() ? 'disabled' : ''; ?>>
                    <option value="">-- Visos mokyklos --</option>
                    <?php foreach ($schools_list as $s): ?>
                        <option value="<?php echo htmlspecialchars($s['pavadinimas']); ?>" <?php echo $f_school === $s['pavadinimas'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['pavadinimas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Klasė</label>
                <select name="class" class="form-select shadow-sm">
                    <option value="">-- Visos --</option>
                    <?php foreach ($classes_list as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['1_klase']); ?>" <?php echo $f_class === $c['1_klase'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['1_klase']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Mokytojas</label>
                <select name="teacher" class="form-select shadow-sm">
                    <option value="">-- Visi mokytojai --</option>
                    <?php foreach ($teachers_list as $t): ?>
                        <?php if(empty($t['1_mok'])) continue; ?>
                        <option value="<?php echo htmlspecialchars($t['1_mok']); ?>" <?php echo $f_teacher === $t['1_mok'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['1_mok']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Prizinės vietos (Kiekis)</label>
                <select name="min_wins" class="form-select shadow-sm border-warning">
                    <option value="0">-- Visi dalyviai --</option>
                    <option value="1" <?php echo $f_min_wins === 1 ? 'selected' : ''; ?>>1 ir daugiau prizinių vietų</option>
                    <option value="2" <?php echo $f_min_wins === 2 ? 'selected' : ''; ?>>2 ir daugiau prizinių vietų</option>
                    <option value="3" <?php echo $f_min_wins === 3 ? 'selected' : ''; ?>>3 ir daugiau prizinių vietų</option>
                </select>
            </div>

            <div class="col-12 text-end d-flex gap-2 justify-content-end mt-2">
                <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="fas fa-filter me-1"></i> Filtruoti sąrašą</button>
                <a href="participants.php" class="btn btn-secondary px-3 shadow-sm"><i class="fas fa-undo me-1"></i> Išvalyti</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h4 class="mb-0 text-dark fw-bold"><i class="fas fa-list-ul text-primary"></i> Atrinkti sistemos įrašai (<?php echo $total_items; ?>)</h4>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="min-height: 400px;">
            <table class="table table-striped table-hover align-middle mb-0 table-sm">
                <thead class="table-light border-bottom">
                    <tr>
                        <th class="ps-3"><?php echo generate_sortable_header('1_vardas', 'Vardas', $sort, $dir); ?></th>
                        <th><?php echo generate_sortable_header('1_pavarde', 'Pavardė', $sort, $dir); ?></th>
                        <th><?php echo generate_sortable_header('1_klase', 'Klasė', $sort, $dir); ?></th>
                        <th><?php echo generate_sortable_header('konkurso_pav', 'Olimpiada / Konkursas', $sort, $dir); ?></th>
                        <th><?php echo generate_sortable_header('var_mokykla', 'Mokykla', $sort, $dir); ?></th>
                        <th>Mokytojas</th>
                        <th><?php echo generate_sortable_header('Vieta', 'Rezultatas', $sort, $dir); ?></th>
                        
                        <?php if ($show_smsm_column): ?>
                            <th class="pe-3"><?php echo generate_sortable_header('kitas_etapas', 'Siunčiamas į kitą etapą', $sort, $dir); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($participants)): foreach ($participants as $p): ?>
                    <tr>
                        <td class="ps-3"><?php echo htmlspecialchars($p['1_vardas'] ?? ''); ?></td>
                        <td><strong><?php echo htmlspecialchars($p['1_pavarde'] ?? ''); ?></strong></td>
                        <td><span class="badge bg-light text-dark border px-2 py-1"><?php echo htmlspecialchars($p['1_klase'] ?: '-'); ?></span></td>
                        <td><?php echo htmlspecialchars($p['konkurso_pav'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($p['mokykla_pilna'] ?? $p['var_mokykla'] ?? ''); ?></td>
                        <td class="small text-muted">
                            <?php 
                            echo htmlspecialchars($p['1_mok'] ?? '-'); 
                            if (!empty($p['2_mok'])) echo ' / ' . htmlspecialchars($p['2_mok']);
                            ?>
                        </td>
                        <td>
                            <?php if (!empty($p['Vieta']) && in_array($p['Vieta'], ['I', 'II', 'III', 'laureat.'])): ?>
                                <span class="badge bg-warning text-dark px-2 py-1 fw-bold shadow-sm"><i class="fas fa-medal me-1"></i> <?php echo htmlspecialchars($p['Vieta']); ?></span>
                            <?php elseif (!empty($p['Vieta'])): ?>
                                <span class="badge bg-light text-secondary border px-2 py-1"><?php echo htmlspecialchars($p['Vieta']); ?></span>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        
                        <?php if ($show_smsm_column): ?>
                            <td class="pe-3">
                                <?php if (($p['smsm_patvirtintas'] ?? 0) == 1): ?>
                                    <?php if (($p['kitas_etapas'] ?? '') === 'Taip'): ?>
                                        <span class="badge bg-success shadow-sm">Siunčiamas</span>
                                    <?php elseif (($p['kitas_etapas'] ?? '') === 'Ne'): ?>
                                        <span class="badge bg-danger shadow-sm">Nesiunčiamas</span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr>
                            <td colspan="<?php echo $show_smsm_column ? '8' : '7'; ?>" class="text-center text-muted py-5">
                                <i class="fas fa-search-minus fa-3x mb-3 text-light"></i><br>
                                Nėra duomenų, atitinkančių pasirinktus filtravimo kriterijus.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white border-top py-3">
        <?php render_pagination($total_items, $limit, $page); ?>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>