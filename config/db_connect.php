<?php
/**
 * Duomenų bazės prisijungimo failas
 * 
 * Šis failas naudojamas prisijungimui prie duomenų bazės
 */

require_once 'config.php';

// Laikome ryšį globaliai, kad išvengtume daugkartinių prisijungimų
$global_db_connection = null;

/**
 * Prisijungimas prie duomenų bazės naudojant mysqli
 */
function db_connect() {
    global $global_db_connection;
    
    if ($global_db_connection === null) {
        $global_db_connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // Tikriname prisijungimą
        if ($global_db_connection->connect_error) {
            error_log("Nepavyko prisijungti prie duomenų bazės: " . $global_db_connection->connect_error);
            die("Nepavyko prisijungti prie duomenų bazės: " . $global_db_connection->connect_error);
        }
        
        // Nustatome koduotę
        $global_db_connection->set_charset("utf8mb4"); // Naudojame utf8mb4, kad atitiktų lentelės koduotę
    }
    
    return $global_db_connection;
}

/**
 * Saugus užklausos vykdymas
 * 
 * @param string $sql SQL užklausa
 * @param array $params Parametrai
 * @param string $types Parametrų tipai (i - integer, s - string, d - double, b - blob)
 * @return mysqli_stmt|false Grąžina paruoštą užklausą arba false klaidos atveju
 */
function db_query($sql, $params = [], $types = '') {
    $conn = db_connect();
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Klaida ruošiant užklausą: " . $conn->error . " | SQL: " . $sql);
        return false;
    }
    
    if (!empty($params)) {
        if (empty($types)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_string($param)) {
                    $types .= 's';
                } else {
                    $types .= 'b';
                }
            }
        }
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        error_log("Klaida vykdant užklausą: " . $stmt->error . " | SQL: " . $sql);
        $stmt->close();
        return false;
    }
    
    return $stmt;
}

/**
 * Gauna visus užklausos rezultatus
 * 
 * @param mysqli_stmt $stmt Paruošta užklausa
 * @return array|false Rezultatų masyvas arba false jei klaida
 */
function db_get_results($stmt) {
    if (!$stmt || !is_object($stmt)) {
        error_log("Klaida: neteisingas statement objektas.");
        return false;
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        error_log("Klaida gaunant rezultatus: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $results = [];
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    
    // Uždarykime result ir statement
    $result->free();
    $stmt->close();
    
    return $results;
}

/**
 * Gauti vieną rezultatą
 * 
 * @param mysqli_stmt $stmt Paruošta užklausa
 * @return array|false Grąžina rezultatą arba false klaidos atveju
 */
function db_get_row($stmt) {
    if (!$stmt || !is_object($stmt)) {
        error_log("Klaida: neteisingas statement objektas.");
        return false;
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        error_log("Klaida gaunant rezultatą: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $row = $result->fetch_assoc();
    
    // Uždarykime result ir statement
    $result->free();
    $stmt->close();
    
    return $row;
}

/**
 * Įterpti duomenis į lentelę
 * 
 * @param string $table Lentelės pavadinimas
 * @param array $data Duomenys (stulpelis => reikšmė)
 * @return int|false Grąžina įterpto įrašo ID arba false klaidos atveju
 */
function db_insert($table, $data) {
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    $stmt = db_query($sql, array_values($data));
    
    if (!$stmt) {
        return false;
    }
    
    $conn = db_connect();
    $insert_id = $conn->insert_id;
    $stmt->close();
    return $insert_id;
}

/**
 * Atnaujinti duomenis lentelėje
 * 
 * @param string $table Lentelės pavadinimas
 * @param array $data Duomenys (stulpelis => reikšmė)
 * @param string $where WHERE sąlyga
 * @param array $where_params WHERE parametrai
 * @return bool Grąžina true jei pavyko, false klaidos atveju
 */
function db_update($table, $data, $where, $where_params = []) {
    $set = [];
    $types = '';
    $values = [];
    
    // Nustatome stulpelius ir jų tipus
    foreach ($data as $column => $value) {
        $set[] = "$column = ?";
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } elseif (is_string($value)) {
            $types .= 's';
        } else {
            $types .= 'b';
        }
        $values[] = $value;
    }
    $set = implode(', ', $set);
    
    // Nustatome WHERE parametrų tipus
    foreach ($where_params as $param) {
        if (is_int($param)) {
            $types .= 'i';
        } elseif (is_float($param)) {
            $types .= 'd';
        } elseif (is_string($param)) {
            $types .= 's';
        } else {
            $types .= 'b';
        }
    }
    
    $sql = "UPDATE $table SET $set WHERE $where";
    $params = array_merge($values, $where_params);
    
    $stmt = db_query($sql, $params, $types);
    
    if ($stmt === false) {
        $conn = db_connect();
        error_log("Klaida atnaujinant duomenis: " . $conn->error . " | SQL: " . $sql);
        return false;
    }
    
    $stmt->close();
    return true;
}

/**
 * Ištrinti duomenis iš lentelės
 * 
 * @param string $table Lentelės pavadinimas
 * @param string $where WHERE sąlyga
 * @param array $params WHERE parametrai
 * @return bool Grąžina true jei pavyko, false klaidos atveju
 */
function db_delete($table, $where, $params = []) {
    $sql = "DELETE FROM $table WHERE $where";
    $stmt = db_query($sql, $params);
    
    if ($stmt) {
        $stmt->close();
    }
    
    return $stmt !== false;
}
?>