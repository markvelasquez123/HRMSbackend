<?php
require_once 'var.php';

$http_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';


if (in_array($http_origin, $IP_THIS)) {
    header("Access-Control-Allow-Origin: $http_origin");
    } else {
    
    error_log("Unauthorized CORS request from origin: " . $http_origin);
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}


$host = 'localhost';
$dbname = 'hrms';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}


$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit();
}


$requiredFields = ['originalEmail', 'FirstName', 'LastName', 'EmailAddress', 'ContactNumber', 
                   'HomeAddress', 'BirthDate', 'Gender', 'PositionApplied'];

foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}


if (!filter_var($data['EmailAddress'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit();
}


if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['BirthDate'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid birth date format']);
    exit();
}

try {
   
    $checkStmt = $pdo->prepare("SELECT appID FROM applicant WHERE EmailAddress = ?");
    $checkStmt->execute([$data['originalEmail']]);
    
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Applicant not found']);
        exit();
    }

  
    if ($data['EmailAddress'] !== $data['originalEmail']) {
        $emailCheckStmt = $pdo->prepare("SELECT appID FROM applicant WHERE EmailAddress = ? AND EmailAddress != ?");
        $emailCheckStmt->execute([$data['EmailAddress'], $data['originalEmail']]);
        
        if ($emailCheckStmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Email address is already in use by another applicant']);
            exit();
        }
    }

  
    $sql = "UPDATE applicant SET 
            FirstName = :FirstName,
            MiddleName = :MiddleName,
            LastName = :LastName,
            EmailAddress = :EmailAddress,
            ContactNumber = :ContactNumber,
            HomeAddress = :HomeAddress,
            BirthDate = :BirthDate,
            Gender = :Gender,
            PositionApplied = :PositionApplied
            WHERE EmailAddress = :originalEmail";

    $stmt = $pdo->prepare($sql);
    
    $updateData = [
        ':FirstName' => trim($data['FirstName']),
        ':MiddleName' => trim($data['MiddleName'] ?? ''),
        ':LastName' => trim($data['LastName']),
        ':EmailAddress' => trim($data['EmailAddress']),
        ':ContactNumber' => trim($data['ContactNumber']),
        ':HomeAddress' => trim($data['HomeAddress']),
        ':BirthDate' => $data['BirthDate'],
        ':Gender' => $data['Gender'],
        ':PositionApplied' => trim($data['PositionApplied']),
        ':originalEmail' => $data['originalEmail']
    ];

    $stmt->execute($updateData);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'Applicant information updated successfully',
            'rowsAffected' => $stmt->rowCount()
        ]);
    } else {
        echo json_encode([
            'success' => true, 
            'message' => 'No changes were made to the applicant record',
            'rowsAffected' => 0
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$pdo = null;
?>