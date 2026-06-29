<?php
$host = 'localhost';
$user = '*******';
$pass = '**********'; // Pakeiskite į tikrąjį slaptažodį
$db = 'testlt_olimpiados';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Klaida: " . $conn->connect_error);
} else {
    echo "Prisijungimas sėkmingas!";
}
$conn->close();
?>
