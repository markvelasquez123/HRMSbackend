<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log all requests for debugging
error_log("Pool API called - Method: " . $_SERVER['REQUEST_METHOD']);

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Database configuration
$host = 'localhost';
$dbname = 'hrms';  // Your database name
$username = 'root'; // Your database username
$password = '';     // Your database password

try {
    // Get JSON input
    $inputRaw = file_get_contents('php://input');
    error_log("Raw input received: " . $inputRaw);
    
    $input = json_decode($inputRaw, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    
    error_log("Parsed input: " . print_r($input, true));
    
    // Validate required fields
    if (!isset($input['name']) || !isset($input['position']) || !isset($input['status'])) {
        throw new Exception('Missing required fields. Received: ' . print_r($input, true));
    }
    
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    error_log("Database connection successful");
    
    // Prepare data
    $name = trim($input['name']);
    $position = trim($input['position']);
    $department = isset($input['department']) ? trim($input['department']) : 'Unassigned';
    $phone = isset($input['phone']) ? trim($input['phone']) : '';
    $status = $input['status']; // 'Accepted' or 'Rejected'
    
    error_log("Processed data - Name: $name, Position: $position, Department: $department, Phone: $phone, Status: $status");
    
    // Validate status
    if (!in_array($status, ['Accepted', 'Rejected'])) {
        throw new Exception('Invalid status. Must be either "Accepted" or "Rejected". Received: ' . $status);
    }
    
    // Check if pool table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'pool'");
    if ($tableCheck->rowCount() == 0) {
        throw new Exception('Pool table does not exist in database');
    }
    
    error_log("Pool table exists, proceeding with insert/update");
    
    // Check if record already exists to prevent duplicates
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM pool WHERE name = ? AND position = ?");
    $checkStmt->execute([$name, $position]);
    $exists = $checkStmt->fetchColumn();
    
    error_log("Existing records found: " . $exists);
    
    if ($exists > 0) {
        // Update existing record instead of creating duplicate
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
        // Insert new record
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