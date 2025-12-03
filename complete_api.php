<?php
/**
 * Complete Working API - All Endpoints
 * Maritime Exam Portal
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/api_errors.log');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
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
            $email = $data['email'];
            $password = $data['password'];
            $firstName = $data['firstName'];
            $lastName = $data['lastName'];

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception('Email already registered');
            }

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, first_name, last_name, role, is_suspended) VALUES (?, ?, ?, ?, 'USER', FALSE)");
            $stmt->execute([$email, $hash, $firstName, $lastName]);
            $userId = $pdo->lastInsertId();

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'user' => formatUser($user),
                    'session_token' => base64_encode(json_encode(['user_id' => $user['id'], 'time' => time()]))
                ],
                'message' => 'Registration successful'
            ]);
            break;

        case 'login':
            $email = $data['email'];
            $password = $data['password'];

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($password, $user['password_hash'])) {
                throw new Exception('Invalid email or password');
            }

            if ($user['is_suspended']) {
                throw new Exception('Account suspended');
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'user' => formatUser($user),
                    'session_token' => base64_encode(json_encode(['user_id' => $user['id'], 'time' => time()]))
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
            $settings = $data['settings'] ?? [];

            foreach ($settings as $key => $value) {
                $dbKey = $key === 'revolutLink' ? 'revolut_link' : ($key === 'facebookLink' ? 'facebook_link' : $key);
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$dbKey, $value, $value]);
            }

            echo json_encode(['success' => true, 'message' => 'Settings saved']);
            break;

        case 'save_category':
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
            $userId = $data['userId'];
            $categoryIds = $data['categoryIds'];

            foreach ($categoryIds as $catId) {
                $stmt = $pdo->prepare("UPDATE access_requests SET status = 'REJECTED', processed_at = NOW() WHERE user_id = ? AND category_id = ? AND status = 'PENDING'");
                $stmt->execute([$userId, $catId]);
            }

            echo json_encode(['success' => true, 'message' => 'Request rejected']);
            break;

        case 'toggle_suspend':
            $userId = $data['userId'];
            $suspend = $data['suspend'] ? 1 : 0;

            $stmt = $pdo->prepare("UPDATE users SET is_suspended = ? WHERE id = ?");
            $stmt->execute([$suspend, $userId]);

            echo json_encode(['success' => true]);
            break;

        // ============ USER ACTIONS ============

        case 'request_access':
            $userId = $data['userId'];
            $categoryIds = $data['categoryIds'];

            foreach ($categoryIds as $catId) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO access_requests (user_id, category_id, status) VALUES (?, ?, 'PENDING')");
                $stmt->execute([$userId, $catId]);
            }

            echo json_encode(['success' => true, 'message' => 'Access requested']);
            break;

        // ============ TEST ACTIONS ============

        case 'generate_test':
            $categoryId = $data['category_id'];
            $userId = $data['user_id'] ?? 1;

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
            $sessionId = $data['session_id'];
            $answers = $data['answers'] ?? [];

            // Get session
            $stmt = $pdo->prepare("SELECT question_order, user_id, category_id FROM test_sessions WHERE id = ?");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
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
            $userId = $data['userId'];
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
