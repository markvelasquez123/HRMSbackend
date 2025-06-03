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

// Connect to DB
$conn = new mysqli($host, $user, $password, $db);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// Handle file uploads
function saveFile($fieldName) {
    if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
        $uploadDir = "uploads/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $filename = time() . "_" . basename($_FILES[$fieldName]["name"]);
        $filepath = $uploadDir . $filename;
        if (move_uploaded_file($_FILES[$fieldName]["tmp_name"], $filepath)) {
            return $filepath;
        }
    }
    return null;
}

// Fetch and sanitize form data
$formFields = [
    "firstName", "middleName", "lastName", "gender", "birthMonth", "birthDay", "birthYear",
    "email", "phone", "street1", "street2", "city", "state", "zip", "position"
];

$data = [];
foreach ($formFields as $field) {
    $data[$field] = isset($_POST[$field]) ? $conn->real_escape_string($_POST[$field]) : "";
}

// Handle uploaded files
$fileFields = [
    "avatar", "resumeUrl", "passport", "diploma", "tor", "medical",
    "tinId", "nbiClearance", "policeClearance", "pagibigNumber", "philhealthNumber"
];

foreach ($fileFields as $field) {
    $data[$field] = saveFile($field);
}

// Insert into DB
$sql = "INSERT INTO applicant (
    avatar, firstName, middleName, lastName, gender, birthMonth, birthDay, birthYear,
    email, phone, street1, street2, city, state, zip, position,
    resumeUrl, passport, diploma, tor, medical, tinId, nbiClearance,
    policeClearance, pagibigNumber, philhealthNumber
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
)";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "ssssssssssssssssssssssssss",
    $data["avatar"], $data["firstName"], $data["middleName"], $data["lastName"],
    $data["gender"], $data["birthMonth"], $data["birthDay"], $data["birthYear"],
    $data["email"], $data["phone"], $data["street1"], $data["street2"],
    $data["city"], $data["state"], $data["zip"], $data["position"],
    $data["resumeUrl"], $data["passport"], $data["diploma"], $data["tor"],
    $data["medical"], $data["tinId"], $data["nbiClearance"], $data["policeClearance"],
    $data["pagibigNumber"], $data["philhealthNumber"]
);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Application submitted successfully."]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to submit application"]);
}

$stmt->close();
$conn->close();
?>
