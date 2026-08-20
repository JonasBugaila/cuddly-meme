<?php
/**
 * Metų pabaigos archyvavimas ir duomenų bazės paruošimas naujiems metams
 *
 * Dviejų žingsnių procesas:
 *  1. Eksportuoti šių metų duomenis (dalyviai, konkursai, žurnalai) - PRIVALOMA
 *     prieš leidžiant valymą (žr. $_SESSION['year_export_completed_at']).
 *  2. Išvalyti pasirinktas lenteles. Numatytai lieka: vartotojas, klases,
 *     kvalifikacijos. Mokyklos - pasirenkama (numatytai NE, žr. įspėjimą).
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in() || !is_admin()) {
    log_action('Saugumo pažeidimas', 'Bandyta pasiekti metų duomenų valymo įrankį be admin teisių.');
    set_message('Neturite teisių pasiekti šį puslapį.', 'error');
    redirect(SITE_URL);
    exit;
}

start_session();

// Lentelės, kurias galima išvalyti, ir jų žmogiškai suprantami pavadinimai
$resettable_tables = [
    'dalyviai' => 'Dalyviai (registracijos ir rezultatai)',
    'konkursai' => 'Olimpiados / konkursai',
    'system_logs' => 'Sistemos žurnalas',
    'teacher_activity_log' => 'Mokytojų veiklos žurnalas',
];
// Papildoma, RIZIKINGA lentelė - numatytai NEpažymėta
$optional_tables = [
    'mokyklos' => 'Mokyklos',
];
// Lentelės, kurios NIEKADA nevalomos šiuo įrankiu (informacinis sąrašas)
$kept_tables = [
    'vartotojas' => 'Vartotojai',
    'klases' => 'Klasės',
    'kvalifikacijos' => 'Kvalifikacijos',
];

$export_valid_seconds = 60 * 60; // 1 valanda
$export_done_at = $_SESSION['year_export_completed_at'] ?? null;
$export_is_valid = $export_done_at && (time() - $export_done_at) < $export_valid_seconds;

$required_phrase = 'TRINTI DUOMENIS';

// ---------------------------------------------------------
// POST: DUOMENŲ VALYMAS
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_message('Netinkamas saugumo žetonas. Bandykite dar kartą.', 'error');
        redirect(SITE_URL . '/modules/admin/year_reset.php');
        exit;
    }

    // SAUGU: pakartotinai tikriname, ar eksportas iš tikrųjų buvo atliktas ir
    // vis dar galioja - neužtenka vien to, kad JS mygtukas buvo atrakintas
    // (JS patikra apeinama trivialiai, todėl serveryje tikriname iš naujo).
    if (!$export_is_valid) {
        set_message('Prieš valydami duomenis, pirma atlikite eksportą (1 žingsnis). Eksportas galioja 60 minučių.', 'error');
        redirect(SITE_URL . '/modules/admin/year_reset.php');
        exit;
    }

    $typed_phrase = trim($_POST['confirm_phrase'] ?? '');
    if ($typed_phrase !== $required_phrase) {
        set_message('Neteisingai įvestas patvirtinimo tekstas. Duomenys NEBUVO pašalinti.', 'error');
        redirect(SITE_URL . '/modules/admin/year_reset.php');
        exit;
    }

    $selected_tables = $_POST['tables'] ?? [];
    $allowed_all = array_merge(array_keys($resettable_tables), array_keys($optional_tables));
    $selected_tables = array_values(array_intersect($selected_tables, $allowed_all));

    if (empty($selected_tables)) {
        set_message('Nepasirinkta nei viena lentelė valymui.', 'error');
        redirect(SITE_URL . '/modules/admin/year_reset.php');
        exit;
    }

    $conn = db_connect();
    $cleared = [];
    $failed = [];

    // SAUGU: naudojame DELETE FROM (ne TRUNCATE) - TRUNCATE reikalauja DROP teisės,
    // kurios aplikacijos DB vartotojui rekomenduojama NEDUOTI (žr. docs/DB_SECURITY.md,
    // mažiausių privilegijų principas). DELETE veikia su minimaliomis SELECT/INSERT/
    // UPDATE/DELETE teisėmis IR yra transakcinis - visos lentelės išvalomos kartu,
    // arba nė viena (jei kuri nors nepavyktų, viskas atšaukiama).
    $conn->begin_transaction();
    try {
        foreach ($selected_tables as $table) {
            // SAUGU: lentelės pavadinimas tikrinamas per griežtą whitelist prieš vykdant
            if (!in_array($table, $allowed_all, true)) {
                continue;
            }
            $mysqli_table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $conn->query("DELETE FROM `$mysqli_table`");
            $cleared[] = $table;
        }
        $conn->commit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        error_log("Year reset DELETE klaida: " . $e->getMessage());
        $failed = $selected_tables;
        $cleared = [];
    }

    // NEPRIVALOMA: bandome atstatyti AUTO_INCREMENT skaitiklius į 1, kad naujų metų
    // ID prasidėtų iš naujo. Tai reikalauja ALTER teisės - jei jos nėra, tyliai
    // praleidžiama (funkcionalumui tai nekritiška, tik numeracijos "švara").
    if (!empty($cleared)) {
        foreach ($cleared as $table) {
            $mysqli_table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            try {
                $conn->query("ALTER TABLE `$mysqli_table` AUTO_INCREMENT = 1");
            } catch (mysqli_sql_exception $e) {
                // Tyliai praleidžiame - dažniausiai reiškia, kad DB vartotojas neturi ALTER teisės
                error_log("Year reset: nepavyko atstatyti AUTO_INCREMENT ({$table}): " . $e->getMessage());
            }
        }
    }

    // Eksporto žymą naudojame tik vieną kartą - priverčiame naują eksportą prieš kitą valymą
    unset($_SESSION['year_export_completed_at']);

    $summary = 'Išvalytos lentelės: ' . implode(', ', $cleared) . '.';
    if (!empty($failed)) {
        $summary .= ' NEPAVYKO išvalyti: ' . implode(', ', $failed) . ' (žr. serverio klaidų žurnalą).';
    }
    log_action('Metų duomenų valymas', $summary . " Administratorius: " . ($_SESSION['user_id'] ?? 'nežinomas'));

    if (empty($failed)) {
        set_message('Duomenys sėkmingai išvalyti. Sistema paruošta naujiems metams. ' . $summary, 'success');
    } else {
        set_message('Dalis lentelių išvalytos, bet įvyko klaidų. ' . $summary, 'error');
    }
    redirect(SITE_URL . '/modules/admin/year_reset.php');
    exit;
}

// ---------------------------------------------------------
// EILUČIŲ SKAIČIAI RODYMUI
// ---------------------------------------------------------
function get_row_count($table) {
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $row = db_get_row(db_query("SELECT COUNT(*) as cnt FROM `$safe`"));
    return $row['cnt'] ?? 0;
}

$counts = [];
foreach (array_merge($resettable_tables, $optional_tables, $kept_tables) as $table => $label) {
    $counts[$table] = get_row_count($table);
}

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-danger">
            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <h1 class="h4 mb-0"><i class="fas fa-calendar-alt"></i> Metų pabaigos archyvavimas ir naujų metų paruošimas</h1>
                <a href="<?php echo SITE_URL; ?>/modules/admin/index.php" class="btn btn-sm btn-light text-dark">Grįžti</a>
            </div>
            <div class="card-body">
                <?php display_message(); ?>

                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Šis įrankis negrįžtamai pašalina duomenis.</strong> Prieš valydami sistemą, PRIVALOTE atsisiųsti
                    ir saugiai išsaugoti metų duomenų archyvą (1 žingsnis). Sistemoje po valymo liks TIK vartotojai,
                    klasės ir kvalifikacijos.
                </div>

                <!-- ESAMA BŪSENA -->
                <h5 class="mb-3">Dabartinė duomenų būsena</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light">
                            <tr><th>Lentelė</th><th>Įrašų sk.</th><th>Ką darys įrankis</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resettable_tables as $table => $label): ?>
                                <tr class="table-danger">
                                    <td><?php echo htmlspecialchars($label); ?></td>
                                    <td><?php echo (int)$counts[$table]; ?></td>
                                    <td><span class="badge bg-danger">Valoma (numatytai)</span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php foreach ($optional_tables as $table => $label): ?>
                                <tr class="table-warning">
                                    <td><?php echo htmlspecialchars($label); ?></td>
                                    <td><?php echo (int)$counts[$table]; ?></td>
                                    <td><span class="badge bg-warning text-dark">Pasirenkama (NErekomenduojama)</span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php foreach ($kept_tables as $table => $label): ?>
                                <tr class="table-success">
                                    <td><?php echo htmlspecialchars($label); ?></td>
                                    <td><?php echo (int)$counts[$table]; ?></td>
                                    <td><span class="badge bg-success">Visada paliekama</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <hr>

                <!-- 1 ŽINGSNIS: EKSPORTAS -->
                <h5 class="mb-3">1 žingsnis: Eksportuoti metų duomenis</h5>
                <p class="text-muted">Sukuriamas SQL failas su visais dalyvių, olimpiadų ir žurnalų duomenimis. Išsaugokite jį saugioje vietoje (pvz. debesyje arba atskirame diske).</p>
                <a href="<?php echo SITE_URL; ?>/modules/backup/year_export.php" class="btn btn-primary mb-4" id="btnExport" target="_blank">
                    <i class="fas fa-download"></i> Atsisiųsti metų archyvą (SQL)
                </a>

                <?php if ($export_is_valid): ?>
                    <div class="alert alert-success py-2">
                        <i class="fas fa-check-circle"></i> Eksportas atliktas <?php echo date('Y-m-d H:i', $export_done_at); ?>. Galite tęsti prie 2 žingsnio (galioja dar <?php echo floor(($export_valid_seconds - (time() - $export_done_at)) / 60); ?> min.).
                    </div>
                <?php else: ?>
                    <div class="alert alert-secondary py-2">
                        <i class="fas fa-info-circle"></i> Kad būtų atrakintas 2 žingsnis, pirma atsisiųskite archyvą aukščiau. Puslapis automatiškai atsinaujins po atsisiuntimo.
                    </div>
                <?php endif; ?>

                <hr>

                <!-- 2 ŽINGSNIS: VALYMAS -->
                <h5 class="mb-3">2 žingsnis: Duomenų bazės paruošimas naujiems metams</h5>

                <form method="post" action="" id="resetForm" class="<?php echo $export_is_valid ? '' : 'opacity-50'; ?>" style="pointer-events: <?php echo $export_is_valid ? 'auto' : 'none'; ?>;">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="confirm_reset" value="1">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Kurias lenteles valyti:</label>
                        <?php foreach ($resettable_tables as $table => $label): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="tables[]" value="<?php echo htmlspecialchars($table); ?>" id="tbl_<?php echo $table; ?>" checked <?php echo $export_is_valid ? '' : 'disabled'; ?>>
                                <label class="form-check-label" for="tbl_<?php echo $table; ?>"><?php echo htmlspecialchars($label); ?> (<?php echo (int)$counts[$table]; ?> įrašų)</label>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach ($optional_tables as $table => $label): ?>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="tables[]" value="<?php echo htmlspecialchars($table); ?>" id="tbl_<?php echo $table; ?>" <?php echo $export_is_valid ? '' : 'disabled'; ?>>
                                <label class="form-check-label text-danger" for="tbl_<?php echo $table; ?>">
                                    <?php echo htmlspecialchars($label); ?> (<?php echo (int)$counts[$table]; ?> įrašų)
                                    <strong>- NEREKOMENDUOJAMA:</strong> vartotojų ir naujų dalyvių įrašai nurodo mokyklą pagal pavadinimą; ištrynus, mokyklų sąrašai (dropdown'ai) liks tušti, kol neįvesite jų iš naujo.
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_phrase" class="form-label fw-bold text-danger">Įrašykite tiksliai "<?php echo htmlspecialchars($required_phrase); ?>", kad patvirtintumėte:</label>
                        <input type="text" class="form-control" id="confirm_phrase" name="confirm_phrase" autocomplete="off" <?php echo $export_is_valid ? '' : 'disabled'; ?>>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="understandCheck" <?php echo $export_is_valid ? '' : 'disabled'; ?>>
                        <label class="form-check-label" for="understandCheck">Supratau, kad šis veiksmas negrįžtamas ir kad archyvą jau atsisiunčiau.</label>
                    </div>

                    <button type="submit" class="btn btn-danger btn-lg" id="btnConfirmReset" disabled>
                        <i class="fas fa-trash-alt"></i> Išvalyti duomenis ir paruošti naujiems metams
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const phraseInput = document.getElementById('confirm_phrase');
    const understandCheck = document.getElementById('understandCheck');
    const submitBtn = document.getElementById('btnConfirmReset');
    const requiredPhrase = <?php echo json_encode($required_phrase); ?>;

    function validateForm() {
        if (!phraseInput || !understandCheck || !submitBtn) return;
        const phraseOk = phraseInput.value.trim() === requiredPhrase;
        submitBtn.disabled = !(phraseOk && understandCheck.checked);
    }

    if (phraseInput) phraseInput.addEventListener('input', validateForm);
    if (understandCheck) understandCheck.addEventListener('change', validateForm);

    // NAUJA: paspaudus eksporto mygtuką, po trumpos pauzės automatiškai
    // perkraukime šį puslapį, kad atsinaujintų sesijos žyma ir atrakintų 2 žingsnį.
    const exportBtn = document.getElementById('btnExport');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            setTimeout(function() {
                window.location.reload();
            }, 3000);
        });
    }
});
</script>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>
