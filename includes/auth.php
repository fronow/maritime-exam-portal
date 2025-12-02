<?php
/**
 * Authentication & Security Module
 * Maritime Exam Portal
 *
 * Handles user authentication, session management, and security
 */

// Prevent direct access
if (!defined('API_ACCESS')) {
    http_response_code(403);
    die('Direct access not permitted');
}

// Session token expiry (7 days in seconds)
define('TOKEN_EXPIRY', 7 * 24 * 60 * 60);

// Secret key for token encryption (CHANGE THIS IN PRODUCTION!)
define('TOKEN_SECRET', 'CHANGE_THIS_SECRET_KEY_IN_PRODUCTION_12345');

/**
 * Hash password securely
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
}

/**
 * Verify password against hash
 * @param string $password Plain text password
 * @param string $hash Hashed password
 * @return bool True if password matches
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate session token for user
 * @param array $user User data
 * @return string Encrypted session token
 */
function generateToken($user) {
    $tokenData = [
        'user_id' => $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'exp' => time() + TOKEN_EXPIRY
    ];

    $json = json_encode($tokenData);
    $encrypted = openssl_encrypt($json, 'AES-256-CBC', TOKEN_SECRET, 0, substr(TOKEN_SECRET, 0, 16));

    return base64_encode($encrypted);
}

/**
 * Verify and decode session token
 * @param string $token Session token
 * @return array|null Decoded token data or null if invalid
 */
function verifyToken($token) {
    if (empty($token)) {
        return null;
    }

    try {
        $encrypted = base64_decode($token);
        $json = openssl_decrypt($encrypted, 'AES-256-CBC', TOKEN_SECRET, 0, substr(TOKEN_SECRET, 0, 16));

        if (!$json) {
            return null;
        }

        $tokenData = json_decode($json, true);

        // Check expiry
        if (!isset($tokenData['exp']) || $tokenData['exp'] < time()) {
            return null;
        }

        return $tokenData;

    } catch (Exception $e) {
        error_log('Token verification error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get current authenticated user from token
 * @param string $token Session token
 * @return array|null User data or null if not authenticated
 */
function getCurrentUser($token) {
    $tokenData = verifyToken($token);

    if (!$tokenData) {
        return null;
    }

    // Get fresh user data from database
    $sql = "SELECT id, email, first_name, last_name, role, is_suspended, last_login
            FROM users
            WHERE id = ?";

    $user = dbQuerySingle($sql, [$tokenData['user_id']]);

    if (!$user) {
        return null;
    }

    // Check if user is suspended
    if ($user['is_suspended']) {
        return null;
    }

    return $user;
}

/**
 * Require authentication - returns user or throws error
 * @param string $token Session token
 * @return array User data
 * @throws Exception if not authenticated
 */
function requireAuth($token) {
    $user = getCurrentUser($token);

    if (!$user) {
        throw new Exception('Authentication required', 401);
    }

    return $user;
}

/**
 * Require admin authentication - returns admin user or throws error
 * @param string $token Session token
 * @return array Admin user data
 * @throws Exception if not authenticated or not admin
 */
function requireAdmin($token) {
    $user = requireAuth($token);

    if ($user['role'] !== 'ADMIN') {
        throw new Exception('Admin access required', 403);
    }

    return $user;
}

/**
 * Sanitize input to prevent XSS
 * @param mixed $data Input data (string, array, or other)
 * @return mixed Sanitized data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }

    if (is_string($data)) {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }

    return $data;
}

/**
 * Validate email format
 * @param string $email Email address
 * @return bool True if valid
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 * @param string $password Password
 * @return array ['valid' => bool, 'message' => string]
 */
function validatePassword($password) {
    if (strlen($password) < 6) {
        return [
            'valid' => false,
            'message' => 'Password must be at least 6 characters long'
        ];
    }

    return ['valid' => true, 'message' => ''];
}

/**
 * Update user's last login timestamp
 * @param int $userId User ID
 */
function updateLastLogin($userId) {
    $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
    dbExecute($sql, [$userId]);
}

/**
 * Get client IP address
 * @return string IP address
 */
function getClientIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

/**
 * Log audit trail for admin actions
 * @param int $userId User who performed action
 * @param string $action Action name
 * @param string $entityType Entity type (user, category, etc.)
 * @param int $entityId Entity ID
 * @param array $details Additional details
 */
function logAudit($userId, $action, $entityType = null, $entityId = null, $details = []) {
    try {
        $sql = "INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address)
                VALUES (?, ?, ?, ?, ?, ?)";

        $detailsJson = !empty($details) ? json_encode($details) : null;
        $ip = getClientIp();

        dbExecute($sql, [$userId, $action, $entityType, $entityId, $detailsJson, $ip]);
    } catch (Exception $e) {
        // Don't fail the request if audit logging fails
        error_log('Audit log error: ' . $e->getMessage());
    }
}

/**
 * Check if user exists by email
 * @param string $email Email address
 * @return bool True if user exists
 */
function userExists($email) {
    $sql = "SELECT COUNT(*) as count FROM users WHERE email = ?";
    $result = dbQuerySingle($sql, [$email]);
    return $result['count'] > 0;
}

/**
 * Get user by email
 * @param string $email Email address
 * @return array|null User data or null
 */
function getUserByEmail($email) {
    $sql = "SELECT * FROM users WHERE email = ?";
    return dbQuerySingle($sql, [$email]);
}

/**
 * Get user by ID
 * @param int $userId User ID
 * @return array|null User data or null
 */
function getUserById($userId) {
    $sql = "SELECT id, email, first_name, last_name, role, is_suspended, created_at, last_login
            FROM users
            WHERE id = ?";
    return dbQuerySingle($sql, [$userId]);
}

/**
 * Prepare user data for API response (remove sensitive fields)
 * @param array $user User data from database
 * @return array Safe user data
 */
function prepareUserData($user) {
    // Remove password hash and other sensitive data
    unset($user['password_hash']);

    // Convert boolean fields
    if (isset($user['is_suspended'])) {
        $user['is_suspended'] = (bool)$user['is_suspended'];
    }

    return $user;
}

?>
