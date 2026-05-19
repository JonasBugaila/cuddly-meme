<?php
/**
 * Vartotojų valdymo puslapis
 * 
 * Šis failas atvaizduoja vartotojų sąrašą ir leidžia juos valdyti
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

// Vartotojo šalinimas
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $user_id = sanitize_input($_GET['delete']);
    
    // Tikriname ar vartotojas egzistuoja
    $sql = "SELECT * FROM vartotojas WHERE vart_id = ?";
    $stmt = db_query($sql, [$user_id]);
    $user = db_get_row($stmt);
    
    if ($user) {
        // Šaliname vartotoją
        $sql = "DELETE FROM vartotojas WHERE vart_id = ?";
        $stmt = db_query($sql, [$user_id]);
        
        if ($stmt) {
            set_message('Vartotojas sėkmingai pašalintas', 'success');
        } else {
            set_message('Klaida šalinant vartotoją', 'error');
        }
    } else {
        set_message('Vartotojas nerastas', 'error');
    }
    
    redirect(SITE_URL . '/modules/admin/users.php');
}

// Gauname vartotojų sąrašą
$sql = "SELECT * FROM vartotojas ORDER BY vart_id ASC";
$stmt = db_query($sql);
$users = db_get_results($stmt);

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h1>Vartotojų valdymas</h1>
                <a href="<?php echo SITE_URL; ?>/modules/admin/user_add.php" class="btn btn-primary">Naujas vartotojas</a>
            </div>
            <div class="card-body">
                <?php if (!empty($users)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Vartotojo ID</th>
                                    <th>Vardas</th>
                                    <th>Pavardė</th>
                                    <th>Mokykla</th>
                                    <th>Lygis</th>
                                    <th>Veiksmai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['vart_id']; ?></td>
                                        <td><?php echo $user['var_vardas']; ?></td>
                                        <td><?php echo $user['var_pavarde']; ?></td>
                                        <td><?php echo $user['var_mokykla']; ?></td>
                                        <td>
                                            <?php if ($user['vart_lygis'] == 'admin'): ?>
                                                <span class="badge bg-danger">Administratorius</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">Mokyklos atstovas</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo SITE_URL; ?>/modules/admin/user_edit.php?id=<?php echo $user['vart_id']; ?>" class="btn btn-sm btn-primary">Redaguoti</a>
                                            <a href="<?php echo SITE_URL; ?>/modules/admin/users.php?delete=<?php echo $user['vart_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Ar tikrai norite pašalinti šį vartotoją?')">Šalinti</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <p>Nėra vartotojų.</p>
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
