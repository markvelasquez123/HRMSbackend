<?php
session_start();

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, OPTIONS, GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get organization prefix from query parameter
$orgPrefix = isset($_GET['org']) ? $_GET['org'] : null;

// Validate the prefix
$validPrefixes = ['RGL', 'ASN', 'PHR'];
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

// CRITICAL: Filter by appID prefix using LIKE
$sql = "SELECT 
    appID, ProfilePicture, FirstName, MiddleName, LastName, Gender, PositionApplied, 
    EmailAddress, ContactNumber, HomeAddress, BirthDate
FROM applicant
WHERE appID LIKE ?";

$searchPattern = $orgPrefix . '-%'; // "RGL-%" or "ASN-%" or "PHR-%"
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $searchPattern);
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

// Also filter requirements by appID prefix
$sql = "SELECT r.*
FROM requirements r
WHERE r.appID LIKE ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $searchPattern);
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