<?php
require_once dirname(__FILE__) . '/includes/header.php';
?>

<div class="container-fluid mt-3">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h1>Olimpiadų sistema</h1>
                </div>
                <div class="card-body">

                    <?php if (!is_logged_in()): ?>
                        <div class="alert alert-info">
                            <p>Prašome <a href="<?= SITE_URL ?>/modules/auth/login.php">prisijungti</a>.</p>
                        </div>
                    <?php else: ?>

                        <!-- GREITOS NUORODOS + KALENDORIAUS MYGTUKAS -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header"><h3>Aktyvios olimpiados</h3></div>
                                    <div class="card-body">
                                        <?php
                                        $stmt = db_query("SELECT * FROM konkursai WHERE status = 0 ORDER BY konk_id DESC LIMIT 5");
                                        $olympiads = db_get_results($stmt);
                                        if (!empty($olympiads)): ?>
                                            <ul class="list-unstyled">
                                                <?php foreach ($olympiads as $o): ?>
                                                    <li><a href="<?= SITE_URL ?>/modules/olympiads/view.php?id=<?= $o['konk_id'] ?>">
                                                        <?= htmlspecialchars($o['konkurso_pav']) ?>
                                                    </a></li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <a href="<?= SITE_URL ?>/modules/olympiads/index.php" class="btn btn-primary btn-sm">Visos</a>
                                        <?php else: ?>
                                            <p class="text-muted">Nėra aktyvių.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header"><h3>Greitos nuorodos</h3></div>
                                    <div class="card-body">
                                        <div class="d-grid gap-2">
                                            <a href="<?= SITE_URL ?>/modules/registration/index.php" class="btn btn-primary">Registracija</a>
                                            <a href="<?= SITE_URL ?>/modules/results/index.php" class="btn btn-primary">Rezultatai</a>
                                            <a href="<?= SITE_URL ?>/modules/reports/index.php" class="btn btn-primary">Ataskaitos</a>
                                            <?php if (is_admin()): ?>
                                                <a href="<?= SITE_URL ?>/modules/admin/index.php" class="btn btn-secondary">Admin</a>
                                            <?php endif; ?>
                                            <button id="show-calendar" class="btn btn-info">Kalendorius</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- KALENDORIAUS KORTELĖ (iš pradžių paslėpta) -->
                        <div id="calendar-box" class="mt-4" style="display: none;">
                            <div class="card border-info shadow">
                                <div class="card-header bg-info text-white d-flex justify-content-between">
                                    <h3 class="mb-0">Olimpiadų kalendorius</h3>
                                    <button id="hide-calendar" class="btn-close btn-close-white"></button>
                                </div>
                                <div class="card-body">
                                    <?php
                                    // Gauname visus konkursus su data
                                    $result = db_query("
                                        SELECT konk_id, konkurso_pav, data, grupe, status 
                                        FROM konkursai 
                                        WHERE data IS NOT NULL AND data != '0000-00-00'
                                        ORDER BY data ASC
                                    ");
                                    ?>
                                    <?php if ($result && db_num_rows($result) > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Data</th>
                                                        <th>Pavadinimas</th>
                                                        <th>Grupė</th>
                                                        <th>Statusas</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php while ($row = db_get_row($result)): ?>
                                                        <?php
                                                        $date = date('Y-m-d', strtotime($row['data']));
                                                        $is_active = $row['status'] == 0;
                                                        ?>
                                                        <tr>
                                                            <td><?= date('M j, Y', strtotime($date)) ?></td>
                                                            <td>
                                                                <a href="<?= SITE_URL ?>/modules/olympiads/view.php?id=<?= $row['konk_id'] ?>">
                                                                    <?= htmlspecialchars($row['konkurso_pav']) ?>
                                                                </a>
                                                            </td>
                                                            <td><?= htmlspecialchars($row['grupe']) ?></td>
                                                            <td>
                                                                <span class="badge <?= $is_active ? 'bg-success' : 'bg-secondary' ?>">
                                                                    <?= $is_active ? 'Aktyvi' : 'Baigta' ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-center text-muted">Nėra konkursų su data.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Mokyklų statistika -->
                        <div class="row mt-5">
                            <div class="col-12">
                                <div class="card shadow">
                                    <div class="card-header bg-success text-white">
                                        <h3 class="mb-0">Mokyklų statistika</h3>
                                    </div>
                                    <div class="card-body">
                                        <!-- Tavo esamas kodas -->
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript – rodyti/slepti kalendorių -->
<script>
document.getElementById('show-calendar').addEventListener('click', function() {
    document.getElementById('calendar-box').style.display = 'block';
    this.style.display = 'none';
});

document.getElementById('hide-calendar').addEventListener('click', function() {
    document.getElementById('calendar-box').style.display = 'none';
    document.getElementById('show-calendar').style.display = 'block';
});
</script>

<?php require_once dirname__FILE__ . '/includes/footer.php'; ?>