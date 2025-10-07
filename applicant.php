<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit();
}

$host = "localhost";
$user = "root";
$password = "";
$db = "hrms";

$conn = new mysqli($host, $user, $password, $db);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

function saveFile($fieldName, $conn) {
    if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
        $uploadDir = "uploads/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $filename = time() . "_" . $conn->real_escape_string(basename($_FILES[$fieldName]["name"]));
        $filepath = $uploadDir . $filename;
        if (move_uploaded_file($_FILES[$fieldName]["tmp_name"], $filepath)) {
            return $filepath;
        }
    }
    return null;
}

function generateAppID($conn, $company) {
    // Generate company prefix based on company name
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
    
    
    $year = date('y');   
    $month = date('m');  
    $day = date('d');   
    
    $dateStr = $year . $month . $day;
    

    $pattern = $companyPrefix . '-' . $dateStr . '%';
    $query = "SELECT appID FROM applicant WHERE appID LIKE ? ORDER BY appID DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sequence = 1; 
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastAppID = $row['appID'];
        
        $parts = explode('-', $lastAppID);
        if (count($parts) >= 2) {
          
            $lastPart = $parts[1];
            $sequence = intval(substr($lastPart, 6)) + 1; 
        }
    }
    $stmt->close();
    
    
    return $companyPrefix . '-' . $dateStr . $sequence;
}

$formFieldMap = [
    "FirstName" => "FirstName", 
    "MiddleName" => "MiddleName", 
    "LastName" => "LastName", 
    "Gender" => "Gender", 
    "BirthDate" => "BirthDate", 
    "EmailAddress" => "EmailAddress", 
    "ContactNumber" => "ContactNumber", 
    "HomeAddress" => "HomeAddress", 
    "PositionApplied" => "PositionApplied", 
    "Company" => "Company"
];

$fileFieldMap = [
    "ProfilePicture" => "ProfilePicture", 
    "Resume" => "Resume", 
    "Passport" => "Passport", 
    "Diploma" => "Diploma", 
    "Tor" => "Tor", 
    "Medical" => "Medical", 
    "TinID" => "TinID", 
    "NBIClearance" => "NBIClearance", 
    "PoliceClearance" => "PoliceClearance", 
    "PagIbig" => "PagIbig", 
    "PhilHealth" => "PhilHealth"
];

$data = [];
foreach ($formFieldMap as $postField => $dbColumn) {
    $data[$dbColumn] = isset($_POST[$postField]) ? $conn->real_escape_string($_POST[$postField]) : null;
}

foreach ($fileFieldMap as $postField => $dbColumn) {
    $data[$dbColumn] = saveFile($postField, $conn);
}

// Generate unique appID based on company and date
$appID = generateAppID($conn, $data["Company"]);

// Insert into applicant table with appID
$sql = "INSERT INTO applicant (
    appID, ProfilePicture, FirstName, MiddleName, LastName, Gender, BirthDate,
    EmailAddress, ContactNumber, HomeAddress, PositionApplied, Company
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
)";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to prepare SQL statement: " . $conn->error]);
    exit();
}

$stmt->bind_param(
    "ssssssssssss",
    $appID,
    $data["ProfilePicture"], 
    $data["FirstName"], 
    $data["MiddleName"], 
    $data["LastName"],
    $data["Gender"], 
    $data["BirthDate"], 
    $data["EmailAddress"], 
    $data["ContactNumber"], 
    $data["HomeAddress"], 
    $data["PositionApplied"],
    $data["Company"]
);

if ($stmt->execute()) {
    // Get the auto-increment ID from applicant table
    $applicantTableID = $conn->insert_id;

    // Insert into requirements table with the SAME appID (foreign key)
    $sqlReq = "INSERT INTO requirements(
        appID, Resume, Passport, Diploma, Tor, Medical, TinID, 
        NBIClearance, PoliceClearance, PagIbig, PhilHealth
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )";
    
    $stmtReq = $conn->prepare($sqlReq);
    if ($stmtReq === false) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to prepare requirements SQL: " . $conn->error]);
        exit();
    }
    
    $stmtReq->bind_param(
        "sssssssssss",
        $appID,  // Same appID as in applicant table
        $data["Resume"], 
        $data["Passport"], 
        $data["Diploma"], 
        $data["Tor"],
        $data["Medical"], 
        $data["TinID"], 
        $data["NBIClearance"], 
        $data["PoliceClearance"],
        $data["PagIbig"], 
        $data["PhilHealth"]
    );
    
    if ($stmtReq->execute()) {
        http_response_code(200);
        echo json_encode([
            "success" => true, 
            "message" => "Application submitted successfully.",
            "appID" => $appID,
            "applicantID" => $applicantTableID
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to submit requirements: " . $stmtReq->error]);
    }
    $stmtReq->close();
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to submit application: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>