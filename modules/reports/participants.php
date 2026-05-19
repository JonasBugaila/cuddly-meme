<?php
/**
 * Dalyvių sąrašų pagal olimpiadas ir mokyklas puslapis
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

if (!is_admin()) {
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL);
}

// Spausdinimo režimo apdorojimas
if (isset($_GET['print']) && $_GET['print'] == '1') {
    $selected_olympiad = isset($_GET['olympiad']) ? urldecode($_GET['olympiad']) : '';
    $selected_school = isset($_GET['school']) ? urldecode($_GET['school']) : '';
    $smsm_patvirtintas = isset($_GET['smsm_patvirtintas']) ? $_GET['smsm_patvirtintas'] : '';
    $ne_rajono = isset($_GET['ne_rajono']) ? $_GET['ne_rajono'] : '';
    
    $sql = "SELECT DISTINCT konkurso_pav FROM konkursai ORDER BY konkurso_pav";
    $stmt = db_query($sql);
    $olympiads = db_get_results($stmt);
    
    $sql = "SELECT mokyklos_id, pavadinimas FROM mokyklos ORDER BY pavadinimas";
    $stmt = db_query($sql);
    $schools = db_get_results($stmt);
    
    $sql = "SELECT d.*, m.pavadinimas AS mokykla, k.smsm_patvirtintas, k.ne_rajono 
            FROM dalyviai d 
            LEFT JOIN mokyklos m ON d.var_mokykla = m.pavadinimas 
            LEFT JOIN konkursai k ON d.konkurso_pav = k.konkurso_pav 
            WHERE 1=1";
    $params = [];
    
    if (!empty($selected_olympiad)) {
        $sql .= " AND d.konkurso_pav = ?";
        $params[] = $selected_olympiad;
    }
    
    if (!empty($selected_school)) {
        $sql .= " AND d.var_mokykla = ?";
        $params[] = $selected_school;
    }
    
    if ($smsm_patvirtintas !== '') {
        $sql .= " AND k.smsm_patvirtintas = ?";
        $params[] = (int)$smsm_patvirtintas;
    }
    
    if ($ne_rajono !== '') {
        $sql .= " AND k.ne_rajono = ?";
        $params[] = (int)$ne_rajono;
    }
    
    $sql .= " ORDER BY m.pavadinimas, d.1_vardas, d.1_pavarde";
    $stmt = db_query($sql, $params);
    $participants = db_get_results($stmt);
    
    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="lt">
    <head>
        <meta charset="UTF-8">
        <title>Dalyvių sąrašas</title>
        <style>
            body { font-family: Arial; margin: 20px; }
            .header { text-align: center; margin-bottom: 20px; }
            .title { font-size: 18px; font-weight: bold; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #000; padding: 5px; }
            th { background-color: #f2f2f2; }
            @page { size: A4 landscape; margin: 10mm; }
            .no-print { display: none; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="title">DALYVIŲ SĄRAŠAS</div>
            <?php if (!empty($selected_school)): ?>
                <div>Mokykla: <?= htmlspecialchars($selected_school) ?></div>
            <?php endif; ?>
            <div>Data: <?= date('Y-m-d') ?></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Eil. Nr.</th>
                    <th>Mokykla</th>
                    <th>Mokinys</th>
                    <th>Klasė</th>
                    <th>Mokytojas</th>
                    <th>Kategorija</th>
                    <th>2 Mokytojas</th>
                    <th>2 Kategorija</th>
                    <th>Vieta</th>
                    <th>Rajoninė</th>
                    <th>ŠMSM patvirtinta</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($participants as $i => $p): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($p['mokykla'] ?? '') ?></td>
                    <td><?= htmlspecialchars($p['1_vardas'].' '.$p['1_pavarde']) ?></td>
                    <td><?= htmlspecialchars($p['1_klase']) ?></td>
                    <td><?= htmlspecialchars($p['1_mok']) ?></td>
                    <td><?= htmlspecialchars($p['1_mok_kvali']) ?></td>
                    <td><?= htmlspecialchars($p['2_mok']) ?></td>
                    <td><?= htmlspecialchars($p['2_mok_kvali']) ?></td>
                    <td><?= htmlspecialchars($p['Vieta'] ?? '-') ?></td>
                    <td><?= $p['ne_rajono'] ? 'TAIP' : 'NE' ?></td>
                    <td><?= $p['smsm_patvirtintas'] ? 'TAIP' : 'NE' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="no-print" style="text-align:center;margin-top:20px;">
            <button onclick="window.print()">Spausdinti</button>
            <button onclick="window.close()">Uždaryti</button>
        </div>

        <script>window.onload=function(){setTimeout(window.print,300);}</script>
    </body>
    </html>
    <?php
    exit;
}

// Įprastas puslapio veikimas
$selected_olympiad = isset($_POST['olympiad']) ? trim($_POST['olympiad']) : '';
$selected_school = isset($_POST['school']) ? trim($_POST['school']) : '';
$smsm_patvirtintas = isset($_POST['smsm_patvirtintas']) ? $_POST['smsm_patvirtintas'] : '';
$ne_rajono = isset($_POST['ne_rajono']) ? $_POST['ne_rajono'] : '';

$sql = "SELECT DISTINCT konkurso_pav FROM konkursai ORDER BY konkurso_pav";
$stmt = db_query($sql);
$olympiads = db_get_results($stmt);

$sql = "SELECT mokyklos_id, pavadinimas FROM mokyklos ORDER BY pavadinimas";
$stmt = db_query($sql);
$schools = db_get_results($stmt);

$sql = "SELECT d.*, m.pavadinimas AS mokykla, k.smsm_patvirtintas, k.ne_rajono 
        FROM dalyviai d 
        LEFT JOIN mokyklos m ON d.var_mokykla = m.pavadinimas 
        LEFT JOIN konkursai k ON d.konkurso_pav = k.konkurso_pav 
        WHERE 1=1";
$params = [];

if (!empty($selected_olympiad)) {
    $sql .= " AND d.konkurso_pav = ?";
    $params[] = $selected_olympiad;
}

if (!empty($selected_school)) {
    $sql .= " AND d.var_mokykla = ?";
    $params[] = $selected_school;
}

if ($smsm_patvirtintas !== '') {
    $sql .= " AND k.smsm_patvirtintas = ?";
    $params[] = (int)$smsm_patvirtintas;
}

if ($ne_rajono !== '') {
    $sql .= " AND k.ne_rajono = ?";
    $params[] = (int)$ne_rajono;
}

$sql .= " ORDER BY m.pavadinimas, d.1_vardas, d.1_pavarde";
$stmt = db_query($sql, $params);
$participants = db_get_results($stmt);

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h1>Dalyvių sąrašai</h1>
                </div>
                <div class="card-body">
                    <?php display_message(); ?>
                    
                    <form method="post" class="mb-4">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Olimpiada</label>
                                    <select name="olympiad" class="form-control">
                                        <option value="">Visos</option>
                                        <?php foreach ($olympiads as $o): ?>
                                        <option value="<?= htmlspecialchars($o['konkurso_pav']) ?>" <?= $selected_olympiad == $o['konkurso_pav'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($o['konkurso_pav']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Mokykla</label>
                                    <select name="school" class="form-control">
                                        <option value="">Visos</option>
                                        <?php foreach ($schools as $s): ?>
                                        <option value="<?= htmlspecialchars($s['pavadinimas']) ?>" <?= $selected_school == $s['pavadinimas'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['pavadinimas']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>ŠMSM patvirtinta</label>
                                    <select name="smsm_patvirtintas" class="form-control">
                                        <option value="" <?= $smsm_patvirtintas === '' ? 'selected' : '' ?>>Visi</option>
                                        <option value="1" <?= $smsm_patvirtintas === '1' ? 'selected' : '' ?>>Taip</option>
                                        <option value="0" <?= $smsm_patvirtintas === '0' ? 'selected' : '' ?>>Ne</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Rajoninė olimpiada</label>
                                    <select name="ne_rajono" class="form-control">
                                        <option value="" <?= $ne_rajono === '' ? 'selected' : '' ?>>Visi</option>
                                        <option value="1" <?= $ne_rajono === '1' ? 'selected' : '' ?>>Taip</option>
                                        <option value="0" <?= $ne_rajono === '0' ? 'selected' : '' ?>>Ne</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-2">Filtruoti</button>
                        <?php if (!empty($participants)): ?>
                        <a href="<?= SITE_URL ?>/modules/reports/participants.php?print=1&olympiad=<?= urlencode($selected_olympiad) ?>&school=<?= urlencode($selected_school) ?>&smsm_patvirtintas=<?= urlencode($smsm_patvirtintas) ?>&ne_rajono=<?= urlencode($ne_rajono) ?>" 
                           class="btn btn-secondary mt-2" target="_blank">
                           Spausdinti
                        </a>
                        <?php endif; ?>
                    </form>

                    <?php if (!empty($participants)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Eil. Nr.</th>
                                    <th>Mokykla</th>
                                    <th>Mokinys</th>
                                    <th>Klasė</th>
                                    <th>Mokytojas</th>
                                    <th>Kategorija</th>
                                    <th>Vieta</th>
                                    <th>Rajoninė</th>
                                    <th>ŠMSM patvirtinta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participants as $i => $p): ?>
                                <tr>
                                    <td><?= $i+1 ?></td>
                                    <td><?= htmlspecialchars($p['mokykla'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($p['1_vardas'].' '.$p['1_pavarde']) ?></td>
                                    <td><?= htmlspecialchars($p['1_klase']) ?></td>
                                    <td><?= htmlspecialchars($p['1_mok']) ?></td>
                                    <td><?= htmlspecialchars($p['1_mok_kvali']) ?></td>
                                    <td><?= htmlspecialchars($p['Vieta'] ?? '-') ?></td>
                                    <td><?= $p['ne_rajono'] ? 'TAIP' : 'NE' ?></td>
                                    <td><?= $p['smsm_patvirtintas'] ? 'TAIP' : 'NE' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">Nėra duomenų pagal pasirinktus filtrus</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
?>