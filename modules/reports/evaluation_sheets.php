<?php
/**
 * Vertinimo lentelės puslapis
 * * Šis failas atvaizduoja olimpiadų vertinimo lentelę su užduočių balais,
 * leidžia spausdinti rezultatus, redaguoti balus ir vietas adminams,
 * bei spausdinti tuščią protokolą pildymui
 */

// Įtraukiame konfigūracijos failus
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname ar vartotojas prisijungęs ir turi administratoriaus teises
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
} elseif (!is_admin()) {
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL);
}

// Gauname filtrų reikšmes
$selected_olympiad = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['olympiad'])) {
    $selected_olympiad = trim(sanitize_input($_POST['olympiad']));
} elseif (isset($_GET['olympiad'])) {
    $selected_olympiad = trim(sanitize_input($_GET['olympiad']));
}

$print_mode = isset($_GET['print']) && $_GET['print'] == '1';
$print_empty_mode = isset($_GET['print_empty']) && $_GET['print_empty'] == '1';

// Apdorojame rezultatų įvedimą
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_results']) && is_admin()) {
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

// Gauname olimpiadų sąrašą
$sql = "SELECT DISTINCT konkurso_pav FROM konkursai ORDER BY konkurso_pav";
$stmt = db_query($sql);
$olympiads = $stmt ? db_get_results($stmt) : [];

// Gauname dalyvių rezultatus
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

// ==================== SPAUSDINIMAS (Su duomenimis arba tuščias) ====================
if (($print_mode || $print_empty_mode) && !empty($selected_olympiad)) {
    header('Content-Type: text/html; charset=UTF-8');
    $headers = ['KODAS', 'I užd.', 'II užd.', 'III užd.', 'IV užd.', 'V užd.', 'VI užd.', 'VII užd.', 'VIII užd.', 'IX užd.', 'X užd.', 'IŠ VISO BALŲ', 'VIETA'];
    ?>
    <style>
        @media print {
            @page { size: landscape; margin: 1cm; }
            body { font-size: 10pt; }
            table { font-size: 9pt; width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #000; padding: 4px; text-align: center; }
            .evaluation-section { page-break-after: always; }
            .evaluation-section:last-child { page-break-after: auto; }
        }
    </style>
    <?php
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

        echo '<div class="evaluation-section">';
        echo generate_printable_table($selected_olympiad, '', $headers, $data, [
            'signature_text' => 'Atsakingo asmens parašas',
            'signature_name' => '',
            'include_back_button' => false
        ]);
        echo '<div style="text-align:center; margin-top:10px;">Puslapis ' . ($page_num + 1) . ' iš ' . $total_pages . '</div>';
        echo '</div>';
    }
    
    echo '<script>window.onload = function() { window.print(); };</script>';
    exit;
}

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h1>Vertinimo lentelė</h1>
                    <a href="<?php echo SITE_URL; ?>/modules/reports/index.php" class="btn btn-secondary">Grįžti į ataskaitas</a>
                </div>
                <div class="card-body">
                    <?php display_message(); ?>

                    <form method="post" action="<?php echo SITE_URL; ?>/modules/reports/evaluation_sheets.php" class="mb-4">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="olympiad" class="form-label">Olimpiada</label>
                                    <select class="form-control" id="olympiad" name="olympiad" required>
                                        <option value="">Pasirinkite olimpiadą</option>
                                        <?php foreach ($olympiads as $oly): ?>
                                            <option value="<?php echo htmlspecialchars($oly['konkurso_pav']); ?>" <?php echo $selected_olympiad === $oly['konkurso_pav'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($oly['konkurso_pav']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6 d-flex align-items-end mb-3 gap-2">
                                <button type="submit" class="btn btn-primary">Rodyti lentelę</button>
                                <?php if (!empty($selected_olympiad) && !empty($participants)): ?>
                                    <a href="?print=1&olympiad=<?php echo urlencode($selected_olympiad); ?>" class="btn btn-outline-primary" target="_blank">Spausdinti su rezultatais</a>
                                    <a href="?print_empty=1&olympiad=<?php echo urlencode($selected_olympiad); ?>" class="btn btn-outline-secondary" target="_blank">Spausdinti tuščią</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>

                    <?php if (!empty($selected_olympiad) && !empty($participants)): ?>
                        <h2 class="text-center mb-4"><?php echo htmlspecialchars($selected_olympiad); ?></h2>
                        <form action="<?php echo SITE_URL; ?>/modules/reports/evaluation_sheets.php?olympiad=<?php echo urlencode($selected_olympiad); ?>" method="post">
                            <div class="mb-3">
                                <button type="submit" name="save_results" class="btn btn-success">Išsaugoti rezultatus</button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>KODAS</th>
                                            <th>I užd.</th><th>II užd.</th><th>III užd.</th><th>IV užd.</th>
                                            <th>V užd.</th><th>VI užd.</th><th>VII užd.</th><th>VIII užd.</th>
                                            <th>IX užd.</th><th>X užd.</th>
                                            <th style="width: 120px;">IŠ VISO BALŲ</th>
                                            <th style="width: 150px;">VIETA</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($participants as $participant): ?>
                                            <tr>
                                                <td><strong><?php echo $participant['reg_id']; ?></strong></td>
                                                <td>-</td><td>-</td><td>-</td><td>-</td><td>-</td>
                                                <td>-</td><td>-</td><td>-</td><td>-</td><td>-</td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" name="participant[<?php echo $participant['reg_id']; ?>][balai]" value="<?php echo htmlspecialchars($participant['Balai']); ?>">
                                                </td>
                                                <td>
                                                    <select class="form-select form-select-sm" name="participant[<?php echo $participant['reg_id']; ?>][vieta]">
                                                        <option value="">-</option>
                                                        <option value="I" <?php echo $participant['Vieta'] == 'I' ? 'selected' : ''; ?>>I</option>
                                                        <option value="II" <?php echo $participant['Vieta'] == 'II' ? 'selected' : ''; ?>>II</option>
                                                        <option value="III" <?php echo $participant['Vieta'] == 'III' ? 'selected' : ''; ?>>III</option>
                                                        <option value="laureat." <?php echo $participant['Vieta'] == 'laureat.' ? 'selected' : ''; ?>>Laureatas</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">
                                <button type="submit" name="save_results" class="btn btn-success">Išsaugoti rezultatus</button>
                            </div>
                        </form>
                        <p class="mt-3 text-muted">Iš viso vertinamų darbų: <strong><?php echo $participant_count; ?></strong></p>
                    <?php elseif (!empty($selected_olympiad)): ?>
                        <div class="alert alert-info">Nėra užregistruotų dalyvių šioje olimpiadoje.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>