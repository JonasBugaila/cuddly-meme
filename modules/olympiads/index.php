<?php
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in()) { redirect(SITE_URL . '/modules/auth/login.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status']) && is_admin()) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $konk_id = sanitize_input($_POST['konk_id']);
        $new_status = (int)$_POST['new_status'];
        db_query("UPDATE konkursai SET status = ? WHERE konk_id = ?", [$new_status, $konk_id], 'ii');
        set_message('Statusas sėkmingai pakeistas', 'success');
    }
    redirect(build_url_with_params([]));
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
if (!in_array($limit, [10, 25, 50, 100])) $limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$sort = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'konk_id';
$dir = isset($_GET['dir']) && $_GET['dir'] === 'ASC' ? 'ASC' : 'DESC';

$count_stmt = db_query("SELECT COUNT(*) as total FROM konkursai");
$total_items = $count_stmt ? db_get_row($count_stmt)['total'] : 0;

$sql = "SELECT * FROM konkursai ORDER BY $sort $dir LIMIT ?, ?";
$stmt = db_query($sql, [$offset, $limit], 'ii');
$olympiads = $stmt ? db_get_results($stmt) : [];

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
        <h1 class="h4 mb-0"><i class="fas fa-trophy"></i> Olimpiados</h1>
    </div>
    <div class="card-body">
        <?php display_message(); ?>
        <div class="table-responsive" style="min-height: 400px;">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th><?php echo generate_sortable_header('konkurso_pav', 'Pavadinimas', $sort, $dir); ?></th>
                        <th><?php echo generate_sortable_header('atsakingas', 'Atsakingas', $sort, $dir); ?></th>
                        <th><?php echo generate_sortable_header('grupe', 'Grupė', $sort, $dir); ?></th>
                        <th>Statusas</th>
                        <th class="text-end">Veiksmai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($olympiads)): ?>
                        <?php foreach ($olympiads as $oly): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($oly['konkurso_pav'] ?? ''); ?></strong></td>
                                <td><?php echo htmlspecialchars($oly['atsakingas'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($oly['grupe'] ?? ''); ?></td>
                                <td><?php echo (($oly['status'] ?? 1) == 0) ? '<span class="badge bg-success">Aktyvus</span>' : '<span class="badge bg-secondary">Neaktyvus</span>'; ?></td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">Veiksmai</button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow">
                                            <li><a class="dropdown-item" href="view.php?id=<?php echo $oly['konk_id']; ?>"><i class="fas fa-eye me-2 text-primary"></i> Peržiūrėti dalyvius</a></li>
                                            <?php if (($oly['status'] ?? 1) == 0): ?>
                                                <li><a class="dropdown-item" href="../registration/register.php?olympiad_id=<?php echo $oly['konk_id']; ?>"><i class="fas fa-user-plus me-2 text-success"></i> Registruoti dalyvius</a></li>
                                                <li><a class="dropdown-item" href="../reports/result_sheet.php?olympiad_id=<?php echo $oly['konk_id']; ?>"><i class="fas fa-check-square me-2 text-success"></i> Suvesti rezultatus</a></li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item" href="../reports/signature_sheets.php?print_empty=1&olympiad=<?php echo urlencode($oly['konkurso_pav'] ?? ''); ?>"><i class="fas fa-file-signature me-2 text-warning"></i> Parašų lapai</a></li>
                                            <li><a class="dropdown-item" href="../reports/participant_id.php?olympiad=<?php echo urlencode($oly['konkurso_pav'] ?? ''); ?>" target="_blank"><i class="fas fa-barcode me-2 text-dark"></i> Kodų priskyrimas</a></li>
                                            <li><a class="dropdown-item" href="../reports/evaluation_sheets.php?print_empty=1&olympiad=<?php echo urlencode($oly['konkurso_pav'] ?? ''); ?>" target="_blank"><i class="fas fa-clipboard-list me-2 text-dark"></i> Vertinimo lapai</a></li>
                                            <?php if (is_admin()): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="post" class="px-3">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <input type="hidden" name="konk_id" value="<?php echo $oly['konk_id']; ?>">
                                                        <input type="hidden" name="new_status" value="<?php echo (($oly['status'] ?? 1) == 0) ? 1 : 0; ?>">
                                                        <input type="hidden" name="toggle_status" value="1">
                                                        <button type="submit" class="btn btn-sm w-100 <?php echo (($oly['status'] ?? 1) == 0) ? 'btn-danger' : 'btn-success'; ?>">
                                                            <?php echo (($oly['status'] ?? 1) == 0) ? 'Išjungti olimpiadą' : 'Įjungti olimpiadą'; ?>
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center">Olimpiadų nerasta.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php render_pagination($total_items, $limit, $page); ?>
    </div>
</div>
<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>