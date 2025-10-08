<?php
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
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


if (!$data || !isset($data['email'], $data['password'], $data['company'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing input fields']);
    exit();
}


if (isset($data['name'])) {
    $fullname = $conn->real_escape_string(trim($data['name']));
} else {
    $firstName = isset($data['FirstName']) ? trim($data['FirstName']) : '';
    $middleName = isset($data['MiddleName']) ? trim($data['MiddleName']) : '';
    $lastName = isset($data['LastName']) ? trim($data['LastName']) : '';
    
    $fullnameParts = array_filter([$firstName, $middleName, $lastName]);
    $fullname = $conn->real_escape_string(implode(' ', $fullnameParts));
}

$EmailAddress = $conn->real_escape_string($data['email']);
$password = $conn->real_escape_string($data['password']);
$Company = $conn->real_escape_string($data['company']);

$dateHired = isset($data['dateHired']) ? $data['dateHired'] : date('Y-m-d');

if (empty($fullname)) {
    echo json_encode(['success' => false, 'message' => 'Name is required']);
    exit();
}

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

$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM employeee");
$countStmt->execute();
$countResult = $countStmt->get_result();
$employeeCount = 1;

if ($countResult && $row = $countResult->fetch_assoc()) {
    $employeeCount = $row['total'] + 1;
}
$countStmt->close();

$employeeNum = str_pad($employeeCount, 2, '0', STR_PAD_LEFT);

$dateStr = $year . $month . $employeeNum;

$pattern = $companyPrefix . '-' . $year . $month . '%';
$stmt = $conn->prepare("SELECT IDNumber FROM signup WHERE IDNumber LIKE ? ORDER BY IDNumber DESC LIMIT 1");
$stmt->bind_param("s", $pattern);
$stmt->execute();
$result = $stmt->get_result();

$sequence = 1;
if ($result && $row = $result->fetch_assoc()) {
    $lastIDNumber = $row['IDNumber'];
 
    $parts = explode('-', $lastIDNumber);
    if (count($parts) >= 2) {
        $lastPart = $parts[1];
        $sequence = intval(substr($lastPart, 6)) + 1;
    }
}
$stmt->close();

$IDNumber = $companyPrefix . '-' . $dateStr . $sequence;

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