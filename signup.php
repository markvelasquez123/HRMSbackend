<?php
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
} 

// Database config
$host = "localhost";
$username = "root";
$password = "";
$database = "hrms";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Read and decode JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Check if data is valid and contains required fields
if (!$data || !isset($data['name'], $data['email'], $data['password'], $data['company'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing input fields']);
    exit();
}

// Sanitize input
$fullname = $conn->real_escape_string($data['name']);
$email = $conn->real_escape_string($data['email']);
$password = $conn->real_escape_string($data['password']);
$company = $conn->real_escape_string($data['company']);

// Generate accid prefix
$prefix = '';
switch (strtolower($company)) {
    case 'asia navis':
        $prefix = 'an-';
        break;
    case 'rigel':
        $prefix = 'rig-';
        break;
    case 'peak hr':
        $prefix = 'phr-';
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid company']);
        exit();
}

// Get the next accid number for this company

$sql = "SELECT accid FROM signup WHERE accid LIKE '{$prefix}%' ORDER BY accid DESC LIMIT 1";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $lastNum = intval(substr($row['accid'], strlen($prefix)));
    $nextNum = $lastNum + 1;
} else {
    $nextNum = 1;
}
$accid = $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

// Use prepared statement to insert into the signup table
$stmt = $conn->prepare("INSERT INTO signup (fullname, email, password, company, accid) VALUES (?, ?, ?, ?, ?)");
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit();
}

$stmt->bind_param("sssss", $fullname, $email, $password, $company, $accid);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Signup successful', 'accid' => $accid]);
} else {
    echo json_encode(['success' => false, 'message' => 'Signup failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>