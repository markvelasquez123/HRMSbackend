<?php
require_once 'var.php';

$http_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

if (in_array($http_origin, $IP_THIS)) {
    header("Access-Control-Allow-Origin: $http_origin");
} else {
    error_log("Unauthorized CORS request from origin: " . $http_origin);
}
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

// Updated query to include ID and status
$stmt = $conn->prepare("SELECT ID, EmailAddress, password, status FROM signup WHERE EmailAddress = ?");
$stmt->bind_param("s", $EmailAddress);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // Verify password
    if ($password === $user['password']) {
        
        // REACTIVATE ACCOUNT IF IT WAS DEACTIVATED
        if (isset($user['status']) && $user['status'] === 'deactivated') {
            $updateStmt = $conn->prepare("UPDATE signup SET status = 'active' WHERE ID = ?");
            $updateStmt->bind_param("i", $user['ID']);
            
            if ($updateStmt->execute()) {
                error_log("✅ Account reactivated for user ID: " . $user['ID'] . " - Email: " . $user['EmailAddress']);
            } else {
                error_log("❌ Failed to reactivate account for user ID: " . $user['ID']);
            }
            
            $updateStmt->close();
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Login successful', 
            'EmailAddress' => $user['EmailAddress'],
            'status' => 'active' // Always return active after successful login
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Incorrect password']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}

$stmt->close();
$conn->close();
?>