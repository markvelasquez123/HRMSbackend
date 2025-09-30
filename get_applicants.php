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

// Select all relevant columns for both summary and details
$sql = "SELECT 
    *
FROM applicant";

$result = $conn->query($sql);

$summary = [];
$details = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // For applicant.js summary table
        $summary[] = [
            'ProfilePicture' => $row['ProfilePicture'],
            'FirstName' => $row['FirstName'],
            'LastName' => $row['LastName'],
            'EmailAddress' => $row['EmailAddress'],
            'ContactNumber' => $row['ContactNumber'],
            'PositionApplied' => $row['PositionApplied']
        ];

        // For applicantSidebar.js detailed view
        $details[] = [
            'ProfilePicture' => $row['ProfilePicture'],
            'FirstName' => $row['FirstName'],
            'LastName' => $row['LastName'],
            'Email' => $row['EmailAddress'],
            'ContactNumber' => $row['ContactNumber'],
            'Gender' => $row['Gender'],
            'PositionApplied' => $row['PositionApplied'],
            'BirthDate' => $row['BirthDate'],
            'HomeAddress' => $row['HomeAddress'],
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
