<?php
/**
 * Atsarginė duomenų bazės kopija
 * Eksportuoja visą DB į .txt failą
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tik administratorius
if (!is_logged_in() || !is_admin()) {
    set_message('Neturite teisės kurti atsarginės kopijos.', 'error');
    redirect(SITE_URL . '/modules/reports/index.php');
}

// Nustatome failo pavadinimą
$backup_file = 'backup_' . date('Y-m-d_H-i-s') . '.txt';
$filepath = dirname(__FILE__) . '/backups/' . $backup_file;

// Sukuriame aplanką, jei nėra
$backup_dir = dirname(__FILE__) . '/backups';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

try {
    $mysqli = db_connect();
    $output = fopen($filepath, 'w');
    if (!$output) throw new Exception("Nepavyko sukurti failo: $filepath");

    // UTF-8
    fwrite($output, "-- Atsarginė duomenų bazės kopija\n");
    fwrite($output, "-- Sukurta: " . date('Y-m-d H:i:s') . "\n");
    fwrite($output, "-- Duomenų bazė: " . DB_NAME . "\n");
    fwrite($output, "-- Vartotojas: " . ($_SESSION['username'] ?? 'nežinomas') . "\n\n");
    fwrite($output, "SET NAMES utf8mb4;\n");
    fwrite($output, "SET foreign_key_checks = 0;\n\n");

    // Gauname visas lenteles
    $tables = [];
    $result = $mysqli->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    while ($row = $result->fetch_array(MYSQLI_NUM)) {
        $tables[] = $row[0];
    }

    foreach ($tables as $table) {
        // Lentelės struktūra
        $create_stmt = $mysqli->query("SHOW CREATE TABLE `$table`");
        $create_table = $create_stmt->fetch_array(MYSQLI_NUM)[1];
        fwrite($output, "-- --------------------------------------------------------\n");
        fwrite($output, "-- Lentelė: `$table`\n");
        fwrite($output, "-- --------------------------------------------------------\n\n");
        fwrite($output, "DROP TABLE IF EXISTS `$table`;\n");
        fwrite($output, "$create_table;\n\n");

        // Duomenys
        $stmt = $mysqli->prepare("SELECT * FROM `$table`");
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            fwrite($output, "-- Duomenys iš `$table`\n");
            $columns = [];
            foreach ($result->fetch_fields() as $field) {
                $columns[] = "`$field->name`";
            }

            while ($row = $result->fetch_array(MYSQLI_NUM)) {
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . $mysqli->real_escape_string($value) . "'";
                    }
                }
                $insert = "INSERT INTO `$table` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
                fwrite($output, $insert);
            }
            fwrite($output, "\n");
        }
        $stmt->close();
    }

    fwrite($output, "SET foreign_key_checks = 1;\n");
    fclose($output);

    // Siunčiame failą vartotojui
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $backup_file . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);

    // Ištriname failą po atsisiuntimo (saugumui)
    unlink($filepath);
    exit;

} catch (Exception $e) {
    error_log("Backup error: " . $e->getMessage());
    set_message('Klaida kuriant atsarginę kopiją: ' . $e->getMessage(), 'error');
    redirect(SITE_URL . '/modules/reports/index.php');
}
?>