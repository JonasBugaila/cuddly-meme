<?php
require_once __DIR__ . '/config.php';

$global_db_connection = null;

function db_connect() {
    global $global_db_connection;
    if ($global_db_connection === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $global_db_connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $global_db_connection->set_charset("utf8mb4");
        } catch (mysqli_sql_exception $e) {
            error_log("DB klaida: " . $e->getMessage());
            die("Sistemos klaida prisijungiant prie duomenų bazės. Kreipkitės į administratorių.");
        }
    }
    return $global_db_connection;
}

// IŠMANI UNIVERSALI FUNKCIJA SQL UŽKLAUSOMS
function db_query($sql, $params = [], $types = '') {
    $conn = db_connect();
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("SQL Prepare klaida: " . $conn->error);
        die("Kritinė klaida formuojant užklausą.");
    }

    if (!empty($params)) {
        // JEI TIPAI NENURODYTI, SISTEMA JUOS ATPAŽĮSTA AUTOMATIŠKAI
        if (empty($types)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's'; // Viskas kita (tekstas, datos ir pan.) eina kaip string
                }
            }
        }
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    return $stmt;
}

// Funkcija gauti vienai eilutei
function db_get_row($stmt) {
    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : null;
}

// Funkcija gauti visiems rezultatams
function db_get_results($stmt) {
    $result = $stmt->get_result();
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

// Funkcija įterpimui (Insert) su automatiniu tipų atpažinimu
function db_insert($table, $data) {
    $conn = db_connect();
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    $values = array_values($data);
    
    // Automatinis tipų nustatymas
    $types = '';
    foreach ($values as $val) {
        if (is_int($val)) $types .= 'i';
        elseif (is_float($val)) $types .= 'd';
        else $types .= 's';
    }
    
    $sql = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    return $stmt->execute();
}
?>