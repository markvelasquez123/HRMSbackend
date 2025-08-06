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
    accid, FirstName, LastName, Position, Department, employeeType, gender, hireDate, birthDate, email, phone, 
    street1, street2, city, state, zip, profilePic
FROM employees";

$result = $conn->query($sql);

$summary = [];
$details = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        
        $summary[] = [
            'accid' => $row['accid'],
            'profilePic' => isset($row['profilePic']) ? $row['profilePic'] : null,
            'FirstName' => $row['FirstName'],
            'LastName' => $row['LastName'],
            'Position' => $row['Position'],
            'Department' => $row['Department'],
            'email' => $row['email'],
            'phone' => $row['phone']
        ];

        
        $details[] = [
            'accid' => $row['accid'],
            'profilePic' => isset($row['profilePic']) ? $row['profilePic'] : null,
            'FirstName' => $row['FirstName'],
            'LastName' => $row['LastName'],
            'Position' => $row['Position'],
            'Department' => $row['Department'],
            'employeeType' => $row['employeeType'],
            'gender' => $row['gender'],
            'hireDate' => $row['hireDate'],
            'birthDate' => $row['birthDate'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'street1' => $row['street1'],
            'street2' => $row['street2'],
            'state' => $row['state'],
            'city' => $row['city'],
            'state' => $row['state'],
            'zip' => $row['zip']
        ];
    }
}

$conn->close();

