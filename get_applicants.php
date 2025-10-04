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
    id, ProfilePicture, FirstName, MiddleName, LastName, Gender, PositionApplied, EmailAddress, ContactNumber, HomeAddress, BirthDate
FROM applicant
ORDER BY id ASC";

$result = $conn->query($sql);

$summary = [];
$emailMap = [];
$allRows = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $allRows[] = $row; 
        $email = $row['EmailAddress'];
        
      
        if (!isset($emailMap[$email]) || $row['id'] > $emailMap[$email]['id']) {
            $emailMap[$email] = [
                'id' => $row['id'],
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
    
    
    foreach ($emailMap as $applicant) {
        unset($applicant['id']); 
        $summary[] = $applicant;
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
    'details' => $details,
    'debug_total_rows' => count($allRows),
    'debug_unique_emails' => count($emailMap)
]);
exit();
?>