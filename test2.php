<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header("Access-Control-Allow-Credentials: true");

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
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $sql = "SELECT * FROM employeee ORDER BY id DESC";
        $stmt = $pdo->query($sql);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($employees);
        exit();
    }
    
    function GenerateIDNumber($pdo, $company, $dateHired) {
        $companyPrefix = '';
        switch ($company) {
            case 'Asia Navis':
                $companyPrefix = 'ASN';
                break;
            case 'Rigel':
                $companyPrefix = 'RGL';
                break;
            case 'PeakHR':
                $companyPrefix = 'PHR';
                break;
        }

        $hireDate = new DateTime($dateHired);
        $year = $hireDate->format('y');   
        $month = $hireDate->format('m');
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM employeee");
        $stmt->execute();
        $employeeCount = $stmt->fetchColumn();  
        
        $sequence = str_pad($employeeCount + 1, 3, '0', STR_PAD_LEFT);
       
        return $companyPrefix . '-' . $year . $month . $sequence;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        error_log("Received POST data: " . print_r($_POST, true));
        
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
        
        $idNumber = GenerateIDNumber($pdo, $company, $dateHired);
        
        error_log("Generated IDNumber: " . $idNumber);
        error_log("FirstName: " . $firstName);
        error_log("LastName: " . $lastName);
        
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM employeee WHERE IDNumber = :idNumber");
        $checkStmt->execute([':idNumber' => $idNumber]);
        if ($checkStmt->fetchColumn() > 0) {
            throw new Exception('Employee ID already exists');
        }
        
        $sql = "INSERT INTO employeee (
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
                'employeeId' => $pdo->lastInsertId(),
                'idNumber' => $idNumber
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