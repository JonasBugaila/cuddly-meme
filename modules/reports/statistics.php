<?php
/**
 * Statistikos modulis: Mokyklų apžvalga, statistika ir ataskaitų spausdinimas
 */
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in()) {
    redirect(SITE_URL . '/modules/auth/login.php');
    exit;
}

// Puslapiavimo nustatymai
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$allowed_sort = ['pavadinimas', 'miestas'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort, true) ? $_GET['sort'] : 'pavadinimas';
$dir  = (isset($_GET['dir']) && strtoupper($_GET['dir']) === 'DESC') ? 'DESC' : 'ASC';

// Bendras mokyklų skaičius puslapiavimui
$count_result = db_get_row(db_query("SELECT COUNT(*) as total FROM mokyklos"));
$total_items = $count_result['total'] ?? 0;

// Mokyklų sąrašas
$sql = "SELECT * FROM mokyklos ORDER BY '$sort' $dir LIMIT ?, ?";
$stmt = db_query($sql, [$offset, $limit], 'ii');
$results = db_get_results($stmt);

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
        <h1 class="h4 mb-0"><i class="fas fa-chart-bar"></i> Mokyklų statistika</h1>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="min-height: 400px;">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3"><?php echo generate_sortable_header('pavadinimas', 'Mokykla', $sort, $dir); ?></th>
                        <th><?php echo generate_sortable_header('miestas', 'Miestas', $sort, $dir); ?></th>
                        <th class="text-end pe-3">Dalyvių skaičius</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($results)): foreach ($results as $row): 
                        $mok = $row['pavadinimas'];
                        $stats = db_get_row(db_query("
                            SELECT 
                                COUNT(*) as total_dalyviai,
                                COUNT(DISTINCT konkurso_pav) as total_olimpiados,
                                COUNT(DISTINCT 1_klase) as total_klases,
                                SUM(CASE WHEN Vieta IN ('I', 'II', 'III') THEN 1 ELSE 0 END) as total_prizininkai
                            FROM dalyviai WHERE var_mokykla = ?", [$mok], 's'));
                        
                        $unique_id = 'stats_' . md5($mok);
                    ?>
                    <tr class="cursor-pointer" data-bs-toggle="collapse" data-bs-target="#<?php echo $unique_id; ?>">
                        <td class="ps-3 fw-bold"><i class="fas fa-chevron-down text-muted me-2"></i> <?php echo htmlspecialchars($mok); ?></td>
                        <td><?php echo htmlspecialchars($row['miestas'] ?? '-'); ?></td>
                        <td class="text-end pe-3">
                            <span class="badge bg-info text-dark fs-6"><?php echo $stats['total_dalyviai'] ?? 0; ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3" class="p-0 border-0">
                            <div class="collapse" id="<?php echo $unique_id; ?>">
                                <div class="card card-body bg-light border-0 rounded-0 p-4" id="print_area_<?php echo $unique_id; ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0 text-primary fw-bold"><?php echo htmlspecialchars($mok); ?> – Išsami ataskaita</h5>
                                        <button onclick="printReport('print_area_<?php echo $unique_id; ?>')" class="btn btn-sm btn-outline-primary d-print-none">
                                            <i class="fas fa-print"></i> Spausdinti ataskaitą
                                        </button>
                                    </div>
                                    
                                    <div class="row text-center g-3">
                                        <div class="col-md-3">
                                            <div class="p-3 bg-white shadow-sm rounded border">
                                                <h6 class="text-muted text-uppercase small">Olimpiados</h6>
                                                <h4 class="text-primary mb-0"><?php echo $stats['total_olimpiados'] ?? 0; ?></h4>
                                                <small class="text-muted d-block mt-1">Dalyvauta konkursuose</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="p-3 bg-white shadow-sm rounded border">
                                                <h6 class="text-muted text-uppercase small">Dalyviai</h6>
                                                <h4 class="text-info mb-0"><?php echo $stats['total_dalyviai'] ?? 0; ?></h4>
                                                <small class="text-muted d-block mt-1">Registruotų moksleivių</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="p-3 bg-white shadow-sm rounded border">
                                                <h6 class="text-muted text-uppercase small">Prizininkai</h6>
                                                <h4 class="text-success mb-0"><?php echo $stats['total_prizininkai'] ?? 0; ?></h4>
                                                <small class="text-muted d-block mt-1">I–III vietų laimėtojai</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="p-3 bg-white shadow-sm rounded border">
                                                <h6 class="text-muted text-uppercase small">Klasės</h6>
                                                <h4 class="text-warning mb-0"><?php echo $stats['total_klases'] ?? 0; ?></h4>
                                                <small class="text-muted d-block mt-1">Skirtingų klasių grupės</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="3" class="text-center text-muted py-5">Duomenų nerasta.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="p-3 border-top">
            <?php render_pagination($total_items, $limit, $page); ?>
        </div>
    </div>
</div>

<script>
function printReport(divId) {
    // 1. Paimame turinį, kurį norime spausdinti
    var content = document.getElementById(divId).innerHTML;
    
    // 2. Atidarome naują, tuščią langą
    var win = window.open('', '_blank', 'height=600,width=800');
    
    // 3. Suformuojame visą HTML dokumentą naujame lange
    win.document.write('<html><head><title>Mokyklos ataskaita</title>');
    // Įkeliame Bootstrap stilius, kad ataskaita išliktų graži
    win.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">');
    // Pridedame stilių, kad spaudinys būtų švarus
    win.document.write('<style>body { padding: 20px; background: white; } .d-print-none { display: none !important; }</style>');
    win.document.write('</head><body>');
    win.document.write(content); // Įrašome tik kortelės turinį
    win.document.write('</body></html>');
    
    // 4. Uždarome dokumento srautą ir spausdiname
    win.document.close(); 
    win.focus();
    
    // Trumpas uždelsimas, kad stiliai spėtų užsikrauti
    setTimeout(function() {
        win.print();
        win.close(); // Uždaro langą po spausdinimo
    }, 250);
}
</script>

<style>
    .cursor-pointer { cursor: pointer; }
    .cursor-pointer:hover { background-color: #f8f9fa; }
    @media print { .d-print-none { display: none !important; } }
</style>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>