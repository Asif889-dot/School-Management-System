<?php

/**
 * Authentication & Authorization
 * Handles user login verification and role checking
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * Redirect to login page if not authenticated
 */
function requireLogin()
{
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        // Clear any existing session data
        session_unset();
        session_destroy();

        // Redirect to login page
        header('Location: ../../index.php?error=unauthorized');
        exit;
    }

    // Check session timeout (30 minutes)
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 1800) {
        session_unset();
        session_destroy();
        header('Location: ../../index.php?error=session_expired');
        exit;
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();
}

/**
 * Check if user has required role
 * @param string $requiredRole The role required to access the page
 */
function requireRole($requiredRole)
{
    requireLogin();

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $requiredRole) {
        // User doesn't have required role
        header('Location: ../../index.php?error=access_denied');
        exit;
    }
}

/**
 * Check if user has any of the specified roles
 * @param array $roles Array of allowed roles
 */
function requireAnyRole($roles)
{
    requireLogin();

    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $roles)) {
        header('Location: ../../index.php?error=access_denied');
        exit;
    }
}

/**
 * Get current user info
 * @return array|null User data or null if not logged in
 */
function getCurrentUser()
{
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'reg_id' => $_SESSION['reg_id'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null,
            'role' => $_SESSION['role'] ?? null
        ];
    }
    return null;
}

/**
 * Check if user is logged in (boolean)
 * @return bool
 */
