<?php
/**
 * Sistemos žurnalo peržiūros puslapis
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname teises (tik administratoriams)
if (!is_logged_in() || !is_admin()) {
    log_action('Saugumo pažeidimas', 'Bandyta pasiekti sistemos žurnalą be admin teisių.');
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL);
}

// Žurnalo valymas (tik super admin veiksmams)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_message('Netinkamas sesijos žetonas (CSRF).', 'error');
    } else {
        $sql = "TRUNCATE TABLE system_logs";
        if (db_query($sql)) {
            log_action('Žurnalo valymas', 'Administratorius išvalė visą sistemos žurnalą.');
            set_message('Sistemos žurnalas sėkmingai išvalytas.', 'success');
        } else {
            set_message('Nepavyko išvalyti žurnalo.', 'error');
        }
    }
    redirect(SITE_URL . '/modules/admin/system_logs.php');
}

// Gauname paskutinius 500 įrašų (kad neperkrautume atminties, jei žurnalas užaugs)
$sql = "SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 500";
$stmt = db_query($sql);
$logs = $stmt ? db_get_results($stmt) : [];

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h1 class="h4 mb-0"><i class="fas fa-clipboard-list"></i> Sistemos žurnalas</h1>
                <div>
                    <form method="post" action="" onsubmit="return confirm('AR TIKRAI NORITE IŠVALYTI VISĄ ŽURNALĄ? Šio veiksmo atšaukti negalima.');" style="display:inline;">
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
                    <i class="fas fa-info-circle"></i> Rodomi paskutiniai 500 sistemos įvykių. Žurnalas padeda sekti sistemos klaidas bei svarbius vartotojų veiksmus.
                </div>

                <?php if (!empty($logs)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle" style="font-size: 0.9em;">
                            <thead class="table-light">
                                <tr>
                                    <th>Data ir Laikas</th>
                                    <th>Vartotojas</th>
                                    <th>Veiksmas</th>
                                    <th>Detalės</th>
                                    <th>IP Adresas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td class="text-nowrap"><?php echo htmlspecialchars($log['created_at']); ?></td>
                                        <td>
                                            <?php if ($log['user_id'] === 'Svečias'): ?>
                                                <span class="badge bg-secondary">Svečias</span>
                                            <?php else: ?>
                                                <strong><?php echo htmlspecialchars($log['user_id']); ?></strong>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $action_class = 'bg-primary';
                                            if (stripos($log['action'], 'klaida') !== false || stripos($log['action'], 'pažeidimas') !== false) { $action_class = 'bg-danger'; }
                                            elseif (stripos($log['action'], 'prisijungimas') !== false) { $action_class = 'bg-success'; }
                                            elseif (stripos($log['action'], 'trynimas') !== false) { $action_class = 'bg-warning text-dark'; }
                                            ?>
                                            <span class="badge <?php echo $action_class; ?>"><?php echo htmlspecialchars($log['action']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['details']); ?></td>
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