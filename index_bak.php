<?php
/**
 * Pagrindinis puslapis
 * * Šis failas yra pagrindinis sistemos puslapis
 */

// Įtraukiame antraštę (kuri taip pat užkrauna config.php, funkcijas ir t.t.)
require_once dirname(__FILE__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white">
                <h1 class="h3 mb-0"><i class="fas fa-home"></i> Olimpiadų sistema</h1>
            </div>
            <div class="card-body">
                
                <?php if (!is_logged_in()): ?>
                    <div class="alert alert-info">
                        <p class="mb-0">Norėdami naudotis sistema, prašome <a href="<?php echo SITE_URL; ?>/modules/auth/login.php" class="fw-bold">prisijungti</a>.</p>
                    </div>
                <?php else: ?>
                    <div class="row mt-4">
                        
                        <div class="col-md-6 mb-4 mb-md-0">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header bg-light">
                                    <h3 class="h5 mb-0"><i class="fas fa-trophy text-warning"></i> Aktyvios olimpiados</h3>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $sql = "SELECT * FROM konkursai WHERE status = 0 ORDER BY konk_id DESC LIMIT 5";
                                    $stmt = db_query($sql);
                                    $olympiads = $stmt ? db_get_results($stmt) : [];
                                    
                                    if (!empty($olympiads)):
                                    ?>
                                        <ul class="list-group list-group-flush mb-3">
                                            <?php foreach ($olympiads as $olympiad): ?>
                                                <li class="list-group-item px-0">
                                                    <a href="<?php echo SITE_URL; ?>/modules/olympiads/view.php?id=<?php echo $olympiad['konk_id']; ?>" class="text-decoration-none fw-bold">
                                                        <?php echo htmlspecialchars($olympiad['konkurso_pav']); ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <a href="<?php echo SITE_URL; ?>/modules/olympiads/index.php" class="btn btn-outline-primary btn-sm">Visos olimpiados</a>
                                    <?php else: ?>
                                        <p class="text-muted">Šiuo metu nėra aktyvių olimpiadų.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card h-100 border-info shadow-sm" style="min-height: 550px;">
                                <div class="card-header bg-info text-white py-2">
                                    <h3 class="mb-0 text-center" style="font-size: 1rem;">
                                        <i class="fas fa-calendar-alt"></i> Olimpiadų kalendorius
                                    </h3>
                                </div>
                                <div class="card-body p-0" style="height: 410px; overflow: hidden; position: relative;">
                                    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; width: 100%; height: 100%;">
                                        <iframe 
                                            src="<?php echo SITE_URL; ?>/modules/reports/kalendorius.php?compact=1" 
                                            width="100%" 
                                            height="100%" 
                                            frameborder="0"
                                            style="border: 0; display: block;"
                                            title="Olimpiadų kalendorius"
                                            loading="lazy">
                                        </iframe>
                                    </div>
                                </div>
                            </div>
                        </div>
                    
                    </div> <div class="row mt-5">
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <div class="card-header bg-success text-white">
                                    <h3 class="h5 mb-0"><i class="fas fa-chart-pie"></i> Mokyklų statistika</h3>
                                </div>
                                <div class="card-body">

                                    <?php
                                    $user_school = '';
                                    if (!is_admin()) {
                                        $user_stmt = db_query("SELECT var_mokykla FROM vartotojas WHERE vart_id = ?", [$_SESSION['user_id']], 's');
                                        if ($user_stmt) {
                                            $user = db_get_row($user_stmt);
                                            $user_school = $user ? ($user['var_mokykla'] ?? '') : '';
                                        }
                                    }

                                    // === 1. Aktyviausios mokyklos ===
                                    $sql = "SELECT m.pavadinimas AS mokykla, COUNT(d.reg_id) AS cnt 
                                            FROM mokyklos m LEFT JOIN dalyviai d ON m.pavadinimas = d.var_mokykla";
                                    if (!is_admin()) {
                                        $sql .= " WHERE m.pavadinimas = ?";
                                    }
                                    $sql .= " GROUP BY m.pavadinimas ORDER BY cnt DESC LIMIT 10";
                                    $stmt = db_query($sql, !is_admin() ? [$user_school] : [], !is_admin() ? 's' : '');
                                    $active_schools = $stmt ? db_get_results($stmt) : [];

                                    // === 2. Prizininkai ===
                                    $sql = "SELECT m.pavadinimas AS mokykla, 
                                            COUNT(CASE WHEN d.Vieta IN ('I','II','III','laureat.') THEN 1 END) AS cnt 
                                            FROM mokyklos m LEFT JOIN dalyviai d ON m.pavadinimas = d.var_mokykla";
                                    if (!is_admin()) {
                                        $sql .= " WHERE m.pavadinimas = ?";
                                    }
                                    $sql .= " GROUP BY m.pavadinimas ORDER BY cnt DESC LIMIT 10";
                                    $stmt = db_query($sql, !is_admin() ? [$user_school] : [], !is_admin() ? 's' : '');
                                    $prize_schools = $stmt ? db_get_results($stmt) : [];

                                    // Diagramų duomenys
                                    $prize_labels = array_column($prize_schools, 'mokykla');
                                    $prize_data = array_column($prize_schools, 'cnt');
                                    $prize_colors = array_map(function() { return '#' . substr(md5(rand()), 0, 6); }, $prize_data);
                                    ?>

                                    <div class="row mb-4">
                                        <div class="col-md-6 mb-4 mb-md-0">
                                            <div class="card h-100 shadow-sm border-0 bg-light">
                                                <div class="card-header border-0 bg-transparent">
                                                    <h5 class="mb-0 text-center">Aktyviausios mokyklos</h5>
                                                </div>
                                                <div class="card-body p-0" style="position: relative; height: 350px;">
                                                    <?php if (!empty($active_schools) && array_sum(array_column($active_schools, 'cnt')) > 0): ?>
                                                        <?php
                                                        $active_labels = array_column($active_schools, 'mokykla');
                                                        $active_data = array_column($active_schools, 'cnt');
                                                        $active_colors = array_map(function() { return '#' . substr(md5(rand()), 0, 6); }, $active_data);
                                                        ?>
                                                        <canvas class="chart-canvas w-100 h-100"
                                                                data-title="Aktyviausios mokyklos"
                                                                data-label="Dalyviai"
                                                                data-labels='<?php echo json_encode($active_labels); ?>'
                                                                data-data='<?php echo json_encode($active_data); ?>'
                                                                data-colors='<?php echo json_encode($active_colors); ?>'>
                                                        </canvas>
                                                    <?php else: ?>
                                                        <div class="d-flex align-items-center justify-content-center h-100">
                                                            <p class="text-muted mb-0">Nėra pakankamai duomenų</p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card h-100 shadow-sm border-0 bg-light">
                                                <div class="card-header border-0 bg-transparent">
                                                    <h5 class="mb-0 text-center">Mokyklos su prizininkais</h5>
                                                </div>
                                                <div class="card-body p-0" style="position: relative; height: 350px;">
                                                    <?php if (!empty($prize_labels) && array_sum($prize_data) > 0): ?>
                                                        <canvas class="chart-canvas w-100 h-100"
                                                                data-title="Prizininkų lyderiai"
                                                                data-label="Prizininkai"
                                                                data-labels='<?php echo json_encode($prize_labels); ?>'
                                                                data-data='<?php echo json_encode($prize_data); ?>'
                                                                data-colors='<?php echo json_encode($prize_colors); ?>'>
                                                        </canvas>
                                                    <?php else: ?>
                                                        <div class="d-flex align-items-center justify-content-center h-100">
                                                            <p class="text-muted mb-0">Nėra pakankamai duomenų</p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <div class="card shadow-sm">
                                                <div class="card-header bg-light"><h5 class="mb-0">Aktyviausios mokyklos</h5></div>
                                                <div class="card-body p-0">
                                                    <?php if (!empty($active_schools)): ?>
                                                        <table class="table table-sm table-striped mb-0">
                                                            <thead class="table-light"><tr><th class="ps-3">#</th><th>Mokykla</th><th class="pe-3 text-center">Dalyviai</th></tr></thead>
                                                            <tbody>
                                                                <?php foreach ($active_schools as $i => $s): ?>
                                                                <tr>
                                                                    <td class="ps-3"><?php echo $i + 1; ?></td>
                                                                    <td><?php echo htmlspecialchars($s['mokykla']); ?></td>
                                                                    <td class="pe-3 text-center"><span class="badge bg-primary rounded-pill"><?php echo $s['cnt']; ?></span></td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    <?php else: ?>
                                                        <p class="text-muted p-3 mb-0">Nėra duomenų</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-4">
                                            <div class="card shadow-sm">
                                                <div class="card-header bg-light"><h5 class="mb-0">Prizininkų lyderiai</h5></div>
                                                <div class="card-body p-0">
                                                    <?php if (!empty($prize_schools)): ?>
                                                        <table class="table table-sm table-striped mb-0">
                                                            <thead class="table-light"><tr><th class="ps-3">#</th><th>Mokykla</th><th class="pe-3 text-center">Prizininkai</th></tr></thead>
                                                            <tbody>
                                                                <?php foreach ($prize_schools as $i => $s): ?>
                                                                <tr>
                                                                    <td class="ps-3"><?php echo $i + 1; ?></td>
                                                                    <td><?php echo htmlspecialchars($s['mokykla']); ?></td>
                                                                    <td class="pe-3 text-center"><span class="badge bg-warning text-dark rounded-pill"><?php echo $s['cnt']; ?></span></td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    <?php else: ?>
                                                        <p class="text-muted p-3 mb-0">Nėra duomenų</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if (!is_admin()): ?>
                                    <div class="alert alert-info mt-2">
                                        <i class="fas fa-info-circle"></i> <strong>Pastaba:</strong> Šiuo metu matote tik savo mokyklos (<?php echo htmlspecialchars($user_school); ?>) statistiką.
                                    </div>
                                    <?php endif; ?>

                                </div>
                            </div>
                        </div>
                    </div>

                <?php endif; ?> </div>
        </div>
    </div>
</div>

<?php
// Įtraukiame poraštę
require_once dirname(__FILE__) . '/includes/footer.php';
?>