<?php
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hrms";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]);
    exit();
}

// Handle GET request - Load employees
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'get') {
        $sql = "SELECT * FROM employees ORDER BY FirstName, LastName";
        $result = $conn->query($sql);
        
        if ($result) {
            $employees = [];
            while ($row = $result->fetch_assoc()) {
                $employees[] = $row;
            }
            echo json_encode($employees);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error fetching employees: ' . $conn->error]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action parameter']);
    }
    
    $conn->close();
    exit();
}

// Handle POST request - Add employee
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Create uploads directory if it doesn't exist
    $upload_dir = "uploads/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Handle file uploads
    $profile_picture = "";
    $resume_file = "";
    
    // Handle profile picture upload
    if (isset($_FILES['ProfilePicture']) && $_FILES['ProfilePicture']['error'] == 0) {
        $allowed_image_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (in_array($_FILES['ProfilePicture']['type'], $allowed_image_types)) {
            $profile_pic_name = time() . "_" . basename($_FILES['ProfilePicture']['name']);
            $profile_pic_path = $upload_dir . $profile_pic_name;
            if (move_uploaded_file($_FILES['ProfilePicture']['tmp_name'], $profile_pic_path)) {
                $profile_picture = $profile_pic_name;
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload profile picture']);
                exit();
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid profile picture format. Only JPG, PNG, and GIF allowed.']);
            exit();
        }
    }
    
    // Handle resume file upload
    if (isset($_FILES['ResumeFile']) && $_FILES['ResumeFile']['error'] == 0) {
        $allowed_doc_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (in_array($_FILES['ResumeFile']['type'], $allowed_doc_types)) {
            $resume_name = time() . "_" . basename($_FILES['ResumeFile']['name']);
            $resume_path = $upload_dir . $resume_name;
            if (move_uploaded_file($_FILES['ResumeFile']['tmp_name'], $resume_path)) {
                $resume_file = $resume_name;
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload resume file']);
                exit();
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid resume format. Only PDF and DOC/DOCX allowed.']);
            exit();
        }
    }
    
    // Get and validate form data
    $first_name = trim($_POST['firstName'] ?? '');
    $last_name = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $id_number = trim($_POST['idNumber'] ?? '');
    $department = trim($_POST['Department'] ?? '');
    $employee_type = trim($_POST['employeeType'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $position = trim($_POST['Position'] ?? '');
    $hire_date = trim($_POST['hireDate'] ?? '');
    $birth_date = trim($_POST['birthDate'] ?? '');
    $salary = trim($_POST['salary'] ?? '');
    $street1 = trim($_POST['street1'] ?? '');
    $street2 = trim($_POST['street2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip = trim($_POST['zip'] ?? '');
    
    // Basic validation
    $required_fields = [
        'firstName' => $first_name,
        'lastName' => $last_name,
        'email' => $email,
        'phone' => $phone,
        'idNumber' => $id_number,
        'Department' => $department,
        'employeeType' => $employee_type,
        'gender' => $gender,
        'Position' => $position,
        'hireDate' => $hire_date,
        'birthDate' => $birth_date,
        'salary' => $salary,
        'street1' => $street1,
        'city' => $city,
        'state' => $state,
        'zip' => $zip
    ];
    
    foreach ($required_fields as $field_name => $field_value) {
        if (empty($field_value)) {
            echo json_encode(['success' => false, 'message' => "Field '$field_name' is required"]);
            exit();
        }
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit();
    }
    
    // Validate salary is numeric
    if (!is_numeric($salary) || $salary < 0) {
        echo json_encode(['success' => false, 'message' => 'Salary must be a valid positive number']);
        exit();
    }
    
    // Check if employee ID already exists
    $check_sql = "SELECT idNumber FROM employees WHERE idNumber = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $id_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Employee ID already exists']);
        $check_stmt->close();
        $conn->close();
        exit();
    }
    $check_stmt->close();
    
    // Insert employee data - matching your database column names exactly
    $sql = "INSERT INTO employees (
        idNumber, FirstName, LastName, Position, Department, employeeType, 
        gender, hireDate, birthDate, email, phone, street1, street2, 
        city, state, zip, ProfilePicture, ResumeFile, salary
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit();
    }
    
    // Convert salary to decimal for database
    $salary_decimal = floatval($salary);
    
    $stmt->bind_param(
        "ssssssssssssssssssd", 
        $id_number, $first_name, $last_name, $position, $department, 
        $employee_type, $gender, $hire_date, $birth_date, $email, 
        $phone, $street1, $street2, $city, $state, $zip, 
        $profile_picture, $resume_file, $salary_decimal
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Employee added successfully',
            'employee_id' => $conn->insert_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error inserting employee: ' . $stmt->error]);
    }
    
    $stmt->close();
}

$conn->close();
?>