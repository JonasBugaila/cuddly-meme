<?php
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in()) redirect(SITE_URL . '/modules/auth/login.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = db_query("SELECT * FROM konkursai WHERE konk_id = ?", [$id], 'i');
$olympiad = db_get_row($stmt);

if (!$olympiad) {
    set_message('Olimpiada nerasta.', 'error');
    redirect(SITE_URL . '/modules/olympiads/index.php');
}

// Patikriname, ar olimpiada patvirtinta pagal ŠMSM
$is_smsm = (isset($olympiad['smsm_patvirtintas']) && $olympiad['smsm_patvirtintas'] == 1);

// Duomenų gavimo logika (su GROUP BY, kad nesidubliuotų)
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$sort = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : '1_pavarde';
$dir = isset($_GET['dir']) && $_GET['dir'] === 'DESC' ? 'DESC' : 'ASC';

$where = "d.konkurso_pav = ?";
$params = [$olympiad['konkurso_pav']];
$types = 's';

if (!is_admin()) {
    $user_school = db_get_row(db_query("SELECT var_mokykla FROM vartotojas WHERE vart_id = ?", [$_SESSION['user_id']], 's'))['var_mokykla'] ?? '';
    $where .= " AND d.var_mokykla = ?";
    $params[] = $user_school;
    $types .= 's';
}

$count_sql = "SELECT COUNT(DISTINCT d.reg_id) as total FROM dalyviai d LEFT JOIN mokyklos m ON d.var_mokykla = m.pavadinimas WHERE $where";
$total_items = db_get_row(db_query($count_sql, $params, $types))['total'] ?? 0;

$order_sql = ($sort === 'Balai') ? "CAST(d.Balai AS UNSIGNED) $dir" : "$sort $dir";
$sql = "SELECT d.*, MIN(m.pavadinimas) AS mokykla_pilna 
        FROM dalyviai d 
        LEFT JOIN mokyklos m ON d.var_mokykla = m.pavadinimas 
        WHERE $where 
        GROUP BY d.reg_id 
        ORDER BY $order_sql 
        LIMIT ?, ?";

$stmt_d = db_query($sql, array_merge($params, [$offset, $limit]), $types . 'ii');
$participants = db_get_results($stmt_d);

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #print-area, #print-area * {
        visibility: visible;
    }
    #print-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 0;
    }
    .d-print-none {
        display: none !important;
    }
}
</style>

<div class="card shadow-sm border-0 mb-4 d-print-none">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
        <h1 class="h4 mb-0"><i class="fas fa-trophy"></i> <?php echo htmlspecialchars($olympiad['konkurso_pav'] ?? ''); ?></h1>
        <a href="index.php" class="btn btn-sm btn-light text-primary fw-bold"><i class="fas fa-arrow-left"></i> Grįžti į sąrašą</a>
    </div>
    <div class="card-body bg-light">
        <div class="row mb-3">
            <div class="col-md-3">
                <strong><i class="fas fa-users text-muted"></i> Grupė/Klasės:</strong> 
                <?php echo htmlspecialchars($olympiad['grupe'] ?? 'Nenurodyta'); ?>
            </div>
            <div class="col-md-3">
                <strong><i class="fas fa-user-tie text-muted"></i> Atsakingas asmuo:</strong> 
                <?php echo htmlspecialchars($olympiad['atsakingas'] ?? 'Nenurodyta'); ?>
            </div>
            <div class="col-md-3">
                <strong><i class="fas fa-info-circle text-muted"></i> Būsena:</strong> 
                <?php echo (($olympiad['status'] ?? 1) == 0) ? '<span class="badge bg-success">Aktyvi (Vyksta)</span>' : '<span class="badge bg-secondary">Neaktyvi (Baigta)</span>'; ?>
            </div>
            <div class="col-md-3">
                <strong><i class="fas fa-check-circle text-muted"></i> ŠMSM patvirtinimas:</strong> 
                <?php echo $is_smsm ? '<span class="badge bg-info text-dark">Taip</span>' : '<span class="badge bg-light text-muted border">Ne</span>'; ?>
            </div>
        </div>
        
        <hr>
        
        <h5 class="mb-3">Valdymo įrankiai ir Ataskaitos:</h5>
        <div class="d-flex flex-wrap gap-2">
            <?php if (($olympiad['status'] ?? 1) == 0): ?>
                <a href="../registration/register.php?olympiad_id=<?=$olympiad['konk_id']?>" class="btn btn-success"><i class="fas fa-user-plus"></i> Registruoti dalyvį</a>
                <a href="../reports/result_sheet.php?olympiad_id=<?=$olympiad['konk_id']?>" class="btn btn-info text-white"><i class="fas fa-check-square"></i> Suvesti rezultatus</a>
            <?php endif; ?>
            
            <!-- Tiesioginio spausdinimo nuorodos toje pačioje kortelėje (nėra target="_blank") -->
            <a href="../reports/participant_id.php?olympiad=<?=urlencode($olympiad['konkurso_pav'])?>&print=1" class="btn btn-dark"><i class="fas fa-barcode"></i> Kodų lapas</a>
            <a href="../reports/signature_sheets.php?olympiad=<?=urlencode($olympiad['konkurso_pav'])?>&print=1" class="btn btn-warning"><i class="fas fa-file-signature"></i> Parašų lapas</a>
            <a href="../reports/evaluation_sheets.php?olympiad=<?=urlencode($olympiad['konkurso_pav'])?>&print_empty=1" class="btn btn-primary"><i class="fas fa-clipboard-list"></i> Vertinimo lapai</a>
            <a href="../reports/protocols.php?olympiad=<?=urlencode($olympiad['konkurso_pav'])?>&print=1" class="btn btn-secondary"><i class="fas fa-file-alt"></i> Protokolas</a>
            
            <button onclick="window.print();" class="btn btn-outline-dark"><i class="fas fa-print"></i> Spausdinti lentelę</button>
        </div>
    </div>
