<?php
/**
 * Rezultatų lentelės puslapis
 * 
 * Šis failas atvaizduoja olimpiadų rezultatų lentelę su balais ir vietomis
 */
// Tikriname, ar buvo paspaustas "Atnaujinti vietas"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recalculate_ranks'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $olympiad_name = sanitize_input($_POST['olympiad_name']);
        
        // Iškviečiame jūsų funkciją iš functions.php
        recalculate_ranks($olympiad_name);
        
        set_message('Vietos sėkmingai perskaičiuotos!', 'success');
        redirect(build_url_with_params(['olympiad_id' => $_GET['olympiad_id']]));
    } else {
        set_message('Saugumo klaida: negaliojantis CSRF žetonas.', 'error');
    }
}
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

//if (!is_logged_in()) {
//    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
//    redirect(SITE_URL . '/modules/auth/login.php');
//}
// Tikriname ar vartotojas prisijungęs ir turi administratoriaus teises
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
} elseif (!is_admin()) {
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL);
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

// Gauname dalyvių sąrašą
$participants = [];
$sql = "SELECT * FROM dalyviai WHERE konkurso_pav = ? ORDER BY Vieta ASC, Balai DESC";
$stmt = db_query($sql, [$olympiad['konkurso_pav']]);
if ($stmt) {
    $participants = db_get_results($stmt);
}

// Rezultatų atnaujinimas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_results']) && is_admin()) {
    foreach ($_POST['participant'] as $reg_id => $data) {
        $balai = isset($data['balai']) ? (int)$data['balai'] : 0;
        $vieta = isset($data['vieta']) ? sanitize_input($data['vieta']) : '';
        
        $sql = "UPDATE dalyviai SET Balai = ?, Vieta = ? WHERE reg_id = ?";
        db_query($sql, [$balai, $vieta, $reg_id]);
    }
    
    set_message('Rezultatai sėkmingai atnaujinti', 'success');
    redirect(SITE_URL . '/modules/reports/result_sheet.php?olympiad_id=' . $olympiad_id);
}

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h1>Rezultatų lentelė: <?php echo htmlspecialchars($olympiad['konkurso_pav']); ?></h1>
                    <a href="<?php echo SITE_URL; ?>/modules/olympiads/index.php" class="btn btn-secondary">Grįžti</a>
                </div>
                <div class="card-body">
                    <?php display_message(); ?>
                    
                    <?php if (is_admin()): ?>
                    <form method="post" action="<?php echo SITE_URL; ?>/modules/reports/result_sheet.php?olympiad_id=<?php echo $olympiad_id; ?>">
                    <?php endif; ?>
                    
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Vieta</th>
                                        <th>Dalyvio kodas</th>
                                        <th>Bendri balai</th>
                                        <?php if (is_admin()): ?>
                                        <th>Vieta</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($participants as $participant): ?>
                                    <tr>
                                        <td><?php echo $participant['Vieta'] ?? '-'; ?></td>
                                        <td><?php echo $participant['reg_id']; ?></td>
                                        <td>
                                            <?php if (is_admin()): ?>
                                                <input type="number" class="form-control" name="participant[<?php echo $participant['reg_id']; ?>][balai]" 
                                                       value="<?php echo $participant['Balai'] ?? 0; ?>" min="0">
                                            <?php else: ?>
                                                <?php echo $participant['Balai'] ?? '-'; ?>
                                            <?php endif; ?>
                                        </td>
                                        <?php if (is_admin()): ?>
                                        <td>
                                            <select class="form-control" name="participant[<?php echo $participant['reg_id']; ?>][vieta]">
                                                <option value="">-</option>
                                                <option value="I" <?php echo ($participant['Vieta'] ?? '') == 'I' ? 'selected' : ''; ?>>I vieta</option>
                                                <option value="II" <?php echo ($participant['Vieta'] ?? '') == 'II' ? 'selected' : ''; ?>>II vieta</option>
                                                <option value="III" <?php echo ($participant['Vieta'] ?? '') == 'III' ? 'selected' : ''; ?>>III vieta</option>
                                                <option value="laureat." <?php echo ($participant['Vieta'] ?? '') == 'laureat.' ? 'selected' : ''; ?>>Laureatas</option>
                                            </select>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (is_admin()): ?>
                        <div class="mt-3">
                            <button type="submit" name="save_results" class="btn btn-primary">Išsaugoti pakeitimus</button>
							
							
    <button type="submit" name="save_results" class="btn btn-primary">Išsaugoti balus</button>

    <form method="post" onsubmit="return confirm('Ar tikrai norite perskaičiuoti vietas visiems dalyviams šioje olimpiadoje?');">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="olympiad_name" value="<?php echo htmlspecialchars($olympiad_name); ?>">
        <button type="submit" name="recalculate_ranks" class="btn btn-warning">
            <i class="fas fa-sync-alt"></i> Atnaujinti vietas
        </button>
    </form>

							
							
							
							
							
                        </div>
						
                        </form>
						
                        <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
?>