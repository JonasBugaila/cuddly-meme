<?php
/**
 * Rezultatų lentelės puslapis
 * 
 * Šis failas atvaizduoja olimpiadų rezultatų lentelę su balais ir vietomis
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

// Gauname olimpiados ID iš GET parametro
$olympiad_id = isset($_GET['olympiad_id']) ? (int)$_GET['olympiad_id'] : 0;

// Gauname olimpiados informaciją
$olympiad = [];
if ($olympiad_id > 0) {
    $sql = "SELECT * FROM konkursai WHERE konk_id = ?";
    $stmt = db_query($sql, [$olympiad_id]);
    $olympiad = db_get_row($stmt);
}

// Jei olimpiada nerasta - rodome klaidą
if (empty($olympiad)) {
    set_message('Olimpiada nerasta', 'error');
    redirect(SITE_URL . '/modules/olympiads/index.php');
}

// Patikriname, ar olimpiada yra patvirtinta ŠMSM (kad žinotume, ar rodyti ir saugoti "Kitas etapas")
$is_smsm = (isset($olympiad['smsm_patvirtintas']) && $olympiad['smsm_patvirtintas'] == 1);

// Gauname dalyvių sąrašą
$participants = [];
$sql = "SELECT * FROM dalyviai WHERE konkurso_pav = ? ORDER BY Vieta ASC, CAST(Balai AS UNSIGNED) DESC";
$stmt = db_query($sql, [$olympiad['konkurso_pav']]);
if ($stmt) {
    $participants = db_get_results($stmt);
}

// Rezultatų atnaujinimas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_results']) && is_admin()) {
    foreach ($_POST['participant'] as $reg_id => $data) {
        $balai = isset($data['balai']) ? sanitize_input($data['balai']) : '';
        $vieta = isset($data['vieta']) ? sanitize_input($data['vieta']) : '';
        
        if ($is_smsm) {
            // KONVERTUOJAME Į SKAIČIŲ, KAD ATITIKTŲ DB STRUKTŪRĄ (int)
            $kitas_etapas = isset($data['kitas_etapas']) ? (int)$data['kitas_etapas'] : 0;
            $sql = "UPDATE dalyviai SET Balai = ?, Vieta = ?, kitas_etapas = ? WHERE reg_id = ?";
            db_query($sql, [$balai, $vieta, $kitas_etapas, $reg_id]);
        } else {
            $sql = "UPDATE dalyviai SET Balai = ?, Vieta = ? WHERE reg_id = ?";
            db_query($sql, [$balai, $vieta, $reg_id]);
        }
    }
    
    set_message('Rezultatai sėkmingai atnaujinti', 'success');
    redirect(SITE_URL . '/modules/reports/result_sheet.php?olympiad_id=' . $olympiad_id);
    exit;
}

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
                    <h1 class="h4 mb-0"><i class="fas fa-edit"></i> Rezultatų lentelė: <?php echo htmlspecialchars($olympiad['konkurso_pav']); ?></h1>
                    <a href="<?php echo SITE_URL; ?>/modules/olympiads/view.php?id=<?php echo $olympiad_id; ?>" class="btn btn-sm btn-light text-primary fw-bold">Grįžti</a>
                </div>
                <div class="card-body bg-light">
                    <?php display_message(); ?>
                    
                    <?php if (is_admin()): ?>
                    <form method="post" action="<?php echo SITE_URL; ?>/modules/reports/result_sheet.php?olympiad_id=<?php echo $olympiad_id; ?>" class="bg-white p-3 border rounded shadow-sm">
                    <?php endif; ?>
                    
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle border">
                                <thead class="table-light">
                                    <tr>
                                        <th>Vieta</th>
                                        <th>Dalyvio kodas</th>
                                        <th>Bendri balai</th>
                                        <?php if (is_admin()): ?>
                                        <th>Prizinė vieta</th>
                                        <?php endif; ?>
                                        
                                        <!-- Papildomas stulpelis antraštėje be išskirtinio stiliaus -->
                                        <?php if ($is_smsm): ?>
                                        <th>Kitas etapas</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($participants as $participant): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($participant['Vieta'])): ?>
                                                <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($participant['Vieta']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($participant['reg_id']); ?></strong></td>
                                        <td>
                                            <?php if (is_admin()): ?>
                                                <input type="text" class="form-control form-control-sm" name="participant[<?php echo $participant['reg_id']; ?>][balai]" 
                                                       value="<?php echo htmlspecialchars($participant['Balai'] ?? ''); ?>" placeholder="-">
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($participant['Balai'] ?? '-'); ?>
                                            <?php endif; ?>
                                        </td>
                                        <?php if (is_admin()): ?>
                                        <td>
                                            <select class="form-select form-select-sm" name="participant[<?php echo $participant['reg_id']; ?>][vieta]">
                                                <option value="">-</option>
                                                <option value="I" <?php echo ($participant['Vieta'] ?? '') == 'I' ? 'selected' : ''; ?>>I vieta</option>
                                                <option value="II" <?php echo ($participant['Vieta'] ?? '') == 'II' ? 'selected' : ''; ?>>II vieta</option>
                                                <option value="III" <?php echo ($participant['Vieta'] ?? '') == 'III' ? 'selected' : ''; ?>>III vieta</option>
                                                <option value="laureat." <?php echo ($participant['Vieta'] ?? '') == 'laureat.' ? 'selected' : ''; ?>>Laureatas</option>
                                            </select>
                                        </td>
                                        <?php endif; ?>
                                        
                                        <!-- Kito etapo išsaugojimo / atvaizdavimo stulpelis be išskirtinio stiliaus -->
                                        <?php if ($is_smsm): ?>
                                        <td>
                                            <?php if (is_admin()): ?>
                                                <select class="form-select form-select-sm" name="participant[<?php echo $participant['reg_id']; ?>][kitas_etapas]">
                                                    <option value="0">-</option>
                                                    <option value="1" <?php echo ($participant['kitas_etapas'] == 1) ? 'selected' : ''; ?>>Siunčiamas</option>
                                                    <option value="2" <?php echo ($participant['kitas_etapas'] == 2) ? 'selected' : ''; ?>>Nesiunčiamas</option>
                                                </select>
                                            <?php else: ?>
                                                <?php if ($participant['kitas_etapas'] == 1): ?>
                                                    <span class="badge bg-success">Siunčiamas</span>
                                                <?php elseif ($participant['kitas_etapas'] == 2): ?>
                                                    <span class="badge bg-danger">Nesiunčiamas</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                        
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (is_admin()): ?>
                        <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                            <button type="submit" name="save_results" class="btn btn-success px-4 shadow-sm"><i class="fas fa-save"></i> Išsaugoti pakeitimus</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>