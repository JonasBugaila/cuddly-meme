<?php
/**
 * Duomenų bazės atsarginės kopijos kūrimo puslapis
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in() || !is_admin()) {
    set_message('Prieiga leidžiama tik administratoriams', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

// PAKEISTA: nukreipiame į saugų PHP-based backup generatorių vietoj exec()/mysqldump
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_message('Netinkamas CSRF žetonas', 'error');
        redirect(SITE_URL . '/modules/admin/backup.php');
    }
    redirect(SITE_URL . '/modules/backup/backup_db.php');
    exit;
}

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h1>Duomenų bazės atsarginė kopija</h1>
                <a href="<?php echo SITE_URL; ?>/modules/reports/index.php" class="btn btn-secondary">Grįžti į ataskaitas</a>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <p>Ši funkcija leidžia sukurti ir atsisiųsti visos duomenų bazės atsarginę kopiją. Failas bus automatiškai siunčiamas atsisiuntimui.</p>
                </div>
                
                <form action="<?php echo SITE_URL; ?>/modules/admin/backup.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <button type="submit" name="create_backup" class="btn btn-primary">Sukurti atsarginę kopiją</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
?>