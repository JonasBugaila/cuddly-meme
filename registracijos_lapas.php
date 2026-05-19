<?php
/**
 * Registracijos lapo generavimas
 * 
 * Šis failas leidžia pasirinkti olimpiadą, rodo registracijos lapą su mokiniais ir olimpiados atsakingu mokytoju,
 * ir turi spausdinimo mygtuką
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

// Gauname olimpiadų sąrašą
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
    $olimpiada_id = sanitize_input($_POST['olimpiada_id']);
    $sql = "SELECT konk_id, konkurso_pav, atsakingas FROM konkursai WHERE konk_id = ?";
    $stmt = db_query($sql, [$olimpiada_id], 'i');
    $selected_olimpiada = db_get_row($stmt);
}

// Įtraukiame antraštę
require_once dirname(dirname(__FILE__)) . '/includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h1>Registracijos lapo generavimas</h1>
                </div>
                <div class="card-body">
                    <!-- Olimpiados pasirinkimas -->
                    <form action="<?php echo SITE_URL; ?>/registracijos_lapas.php" method="post" class="mb-4">
                        <div class="form-group">
                            <label for="olimpiada_id" class="form-label">Pasirinkite olimpiadą:</label>
                            <select class="form-control" id="olimpiada_id" name="olimpiada_id" required>
                                <option value="">Pasirinkite olimpiadą</option>
                                <?php foreach ($olimpiados as $olimpiada): ?>
                                    <option value="<?php echo $olimpiada['konk_id']; ?>" <?php echo isset($_POST['olimpiada_id']) && $_POST['olimpiada_id'] == $olimpiada['konk_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($olimpiada['konkurso_pav']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary mt-2">Rodyti registracijos lapą</button>
                    </form>

                    <!-- Registracijos lentelė -->
                    <?php if ($selected_olimpiada && !empty($mokiniai)): ?>
                        <div id="printableArea">
                            <h2>REGISTRACIJOS LAPAS</h2>
                            <h3><?php echo htmlspecialchars($selected_olimpiada['konkurso_pav']); ?></h3>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>№</th>
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
                                            <td><?php echo $count++; ?></td>
                                            <td><?php echo htmlspecialchars($mokinys['var_vardas'] . ' ' . $mokinys['var_pavarde']); ?></td>
                                            <td> </td> <!-- Klasė tuščia -->
                                            <td> </td> <!-- Mokykla tuščia -->
                                            <td><?php echo htmlspecialchars($selected_olimpiada['atsakingas']); ?></td>
                                            <td> </td> <!-- Antras mokytojas tuščias -->
                                            <td> </td> <!-- Informacija tuščia -->
                                            <td> </td> <!-- Parašas tuščias -->
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button onclick="printTable()" class="btn btn-success mt-2">Spausdinti</button>
                    <?php elseif ($selected_olimpiada): ?>
                        <p class="text-warning">Sistemoje nėra mokinių, kuriuos galima priskirti šiai olimpiadai.</p>
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
    window.location.reload(); // Atnaujiname puslapį, kad išlaikytume funkcionalumą
}
</script>

<?php
require_once dirname(dirname(__FILE__)) . '/includes/footer.php';
?>