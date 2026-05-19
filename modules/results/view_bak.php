<?php
/**
 * Rezultatų peržiūros puslapis
 * 
 * Šis failas atvaizduoja olimpiados rezultatus ir leidžia juos redaguoti
 */

// Įtraukiame konfigūracijos failus
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname ar vartotojas prisijungęs
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

// Tikriname ar nurodytas olimpiados ID
if (!isset($_GET['olympiad_id']) || empty($_GET['olympiad_id'])) {
    set_message('Nenurodyta olimpiada', 'error');
    redirect(SITE_URL . '/modules/results/index.php');
}

$olympiad_id = sanitize_input($_GET['olympiad_id']);

// Gauname olimpiados informaciją
$sql = "SELECT * FROM konkursai WHERE konk_id = ?";
$stmt = db_query($sql, [$olympiad_id]);
$olympiad = db_get_row($stmt);

if (!$olympiad) {
    set_message('Olimpiada nerasta', 'error');
    redirect(SITE_URL . '/modules/results/index.php');
}

// Gauname olimpiados dalyvius
$sql = "SELECT * FROM dalyviai WHERE konkurso_pav = ? ORDER BY Balai DESC, 1_pavarde ASC";
$stmt = db_query($sql, [$olympiad['konkurso_pav']]);
$participants = db_get_results($stmt);

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
    redirect(SITE_URL . '/modules/results/view.php?olympiad_id=' . $olympiad_id);
}

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h1>Olimpiados "<?php echo $olympiad['konkurso_pav']; ?>" rezultatai</h1>
                <a href="<?php echo SITE_URL; ?>/modules/results/index.php" class="btn btn-secondary">Grįžti į sąrašą</a>
            </div>
            <div class="card-body">
                <?php if (!empty($participants)): ?>
                    <?php if (is_admin()): ?>
                        <form action="<?php echo SITE_URL; ?>/modules/results/view.php?olympiad_id=<?php echo $olympiad_id; ?>" method="post">
                            <div class="mb-3">
                                <button type="submit" name="save_results" class="btn btn-primary">Išsaugoti rezultatus</button>
                                <button type="button" class="btn btn-secondary" onclick="calculateRanks()">Automatiškai skaičiuoti vietas</button>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="results-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Vardas</th>
                                            <th>Pavardė</th>
                                            <th>Klasė</th>
                                            <th>Mokykla</th>
                                            <th>Mokytojas</th>
                                            <th>Mokytojas</th>
                                            <th>Balai</th>
                                            <th>Vieta</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($participants as $participant): ?>
                                            <tr>
                                                <td><?php echo $participant['reg_id']; ?></td>
                                                <td><?php echo $participant['1_vardas']; ?></td>
                                                <td><?php echo $participant['1_pavarde']; ?></td>
                                                <td><?php echo $participant['1_klase']; ?></td>
                                                <td><?php echo $participant['var_mokykla']; ?></td>
                                                <td><?php echo $participant['1_mok']; ?></td>
                                                <td><?php echo $participant['2_mok']; ?></td>
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
                                        <th>ID</th>
                                        <th>Vardas</th>
                                        <th>Pavardė</th>
                                        <th>Klasė</th>
                                        <th>Mokykla</th>
                                        <th>Mokytojas</th>
                                        <th>Mokytojas</th>
                                        <th>Balai</th>
                                        <th>Vieta</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($participants as $participant): ?>
                                        <tr>
                                            <td><?php echo $participant['reg_id']; ?></td>
                                            <td><?php echo $participant['1_vardas']; ?></td>
                                            <td><?php echo $participant['1_pavarde']; ?></td>
                                            <td><?php echo $participant['1_klase']; ?></td>
                                            <td><?php echo $participant['var_mokykla']; ?></td>
                                            <td><?php echo $participant['1_mok']; ?></td>
                                            <td><?php echo $participant['2_mok']; ?></td>
                                            <td><?php echo $participant['Balai']; ?></td>
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
                <?php else: ?>
                    <div class="alert alert-info">
                        <p>Nėra užregistruotų dalyvių.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Įtraukiame poraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
?>