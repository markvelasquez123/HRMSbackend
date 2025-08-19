<?php
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");


// --- Example user data (for demo only)
$host = "localhost";
$user = "root";
$password = "";
$db = "hrms";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// --- Function 1: Hello
function hello() {
    echo json_encode(['message' => 'Hello from signin.php!']);
}

// --- Function 2: Sign in
function signin($demo_user) {
    // Get raw input from frontend (POST)
    $input = json_decode(file_get_contents("php://input"), true);

    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    if ($username === $demo_user['username'] && $password === $demo_user['password']) {
        echo json_encode(['success' => true, 'message' => 'Login successful']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
}

// --- Router (based on ?action=...)
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
