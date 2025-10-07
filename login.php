<?php
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host = "localhost";
$username = "root";
$password = "";
$database = "hrms";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}


$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['EmailAddress'], $data['password'])) {
    echo json_encode(['success' => false, 'message' => 'Missing email or password']);
    exit();
}

$EmailAddress = $data['EmailAddress'];
$password = $data['password'];


$stmt = $conn->prepare("SELECT EmailAddress, password FROM signup WHERE EmailAddress = ?");
$stmt->bind_param("s", $EmailAddress);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    
    if ($password === $user['password']) {
        echo json_encode(['success' => true, 'message' => 'Login successful', 'EmailAddress' => $user['EmailAddress']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Incorrect password']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}

$stmt->close();
$conn->close();
?>
