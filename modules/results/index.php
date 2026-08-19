<?php
/**
 * Rezultatų modulio pagrindinis puslapis
 * Šis failas atvaizduoja olimpiadų sąrašą (Lentelės formatu)
 */

// Įtraukiame konfigūracijos failus
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname ar vartotojas prisijungęs
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
    exit;
}

// SAUGU: rezultatai matomi tik administratoriui. Mokytojai (paprasti vartotojai)
// rezultatų peržiūrėti ir keisti negali.
if (!is_admin()) {
    set_message('Neturite teisių peržiūrėti rezultatų.', 'error');
    redirect(SITE_URL);
    exit;
}

// 1. FILTRAI IR PAIEŠKA
$where_clauses = ["1=1"];
$params = [];
$types = '';

$f_search = $_GET['f_search'] ?? '';
$f_grupe = $_GET['f_grupe'] ?? '';

if (!empty($f_search)) {
    $where_clauses[] = "(konkurso_pav LIKE ? OR atsakingas LIKE ?)";
    $params[] = "%$f_search%";
    $params[] = "%$f_search%";
    $types .= 'ss';
}
if (!empty($f_grupe)) {
    $where_clauses[] = "grupe = ?";
    $params[] = $f_grupe;
    $types .= 's';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_clauses);

// 2. PUSLAPIAVIMAS
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10; 
if (!in_array($limit, [10, 25, 50, 100])) $limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Skaičiuojame bendrą olimpiadų kiekį su filtrais
$count_sql = "SELECT COUNT(*) as total FROM konkursai $where_clause";
$total_items = db_get_row(db_query($count_sql, $params, $types))['total'] ?? 0;

// Gauname olimpiadų sąrašą su filtrais ir puslapiavimu
$sql = "SELECT * FROM konkursai $where_clause ORDER BY konk_id DESC LIMIT ?, ?";
$data_params = array_merge($params, [$offset, $limit]);
$data_types = $types . 'ii';

$stmt = db_query($sql, $data_params, $data_types);
$olympiads = $stmt ? db_get_results($stmt) : [];

// Gauname unikalias grupes filtravimo dropdownui
$all_groups = db_get_results(db_query("SELECT DISTINCT grupe FROM konkursai WHERE grupe != '' ORDER BY grupe ASC"));

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="card shadow-sm border-0 mb-4 rounded-3">
    <div class="card-body p-3 bg-light rounded-3">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label small text-muted fw-bold mb-1">Paieška</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" name="f_search" class="form-control border-start-0" placeholder="Olimpiados pavadinimas arba atsakingas..." value="<?php echo htmlspecialchars($f_search); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted fw-bold mb-1">Filtruoti pagal grupę</label>
                <select name="f_grupe" class="form-select">
                    <option value="">Visos grupės</option>
                    <?php foreach ($all_groups as $g): ?>
                        <option value="<?php echo htmlspecialchars($g['grupe']); ?>" <?php echo $f_grupe === $g['grupe'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($g['grupe']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 text-end d-flex gap-2">
                <a href="index.php" class="btn btn-outline-secondary w-50"><i class="fas fa-undo"></i> Išvalyti</a>
                <button type="submit" class="btn btn-primary w-50"><i class="fas fa-sliders-h"></i> Rasti</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-lg border-0 mb-4 rounded-3 overflow-hidden">
    <div class="card-header bg-gradient bg-primary text-white py-3 d-flex justify-content-between align-items-center">
        <h1 class="h4 mb-0 d-flex align-items-center gap-2">
            <i class="fas fa-list text-light"></i> Pasirinkite olimpiadą
        </h1>
        <span class="badge bg-white text-primary px-3 py-2 fs-6 fw-bold shadow-sm">
            Iš viso: <?php echo $total_items; ?>
        </span>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0" style="min-width: 900px;">
                <thead class="table-dark bg-opacity-75">
                    <tr>
                        <th class="ps-4 py-3" style="width: 45%;">Olimpiados pavadinimas</th>
                        <th style="width: 15%;">Grupė</th>
                        <th style="width: 15%;">Atsakingas asmuo</th>
                        <th style="width: 10%;">Data</th>
                        <th style="width: 15%;" class="text-end pe-4">Veiksmai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($olympiads)): ?>
                        <?php foreach ($olympiads as $olympiad): ?>
                        <tr>
                            <td class="ps-4">
                                <span class="fw-bold text-dark d-block mb-1">
                                    <?php echo htmlspecialchars($olympiad['konkurso_pav']); ?>
                                </span>
                                <?php if (isset($olympiad['status']) && $olympiad['status'] == 0): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 small">Aktyvi</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1 small">Neaktyvi</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                    <i class="fas fa-users me-1 small"></i> <?php echo htmlspecialchars($olympiad['grupe']); ?>
                                </span>
                            </td>
                            
                            <td class="text-muted small">
                                <?php echo htmlspecialchars($olympiad['atsakingas']); ?>
                            </td>
                            
                            <td class="text-muted small">
                                <?php echo !empty($olympiad['data']) ? htmlspecialchars(date('Y-m-d', strtotime($olympiad['data']))) : '-'; ?>
                            </td>
                            
                            <td class="text-end pe-4">
                                <a href="<?php echo SITE_URL; ?>/modules/results/view.php?olympiad_id=<?php echo $olympiad['konk_id']; ?>" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-2 shadow-sm px-3 py-2 stretched-link">
                                    Rezultatai <i class="fas fa-arrow-right small"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted fs-5">
                                <i class="fas fa-search d-block mb-2 fa-2x opacity-50"></i> Nėra olimpiadų pagal jūsų paiešką.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="p-3 border-top d-flex flex-column flex-sm-row justify-content-between align-items-center bg-light gap-3">
            <span class="text-muted small order-2 order-sm-1">
                Rodoma nuo <strong><?php echo $offset + 1; ?></strong> iki <strong><?php echo min($offset + $limit, $total_items); ?></strong> įrašo
            </span>
            <div class="order-1 order-sm-2">
                <?php render_pagination($total_items, $limit, $page); ?>
            </div>
        </div>
    </div>
</div>

<style>
tbody tr {
    position: relative;
    transition: background-color 0.2s;
}
tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05) !important;
}
</style>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>