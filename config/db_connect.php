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

function db_query($sql, $params = [], $types = '') {
    $conn = db_connect();
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("SQL Prepare klaida: " . $conn->error);
        die("Kritinė klaida formuojant užklausą.");
    }

    if (!empty($params)) {
        if (empty($types)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
        }
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    return $stmt;
}

function db_get_row($stmt) {
    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : null;
}

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

function db_insert($table, $data) {
    $conn = db_connect();
    $columns = implode(', ', array_map(fn($col) => "`$col`", array_keys($data)));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    $values = array_values($data);
    
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

/**
 * Atnaujinti duomenis lentelėje
 */
function db_update($table, $data, $where, $where_params = []) {
    $set = [];
    $types = '';
    $values = [];

    foreach ($data as $column => $value) {
        $set[] = "`" . str_replace("`", "``", $column) . "` = ?";
        if (is_int($value))        $types .= 'i';
        elseif (is_float($value))  $types .= 'd';
        else                       $types .= 's';
        $values[] = $value;
    }

    foreach ($where_params as $param) {
        if (is_int($param))        $types .= 'i';
        elseif (is_float($param))  $types .= 'd';
        else                       $types .= 's';
    }

    $sql = "UPDATE `$table` SET " . implode(', ', $set) . " WHERE $where";
    $params = array_merge($values, $where_params);

    $stmt = db_connect()->prepare($sql);
    if (!$stmt) {
        error_log("db_update prepare klaida: " . db_connect()->error);
        return false;
    }
    $stmt->bind_param($types, ...$params);
    return $stmt->execute();
}