<?php
/**
 * Aktyvių olimpiadų sąrašas registracijai
 */
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in()) {
    redirect(SITE_URL . '/modules/auth/login.php');
    exit;
}

// 1. Puslapiavimo nustatymai
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// 2. Skaičiuojame tik aktyvias olimpiadas puslapiavimui
$count_query = "SELECT COUNT(*) as total FROM konkursai WHERE status = 0";
$total_items = db_get_row(db_query($count_query))['total'] ?? 0;

// 3. Gauname sąrašą su dalyvių skaičiavimu (Subquery)
$sql = "SELECT k.*, 
        (SELECT COUNT(*) FROM dalyviai d WHERE d.konkurso_pav = k.konkurso_pav) as participant_count 
        FROM konkursai k 
        WHERE k.status = 0 
        ORDER BY k.konk_id DESC 
        LIMIT ?, ?";

$stmt = db_query($sql, [$offset, $limit], 'ii');
$olympiads = db_get_results($stmt);

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-success text-white py-3">
        <h1 class="h4 mb-0"><i class="fas fa-edit"></i> Registracija į aktyvias olimpiadas</h1>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Olimpiados pavadinimas</th>
                        <th>Grupė / Klasės</th>
                        <th class="text-center">Jau užregistruota</th>
                        <th class="text-end pe-3">Veiksmas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($olympiads)): foreach ($olympiads as $oly): ?>
                    <tr>
                        <td class="ps-3 fw-bold"><?php echo htmlspecialchars($oly['konkurso_pav']); ?></td>
                        <td><?php echo htmlspecialchars($oly['grupe'] ?? '-'); ?></td>
                        <td class="text-center">
                            <span class="badge bg-secondary"><?php echo $oly['participant_count']; ?> dalyvių</span>
                        </td>
                        <td class="text-end pe-3">
                            <a href="<?php echo SITE_URL; ?>/modules/registration/register.php?olympiad_id=<?php echo $oly['konk_id']; ?>" class="btn btn-sm btn-success">
                                <i class="fas fa-user-plus"></i> Registruoti dalyvius
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center text-muted py-5">Šiuo metu nėra aktyvių olimpiadų.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="p-3 border-top">
            <?php render_pagination($total_items, $limit, $page); ?>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>