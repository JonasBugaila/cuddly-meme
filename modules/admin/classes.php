<?php
/**
 * Klasių valdymo puslapis (Administravimas)
 *
 * Leidžia administratoriui pridėti, redaguoti ir šalinti klases, kurios
 * naudojamos kaip pasirenkamas sąrašas registruojant dalyvius
 * (modules/registration/register.php, edit_participant.php, admin/participant_edit.php
 * visos šią lentelę skaito tiesiogiai - pakeitimai čia iškart atsispindi visur).
 *
 * PASTABA: dalyviai.1_klase saugo klasės PAVADINIMĄ kaip tekstą (kopija
 * registracijos metu), o ne nuorodą į šios lentelės ID - todėl klasės
 * pervadinimas ar pašalinimas NEPALIEČIA jau egzistuojančių dalyvių įrašų
 * (skirtingai nei mokyklos/olimpiados pavadinimas, kurie veikia kaip
 * "nuoroda" per tekstą - žr. school_edit.php/olympiad_edit.php cascade logiką).
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
    exit;
}
if (!is_admin()) {
    log_action('Saugumo pažeidimas', 'Bandyta pasiekti klasių valdymą be admin teisių.');
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL);
    exit;
}

// ---------------------------------------------------------
// PRIDĖTI NAUJĄ KLASĘ
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_message('Netinkamas saugumo žetonas.', 'error');
        redirect(SITE_URL . '/modules/admin/classes.php');
        exit;
    }

    $new_class = trim(sanitize_input($_POST['klases'] ?? ''));

    if ($new_class === '') {
        set_message('Prašome įvesti klasės pavadinimą.', 'error');
    } elseif (mb_strlen($new_class, 'UTF-8') > 10) {
        set_message('Klasės pavadinimas negali būti ilgesnis nei 10 simbolių.', 'error');
    } else {
        // SAUGU: patikra dėl dublikatų prieš įrašant (klases lentelė neturi DB lygio
        // UNIQUE apribojimo, todėl tikriname aplikacijos pusėje)
        $existing = db_get_row(db_query("SELECT klases_id FROM klases WHERE klases = ?", [$new_class], 's'));
        if ($existing) {
            set_message("Klasė \"{$new_class}\" jau egzistuoja.", 'error');
        } else {
            $result = db_insert('klases', ['klases' => $new_class]);
            if ($result) {
                log_action('Klasės pridėjimas', "Pridėta nauja klasė: \"{$new_class}\"");
                set_message("Klasė \"{$new_class}\" sėkmingai pridėta.", 'success');
            } else {
                set_message('Klaida pridedant klasę.', 'error');
            }
        }
    }
    redirect(SITE_URL . '/modules/admin/classes.php');
    exit;
}

// ---------------------------------------------------------
// REDAGUOTI (PERVADINTI) KLASĘ
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_class'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_message('Netinkamas saugumo žetonas.', 'error');
        redirect(SITE_URL . '/modules/admin/classes.php');
        exit;
    }

    $class_id = (int)($_POST['klases_id'] ?? 0);
    $new_name = trim(sanitize_input($_POST['klases'] ?? ''));

    if ($class_id <= 0 || $new_name === '') {
        set_message('Neteisingi duomenys redagavimui.', 'error');
    } elseif (mb_strlen($new_name, 'UTF-8') > 10) {
        set_message('Klasės pavadinimas negali būti ilgesnis nei 10 simbolių.', 'error');
    } else {
        $existing = db_get_row(db_query("SELECT klases_id FROM klases WHERE klases = ? AND klases_id != ?", [$new_name, $class_id], 'si'));
        if ($existing) {
            set_message("Klasė \"{$new_name}\" jau egzistuoja.", 'error');
        } else {
            $old_row = db_get_row(db_query("SELECT klases FROM klases WHERE klases_id = ?", [$class_id], 'i'));
            $result = db_update('klases', ['klases' => $new_name], 'klases_id = ?', [$class_id]);
            if ($result) {
                $old_name = $old_row['klases'] ?? '?';
                log_action('Klasės redagavimas', "Klasė pervadinta iš \"{$old_name}\" į \"{$new_name}\" (ID: {$class_id}). Ankstesnių dalyvių įrašai nekeičiami.");
                set_message('Klasė sėkmingai atnaujinta.', 'success');
            } else {
                set_message('Klaida atnaujinant klasę.', 'error');
            }
        }
    }
    redirect(SITE_URL . '/modules/admin/classes.php');
    exit;
}

// ---------------------------------------------------------
// ŠALINTI KLASĘ
// ---------------------------------------------------------
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        set_message('Netinkamas saugumo žetonas.', 'error');
        redirect(SITE_URL . '/modules/admin/classes.php');
        exit;
    }

    $class_id = (int)$_GET['delete'];
    $class_row = db_get_row(db_query("SELECT klases FROM klases WHERE klases_id = ?", [$class_id], 'i'));

    if ($class_row) {
        // INFORMACINIS: rodome, kiek istorinių dalyvių įrašų naudoja šį pavadinimą,
        // bet NEBLOKUOJAME trynimo - dalyviai.1_klase yra atskira teksto kopija,
        // trynimas iš šio sąrašo nekeičia ir "nesugadina" jokių esamų įrašų.
        db_query("DELETE FROM klases WHERE klases_id = ?", [$class_id], 'i');
        log_action('Klasės pašalinimas', "Pašalinta klasė: \"{$class_row['klases']}\" (ID: {$class_id})");
        set_message("Klasė \"{$class_row['klases']}\" pašalinta.", 'success');
    } else {
        set_message('Klasė nerasta.', 'error');
    }
    redirect(SITE_URL . '/modules/admin/classes.php');
    exit;
}

// ---------------------------------------------------------
// SĄRAŠAS
// ---------------------------------------------------------
$classes = db_get_results(db_query("SELECT klases_id, klases FROM klases ORDER BY klases ASC"));

// Kiek dalyvių istoriškai naudoja kiekvieną klasės pavadinimą (informacinis stulpelis)
$usage_counts = [];
$usage_rows = db_get_results(db_query("SELECT `1_klase`, COUNT(*) as cnt FROM dalyviai GROUP BY `1_klase`"));
foreach ($usage_rows as $row) {
    $usage_counts[$row['1_klase']] = $row['cnt'];
}

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h1>Klasių valdymas</h1>
                <a href="<?php echo SITE_URL; ?>/modules/admin/index.php" class="btn btn-secondary btn-sm">Grįžti</a>
            </div>
            <div class="card-body">
                <?php display_message(); ?>

                <form method="post" action="" class="row g-2 align-items-end mb-4 bg-light p-3 rounded border">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Nauja klasė</label>
                        <input type="text" name="klases" class="form-control" maxlength="10" placeholder="pvz. 8a" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" name="add_class" class="btn btn-success"><i class="fas fa-plus"></i> Pridėti</button>
                    </div>
                </form>

                <?php if (!empty($classes)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Klasė</th>
                                    <th>Naudota dalyvių įrašuose</th>
                                    <th class="text-end">Veiksmai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $class): ?>
                                    <tr>
                                        <td class="text-muted"><?php echo (int)$class['klases_id']; ?></td>
                                        <td>
                                            <form method="post" action="" class="d-flex gap-2 align-items-center">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="klases_id" value="<?php echo (int)$class['klases_id']; ?>">
                                                <input type="text" name="klases" value="<?php echo htmlspecialchars($class['klases']); ?>" maxlength="10" class="form-control form-control-sm" style="max-width: 120px;">
                                                <button type="submit" name="edit_class" class="btn btn-sm btn-outline-primary">Atnaujinti<i class="fas fa-save"></i></button>
                                            </form>
                                        </td>
                                        <td class="text-muted"><?php echo (int)($usage_counts[$class['klases']] ?? 0); ?></td>
                                        <td class="text-end">
                                            <a href="<?php echo SITE_URL; ?>/modules/admin/classes.php?delete=<?php echo (int)$class['klases_id']; ?>&csrf_token=<?php echo urlencode(generate_csrf_token()); ?>"
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Ar tikrai norite pašalinti šią klasę iš sąrašo? Tai NEPALIES jau egzistuojančių dalyvių įrašų, tik nebebus siūloma registruojant naujus.');">
                                                <i class="fas fa-trash"></i> Šalinti
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">Klasių sąrašas tuščias.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>
