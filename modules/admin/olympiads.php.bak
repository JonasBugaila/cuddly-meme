<?php
/**
 * Olimpiadų valdymo puslapis
 * 
 * Šis failas atvaizduoja olimpiadų sąrašą ir leidžia jas valdyti
 */

// Įtraukiame konfigūracijos failus
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname ar vartotojas prisijungęs ir turi administratoriaus teises
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

if (!is_admin()) {
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL);
}

// Olimpiados šalinimas
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $olympiad_id = sanitize_input($_GET['delete']);
    
    // Tikriname ar olimpiada egzistuoja
    $sql = "SELECT * FROM konkursai WHERE konk_id = ?";
    $stmt = db_query($sql, [$olympiad_id]);
    $olympiad = db_get_row($stmt);
    
    if ($olympiad) {
        // Šaliname olimpiadą
        $sql = "DELETE FROM konkursai WHERE konk_id = ?";
        $stmt = db_query($sql, [$olympiad_id]);
        
        if ($stmt) {
            set_message('Olimpiada sėkmingai pašalinta', 'success');
        } else {
            set_message('Klaida šalinant olimpiadą', 'error');
        }
    } else {
        set_message('Olimpiada nerasta', 'error');
    }
    
    redirect(SITE_URL . '/modules/admin/olympiads.php');
}

// Gauname olimpiadų sąrašą
$sql = "SELECT * FROM konkursai ORDER BY konk_id DESC";
$stmt = db_query($sql);
$olympiads = db_get_results($stmt);

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h1>Olimpiadų valdymas</h1>
                <a href="<?php echo SITE_URL; ?>/modules/admin/olympiad_add.php" class="btn btn-primary">Nauja olimpiada</a>
            </div>
            <div class="card-body">
                <?php if (!empty($olympiads)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Pavadinimas</th>
                                    <th>Atsakingas</th>
                                    <th>Grupė</th>
                                    <th>Statusas</th>
                                    <th>Veiksmai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($olympiads as $olympiad): ?>
                                    <tr>
                                        <td><?php echo $olympiad['konk_id']; ?></td>
                                        <td><?php echo $olympiad['konkurso_pav']; ?></td>
                                        <td><?php echo $olympiad['atsakingas']; ?></td>
                                        <td><?php echo $olympiad['grupe']; ?></td>
                                        <td>
                                            <?php if ($olympiad['status'] == 0): ?>
                                                <span class="badge bg-success">Aktyvus</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Neaktyvus</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo SITE_URL; ?>/modules/admin/olympiad_edit.php?id=<?php echo $olympiad['konk_id']; ?>" class="btn btn-sm btn-primary">Redaguoti</a>
                                            <a href="<?php echo SITE_URL; ?>/modules/admin/olympiads.php?delete=<?php echo $olympiad['konk_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Ar tikrai norite pašalinti šią olimpiadą?')">Šalinti</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <p>Nėra olimpiadų.</p>
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
