<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log all requests for debugging
error_log("Get Pool Data API called - Method: " . $_SERVER['REQUEST_METHOD']);

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Database configuration
$host = 'localhost';
$dbname = 'hrms';  // Your database name
$username = 'root'; // Your database username
$password = '';     // Your database password

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    error_log("Database connection successful for get_pool_data");
    
    // Check if pool table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'pool'");
    if ($tableCheck->rowCount() == 0) {
        throw new Exception('Pool table does not exist in database');
    }
    
    error_log("Pool table exists, fetching data");
    
    // Fetch all pool records ordered by created_at DESC (newest first)
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
    
    // Format the data for frontend
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
    
    // Return success response
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