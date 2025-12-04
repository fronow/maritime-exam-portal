<?php
/**
 * Complete Working API - All Endpoints
 * Maritime Exam Portal
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/api_errors.log');

// Security: Force HTTPS
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) { // Allow localhost for testing
        header('HTTP/1.1 403 Forbidden');
        die(json_encode(['success' => false, 'error' => 'HTTPS required']));
    }
}

// Security: CORS - Only allow your domain
$allowed_origin = 'https://news.morskiizpit.com';
if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] === $allowed_origin) {
    header('Access-Control-Allow-Origin: ' . $allowed_origin);
} else {
    header('Access-Control-Allow-Origin: ' . $allowed_origin); // Fallback
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Database connection
define('DB_HOST', 'localhost');
define('DB_NAME', 'morskiiz_maritime');
define('DB_USER', 'morskiiz_maritime_user');
define('DB_PASS', 'YOUR_PASSWORD'); // ⚠️ UPDATE THIS!

function getDb() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdo;
}

// Security: Rate limiting (simple IP-based)
function checkRateLimit($action, $identifier) {
    $pdo = getDb();
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM audit_log WHERE action = ? AND details LIKE ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute([$action, "%$identifier%"]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $limits = [
        'register' => 5,  // Max 5 registrations per IP per hour
        'login' => 20     // Max 20 login attempts per IP per hour
    ];

    return ($result['count'] < ($limits[$action] ?? 100));
}

// Security: Validate email
function validateEmail($email) {
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Security: Validate password strength
function validatePassword($password) {
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

// Security: Sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Security: Generate secure token
function generateSecureToken($userId) {
    $data = [
        'user_id' => $userId,
        'timestamp' => time(),
        'random' => bin2hex(random_bytes(16))
    ];

    $json = json_encode($data);

    // Use openssl to encrypt token
    $key = hash('sha256', 'YOUR_SECRET_KEY_HERE_CHANGE_THIS', true); // ⚠️ CHANGE THIS!
    $iv = substr(hash('sha256', 'YOUR_IV_HERE_CHANGE_THIS'), 0, 16);

    $encrypted = openssl_encrypt($json, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($encrypted);
}

// Security: Verify token and return user data
function verifyToken($token) {
    try {
        $key = hash('sha256', 'YOUR_SECRET_KEY_HERE_CHANGE_THIS', true);
        $iv = substr(hash('sha256', 'YOUR_IV_HERE_CHANGE_THIS'), 0, 16);

        $encrypted = base64_decode($token);
        $json = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);

        if (!$json) return false;

        $data = json_decode($json, true);

        // Check if token is expired (7 days)
        if (time() - $data['timestamp'] > 7 * 24 * 60 * 60) {
            return false;
        }

        // Get user from database
        $pdo = getDb();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$data['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || $user['is_suspended']) {
            return false;
        }

        return $user;
    } catch (Exception $e) {
        return false;
    }
}

// Security: Require authentication (returns user or throws exception)
function requireAuth($sessionToken) {
    $user = verifyToken($sessionToken);
    if (!$user) {
        throw new Exception('Authentication required', 401);
    }
    return $user;
}

// Security: Require admin authentication (returns admin user or throws exception)
function requireAdmin($sessionToken) {
    $user = requireAuth($sessionToken);
    if ($user['role'] !== 'ADMIN') {
        throw new Exception('Admin access required', 403);
    }
    return $user;
}

// Security: Get client IP (only trust REMOTE_ADDR to prevent spoofing)
function getClientIp() {
    // Only trust REMOTE_ADDR as it cannot be spoofed by the client
    // If behind a proxy, configure your proxy to set REMOTE_ADDR correctly
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function formatUser($user) {
    return [
        'id' => (string)$user['id'],
        'email' => $user['email'],
        'firstName' => $user['first_name'],
        'lastName' => $user['last_name'],
        'role' => $user['role'],
        'isSuspended' => (bool)$user['is_suspended'],
        'createdAt' => $user['created_at'] ?? null
    ];
}

function formatCategory($cat) {
    return [
        'id' => (string)$cat['id'],
        'nameBg' => $cat['name_bg'] ?: $cat['category'],
        'nameEn' => $cat['name_en'] ?: $cat['category'],
        'price' => (float)($cat['price'] ?? 25.00),
        'questionCount' => (int)($cat['question_count'] ?? 0),
        'durationMinutes' => (int)($cat['exam_duration_minutes'] ?? 60),
        'durationDays' => (int)($cat['duration_days'] ?? 365)
    ];
}

function getQuestionWithOptions($questionId, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE id = ?");
    $stmt->execute([$questionId]);
    $q = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$q) return null;

    $stmt = $pdo->prepare("SELECT choice, is_correct FROM question_answer_choices WHERE question_id = ? ORDER BY id LIMIT 4");
    $stmt->execute([$questionId]);
    $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $options = [
        'A' => $choices[0]['choice'] ?? '',
        'B' => $choices[1]['choice'] ?? '',
        'C' => $choices[2]['choice'] ?? '',
        'D' => $choices[3]['choice'] ?? ''
    ];

    $correct = 'A';
    foreach ($choices as $idx => $choice) {
        if ($choice['is_correct'] == 1) {
            $correct = chr(65 + $idx);
            break;
        }
    }

    return [
        'id' => (int)$q['id'],
        'categoryId' => (int)$q['question_category_id'],
        'text' => $q['question'],
        'optionA' => $options['A'],
        'optionB' => $options['B'],
        'optionC' => $options['C'],
        'optionD' => $options['D'],
        'correctAnswer' => $correct,
        'imageFilename' => $q['question_image']
    ];
}

try {
    $input = file_get_contents('php://input');
    $request = json_decode($input, true);

    if (!$request || !isset($request['action'])) {
        throw new Exception('No action specified');
    }

    $action = $request['action'];
    $data = $request['data'] ?? [];
    $pdo = getDb();

    switch ($action) {

        // ============ AUTH ACTIONS ============

        case 'register':
            // Security: Rate limiting
            $clientIp = getClientIp();
            if (!checkRateLimit('register', $clientIp)) {
                throw new Exception('Too many registration attempts. Please try again later.');
            }

            // Security: Sanitize inputs
            $email = sanitizeInput($data['email']);
            $password = $data['password']; // Don't sanitize password
            $firstName = sanitizeInput($data['firstName']);
            $lastName = sanitizeInput($data['lastName']);

            // Security: Validate email
            if (!validateEmail($email)) {
                throw new Exception('Invalid email address');
            }

            // Security: Validate password strength
            $passwordCheck = validatePassword($password);
            if (!$passwordCheck['valid']) {
                throw new Exception(implode('. ', $passwordCheck['errors']));
            }

            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception('Email already registered');
            }

            // Hash password securely
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            // Insert user
            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, first_name, last_name, role, is_suspended) VALUES (?, ?, ?, ?, 'USER', FALSE)");
            $stmt->execute([$email, $hash, $firstName, $lastName]);
            $userId = $pdo->lastInsertId();

            // Log registration for rate limiting
            $stmt = $pdo->prepare("INSERT INTO audit_log (action, details) VALUES ('register', ?)");
            $stmt->execute([json_encode(['ip' => $clientIp, 'email' => $email])]);

            // Get user
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Generate secure token
            $token = generateSecureToken($user['id']);

            echo json_encode([
                'success' => true,
                'data' => [
                    'user' => formatUser($user),
                    'session_token' => $token
                ],
                'message' => 'Registration successful'
            ]);
            break;

        case 'login':
            // Security: Rate limiting
            $clientIp = getClientIp();
            if (!checkRateLimit('login', $clientIp)) {
                throw new Exception('Too many login attempts. Please try again later.');
            }

            // Security: Sanitize email
            $email = sanitizeInput($data['email']);
            $password = $data['password'];

            // Security: Validate email format
            if (!validateEmail($email)) {
                throw new Exception('Invalid email address');
            }

            // Get user
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Security: Use constant time comparison to prevent timing attacks
            if (!$user || !password_verify($password, $user['password_hash'])) {
                // Log failed attempt
                $stmt = $pdo->prepare("INSERT INTO audit_log (action, details) VALUES ('login_failed', ?)");
                $stmt->execute([json_encode(['ip' => $clientIp, 'email' => $email])]);

                throw new Exception('Invalid email or password');
            }

            // Check if account is suspended
            if ($user['is_suspended']) {
                throw new Exception('Account suspended. Please contact support.');
            }

            // Log successful login
            $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, details) VALUES (?, 'login', ?)");
            $stmt->execute([$user['id'], json_encode(['ip' => $clientIp])]);

            // Update last login time
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);

            // Generate secure token
            $token = generateSecureToken($user['id']);

            echo json_encode([
                'success' => true,
                'data' => [
                    'user' => formatUser($user),
                    'session_token' => $token
                ]
            ]);
            break;

        case 'get_initial_data':
            $stmt = $pdo->query("SELECT * FROM question_categories WHERE is_active = TRUE ORDER BY id");
            $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $categories = array_map('formatCategory', $cats);

            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            $settingsRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $settings = [];
            foreach ($settingsRows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'categories' => $categories,
                    'packages' => [],
                    'settings' => [
                        'revolutLink' => $settings['revolut_link'] ?? '',
                        'facebookLink' => $settings['facebook_link'] ?? ''
                    ]
                ]
            ]);
            break;

        // ============ ADMIN ACTIONS ============

        case 'get_admin_data':
            // Security: Require admin authentication
            $token = $request['session_token'] ?? null;
            $admin = requireAdmin($token);

            $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->query("
                SELECT ar.*, u.email, u.first_name, u.last_name, c.category as category_name
                FROM access_requests ar
                JOIN users u ON ar.user_id = u.id
                JOIN question_categories c ON ar.category_id = c.id
                WHERE ar.status = 'PENDING'
                ORDER BY ar.requested_at DESC
            ");
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'users' => array_map('formatUser', $users),
                    'pendingRequests' => $requests
                ]
            ]);
            break;

        case 'save_settings':
            // Security: Require admin authentication
            $token = $request['session_token'] ?? null;
            $admin = requireAdmin($token);

            $settings = $data['settings'] ?? [];

            foreach ($settings as $key => $value) {
                $dbKey = $key === 'revolutLink' ? 'revolut_link' : ($key === 'facebookLink' ? 'facebook_link' : $key);
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$dbKey, $value, $value]);
            }

            echo json_encode(['success' => true, 'message' => 'Settings saved']);
            break;

        case 'save_category':
            // Security: Require admin authentication
            $token = $request['session_token'] ?? null;
            $admin = requireAdmin($token);

            $cat = $data['category'];
            $id = $cat['id'] ?? null;

            if ($id && strpos($id, 'cat-') === false) {
                $stmt = $pdo->prepare("UPDATE question_categories SET name_bg = ?, name_en = ?, price = ?, exam_duration_minutes = ?, duration_days = ? WHERE id = ?");
                $stmt->execute([
                    $cat['nameBg'],
                    $cat['nameEn'],
                    $cat['price'],
                    $cat['durationMinutes'],
                    $cat['durationDays'] ?? 365,
                    $id
                ]);
            }

            echo json_encode(['success' => true]);
            break;

        case 'approve_request':
            // Security: Require admin authentication
            $token = $request['session_token'] ?? null;
            $admin = requireAdmin($token);

            $requestId = $data['requestId'] ?? null;
            $userId = $data['userId'];
            $categoryIds = $data['categoryIds'] ?? [];
            $durationDays = $data['durationDays'] ?? 365;
            $expirationDate = $data['expirationDate'] ?? null; // Custom expiration date

            // Use custom date if provided, otherwise calculate from duration
            if ($expirationDate) {
                $expiresAt = $expirationDate;
            } else {
                $expiresAt = date('Y-m-d H:i:s', strtotime("+{$durationDays} days"));
            }

            // If single request ID provided, get category from request
            if ($requestId && empty($categoryIds)) {
                $stmt = $pdo->prepare("SELECT category_id FROM access_requests WHERE id = ?");
                $stmt->execute([$requestId]);
                $req = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($req) {
                    $categoryIds = [$req['category_id']];
                }
            }

            foreach ($categoryIds as $catId) {
                $stmt = $pdo->prepare("INSERT INTO user_categories (user_id, category_id, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE expires_at = ?");
                $stmt->execute([$userId, $catId, $expiresAt, $expiresAt]);

                $stmt = $pdo->prepare("UPDATE access_requests SET status = 'APPROVED', processed_at = NOW() WHERE user_id = ? AND category_id = ? AND status = 'PENDING'");
                $stmt->execute([$userId, $catId]);
            }

            echo json_encode(['success' => true, 'message' => 'Access approved']);
            break;

        case 'get_pending_requests':
            // Security: Require admin authentication
            $token = $request['session_token'] ?? null;
            $admin = requireAdmin($token);

            // Get all pending requests with user and category details
            $stmt = $pdo->query("
                SELECT
                    ar.*,
                    u.email, u.first_name, u.last_name,
                    c.category as category_name, c.name_bg, c.name_en, c.price, c.duration_days
                FROM access_requests ar
                JOIN users u ON ar.user_id = u.id
                JOIN question_categories c ON ar.category_id = c.id
                WHERE ar.status = 'PENDING'
                ORDER BY ar.requested_at DESC
            ");
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $formatted = [];
            foreach ($requests as $req) {
                $formatted[] = [
                    'id' => (int)$req['id'],
                    'userId' => (int)$req['user_id'],
                    'categoryId' => (int)$req['category_id'],
                    'userName' => $req['first_name'] . ' ' . $req['last_name'],
                    'userEmail' => $req['email'],
                    'categoryName' => $req['category_name'],
                    'categoryNameBg' => $req['name_bg'] ?: $req['category_name'],
                    'categoryNameEn' => $req['name_en'] ?: $req['category_name'],
                    'price' => (float)($req['price'] ?? 25),
                    'defaultDuration' => (int)($req['duration_days'] ?? 365),
                    'requestedAt' => $req['requested_at'],
                    'status' => $req['status']
                ];
            }

            echo json_encode(['success' => true, 'data' => ['requests' => $formatted]]);
            break;

        case 'reject_request':
            // Security: Require admin authentication
            $token = $request['session_token'] ?? null;
            $admin = requireAdmin($token);

            $userId = $data['userId'];
            $categoryIds = $data['categoryIds'];

            foreach ($categoryIds as $catId) {
                $stmt = $pdo->prepare("UPDATE access_requests SET status = 'REJECTED', processed_at = NOW() WHERE user_id = ? AND category_id = ? AND status = 'PENDING'");
                $stmt->execute([$userId, $catId]);
            }

            echo json_encode(['success' => true, 'message' => 'Request rejected']);
            break;

        case 'toggle_suspend':
            // Security: Require admin authentication
            $token = $request['session_token'] ?? null;
            $admin = requireAdmin($token);

            $userId = $data['userId'];
            $suspend = $data['suspend'] ? 1 : 0;

            // Prevent admin from suspending themselves
            if ($userId == $admin['id']) {
                throw new Exception('You cannot suspend your own account', 400);
            }

            $stmt = $pdo->prepare("UPDATE users SET is_suspended = ? WHERE id = ?");
            $stmt->execute([$suspend, $userId]);

            echo json_encode(['success' => true]);
            break;

        // ============ USER ACTIONS ============

        case 'request_access':
            // Security: Require user authentication
            $token = $request['session_token'] ?? null;
            $user = requireAuth($token);

            $userId = $user['id']; // Use authenticated user's ID, not from request
            $categoryIds = $data['categoryIds'];

            foreach ($categoryIds as $catId) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO access_requests (user_id, category_id, status) VALUES (?, ?, 'PENDING')");
                $stmt->execute([$userId, $catId]);
            }

            echo json_encode(['success' => true, 'message' => 'Access requested']);
            break;

        // ============ TEST ACTIONS ============

        case 'generate_test':
            // Security: Require user authentication
            $token = $request['session_token'] ?? null;
            $user = requireAuth($token);

            $categoryId = $data['category_id'];
            $userId = $user['id']; // Use authenticated user's ID

            // Verify user has access to this category
            $stmt = $pdo->prepare("SELECT * FROM user_categories WHERE user_id = ? AND category_id = ? AND (expires_at IS NULL OR expires_at > NOW())");
            $stmt->execute([$userId, $categoryId]);
            if (!$stmt->fetch()) {
                throw new Exception('You do not have access to this category', 403);
            }

            // Get all questions for category
            $stmt = $pdo->prepare("SELECT id FROM questions WHERE question_category_id = ? ORDER BY id");
            $stmt->execute([$categoryId]);
            $questionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($questionIds) < 60) {
                throw new Exception('Not enough questions in this category');
            }

            // 25% distribution
            $total = count($questionIds);
            $chunkSize = floor($total / 4);
            $selected = [];

            for ($i = 0; $i < 4; $i++) {
                $start = $i * $chunkSize;
                $end = ($i === 3) ? $total : ($i + 1) * $chunkSize;
                $chunk = array_slice($questionIds, $start, $end - $start);
                shuffle($chunk);
                $selected = array_merge($selected, array_slice($chunk, 0, 15));
            }

            // Create session
            $stmt = $pdo->prepare("INSERT INTO test_sessions (user_id, category_id, question_order) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $categoryId, json_encode($selected)]);
            $sessionId = $pdo->lastInsertId();

            // Get questions with options
            $questions = [];
            foreach ($selected as $qId) {
                $q = getQuestionWithOptions($qId, $pdo);
                if ($q) {
                    unset($q['correctAnswer']); // Don't send correct answer to frontend
                    $questions[] = $q;
                }
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'sessionId' => $sessionId,
                    'questions' => $questions
                ]
            ]);
            break;

        case 'complete_test':
            // Security: Require user authentication
            $token = $request['session_token'] ?? null;
            $user = requireAuth($token);

            $sessionId = $data['session_id'];
            $answers = $data['answers'] ?? [];

            // Get session and verify it belongs to this user
            $stmt = $pdo->prepare("SELECT question_order, user_id, category_id FROM test_sessions WHERE id = ? AND user_id = ?");
            $stmt->execute([$sessionId, $user['id']]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                throw new Exception('Test session not found or access denied', 404);
            }
            $questionIds = json_decode($session['question_order'], true);

            $score = 0;
            $results = [];

            foreach ($questionIds as $qId) {
                $userAnswer = $answers[(string)$qId] ?? null;
                $q = getQuestionWithOptions($qId, $pdo);

                if ($q && $userAnswer) {
                    $isCorrect = ($userAnswer === $q['correctAnswer']);
                    if ($isCorrect) $score++;

                    $results[] = [
                        'questionId' => $qId,
                        'userAnswer' => $userAnswer,
                        'correctAnswer' => $q['correctAnswer'],
                        'isCorrect' => $isCorrect
                    ];
                }
            }

            // Update session
            $stmt = $pdo->prepare("UPDATE test_sessions SET score = ?, completed = TRUE, completed_at = NOW() WHERE id = ?");
            $stmt->execute([$score, $sessionId]);

            // Cleanup: Keep only last 25 tests per user per category
            $stmt = $pdo->prepare("
                DELETE FROM test_sessions
                WHERE user_id = ? AND category_id = ? AND completed = TRUE
                AND id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM test_sessions
                        WHERE user_id = ? AND category_id = ? AND completed = TRUE
                        ORDER BY completed_at DESC LIMIT 25
                    ) tmp
                )
            ");
            $stmt->execute([$session['user_id'], $session['category_id'], $session['user_id'], $session['category_id']]);

            echo json_encode([
                'success' => true,
                'data' => [
                    'score' => $score,
                    'totalQuestions' => count($questionIds),
                    'percentage' => round(($score / count($questionIds)) * 100, 2),
                    'results' => $results
                ]
            ]);
            break;

        case 'get_test_history':
            // Security: Require user authentication
            $token = $request['session_token'] ?? null;
            $user = requireAuth($token);

            $userId = $user['id']; // Use authenticated user's ID
            $categoryId = $data['categoryId'] ?? null;

            $sql = "SELECT ts.*, c.category as category_name
                    FROM test_sessions ts
                    JOIN question_categories c ON ts.category_id = c.id
                    WHERE ts.user_id = ? AND ts.completed = TRUE";

            $params = [$userId];
            if ($categoryId) {
                $sql .= " AND ts.category_id = ?";
                $params[] = $categoryId;
            }

            $sql .= " ORDER BY ts.completed_at DESC LIMIT 25";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $history = [];
            foreach ($tests as $test) {
                $history[] = [
                    'id' => (int)$test['id'],
                    'categoryId' => (int)$test['category_id'],
                    'categoryName' => $test['category_name'],
                    'score' => (int)$test['score'],
                    'totalQuestions' => (int)$test['total_questions'],
                    'percentage' => round(($test['score'] / $test['total_questions']) * 100, 2),
                    'completedAt' => $test['completed_at']
                ];
            }

            echo json_encode(['success' => true, 'data' => ['tests' => $history]]);
            break;

        default:
            throw new Exception('Unknown action: ' . $action);
    }

} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
