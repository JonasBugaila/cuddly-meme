<?php
/**
 * Vertinimo lentelės puslapis
 */
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
} elseif (!is_admin()) {
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL);
}

$selected_olympiad = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['olympiad'])) {
    $selected_olympiad = trim(sanitize_input($_POST['olympiad']));
} elseif (isset($_GET['olympiad'])) {
    $selected_olympiad = trim(sanitize_input($_GET['olympiad']));
}

$print_mode = isset($_GET['print']) && $_GET['print'] == '1';
$print_empty_mode = isset($_GET['print_empty']) && $_GET['print_empty'] == '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_results']) && is_admin()) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_message('Netinkamas CSRF žetonas.', 'error');
        redirect(SITE_URL . '/modules/reports/evaluation_sheets.php?olympiad=' . urlencode($selected_olympiad));
        exit;
    }
    if (isset($_POST['participant']) && is_array($_POST['participant'])) {
        foreach ($_POST['participant'] as $reg_id => $data) {
            $balai = isset($data['balai']) ? sanitize_input($data['balai']) : '';
            $vieta = isset($data['vieta']) ? sanitize_input($data['vieta']) : '';
            
            $sql = "UPDATE dalyviai SET Balai = ?, Vieta = ? WHERE reg_id = ?";
            db_query($sql, [$balai, $vieta, $reg_id], 'ssi');
        }
        set_message('Rezultatai sėkmingai išsaugoti', 'success');
    } else {
        set_message('Nėra duomenų išsaugojimui.', 'warning');
    }
    redirect(SITE_URL . '/modules/reports/evaluation_sheets.php?olympiad=' . urlencode($selected_olympiad));
}

$sql = "SELECT DISTINCT konkurso_pav FROM konkursai ORDER BY konkurso_pav";
$stmt = db_query($sql);
$olympiads = $stmt ? db_get_results($stmt) : [];

$participants = [];
$participant_count = 0;
if (!empty($selected_olympiad)) {
    $sql = "SELECT reg_id, konkurso_pav, Balai, Vieta FROM dalyviai WHERE konkurso_pav = ? ORDER BY CAST(Balai AS UNSIGNED) DESC, reg_id ASC";
    $stmt = db_query($sql, [$selected_olympiad], 's');
    if ($stmt) {
        $participants = db_get_results($stmt);
        $participant_count = count($participants);
    }
}

