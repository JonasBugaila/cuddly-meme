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

// Tikriname, ar vartotojas prisijungęs
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

// Gauname filtrų reikšmes
$selected_olympiad = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['olympiad'])) {
    $selected_olympiad = trim(sanitize_input($_POST['olympiad']));
} elseif (isset($_GET['olympiad'])) {
    $selected_olympiad = trim(sanitize_input($_GET['olympiad']));
}

$print_mode = isset($_GET['print']) && $_GET['print'] == '1';

// Gauname olimpiadų sąrašą filtrui
$sql = "SELECT DISTINCT konkurso_pav FROM konkursai ORDER BY konkurso_pav";
$stmt = db_query($sql);
$olympiads = $stmt ? db_get_results($stmt) : [];

// Gauname dalyvių sąrašą
$grouped_participants = [];
if (!empty($selected_olympiad)) {
    $sql = "
        SELECT d.reg_id, d.konkurso_pav, d.1_vardas, d.1_pavarde, d.1_klase, 
               m.pavadinimas AS mokykla, d.Balai, d.Vieta
        FROM dalyviai d
        LEFT JOIN mokyklos m ON d.var_mokykla = m.pavadinimas
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
        $user_sql = "SELECT var_mokykla FROM vartotojas WHERE vart_id = ?";
        $user_stmt = db_query($user_sql, [$_SESSION['user_id']], 's');
        $user_data = $user_stmt ? db_get_row($user_stmt) : null;
        
        if (!$user_data || empty($user_data['var_mokykla'])) {
            set_message('Jūsų mokykla nenurodyta. Susisiekite su administratoriumi.', 'error');
            redirect(SITE_URL);
        }
        
        $sql .= " AND d.var_mokykla = ?";
        $params[] = $user_data['var_mokykla'];
        $param_types .= 's';
    }

    // Rūšiuojame: pirmiausia I, II, III vietos, tada pagal balus
    $sql .= " ORDER BY d.konkurso_pav, FIELD(d.Vieta, 'I','II','III','laureat.'), CAST(d.Balai AS UNSIGNED) DESC, d.1_vardas, d.1_pavarde";
    
    $stmt = db_query($sql, $params, $param_types);
    
    if ($stmt) {
        $participants = db_get_results($stmt);
        foreach ($participants as $participant) {
            $olympiad = $participant['konkurso_pav'];
            $grouped_participants[$olympiad][] = $participant;
        }
    }

    // Patikriname, ar filtrai grąžino rezultatus
    if (empty($grouped_participants)) {
        set_message('Nerasta dalyvių pasirinktai olimpiadai.', 'warning');
    }
}

// Jei esame spausdinimo režime
if ($print_mode && !empty($grouped_participants)) {
    header('Content-Type: text/html; charset=UTF-8');

    $headers = ['Eil. Nr.', 'Mokinio vardas, pavardė', 'Klasė', 'Mokykla', 'Balai', 'Vieta'];
    $institution = '';

    echo '<style>
        @media print {
            .olympiad-section { page-break-after: always; }
            .olympiad-section:last-child { page-break-after: auto; }
            .protocol-number { text-align: center; font-size: 14px; margin-bottom: 20px; }
        }
    </style>';

    echo '<div class="protocol-number">Protokolo Nr. _________</div>';

    foreach ($grouped_participants as $olympiad => $participants) {
        // Suskaidom po 15 eilučių, kad geriau tilptų lape
        $chunks = array_chunk($participants, 15);
        $total_pages = count($chunks);
        $global_index = 1;

        foreach ($chunks as $page_num => $chunk) {
            $data = [];
            foreach ($chunk as $participant) {
                $data[] = [
                    $global_index++,
                    htmlspecialchars($participant['1_vardas'] . ' ' . $participant['1_pavarde']),
                    htmlspecialchars($participant['1_klase'] ?? '-'),
                    htmlspecialchars($participant['mokykla'] ?? '-'),
                    htmlspecialchars($participant['Balai'] ?? '-'),
                    htmlspecialchars($participant['Vieta'] ?? '-')
                ];
            }

            echo '<div class="olympiad-section">';
            echo generate_printable_table($olympiad, $institution, $headers, $data, [
                'signature_text' => 'Atsakingo asmens parašas',
                'signature_name' => '',
                'include_back_button' => false,
                'back_button_text' => 'Grįžti'
            ]);

            echo '<div style="text-align:center; margin-top:10px;">
                Puslapis ' . ($page_num + 1) . ' iš ' . $total_pages . '
            </div>';
            echo '</div>';
        }
    }

    echo '<script type="text/javascript">
        window.onload = function() { window.print(); };
    </script>';
    exit;
}

// Įtraukiame antraštę naršyklės vaizdui
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
                <?php display_message(); ?>
                <form action="<?php echo SITE_URL; ?>/modules/reports/protocols.php" method="post" class="mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label for="olympiad" class="form-label">Olimpiada</label>
                                <select class="form-control" id="olympiad" name="olympiad" required>
                                    <option value="">Pasirinkite olimpiadą</option>
                                    <option value="all" <?php echo $selected_olympiad === 'all' ? 'selected' : ''; ?>>Visos olimpiados</option>
                                    <?php foreach ($olympiads as $oly): ?>
                                        <option value="<?php echo htmlspecialchars($oly['konkurso_pav']); ?>" <?php echo $selected_olympiad === $oly['konkurso_pav'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($oly['konkurso_pav']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end mb-3">
                            <button type="submit" class="btn btn-primary">Rodyti protokolą</button>
                        </div>
                    </div>
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
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>