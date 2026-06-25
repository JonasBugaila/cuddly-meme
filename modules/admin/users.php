<?php
/**
 * Vartotojų valdymo puslapis
 * * Šis failas atvaizduoja vartotojų sąrašą ir leidžia juos valdyti
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

// PATAISYTA: Saugus vartotojo šalinimas vykdomas tik per POST užklausą
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    // Tikriname CSRF žetoną
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_message('Netinkamas sesijos žetonas (CSRF).', 'error');
        redirect(SITE_URL . '/modules/admin/users.php');
    }

    $user_id = sanitize_input($_POST['user_id']);
    
    // Tikriname, ar vartotojas nebando ištrinti savęs
    if ($user_id === $_SESSION['user_id']) {
        set_message('Negalite ištrinti patys savęs!', 'error');
    } else {
        $sql = "SELECT * FROM vartotojas WHERE vart_id = ?";
        $stmt = db_query($sql, [$user_id], 's');
        $user = $stmt ? db_get_row($stmt) : null;
        
        if ($user) {
            $sql = "DELETE FROM vartotojas WHERE vart_id = ?";
            $delete_stmt = db_query($sql, [$user_id], 's');
            
            if ($delete_stmt) {
                set_message('Vartotojas sėkmingai pašalintas', 'success');
            } else {
                set_message('Klaida šalinant vartotoją', 'error');
            }
        } else {
            set_message('Vartotojas nerastas', 'error');
        }
    }
    redirect(SITE_URL . '/modules/admin/users.php');
}

// Gauname vartotojų sąrašą
$sql = "SELECT * FROM vartotojas ORDER BY vart_id ASC";
$stmt = db_query($sql);
$users = $stmt ? db_get_results($stmt) : [];

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">Vartotojų valdymas</h1>
                <a href="<?php echo SITE_URL; ?>/modules/admin/user_add.php" class="btn btn-primary btn-sm">Naujas vartotojas</a>
            </div>
            <div class="card-body">
                <?php display_message(); ?>
                
                <?php if (!empty($users)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Vartotojo ID (Vardas)</th>
                                    <th>Vardas</th>
                                    <th>Pavardė</th>
                                    <th>Mokykla</th>
                                    <th>Lygis</th>
                                    <th class="text-end">Veiksmai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($user['vart_id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($user['var_vardas']); ?></td>
                                        <td><?php echo htmlspecialchars($user['var_pavarde']); ?></td>
                                        <td><?php echo htmlspecialchars($user['var_mokykla'] ?: 'Nepriskirta'); ?></td>
                                        <td>
                                            <?php if ($user['vart_lygis'] == 'admin'): ?>
                                                <span class="badge bg-danger">Administratorius</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">Mokyklos atstovas</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                <a href="<?php echo SITE_URL; ?>/modules/admin/user_edit.php?id=<?php echo urlencode($user['vart_id']); ?>" class="btn btn-sm btn-outline-primary">Redaguoti</a>
                                                
                                                <?php if ($user['vart_id'] !== $_SESSION['user_id']): ?>
                                                    <form method="post" action="" onsubmit="return confirm('Ar tikrai norite pašalinti šį vartotoją?');" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['vart_id']); ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">Ištrinti</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">Sistemoje nėra registruotų vartotojų.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>