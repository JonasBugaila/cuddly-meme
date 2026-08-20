<?php
/**
 * Rezultatų peržiūros puslapis
 * 
 * Šis failas atvaizduoja olimpiados rezultatus ir leidžia juos redaguoti bei spausdinti
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname ar vartotojas prisijungęs
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

// SAUGU: rezultatus (balus, vietas) mato ir tvarko tik administratorius.
// Mokytojai (paprasti vartotojai) rezultatų peržiūrėti negali.
if (!is_admin()) {
    log_action('Saugumo pažeidimas', 'Bandyta pasiekti rezultatų puslapį be admin teisių.');
    set_message('Neturite teisių peržiūrėti rezultatų.', 'error');
    redirect(SITE_URL);
    exit;
}

// Tikriname ar nurodytas olimpiados ID
if (!isset($_GET['olympiad_id']) || empty($_GET['olympiad_id'])) {
    set_message('Nenurodyta olimpiada', 'error');
    redirect(SITE_URL . '/modules/results/index.php');
}

$olympiad_id = sanitize_input($_GET['olympiad_id']);
$print_mode = isset($_GET['print']) && $_GET['print'] == '1';

// Gauname olimpiados informaciją
$sql = "SELECT * FROM konkursai WHERE konk_id = ?";
$stmt = db_query($sql, [$olympiad_id]);
$olympiad = db_get_row($stmt);

if (!$olympiad) {
    set_message('Olimpiada nerasta', 'error');
    redirect(SITE_URL . '/modules/results/index.php');
}

// Patikriname, ar olimpiada yra patvirtinta ŠMSM
$is_smsm = (isset($olympiad['smsm_patvirtintas']) && $olympiad['smsm_patvirtintas'] == 1);

// Gauname olimpiados dalyvius
if (is_admin()) {
    // Administratorius mato visus dalyvius
    $sql = "SELECT * FROM dalyviai WHERE konkurso_pav = ? ORDER BY CAST(Balai AS UNSIGNED) DESC, 1_pavarde ASC";
    $stmt = db_query($sql, [$olympiad['konkurso_pav']]);
} else {
    // Paprastas vartotojas mato tik savo mokyklos dalyvius
    if (!isset($_SESSION['user_id'])) {
        set_message('Jūsų sesijos duomenys neteisingi. Prašome prisijungti iš naujo.', 'error');
        redirect(SITE_URL . '/modules/auth/login.php');
    }
    // Gauname vartotojo mokyklą iš duomenų bazės
    $sql = "SELECT var_mokykla FROM vartotojas WHERE vart_id = ?";
    $stmt = db_query($sql, [$_SESSION['user_id']], 's');
    $user_data = db_get_row($stmt);
    
    if (!$user_data || empty($user_data['var_mokykla'])) {
        set_message('Jūsų mokykla nenurodyta. Susisiekite su administratoriumi.', 'error');
        redirect(SITE_URL . '/modules/results/index.php');
    }
    
    $user_school = $user_data['var_mokykla'];
    $sql = "SELECT * FROM dalyviai WHERE konkurso_pav = ? AND var_mokykla = ? ORDER BY CAST(Balai AS UNSIGNED) DESC, 1_pavarde ASC";
    $stmt = db_query($sql, [$olympiad['konkurso_pav'], $user_school], 'ss');
}
$participants = db_get_results($stmt);

// Apdorojame rezultatų įvedimą
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_results']) && is_admin()) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_message('Netinkamas CSRF žetonas.', 'error');
        redirect(SITE_URL . '/modules/results/view.php?olympiad_id=' . $olympiad_id);
        exit;
    }
    foreach ($_POST['participant'] as $reg_id => $data) {
        $balai = isset($data['balai']) ? sanitize_input($data['balai']) : '';
        $vieta = isset($data['vieta']) ? sanitize_input($data['vieta']) : '';
        
        if ($is_smsm) {
            $kitas_etapas = isset($data['kitas_etapas']) ? (int)$data['kitas_etapas'] : 0;
            $sql = "UPDATE dalyviai SET Balai = ?, Vieta = ?, kitas_etapas = ? WHERE reg_id = ?";
            db_query($sql, [$balai, $vieta, $kitas_etapas, $reg_id]);
        } else {
            $sql = "UPDATE dalyviai SET Balai = ?, Vieta = ? WHERE reg_id = ?";
            db_query($sql, [$balai, $vieta, $reg_id]);
        }
    }
    
    set_message('Rezultatai sėkmingai išsaugoti', 'success');
    redirect(SITE_URL . '/modules/results/view.php?olympiad_id=' . $olympiad_id);
    exit;
}

// ==================== SPAUSDINIMAS ====================
if ($print_mode && !empty($participants)) {
    header('Content-Type: text/html; charset=UTF-8');

    $headers = ['ID', 'Vardas', 'Pavardė', 'Klasė', 'Mokykla', 'Mokytojas', '2-as Mokytojas', 'Balai', 'Vieta'];
    if ($is_smsm) {
        $headers[] = 'Kitas etapas';
    }

    $chunks = array_chunk($participants, 20);
    $total_pages = count($chunks);

    foreach ($chunks as $page_num => $chunk) {
        $data = [];
        foreach ($chunk as $p) {
            $balai = (isset($p['Balai']) && $p['Balai'] !== '') ? $p['Balai'] : '-';
            $vieta = !empty($p['Vieta']) ? $p['Vieta'] : '-';
            
            $row = [
                htmlspecialchars($p['reg_id']),
                htmlspecialchars($p['1_vardas'] ?? ''),
                htmlspecialchars($p['1_pavarde'] ?? ''),
                htmlspecialchars($p['1_klase'] ?? ''),
                htmlspecialchars($p['var_mokykla'] ?? ''),
                htmlspecialchars($p['1_mok'] ?? '-'),
                htmlspecialchars($p['2_mok'] ?? '-'),
                htmlspecialchars($balai),
                htmlspecialchars($vieta)
            ];
            
            if ($is_smsm) {
                $kitas_etapas_tekstas = '—';
                if (($p['kitas_etapas'] ?? 0) == 1) $kitas_etapas_tekstas = 'Siunčiamas';
                elseif (($p['kitas_etapas'] ?? 0) == 2) $kitas_etapas_tekstas = 'Nesiunčiamas';
                $row[] = htmlspecialchars($kitas_etapas_tekstas);
            }
            
            $data[] = $row;
        }

        echo '<div class="olympiad-section" style="page-break-after: always;">';
        
        echo generate_printable_table($olympiad['konkurso_pav'] . ' - Rezultatai', '', $headers, $data, [
            'include_back_button' => false,
            'page_num' => $page_num + 1,
            'total_pages' => $total_pages
        ], 'protocol');
        
        echo '</div>';
    }

    exit;
}

// Įtraukiame antraštę naršyklės vaizdui
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
                <h1 class="h4 mb-0"><i class="fas fa-list-ol"></i> Olimpiados "<?php echo htmlspecialchars($olympiad['konkurso_pav']); ?>" rezultatai</h1>
                <div>
                    <!-- Spausdinimo mygtukas dabar naudoja standartinę PHP logiką kaip visur kitur -->
                    <a href="?olympiad_id=<?php echo $olympiad_id; ?>&print=1" class="btn btn-sm btn-light text-dark fw-bold me-2"><i class="fas fa-print"></i> Spausdinti</a>
                    <a href="<?php echo SITE_URL; ?>/modules/results/index.php" class="btn btn-sm btn-secondary">Grįžti į sąrašą</a>
                </div>
            </div>
            
            <div class="card-body bg-light">
                <?php display_message(); ?>
                
                <?php if (!empty($participants)): ?>
                    <?php if (is_admin()): ?>
                        <form action="<?php echo SITE_URL; ?>/modules/results/view.php?olympiad_id=<?php echo $olympiad_id; ?>" method="post" class="bg-white p-3 border rounded shadow-sm">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <div class="mb-3 d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 text-secondary">Redaguojamas sąrašas</h5>
                                <button type="submit" name="save_results" class="btn btn-success"><i class="fas fa-save"></i> Išsaugoti rezultatus</button>
                            </div>
                    <?php else: ?>
                        <div class="bg-white p-3 border rounded shadow-sm">
                    <?php endif; ?>
                            
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle border">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Vardas</th>
                                            <th>Pavardė</th>
                                            <th>Klasė</th>
                                            <th>Mokykla</th>
                                            <th>Mokytojas</th>
                                            <th>2-as Mokytojas</th>
                                            <th style="width: 100px;">Balai</th>
                                            <th style="width: 130px;">Vieta</th>
                                            <?php if ($is_smsm): ?>
                                                <th>Kitas etapas</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($participants as $participant): ?>
                                            <tr>
                                                <td class="text-muted"><?php echo htmlspecialchars($participant['reg_id']); ?></td>
                                                <td><?php echo htmlspecialchars($participant['1_vardas'] ?? ''); ?></td>
                                                <td><strong><?php echo htmlspecialchars($participant['1_pavarde'] ?? ''); ?></strong></td>
                                                <td><?php echo htmlspecialchars($participant['1_klase'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($participant['var_mokykla'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($participant['1_mok'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($participant['2_mok'] ?? '-'); ?></td>
                                                
                                                <!-- Balai -->
                                                <td>
                                                    <?php if (is_admin()): ?>
                                                        <input type="text" class="form-control form-control-sm" name="participant[<?php echo $participant['reg_id']; ?>][balai]" value="<?php echo htmlspecialchars($participant['Balai'] ?? ''); ?>" placeholder="-">
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars($participant['Balai'] ?? '-'); ?>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <!-- Vieta -->
                                                <td>
                                                    <?php if (is_admin()): ?>
                                                        <select class="form-select form-select-sm" name="participant[<?php echo $participant['reg_id']; ?>][vieta]">
                                                            <option value="">-</option>
                                                            <option value="I" <?php echo ($participant['Vieta'] ?? '') == 'I' ? 'selected' : ''; ?>>I</option>
                                                            <option value="II" <?php echo ($participant['Vieta'] ?? '') == 'II' ? 'selected' : ''; ?>>II</option>
                                                            <option value="III" <?php echo ($participant['Vieta'] ?? '') == 'III' ? 'selected' : ''; ?>>III</option>
                                                            <option value="laureat." <?php echo ($participant['Vieta'] ?? '') == 'laureat.' ? 'selected' : ''; ?>>Laureatas</option>
                                                        </select>
                                                    <?php else: ?>
                                                        <?php if (!empty($participant['Vieta'])): ?>
                                                            <span class="badge bg-primary"><?php echo htmlspecialchars($participant['Vieta']); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <!-- Kitas etapas -->
                                                <?php if ($is_smsm): ?>
                                                <td>
                                                    <?php if (is_admin()): ?>
                                                        <select class="form-select form-select-sm" name="participant[<?php echo $participant['reg_id']; ?>][kitas_etapas]">
                                                            <option value="0">-</option>
                                                            <option value="1" <?php echo (($participant['kitas_etapas'] ?? 0) == 1) ? 'selected' : ''; ?>>Siunčiamas</option>
                                                            <option value="2" <?php echo (($participant['kitas_etapas'] ?? 0) == 2) ? 'selected' : ''; ?>>Nesiunčiamas</option>
                                                        </select>
                                                    <?php else: ?>
                                                        <?php if (($participant['kitas_etapas'] ?? 0) == 1): ?>
                                                            <span class="badge bg-success">Siunčiamas</span>
                                                        <?php elseif (($participant['kitas_etapas'] ?? 0) == 2): ?>
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
                        </form>
                    <?php else: ?>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="alert alert-info">
                        <p class="mb-0"><i class="fas fa-info-circle"></i> Nėra užregistruotų dalyvių.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>