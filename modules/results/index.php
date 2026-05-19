<?php
/**
 * Rezultatų modulio pagrindinis puslapis
 * 
 * Šis failas atvaizduoja olimpiadų sąrašą, kurių rezultatus galima peržiūrėti
 */

// Įtraukiame konfigūracijos failus
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname ar vartotojas prisijungęs
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

// Gauname olimpiadų sąrašą
$sql = "SELECT * FROM konkursai ORDER BY konk_id DESC";
$stmt = db_query($sql);
$olympiads = db_get_results($stmt);

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h1>Rezultatai</h1>
            </div>
            <div class="card-body">
                <p>Pasirinkite olimpiadą, kurios rezultatus norite peržiūrėti:</p>
                
                <?php if (!empty($olympiads)): ?>
                    <div class="list-group">
                        <?php foreach ($olympiads as $olympiad): ?>
                            <div class="card">
                                <a href="<?php echo SITE_URL; ?>/modules/results/view.php?olympiad_id=<?php echo $olympiad['konk_id']; ?>" class="card-link">
                                    <div class="card-header">
                                        <h5 class="mb-1"><?php echo $olympiad['konkurso_pav']; ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-1">Atsakingas: <?php echo $olympiad['atsakingas']; ?></p>
                                        <small>Grupė: <?php echo $olympiad['grupe']; ?></small>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <p>Nėra olimpiadų, kurių rezultatus galima peržiūrėti.</p>
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