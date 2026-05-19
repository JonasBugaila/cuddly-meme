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

                    <!-- Mokyklų statistika (įterpta čia) -->
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

                                    // === 3. Chart duomenys ===
                                    $active_labels = $active_data = $active_colors = [];
                                    $prize_labels = $prize_data = $prize_colors = [];
                                    $colors = ['#36A2EB', '#FF6384', '#FFCE56', '#4BC0C0', '#9966FF'];
                                    foreach ($active_schools as $i => $s) {
                                        $active_labels[] = $s['mokykla'];
                                        $active_data[] = (int)$s['cnt'];
                                        $active_colors[] = $colors[$i % count($colors)];
                                    }
                                    foreach ($prize_schools as $i => $s) {
                                        $prize_labels[] = $s['mokykla'];
                                        $prize_data[] = (int)$s['cnt'];
                                        $prize_colors[] = $colors[$i % count($colors)];
                                    }

                                    // === 4. Bendros statistikos ===
                                    if (is_admin()) {
                                        $sql = "SELECT COUNT(DISTINCT m.pavadinimas) as s, COUNT(d.reg_id) as p, 
                                                COUNT(CASE WHEN d.Vieta IN ('I','II','III','laureat.') THEN 1 END) as z 
                                                FROM mokyklos m LEFT JOIN dalyviai d ON m.pavadinimas = d.var_mokykla";
                                        $stmt = db_query($sql);
                                        $r = db_get_row($stmt);
                                        $total_schools = $r['s'] ?? 0;
                                        $total_participants = $r['p'] ?? 0;
                                        $total_prizes = $r['z'] ?? 0;
                                    } else {
                                        $sql = "SELECT COUNT(d.reg_id) as p, 
                                                COUNT(CASE WHEN d.Vieta IN ('I','II','III','laureat.') THEN 1 END) as z 
                                                FROM dalyviai d WHERE d.var_mokykla = ?";
                                        $stmt = db_query($sql, [$user_school], 's');
                                        $r = db_get_row($stmt);
                                        $total_participants = $r['p'] ?? 0;
                                        $total_prizes = $r['z'] ?? 0;
                                        $total_schools = 1;
                                    }
                                    ?>

                                    <!-- Statistikos kortelės -->
                                    <div class="row text-center mb-4">
                                        <div class="col-md-4 mb-3">
                                            <div class="card bg-primary text-white h-100">
                                                <div class="card-body d-flex flex-column justify-content-center">
                                                    <h2 class="mb-0"><?php echo $total_schools; ?></h2>
                                                    <p class="mb-0">Mokyklų</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="card bg-success text-white h-100">
                                                <div class="card-body d-flex flex-column justify-content-center">
                                                    <h2 class="mb-0"><?php echo $total_participants; ?></h2>
                                                    <p class="mb-0">Dalyvių</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="card bg-warning text-white h-100">
                                                <div class="card-body d-flex flex-column justify-content-center">
                                                    <h2 class="mb-0"><?php echo $total_prizes; ?></h2>
                                                    <p class="mb-0">Prizininkų</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Diagramos -->
                                    <div class="row mb-4">
                                        <div class="col-md-6 mb-4">
                                            <div class="card h-100">
                                                <div class="card-header">
                                                    <h5>Aktyviausios mokyklos</h5>
                                                </div>
                                                <div class="card-body p-0" style="position: relative; height: 350px;">
                                                    <?php if (!empty($active_labels)): ?>
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
                                        <div class="col-md-6 mb-4">
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
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Įtraukiame poraštę
require_once dirname(__FILE__) . '/includes/footer.php';
?>