<?php
/**
 * Administravimo modulio pagrindinis puslapis
 * 
 * Šis failas atvaizduoja administravimo modulio pagrindinį puslapį
 */

// Įtraukiame konfigūracijos failus
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname ar vartotojas prisijungęs ir turi administratoriaus teises
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

if (!is_admin()) {
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL);
}

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h1>Administravimas</h1>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card admin-card mb-4">
                            <div class="card-header">
                                <h3>Vartotojų valdymas</h3>
                            </div>
                            <div class="card-body">
                                <p>Vartotojų kūrimas, redagavimas ir šalinimas.</p>
                                <a href="<?php echo SITE_URL; ?>/modules/admin/users.php" class="btn btn-primary">Vartotojų valdymas</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card admin-card mb-4">
                            <div class="card-header">
                                <h3>Olimpiadų valdymas</h3>
                            </div>
                            <div class="card-body">
                                <p>Olimpiadų kūrimas, redagavimas ir šalinimas.</p>
                                <a href="<?php echo SITE_URL; ?>/modules/admin/olympiads.php" class="btn btn-primary">Olimpiadų valdymas</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card admin-card mb-4">
                            <div class="card-header">
                                <h3>Mokyklų valdymas</h3>
                            </div>
                            <div class="card-body">
                                <p>Mokyklų kūrimas, redagavimas ir šalinimas.</p>
                                <a href="<?php echo SITE_URL; ?>/modules/admin/schools.php" class="btn btn-primary">Mokyklų valdymas</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card admin-card mb-4">
                            <div class="card-header">
                                <h3>Dalyvių valdymas</h3>
                            </div>
                            <div class="card-body">
                                <p>Dalyvių informacijos peržiūra ir redagavimas.</p>
                                <a href="<?php echo SITE_URL; ?>/modules/admin/participants_list.php" class="btn btn-primary">Dalyvių valdymas</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card admin-card mb-4">
                            <div class="card-header">
                                <h3>Atsarginės kopijos</h3>
                            </div>
                            <div class="card-body">
                                <p>Duomenų bazės atsarginių kopijų kūrimas ir atkūrimas.</p>
                                <?php if (is_admin()): ?>
    <a href="<?php echo SITE_URL; ?>/modules/backup/backup_db.php" class="btn btn-warning">
        Atsarginė kopija (.txt)
    </a>
<?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card admin-card mb-4">
                            <div class="card-header">
                                <h3>Sistemos žurnalas</h3>
                            </div>
                            <div class="card-body">
                                <p>Sistemos įvykių žurnalo peržiūra.</p>
                                <a href="<?php echo SITE_URL; ?>/modules/admin/logs.php" class="btn btn-primary">Sistemos žurnalas</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Įtraukiame poraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
?>