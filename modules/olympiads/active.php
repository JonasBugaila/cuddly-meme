<?php
/**
 * Aktyvios olimpiados - visos funkcijos su tekstiniais mygtukais
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in()) redirect(SITE_URL . '/modules/auth/login.php');

// Gauname tik aktyvias olimpiadas
$sql = "SELECT * FROM konkursai WHERE status = 0 ORDER BY konk_id DESC";
$olympiads = db_get_results(db_query($sql));

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-success text-white">
        <h1 class="h4 mb-0"><i class="fas fa-check-circle"></i> Aktyvios olimpiados</h1>
    </div>
    <div class="card-body">
        <?php display_message(); ?>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>Olimpiados pavadinimas</th>
                        <th>Grupė</th>
                        <th>Veiksmai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($olympiads)): foreach ($olympiads as $oly): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($oly['konkurso_pav']); ?></strong></td>
                        <td><?php echo htmlspecialchars($oly['grupe']); ?></td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                <a href="../registration/register.php?olympiad_id=<?=$oly['konk_id']?>" class="btn btn-sm btn-success">
                                    <i class="fas fa-user-plus"></i> Registruoti
                                </a>
                                <a href="../reports/participant_id.php?olympiad=<?=urlencode($oly['konkurso_pav'])?>" target="_blank" class="btn btn-sm btn-dark">
                                    <i class="fas fa-barcode"></i> Kodai
                                </a>
                                <a href="../reports/signature_sheets.php?print_empty=1&olympiad=<?=urlencode($oly['konkurso_pav'])?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-file-signature"></i> Parašai
                                </a>
                                <a href="../reports/evaluation_sheets.php?print_empty=1&olympiad=<?=urlencode($oly['konkurso_pav'])?>" target="_blank" class="btn btn-sm btn-info">
                                    <i class="fas fa-clipboard-list"></i> Vertinimas
                                </a>
                                <a href="../reports/protocols.php?olympiad=<?=urlencode($oly['konkurso_pav'])?>" target="_blank" class="btn btn-sm btn-secondary">
                                    <i class="fas fa-file-alt"></i> Protokolas
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="3" class="text-center text-muted">Aktyvių olimpiadų nėra.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>