<?php
/**
 * Atsarginė duomenų bazės kopija
 * Eksportuoja visą DB struktūrą bei duomenis tiesiai į atsisiunčiamą failą (Streaming)
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tik administratorius gali atlikti DB atsarginių kopijų operacijas
if (!is_logged_in() || !is_admin()) {
    set_message('Neturite teisės kurti atsarginės kopijos.', 'error');
    redirect(SITE_URL . '/modules/reports/index.php');
    exit;
}

// IŠTAISYTA: Klaidų rodymas išjungiamas, kad nesugadintų SQL eksporto failo
error_reporting(0);
ini_set('display_errors', 0);

$backup_file = 'backup_' . date('Y-m-d_H-i-s') . '.txt';

try {
    $mysqli = db_connect();
    
    // IŠTAISYTA: Išvalome bet kokį ankstesnį buferį (nematomus tarpus ir pan.)
    while (ob_get_level()) { ob_end_clean(); }
    
    // IŠTAISYTA: Išsiunčiame antraštes iš karto. Naršyklė supranta, kad gaus failą
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $backup_file . '"');
    
    // IŠTAISYTA: Atidarome tiesioginį išvesties srautą į naršyklę. 
    // Joks laikinas failas serveryje nekuriamas, todėl nėra pavojaus, kad jis nutekės!
    $output = fopen('php://output', 'w');
    if (!$output) throw new Exception("Nepavyko atidaryti išvesties srauto.");

    // SQL struktūros pradžios metaduomenys
    fwrite($output, "-- Olimpiadų Sistemos Atsarginė Kopija\n");
    fwrite($output, "-- Sukurta: " . date('Y-m-d H:i:s') . "\n");
    fwrite($output, "-- Duomenų bazė: " . DB_NAME . "\n");
    fwrite($output, "-- Administratorius: " . htmlspecialchars($_SESSION['user_id'] ?? 'nežinomas', ENT_QUOTES, 'UTF-8') . "\n\n");
    fwrite($output, "SET NAMES utf8mb4;\n");
    fwrite($output, "SET foreign_key_checks = 0;\n\n");

    // Gauname visas duomenų bazės lenteles
    $tables = [];
    $result = $mysqli->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    if ($result) {
        while ($row = $result->fetch_array(MYSQLI_NUM)) {
            $tables[] = $row[0];
        }
    }

    foreach ($tables as $table) {
        // Generuojame lentelės struktūrą (CREATE TABLE)
        $create_stmt = $mysqli->query("SHOW CREATE TABLE `$table`");
        if ($create_stmt) {
            $create_table = $create_stmt->fetch_array(MYSQLI_NUM)[1];
            fwrite($output, "-- --------------------------------------------------------\n");
            fwrite($output, "-- Lentelės struktūra: `$table`\n");
            fwrite($output, "-- --------------------------------------------------------\n\n");
            fwrite($output, "DROP TABLE IF EXISTS `$table`;\n");
            fwrite($output, "$create_table;\n\n");
            $create_stmt->free();
        }

        // IŠTAISYTA: Naudojame MYSQLI_USE_RESULT (Unbuffered query)
        // Tai užtikrina, kad didelės lentelės nesunaudos viso serverio RAM atminties.
        $data_result = $mysqli->query("SELECT * FROM `$table`", MYSQLI_USE_RESULT);
        if ($data_result) {
            $fields = $data_result->fetch_fields();
            if (count($fields) > 0) {
                fwrite($output, "-- Duomenys lentelėje `$table`\n");
                
                $columns = [];
                foreach ($fields as $field) {
                    $columns[] = "`$field->name`";
                }
                $columns_str = implode(', ', $columns);

                // Skaitome eilutę po eilutės, o ne viską iškart
                while ($row = $data_result->fetch_array(MYSQLI_NUM)) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $mysqli->real_escape_string($value) . "'";
                        }
                    }
                    $insert = "INSERT INTO `$table` ($columns_str) VALUES (" . implode(', ', $values) . ");\n";
                    fwrite($output, $insert);
                }
                fwrite($output, "\n");
            }
            $data_result->free(); // Atlaisviname atmintį pereinant prie kitos lentelės
        }
    }

    fwrite($output, "SET foreign_key_checks = 1;\n");
    fclose($output);
    exit; // Užbaigiame skriptą

} catch (Exception $e) {
    // Jei ivyko klaida dar nepradėjus siųsti failo, galime grąžinti į puslapį
    error_log("Backup error: " . $e->getMessage());
    set_message('Klaida kuriant atsarginę kopiją: ' . $e->getMessage(), 'error');
    redirect(SITE_URL . '/modules/reports/index.php');
    exit;
}
?>