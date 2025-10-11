<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("Request URI: " . $_SERVER['REQUEST_URI']);


if (isset($_GET['test'])) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=hrms;charset=utf8mb4", 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'ofw'");
        $tableExists = $tableCheck->rowCount() > 0;
        
        if ($tableExists) {
            $structureCheck = $pdo->query("DESCRIBE ofw");
            $columns = $structureCheck->fetchAll(PDO::FETCH_COLUMN);
            
            $countCheck = $pdo->query("SELECT COUNT(*) FROM ofw");
            $recordCount = $countCheck->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'message' => 'PHP file and database are working',
                'database_connection' => 'OK',
                'ofw_table_exists' => true,
                'ofw_table_columns' => $columns,
                'existing_ofw_records' => $recordCount,
                'request_method' => $_SERVER['REQUEST_METHOD'],
                'has_status_column' => in_array('status', $columns)
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'OFW table does not exist',
                'database_connection' => 'OK',
                'ofw_table_exists' => false,
                'request_method' => $_SERVER['REQUEST_METHOD']
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage(),
            'request_method' => $_SERVER['REQUEST_METHOD']
        ]);
    }
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
        'error' => 'Database connection failed: ' . $e->getMessage(),
        'request_method' => $_SERVER['REQUEST_METHOD']
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

       
        if (isset($data['employeeId']) && isset($data['status']) && count($data) == 2) {
            $employeeId = $data['employeeId'];
            $status = $data['status'];
            
            
            $validStatuses = ['On Process', 'Deployed', 'Repatriated'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception('Invalid status. Must be: ' . implode(', ', $validStatuses));
            }
            
       
            $checkStmt = $pdo->prepare("SELECT ID FROM ofw WHERE ID = ?");
            $checkStmt->execute([$employeeId]);
            if (!$checkStmt->fetch()) {
                throw new Exception('Employee not found with ID: ' . $employeeId);
            }
            
       
            $updateStmt = $pdo->prepare("UPDATE ofw SET status = ? WHERE ID = ?");
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
            
            exit(); 
        }

        
        $requiredFields = ['firstName', 'lastName', 'email', 'phone', 'position'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        
        $checkStmt = $pdo->prepare("SELECT ID FROM ofw WHERE EmailAddress = ?");
        $checkStmt->execute([$data['email']]);
        if ($checkStmt->fetch()) {
            throw new Exception('An OFW with this email already exists');
        }


        $currentYear = date('y');   
        $currentMonth = date('m'); 
        $ofwPrefix = "OFW{$currentYear}{$currentMonth}";
        
       
        $countStmt = $pdo->prepare("
            SELECT appID FROM ofw 
            WHERE appID LIKE ? 
            ORDER BY appID DESC 
            LIMIT 1
        ");
        $countStmt->execute([$ofwPrefix . '%']);
        $lastOfw = $countStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lastOfw && $lastOfw['appID']) {
        
            $lastNumber = intval(substr($lastOfw['appID'], -3));
            $nextNumber = $lastNumber + 1;
        } else {
           
            $nextNumber = 1;
        }
        
    
        $generatedOfwId = $ofwPrefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        
        error_log("Generated OFW ID: $generatedOfwId for year-month: {$currentYear}{$currentMonth}");
        
        
        $insertData = [
            $generatedOfwId,                                
            $data['firstName'],                             
            $data['lastName'],                             
            $data['middleName'] ?? '',                      
            $data['email'],                                
            $data['phone'],                                 
            $data['position'],                            
            $data['department'] ?? 'OFW',                    
            $data['employeeType'] ?? 'OFW',                  
            $data['dateHired'] ?? date('Y-m-d'),           
            $data['birthDate'] ?? null,                     
            $data['gender'] ?? null,                         
            $data['homeAddress'] ?? null,                    
            $data['status'] ?? 'On Process'                  
        ];
        
        error_log("Insert data: " . print_r($insertData, true));

       
        $insertStmt = $pdo->prepare("
            INSERT INTO ofw (
                appID, FirstName, LastName, MiddleName, EmailAddress, ContactNumber, 
                PositionApplied, Department, employeeType, dateHired, Birthdate, 
                Gender, HomeAddress, status
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");

        $result = $insertStmt->execute($insertData);

        if ($result) {
            $newOfwId = $pdo->lastInsertId();
            
           
            try {
                $deleteStmt = $pdo->prepare("DELETE FROM applicants WHERE EmailAddress = ?");
                $deleteStmt->execute([$data['email']]);
                $applicantRemoved = true;
                error_log("Successfully removed applicant with email: " . $data['email']);
            } catch (Exception $e) {
                error_log("Warning: Could not remove applicant: " . $e->getMessage());
                $applicantRemoved = false;
            }

            error_log("OFW successfully created with ID: $newOfwId, appID: $generatedOfwId");

            echo json_encode([
                'success' => true,
                'message' => 'OFW added successfully',
                'ofw_id' => $newOfwId,
                'app_id' => $generatedOfwId,
                'applicant_removed' => $applicantRemoved
            ]);
        } else {
            throw new Exception('Failed to insert OFW record');
        }

    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'debug_info' => [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_method' => $_SERVER['REQUEST_METHOD']
            ]
        ]);
    }
} else {
    error_log("Method not allowed. Received: " . $_SERVER['REQUEST_METHOD'] . ", Expected: POST");
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.',
        'received_method' => $_SERVER['REQUEST_METHOD'],
        'allowed_methods' => ['POST', 'OPTIONS']
    ]);
}
?>