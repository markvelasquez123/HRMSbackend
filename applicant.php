<?php
header("Access-Control-Allow-Origin: http://localhost:3001");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
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


$formFieldMap = [
    "FirstName" => "FirstName", "MiddleName" => "MiddleName", "LastName" => "LastName", 
    "Gender" => "Gender", "BirthDate" => "BirthDate", "EmailAddress" => "EmailAddress", 
    "ContactNumber" => "ContactNumber", "HomeAddress" => "HomeAddress", 
    "PositionApplied" => "PositionApplied"
];

$fileFieldMap = [
    "ProfilePicture" => "ProfilePicture", "Resume" => "Resume", "Passport" => "Passport", 
    "Diploma" => "Diploma", "Tor" => "Tor", "Medical" => "Medical", 
    "TinID" => "TinID", "NBIClearance" => "NBIClearance", 
    "PoliceClearance" => "PoliceClearance", "Pag-Ibig" => "PagIbig", 
    "PhilHealth" => "PhilHealth"
];

$data = [];
foreach ($formFieldMap as $postField => $dbColumn) {
    $data[$dbColumn] = isset($_POST[$postField]) ? $conn->real_escape_string($_POST[$postField]) : null;
}

foreach ($fileFieldMap as $postField => $dbColumn) {
    $data[$dbColumn] = saveFile($postField, $conn);
}


if (isset($_POST['birthYear']) && isset($_POST['birthMonth']) && isset($_POST['birthDay'])) {
    $year = $_POST['birthYear'];
    $month = $_POST['birthMonth'];
    $day = $_POST['birthDay'];
    
    $data['BirthDate'] = "{$year}-{$month}-{$day}";
}


$sql = "INSERT INTO applicant (
    ProfilePicture, FirstName, MiddleName, LastName, Gender, BirthDate,
    EmailAddress, ContactNumber, HomeAddress, PositionApplied,
    
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
)"
$sql = "INSERT INTO requirements(
    Resume, Passport, Diploma, Tor, Medical, TinID, 
    NBIClearance, PoliceClearance, PagIbig, PhilHealth
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
)";



$stmt = $conn->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to prepare SQL statement: " . $conn->error]);
    exit();
}

$stmt->bind_param(
    "ssssssssssssssssssss", 
    $data["ProfilePicture"], $data["FirstName"], $data["MiddleName"], $data["LastName"],
    $data["Gender"], $data["BirthDate"], 
    $data["EmailAddress"], $data["ContactNumber"], $data["HomeAddress"], $data["PositionApplied"],
    $data["Resume"], $data["Passport"], $data["Diploma"], $data["Tor"],
    $data["Medical"], $data["TinID"], $data["NBIClearance"], $data["PoliceClearance"],
    $data["PagIbig"], $data["PhilHealth"]
);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Application submitted successfully."]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to submit application: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
