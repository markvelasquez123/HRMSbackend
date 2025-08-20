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
    avatar, firstName, lastName, email, phone, position, gender,
    birthMonth, birthDay, birthYear, 
    street1, street2, city, state, zip, 
    resumeUrl, passport, diploma, tor, medical, tinId, 
    nbiClearance, policeClearance, pagibigNumber, philhealthNumber 
FROM applicant";

$result = $conn->query($sql);

$summary = [];
$details = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // For applicant.js summary table
        $summary[] = [
            'avatar' => $row['avatar'],
            'firstName' => $row['firstName'],
            'lastName' => $row['lastName'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'position' => $row['position']
        ];

        // For applicantSidebar.js detailed view
        $details[] = [
            'avatar' => $row['avatar'],
            'firstName' => $row['firstName'],
            'lastName' => $row['lastName'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'gender' => $row['gender'],  
            'position' => $row['position'],
            'birthMonth' => $row['birthMonth'],
            'birthDay' => $row['birthDay'],
            'birthYear' => $row['birthYear'],
            'street1' => $row['street1'],
            'street2' => $row['street2'],
            'city' => $row['city'],
            'state' => $row['state'],
            'zip' => $row['zip'],
            'resumeUrl' => $row['resumeUrl'],
            'passport' => $row['passport'],
            'diploma' => $row['diploma'],
            'tor' => $row['tor'],
            'medical' => $row['medical'],
            'tinId' => $row['tinId'],
            'nbiClearance' => $row['nbiClearance'],
            'policeClearance' => $row['policeClearance'],
            'pagibigNumber' => $row['pagibigNumber'],
            'philhealthNumber' => $row['philhealthNumber']
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
