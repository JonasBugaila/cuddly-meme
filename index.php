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
                    <!-- VISAS TURINYS TIK PRISIJUNGUSIEMS -->

                    <!-- 1. Aktyvios olimpiados + Kalendorius -->
                    <div class="row mt-4">
                        <!-- Kairė pusė: Aktyvios olimpiados -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3>Aktyvios olimpiados</h3>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $sql = "SELECT * FROM konkursai WHERE status = 0 ORDER BY konk_id DESC LIMIT 5";
                                    $stmt = db_query($sql);
                                    $olympiads = db_get_results($stmt);
                                    
                                    if (!empty($olympiads)):
                                    ?>
                                        <ul class="list-unstyled">
                                            <?php foreach ($olympiads as $olympiad): ?>
                                                <li class="mb-2">
                                                    <a href="<?php echo SITE_URL; ?>/modules/olympiads/view.php?id=<?php echo $olympiad['konk_id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($olympiad['konkurso_pav']); ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <a href="<?php echo SITE_URL; ?>/modules/olympiads/index.php" class="btn btn-primary btn-sm">Visos olimpiados</a>
                                    <?php else: ?>
                                        <p class="text-muted">Šiuo metu nėra aktyvių olimpiadų.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

<!-- Dešinė pusė: KALENDORIUS (padidinta kortelė, viskas matoma, be scroll) -->
<div class="col-md-6">
    <div class="card h-100 border-info" style="min-height: 550px;">
        <div class="card-header bg-info text-white py-2">
            <h3 class="mb-0 text-center" style="font-size: 1rem;">
                Olimpiadų kalendorius
            </h3>
        </div>
        <div class="card-body p-0" style="height: 410px; overflow: hidden; position: relative;">
            <div style="
                position: absolute;
                top: 0; left: 0; right: 0; bottom: 0;
                transform: scale(1); /* Mastelis grąžintas į normalų */
                transform-origin: top left;
                width: 100%;
                height: 100%;
            ">
                <iframe 
                    src="<?php echo SITE_URL; ?>/modules/reports/kalendorius.php?compact=1" 
                    width="100%" 
                    height="410" 
                    frameborder="0"
                    style="
                        border: 0;
                        display: block;
                        width: 100%;
                        height: 100%;
                    "
                    title="Olimpiadų kalendorius"
                    loading="lazy">
                </iframe>
            </div>
        </div>
    </div>
