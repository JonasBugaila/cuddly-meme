<?php
/**
 * Ataskaitų modulio pagrindinis puslapis
 * 
 * Šis failas atvaizduoja ataskaitų generavimo galimybes
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname ar vartotojas prisijungęs
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h1>Ataskaitos</h1>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3>Prizininkų ataskaitos</h3>
                            </div>
                            <div class="card-body">
                                <p>Prizininkų sąrašai pagal olimpiadas ir mokyklas.</p>
                                <a href="<?php echo SITE_URL; ?>/modules/reports/winners.php" class="btn btn-primary">Prizininkų ataskaitos</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3>Dalyvių ataskaitos</h3>
                            </div>
                            <div class="card-body">
                                <p>Dalyvių sąrašai pagal olimpiadas ir mokyklas.</p>
                                <a href="<?php echo SITE_URL; ?>/modules/reports/participants.php" class="btn btn-primary">Dalyvių ataskaitos</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3>Statistikos ataskaitos</h3>
                            </div>
                            <div class="card-body">
                                <p>Olimpiadų statistikos ataskaitos.</p>
                                <a href="<?php echo SITE_URL; ?>/modules/reports/statistics.php" class="btn btn-primary">Statistikos ataskaitos</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3>Registracijos lapai</h3>
                            </div>
                            <div class="card-body">
                                <p>Olimpiadų registracijos lapų spausdinimas.</p>
                                <a href="<?php echo SITE_URL; ?>/modules/reports/signature_sheets.php" class="btn btn-primary">Registracijos lapai</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3>Vertinimo lapai</h3>
                            </div>
                            <div class="card-body">
                                <p>Olimpiadų vertinimo lapų spausdinimas.</p>
                                <a href="<?php echo SITE_URL; ?>/modules/reports/evaluation_sheets.php" class="btn btn-primary">Vertinimo lapai</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3>Protokolai</h3>
                            </div>
                            <div class="card-body">
                                <p>Olimpiadų protokolų spausdinimas.</p>
                                <a href="<?php echo SITE_URL; ?>/modules/reports/protocols.php" class="btn btn-primary">Protokolai</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3>Detali ataskaita</h3>
                            </div>
                            <div class="card-body">
                                <p>Visos olimpiados su dalyvių informacija, sugrupuotą pagal mokyklas.</p>
                                <a href="<?php echo SITE_URL; ?>/modules/reports/school_olympiad_report.php" class="btn btn-primary">Detali ataskaita</a>
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