<?php
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, OPTIONS, GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host = 'localhost';
$dbname = 'hrms';
$username = 'root';
$password = '';

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$sql = "SELECT * FROM employeee";
$result = $conn->query($sql);
$employeee = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $employeee[] = $row;
    }
}
$sql = "SELECT 
    *
    FROM companytable";
$result = $conn->query($sql);
$companytable = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $companytable[] = $row;
    }
}
$sql = "SELECT
*
FROM imgtable";
$result = $conn->query($sql);
echo json_encode($employeee);
exit;