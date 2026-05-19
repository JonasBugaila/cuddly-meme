<?php
/**
 * Mokyklų valdymo puslapis
 * 
 * Šis failas atvaizduoja mokyklų sąrašą ir leidžia jas valdyti
 */

// Įtraukiame konfigūracijos failus
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname, ar vartotojas prisijungęs ir turi administratoriaus teises
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

if (!is_admin()) {
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL);
}

// Mokyklos šalinimas
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $school_id = sanitize_input($_GET['delete']);
    
    // Tikriname, ar mokykla egzistuoja
    $sql = "SELECT mokyklos_id FROM mokyklos WHERE mokyklos_id = ?";
    $stmt = db_query($sql, [$school_id], 'i');
    $school = db_get_row($stmt);
    
    if ($school) {
        // Tikriname, ar mokykla nėra susieta su vartotojais ar dalyviais
        $sql = "SELECT COUNT(*) as count FROM vartotojas WHERE var_mokykla = ?";
        $stmt = db_query($sql, [$school_id], 'i');
        $user_count = db_get_row($stmt)['count'];
        
        $sql = "SELECT COUNT(*) as count FROM dalyviai WHERE var_mokykla = ?";
        $stmt = db_query($sql, [$school_id], 's');
        $participant_count = db_get_row($stmt)['count'];
        
        if ($user_count > 0 || $participant_count > 0) {
            set_message('Negalima pašalinti mokyklos, nes ji susieta su vartotojais arba dalyviais', 'error');
        } else {
            // Šaliname mokyklą
            $sql = "DELETE FROM mokyklos WHERE mokyklos_id = ?";
            $stmt = db_query($sql, [$school_id], 'i');
            
            if ($stmt) {
                set_message('Mokykla sėkmingai pašalinta', 'success');
            } else {
                set_message('Klaida šalinant mokyklą', 'error');
            }
        }
    } else {
        set_message('Mokykla nerasta', 'error');
    }
    
    redirect(SITE_URL . '/modules/admin/schools.php');
}

// Gauname mokyklų sąrašą
$sql = "SELECT mokyklos_id, pavadinimas, adresas, telefonas, el_pastas, direktorius FROM mokyklos ORDER BY pavadinimas ASC";
$stmt = db_query($sql);
$schools = db_get_results($stmt);

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h1>Mokyklų valdymas</h1>
                <a href="<?php echo SITE_URL; ?>/modules/admin/school_add.php" class="btn btn-primary">Nauja mokykla</a>
            </div>
            <div class="card-body">
                <?php display_message(); ?>
                <?php if (!empty($schools)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Pavadinimas</th>
                                    <th>Adresas</th>
                                    <th>Telefonas</th>
                                    <th>El. paštas</th>
                                    <th>Direktorius</th>
                                    <th>Veiksmai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schools as $school): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($school['mokyklos_id']); ?></td>
                                        <td><?php echo htmlspecialchars($school['pavadinimas']); ?></td>
                                        <td><?php echo htmlspecialchars($school['adresas'] ?? 'Nenurodyta'); ?></td>
                                        <td><?php echo htmlspecialchars($school['telefonas'] ?? 'Nenurodyta'); ?></td>
                                        <td><?php echo htmlspecialchars($school['el_pastas'] ?? 'Nenurodyta'); ?></td>
                                        <td><?php echo htmlspecialchars($school['direktorius'] ?? 'Nenurodyta'); ?></td>
                                        <td>
                                            <a href="<?php echo SITE_URL; ?>/modules/admin/school_edit.php?id=<?php echo htmlspecialchars($school['mokyklos_id']); ?>" class="btn btn-sm btn-primary">Redaguoti</a>
                                            <a href="<?php echo SITE_URL; ?>/modules/admin/schools.php?delete=<?php echo htmlspecialchars($school['mokyklos_id']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Ar tikrai norite pašalinti šią mokyklą?')">Šalinti</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <p>Nėra mokyklų.</p>
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