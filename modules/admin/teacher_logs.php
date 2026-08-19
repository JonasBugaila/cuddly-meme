<?php
/**
 * Mokytojų veiklos žurnalo peržiūra (ATSKIRA nuo bendro system_logs).
 * Rodo tik mokytojų (paprastų vartotojų) atliktus veiksmus:
 * dalyvių registravimą ir jų informacijos redagavimą.
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in() || !is_admin()) {
    log_action('Saugumo pažeidimas', 'Bandyta pasiekti mokytojų žurnalą be admin teisių.');
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_message('Netinkamas sesijos žetonas (CSRF).', 'error');
    } else {
        if (db_query("TRUNCATE TABLE teacher_activity_log")) {
            log_action('Žurnalo valymas', 'Administratorius išvalė mokytojų veiklos žurnalą.');
            set_message('Mokytojų žurnalas sėkmingai išvalytas.', 'success');
        } else {
            set_message('Nepavyko išvalyti žurnalo.', 'error');
        }
    }
    redirect(SITE_URL . '/modules/admin/teacher_logs.php');
    exit;
}

// Filtravimas pagal mokyklą (neprivalomas)
$filter_school = isset($_GET['school']) ? sanitize_input($_GET['school']) : '';
$where = '';
$params = [];
$types = '';
if ($filter_school !== '') {
    $where = 'WHERE var_mokykla = ?';
    $params[] = $filter_school;
    $types = 's';
}

$sql = "SELECT * FROM teacher_activity_log $where ORDER BY created_at DESC LIMIT 500";
$logs = db_get_results(db_query($sql, $params, $types));

$schools = db_get_results(db_query("SELECT DISTINCT var_mokykla FROM teacher_activity_log WHERE var_mokykla IS NOT NULL AND var_mokykla != '' ORDER BY var_mokykla ASC"));

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h1 class="h4 mb-0"><i class="fas fa-chalkboard-teacher"></i> Mokytojų veiklos žurnalas</h1>
                <div>
                    <form method="post" action="" onsubmit="return confirm('AR TIKRAI NORITE IŠVALYTI VISĄ MOKYTOJŲ ŽURNALĄ?');" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="clear_logs" value="1">
                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Išvalyti žurnalą</button>
                    </form>
                    <a href="<?php echo SITE_URL; ?>/modules/admin/index.php" class="btn btn-secondary btn-sm ms-2">Grįžti</a>
                </div>
            </div>
            <div class="card-body">
                <?php display_message(); ?>

                <div class="alert alert-secondary">
                    <i class="fas fa-info-circle"></i> Šis žurnalas atskiras nuo bendro sistemos žurnalo ir fiksuoja
                    tik mokytojų (paprastų vartotojų) veiksmus: dalyvių registravimą ir jų informacijos redagavimą.
                    Rodomi paskutiniai 500 įrašų.
                </div>

                <form method="get" action="" class="mb-3">
                    <div class="input-group" style="max-width: 350px;">
                        <select name="school" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Visos mokyklos --</option>
                            <?php foreach ($schools as $sch): ?>
                                <option value="<?php echo htmlspecialchars($sch['var_mokykla']); ?>" <?php echo $filter_school === $sch['var_mokykla'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sch['var_mokykla']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <?php if (!empty($logs)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle" style="font-size: 0.9em;">
                            <thead class="table-light">
                                <tr>
                                    <th>Data ir Laikas</th>
                                    <th>Mokytojas</th>
                                    <th>Mokykla</th>
                                    <th>Veiksmas</th>
                                    <th>Detalės</th>
                                    <th>Dalyvio ID</th>
                                    <th>IP Adresas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td class="text-nowrap"><?php echo htmlspecialchars($log['created_at']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($log['vart_id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($log['var_mokykla'] ?? '-'); ?></td>
                                        <td>
                                            <?php $badge = (stripos($log['action'], 'redaga') !== false) ? 'bg-warning text-dark' : 'bg-success'; ?>
                                            <span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($log['action']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['details']); ?></td>
                                        <td class="text-muted"><?php echo $log['reg_id'] ? htmlspecialchars($log['reg_id']) : '-'; ?></td>
                                        <td class="text-muted"><small><?php echo htmlspecialchars($log['ip_address']); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">Žurnalas šiuo metu tuščias.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>
