<?php
/**
 * Metų duomenų eksportas (archyvavimas prieš valymą)
 *
 * Eksportuoja TIK metų/renginių duomenis (dalyviai, konkursai, sistemos ir
 * mokytojų žurnalus) - NE vartotojus/klases/kvalifikacijas, kurios lieka
 * sistemoje ir naujais metais.
 *
 * Naudoja tą patį patikrintą streaming metodą kaip modules/backup/backup_db.php.
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in() || !is_admin()) {
    set_message('Neturite teisės eksportuoti metų duomenų.', 'error');
    redirect(SITE_URL . '/modules/admin/year_reset.php');
    exit;
}

// SAUGU: griežtai fiksuotas lentelių sąrašas - joks vartotojo įvestas parametras
// negali paveikti, kurios lentelės eksportuojamos.
$year_tables = ['dalyviai', 'konkursai', 'system_logs', 'teacher_activity_log'];

error_reporting(0);
ini_set('display_errors', 0);

$export_file = 'metu_archyvas_' . date('Y-m-d_H-i-s') . '.sql';

try {
    $mysqli = db_connect();

    while (ob_get_level()) { ob_end_clean(); }

    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $export_file . '"');

    $output = fopen('php://output', 'w');
    if (!$output) throw new Exception("Nepavyko atidaryti išvesties srauto.");

    fwrite($output, "-- Olimpiadų Sistemos - Metų duomenų archyvas\n");
    fwrite($output, "-- Sukurta: " . date('Y-m-d H:i:s') . "\n");
    fwrite($output, "-- Duomenų bazė: " . DB_NAME . "\n");
    fwrite($output, "-- Administratorius: " . htmlspecialchars($_SESSION['user_id'] ?? 'nežinomas', ENT_QUOTES, 'UTF-8') . "\n");
    fwrite($output, "-- SVARBU: šiame faile TIK dalyviai/konkursai/žurnalai - vartotojai,\n");
    fwrite($output, "-- klasės ir kvalifikacijos NEEKSPORTUOJAMOS (jos lieka sistemoje).\n\n");
    fwrite($output, "SET NAMES utf8mb4;\n");
    fwrite($output, "SET foreign_key_checks = 0;\n\n");

    foreach ($year_tables as $table) {
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
            $data_result->free();
        }
    }

    fwrite($output, "SET foreign_key_checks = 1;\n");
    fclose($output);

    // NAUJA: fiksuojame eksportą sesijoje - tik po sėkmingo eksporto leidžiama
    // pereiti prie duomenų valymo (žr. year_reset.php). Galioja 60 minučių.
    start_session();
    $_SESSION['year_export_completed_at'] = time();

    log_action('Metų duomenų eksportas', 'Administratorius eksportavo metų archyvą prieš duomenų valymą.');

    exit;

} catch (Exception $e) {
    error_log("Year export error: " . $e->getMessage());
    set_message('Klaida eksportuojant metų duomenis: ' . $e->getMessage(), 'error');
    redirect(SITE_URL . '/modules/admin/year_reset.php');
    exit;
}
?>