</div>

<!-- Šis blokas bus vienintelis matomas spausdinant -->
<div id="print-area">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom py-3">
            <h3 class="mb-0 text-dark font-weight-bold">
                <i class="fas fa-list-ol d-print-none"></i> <?php echo htmlspecialchars($olympiad['konkurso_pav'] ?? ''); ?> — Dalyvių sąrašas (<?php echo $total_items; ?>)
            </h3>
        </div>
        <div class="card-body p-0">
            <?php display_message(); ?>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 border">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 d-print-none"><?php echo generate_sortable_header('1_vardas', 'Vardas', $sort, $dir); ?></th>
                            <th class="d-none d-print-table-cell">Vardas</th>
                            
                            <th class="d-print-none"><?php echo generate_sortable_header('1_pavarde', 'Pavardė', $sort, $dir); ?></th>
                            <th class="d-none d-print-table-cell">Pavardė</th>
                            
                            <th class="d-print-none"><?php echo generate_sortable_header('1_klase', 'Klasė', $sort, $dir); ?></th>
                            <th class="d-none d-print-table-cell">Klasė</th>
                            
                            <th>Mokykla</th>
                            <th>Balai</th>
                            <th>Vieta</th>
                            <?php if ($is_smsm): ?>
                                <th class="pe-3">Kitas etapas</th>
                            <?php else: ?>
                                <th class="pe-3"></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($participants)): foreach ($participants as $p): ?>
                        <tr>
                            <td class="ps-3"><?php echo htmlspecialchars($p['1_vardas'] ?? ''); ?></td>
                            <td><strong><?php echo htmlspecialchars($p['1_pavarde'] ?? ''); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['1_klase'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($p['mokykla_pilna'] ?? $p['var_mokykla'] ?? ''); ?></td>
                            <td>
                                <!-- Apsaugota nuo NULL klaidų. Tinka ir tuomet, kai balai lygūs "0" -->
                                <?php if(isset($p['Balai']) && $p['Balai'] !== ''): ?>
                                    <span class="badge bg-secondary fs-6 d-print-none"><?php echo htmlspecialchars($p['Balai']); ?></span>
                                    <span class="d-none d-print-inline"><?php echo htmlspecialchars($p['Balai']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted d-print-none">-</span>
                                    <span class="d-none d-print-inline">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($p['Vieta'])): ?>
                                    <?php $v_color = in_array($p['Vieta'], ['I','II','III','laureat.']) ? 'bg-warning text-dark' : 'bg-primary'; ?>
                                    <span class="badge <?php echo $v_color; ?> fs-6 d-print-none"><?php echo htmlspecialchars($p['Vieta']); ?></span>
                                    <span class="d-none d-print-inline"><?php echo htmlspecialchars($p['Vieta']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted d-print-none">-</span>
                                    <span class="d-none d-print-inline">-</span>
                                <?php endif; ?>
                            </td>
                            
                            <?php if ($is_smsm): ?>
                                <td class="pe-3">
                                    <?php if (isset($p['kitas_etapas']) && $p['kitas_etapas'] == 1): ?>
                                        <span class="badge bg-success d-print-none">Siunčiamas</span>
                                        <span class="d-none d-print-inline">Siunčiamas</span>
                                    <?php elseif (isset($p['kitas_etapas']) && $p['kitas_etapas'] == 2): ?>
                                        <span class="badge bg-danger d-print-none">Nesiunčiamas</span>
                                        <span class="d-none d-print-inline">Nesiunčiamas</span>
                                    <?php else: ?>
                                        <span class="text-muted d-print-none">—</span>
                                        <span class="d-none d-print-inline">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php else: ?>
                                <td class="pe-3"></td>
                            <?php endif; ?>
                            
                        </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-5">Dalyvių šioje olimpiadoje dar nėra.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="mt-3 d-print-none">
    <?php render_pagination($total_items, $limit, $page); ?>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>