<?php
/**
 * Login Processing Script
 * Handles user authentication for the SMS portal
 */

// Include the database connection and session start
require_once 'config.php';
require_once 'auth.php';

// Set JSON response header for AJAX compatibility
header('Content-Type: application/json');

// Initialize response structure
$response = [
    'success' => false,
    'message' => '',
    'redirect' => ''
];

// Ensure the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Sanitize and collect input from the Bootstrap form
$reg_id = trim($_POST['reg_id'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'student'; // Default to student if not provided
$remember = isset($_POST['remember']) ? true : false;

// Basic Validation
if (empty($reg_id) || empty($password)) {
    $response['message'] = 'Please enter both your ID/Email and password.';
    echo json_encode($response);
    exit;
}

try {
    // Search for the user by Registration ID or Email within the specific Role
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (reg_id = :reg_id OR email = :email) AND role = :role AND status = 'active' LIMIT 1");
    $stmt->execute([
        'reg_id' => $reg_id,
        'email' => $reg_id,
        'role' => $role
    ]);

    $user = $stmt->fetch();

    // Verify user exists and the hashed password matches
    if ($user && password_verify($password, $user['password'])) {

        // Regenerate session ID for security against session fixation
        session_regenerate_id(true);

        // Set secure session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['reg_id'] = $user['reg_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Load permissions - from user data or use defaults
        $permissionsJson = $user['permissions'] ?? null;
        if ($permissionsJson) {
            $_SESSION['permissions'] = json_decode($permissionsJson, true);
        } else {
            // Use default permissions for the role
            $_SESSION['permissions'] = getDefaultPermissionsForRole($user['role']);
        }

        // Handle "Remember Me" Cookie (Optional functionality)
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            setcookie('remember_token', $token, time() + (86400 * 30), "/", "", false, true); // 30-day HttpOnly cookie
        }

        // Define redirection paths based on user roles
        $redirectPaths = [
            'admin'    => 'frontend/admin/dashboard.php',
            'teacher'  => 'frontend/teacher/dashboard.php',
            'student'  => 'frontend/student/dashboard.php',
            'guardian' => 'frontend/guardian/dashboard.php'
        ];

        $response['success'] = true;
        $response['message'] = 'Login successful! Redirecting...';
        $response['redirect'] = $redirectPaths[$user['role']] ?? 'index.php';

        // Log the successful entry for audit purposes
        error_log("Successful login: User ID {$user['reg_id']} as {$user['role']}");
    } else {
        // Generic error message to prevent account harvesting
        $response['message'] = 'Invalid credentials. Please check your ID and password.';
        error_log("Failed login attempt for: $reg_id");
    }
} catch (PDOException $e) {
    // Log actual error but hide it from the end-user
    error_log("Login System Error: " . $e->getMessage());
    $response['message'] = 'A system error occurred. Please try again later.';
}

// Return the result to the AJAX handler
echo json_encode($response);
exit;
