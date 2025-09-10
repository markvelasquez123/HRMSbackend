<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host = 'localhost';
$dbname = 'hrms'; 
$username = 'root'; 
$password = ''; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $json = file_get_contents('php://input');
        error_log("Raw JSON input: " . $json);
        
        if (empty($json)) {
            throw new Exception('No JSON data received in request body');
        }
        
        $data = json_decode($json, true);
        error_log("Decoded data: " . print_r($data, true));
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON data received: ' . json_last_error_msg());
        }

        if (empty($data)) {
            throw new Exception('Empty data received after JSON decode');
        }

        if (!isset($data['employeeId']) || !isset($data['status'])) {
            throw new Exception('Missing required fields: employeeId and status');
        }
        
        $employeeId = $data['employeeId'];
        $status = $data['status'];
        
        // Validate status values
        $validStatuses = ['On Process', 'Deployed', 'Repatriated'];
        if (!in_array($status, $validStatuses)) {
            throw new Exception('Invalid status. Must be: ' . implode(', ', $validStatuses));
        }
        
        // Check if employee exists
        $checkStmt = $pdo->prepare("SELECT id FROM ofw WHERE id = ?");
        $checkStmt->execute([$employeeId]);
        if (!$checkStmt->fetch()) {
            throw new Exception('Employee not found with ID: ' . $employeeId);
        }
        
        // Check if status column exists, if not add it
        $columnCheck = $pdo->query("SHOW COLUMNS FROM ofw LIKE 'status'");
        if ($columnCheck->rowCount() == 0) {
            error_log("Adding status column to ofw table");
            $pdo->exec("ALTER TABLE ofw ADD COLUMN status VARCHAR(50) DEFAULT 'On Process'");
        }
        
        // Update the status
        $updateStmt = $pdo->prepare("UPDATE ofw SET status = ?, updated_at = NOW() WHERE id = ?");
        $result = $updateStmt->execute([$status, $employeeId]);
        
        if ($result) {
            error_log("Status updated successfully for employee ID: $employeeId to status: $status");
            echo json_encode([
                'success' => true,
                'message' => 'Status updated successfully',
                'employee_id' => $employeeId,
                'new_status' => $status
            ]);
        } else {
            throw new Exception('Failed to update status');
        }

    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.',
        'received_method' => $_SERVER['REQUEST_METHOD']
    ]);
}
?>