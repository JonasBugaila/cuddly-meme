<?php
/**
 * "Mano dalyviai" - mokytojo (paprasto vartotojo) sąrašas savo mokyklos
 * dalyvių, su galimybe redaguoti jų informaciją.
 *
 * Administratoriui rodomi VISI dalyviai (visos mokyklos).
 * Paprastam vartotojui - tik jo paties mokyklos dalyviai.
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
if (!in_array($limit, [10, 25, 50, 100])) $limit = 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['q']) ? sanitize_input($_GET['q']) : '';

$where_clauses = [];
$params = [];
$types = '';

if (is_admin()) {
    $where_clauses[] = '1=1';
} else {
    $own_school_row = db_get_row(db_query("SELECT var_mokykla FROM vartotojas WHERE vart_id = ?", [$_SESSION['user_id']], 's'));
    $own_school = $own_school_row['var_mokykla'] ?? '';

    if (empty($own_school)) {
        set_message('Jūsų mokykla nenurodyta. Susisiekite su administratoriumi.', 'error');
        redirect(SITE_URL);
    }

    $where_clauses[] = 'd.var_mokykla = ?';
    $params[] = $own_school;
    $types .= 's';
}

if ($search !== '') {
    // PATAISYTA: stulpeliai kvalifikuoti "d." prefiksu, nes pridėjus JOIN su konkursai
    // (žr. žemiau), konkurso_pav tampa dviprasmiškas (egzistuoja abiejose lentelėse).
    $where_clauses[] = '(d.1_vardas LIKE ? OR d.1_pavarde LIKE ? OR d.konkurso_pav LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

$where = 'WHERE ' . implode(' AND ', $where_clauses);

// PATAISYTA: JOIN su konkursai pridėtas ir COUNT, ir pagrindinei užklausai vienodai,
// kad "d." aliaso naudojimas $where sąlygoje veiktų abiejose.
$count_sql = "SELECT COUNT(*) as total FROM dalyviai d LEFT JOIN konkursai k ON d.konkurso_pav = k.konkurso_pav $where";
$total_items = db_get_row(db_query($count_sql, $params, $types))['total'] ?? 0;

$sql = "SELECT d.reg_id, d.konkurso_pav, d.var_mokykla, d.1_vardas, d.1_pavarde, d.1_klase, d.1_mok, d.pil_data,
               k.status as olympiad_status
        FROM dalyviai d
        LEFT JOIN konkursai k ON d.konkurso_pav = k.konkurso_pav
        $where
        ORDER BY d.pil_data DESC
        LIMIT ?, ?";
$stmt = db_query($sql, array_merge($params, [$offset, $limit]), $types . 'ii');
$students = db_get_results($stmt);

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
        <h1 class="h4 mb-0"><i class="fas fa-user-graduate"></i> Mano dalyviai</h1>
        <a href="<?php echo SITE_URL; ?>/modules/registration/index.php" class="btn btn-sm btn-light text-primary fw-bold">
            <i class="fas fa-user-plus"></i> Registruoti naują
        </a>
    </div>
    <div class="card-body">
        <?php display_message(); ?>

        <form method="get" action="" class="mb-3">
            <input type="hidden" name="limit" value="<?php echo (int)$limit; ?>">
            <div class="input-group" style="max-width: 400px;">
                <input type="text" name="q" class="form-control" placeholder="Ieškoti pagal vardą, pavardę, olimpiadą..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i></button>
                <?php if ($search !== ''): ?>
                    <a href="?limit=<?php echo (int)$limit; ?>" class="btn btn-outline-secondary">Išvalyti</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Vardas Pavardė</th>
                        <th>Klasė</th>
                        <th>Olimpiada</th>
                        <?php if (is_admin()): ?><th>Mokykla</th><?php endif; ?>
                        <th>Registruota</th>
                        <th class="text-end">Veiksmas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($students)): foreach ($students as $s): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($s['1_vardas'] . ' ' . $s['1_pavarde']); ?></strong></td>
                        <td><?php echo htmlspecialchars($s['1_klase']); ?></td>
                        <td><?php echo htmlspecialchars($s['konkurso_pav']); ?></td>
                        <?php if (is_admin()): ?><td><?php echo htmlspecialchars($s['var_mokykla']); ?></td><?php endif; ?>
                        <td class="text-muted small"><?php echo htmlspecialchars($s['pil_data']); ?></td>
                        <td class="text-end">
                            <?php
                            $is_active_olympiad = (int)($s['olympiad_status'] ?? 1) === 0;
                            // NAUJA: mokytojui (ne admin) redagavimo mygtukas rodomas tik kol
                            // olimpiada aktyvi. Administratoriui - visada.
                            $can_edit = is_admin() || $is_active_olympiad;
                            ?>
                            <?php if ($can_edit): ?>
                                <a href="<?php echo SITE_URL; ?>/modules/registration/edit_participant.php?id=<?php echo (int)$s['reg_id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i> Redaguoti
                                </a>
                            <?php else: ?>
                                <span class="btn btn-sm btn-outline-secondary disabled" title="Olimpiada nebeaktyvi - redaguoti gali tik administratorius">
                                    <i class="fas fa-lock"></i> Redaguoti
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Dalyvių nerasta.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php render_pagination($total_items, $limit, $page); ?>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>
