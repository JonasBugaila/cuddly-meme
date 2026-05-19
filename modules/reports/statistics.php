<?php
/**
 * Mokyklų statistikos puslapis
 * 
 * Šis failas atvaizduoja mokyklų statistikas pagal olimpiadas
 * (dalyviai, vidutiniai balai, prizininkai)
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname ar vartotojas prisijungęs
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

// Gauname filtrus
$selected_olympiad = isset($_GET['olympiad']) ? sanitize_input($_GET['olympiad']) : '';
$print_mode = isset($_GET['print']) && $_GET['print'] == '1';

// Gauname olimpiadų sąrašą
$sql = "SELECT DISTINCT konkurso_pav FROM konkursai ORDER BY konkurso_pav ASC";
$stmt = db_query($sql);
$olympiads = db_get_results($stmt);

// Gauname statistikas
$stats = [];
if (!empty($selected_olympiad) || is_admin()) {
    $sql = "
        SELECT 
            m.pavadinimas AS mokykla,
            COALESCE(k.konkurso_pav, 'Visos olimpiados') AS olimpiada,
            COUNT(d.reg_id) AS dalyviu_skaicius,
            ROUND(AVG(CAST(COALESCE(d.Balai, '0') AS INTEGER)), 2) AS vidutiniai_balai,
            COUNT(CASE WHEN d.Vieta IN ('I', 'II', 'III', 'laureat.') THEN 1 END) AS prizininku_skaicius
        FROM mokyklos m
        LEFT JOIN dalyviai d ON m.pavadinimas = d.var_mokykla
        LEFT JOIN konkursai k ON d.konkurso_pav = k.konkurso_pav
    ";
    $params = [];
    $param_types = '';

    if (!is_admin()) {
        // Paprastas vartotojas mato tik savo mokyklą
        if (!isset($_SESSION['user_id'])) {
            set_message('Jūsų sesijos duomenys neteisingi. Prašome prisijungti iš naujo.', 'error');
            redirect(SITE_URL . '/modules/auth/login.php');
        }
        $user_sql = "SELECT var_mokykla FROM vartotojas WHERE vart_id = ?";
        $user_stmt = db_query($user_sql, [$_SESSION['user_id']], 's');
        $user_data = db_get_row($user_stmt);
        
        if (!$user_data || empty($user_data['var_mokykla'])) {
            set_message('Jūsų mokykla nenurodyta. Susisiekite su administratoriumi.', 'error');
            redirect(SITE_URL . '/modules/reports/index.php');
        }
        
        $sql .= " WHERE m.pavadinimas = ?";
        $params[] = $user_data['var_mokykla'];
        $param_types = 's';
    } elseif (!empty($selected_olympiad)) {
        $sql .= " WHERE k.konkurso_pav = ?";
        $params[] = $selected_olympiad;
        $param_types = 's';
    }

    $sql .= " GROUP BY m.pavadinimas, k.konkurso_pav HAVING dalyviu_skaicius > 0 ORDER BY m.pavadinimas, k.konkurso_pav";
    $stmt = db_query($sql, $params, $param_types);
    $stats = db_get_results($stmt);
}

// Bendros statistikos
$total_stats = [];
if (is_admin() || empty($selected_olympiad)) {
    $total_sql = "
        SELECT 
            COUNT(DISTINCT m.pavadinimas) AS mokyklu_skaicius,
            COUNT(d.reg_id) AS bendras_dalyviu_skaicius,
            ROUND(AVG(CAST(COALESCE(d.Balai, '0') AS INTEGER)), 2) AS bendri_vidutiniai_balai
        FROM mokyklos m
        LEFT JOIN dalyviai d ON m.pavadinimas = d.var_mokykla
    ";
    if (!is_admin()) {
        $user_sql = "SELECT var_mokykla FROM vartotojas WHERE vart_id = ?";
        $user_stmt = db_query($user_sql, [$_SESSION['user_id']], 's');
        $user_data = db_get_row($user_stmt);
        if ($user_data && !empty($user_data['var_mokykla'])) {
            $total_sql .= " WHERE m.pavadinimas = ?";
            $total_params = [$user_data['var_mokykla']];
            $total_stmt = db_query($total_sql, $total_params, 's');
        } else {
            $total_stats = [['mokyklu_skaicius' => 0, 'bendras_dalyviu_skaicius' => 0, 'bendri_vidutiniai_balai' => 0]];
        }
    } else {
        $total_stmt = db_query($total_sql);
    }
    $total_stats = db_get_results($total_stmt) ?: [['mokyklu_skaicius' => 0, 'bendras_dalyviu_skaicius' => 0, 'bendri_vidutiniai_balai' => 0]];
    $total_stats = $total_stats[0];
}

// Jei spausdinimo režimas
if ($print_mode && !empty($stats)) {
    header('Content-Type: text/html; charset=UTF-8');
    $headers = ['Mokykla', 'Olimpiada', 'Dalyvių skaičius', 'Vidutiniai balai', 'Prizininkų skaičius'];
    $data = [];
    foreach ($stats as $stat) {
        $data[] = [
            $stat['mokykla'],
            $stat['olimpiada'],
            $stat['dalyviu_skaicius'],
            $stat['vidutiniai_balai'],
            $stat['prizininku_skaicius']
        ];
    }
    echo generate_printable_table('Mokyklų statistika', 'Švietimo pagalbos tarnyba', $headers, $data, [
        'signature_text' => 'Atsakingo asmens parašas',
        'include_back_button' => true
    ]);
    exit;
}

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h1>Mokyklų statistika</h1>
                <div>
                    <?php if (!empty($stats)): ?>
                        <a href="<?php echo SITE_URL; ?>/modules/reports/school_stats.php?olympiad=<?php echo urlencode($selected_olympiad); ?>&print=1" target="_blank" class="btn btn-primary">Spausdinti</a>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>/modules/reports/index.php" class="btn btn-secondary">Grįžti į ataskaitas</a>
                </div>
            </div>
            <div class="card-body">
                <!-- Filtrai -->
                <form action="<?php echo SITE_URL; ?>/modules/reports/school_stats.php" method="get" class="mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label for="olympiad" class="form-label">Olimpiada (tik administratoriams)</label>
                                <select class="form-control" id="olympiad" name="olympiad">
                                    <option value="">Visos olimpiados</option>
                                    <?php foreach ($olympiads as $o): ?>
                                        <option value="<?php echo htmlspecialchars($o['konkurso_pav']); ?>" <?php echo $selected_olympiad == $o['konkurso_pav'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($o['konkurso_pav']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block">Filtruoti</button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Bendros statistikos -->
                <?php if (!empty($total_stats['mokyklu_skaicius']) || is_admin()): ?>
                    <div class="alert alert-info mb-4">
                        <h5>Bendros statistikos</h5>
                        <p><strong>Mokyklų skaičius:</strong> <?php echo $total_stats['mokyklu_skaicius']; ?></p>
                        <p><strong>Bendras dalyvių skaičius:</strong> <?php echo $total_stats['bendras_dalyviu_skaicius']; ?></p>
                        <p><strong>Bendri vidutiniai balai:</strong> <?php echo $total_stats['bendri_vidutiniai_balai']; ?></p>
                    </div>
                <?php endif; ?>

                <!-- Statistikos lentelė -->
                <?php if (!empty($stats)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Mokykla</th>
                                    <th>Olimpiada</th>
                                    <th>Dalyvių skaičius</th>
                                    <th>Vidutiniai balai</th>
                                    <th>Prizininkų skaičius</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats as $stat): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($stat['mokykla']); ?></td>
                                        <td><?php echo htmlspecialchars($stat['olimpiada']); ?></td>
                                        <td><?php echo $stat['dalyviu_skaicius']; ?></td>
                                        <td><?php echo $stat['vidutiniai_balai']; ?></td>
                                        <td><?php echo $stat['prizininku_skaicius']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <p>Nėra statistikos duomenų pagal pasirinktus filtrus.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
?>