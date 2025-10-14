<?php
require_once 'var.php';

$http_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';


if (in_array($http_origin, $IP_THIS)) {
    header("Access-Control-Allow-Origin: $http_origin");
    } else {
    
    error_log("Unauthorized CORS request from origin: " . $http_origin);
}

header('Content-Type: application/json');

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hrms";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
error_log("=== DELETE APPLICANT REQUEST ===");
error_log("Raw input: " . file_get_contents("php://input"));
error_log("Decoded data: " . print_r($data, true));

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'No data provided.']);
    exit;
}

// Get email from either field
$email = $data['email'] ?? $data['EmailAddress'] ?? null;

if (!$email) {
    error_log("ERROR: No email provided in request");
    echo json_encode(['success' => false, 'message' => 'No applicant email provided.']);
    exit;
}

error_log("Attempting to delete applicant with email: " . $email);

try {
    // First, let's check what tables exist
    $tables = $pdo->query("SHOW TABLES LIKE '%applicant%'")->fetchAll(PDO::FETCH_COLUMN);
    error_log("Found applicant tables: " . implode(', ', $tables));
    
    $rowsDeleted = 0;
    $deletedFrom = '';
    
    // Try each possible table and column combination
    $attempts = [
        ['table' => 'applicants', 'column' => 'EmailAddress'],
        ['table' => 'applicants', 'column' => 'email'],
        ['table' => 'applicant', 'column' => 'EmailAddress'],
        ['table' => 'applicant', 'column' => 'email'],
    ];
    
    foreach ($attempts as $attempt) {
        $tableName = $attempt['table'];
        $columnName = $attempt['column'];
        
        try {
            // Check if table exists
            $tableCheck = $pdo->query("SHOW TABLES LIKE '$tableName'")->fetchColumn();
            if (!$tableCheck) {
                error_log("Table '$tableName' does not exist, skipping");
                continue;
            }
            
            // Check if column exists
            $columnCheck = $pdo->query("SHOW COLUMNS FROM `$tableName` LIKE '$columnName'")->fetchColumn();
            if (!$columnCheck) {
                error_log("Column '$columnName' does not exist in table '$tableName', skipping");
                continue;
            }
            
            // Both table and column exist, try to find the record first
            $checkStmt = $pdo->prepare("SELECT * FROM `$tableName` WHERE `$columnName` = :email LIMIT 1");
            $checkStmt->execute(['email' => $email]);
            $record = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($record) {
                error_log("Found record in $tableName.$columnName: " . print_r($record, true));
                
                // Now delete it
                $deleteStmt = $pdo->prepare("DELETE FROM `$tableName` WHERE `$columnName` = :email");
                $deleteStmt->execute(['email' => $email]);
                $rowsDeleted = $deleteStmt->rowCount();
                $deletedFrom = "$tableName.$columnName";
                
                error_log("DELETE executed on $tableName.$columnName, rows affected: $rowsDeleted");
                
                if ($rowsDeleted > 0) {
                    break; // Successfully deleted, exit loop
                }
            } else {
                error_log("No record found in $tableName.$columnName with email: $email");
            }
            
        } catch (PDOException $e) {
            error_log("Error trying $tableName.$columnName: " . $e->getMessage());
            continue;
        }
    }

    if ($rowsDeleted > 0) {
        error_log("✅ SUCCESS: Deleted $rowsDeleted row(s) from $deletedFrom");
        echo json_encode([
            'success' => true, 
            'message' => 'Applicant deleted successfully.',
            'email' => $email,
            'rows_deleted' => $rowsDeleted,
            'deleted_from' => $deletedFrom
        ]);
    } else {
        error_log("⚠️ WARNING: No rows deleted. Applicant may not exist or already deleted.");
        error_log("Searched email: $email");
        error_log("Tables searched: " . implode(', ', array_column($attempts, 'table')));
        
        // Return success anyway since goal is achieved (applicant not in table)
        echo json_encode([
            'success' => true, 
            'message' => 'Applicant not found or already deleted.',
            'email' => $email,
            'rows_deleted' => 0,
            'tables_searched' => array_column($attempts, 'table')
        ]);
    }
} catch (Exception $e) {
    error_log("❌ ERROR: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage(),
        'email' => $email
    ]);
}
?>