<?php
/**
 * Utility Helper Functions
 * Maritime Exam Portal
 *
 * Common helper functions used across the application
 */

// Prevent direct access
if (!defined('API_ACCESS')) {
    http_response_code(403);
    die('Direct access not permitted');
}

/**
 * Send JSON success response
 * @param mixed $data Response data
 * @param string $message Optional success message
 */
function successResponse($data = null, $message = null) {
    http_response_code(200);
    header('Content-Type: application/json');

    $response = ['success' => true];

    if ($data !== null) {
        $response['data'] = $data;
    }

    if ($message !== null) {
        $response['message'] = $message;
    }

    echo json_encode($response);
    exit;
}

/**
 * Send JSON error response
 * @param string $error Error message
 * @param int $code HTTP status code
 */
function errorResponse($error, $code = 400) {
    http_response_code($code);
    header('Content-Type: application/json');

    echo json_encode([
        'success' => false,
        'error' => $error
    ]);
    exit;
}

/**
 * Get JSON request body
 * @return array|null Decoded JSON data or null
 */
function getJsonInput() {
    $json = file_get_contents('php://input');
    return json_decode($json, true);
}

/**
 * Validate required fields in data array
 * @param array $data Input data
 * @param array $requiredFields List of required field names
 * @throws Exception if any required field is missing
 */
function validateRequiredFields($data, $requiredFields) {
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            throw new Exception("Field '$field' is required", 400);
        }
    }
}

/**
 * Calculate test grade based on percentage
 * @param float $percentage Score percentage (0-100)
 * @return string Grade name
 */
function calculateGrade($percentage) {
    if ($percentage < 50) {
        return 'Fail';
    } elseif ($percentage < 60) {
        return 'Pass';
    } elseif ($percentage < 75) {
        return 'Good';
    } elseif ($percentage < 90) {
        return 'Very Good';
    } else {
        return 'Excellent';
    }
}

/**
 * Generate random questions with 25% distribution algorithm
 * @param array $allQuestions All questions for the category (sorted by original_index)
 * @param int $totalNeeded Total questions needed (default 60)
 * @return array Selected questions
 */
function generate25PercentDistribution($allQuestions, $totalNeeded = 60) {
    $totalQuestions = count($allQuestions);

    if ($totalQuestions < $totalNeeded) {
        throw new Exception('Not enough questions in this category');
    }

    // Divide into 4 equal chunks
    $chunkSize = floor($totalQuestions / 4);
    $perChunk = floor($totalNeeded / 4); // 15 questions per chunk

    $selected = [];

    // Select from each chunk
    for ($i = 0; $i < 4; $i++) {
        $start = $i * $chunkSize;
        $end = ($i === 3) ? $totalQuestions : ($i + 1) * $chunkSize;

        // Get this chunk
        $chunk = array_slice($allQuestions, $start, $end - $start);

        // Shuffle and select
        shuffle($chunk);
        $selectedFromChunk = array_slice($chunk, 0, $perChunk);

        $selected = array_merge($selected, $selectedFromChunk);
    }

    return $selected;
}

/**
 * Format date for API response
 * @param string $datetime MySQL datetime string
 * @return string ISO 8601 formatted date
 */
function formatDate($datetime) {
    if (empty($datetime)) {
        return null;
    }

    $dt = new DateTime($datetime);
    return $dt->format('c'); // ISO 8601
}

/**
 * Add days to current date
 * @param int $days Number of days to add
 * @return string MySQL datetime format
 */
function addDays($days) {
    $dt = new DateTime();
    $dt->add(new DateInterval("P{$days}D"));
    return $dt->format('Y-m-d H:i:s');
}

/**
 * Check if date has expired
 * @param string $datetime MySQL datetime string
 * @return bool True if expired
 */
function isExpired($datetime) {
    if (empty($datetime)) {
        return true;
    }

    $expiry = new DateTime($datetime);
    $now = new DateTime();

    return $now > $expiry;
}

/**
 * Get setting value by key
 * @param string $key Setting key
 * @return string|null Setting value or null
 */
function getSetting($key) {
    $sql = "SELECT setting_value FROM settings WHERE setting_key = ?";
    $result = dbQuerySingle($sql, [$key]);
    return $result ? $result['setting_value'] : null;
}

/**
 * Set setting value
 * @param string $key Setting key
 * @param string $value Setting value
 */
function setSetting($key, $value) {
    $sql = "INSERT INTO settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = ?";

    dbExecute($sql, [$key, $value, $value]);
}

/**
 * Get all settings as key-value array
 * @return array Settings array
 */
function getAllSettings() {
    $sql = "SELECT setting_key, setting_value FROM settings";
    $rows = dbQuery($sql);

    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    return $settings;
}

/**
 * Prepare category data for API response
 * @param array $category Category data
 * @return array Formatted category
 */
function prepareCategoryData($category) {
    return [
        'id' => (int)$category['id'],
        'nameBg' => $category['name_bg'],
        'nameEn' => $category['name_en'],
        'price' => (float)$category['price'],
        'durationDays' => (int)$category['duration_days'],
        'questionCount' => (int)$category['question_count'],
        'examDurationMinutes' => (int)$category['exam_duration_minutes'],
        'isActive' => (bool)$category['is_active']
    ];
}

/**
 * Prepare package data for API response
 * @param array $package Package data
 * @param array $categoryIds Array of category IDs in this package
 * @return array Formatted package
 */
function preparePackageData($package, $categoryIds = []) {
    return [
        'id' => (int)$package['id'],
        'nameBg' => $package['name_bg'],
        'nameEn' => $package['name_en'],
        'descriptionBg' => $package['description_bg'] ?? '',
        'descriptionEn' => $package['description_en'] ?? '',
        'price' => (float)$package['price'],
        'durationDays' => (int)$package['duration_days'],
        'categoryIds' => array_map('intval', $categoryIds),
        'isActive' => (bool)$package['is_active']
    ];
}

/**
 * Prepare question data for API response (hide correct answer for active tests)
 * MOVED TO db_compat.php - This function is now handled by the compatibility layer
 */
// function prepareQuestionData($question, $includeAnswer = false) {
//     // This function has been moved to includes/db_compat.php
//     // to support both old and new database structures
// }

/**
 * Prepare test session data for API response
 * @param array $session Session data
 * @return array Formatted session
 */
function prepareSessionData($session) {
    $data = [
        'id' => (int)$session['id'],
        'userId' => (int)$session['user_id'],
        'categoryId' => (int)$session['category_id'],
        'startTime' => formatDate($session['start_time']),
        'score' => (int)$session['score'],
        'totalQuestions' => (int)$session['total_questions'],
        'isCompleted' => (bool)$session['is_completed']
    ];

    if (!empty($session['end_time'])) {
        $data['endTime'] = formatDate($session['end_time']);
    }

    if ($session['percentage'] !== null) {
        $data['percentage'] = (float)$session['percentage'];
    }

    if (!empty($session['grade'])) {
        $data['grade'] = $session['grade'];
    }

    if (!empty($session['duration_seconds'])) {
        $data['durationSeconds'] = (int)$session['duration_seconds'];
    }

    return $data;
}

/**
 * Enable CORS for frontend
 */
function enableCors() {
    // Allow from any origin (restrict this in production!)
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * Log error securely
 * @param string $message Error message
 * @param Exception $e Exception object (optional)
 */
function logError($message, $e = null) {
    $logMessage = $message;

    if ($e) {
        $logMessage .= ' | Error: ' . $e->getMessage();
        $logMessage .= ' | File: ' . $e->getFile() . ':' . $e->getLine();
    }

    error_log($logMessage);
}

?>
