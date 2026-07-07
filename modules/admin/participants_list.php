<?php
/**
 * Visų dalyvių sąrašas su interaktyviu filtravimu
 */
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in() || !is_admin()) {
    redirect(SITE_URL . '/index.php');
    exit;
}

// 1. Filtravimo logikos apdorojimas
$where_clauses = ["1=1"];
$params = [];
$types = "";

// Filtravimo kintamieji
$f_klase = $_GET['f_klase'] ?? '';
$f_mokykla = $_GET['f_mokykla'] ?? '';
$f_olympiad = $_GET['f_olympiad'] ?? '';
$f_vardas = $_GET['f_vardas'] ?? '';
$f_pavarde = $_GET['f_pavarde'] ?? '';

if ($f_klase !== '') { $where_clauses[] = "1_klase = ?"; $params[] = $f_klase; $types .= "s"; }
if ($f_mokykla !== '') { $where_clauses[] = "var_mokykla = ?"; $params[] = $f_mokykla; $types .= "s"; }
if ($f_olympiad !== '') { $where_clauses[] = "konkurso_pav = ?"; $params[] = $f_olympiad; $types .= "s"; }
if ($f_vardas !== '') { $where_clauses[] = "1_vardas LIKE ?"; $params[] = "%$f_vardas%"; $types .= "s"; }
if ($f_pavarde !== '') { $where_clauses[] = "1_pavarde LIKE ?"; $params[] = "%$f_pavarde%"; $types .= "s"; }

$where_sql = implode(" AND ", $where_clauses);

// 2. Puslapiavimas ir rikiavimas
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$allowed_sort_cols = ['1_vardas', '1_pavarde', '1_klase', 'var_mokykla', 'konkurso_pav', '1_mok', 'reg_id'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort_cols) ? $_GET['sort'] : 'reg_id';
$dir = isset($_GET['dir']) && strtoupper($_GET['dir']) === 'ASC' ? 'ASC' : 'DESC';

// Skaičiuojame įrašus su filtrais
$count_query = "SELECT COUNT(*) as total FROM dalyviai WHERE $where_sql";
$total_items = db_get_row(db_query($count_query, $params, $types))['total'] ?? 0;

// Gauname duomenis su filtrais
$sql = "SELECT * FROM dalyviai WHERE $where_sql ORDER BY $sort $dir LIMIT ?, ?";
$stmt = db_query($sql, array_merge($params, [$offset, $limit]), $types . "ii");
$participants = db_get_results($stmt);

// 3. Duomenys filtrams (Dropdowns)
$all_klases = db_get_results(db_query("SELECT DISTINCT 1_klase FROM dalyviai WHERE 1_klase != '' ORDER BY 1_klase ASC"));
$all_mokyklos = db_get_results(db_query("SELECT DISTINCT var_mokykla FROM dalyviai WHERE var_mokykla != '' ORDER BY var_mokykla ASC"));
$all_olympiads = db_get_results(db_query("SELECT DISTINCT konkurso_pav FROM dalyviai WHERE konkurso_pav != '' ORDER BY konkurso_pav ASC"));

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-light border-0 py-3">
        <h5 class="mb-0"><i class="fas fa-filter text-primary"></i> Filtruoti dalyvius</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <input type="text" name="f_vardas" class="form-control" placeholder="Vardas" value="<?php echo htmlspecialchars($f_vardas); ?>">
            </div>
            <div class="col-md-2">
                <input type="text" name="f_pavarde" class="form-control" placeholder="Pavardė" value="<?php echo htmlspecialchars($f_pavarde); ?>">
            </div>
            <div class="col-md-2">
                <select name="f_klase" class="form-select">
                    <option value="">Klasė (visos)</option>
                    <?php foreach($all_klases as $r): ?><option value="<?php echo $r['1_klase']; ?>" <?php echo $f_klase == $r['1_klase'] ? 'selected' : ''; ?>><?php echo $r['1_klase']; ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="f_mokykla" class="form-select">
                    <option value="">Mokykla (visos)</option>
                    <?php foreach($all_mokyklos as $r): ?><option value="<?php echo $r['var_mokykla']; ?>" <?php echo $f_mokykla == $r['var_mokykla'] ? 'selected' : ''; ?>><?php echo $r['var_mokykla']; ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="f_olympiad" class="form-select">
                    <option value="">Olimpiada (visos)</option>
                    <?php foreach($all_olympiads as $r): ?><option value="<?php echo $r['konkurso_pav']; ?>" <?php echo $f_olympiad == $r['konkurso_pav'] ? 'selected' : ''; ?>><?php echo $r['konkurso_pav']; ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 text-end">
                <a href="participants_list.php" class="btn btn-secondary me-2">Išvalyti</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtruoti</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-lg border-0 mb-4 rounded-3 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0" style="min-width: 1050px;">
            <thead class="table-dark">
                <tr>
                    <th class="ps-4">Vardas</th>
                    <th>Pavardė</th>
                    <th class="text-center">Klasė</th>
                    <th>Mokykla</th>
                    <th>Olimpiada</th>
                    <th>Mokytojas</th>
                    <th class="text-end pe-4">Veiksmai</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($participants)): foreach ($participants as $p): ?>
                <tr>
                    <td class="ps-4"><?php echo htmlspecialchars($p['1_vardas'] ?? ''); ?></td>
                    <td class="fw-bold"><?php echo htmlspecialchars($p['1_pavarde'] ?? ''); ?></td>
                    <td class="text-center"><span class="badge bg-light text-dark"><?php echo htmlspecialchars($p['1_klase'] ?? ''); ?></span></td>
                    <td class="text-truncate" style="max-width: 220px;" title="<?php echo htmlspecialchars($p['var_mokykla'] ?? ''); ?>"><?php echo htmlspecialchars($p['var_mokykla'] ?? ''); ?></td>
                    <td class="text-truncate" style="max-width: 220px;" title="<?php echo htmlspecialchars($p['konkurso_pav'] ?? ''); ?>"><span class="badge bg-primary bg-opacity-10 text-primary"><?php echo htmlspecialchars($p['konkurso_pav'] ?? ''); ?></span></td>
                    <td><?php echo htmlspecialchars($p['1_mok'] ?? ''); ?></td>
                    <td class="text-end pe-4">
                        <a href="<?php echo SITE_URL; ?>/modules/admin/participant_edit.php?id=<?php echo $p['reg_id']; ?>" class="btn btn-sm btn-primary">Redaguoti</a>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">Nėra įrašų pagal pasirinktus kriterijus.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div class="p-3 border-top bg-light text-center">
        <?php 
            // SVARBU: Kad puslapiavimas veiktų su filtrais, turite įsitikinti, kad render_pagination 
            // įtraukia $_GET parametrus. Jei jūsų funkcija tai daro automatiškai - viskas gerai.
            render_pagination($total_items, $limit, $page); 
        ?>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>