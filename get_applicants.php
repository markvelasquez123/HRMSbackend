<?php
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, OPTIONS, GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
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


$sql = "SELECT 
    appID, ProfilePicture, FirstName, MiddleName, LastName, Gender, PositionApplied, EmailAddress, ContactNumber, HomeAddress, BirthDate
FROM applicant";

$result = $conn->query($sql);

if ($result === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed: ' . $conn->error]);
    exit();
}

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


$sql = "SELECT 
    Resume, Passport, Diploma, Tor, Medical, TinID, NBIClearance, PoliceClearance, PagIbig, PhilHealth
FROM requirements";
$result = $conn->query($sql);
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
$conn->close();

header('Content-Type: application/json');
echo json_encode([
    'summary' => $summary,
    'details' => $details
]);
exit();
?>