<?php
require_once 'var.php';

$http_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

if (in_array($http_origin, $IP_THIS)) {
    header("Access-Control-Allow-Origin: $http_origin");
} else {
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';


if ($method === 'GET' && $action === 'getProfile') {
    $companyId = isset($_GET['companyId']) ? $_GET['companyId'] : null;
    
    if (!$companyId) {
        echo json_encode(['success' => false, 'message' => 'Company ID is required']);
        exit();
    }
    
    try {
        error_log("Searching for user with Company ID (IDNumber): " . $companyId);
        
        $stmt = $pdo->prepare("SELECT ID, fullname, EmailAddress, Company, IDNumber, status FROM signup WHERE IDNumber = ?");
        $stmt->execute([$companyId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("User found: " . ($user ? 'YES' : 'NO'));
        if ($user) {
            error_log("User data: " . json_encode($user));
        }
        
        if ($user) {
            $nameParts = explode(' ', $user['fullname'], 2);
            $firstName = $nameParts[0];
            $lastName = isset($nameParts[1]) ? $nameParts[1] : '';
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $user['ID'],
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'email' => $user['EmailAddress'],
                    'companyId' => $user['IDNumber'],
                    'company' => $user['Company'],
                    'status' => $user['status']
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'User not found with Company ID: ' . $companyId . '. Please check if this user exists in the database.'
            ]);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching profile: ' . $e->getMessage()]);
    }
}


if ($method === 'POST' && $action === 'verifyCompanyId') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $companyId = isset($data['companyId']) ? trim($data['companyId']) : '';
    
    if (!$companyId) {
        echo json_encode(['success' => false, 'message' => 'Company ID is required']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("SELECT ID, IDNumber FROM signup WHERE IDNumber = ?");
        $stmt->execute([$companyId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Company ID not found']);
            exit();
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Company ID verified successfully'
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error verifying Company ID: ' . $e->getMessage()]);
    }
}


if ($method === 'POST' && $action === 'updateProfile') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $companyId = isset($data['companyId']) ? trim($data['companyId']) : '';
    $firstName = isset($data['firstName']) ? trim($data['firstName']) : '';
    $lastName = isset($data['lastName']) ? trim($data['lastName']) : '';
    $email = isset($data['email']) ? trim($data['email']) : '';
    $newCompanyId = isset($data['newCompanyId']) ? trim($data['newCompanyId']) : '';
    
    if (!$companyId || !$firstName || !$lastName || !$email) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit();
    }
    
    try {
        // Get user by Company ID
        $stmt = $pdo->prepare("SELECT ID, IDNumber FROM signup WHERE IDNumber = ?");
        $stmt->execute([$companyId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Company ID not found']);
            exit();
        }
        
        $userId = $user['ID'];
        
        // Check if email is already in use by another user
        $stmt = $pdo->prepare("SELECT ID FROM signup WHERE EmailAddress = ? AND ID != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email address already in use']);
            exit();
        }
        
        // Check if new Company ID is already in use by another user
        if ($newCompanyId && $newCompanyId !== $companyId) {
            $stmt = $pdo->prepare("SELECT ID FROM signup WHERE IDNumber = ? AND ID != ?");
            $stmt->execute([$newCompanyId, $userId]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Company ID already in use']);
                exit();
            }
        }
        
        $fullname = $firstName . ' ' . $lastName;
        $finalCompanyId = $newCompanyId ? $newCompanyId : $companyId;
        
        $stmt = $pdo->prepare("UPDATE signup SET fullname = ?, EmailAddress = ?, IDNumber = ? WHERE ID = ?");
        $stmt->execute([$fullname, $email, $finalCompanyId, $userId]);
        
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating profile: ' . $e->getMessage()]);
    }
}


if ($method === 'POST' && $action === 'changePassword') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $companyId = isset($data['companyId']) ? trim($data['companyId']) : '';
    $oldPassword = isset($data['oldPassword']) ? $data['oldPassword'] : '';
    $newPassword = isset($data['newPassword']) ? $data['newPassword'] : '';
    $confirmPassword = isset($data['confirmPassword']) ? $data['confirmPassword'] : '';
    
    if (!$companyId || !$oldPassword || !$newPassword || !$confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }
    
    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'New password and confirm password do not match']);
        exit();
    }
    
    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("SELECT ID, IDNumber, password FROM signup WHERE IDNumber = ?");
        $stmt->execute([$companyId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Company ID not found']);
            exit();
        }
        
        // Verify old password
        if (password_verify($oldPassword, $user['password']) || $oldPassword === $user['password']) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE signup SET password = ? WHERE ID = ?");
            $stmt->execute([$hashedPassword, $user['ID']]);
            
            echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error changing password: ' . $e->getMessage()]);
    }
}


if ($method === 'POST' && $action === 'deactivateAccount') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $companyId = isset($data['companyId']) ? trim($data['companyId']) : '';
    $password = isset($data['password']) ? $data['password'] : '';
    
    if (!$companyId || !$password) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("SELECT ID, IDNumber, password FROM signup WHERE IDNumber = ?");
        $stmt->execute([$companyId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Company ID not found']);
            exit();
        }
        
        // Verify password
        if (password_verify($password, $user['password']) || $password === $user['password']) {
            $stmt = $pdo->prepare("UPDATE signup SET status = 'deactivated' WHERE ID = ?");
            $stmt->execute([$user['ID']]);
            
            echo json_encode(['success' => true, 'message' => 'Account deactivated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Incorrect password']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error deactivating account: ' . $e->getMessage()]);
    }
}


if ($method === 'POST' && $action === 'deleteAccount') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $companyId = isset($data['companyId']) ? trim($data['companyId']) : '';
    $password = isset($data['password']) ? $data['password'] : '';
    
    if (!$companyId || !$password) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("SELECT ID, IDNumber, password FROM signup WHERE IDNumber = ?");
        $stmt->execute([$companyId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Company ID not found']);
            exit();
        }
        
        // Verify password
        if (password_verify($password, $user['password']) || $password === $user['password']) {
            $stmt = $pdo->prepare("DELETE FROM signup WHERE ID = ?");
            $stmt->execute([$user['ID']]);
            
            echo json_encode(['success' => true, 'message' => 'Account deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Incorrect password']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error deleting account: ' . $e->getMessage()]);
    }
}

if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
}
?>