<?php
require_once 'var.php';

$http_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';


if (in_array($http_origin, $IP_THIS)) {
    header("Access-Control-Allow-Origin: $http_origin");
    } else {
    
    error_log("Unauthorized CORS request from origin: " . $http_origin);
}
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
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

$sql = "SELECT DISTINCT PositionApplied FROM applicant ORDER BY PositionApplied ASC";
$result = $conn->query($sql);
$positions = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $positions[] = $row['PositionApplied'];
    }
}

$conn->close();

echo json_encode(['positions' => $positions]);
exit();
?>
