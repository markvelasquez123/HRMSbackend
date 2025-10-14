<?php
require_once 'var.php';

$http_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';


if (in_array($http_origin, $IP_THIS)) {
    header("Access-Control-Allow-Origin: $http_origin");
    } else {
    
    error_log("Unauthorized CORS request from origin: " . $http_origin);
}
header('Content-Type: application/json');

header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');


error_reporting(E_ALL);
ini_set('display_errors', 1);


error_log("Get Pool Data API called - Method: " . $_SERVER['REQUEST_METHOD']);


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}


$host = 'localhost';
$dbname = 'hrms';  
$username = 'root'; 
$password = '';    

try {
  
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    error_log("Database connection successful for get_pool_data");
    
    
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'pool'");
    if ($tableCheck->rowCount() == 0) {
        throw new Exception('Pool table does not exist in database');
    }
    
    error_log("Pool table exists, fetching data");
    
   
    $stmt = $pdo->prepare("
        SELECT 
            name,
            position,
            department,
            phone,
            status,
            resigned_date,
            created_at,
            updated_at
        FROM pool 
        ORDER BY created_at DESC
    ");
    
    $stmt->execute();
    $poolRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Fetched " . count($poolRecords) . " pool records");
    
  
    $formattedData = [];
    foreach ($poolRecords as $record) {
        $formattedData[] = [
            'name' => $record['name'] ?? 'N/A',
            'position' => $record['position'] ?? 'N/A',
            'department' => $record['department'] ?? 'N/A',
            'phone' => $record['phone'] ?? 'N/A',
            'status' => $record['status'] ?? 'N/A',
            'resigned_date' => $record['resigned_date'],
            'created_at' => $record['created_at'],
            'updated_at' => $record['updated_at']
        ];
    }
    
   
    echo json_encode([
        'success' => true,
        'message' => 'Pool data fetched successfully',
        'data' => $formattedData,
        'count' => count($formattedData)
    ]);
    
} catch (PDOException $e) {
    $errorMsg = "Database error in get_pool_data.php: " . $e->getMessage();
    error_log($errorMsg);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage(),
        'type' => 'database_error'
    ]);
} catch (Exception $e) {
    $errorMsg = "General error in get_pool_data.php: " . $e->getMessage();
    error_log($errorMsg);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'type' => 'general_error'
    ]);
}
?>