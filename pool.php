<?php
require_once 'var.php';

$http_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';


if (in_array($http_origin, $IP_THIS)) {
    header("Access-Control-Allow-Origin: $http_origin");
    } else {
    
    error_log("Unauthorized CORS request from origin: " . $http_origin);
}

header('Content-Type: application/json');

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');


error_reporting(E_ALL);
ini_set('display_errors', 1);


error_log("Pool API called - Method: " . $_SERVER['REQUEST_METHOD']);


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
    
    $inputRaw = file_get_contents('php://input');
    error_log("Raw input received: " . $inputRaw);
    
    $input = json_decode($inputRaw, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    
    error_log("Parsed input: " . print_r($input, true));
    
   
    if (!isset($input['name']) || !isset($input['position']) || !isset($input['status'])) {
        throw new Exception('Missing required fields. Received: ' . print_r($input, true));
    }
    
   
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    error_log("Database connection successful");
    
  
    $name = trim($input['name']);
    $position = trim($input['position']);
    $department = isset($input['department']) ? trim($input['department']) : 'Unassigned';
    $phone = isset($input['phone']) ? trim($input['phone']) : '';
    $status = $input['status'];
    
    error_log("Processed data - Name: $name, Position: $position, Department: $department, Phone: $phone, Status: $status");
    
    
    if (!in_array($status, ['Accepted', 'Rejected'])) {
        throw new Exception('Invalid status. Must be either "Accepted" or "Rejected". Received: ' . $status);
    }
    
   
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'pool'");
    if ($tableCheck->rowCount() == 0) {
        throw new Exception('Pool table does not exist in database');
    }
    
    error_log("Pool table exists, proceeding with insert/update");
    

    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM pool WHERE name = ? AND position = ?");
    $checkStmt->execute([$name, $position]);
    $exists = $checkStmt->fetchColumn();
    
    error_log("Existing records found: " . $exists);
    
    if ($exists > 0) {
        
        $updateStmt = $pdo->prepare("
            UPDATE pool 
            SET department = ?, phone = ?, status = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE name = ? AND position = ?
        ");
        $result = $updateStmt->execute([$department, $phone, $status, $name, $position]);
        
        error_log("Update executed, result: " . ($result ? 'success' : 'failed'));
        
        echo json_encode([
            'success' => true,
            'message' => 'Pool record updated successfully',
            'action' => 'updated',
            'data' => [
                'name' => $name,
                'position' => $position,
                'department' => $department,
                'phone' => $phone,
                'status' => $status
            ]
        ]);
    } else {

        $insertStmt = $pdo->prepare("
            INSERT INTO pool (name, position, department, phone, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $result = $insertStmt->execute([$name, $position, $department, $phone, $status]);
        $insertId = $pdo->lastInsertId();
        
        error_log("Insert executed, result: " . ($result ? 'success' : 'failed') . ", ID: " . $insertId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Pool record inserted successfully',
            'action' => 'inserted',
            'id' => $insertId,
            'data' => [
                'name' => $name,
                'position' => $position,
                'department' => $department,
                'phone' => $phone,
                'status' => $status
            ]
        ]);
    }
    
} catch (PDOException $e) {
    $errorMsg = "Database error in insert_to_pool.php: " . $e->getMessage();
    error_log($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage(),
        'type' => 'database_error'
    ]);
} catch (Exception $e) {
    $errorMsg = "General error in insert_to_pool.php: " . $e->getMessage();
    error_log($errorMsg);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'type' => 'general_error'
    ]);
}
?> 