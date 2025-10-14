<?php
require_once 'var.php';

$http_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';


if (in_array($http_origin, $IP_THIS)) {
    header("Access-Control-Allow-Origin: $http_origin");
    } else {
    
    error_log("Unauthorized CORS request from origin: " . $http_origin);
}
header("Access-Control-Allow-Methods: POST, OPTIONS, GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$host = 'localhost';
$dbname = 'hrms';
$username = 'root'; 
$password = '';     

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $FirstName = $input['FirstName'] ?? '';
    $LastName = $input['LastName'] ?? '';
    $EmailAddress = $input['EmailAddress'] ?? '';
    $PositionApplied = $input['PositionApplied'] ?? '';
    $Department = $input['Department'] ?? $input['department'] ?? '';
    $EmployeeType = $input['EmployeeType'] ?? $input['employeeType'] ?? '';
   
    if (empty($FirstName)) throw new Exception("Missing required field: FirstName");
    if (empty($LastName)) throw new Exception("Missing required field: LastName");
    if (empty($EmailAddress)) throw new Exception("Missing required field: EmailAddress");
    if (empty($PositionApplied)) throw new Exception("Missing required field: PositionApplied");
    if (empty($Department)) throw new Exception("Missing required field: Department");
    if (empty($EmployeeType)) throw new Exception("Missing required field: EmployeeType");
    
    $pdo->beginTransaction();
    
    $appID = $input['AppID'] ?? $input['appID'] ?? '';
    
    $companyPrefix = '';
    if (preg_match('/^([A-Z]+)/', $appID, $matches)) {
        $companyPrefix = $matches[1];
    }
    
    if (!empty($companyPrefix)) {
        $year = date('y');  
        $month = date('m'); 
        
        
        $countSql = "SELECT COUNT(*) as count FROM employeee WHERE IDNumber LIKE :prefix";
        $countStmt = $pdo->prepare($countSql);
        $searchPrefix = $companyPrefix . '%';  
        $countStmt->bindParam(':prefix', $searchPrefix);
        $countStmt->execute();
        $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
        $employeeCount = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        
        
        $IDNumber = "{$companyPrefix}-{$year}{$month}{$employeeCount}";
        
       
        $companyNames = [
            'ASN' => 'Asia Navis',
            'RGL' => 'Rigel',
            'PHR' => 'PeakHR',
        ];
        $company = $companyNames[$companyPrefix] ?? $companyPrefix;
    } else {
        
        $IDNumber = 'EMP' . time();
        $company = $input['Company'] ?? $input['company'] ?? '';
    }
    
    $currentDate = date('Y-m-d H:i:s');
    $dateHired = $input['dateHired'] ?? date('Y-m-d');
    
    $Birthdate = null;
    if (isset($input['BirthDate']) && !empty($input['BirthDate'])) {
        $Birthdate = $input['BirthDate'];
    } elseif (isset($input['birthYear']) && isset($input['birthMonth']) && isset($input['birthDay'])) {
        $Birthdate = sprintf('%04d-%02d-%02d', 
            $input['birthYear'], 
            $input['birthMonth'], 
            $input['birthDay']
        );
    }
    
    $Gender = $input['Gender'] ?? $input['gender'] ?? null;
    $ContactNumber = $input['ContactNumber'] ?? $input['phone'] ?? null;
    $HomeAddress = $input['HomeAddress'] ?? $input['homeaddress'] ?? null;
    $ProfilePicture = $input['ProfilePicture'] ?? $input['avatar'] ?? null;
    $Passport = $input['Passport'] ?? '';
    
    $sql = "INSERT INTO employeee (
        IDNumber,
        FirstName,
        LastName,
        PositionApplied,
        Department,
        EmployeeType,
        Gender,
        DateHired,
        Birthdate,
        EmailAddress,
        ContactNumber,
        HomeAddress,
        Passport,
        Company
    ) VALUES (
        :IDNumber,
        :FirstName,
        :LastName,
        :PositionApplied,
        :Department,
        :EmployeeType,
        :Gender,
        :DateHired,
        :Birthdate,
        :EmailAddress,
        :ContactNumber,
        :HomeAddress,
        :Passport,
        :Company
    )";
    
    $stmt = $pdo->prepare($sql);
    
    $stmt->bindParam(':IDNumber', $IDNumber);
    $stmt->bindParam(':FirstName', $FirstName);
    $stmt->bindParam(':LastName', $LastName);
    $stmt->bindParam(':PositionApplied', $PositionApplied);
    $stmt->bindParam(':Department', $Department);
    $stmt->bindParam(':EmployeeType', $EmployeeType);
    $stmt->bindParam(':Gender', $Gender);
    $stmt->bindParam(':DateHired', $dateHired);
    $stmt->bindParam(':Birthdate', $Birthdate);
    $stmt->bindParam(':EmailAddress', $EmailAddress);
    $stmt->bindParam(':ContactNumber', $ContactNumber);
    $stmt->bindParam(':HomeAddress', $HomeAddress);
    $stmt->bindParam(':Passport', $Passport);
    $stmt->bindParam(':Company', $company);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to insert employee');
    }
    
    $insertedId = $pdo->lastInsertId();
    
    $deleteApplicantSql = "DELETE FROM applicant WHERE EmailAddress = :EmailAddress";
    $deleteStmt = $pdo->prepare($deleteApplicantSql);
    $deleteStmt->bindParam(':EmailAddress', $EmailAddress);
    
    if (!$deleteStmt->execute()) {
        throw new Exception('Failed to delete applicant');
    }
    
    $deletedRows = $deleteStmt->rowCount();
    
    $pdo->commit();
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Applicant successfully accepted and moved to employees',
        'data' => [
            'ID' => $insertedId,
            'IDNumber' => $IDNumber,
            'FirstName' => $FirstName,
            'LastName' => $LastName,
            'EmailAddress' => $EmailAddress,
            'PositionApplied' => $PositionApplied,
            'Department' => $Department,
            'EmployeeType' => $EmployeeType,
            'DateHired' => $dateHired,
            'Birthdate' => $Birthdate,
            'employee_id' => $insertedId,
            'applicant_removed' => $deletedRows > 0
        ]
    ]);
    
} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'message' => $e->getMessage()
    ]);
    error_log("Database error: " . $e->getMessage());
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
     
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred',
        'message' => $e->getMessage()
    ]);
    error_log("General error: " . $e->getMessage());
}
?>