</div>

                    <!-- 2. Mokyklų statistika -->
                    <div class="row mt-5">
                        <div class="col-12">
                            <div class="card shadow">
                                <div class="card-header bg-success text-white">
                                    <h3 class="mb-0">Mokyklų statistika</h3>
                                </div>
                                <div class="card-body">

                                    <?php
                                    // === 1. Aktyviausios mokyklos ===
                                    $sql = "SELECT m.pavadinimas AS mokykla, COUNT(d.reg_id) AS cnt 
                                            FROM mokyklos m LEFT JOIN dalyviai d ON m.pavadinimas = d.var_mokykla";
                                    if (!is_admin()) {
                                        $user_stmt = db_query("SELECT var_mokykla FROM vartotojas WHERE vart_id = ?", [$_SESSION['user_id']], 's');
                                        $user = db_get_row($user_stmt);
                                        $user_school = $user['var_mokykla'] ?? '';
                                        $sql .= " WHERE m.pavadinimas = ?";
                                    }
                                    $sql .= " GROUP BY m.pavadinimas ORDER BY cnt DESC LIMIT 10";
                                    $stmt = db_query($sql, !is_admin() ? [$user_school] : [], !is_admin() ? 's' : '');
                                    $active_schools = $stmt ? db_get_results($stmt) : [];

                                    // === 2. Prizininkai ===
                                    $sql = "SELECT m.pavadinimas AS mokykla, 
                                            COUNT(CASE WHEN d.Vieta IN ('I','II','III','laureat.') THEN 1 END) AS cnt 
                                            FROM mokyklos m LEFT JOIN dalyviai d ON m.pavadinimas = d.var_mokykla";
                                    if (!is_admin()) $sql .= " WHERE m.pavadinimas = ?";
                                    $sql .= " GROUP BY m.pavadinimas ORDER BY cnt DESC LIMIT 10";
                                    $stmt = db_query($sql, !is_admin() ? [$user_school] : [], !is_admin() ? 's' : '');
                                    $prize_schools = $stmt ? db_get_results($stmt) : [];

                                    // Diagramų duomenys
                                    $prize_labels = array_column($prize_schools, 'mokykla');
                                    $prize_data = array_column($prize_schools, 'cnt');
                                    $prize_colors = array_map(function() { return '#' . substr(md5(rand()), 0, 6); }, $prize_data);
                                    ?>

                                    <!-- Diagramos -->
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <div class="card h-100">
                                                <div class="card-header">
                                                    <h5>Aktyviausios mokyklos</h5>
                                                </div>
                                                <div class="card-body p-0" style="position: relative; height: 350px;">
                                                    <?php if (!empty($active_schools)): ?>
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
                                                        <p class="text-muted text-center pt-5">Nėra duomenų</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card h-100">
                                                <div class="card-header">
                                                    <h5>Mokyklos su prizininkais</h5>
                                                </div>
                                                <div class="card-body p-0" style="position: relative; height: 350px;">
                                                    <?php if (!empty($prize_labels)): ?>
                                                        <canvas class="chart-canvas w-100 h-100"
                                                                data-title="Prizininkų lyderiai"
                                                                data-label="Prizininkai"
                                                                data-labels='<?php echo json_encode($prize_labels); ?>'
                                                                data-data='<?php echo json_encode($prize_data); ?>'
                                                                data-colors='<?php echo json_encode($prize_colors); ?>'>
                                                        </canvas>
                                                    <?php else: ?>
                                                        <p class="text-muted text-center pt-5">Nėra duomenų</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Lentelės -->
                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <div class="card">
                                                <div class="card-header"><h5>Aktyviausios mokyklos</h5></div>
                                                <div class="card-body">
                                                    <?php if (!empty($active_schools)): ?>
                                                        <table class="table table-sm table-striped">
                                                            <thead><tr><th>#</th><th>Mokykla</th><th>Dalyviai</th></tr></thead>
                                                            <tbody>
                                                                <?php foreach ($active_schools as $i => $s): ?>
                                                                <tr>
                                                                    <td><?php echo $i + 1; ?></td>
                                                                    <td><?php echo htmlspecialchars($s['mokykla']); ?></td>
                                                                    <td><span class="badge bg-primary"><?php echo $s['cnt']; ?></span></td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    <?php else: ?>
                                                        <p class="text-muted">Nėra duomenų</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-4">
                                            <div class="card">
                                                <div class="card-header"><h5>Prizininkų lyderiai</h5></div>
                                                <div class="card-body">
                                                    <?php if (!empty($prize_schools)): ?>
                                                        <table class="table table-sm table-striped">
                                                            <thead><tr><th>#</th><th>Mokykla</th><th>Prizininkai</th></tr></thead>
                                                            <tbody>
                                                                <?php foreach ($prize_schools as $i => $s): ?>
                                                                <tr>
                                                                    <td><?php echo $i + 1; ?></td>
                                                                    <td><?php echo htmlspecialchars($s['mokykla']); ?></td>
                                                                    <td><span class="badge bg-warning text-dark"><?php echo $s['cnt']; ?></span></td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    <?php else: ?>
                                                        <p class="text-muted">Nėra duomenų</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if (!is_admin()): ?>
                                    <div class="alert alert-info">
                                        <strong>Pastaba:</strong> Matote tik savo mokyklos statistiką.
                                    </div>
                                    <?php endif; ?>

                                </div>
                            </div>
                        </div>
                    </div>

                <?php endif; ?> <!-- UŽDARYMAS: jei neprisijungęs -->

            </div>
        </div>
    </div>
</div>

<?php
// Įtraukiame poraštę
require_once dirname(__FILE__) . '/includes/footer.php';
?>