function isLoggedIn()
{
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Get user role
 * @return string|null
 */
function getUserRole()
{
    return $_SESSION['role'] ?? null;
}

/**
 * Get user permissions from session
 * @return array
 */
function getUserPermissions()
{
    return $_SESSION['permissions'] ?? [];
}

/**
 * Define all available permissions in the system
 * @return array
 */
function getAllPermissions()
{
    return [
        // Students
        'students_view' => ['category' => 'Students', 'name' => 'View Students', 'description' => 'View list of all students'],
        'students_add' => ['category' => 'Students', 'name' => 'Add Students', 'description' => 'Add new students to the system'],
        'students_edit' => ['category' => 'Students', 'name' => 'Edit Students', 'description' => 'Edit existing student information'],
        'students_delete' => ['category' => 'Students', 'name' => 'Delete Students', 'description' => 'Remove students from the system'],
        'students_approve' => ['category' => 'Students', 'name' => 'Approve Students', 'description' => 'Approve student registrations'],

        // Teachers
        'teachers_view' => ['category' => 'Teachers', 'name' => 'View Teachers', 'description' => 'View list of all teachers'],
        'teachers_add' => ['category' => 'Teachers', 'name' => 'Add Teachers', 'description' => 'Add new teachers to the system'],
        'teachers_edit' => ['category' => 'Teachers', 'name' => 'Edit Teachers', 'description' => 'Edit existing teacher information'],
        'teachers_delete' => ['category' => 'Teachers', 'name' => 'Delete Teachers', 'description' => 'Remove teachers from the system'],

        // Guardians
        'guardians_view' => ['category' => 'Guardians', 'name' => 'View Guardians', 'description' => 'View list of all guardians'],
        'guardians_add' => ['category' => 'Guardians', 'name' => 'Add Guardians', 'description' => 'Add new guardians to the system'],
        'guardians_edit' => ['category' => 'Guardians', 'name' => 'Edit Guardians', 'description' => 'Edit existing guardian information'],
        'guardians_delete' => ['category' => 'Guardians', 'name' => 'Delete Guardians', 'description' => 'Remove guardians from the system'],

        // Fee Management
        'fee_view' => ['category' => 'Fee Management', 'name' => 'View Fee Structure', 'description' => 'View fee structures and collections'],
        'fee_add' => ['category' => 'Fee Management', 'name' => 'Add Fee Structure', 'description' => 'Create new fee structures'],
        'fee_edit' => ['category' => 'Fee Management', 'name' => 'Edit Fee Structure', 'description' => 'Modify existing fee structures'],
        'fee_delete' => ['category' => 'Fee Management', 'name' => 'Delete Fee Structure', 'description' => 'Remove fee structures'],
        'fee_collect' => ['category' => 'Fee Management', 'name' => 'Collect Fees', 'description' => 'Process fee collections'],

        // Exam Management
        'exams_view' => ['category' => 'Examinations', 'name' => 'View Exams', 'description' => 'View exam schedules and results'],
        'exams_manage' => ['category' => 'Examinations', 'name' => 'Manage Exams', 'description' => 'Create and edit exam schedules'],
        'marks_entry' => ['category' => 'Examinations', 'name' => 'Marks Entry', 'description' => 'Enter and edit student marks'],
        'marks_view' => ['category' => 'Examinations', 'name' => 'View Marks', 'description' => 'View student marks and results'],

        // Sessions
        'sessions_view' => ['category' => 'Academic Sessions', 'name' => 'View Sessions', 'description' => 'View academic sessions'],
        'sessions_manage' => ['category' => 'Academic Sessions', 'name' => 'Manage Sessions', 'description' => 'Create and edit academic sessions'],

        // Courses
        'courses_view' => ['category' => 'Courses', 'name' => 'View Courses', 'description' => 'View course list'],
        'courses_add' => ['category' => 'Courses', 'name' => 'Add Courses', 'description' => 'Create new courses'],
        'courses_edit' => ['category' => 'Courses', 'name' => 'Edit Courses', 'description' => 'Modify course details'],
        'courses_delete' => ['category' => 'Courses', 'name' => 'Delete Courses', 'description' => 'Remove courses'],

        // Books/Library
        'books_view' => ['category' => 'Library', 'name' => 'View Books', 'description' => 'View library books'],
        'books_add' => ['category' => 'Library', 'name' => 'Add Books', 'description' => 'Add new books to library'],
        'books_edit' => ['category' => 'Library', 'name' => 'Edit Books', 'description' => 'Edit book information'],
        'books_delete' => ['category' => 'Library', 'name' => 'Delete Books', 'description' => 'Remove books from library'],

        // Attendance
        'attendance_view' => ['category' => 'Attendance', 'name' => 'View Attendance', 'description' => 'View attendance records'],
        'attendance_mark' => ['category' => 'Attendance', 'name' => 'Mark Attendance', 'description' => 'Mark student attendance'],
        'attendance_report' => ['category' => 'Attendance', 'name' => 'Attendance Reports', 'description' => 'Generate attendance reports'],

        // Timetable
        'timetable_view' => ['category' => 'Timetable', 'name' => 'View Timetable', 'description' => 'View class timetables'],
        'timetable_manage' => ['category' => 'Timetable', 'name' => 'Manage Timetable', 'description' => 'Create and edit timetables'],

        // Reports
        'reports_view' => ['category' => 'Reports', 'name' => 'View Reports', 'description' => 'Access all reports'],
        'reports_generate' => ['category' => 'Reports', 'name' => 'Generate Reports', 'description' => 'Generate various reports'],

        // Users & Roles
        'users_view' => ['category' => 'Users & Roles', 'name' => 'View Users', 'description' => 'View system users'],
        'users_manage' => ['category' => 'Users & Roles', 'name' => 'Manage Users', 'description' => 'Create, edit, and delete users'],

        // Settings
        'settings_view' => ['category' => 'Settings', 'name' => 'View Settings', 'description' => 'View system settings'],
        'settings_edit' => ['category' => 'Settings', 'name' => 'Edit Settings', 'description' => 'Modify system settings']
    ];
}

/**
 * Get default permissions for each role
 * @param string $role User role
 * @return array Array of permission keys
 */
function getDefaultPermissionsForRole($role)
{
    $defaults = [
        // Admin: Full access to everything
        'admin' => [
            'students_view',
            'students_add',
            'students_edit',
            'students_delete',
            'students_approve',
            'teachers_view',
            'teachers_add',
            'teachers_edit',
            'teachers_delete',
            'guardians_view',
            'guardians_add',
            'guardians_edit',
            'guardians_delete',
            'fee_view',
            'fee_add',
            'fee_edit',
            'fee_delete',
            'fee_collect',
            'exams_view',
            'exams_manage',
            'marks_entry',
            'marks_view',
            'sessions_view',
            'sessions_manage',
            'courses_view',
            'courses_add',
            'courses_edit',
            'courses_delete',
            'books_view',
            'books_add',
            'books_edit',
            'books_delete',
            'attendance_view',
            'attendance_mark',
            'attendance_report',
            'timetable_view',
            'timetable_manage',
            'reports_view',
            'reports_generate',
            'users_view',
            'users_manage',
            'settings_view',
            'settings_edit'
        ],
        // Teacher: Standard teacher permissions
        'teacher' => [
            'students_view',
            'exams_view',
            'marks_entry',
            'marks_view',
            'courses_view',
            'attendance_view',
            'attendance_mark',
            'timetable_view'
        ],
        // Guardian: Basic view permissions for their children
        'guardian' => [
            'students_view',
            'fee_view',
            'attendance_view',
            'marks_view'
        ],
        // Student: Minimal permissions
        'student' => [
            'courses_view',
            'attendance_view',
            'marks_view',
            'fee_view'
        ]
    ];

    return $defaults[$role] ?? [];
}

/**
 * Check if user has specific permission
 * @param string $permission Permission key to check
 * @return bool
 */
function hasPermission($permission)
{
    // Admin has all permissions
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return true;
    }

    // Get permissions from session (loaded from database)
    $permissions = getUserPermissions();

    // If user has no specific permissions assigned, use role defaults
    if (empty($permissions)) {
        $role = $_SESSION['role'] ?? null;
        if ($role) {
            $permissions = getDefaultPermissionsForRole($role);
        }
    }

    return in_array($permission, $permissions);
}

/**
 * Require specific permission - redirects if not allowed
 * @param string $permission Permission key required
 */
function requirePermission($permission)
{
    requireLogin();

    if (!hasPermission($permission)) {
        header('Location: ../../index.php?error=access_denied');
        exit;
    }
}

/**
 * Check if user has any of the specified permissions
 * @param array $permissions Array of permission keys
 * @return bool
 */
function hasAnyPermission($permissions)
{
    foreach ($permissions as $permission) {
        if (hasPermission($permission)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if user has all of the specified permissions
 * @param array $permissions Array of permission keys
 * @return bool
 */
function hasAllPermissions($permissions)
{
    foreach ($permissions as $permission) {
        if (!hasPermission($permission)) {
            return false;
        }
    }
    return true;
}

/**
 * Logout user
 */
function logout()
{
    // Clear all session variables
    $_SESSION = array();

    // Delete session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    // Destroy session
    session_destroy();

    // Delete remember me cookie if exists
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
}

/**
 * Get user data by ID
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @return array|null User data or null if not found
 */
function getUserById($pdo, $userId)
{
    $stmt = $pdo->prepare("SELECT id, reg_id, full_name, email, role, status, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Update user's last login time
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 */
function updateLastLogin($pdo, $userId)
{
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$userId]);
}
