<?php
/**
 * Olimpiadų modulio pagrindinis puslapis
 * 
 * Šis failas atvaizduoja olimpiadų sąrašą
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname ar vartotojas prisijungęs
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

// Apdorojame statuso keitimą
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status']) && is_admin()) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_message('Netinkamas CSRF žetonas', 'error');
    } else {
        $konk_id = sanitize_input($_POST['konk_id']);
        $new_status = (int)$_POST['new_status'];

        $sql = "UPDATE konkursai SET status = ? WHERE konk_id = ?";
        db_query($sql, [$new_status, $konk_id], 'ii');

        set_message('Statusas sėkmingai pakeistas', 'success');
    }
    redirect(SITE_URL . '/modules/olympiads/index.php');
}

// Gauname olimpiadų sąrašą
$sql = "SELECT * FROM konkursai";
$stmt = db_query($sql);
if (!$stmt) {
    $conn = db_connect();
    die("Klaida vykdant SQL užklausą: " . $conn->error);
}
$olympiads = db_get_results($stmt);
if ($olympiads === false) {
    $conn = db_connect();
    die("Klaida gaunant rezultatus: " . $conn->error);
}

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h1>Olimpiados</h1>
            </div>
            <div class="card-body">
                <?php if (!empty($olympiads)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Pavadinimas</th>
                                    <th>Atsakingas</th>
                                    <th>Grupė</th>
                                    <th>Statusas</th>
                                    <th>Veiksmai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($olympiads as $olympiad): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($olympiad['konkurso_pav']); ?></td>
                                        <td><?php echo htmlspecialchars($olympiad['atsakingas']); ?></td>
                                        <td><?php echo htmlspecialchars($olympiad['grupe']); ?></td>
                                        <td>
                                            <?php if ($olympiad['status'] == 0): ?>
                                                <span class="badge bg-success">Aktyvus</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Neaktyvus</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo SITE_URL; ?>/modules/olympiads/view.php?id=<?php echo $olympiad['konk_id']; ?>" class="btn btn-sm btn-primary">Peržiūrėti</a>
                                            <?php if ($olympiad['status'] == 0): ?>
                                                <a href="<?php echo SITE_URL; ?>/modules/registration/register.php?olympiad_id=<?php echo $olympiad['konk_id']; ?>" class="btn btn-sm btn-success">Registruoti dalyvius</a>
                                                <a href="<?php echo SITE_URL; ?>/modules/reports/result_sheet.php?olympiad_id=<?php echo $olympiad['konk_id']; ?>" class="btn btn-sm btn-success">Vertinti</a>
                                            <?php endif; ?></br>
                                            </br><a href="<?php echo SITE_URL; ?>/modules/reports/signature_sheets.php?print_empty=1&olympiad=<?php echo urlencode($olympiad['konkurso_pav']); ?>" class="btn btn-sm btn-warning">Parašų lapas</a>
                                            <a href="<?php echo SITE_URL; ?>/modules/reports/participant_id.php?olympiad=<?php echo urlencode($olympiad['konkurso_pav']); ?>" class="btn btn-sm btn-dark" target="_blank">Kodų priskyrimas</a>
                                            <a href="<?php echo SITE_URL; ?>/modules/reports/evaluation_sheets.php?print_empty=1&olympiad=<?php echo urlencode($olympiad['konkurso_pav']); ?>" class="btn btn-sm btn-dark" target="_blank">Vertinimo lapai</a>
                                            <?php if (is_admin()): ?>
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="konk_id" value="<?php echo htmlspecialchars($olympiad['konk_id']); ?>">
                                                    <input type="hidden" name="new_status" value="<?php echo $olympiad['status'] == 0 ? 1 : 0; ?>">
                                                    <input type="hidden" name="toggle_status" value="1">
                                                    <button type="submit" class="btn btn-sm <?php echo $olympiad['status'] == 0 ? 'btn-danger' : 'btn-success'; ?>">
                                                        <?php echo $olympiad['status'] == 0 ? 'Išjungti' : 'Įjungti'; ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <p>Nėra olimpiadų.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Įtraukiame poraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
?>