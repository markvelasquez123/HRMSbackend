<?php
// get_applicants.php - Applicant API with Admin access
session_start();

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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$orgPrefix = isset($_GET['org']) ? $_GET['org'] : null;

// Validate the prefix
$validPrefixes = ['RGL', 'ASN', 'PHR', 'Admin'];
if (!$orgPrefix || !in_array($orgPrefix, $validPrefixes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid organization prefix']);
    exit();
}

$host = 'localhost'; 
$dbname = 'hrms';
$username = 'root';       
$password = '';          

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// If Admin, get all applicants; otherwise filter by prefix
if ($orgPrefix === 'Admin') {
    $sql = "SELECT 
        appID, ProfilePicture, FirstName, MiddleName, LastName, Gender, PositionApplied, 
        EmailAddress, ContactNumber, HomeAddress, BirthDate
    FROM applicant";
    $stmt = $conn->prepare($sql);
} else {
    $sql = "SELECT 
        appID, ProfilePicture, FirstName, MiddleName, LastName, Gender, PositionApplied, 
        EmailAddress, ContactNumber, HomeAddress, BirthDate
    FROM applicant
    WHERE appID LIKE ?";
    $searchPattern = $orgPrefix . '-%';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $searchPattern);
}

$stmt->execute();
$result = $stmt->get_result();

$summary = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $summary[] = [
            'appID' => $row['appID'],
            'ProfilePicture' => $row['ProfilePicture'],
            'FirstName' => $row['FirstName'],
            'MiddleName' => $row['MiddleName'],
            'EmailAddress' => $row['EmailAddress'],
            'LastName' => $row['LastName'],
            'Gender' => $row['Gender'],
            'ContactNumber' => $row['ContactNumber'],
            'PositionApplied' => $row['PositionApplied'],
            'HomeAddress' => $row['HomeAddress'],
            'BirthDate' => $row['BirthDate']
        ];
    }
}

// Also filter requirements by appID prefix or get all for Admin
if ($orgPrefix === 'Admin') {
    $sql = "SELECT r.* FROM requirements r";
    $stmt = $conn->prepare($sql);
} else {
    $sql = "SELECT r.* FROM requirements r WHERE r.appID LIKE ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $searchPattern);
}

$stmt->execute();
$result = $stmt->get_result();

$details = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $details[] = [
            'Resume' => $row['Resume'],
            'Passport' => $row['Passport'],
            'Diploma' => $row['Diploma'],
            'Tor' => $row['Tor'],
            'Medical' => $row['Medical'],
            'TinID' => $row['TinID'],
            'NBIClearance' => $row['NBIClearance'],
            'PoliceClearance' => $row['PoliceClearance'],
            'PagIbig' => $row['PagIbig'],
            'PhilHealth' => $row['PhilHealth']
        ];
    }
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode([
    'summary' => $summary,
    'details' => $details
]);
exit();
?>