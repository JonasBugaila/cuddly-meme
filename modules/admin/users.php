<?php
/**
 * Vartotojų valdymo puslapis
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in() || !is_admin()) {
    redirect(SITE_URL);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $user_id = sanitize_input($_POST['user_id']);
        if ($user_id !== $_SESSION['user_id']) {
            db_query("DELETE FROM vartotojas WHERE vart_id = ?", [$user_id], 's');
            set_message('Vartotojas sėkmingai pašalintas', 'success');
        }
    }
    redirect(build_url_with_params([]));
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
if (!in_array($limit, [10, 25, 50, 100])) $limit = 10;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$allowed_sort_columns = ['vart_id', 'var_vardas', 'var_pavarde', 'var_mokykla', 'vart_lygis'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort_columns) ? $_GET['sort'] : 'vart_id';
$dir = isset($_GET['dir']) && strtoupper($_GET['dir']) === 'DESC' ? 'DESC' : 'ASC';

$count_stmt = db_query("SELECT COUNT(*) as total FROM vartotojas");
$total_items = $count_stmt ? db_get_row($count_stmt)['total'] : 0;

$sql = "SELECT * FROM vartotojas ORDER BY {$sort} {$dir} LIMIT ?, ?";
$stmt = db_query($sql, [$offset, $limit], 'ii');
$users = $stmt ? db_get_results($stmt) : [];

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
                <h1 class="h4 mb-0"><i class="fas fa-users"></i> Vartotojų valdymas</h1>
                <a href="<?php echo SITE_URL; ?>/modules/admin/user_add.php" class="btn btn-light btn-sm fw-bold"><i class="fas fa-plus"></i> Naujas vartotojas</a>
            </div>
            <div class="card-body">
                <?php display_message(); ?>
                
                <?php if ($total_items > 0): ?>
                    <div class="table-responsive" style="min-height: 400px;">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th><?php echo generate_sortable_header('vart_id', 'Vartotojo ID', $sort, $dir); ?></th>
                                    <th><?php echo generate_sortable_header('var_vardas', 'Vardas', $sort, $dir); ?></th>
                                    <th><?php echo generate_sortable_header('var_pavarde', 'Pavardė', $sort, $dir); ?></th>
                                    <th><?php echo generate_sortable_header('var_mokykla', 'Mokykla', $sort, $dir); ?></th>
                                    <th><?php echo generate_sortable_header('vart_lygis', 'Lygis', $sort, $dir); ?></th>
                                    <th class="text-end">Veiksmai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($user['vart_id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($user['var_vardas']); ?></td>
                                        <td><?php echo htmlspecialchars($user['var_pavarde']); ?></td>
                                        <td><?php echo htmlspecialchars($user['var_mokykla'] ?: 'Nepriskirta'); ?></td>
                                        <td>
                                            <?php if ($user['vart_lygis'] == 'admin'): ?>
                                                <span class="badge bg-danger">Administratorius</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">Mokyklos atstovas</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                <a href="<?php echo SITE_URL; ?>/modules/admin/user_edit.php?id=<?php echo urlencode($user['vart_id']); ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i> Redaguoti
                                                </a>
                                                <?php if ($user['vart_id'] !== $_SESSION['user_id']): ?>
                                                    <form method="post" action="" onsubmit="return confirm('Ar tikrai norite pašalinti šį vartotoją?');" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['vart_id']); ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash"></i> Ištrinti
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php render_pagination($total_items, $limit, $page); ?>
                <?php else: ?>
                    <div class="alert alert-info">Sistemoje nėra registruotų vartotojų.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>