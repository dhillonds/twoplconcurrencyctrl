<?php
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "dbconcctrl";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} 
// sql to delete a record
$sql = "DELETE FROM trans_table WHERE 1";
if ($conn->query($sql) === TRUE) {
} else {
    echo "Error deleting record: " . $conn->error;
}
$sql = "DELETE FROM lock_table WHERE 1";
if ($conn->query($sql) === TRUE) {
} else {
    echo "Error deleting record: " . $conn->error;
}