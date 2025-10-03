<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration
$host = 'localhost';
$dbname = 'hrms'; 
$username = 'root'; 
$password = ''; 

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Handle GET request - Fetch all employees
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $sql = "SELECT * FROM employeee ORDER BY id DESC";
        $stmt = $pdo->query($sql);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($employees);
        exit();
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Debug: Log received POST data
        error_log("Received POST data: " . print_r($_POST, true));
        
        // Get form data
        $idNumber = $_POST['IDNumber'] ?? '';
        $firstName = $_POST['FirstName'] ?? '';
        $lastName = $_POST['LastName'] ?? '';
        $middleName = $_POST['MiddleName'] ?? '';
        $birthdate = $_POST['Birthdate'] ?? '';
        $company = $_POST['Company'] ?? '';
        $gender = $_POST['Gender'] ?? '';
        $contactNumber = $_POST['ContactNumber'] ?? '';
        $emailAddress = $_POST['EmailAddress'] ?? '';
        $homeAddress = $_POST['HomeAddress'] ?? '';
        $department = $_POST['Department'] ?? '';
        $positionApplied = $_POST['PositionApplied'] ?? '';
        $employeeType = $_POST['EmployeeType'] ?? '';
        $dateHired = $_POST['DateHired'] ?? '';
        $passport = $_POST['Passport'] ?? '';
        
        // Debug: Log extracted values
        error_log("FirstName: " . $firstName);
        error_log("LastName: " . $lastName);
        
        // Validate required fields
        if (empty($idNumber)) {
            throw new Exception('Field IDNumber is required');
        }
        if (empty($firstName)) {
            throw new Exception('Field FirstName is required');
        }
        if (empty($lastName)) {
            throw new Exception('Field LastName is required');
        }
        if (empty($birthdate)) {
            throw new Exception('Field Birthdate is required');
        }
        if (empty($company)) {
            throw new Exception('Field Company is required');
        }
        if (empty($gender)) {
            throw new Exception('Field Gender is required');
        }
        if (empty($contactNumber)) {
            throw new Exception('Field ContactNumber is required');
        }
        if (empty($emailAddress)) {
            throw new Exception('Field EmailAddress is required');
        }
        if (empty($homeAddress)) {
            throw new Exception('Field HomeAddress is required');
        }
        if (empty($department)) {
            throw new Exception('Field Department is required');
        }
        if (empty($positionApplied)) {
            throw new Exception('Field PositionApplied is required');
        }
        if (empty($employeeType)) {
            throw new Exception('Field EmployeeType is required');
        }
        if (empty($dateHired)) {
            throw new Exception('Field DateHired is required');
        }
        if (empty($passport)) {
            throw new Exception('Field Passport is required');
        }
        
        // Check if employee ID already exists
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE IDNumber = :idNumber");
        $checkStmt->execute([':idNumber' => $idNumber]);
        if ($checkStmt->fetchColumn() > 0) {
            throw new Exception('Employee ID already exists');
        }
        
        // Insert employee data
        $sql = "INSERT INTO employees (
            IDNumber, FirstName, LastName, MiddleName, Birthdate, Company, Gender,
            ContactNumber, EmailAddress, HomeAddress, Department, PositionApplied,
            EmployeeType, DateHired, Passport
        ) VALUES (
            :idNumber, :firstName, :lastName, :middleName, :birthdate, :company, :gender,
            :contactNumber, :emailAddress, :homeAddress, :department, :positionApplied,
            :employeeType, :dateHired, :passport
        )";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':idNumber' => $idNumber,
            ':firstName' => $firstName,
            ':lastName' => $lastName,
            ':middleName' => $middleName,
            ':birthdate' => $birthdate,
            ':company' => $company,
            ':gender' => $gender,
            ':contactNumber' => $contactNumber,
            ':emailAddress' => $emailAddress,
            ':homeAddress' => $homeAddress,
            ':department' => $department,
            ':positionApplied' => $positionApplied,
            ':employeeType' => $employeeType,
            ':dateHired' => $dateHired,
            ':passport' => $passport
        ]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Employee added successfully',
                'employeeId' => $pdo->lastInsertId()
            ]);
        } else {
            throw new Exception('Failed to add employee');
        }
        
    } else {
        throw new Exception('Invalid request method');
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>