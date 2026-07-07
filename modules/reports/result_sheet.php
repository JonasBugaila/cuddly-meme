<?php
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in()) {
    set_message('Turite prisijungti', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
} elseif (!is_admin()) {
    set_message('Neturite teisių', 'error');
    redirect(SITE_URL);
}

$olympiad_id = isset($_GET['olympiad_id']) ? (int)$_GET['olympiad_id'] : 0;
$stmt = db_query("SELECT * FROM konkursai WHERE konk_id = ?", [$olympiad_id]);
$olympiad = db_get_row($stmt);

if (empty($olympiad)) {
    set_message('Olimpiada nerasta', 'error');
    redirect(SITE_URL . '/modules/olympiads/index.php');
}

$olympiad_name = $olympiad['konkurso_pav'];

// ── Balų išsaugojimas ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_results'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_message('Saugumo klaida.', 'error');
        redirect(SITE_URL . '/modules/reports/result_sheet.php?olympiad_id=' . $olympiad_id);
    }
    foreach ($_POST['participant'] as $reg_id => $data) {
        $balai = ($data['balai'] !== '') ? (int)$data['balai'] : null;
        $vieta = sanitize_input($data['vieta'] ?? '');
        db_query("UPDATE dalyviai SET Balai = ?, Vieta = ? WHERE reg_id = ?", [$balai, $vieta, $reg_id]);
    }
    set_message('Rezultatai išsaugoti.', 'success');
    redirect(SITE_URL . '/modules/reports/result_sheet.php?olympiad_id=' . $olympiad_id);
}

// ── Automatinis skaičiavimas pagal LINEŠA metodiką ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recalculate_ranks'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_message('Saugumo klaida.', 'error');
        redirect(SITE_URL . '/modules/reports/result_sheet.php?olympiad_id=' . $olympiad_id);
    }

    $method     = sanitize_input($_POST['method'] ?? 'percent');
    $max_score  = (int)($_POST['max_score'] ?? 40);

    $stmt = db_query(
        "SELECT reg_id, CAST(Balai AS UNSIGNED) as Balai FROM dalyviai
         WHERE konkurso_pav = ? AND Balai IS NOT NULL AND Balai != ''
         ORDER BY CAST(Balai AS UNSIGNED) DESC",
        [$olympiad_name]
    );
    $rows  = db_get_results($stmt);
    $total = count($rows);

    if ($total === 0) {
        set_message('Nėra dalyvių su balais.', 'warning');
        redirect(SITE_URL . '/modules/reports/result_sheet.php?olympiad_id=' . $olympiad_id);
    }

    // Pirmiausia nuvalome visas esamas vietas
    db_query("UPDATE dalyviai SET Vieta = '' WHERE konkurso_pav = ?", [$olympiad_name]);

    // ── 1 METODAS: Absoliučios procentų kartelės (pagal max balų) ─────────
    // Oficialus LINEŠA metodas: I ≥ X% max, II ≥ Y% max ir t.t.
    // Lygūs balai → ta pati vieta, sekanti vieta praleidžiama
    if ($method === 'absolute') {

        $pct_I       = (float)($_POST['abs_pct_I']       ?? 90);
        $pct_II      = (float)($_POST['abs_pct_II']      ?? 75);
        $pct_III     = (float)($_POST['abs_pct_III']     ?? 60);
        $pct_laureat = (float)($_POST['abs_pct_laureat'] ?? 45);

        if ($max_score <= 0) {
            set_message('Maksimalūs balai turi būti didesni už 0.', 'error');
            redirect(SITE_URL . '/modules/reports/result_sheet.php?olympiad_id=' . $olympiad_id);
        }

        $min_I       = round($max_score * $pct_I       / 100, 2);
        $min_II      = round($max_score * $pct_II      / 100, 2);
        $min_III     = round($max_score * $pct_III     / 100, 2);
        $min_laureat = round($max_score * $pct_laureat / 100, 2);

        $assigned = 0;
        foreach ($rows as $row) {
            $b = $row['Balai'];
            if      ($b >= $min_I)       $vieta = 'I';
            elseif  ($b >= $min_II)      $vieta = 'II';
            elseif  ($b >= $min_III)     $vieta = 'III';
            elseif  ($b >= $min_laureat) $vieta = 'laureat.';
            else                         $vieta = '';

            if ($vieta !== '') {
                db_query("UPDATE dalyviai SET Vieta = ? WHERE reg_id = ?", [$vieta, $row['reg_id']]);
                $assigned++;
            }
        }

        set_message(
            "Vietos priskirtos $assigned iš $total dalyvių (metodas: absoliučios kartelės — " .
            "I≥{$pct_I}%, II≥{$pct_II}%, III≥{$pct_III}%, Laur.≥{$pct_laureat}% iš $max_score bal.).",
            'success'
        );

    // ── 2 METODAS: Procentinė dalis nuo dalyvių skaičiaus ─────────────────
    // Lygūs balai dalijasi tą pačią vietą, sekanti vieta praleidžiama
    } elseif ($method === 'percent') {

        $pct_I       = (float)($_POST['pct_I']       ?? 10);
        $pct_II      = (float)($_POST['pct_II']      ?? 25);
        $pct_III     = (float)($_POST['pct_III']     ?? 40);
        $pct_laureat = (float)($_POST['pct_laureat'] ?? 60);

        // Sugrupuojame pagal lygius balus
        // Kiekviena balo reikšmė → vienas lygis
        $score_groups = [];
        foreach ($rows as $row) {
            $score_groups[$row['Balai']][] = $row['reg_id'];
        }
        // $score_groups surikiuotas DESC pagal balus (nes $rows jau DESC)
        // Apskaičiuojame vietą kiekvienai grupei
        $rank_pos = 0; // pozicija (ne vieta) — skaičiuojama žmonėmis

        foreach ($score_groups as $score => $ids) {
            $group_size  = count($ids);
            // Vidutinė pozicija šiai grupei (%)
            // Naudojame pirmosios pozicijos procentą (LINEŠA: kiek % pirmauja)
            $position_pct = ($rank_pos / $total) * 100;

            if      ($position_pct < $pct_I)       $vieta = 'I';
            elseif  ($position_pct < $pct_II)      $vieta = 'II';
            elseif  ($position_pct < $pct_III)     $vieta = 'III';
            elseif  ($position_pct < $pct_laureat) $vieta = 'laureat.';
            else                                   $vieta = '';

            if ($vieta !== '') {
                foreach ($ids as $rid) {
                    db_query("UPDATE dalyviai SET Vieta = ? WHERE reg_id = ?", [$vieta, $rid]);
                }
            }

            $rank_pos += $group_size; // sekančiai grupei pozicija šokteli per visus šios grupės narius
        }

        set_message(
            "Vietos perskaičiuotos $total dalyvių (metodas: procentinė dalis — " .
            "I: top {$pct_I}%, II: top {$pct_II}%, III: top {$pct_III}%, Laur.: top {$pct_laureat}%).",
            'success'
        );
    }

    redirect(SITE_URL . '/modules/reports/result_sheet.php?olympiad_id=' . $olympiad_id);
}

