<?php
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, OPTIONS, GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Database configuration for HRMS
$host = 'localhost';
$dbname = 'hrms';
$username = 'root'; // Change to your database username
$password = '';     // Change to your database password

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    $requiredFields = ['firstName', 'lastName', 'email', 'position', 'department', 'employeeType'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Generate employee ID
    $employeeId = 'EMP' . time();
    
    // Prepare current date and hire date
    $currentDate = date('Y-m-d H:i:s');
    $hireDate = $input['dateHired'] ?? date('Y-m-d');
    
    // Create birth date from individual components
    $birthDate = null;
    if (isset($input['birthYear']) && isset($input['birthMonth']) && isset($input['birthDay'])) {
        $birthDate = sprintf('%04d-%02d-%02d', 
            $input['birthYear'], 
            $input['birthMonth'], 
            $input['birthDay']
        );
    }
    
    // Prepare all variables for binding
    $IDNumber = $employeeId;
    $FirstName = $input['firstName'];
    $LastName = $input['lastName'];
    $PositionApplied = $input['position'];
    $Department = $input['department'];
    $EmployeeType = $input['employeeType'];
    $gender = $input['gender'] ?? null;
    $EmailAddress = $input['email'];
    $ContactNumber = $input['phone'] ?? null;
    $HomeAddress = $input['homeaddress'] ?? null;
    $ProfilePicture = $input['avatar'] ?? null;
    $salary = null; 
    $resumeFile = $input['resumeUrl'] ?? null;
    
    // Prepare SQL statement for inserting into employees table
    $sql = "INSERT INTO employeee (
        IDNumber,
        FirstName,
        LastName,
        PositionApplied,
        Department,
        EmployeeType,
        gender,
        DateHired,
        Birthdate,
        EmailAddress,
        ContactNumber,
        HomeAddress,
        ProfilePicture,
        salary,
        ResumeFile,
        created_at
    ) VALUES (
        :IDNumber,
        :FirstName,
        :LastName,
        :PositionApplied,
        :Department,
        :EmployeeType,
        :gender,
        :DateHired,
        :Birthdate,
        :EmailAddress,
        :ContactNumber,
        :HomeAddress,
        :ProfilePicture,
        :salary,
        :ResumeFile,
        :created_at
    )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':IDNumber', $IDNumber);
    $stmt->bindParam(':FirstName', $FirstName);
    $stmt->bindParam(':LastName', $LastName);
    $stmt->bindParam(':PositionApplied', $PositionApplied);
    $stmt->bindParam(':Department', $Department);
    $stmt->bindParam(':EmployeeType', $EmployeeType);
    $stmt->bindParam(':gender', $gender);
    $stmt->bindParam(':DateHired', $hireDate);
    $stmt->bindParam(':Birthdate', $birthDate);
    $stmt->bindParam(':EmailAddress', $EmailAddress);
    $stmt->bindParam(':ContactNumber', $ContactNumber);
    $stmt->bindParam(':HomeAddress', $HomeAddress);
    $stmt->bindParam(':ProfilePicture', $ProfilePicture);
    $stmt->bindParam(':salary', $salary);
    $stmt->bindParam(':ResumeFile', $resumeFile);
    $stmt->bindParam(':created_at', $currentDate);
    
    $stmt = $pdo->prepare($sql);
    
    // Bind parameters
    $stmt->bindParam(':IDNumber', $IDNumber);
    $stmt->bindParam(':FirstName', $FirstName);
    $stmt->bindParam(':LastName', $LastName);
    $stmt->bindParam(':PositionApplied', $PositionApplied);
    $stmt->bindParam(':Department', $Department);
    $stmt->bindParam(':EmployeeType', $EmployeeType);
    $stmt->bindParam(':gender', $gender);
    $stmt->bindParam(':DateHired', $DateHired);
    $stmt->bindParam(':Birthdate', $Birthdate);
    $stmt->bindParam(':EmailAddress', $EmailAddress);
    $stmt->bindParam(':ContactNumber', $ContactNumber);
    $stmt->bindParam(':HomeAddress', $HomeAddress);
    $stmt->bindParam(':ProfilePicture', $ProfilePicture);
    $stmt->bindParam(':salary', $salary);
    $stmt->bindParam(':ResumeFile', $resumeFile);
    $stmt->bindParam(':ProfilePicture', $ProfilePicture);
    $stmt->bindParam(':salary', $salary);
    $stmt->bindParam(':ResumeFile', $resumeFile);
    $stmt->bindParam(':created_at', $currentDate);
    
    // Execute the statement
    if (!$stmt->execute()) {
        throw new Exception('Failed to insert employee');
    }
    
    // Get the inserted employee's database ID
    $insertedId = $pdo->lastInsertId();
    
    // Delete the applicant from the applicant table
    $deleteApplicantSql = "DELETE FROM applicant WHERE email = :email AND firstName = :firstName AND lastName = :lastName";
    $deleteStmt = $pdo->prepare($deleteApplicantSql);
    $deleteStmt->bindParam(':email', $email);
    $deleteStmt->bindParam(':firstName', $firstName);
    $deleteStmt->bindParam(':lastName', $lastName);
    
    if (!$deleteStmt->execute()) {
        throw new Exception('Failed to delete applicant');
    }
    
    $deletedRows = $deleteStmt->rowCount();
    
    // Optionally, update the applicant status in the applicants table (if it exists separately)
    try {
        $updateApplicantSql = "UPDATE applicants SET status = 'Accepted', updated_at = :updated_at WHERE email = :email";
        $updateStmt = $pdo->prepare($updateApplicantSql);
        $updateStmt->bindParam(':updated_at', $currentDate);
        $updateStmt->bindParam(':email', $email);
        $updateStmt->execute();
    } catch (Exception $e) {
        // If applicants table doesn't exist or update fails, log but don't fail the main operation
        error_log("Failed to update applicant status: " . $e->getMessage());
    }
    
    // Commit the transaction
    $pdo->commit();
    
    // Return success response
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Applicant successfully accepted and moved to employees',
        'data' => [
            'ID' => $insertedID,
            'IDNumber' => $IDNumber,
            'FirstName' => $FirstName,
            'LastName' => $LastName,
            'EmailAddress' => $EmailAddress,
            'PositionApplied' => $PositionApplied,
            'Department' => $Department,
            'EmployeeType' => $EmployeeType,
            'DateHired' => $DateHired,
            'Birthdate' => $Birthdate,
            'employee_id' => $insertedId,
            'applicant_removed' => $deletedRows > 0
        ]
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on database error
    if ($pdo->inTransaction()) {
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
    // Rollback transaction on general error
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