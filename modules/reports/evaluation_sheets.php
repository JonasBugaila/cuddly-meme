<?php
/**
 * Vertinimo lentelės puslapis
 * 
 * Šis failas atvaizduoja olimpiadų vertinimo lentelę su užduočių balais,
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

// Gauname filtrų reikšmes iš POST arba GET (spausdinimo režimui)
$selected_olympiad = isset($_POST['olympiad']) ? trim(sanitize_input($_POST['olympiad'])) : '';
$print_mode = isset($_GET['print']) && $_GET['print'] == '1';
$print_empty_mode = isset($_GET['print_empty']) && $_GET['print_empty'] == '1';
if ($print_mode && isset($_GET['olympiad'])) {
    $selected_olympiad = trim(sanitize_input($_GET['olympiad']));
}
if ($print_empty_mode && isset($_GET['olympiad'])) {
    $selected_olympiad = trim(sanitize_input($_GET['olympiad']));
}

// Apdorojame rezultatų įvedimą
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_results']) && is_admin()) {
    foreach ($_POST['participant'] as $reg_id => $data) {
        $balai = !empty($data['balai']) ? sanitize_input($data['balai']) : '';
        $vieta = !empty($data['vieta']) ? sanitize_input($data['vieta']) : '';
        
        // Atnaujiname rezultatus
        $sql = "UPDATE dalyviai SET Balai = ?, Vieta = ? WHERE reg_id = ?";
        $stmt = db_query($sql, [$balai, $vieta, $reg_id]);
    }
    
    set_message('Rezultatai sėkmingai išsaugoti', 'success');
    redirect(SITE_URL . '/modules/reports/evaluation_sheets.php?olympiad=' . urlencode($selected_olympiad));
}

// Gauname olimpiadų sąrašą filtrui
$sql = "SELECT DISTINCT konkurso_pav FROM konkursai ORDER BY konkurso_pav";
$stmt = db_query($sql);
if (!$stmt) {
    error_log("Failed to fetch olympiads: " . db_connect()->error);
    set_message('Klaida gaunant olimpiadų sąrašą', 'error');
}
$olympiads = db_get_results($stmt);

// Gauname dalyvių rezultatus
$participants = [];
$participant_count = 0;
if (!empty($selected_olympiad)) {
    $sql = "SELECT reg_id, konkurso_pav, Balai, Vieta FROM dalyviai WHERE konkurso_pav = ? ORDER BY reg_id ASC";
    $stmt = db_query($sql, [$selected_olympiad]);
    if ($stmt) {
        $participants = db_get_results($stmt);
        $participant_count = count($participants);
    } else {
        error_log("Failed to fetch participants: " . db_connect()->error);
        set_message('Klaida gaunant dalyvių sąrašą', 'error');
    }
}

// ==================== SPAUSDINIMAS SU DUOMENIMIS (landscape) ====================
if ($print_mode && !empty($selected_olympiad) && !empty($participants)) {
    header('Content-Type: text/html; charset=UTF-8');
    $headers = ['KODAS', 'I užd.', 'II užd.', 'III užd.', 'IV užd.', 'V užd.', 'VI užd.', 'VII užd.', 'VIII užd.', 'IX užd.', 'X užd.', 'IŠ VISO BALŲ', 'VIETA'];
    ?>
    <style>
        @media print {
            @page {
                size: landscape;
                margin: 1cm;
            }
            body {
                font-size: 10pt;
            }
            table {
                font-size: 9pt;
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid #000;
                padding: 4px;
                text-align: center;
            }
            .evaluation-section {
                page-break-after: always;
            }
            .evaluation-section:last-child {
                page-break-after: auto;
            }
            .evaluation-title {
                text-align: center;
                font-size: 16px;
                font-weight: bold;
                margin-bottom: 10px;
            }
            .protocol-number {
                text-align: center;
                font-size: 12px;
                margin-bottom: 20px;
            }
        }
    </style>
    <?php
    // Padaliname po 15 eilučių
    $chunks = array_chunk($participants, 15);
    $total_pages = count($chunks);

    foreach ($chunks as $page_num => $chunk) {
        $data = [];
        foreach ($chunk as $participant) {
            $row = [
                $participant['reg_id'],
                '-', '-', '-', '-', '-', '-', '-', '-', '-', '-',
                $participant['Balai'] ?? '-',
                $participant['Vieta'] ?? '-'
            ];
            $data[] = $row;
        }

        echo '<div class="evaluation-section">';
       // echo '<div class="evaluation-title">' . htmlspecialchars($selected_olympiad) . '</div>';
      ///  echo '<div class="protocol-number">Protokolo Nr. _________</div>';
        echo generate_printable_table($selected_olympiad, '', $headers, $data, [
            'signature_text' => 'Atsakingo asmens parašas',
            'signature_name' => '',
            'include_back_button' => false
        ]);
        echo '<div style="text-align:center; margin-top:10px;">Puslapis ' . ($page_num + 1) . ' iš ' . $total_pages . '</div>';
        echo '</div>';
    }
    exit;
}

// ==================== TUŠČIO PROTOKOLO SPAUSDINIMAS (landscape) ====================
if ($print_empty_mode && !empty($selected_olympiad) && !empty($participants)) {
    header('Content-Type: text/html; charset=UTF-8');
    $headers = ['KODAS', 'I užd.', 'II užd.', 'III užd.', 'IV užd.', 'V užd.', 'VI užd.', 'VII užd.', 'VIII užd.', 'IX užd.', 'X užd.', 'IŠ VISO BALŲ', 'VIETA'];
    ?>
    <style>
        @media print {
            @page {
                size: landscape;
                margin: 1cm;
            }
            body {
                font-size: 10pt;
            }
            table {
                font-size: 9pt;
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid #000;
                padding: 4px;
                text-align: center;
            }
            .evaluation-section {
                page-break-after: always;
            }
            .evaluation-section:last-child {
                page-break-after: auto;
            }
            .evaluation-title {
                text-align: center;
                font-size: 16px;
                font-weight: bold;
                margin-bottom: 10px;
            }
            .protocol-number {
                text-align: center;
                font-size: 12px;
                margin-bottom: 20px;
            }
        }
    </style>
    <?php
    $chunks = array_chunk($participants, 15);
    $total_pages = count($chunks);

    foreach ($chunks as $page_num => $chunk) {
        $data = [];
        foreach ($chunk as $participant) {
            $row = [
                $participant['reg_id'],
                '', '', '', '', '', '', '', '', '', '',
                '',
                ''
            ];
            $data[] = $row;
        }

        echo '<div class="evaluation-section">';
        //echo '<div class="evaluation-title">' . htmlspecialchars($selected_olympiad) . '</div>';
        //echo '<div class="protocol-number">Protokolo Nr. _________</div>';
        echo generate_printable_table($selected_olympiad, '', $headers, $data, [
            'signature_text' => 'Atsakingo asmens parašas',
            'signature_name' => '',
            'include_back_button' => false
        ]);
        echo '<div style="text-align:center; margin-top:10px;">Puslapis ' . ($page_num + 1) . ' iš ' . $total_pages . '</div>';
        echo '</div>';
    }
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

                    <!-- Olimpiados pasirinkimo forma -->
                    <form method="post" action="<?php echo SITE_URL; ?>/modules/reports/evaluation_sheets.php" class="mb-4">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="olympiad" class="form-label">Olimpiada</label>
                                    <select class="form-control" id="olympiad" name="olympiad" required>
                                        <option value="">Pasirinkite olimpiadą</option>
                                        <?php foreach ($olympiads as $olympiad): ?>
                                            <option value="<?php echo htmlspecialchars($olympiad['konkurso_pav']); ?>" <?php echo $selected_olympiad === $olympiad['konkurso_pav'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($olympiad['konkurso_pav']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Rodyti lentelę</button>
                        <?php if (!empty($selected_olympiad) && !empty($participants)): ?>
                            <a href="<?php echo SITE_URL; ?>/modules/reports/evaluation_sheets.php?print=1&olympiad=<?php echo urlencode($selected_olympiad); ?>" class="btn btn-primary" target="_blank">Spausdinti</a>
                            <a href="<?php echo SITE_URL; ?>/modules/reports/evaluation_sheets.php?print_empty=1&olympiad=<?php echo urlencode($selected_olympiad); ?>" class="btn btn-primary" target="_blank">Spausdinti protokolą pildymui</a>
                        <?php endif; ?>
                    </form>

                    <!-- Vertinimo lentelė -->
                    <?php if (!empty($selected_olympiad) && !empty($participants)): ?>
                        <h2 class="text-center mb-4"><?php echo htmlspecialchars($selected_olympiad); ?></h2>
                        <?php if (is_admin()): ?>
                            <form action="<?php echo SITE_URL; ?>/modules/reports/evaluation_sheets.php?olympiad=<?php echo urlencode($selected_olympiad); ?>" method="post">
                                <div class="mb-3">
                                    <button type="submit" name="save_results" class="btn btn-primary">Išsaugoti rezultatus</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>KODAS</th>
                                                <th>I užd.</th>
                                                <th>II užd.</th>
                                                <th>III užd.</th>
                                                <th>IV užd.</th>
                                                <th>V užd.</th>
                                                <th>VI užd.</th>
                                                <th>VII užd.</th>
                                                <th>VIII užd.</th>
                                                <th>IX užd.</th>
                                                <th>X užd.</th>
                                                <th>IŠ VISO BALŲ</th>
                                                <th>VIETA</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($participants as $participant): ?>
                                                <tr>
                                                    <td><?php echo $participant['reg_id']; ?></td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>
                                                        <input type="number" class="form-control" name="participant[<?php echo $participant['reg_id']; ?>][balai]" value="<?php echo $participant['Balai']; ?>" min="0" max="100">
                                                    </td>
                                                    <td>
                                                        <select class="form-control" name="participant[<?php echo $participant['reg_id']; ?>][vieta]">
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
                            </form>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>KODAS</th>
                                            <th>I užd.</th>
                                            <th>II užd.</th>
                                            <th>III užd.</th>
                                            <th>IV užd.</th>
                                            <th>V užd.</th>
                                            <th>VI užd.</th>
                                            <th>VII užd.</th>
                                            <th>VIII užd.</th>
                                            <th>IX užd.</th>
                                            <th>X užd.</th>
                                            <th>IŠ VISO BALŲ</th>
                                            <th>VIETA</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($participants as $participant): ?>
                                            <tr>
                                                <td><?php echo $participant['reg_id']; ?></td>
                                                <td>-</td>
                                                <td>-</td>
                                                <td>-</td>
                                                <td>-</td>
                                                <td>-</td>
                                                <td>-</td>
                                                <td>-</td>
                                                <td>-</td>
                                                <td>-</td>
                                                <td>-</td>
                                                <td><?php echo $participant['Balai'] ?? '-'; ?></td>
                                                <td>
                                                    <?php if (!empty($participant['Vieta'])): ?>
                                                        <span class="badge bg-primary"><?php echo $participant['Vieta']; ?></span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        <p class="mt-3">Dalyvių skaičius: <?php echo $participant_count; ?></p>
                    <?php elseif (!empty($selected_olympiad)): ?>
                        <div class="alert alert-info">
                            <p>Nėra užregistruotų dalyvių šioje olimpiadoje.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Įtraukiame poraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
?>