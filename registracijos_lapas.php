<?php
/**
 * Registracijos lapo generavimas
 */
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

// Gauname olimpiadas
$sql = "SELECT konk_id, konkurso_pav, atsakingas FROM konkursai ORDER BY konkurso_pav ASC";
$stmt = db_query($sql);
$olimpiados = db_get_results($stmt);

// Gauname mokinius
$sql = "SELECT vart_id, var_vardas, var_pavarde FROM vartotojas WHERE var_tipas = 'studentas' ORDER BY var_pavarde ASC";
$stmt = db_query($sql);
$mokiniai = db_get_results($stmt);

// Apdorojame pasirinktą olimpiadą
$selected_olimpiada = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['olimpiada_id'])) {
    
    // SAUGUMAS: CSRF patikrinimas
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_message('Saugumo klaida. Neteisingas CSRF žetonas.', 'error');
        redirect(SITE_URL . '/registracijos_lapas.php');
    }

    // SAUGUMAS: ID paverčiame į sveikąjį skaičių
    $olimpiada_id = (int)$_POST['olimpiada_id'];
    
    $sql = "SELECT konk_id, konkurso_pav, atsakingas FROM konkursai WHERE konk_id = ?";
    // IŠTAISYTA: Pašalintas 'i' parametras
    $stmt = db_query($sql, [$olimpiada_id]); 
    $selected_olimpiada = db_get_row($stmt);
}

// Įtraukiame antraštę
require_once dirname(dirname(__FILE__)) . '/includes/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h1 class="h4 mb-0">Registracijos lapo generavimas</h1>
                </div>
                <div class="card-body bg-light">
                    <?php display_message(); ?>
                    
                    <!-- Olimpiados pasirinkimas -->
                    <form action="<?php echo SITE_URL; ?>/registracijos_lapas.php" method="post" class="mb-4 bg-white p-3 border rounded">
                        <!-- SAUGUMAS: CSRF žetonas -->
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="form-group mb-3">
                            <label for="olimpiada_id" class="form-label fw-bold">Pasirinkite olimpiadą:</label>
                            <select class="form-select border-primary" id="olimpiada_id" name="olimpiada_id" required>
                                <option value="">-- Pasirinkite olimpiadą --</option>
                                <?php foreach ($olimpiados as $olimpiada): ?>
                                    <option value="<?php echo htmlspecialchars($olimpiada['konk_id']); ?>" <?php echo (isset($_POST['olimpiada_id']) && $_POST['olimpiada_id'] == $olimpiada['konk_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($olimpiada['konkurso_pav']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-file-signature"></i> Rodyti registracijos lapą</button>
                    </form>

                    <!-- Registracijos lentelė -->
                    <?php if ($selected_olimpiada && !empty($mokiniai)): ?>
                        <div class="bg-white p-4 border rounded shadow-sm">
                            <div id="printableArea">
                                <h2 class="text-center mb-2">REGISTRACIJOS LAPAS</h2>
                                <h3 class="text-center mb-4 text-secondary"><?php echo htmlspecialchars($selected_olimpiada['konkurso_pav']); ?></h3>
                                <table class="table table-bordered table-sm align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Dalyvio vardas, pavardė</th>
                                            <th>Klasė</th>
                                            <th>Mokykla</th>
                                            <th>Mokytojas</th>
                                            <th>Antras mokytojas</th>
                                            <th>Informacija</th>
                                            <th>Parašas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $count = 1;
                                        foreach ($mokiniai as $mokinys):
                                        ?>
                                            <tr>
                                                <td class="text-muted"><?php echo $count++; ?></td>
                                                <td><strong><?php echo htmlspecialchars($mokinys['var_vardas'] . ' ' . $mokinys['var_pavarde']); ?></strong></td>
                                                <td></td> <!-- Klasė tuščia -->
                                                <td></td> <!-- Mokykla tuščia -->
                                                <td><?php echo htmlspecialchars($selected_olimpiada['atsakingas']); ?></td>
                                                <td></td> <!-- Antras mokytojas tuščias -->
                                                <td></td> <!-- Informacija tuščia -->
                                                <td></td> <!-- Parašas tuščias -->
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3 text-end d-print-none">
                                <button onclick="printTable()" class="btn btn-success px-4"><i class="fas fa-print"></i> Spausdinti</button>
                            </div>
                        </div>
                    <?php elseif ($selected_olimpiada): ?>
                        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Sistemoje nėra mokinių, kuriuos galima priskirti šiai olimpiadai.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Spausdinimo funkcija
function printTable() {
    var printContents = document.getElementById('printableArea').innerHTML;
    var originalContents = document.body.innerHTML;
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
    window.location.reload(); 
}
</script>

<?php require_once dirname(dirname(__FILE__)) . '/includes/footer.php'; ?>