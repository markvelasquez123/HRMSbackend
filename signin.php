<?php
require_once 'var.php';

$http_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';


if (in_array($http_origin, $IP_THIS)) {
    header("Access-Control-Allow-Origin: $http_origin");
    } else {
    
    error_log("Unauthorized CORS request from origin: " . $http_origin);
}


header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");



$host = "localhost";
$user = "root";
$password = "";
$db = "hrms";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}


function hello() {
    echo json_encode(['message' => 'Hello from signin.php!']);
}


function signin($demo_user) {
 
    $input = json_decode(file_get_contents("php://input"), true);

    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    if ($username === $demo_user['username'] && $password === $demo_user['password']) {
        echo json_encode(['success' => true, 'message' => 'Login successful']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
}


$action = $_GET['action'] ?? '';

switch ($action) {
    case 'hello':
        hello();
        break;

    case 'signin':
        signin($demo_user);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        break;
}
?>
