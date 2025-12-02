<?php
/**
 * Authentication API Actions
 * Maritime Exam Portal
 *
 * Handles user registration and login
 */

// Prevent direct access
if (!defined('API_ACCESS')) {
    http_response_code(403);
    die('Direct access not permitted');
}

/**
 * Register new user account
 * @param array $data Request data
 */
function action_register($data) {
    // Validate required fields
    validateRequiredFields($data, ['email', 'password', 'first_name', 'last_name']);

    $email = sanitizeInput($data['email']);
    $password = $data['password']; // Don't sanitize password
    $firstName = sanitizeInput($data['first_name']);
    $lastName = sanitizeInput($data['last_name']);

    // Validate email format
    if (!validateEmail($email)) {
        throw new Exception('Invalid email format', 400);
    }

    // Validate password strength
    $passwordCheck = validatePassword($password);
    if (!$passwordCheck['valid']) {
        throw new Exception($passwordCheck['message'], 400);
    }

    // Check if user already exists
    if (userExists($email)) {
        throw new Exception('Email already registered', 400);
    }

    // Hash password
    $passwordHash = hashPassword($password);

    // Insert user
    $sql = "INSERT INTO users (email, password_hash, first_name, last_name, role, is_suspended)
            VALUES (?, ?, ?, ?, 'USER', FALSE)";

    $userId = dbInsert($sql, [$email, $passwordHash, $firstName, $lastName]);

    // Get created user
    $user = getUserById($userId);

    // Generate session token
    $token = generateToken($user);

    // Update last login
    updateLastLogin($userId);

    // Prepare response
    $userData = prepareUserData($user);

    successResponse([
        'user' => $userData,
        'session_token' => $token
    ], 'Registration successful');
}

/**
 * Login user
 * @param array $data Request data
 */
function action_login($data) {
    // Validate required fields
    validateRequiredFields($data, ['email', 'password']);

    $email = sanitizeInput($data['email']);
    $password = $data['password'];

    // Get user by email
    $user = getUserByEmail($email);

    if (!$user) {
        throw new Exception('Invalid email or password', 401);
    }

    // Verify password
    if (!verifyPassword($password, $user['password_hash'])) {
        throw new Exception('Invalid email or password', 401);
    }

    // Check if user is suspended
    if ($user['is_suspended']) {
        throw new Exception('Your account has been suspended. Please contact support.', 403);
    }

    // Generate session token
    $token = generateToken($user);

    // Update last login
    updateLastLogin($user['id']);

    // Prepare response
    $userData = prepareUserData($user);

    successResponse([
        'user' => $userData,
        'session_token' => $token
    ], 'Login successful');
}

/**
 * Get initial public data (categories, packages, settings)
 * No authentication required
 * @param array $data Request data (unused)
 */
function action_get_initial_data($data) {
    // Get all active categories
    $sql = "SELECT * FROM categories WHERE is_active = TRUE ORDER BY name_en";
    $categories = dbQuery($sql);

    // Get all active packages with category IDs
    $sql = "SELECT * FROM packages WHERE is_active = TRUE ORDER BY name_en";
    $packages = dbQuery($sql);

    // Get category IDs for each package
    foreach ($packages as &$package) {
        $sql = "SELECT category_id FROM package_categories WHERE package_id = ?";
        $catRows = dbQuery($sql, [$package['id']]);
        $categoryIds = array_column($catRows, 'category_id');
        $package = preparePackageData($package, $categoryIds);
    }

    // Prepare categories
    $categories = array_map('prepareCategoryData', $categories);

    // Get settings
    $settings = getAllSettings();

    // Return public settings only
    $publicSettings = [
        'revolutLink' => $settings['revolut_payment_link'] ?? '',
        'facebookLink' => $settings['facebook_link'] ?? '',
        'announcement' => $settings['announcement_text'] ?? ''
    ];

    successResponse([
        'categories' => $categories,
        'packages' => $packages,
        'settings' => $publicSettings
    ]);
}

?>
