<?php
session_start();

require_once 'var.php';

$http_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';


if (in_array($http_origin, $IP_THIS)) {
    header("Access-Control-Allow-Origin: $http_origin");
    } else {
    
    error_log("Unauthorized CORS request from origin: " . $http_origin);
}

header("Access-Control-Allow-Methods: POST, OPTIONS, GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$orgPrefix = isset($_GET['org']) ? $_GET['org'] : null;

$validPrefixes = ['RGL', 'ASN', 'PHR'];
if (!$orgPrefix || !in_array($orgPrefix, $validPrefixes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid organization prefix']);
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


$sql = "SELECT * FROM employeee WHERE IDNumber LIKE ?";

$searchPattern = $orgPrefix . '%'; 
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $searchPattern);
$stmt->execute();
$result = $stmt->get_result();

$employeee = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $employeee[] = $row;
    }
}

$stmt->close();
$conn->close();

echo json_encode($employeee);
exit();
?>