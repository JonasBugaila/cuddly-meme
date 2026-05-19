<?php
/**
 * Duomenų bazės atsarginės kopijos kūrimo puslapis
 * 
 * Šis failas leidžia administratoriui sukurti ir atsisiųsti DB atsarginę kopiją
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname, ar vartotojas prisijungęs ir yra administratorius
if (!is_logged_in() || !is_admin()) {
    set_message('Prieiga leidžiama tik administratoriams', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

// Nustatome atsarginių kopijų katalogą
$backup_dir = dirname(dirname(dirname(__FILE__))) . '/backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Apdorojame atsarginės kopijos kūrimo užklausą
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_message('Netinkamas CSRF žetonas', 'error');
        redirect(SITE_URL . '/modules/admin/backup.php');
    }

    // Generuojame unikalų failo pavadinimą
    $backup_file = 'backup_' . date('Ymd_His') . '.sql';
    $backup_path = $backup_dir . $backup_file;

    // Duomenų bazės prisijungimo informacija
    $db_host = DB_HOST;
    $db_user = DB_USER;
    $db_pass = DB_PASS;
    $db_name = DB_NAME;

    // Formuojame mysqldump komandą
    $command = "mysqldump --host=$db_host --user=$db_user --password=$db_pass $db_name > " . escapeshellarg($backup_path);
    
    // Vykdome komandą
    exec($command, $output, $return_var);

    if ($return_var === 0 && file_exists($backup_path)) {
        // Siunčiame failą atsisiuntimui
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $backup_file . '"');
        header('Content-Length: ' . filesize($backup_path));
        readfile($backup_path);
        
        // Pasirinktinai: ištriname failą po siuntimo
        unlink($backup_path);
        exit;
    } else {
        set_message('Nepavyko sukurti atsarginės kopijos. Patikrinkite serverio konfigūraciją.', 'error');
        error_log("Backup failed: Return code $return_var, Output: " . implode("\n", $output));
    }
}

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h1>Duomenų bazės atsarginė kopija</h1>
                <a href="<?php echo SITE_URL; ?>/modules/reports/index.php" class="btn btn-secondary">Grįžti į ataskaitas</a>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <p>Ši funkcija leidžia sukurti ir atsisiųsti visos duomenų bazės atsarginę kopiją (SQL formatu). Atsarginės kopijos failas bus automatiškai siunčiamas atsisiuntimui.</p>
                </div>
                
                <form action="<?php echo SITE_URL; ?>/modules/admin/backup.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <button type="submit" name="create_backup" class="btn btn-primary">Sukurti atsarginę kopiją</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Įtraukiame poraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
?>