// ── Dalyvių sąrašas ────────────────────────────────────────────────────────
$stmt = db_query(
    "SELECT * FROM dalyviai WHERE konkurso_pav = ? ORDER BY CAST(Balai AS UNSIGNED) DESC, reg_id ASC",
    [$olympiad['konkurso_pav']]
);
$participants    = $stmt ? db_get_results($stmt) : [];
$total_p         = count($participants);
$with_scores     = count(array_filter($participants, fn($p) => $p['Balai'] !== '' && $p['Balai'] !== null));

// Statistika vietoms
$vietos_count = ['I' => 0, 'II' => 0, 'III' => 0, 'laureat.' => 0];
foreach ($participants as $p) {
    if (isset($vietos_count[$p['Vieta']])) $vietos_count[$p['Vieta']]++;
}

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="container mt-4">
<div class="row">
<div class="col-12">
<div class="card">

    <div class="card-header d-flex justify-content-between align-items-center">
        <h1 class="h4 mb-0">
            Rezultatų lentelė: <?php echo htmlspecialchars($olympiad['konkurso_pav']); ?>
            <span class="badge bg-secondary ms-2"><?php echo $total_p; ?> dalyvių</span>
        </h1>
        <a href="<?php echo SITE_URL; ?>/modules/olympiads/index.php" class="btn btn-secondary btn-sm">Grįžti</a>
    </div>

    <div class="card-body">
        <?php display_message(); ?>

        <!-- Statistikos juosta -->
        <?php if (array_sum($vietos_count) > 0): ?>
        <div class="row g-2 mb-4">
            <div class="col-6 col-md-3">
                <div class="card text-center border-warning">
                    <div class="card-body py-2">
                        <div class="h4 mb-0 text-warning"><?php echo $vietos_count['I']; ?></div>
                        <small class="text-muted">I vieta</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center border-secondary">
                    <div class="card-body py-2">
                        <div class="h4 mb-0 text-secondary"><?php echo $vietos_count['II']; ?></div>
                        <small class="text-muted">II vieta</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center" style="border-color:#cd7f32;">
                    <div class="card-body py-2">
                        <div class="h4 mb-0" style="color:#cd7f32;"><?php echo $vietos_count['III']; ?></div>
                        <small class="text-muted">III vieta</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center border-info">
                    <div class="card-body py-2">
                        <div class="h4 mb-0 text-info"><?php echo $vietos_count['laureat.']; ?></div>
                        <small class="text-muted">Laureatai</small>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ═══ AUTOMATINIO SKAIČIAVIMO SKYDELIS ═══ -->
        <div class="card border-warning mb-4">
            <div class="card-header bg-warning bg-opacity-25 d-flex justify-content-between align-items-center"
                 style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#rankCalcPanel">
                <span><i class="fas fa-calculator"></i>
                    <strong>Automatinis vietų skaičiavimas (LINEŠA metodika)</strong></span>
                <i class="fas fa-chevron-down"></i>
            </div>

            <div class="collapse" id="rankCalcPanel">
            <div class="card-body">

                <!-- Metodo pasirinkimas -->
                <ul class="nav nav-tabs mb-3" id="methodTabs">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab_absolute">
                            <i class="fas fa-percent"></i> Absoliučios kartelės
                            <span class="badge bg-success ms-1">Rekomenduojama</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab_percent">
                            <i class="fas fa-users"></i> Procentinė dalis
                        </button>
                    </li>
                </ul>

                <div class="tab-content">

                    <!-- ─── 1 METODAS: Absoliučios kartelės ─── -->
                    <div class="tab-pane fade show active" id="tab_absolute">
                        <div class="alert alert-info py-2 small mb-3">
                            <i class="fas fa-info-circle"></i>
                            <strong>Oficialus LINEŠA metodas.</strong>
                            Vietą gauna visi mokiniai, surinkę ne mažiau kaip nustatytą procentą nuo galimų maksimalių balų.
                            Lygūs balai → <strong>ta pati vieta</strong> visiems. Gali būti keli I vietos laimėtojai.
                        </div>

                        <form method="post"
                              action="<?php echo SITE_URL; ?>/modules/reports/result_sheet.php?olympiad_id=<?php echo $olympiad_id; ?>"
                              onsubmit="return confirm('Perskaičiuoti vietas pagal absoliučias kartelės? Dabartinės vietos bus perrašytos.');">
                            <input type="hidden" name="csrf_token"  value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="method"      value="absolute">

                            <div class="row g-3 mb-3">
                                <div class="col-6 col-md-2">
                                    <label class="form-label fw-bold">Maks. balai</label>
                                    <input type="number" class="form-control" name="max_score"
                                           id="abs_max" value="40" min="1" required>
                                    <div class="form-text">Olimpiados maksimumas</div>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label fw-bold text-warning">I vieta ≥</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control abs-pct" name="abs_pct_I"
                                               id="abs_I" value="90" min="1" max="100" step="0.5" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <div class="form-text abs-min" id="min_I">≥ 36 bal.</div>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label fw-bold text-secondary">II vieta ≥</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control abs-pct" name="abs_pct_II"
                                               id="abs_II" value="75" min="1" max="100" step="0.5" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <div class="form-text abs-min" id="min_II">≥ 30 bal.</div>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label fw-bold" style="color:#cd7f32;">III vieta ≥</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control abs-pct" name="abs_pct_III"
                                               id="abs_III" value="60" min="1" max="100" step="0.5" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <div class="form-text abs-min" id="min_III">≥ 24 bal.</div>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label fw-bold text-info">Laureatas ≥</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control abs-pct" name="abs_pct_laureat"
                                               id="abs_laureat" value="45" min="1" max="100" step="0.5" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <div class="form-text abs-min" id="min_laureat">≥ 18 bal.</div>
                                </div>
                            </div>

                            <!-- Peržiūros lentelė -->
                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-bordered text-center" style="font-size:0.85rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Vieta</th>
                                            <th>Min. balai (apskaičiuota)</th>
                                            <th>Sąlyga</th>
                                            <th>Dabartinis laimėtojų sk.</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $max_s = 40;
                                        $abs_vals = [
                                            ['I',       90, 'warning',   $vietos_count['I']],
                                            ['II',      75, 'secondary', $vietos_count['II']],
                                            ['III',     60, 'bronze',    $vietos_count['III']],
                                            ['laureat.',45, 'info',      $vietos_count['laureat.']],
                                        ];
                                        foreach ($abs_vals as [$v, $pct, $cls, $cnt]):
                                            $min_b = round($max_s * $pct / 100, 1);
                                        ?>
                                        <tr>
                                            <td><span class="badge bg-<?php echo $cls === 'bronze' ? 'warning text-dark' : $cls; ?>"><?php echo $v; ?></span></td>
                                            <td class="fw-bold" id="preview_min_<?php echo str_replace('.','_',$v); ?>"><?php echo $min_b; ?></td>
                                            <td>≥ <?php echo $pct; ?>% × maks. balų</td>
                                            <td><span class="badge bg-light text-dark border"><?php echo $cnt; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <button type="submit" name="recalculate_ranks" class="btn btn-warning">
                                <i class="fas fa-sync-alt"></i> Priskirti vietas pagal kartelę
                            </button>
                            <span class="text-muted small ms-2">Dalyvių su balais: <strong><?php echo $with_scores; ?></strong></span>
                        </form>
                    </div>

                    <!-- ─── 2 METODAS: Procentinė dalis ─── -->
                    <div class="tab-pane fade" id="tab_percent">
                        <div class="alert alert-secondary py-2 small mb-3">
                            <i class="fas fa-info-circle"></i>
                            Vietos skiriamos geriausiam X% dalyvių. Lygūs balai dalijasi tą pačią vietą —
                            sekanti vieta <strong>praleidžiama</strong> (pvz.: du II → kitas gauna IV).
                        </div>

                        <form method="post"
                              action="<?php echo SITE_URL; ?>/modules/reports/result_sheet.php?olympiad_id=<?php echo $olympiad_id; ?>"
                              onsubmit="return confirm('Perskaičiuoti vietas pagal procentinę dalį?');">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="method"     value="percent">

                            <div class="row g-3 mb-3">
                                <?php
                                $pct_fields = [
                                    ['pct_I',       'I vieta',  'warning',  10, 'text-warning'],
                                    ['pct_II',      'II vieta', 'secondary',25, 'text-secondary'],
                                    ['pct_III',     'III vieta','bronze',   40, ''],
                                    ['pct_laureat', 'Laureatas','info',     60, 'text-info'],
                                ];
                                foreach ($pct_fields as [$name, $label, $cls, $def, $tcls]):
                                ?>
                                <div class="col-6 col-md-3">
                                    <label class="form-label fw-bold <?php echo $tcls; ?>" <?php if($cls==='bronze') echo 'style="color:#cd7f32;"'; ?>>
                                        <?php echo $label; ?> — top %
                                    </label>
                                    <div class="input-group">
                                        <input type="number" class="form-control pct-field" name="<?php echo $name; ?>"
                                               value="<?php echo $def; ?>" min="1" max="100" required
                                               data-bar="bar_<?php echo explode('_',$name)[1] ?? $name; ?>">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <div class="form-text">
                                        ≈ <?php echo round($with_scores * $def / 100); ?> dalyvių
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Gyva juosta -->
                            <div class="mb-3">
                                <div class="d-flex rounded overflow-hidden" style="height:30px;font-size:0.75rem;" id="pctBar">
                                    <div class="bg-warning text-dark d-flex align-items-center justify-content-center fw-bold" id="bar_I"       style="width:10%">I 10%</div>
                                    <div class="bg-secondary text-white d-flex align-items-center justify-content-center fw-bold" id="bar_II"    style="width:15%">II 15%</div>
                                    <div class="d-flex align-items-center justify-content-center fw-bold text-white" id="bar_III"               style="width:15%;background:#cd7f32;">III 15%</div>
                                    <div class="bg-info text-white d-flex align-items-center justify-content-center fw-bold" id="bar_laureat"   style="width:20%">Laur. 20%</div>
                                    <div class="bg-light text-muted d-flex align-items-center justify-content-center" id="bar_none"             style="width:40%">Be vietos 40%</div>
                                </div>
                            </div>

                            <button type="submit" name="recalculate_ranks" class="btn btn-secondary">
                                <i class="fas fa-sync-alt"></i> Priskirti vietas pagal procentus
                            </button>
                        </form>
                    </div>

                </div><!-- tab-content -->
            </div><!-- card-body -->
            </div><!-- collapse -->
        </div><!-- card -->

        <!-- ═══ BALŲ ĮVEDIMO FORMA ═══ -->
        <form method="post"
              action="<?php echo SITE_URL; ?>/modules/reports/result_sheet.php?olympiad_id=<?php echo $olympiad_id; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px;">#</th>
                            <th>Kodas</th>
                            <th style="width:120px;">Balai</th>
                            <th style="width:160px;">Vieta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $i => $p): ?>
                        <tr class="<?php
                            if ($p['Vieta'] === 'I')        echo 'table-warning';
                            elseif ($p['Vieta'] === 'II')   echo 'table-secondary';
                            elseif ($p['Vieta'] === 'III')  echo 'table-light';
                            elseif ($p['Vieta'] === 'laureat.') echo 'table-info bg-opacity-25';
                        ?>">
                            <td class="text-muted"><?php echo $i + 1; ?></td>
                            <td><strong><?php echo $p['reg_id']; ?></strong></td>
                            <td>
                                <input type="number" class="form-control form-control-sm"
                                       name="participant[<?php echo $p['reg_id']; ?>][balai]"
                                       value="<?php echo htmlspecialchars($p['Balai'] ?? ''); ?>"
                                       min="0" placeholder="—">
                            </td>
                            <td>
                                <select class="form-select form-select-sm"
                                        name="participant[<?php echo $p['reg_id']; ?>][vieta]">
                                    <option value="">—</option>
                                    <option value="I"        <?php echo ($p['Vieta'] ?? '') === 'I'        ? 'selected' : ''; ?>>I vieta</option>
                                    <option value="II"       <?php echo ($p['Vieta'] ?? '') === 'II'       ? 'selected' : ''; ?>>II vieta</option>
                                    <option value="III"      <?php echo ($p['Vieta'] ?? '') === 'III'      ? 'selected' : ''; ?>>III vieta</option>
                                    <option value="laureat." <?php echo ($p['Vieta'] ?? '') === 'laureat.' ? 'selected' : ''; ?>>Laureatas</option>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                <button type="submit" name="save_results" class="btn btn-primary">
                    <i class="fas fa-save"></i> Išsaugoti balus ir vietas
                </button>
            </div>
        </form>

    </div><!-- card-body -->
