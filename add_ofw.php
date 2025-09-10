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
            
            exit(); 
        }

        
        $requiredFields = ['firstName', 'lastName', 'email', 'phone', 'position'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        $checkStmt = $pdo->prepare("SELECT id FROM ofw WHERE email = ?");
        $checkStmt->execute([$data['email']]);
        if ($checkStmt->fetch()) {
            throw new Exception('An OFW with this email already exists');
        }

        $birthDate = null;
        if (!empty($data['birthYear']) && !empty($data['birthMonth']) && !empty($data['birthDay'])) {
            $birthDate = sprintf('%04d-%02d-%02d', 
                intval($data['birthYear']), 
                intval($data['birthMonth']), 
                intval($data['birthDay'])
            );
        }

        $ofwId = null;
        $nextNumber = 1;
        
        while (true) {
            $ofwId = 'OFW' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT); 
            
            $idCheckStmt = $pdo->prepare("SELECT id FROM ofw WHERE employeeId = ?");
            $idCheckStmt->execute([$ofwId]);
            
            if (!$idCheckStmt->fetch()) {
                break;
            }
            
            $nextNumber++;
            
            if ($nextNumber > 999999) {
                throw new Exception('Maximum OFW ID limit reached. Please contact administrator.');
            }
        }
        
        error_log("Generated sequential OFW ID: $ofwId");

        $insertData = [
            $ofwId,
            $data['firstName'],
            $data['lastName'], 
            $data['email'],
            $data['phone'],
            $data['position'],
            $data['department'] ?? 'OFW',
            'OFW',
            $data['dateHired'] ?? date('Y-m-d'),
            $birthDate,
            $data['gender'] ?? null,
            $data['street1'] ?? null,
            $data['street2'] ?? null,
            $data['city'] ?? null,
            $data['state'] ?? null,
            $data['zip'] ?? null,
            $data['profilePicture'] ?? null,
            $data['resumeUrl'] ?? null,
            $data['passport'] ?? null,
            $data['diploma'] ?? null,
            $data['tor'] ?? null,
            $data['medical'] ?? null,
            $data['tinId'] ?? null,
            $data['nbiClearance'] ?? null,
            $data['policeClearance'] ?? null,
            $data['pagibigNumber'] ?? null,
            $data['philhealthNumber'] ?? null
        ];
        
        error_log("Insert data: " . print_r($insertData, true));

        $insertStmt = $pdo->prepare("
            INSERT INTO ofw (
                employeeId, firstName, lastName, email, phone, position, 
                department, employeeType, dateHired, birthDate, gender,
                street1, street2, city, state, zip, profilePicture, resumeUrl,
                passport, diploma, tor, medical, tinId, nbiClearance, 
                policeClearance, pagibigNumber, philhealthNumber,
                created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
            )
        ");

        $result = $insertStmt->execute($insertData);

        if ($result) {
            $newOfwId = $pdo->lastInsertId();
            
            try {
                $deleteStmt = $pdo->prepare("DELETE FROM applicants WHERE email = ?");
                $deleteStmt->execute([$data['email']]);
                $applicantRemoved = true;
                error_log("Successfully removed applicant with email: " . $data['email']);
            } catch (Exception $e) {
                error_log("Warning: Could not remove applicant: " . $e->getMessage());
                $applicantRemoved = false;
            }

            $documentCount = 0;
            $documentFields = ['resumeUrl', 'passport', 'diploma', 'tor', 'medical', 'tinId', 'nbiClearance', 'policeClearance', 'pagibigNumber', 'philhealthNumber'];
            foreach ($documentFields as $field) {
                if (!empty($data[$field])) {
                    $documentCount++;
                }
            }

            error_log("OFW successfully created with ID: $newOfwId, Employee ID: $ofwId");

            echo json_encode([
                'success' => true,
                'message' => 'OFW added successfully with all documents',
                'ofw_id' => $newOfwId,
                'employee_id' => $ofwId,
                'documents_saved' => $documentCount,
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