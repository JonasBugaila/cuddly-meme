<?php
$host = 'localhost';
$user = 'testlt_oli';
$pass = 'olimipic=0LI'; // Pakeiskite į tikrąjį slaptažodį
$db = 'testlt_olimpiados';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Klaida: " . $conn->connect_error);
} else {
    echo "Prisijungimas sėkmingas!";
}
$conn->close();
?>