</div><!-- card -->
</div>
</div>
</div>

<script>
// ── Absoliučios kartelės: gyvas minimalių balų skaičiavimas ───────────────
(function () {
    const maxEl  = document.getElementById('abs_max');
    const pctEls = document.querySelectorAll('.abs-pct');
    const keys   = ['I','II','III','laureat_'];

    function update() {
        const max = parseFloat(maxEl?.value) || 0;
        document.querySelectorAll('.abs-pct').forEach(function (el) {
            const pct  = parseFloat(el.value) || 0;
            const min  = (max * pct / 100).toFixed(1);
            const id   = el.id.replace('abs_','');
            const hint = document.getElementById('min_' + id);
            if (hint) hint.textContent = '≥ ' + min + ' bal.';

            // Atnaujinti preview lentelę
            const prev = document.getElementById('preview_min_' + id.replace('.','_'));
            if (prev) prev.textContent = min;
        });
    }

    if (maxEl) maxEl.addEventListener('input', update);
    pctEls.forEach(function (el) { el.addEventListener('input', update); });
    update();
})();

// ── Procentinė dalis: gyva juosta ─────────────────────────────────────────
(function () {
    const fields = ['pct_I','pct_II','pct_III','pct_laureat'];

    function updateBar() {
        const vals = fields.map(function(n) {
            return parseFloat(document.querySelector('[name="' + n + '"]')?.value) || 0;
        });
        const [i, ii, iii, laur] = vals;
        const segs = [
            {id:'bar_I',       w:i,          label:'I ' + i + '%'},
            {id:'bar_II',      w:ii - i,     label:'II ' + (ii-i) + '%'},
            {id:'bar_III',     w:iii - ii,   label:'III ' + (iii-ii) + '%'},
            {id:'bar_laureat', w:laur - iii, label:'Laur. ' + (laur-iii) + '%'},
            {id:'bar_none',    w:100 - laur, label:'Be vietos ' + (100-laur) + '%'},
        ];
        segs.forEach(function(s) {
            const el = document.getElementById(s.id);
            if (!el) return;
            const w = Math.max(0, s.w);
            el.style.width   = w + '%';
            el.style.display = w > 0 ? '' : 'none';
            el.textContent   = w > 4 ? s.label : '';
        });
    }

    fields.forEach(function(n) {
        const el = document.querySelector('[name="' + n + '"]');
        if (el) el.addEventListener('input', updateBar);
    });
    updateBar();
})();
</script>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>