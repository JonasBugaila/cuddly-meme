<?php
/**
 * Olimpiadų protokolų ataskaitos puslapis
 * Šis failas atvaizduoja olimpiadų protokolus su dalyvių informacija
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

// Ar olimpiada patvirtinta pagal ŠMSM (jei pasirinkta konkreti)
$is_smsm = false;
if (!empty($selected_olympiad) && $selected_olympiad !== 'all') {
    $stmt_oly = db_query("SELECT * FROM konkursai WHERE konkurso_pav = ?", [$selected_olympiad]);
    $olympiad_data = $stmt_oly ? db_get_row($stmt_oly) : null;
    $is_smsm = (isset($olympiad_data['smsm_patvirtintas']) && $olympiad_data['smsm_patvirtintas'] == 1);
}

// Gauname dalyvių sąrašą
$grouped_participants = [];
if (!empty($selected_olympiad)) {
    // Pridėtas "1_mok" (mokytojas) ir "kitas_etapas" laukų paėmimas spausdinimui
    $sql = "
        SELECT d.reg_id, d.konkurso_pav, d.1_vardas, d.1_pavarde, d.1_klase, 
               d.1_mok, d.kitas_etapas, m.pavadinimas AS mokykla, d.Balai, d.Vieta
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
        $sql .= " WHERE 1=1"; 
    }

    if (!is_admin()) {
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

    $sql .= " ORDER BY d.konkurso_pav, FIELD(d.Vieta, 'I','II','III','laureat.'), CAST(d.Balai AS UNSIGNED) DESC, d.1_vardas, d.1_pavarde";
    
    $stmt = db_query($sql, $params, $param_types);
    
    if ($stmt) {
        $participants = db_get_results($stmt);
        foreach ($participants as $participant) {
            $olympiad = $participant['konkurso_pav'];
            $grouped_participants[$olympiad][] = $participant;
        }
    }

    if (empty($grouped_participants)) {
        set_message('Nerasta dalyvių pasirinktai olimpiadai.', 'warning');
    }
}

// ==================== SPAUSDINIMAS ====================
if ($print_mode && !empty($grouped_participants)) {
    header('Content-Type: text/html; charset=UTF-8');

    // Dinaminės antraštės spausdinimui
    if ($is_smsm) {
        $headers = ['Vieta', 'Mokinio vardas, pavardė', 'Klasė', 'Mokykla', 'Ruošęs mokytojas', 'Balai', 'Kitas etapas'];
    } else {
        $headers = ['Vieta', 'Mokinio vardas, pavardė', 'Klasė', 'Mokykla', 'Ruošęs mokytojas', 'Balai'];
    }

    foreach ($grouped_participants as $olympiad_name => $participants) {
        
        $chunks = array_chunk($participants, 15);
        $total_pages = count($chunks);

        foreach ($chunks as $page_num => $chunk) {
            $data = [];
            foreach ($chunk as $p) {
                // Saugus tuščių reikšmių apdorojimas (Apsauga nuo PHP 8 Deprecated klaidų)
                $balai = (isset($p['Balai']) && $p['Balai'] !== '') ? $p['Balai'] : '—';
                $vieta = (!empty($p['Vieta'])) ? $p['Vieta'] : '—';
                $mokytojas = (!empty($p['1_mok'])) ? $p['1_mok'] : '—';
                
                $row = [
                    htmlspecialchars($vieta),
                    htmlspecialchars(($p['1_vardas'] ?? '') . ' ' . ($p['1_pavarde'] ?? '')),
                    htmlspecialchars($p['1_klase'] ?? '-'),
                    htmlspecialchars($p['mokykla'] ?? '-'),
                    htmlspecialchars($mokytojas),
                    htmlspecialchars($balai)
                ];
                
                if ($is_smsm) {
                    // Konvertuojame skaičių (iš DB) į žodį spausdinimui
                    $kitas_etapas_tekstas = '—';
                    if (isset($p['kitas_etapas'])) {
                        if ($p['kitas_etapas'] == 1) {
                            $kitas_etapas_tekstas = 'Siunčiamas';
                        } elseif ($p['kitas_etapas'] == 2) {
                            $kitas_etapas_tekstas = 'Nesiunčiamas';
                        }
                    }
                    $row[] = htmlspecialchars($kitas_etapas_tekstas);
                }
                
                $data[] = $row;
            }

            echo '<div class="olympiad-section" style="page-break-after: always;">';
            
            // IŠTAISYTA: Pridėtas 'protocol' parametras spausdinimo funkcijai
            echo generate_printable_table($olympiad_name, '', $headers, $data, [
                'signature_text' => 'Atsakingo asmens parašas',
                'signature_name' => '',
                'include_back_button' => false,
                'back_button_text' => 'Grįžti'
            ], 'protocol');
            
            echo '<div style="text-align:center; margin-top:10px;">Puslapis ' . ($page_num + 1) . ' iš ' . $total_pages . '</div>';
            echo '</div>';
        }
    }

    exit; // Nutraukiame vykdymą, kad neužkrautų vizualinės dalies spausdinimo metu
}

// Įtraukiame antraštę naršyklės vaizdui
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row mb-5">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
                <h1 class="h4 mb-0 text-white"><i class="fas fa-file-alt"></i> Rezultatų protokolai</h1>
                <div>
                    <!-- Pašalintas target="_blank", kad atsidarytų ir iššoktų toje pačioje kortelėje -->
                    <?php if (!empty($grouped_participants)): ?>
                        <a href="<?php echo SITE_URL; ?>/modules/reports/protocols.php?olympiad=<?php echo urlencode($selected_olympiad); ?>&print=1" class="btn btn-sm btn-light text-primary fw-bold me-2"><i class="fas fa-print"></i> Spausdinti protokolą</a>
                    <?php endif; ?>
                    <button type="button" onclick="window.history.back();" class="btn btn-sm btn-light text-primary fw-bold"><i class="fas fa-arrow-left"></i> Grįžti atgal</button>
                </div>
            </div>
            <div class="card-body bg-light">
                <?php display_message(); ?>
                
                <form action="<?php echo SITE_URL; ?>/modules/reports/protocols.php" method="post" class="mb-4 bg-white p-3 border rounded">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label for="olympiad" class="form-label fw-bold text-primary">Pasirinkite olimpiadą</label>
                                <select class="form-select border-primary" id="olympiad" name="olympiad" required>
                                    <option value="">-- Pasirinkite --</option>
                                    <option value="all" <?php echo $selected_olympiad === 'all' ? 'selected' : ''; ?>>Visos olimpiados (Bendras sąrašas)</option>
                                    <?php foreach ($olympiads as $oly): ?>
                                        <option value="<?php echo htmlspecialchars($oly['konkurso_pav']); ?>" <?php echo $selected_olympiad === $oly['konkurso_pav'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($oly['konkurso_pav']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end mb-0 mt-3 mt-md-0">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Rodyti protokolą naršyklėje</button>
                        </div>
                    </div>
                </form>

                <?php if (!empty($grouped_participants)): ?>
                    <?php foreach ($grouped_participants as $olympiad => $participants): ?>
                        <div class="mb-4 bg-white p-4 border rounded shadow-sm">
                            <h3 class="mb-3 text-dark border-bottom pb-2"><?php echo htmlspecialchars($olympiad); ?></h3>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle border">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 60px;">#</th>
                                            <th>Mokinio vardas, pavardė</th>
                                            <th>Klasė</th>
                                            <th>Mokykla</th>
                                            <th>Ruošęs mokytojas</th>
                                            <th>Balai</th>
                                            <th>Vieta</th>
                                            <?php if ($is_smsm): ?>
                                                <th class="border-start border-info bg-info bg-opacity-10">Kitas etapas</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $index = 1; ?>
                                        <?php foreach ($participants as $participant): ?>
                                            <tr>
                                                <td class="text-muted"><?php echo $index++; ?></td>
                                                <td><strong><?php echo htmlspecialchars(($participant['1_vardas'] ?? '') . ' ' . ($participant['1_pavarde'] ?? '')); ?></strong></td>
                                                <td><?php echo htmlspecialchars($participant['1_klase'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($participant['mokykla'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($participant['1_mok'] ?? '-'); ?></td>
                                                <td><span class="badge bg-secondary fs-6"><?php echo htmlspecialchars((isset($participant['Balai']) && $participant['Balai'] !== '') ? $participant['Balai'] : '-'); ?></span></td>
                                                <td>
                                                    <?php if (!empty($participant['Vieta'])): ?>
                                                        <?php $v_color = in_array($participant['Vieta'], ['I','II','III','laureat.']) ? 'bg-warning text-dark' : 'bg-primary'; ?>
                                                        <span class="badge <?php echo $v_color; ?> fs-6"><?php echo htmlspecialchars($participant['Vieta']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php if ($is_smsm): ?>
                                                    <td class="border-start border-info bg-info bg-opacity-10">
                                                        <?php if (isset($participant['kitas_etapas']) && $participant['kitas_etapas'] == 1): ?>
                                                            <span class="badge bg-success">Siunčiamas</span>
                                                        <?php elseif (isset($participant['kitas_etapas']) && $participant['kitas_etapas'] == 2): ?>
                                                            <span class="badge bg-danger">Nesiunčiamas</span>
                                                        <?php else: ?>
                                                            <span class="text-muted">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
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