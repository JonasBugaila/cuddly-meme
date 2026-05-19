<?php
/**
 * Olimpiadų protokolų ataskaitos puslapis
 * * Šis failas atvaizduoja olimpiadų protokolus su dalyvių informacija
 * ir suteikia spausdinimo funkcionalumą
 */

// Įtraukiame konfigūracijos failus
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Rodome visas klaidas
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Tikriname, ar vartotojas prisijungęs
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

// Gauname filtrų reikšmes iš POST arba GET (spausdinimo režimui)
$selected_olympiad = isset($_POST['olympiad']) ? trim(sanitize_input($_POST['olympiad'])) : '';
$print_mode = isset($_GET['print']) && $_GET['print'] == '1';
if ($print_mode && isset($_GET['olympiad'])) {
    $selected_olympiad = trim(sanitize_input($_GET['olympiad']));
}

// Registruojame POST duomenis derinimui
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST data received in protocols.php: " . json_encode($_POST));
}

// Gauname olimpiadų sąrašą filtrui
$sql = "SELECT DISTINCT konkurso_pav FROM konkursai ORDER BY konkurso_pav";
$stmt = db_query($sql);
if (!$stmt) {
    error_log("Failed to fetch olympiads: " . db_connect()->error);
    set_message('Klaida gaunant olimpiadų sąrašą', 'error');
}
$olympiads = db_get_results($stmt);

// Gauname dalyvių sąrašą
$grouped_participants = [];
if (!empty($selected_olympiad)) {
    $sql = "
        SELECT d.reg_id, d.konkurso_pav, d.1_vardas, d.1_pavarde, d.1_klase, 
               m.pavadinimas AS mokykla, d.Balai, d.Vieta
        FROM dalyviai d
        LEFT JOIN mokyklos m ON d.var_mokykla = m.mokyklos_id
    ";
    $params = [];
    $param_types = '';

    if ($selected_olympiad !== 'all') {
        $sql .= " WHERE d.konkurso_pav = ?";
        $params[] = $selected_olympiad;
        $param_types .= 's';
    } else {
        $sql .= " WHERE 1=1"; // Pradinis filtras, jei 'all'
    }

    if (!is_admin()) {
        // Paprastas vartotojas mato tik savo mokyklos dalyvius
        if (!isset($_SESSION['user_id'])) {
            set_message('Jūsų sesijos duomenys neteisingi. Prašome prisijungti iš naujo.', 'error');
            redirect(SITE_URL . '/modules/auth/login.php');
        }
        // Gauname vartotojo mokyklą iš duomenų bazės
        $user_sql = "SELECT var_mokykla FROM vartotojas WHERE vart_id = ?";
        $user_stmt = db_query($user_sql, [$_SESSION['user_id']], 's');
        $user_data = db_get_row($user_stmt);
        
        if (!$user_data || empty($user_data['var_mokykla'])) {
            set_message('Jūsų mokykla nenurodyta. Susisiekite su administratoriumi.', 'error');
            redirect(SITE_URL);
        }
        
        $user_school = $user_data['var_mokykla'];
        $sql .= " AND d.var_mokykla = ?";
        $params[] = $user_school;
        $param_types .= 's';
    }

    $sql .= " ORDER BY d.konkurso_pav, d.Vieta, d.Balai DESC, d.1_vardas, d.1_pavarde";
    $stmt = db_query($sql, $params, $param_types);
    if (!$stmt) {
        error_log("Failed to fetch participants: " . db_connect()->error . " | SQL: $sql | Params: " . json_encode($params));
        set_message('Klaida gaunant dalyvių sąrašą', 'error');
    } else {
        $participants = db_get_results($stmt);
        // Grupuojame dalyvius pagal olimpiadą
        foreach ($participants as $participant) {
            $olympiad = $participant['konkurso_pav'];
            $grouped_participants[$olympiad][] = $participant;
        }
    }

    // Patikriname, ar filtrai grąžino rezultatus
    if (empty($grouped_participants)) {
        set_message('Nerasta dalyvių pasirinktai olimpiadai', 'warning');
    }
}

