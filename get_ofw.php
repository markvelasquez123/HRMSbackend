<?php
// get_ofw.php - OFW API with Admin access
require_once 'var.php';

$http_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

if (in_array($http_origin, $IP_THIS)) {
    header("Access-Control-Allow-Origin: $http_origin");
} else {
    error_log("Unauthorized CORS request from origin: " . $http_origin);
}
header('Content-Type: application/json');

header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host = 'localhost';
$dbname = 'hrms';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Get org parameter
        $orgPrefix = isset($_GET['org']) ? $_GET['org'] : null;
        
        // If Admin, get all OFW records; otherwise filter by appID prefix
        if ($orgPrefix === 'Admin') {
            $stmt = $pdo->prepare("
                SELECT 
                    ID, 
                    appID, 
                    FirstName, 
                    LastName, 
                    MiddleName, 
                    EmailAddress, 
                    ContactNumber, 
                    PositionApplied,
                    Department, 
                    employeeType, 
                    dateHired, 
                    Birthdate, 
                    Gender, 
                    HomeAddress, 
                    status
                FROM ofw 
                ORDER BY ID ASC
            ");
            $stmt->execute();
        } else if ($orgPrefix && in_array($orgPrefix, ['RGL', 'ASN', 'PHR'])) {
            $stmt = $pdo->prepare("
                SELECT 
                    ID, 
                    appID, 
                    FirstName, 
                    LastName, 
                    MiddleName, 
                    EmailAddress, 
                    ContactNumber, 
                    PositionApplied,
                    Department, 
                    employeeType, 
                    dateHired, 
                    Birthdate, 
                    Gender, 
                    HomeAddress, 
                    status
                FROM ofw 
                WHERE appID LIKE ?
                ORDER BY ID ASC
            ");
            $searchPattern = $orgPrefix . '-%';
            $stmt->execute([$searchPattern]);
        } else {
            // Default: get all if no valid org specified
            $stmt = $pdo->prepare("
                SELECT 
                    ID, 
                    appID, 
                    FirstName, 
                    LastName, 
                    MiddleName, 
                    EmailAddress, 
                    ContactNumber, 
                    PositionApplied,
                    Department, 
                    employeeType, 
                    dateHired, 
                    Birthdate, 
                    Gender, 
                    HomeAddress, 
                    status
                FROM ofw 
                ORDER BY ID ASC
            ");
            $stmt->execute();
        }
        
        $ofwRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $mappedRecords = array_map(function($record) {
            return [
                'id' => $record['ID'],
                'employeeId' => $record['appID'],
                'firstName' => $record['FirstName'],
                'lastName' => $record['LastName'],
                'middleName' => $record['MiddleName'],
                'email' => $record['EmailAddress'],
                'phone' => $record['ContactNumber'],
                'position' => $record['PositionApplied'],
                'department' => $record['Department'],
                'employeeType' => $record['employeeType'],
                'dateHired' => $record['dateHired'],
                'birthDate' => $record['Birthdate'],
                'gender' => $record['Gender'],
                'street1' => $record['HomeAddress'], 
                'status' => $record['status'],
                'profilePicture' => null 
            ];
        }, $ofwRecords);
        
        echo json_encode([
            'success' => true,
            'data' => $mappedRecords,
            'count' => count($mappedRecords)
        ]);
        
    } catch (Exception $e) {
        error_log("Get OFW Error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use GET.'
    ]);
}
?>