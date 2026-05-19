<?php
/**
 * Pagrindinis puslapis
 * 
 * Šis failas yra pagrindinis sistemos puslapis
 */

// Įtraukiame antraštę
require_once dirname(__FILE__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h1>Olimpiadų sistema</h1>
            </div>
            <div class="card-body">
                
                <?php if (!is_logged_in()): ?>
                    <div class="alert alert-info">
                        <p>Norėdami naudotis sistema, prašome <a href="<?php echo SITE_URL; ?>/modules/auth/login.php">prisijungti</a>.</p>
                    </div>
                <?php else: ?>
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3>Aktyvios olimpiados</h3>
                                </div>
                                <div class="card-body">
                                    <?php
                                    // Gauname aktyvias olimpiadas
                                    $sql = "SELECT * FROM konkursai WHERE status = 0 ORDER BY konk_id DESC LIMIT 5";
                                    $stmt = db_query($sql);
                                    $olympiads = db_get_results($stmt);
                                    
                                    if (!empty($olympiads)):
                                    ?>
                                        <ul>
                                            <?php foreach ($olympiads as $olympiad): ?>
                                                <li>
                                                    <a href="<?php echo SITE_URL; ?>/modules/olympiads/view.php?id=<?php echo $olympiad['konk_id']; ?>">
                                                        <?php echo $olympiad['konkurso_pav']; ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <a href="<?php echo SITE_URL; ?>/modules/olympiads/index.php" class="btn btn-primary">Visos olimpiados</a>
                                    <?php else: ?>
                                        <p>Šiuo metu nėra aktyvių olimpiadų.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3>Greitos nuorodos</h3>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="<?php echo SITE_URL; ?>/modules/registration/index.php" class="btn btn-primary mb-2">Dalyvių registracija</a>
                                        <a href="<?php echo SITE_URL; ?>/modules/results/index.php" class="btn btn-primary mb-2">Rezultatų peržiūra</a>
                                        <a href="<?php echo SITE_URL; ?>/modules/reports/index.php" class="btn btn-primary mb-2">Ataskaitos</a>
                                        <?php if (is_admin()): ?>
                                            <a href="<?php echo SITE_URL; ?>/modules/admin/index.php" class="btn btn-secondary">Administravimas</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Įtraukiame poraštę
require_once dirname(__FILE__) . '/includes/footer.php';
?>