// ==================== SPAUSDINIMAS ====================
if (($print_mode || $print_empty_mode) && !empty($selected_olympiad)) {
    header('Content-Type: text/html; charset=UTF-8');
    $headers = ['KODAS', 'I užd.', 'II užd.', 'III užd.', 'IV užd.', 'V užd.', 'VI užd.', 'VII užd.', 'VIII užd.', 'IX užd.', 'X užd.', 'IŠ VISO BALŲ', 'VIETA'];
    
    $chunks = array_chunk($participants, 15);
    $total_pages = count($chunks);

    foreach ($chunks as $page_num => $chunk) {
        $data = [];
        foreach ($chunk as $participant) {
            if ($print_empty_mode) {
                $data[] = [$participant['reg_id'], '', '', '', '', '', '', '', '', '', '', '', ''];
            } else {
                $data[] = [
                    $participant['reg_id'], '-', '-', '-', '-', '-', '-', '-', '-', '-', '-',
                    $participant['Balai'] ?? '-',
                    $participant['Vieta'] ?? '-'
                ];
            }
        }

        echo '<div class="evaluation-section" style="page-break-after: always;">';
        echo generate_printable_table($selected_olympiad, '', $headers, $data, [
            'signature_text' => 'Atsakingo asmens parašas',
            'signature_name' => '',
            'include_back_button' => false
        ], 'evaluation');
        
        echo '<div style="text-align:center; margin-top:10px;">Puslapis ' . ($page_num + 1) . ' iš ' . $total_pages . '</div>';
        echo '</div>';
    }
    exit; 
}

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
                    <h1 class="h4 mb-0 text-white"><i class="fas fa-clipboard-list"></i> Vertinimo lentelė</h1>
                    <!-- DInamiškas grįžimo mygtukas -->
                    <button type="button" onclick="window.history.back();" class="btn btn-sm btn-light text-primary fw-bold">
                        <i class="fas fa-arrow-left"></i> Grįžti atgal
                    </button>
                </div>
                <div class="card-body bg-light">
                    <?php display_message(); ?>

                    <form method="post" action="<?php echo SITE_URL; ?>/modules/reports/evaluation_sheets.php" class="mb-4 bg-white p-3 border rounded">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label for="olympiad" class="form-label fw-bold text-primary">Olimpiada / Konkursas</label>
                                    <select class="form-select border-primary" id="olympiad" name="olympiad" required>
                                        <option value="">-- Pasirinkite olimpiadą --</option>
                                        <?php foreach ($olympiads as $oly): ?>
                                            <option value="<?php echo htmlspecialchars($oly['konkurso_pav']); ?>" <?php echo $selected_olympiad === $oly['konkurso_pav'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($oly['konkurso_pav']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6 d-flex align-items-end gap-2 mt-3 mt-md-0">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Rodyti lentelę</button>
                                <?php if (!empty($selected_olympiad) && !empty($participants)): ?>
                                    <a href="?print=1&olympiad=<?php echo urlencode($selected_olympiad); ?>" class="btn btn-outline-primary"><i class="fas fa-print"></i> Su rezultatais</a>
                                    <a href="?print_empty=1&olympiad=<?php echo urlencode($selected_olympiad); ?>" class="btn btn-outline-secondary"><i class="fas fa-print"></i> Tuščią</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>

                    <?php if (!empty($selected_olympiad) && !empty($participants)): ?>
                        <h3 class="text-center mb-4 text-dark"><?php echo htmlspecialchars($selected_olympiad); ?></h3>
                        <form action="<?php echo SITE_URL; ?>/modules/reports/evaluation_sheets.php?olympiad=<?php echo urlencode($selected_olympiad); ?>" method="post" class="bg-white p-3 border rounded shadow-sm">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle border">
                                    <thead class="table-light">
                                        <tr>
                                            <th>KODAS</th>
                                            <th>I užd.</th><th>II užd.</th><th>III užd.</th><th>IV užd.</th>
                                            <th>V užd.</th><th>VI užd.</th><th>VII užd.</th><th>VIII užd.</th>
                                            <th>IX užd.</th><th>X užd.</th>
                                            <th style="width: 100px;">IŠ VISO BALŲ</th>
                                            <th style="width: 130px;">VIETA</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($participants as $participant): ?>
                                            <tr>
                                                <td><span class="badge bg-dark fs-6"><?php echo htmlspecialchars($participant['reg_id'] ?? ''); ?></span></td>
                                                <td class="text-muted">-</td><td class="text-muted">-</td><td class="text-muted">-</td><td class="text-muted">-</td><td class="text-muted">-</td>
                                                <td class="text-muted">-</td><td class="text-muted">-</td><td class="text-muted">-</td><td class="text-muted">-</td><td class="text-muted">-</td>
                                                <td>
                                                    <!-- Ištaisyta vieta! Pridėtas ?? '' saugiam NULL apdorojimui -->
                                                    <input type="text" class="form-control form-control-sm border-primary" name="participant[<?php echo $participant['reg_id']; ?>][balai]" value="<?php echo htmlspecialchars($participant['Balai'] ?? ''); ?>">
                                                </td>
                                                <td>
                                                    <select class="form-select form-select-sm" name="participant[<?php echo $participant['reg_id']; ?>][vieta]">
                                                        <option value="">-</option>
                                                        <option value="I" <?php echo ($participant['Vieta'] ?? '') == 'I' ? 'selected' : ''; ?>>I</option>
                                                        <option value="II" <?php echo ($participant['Vieta'] ?? '') == 'II' ? 'selected' : ''; ?>>II</option>
                                                        <option value="III" <?php echo ($participant['Vieta'] ?? '') == 'III' ? 'selected' : ''; ?>>III</option>
                                                        <option value="laureat." <?php echo ($participant['Vieta'] ?? '') == 'laureat.' ? 'selected' : ''; ?>>Laureatas</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-4 d-flex justify-content-between align-items-center border-top pt-3">
                                <span class="text-muted">Iš viso vertinamų darbų: <strong><?php echo $participant_count; ?></strong></span>
                                <button type="submit" name="save_results" class="btn btn-success px-4 shadow-sm"><i class="fas fa-save"></i> Išsaugoti rezultatus</button>
                            </div>
                        </form>
                    <?php elseif (!empty($selected_olympiad)): ?>
                        <div class="alert alert-info shadow-sm"><i class="fas fa-info-circle"></i> Nėra užregistruotų dalyvių šioje olimpiadoje.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>