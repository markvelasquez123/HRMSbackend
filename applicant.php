<?php
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
    
    // Count only applicants from the same company
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM applicant WHERE Company = ?");
    $countStmt->bind_param("s", $company);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $applicantCount = 1;
    
    if ($countResult && $row = $countResult->fetch_assoc()) {
        $applicantCount = $row['total'] + 1;
    }
    $countStmt->close();
    
    $applicantNum = str_pad($applicantCount, 3, '0', STR_PAD_LEFT);
    
    return $companyPrefix . '-' . $applicantNum;
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

$appID = generateAppID($conn, $data["Company"]);

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
    $applicantTableID = $conn->insert_id;

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
        $appID,
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