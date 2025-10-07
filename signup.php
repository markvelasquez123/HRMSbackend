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
if (!$data || !isset($data['email'], $data['password'], $data['company'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing input fields']);
    exit();
}

// Handle fullname - could be a single string or separate name parts
if (isset($data['name'])) {
    // Coming from AddEmployeeForm checkbox (already combined)
    $fullname = $conn->real_escape_string(trim($data['name']));
} else {
    // Coming from separate FirstName, MiddleName, LastName fields
    $firstName = isset($data['FirstName']) ? trim($data['FirstName']) : '';
    $middleName = isset($data['MiddleName']) ? trim($data['MiddleName']) : '';
    $lastName = isset($data['LastName']) ? trim($data['LastName']) : '';
    
    // Build fullname string
    $fullnameParts = array_filter([$firstName, $middleName, $lastName]);
    $fullname = $conn->real_escape_string(implode(' ', $fullnameParts));
}

$EmailAddress = $conn->real_escape_string($data['email']);
$password = $conn->real_escape_string($data['password']);
$Company = $conn->real_escape_string($data['company']);

// Get DateHired if provided, otherwise use current date
$dateHired = isset($data['dateHired']) ? $data['dateHired'] : date('Y-m-d');

// Validate fullname is not empty
if (empty($fullname)) {
    echo json_encode(['success' => false, 'message' => 'Name is required']);
    exit();
}

// Generate ID prefix based on company
$companyPrefix = '';
switch ($Company) {
    case 'Asia Navis':
        $companyPrefix = 'ASN';
        break;
    case 'Rigel':
        $companyPrefix = 'RGL';
        break;
    case 'PeakHR':
        $companyPrefix = 'PHR';
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid company']);
        exit();
}


$hireDate = new DateTime($dateHired);
$year = $hireDate->format('y');   
$month = $hireDate->format('m');  
$day = $hireDate->format('d');  


$dateStr = $year . $month . $day;

// Find the last IDNumber for this company and date
$pattern = $companyPrefix . '-' . $dateStr . '%';
$stmt = $conn->prepare("SELECT IDNumber FROM signup WHERE IDNumber LIKE ? ORDER BY IDNumber DESC LIMIT 1");
$stmt->bind_param("s", $pattern);
$stmt->execute();
$result = $stmt->get_result();

$sequence = 1; // Start from 1
if ($result && $row = $result->fetch_assoc()) {
    $lastIDNumber = $row['IDNumber'];
    // Extract sequence number (everything after the date part)
    // Example: ASN-251061 -> extract 1
    $parts = explode('-', $lastIDNumber);
    if (count($parts) >= 2) {
        $lastPart = $parts[1];
        $sequence = intval(substr($lastPart, 6)) + 1; // Skip 6 date digits (YYMMDD)
    }
}
$stmt->close();

// Generate final IDNumber: PREFIX-YYMMDDSEQUENCE
// Example: ASN-251061, ASN-251062, etc.
$IDNumber = $companyPrefix . '-' . $dateStr . $sequence;

// Check if email already exists
$checkEmail = $conn->prepare("SELECT EmailAddress FROM signup WHERE EmailAddress = ?");
$checkEmail->bind_param("s", $EmailAddress);
$checkEmail->execute();
$checkEmail->store_result();

if ($checkEmail->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already exists']);
    $checkEmail->close();
    $conn->close();
    exit();
}
$checkEmail->close();

// Insert into signup table
$insertStmt = $conn->prepare("INSERT INTO signup (fullname, EmailAddress, password, Company, IDNumber) VALUES (?, ?, ?, ?, ?)");
if ($insertStmt === false) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit();
}

$insertStmt->bind_param("sssss", $fullname, $EmailAddress, $password, $Company, $IDNumber);

if ($insertStmt->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => 'Signup successful', 
        'IDNumber' => $IDNumber,
        'fullname' => $fullname
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Signup failed: ' . $insertStmt->error]);
}

$insertStmt->close();
$conn->close();
?>