// Jei esame spausdinimo režime, generuojame spausdinamą lentelę
if ($print_mode && !empty($grouped_participants)) {
    header('Content-Type: text/html; charset=UTF-8');

    $headers = ['Eil. Nr.', 'Mokinio vardas, pavardė', 'Klasė', 'Mokykla', 'Balai', 'Vieta'];
    $institution = '';

    echo '<style>
        @media print {
            .olympiad-section {
                page-break-after: always;
            }
            .olympiad-section:last-child {
                page-break-after: auto;
            }
            .olympiad-title {
                text-align: center;
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 10px;
            }
            .protocol-number {
                text-align: center;
                font-size: 14px;
                margin-bottom: 20px;
            }
        }
    </style>';

    echo '<div class="protocol-number">Protokolo Nr. _________</div>';

    foreach ($grouped_participants as $olympiad => $participants) {

        // Suskaidom po 15 eilučių
        $chunks = array_chunk($participants, 15);
        $total_pages = count($chunks);

        $global_index = 1;

        foreach ($chunks as $page_num => $chunk) {

            $data = [];

            foreach ($chunk as $participant) {
                $data[] = [
                    $global_index++,
                    $participant['1_vardas'] . ' ' . $participant['1_pavarde'],
                    $participant['1_klase'] ?? '-',
                    $participant['mokykla'] ?? '-',
                    $participant['Balai'] ?? '-',
                    $participant['Vieta'] ?? '-'
                ];
            }

            echo '<div class="olympiad-section">';

            echo generate_printable_table($olympiad, $institution, $headers, $data, [
                'signature_text' => 'Atsakingo asmens parašas',
                'signature_name' => '',
                'include_back_button' => false,
                'back_button_text' => 'Grįžti'
            ]);

            // Puslapio numeracija
            echo '<div style="text-align:center; margin-top:10px;">
                Puslapis ' . ($page_num + 1) . ' iš ' . $total_pages . '
            </div>';

            echo '</div>';
        }
    }

    // Automatiškai iškviečiame spausdinimo langą užsikrovus puslapiui
    echo '<script type="text/javascript">
        window.onload = function() { 
            window.print(); 
        };
    </script>';

    exit;
}

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h1>Protokolai</h1>
                <div>
                    <?php if (!empty($grouped_participants)): ?>
                        <a href="<?php echo SITE_URL; ?>/modules/reports/protocols.php?olympiad=<?php echo urlencode($selected_olympiad); ?>&print=1" target="_blank" class="btn btn-primary">Spausdinti</a>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>/modules/reports/index.php" class="btn btn-secondary">Grįžti į ataskaitas</a>
                </div>
            </div>
            <div class="card-body">
                <form action="<?php echo SITE_URL; ?>/modules/reports/protocols.php" method="post" class="mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label for="olympiad" class="form-label">Olimpiada</label>
                                <select class="form-control" id="olympiad" name="olympiad" required>
                                    <option value="">Pasirinkite olimpiadą</option>
                                    <option value="all" <?php echo $selected_olympiad === 'all' ? 'selected' : ''; ?>>Visos olimpiados</option>
                                    <?php foreach ($olympiads as $olympiad): ?>
                                        <option value="<?php echo htmlspecialchars($olympiad['konkurso_pav']); ?>" <?php echo $selected_olympiad === $olympiad['konkurso_pav'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($olympiad['konkurso_pav']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Rodyti protokolą</button>
                    </form>

                    <?php if (!empty($grouped_participants)): ?>
                        <?php foreach ($grouped_participants as $olympiad => $participants): ?>
                            <div class="mb-4">
                                <h3><?php echo htmlspecialchars($olympiad); ?></h3>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Eil. Nr.</th>
                                                <th>Mokinio vardas, pavardė</th>
                                                <th>Klasė</th>
                                                <th>Mokykla</th>
                                                <th>Balai</th>
                                                <th>Vieta</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $index = 1; ?>
                                            <?php foreach ($participants as $participant): ?>
                                                <tr>
                                                    <td><?php echo $index++; ?></td>
                                                    <td><?php echo htmlspecialchars($participant['1_vardas'] . ' ' . $participant['1_pavarde']); ?></td>
                                                    <td><?php echo htmlspecialchars($participant['1_klase'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($participant['mokykla'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($participant['Balai'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($participant['Vieta'] ?? '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif (!empty($selected_olympiad)): ?>
                        <div class="alert alert-info">
                            <p>Nėra dalyvių pasirinktai olimpiadai.</p>
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