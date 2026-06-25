<?php
/**
 * Atsarginė duomenų bazės kopija
 * Eksportuoja visą DB struktūrą bei duomenis į atsisiunčiamą SQL/TXT failą
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tik administratorius gali atlikti DB atsarginių kopijų operacijas
if (!is_logged_in() || !is_admin()) {
    set_message('Neturite teisės kurti atsarginės kopijos.', 'error');
    redirect(SITE_URL . '/modules/reports/index.php');
}

$template_dir = dirname(dirname(dirname(__FILE__))) . '/config';
$backup_file = 'backup_' . date('Y-m-d_H-i-s') . '.txt';
$filepath = $template_dir . '/' . $backup_file;

try {
    $mysqli = db_connect();
    $output = fopen($filepath, 'w');
    if (!$output) throw new Exception("Nepavyko sukurti failo konfigūracijos aplanke.");

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
        $create_table = $create_stmt->fetch_array(MYSQLI_NUM)[1];
        fwrite($output, "-- --------------------------------------------------------\n");
        fwrite($output, "-- Lentelės struktūra: `$table`\n");
        fwrite($output, "-- --------------------------------------------------------\n\n");
        fwrite($output, "DROP TABLE IF EXISTS `$table`;\n");
        fwrite($output, "$create_table;\n\n");

        // Eksportuojame pačius lentelės duomenis
        $data_result = $mysqli->query("SELECT * FROM `$table`");
        if ($data_result && $data_result->num_rows > 0) {
            fwrite($output, "-- Duomenys lentelėje `$table`\n");
            
            $columns = [];
            foreach ($data_result->fetch_fields() as $field) {
                $columns[] = "`$field->name`";
            }
            $columns_str = implode(', ', $columns);

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
    }

    fwrite($output, "SET foreign_key_checks = 1;\n");
    fclose($output);

    // Atiduodame sugeneruotą failą tiesiai į naršyklę atsisiuntimui
    if (file_exists($filepath)) {
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $backup_file . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);

        // Ištriname laikiną failą iš serverio dėl saugumo
        unlink($filepath);
        exit;
    } else {
        throw new Exception("Sukurto failo nepavyko rasti serveryje.");
    }

} catch (Exception $e) {
    error_log("Backup error: " . $e->getMessage());
    set_message('Klaida kuriant atsarginę kopiją: ' . $e->getMessage(), 'error');
    redirect(SITE_URL . '/modules/olympiads/index.php');
}
?>