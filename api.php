<?php
/**
 * Maritime Exam Portal - Main API Endpoint
 *
 * Single endpoint that routes all API requests to appropriate handlers
 *
 * Usage: POST to /api.php with JSON body:
 * {
 *   "action": "action_name",
 *   "data": { ... },
 *   "session_token": "optional_token"
 * }
 */

// Define API access constant
define('API_ACCESS', true);

// Error reporting (disable in production!)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to client
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/api_errors.log'); // Make sure logs/ directory exists

// Start session
session_start();

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include required files
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/db_compat.php'; // Database compatibility layer
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/utils.php';
require_once __DIR__ . '/includes/actions/auth.php';
require_once __DIR__ . '/includes/actions/user.php';
require_once __DIR__ . '/includes/actions/test.php';
require_once __DIR__ . '/includes/actions/admin.php';

/**
 * Main API request handler
 */
function handleRequest() {
    try {
        // Get request method
        $method = $_SERVER['REQUEST_METHOD'];

        // Get JSON input
        $input = getJsonInput();

        if (!$input) {
            throw new Exception('Invalid JSON request', 400);
        }

        // Get action and data
        $action = $input['action'] ?? null;
        $data = $input['data'] ?? [];
        $token = $input['session_token'] ?? null;

        if (!$action) {
            throw new Exception('Action is required', 400);
        }

        // Route to appropriate handler
        switch ($action) {
            // ============================================================
            // PUBLIC ACTIONS (No authentication required)
            // ============================================================

            case 'register':
                action_register($data);
                break;

            case 'login':
                action_login($data);
                break;

            case 'get_initial_data':
                action_get_initial_data($data);
                break;

            // ============================================================
            // USER ACTIONS (User authentication required)
            // ============================================================

            case 'get_user_data':
                action_get_user_data($data, $token);
                break;

            case 'request_access':
                action_request_access($data, $token);
                break;

            case 'generate_test':
                action_generate_test($data, $token);
                break;

            case 'submit_answer':
                action_submit_answer($data, $token);
                break;

            case 'complete_test':
                action_complete_test($data, $token);
                break;

            case 'get_active_session':
                action_get_active_session($data, $token);
                break;

            // ============================================================
            // ADMIN ACTIONS (Admin authentication required)
            // ============================================================

            case 'get_admin_data':
                action_get_admin_data($data, $token);
                break;

            case 'approve_request':
                action_approve_request($data, $token);
                break;

            case 'reject_request':
                action_reject_request($data, $token);
                break;

            case 'toggle_suspend_user':
                action_toggle_suspend_user($data, $token);
                break;

            case 'save_category':
                action_save_category($data, $token);
                break;

            case 'save_package':
                action_save_package($data, $token);
                break;

            case 'import_questions':
                action_import_questions($data, $token);
                break;

            case 'save_settings':
                action_save_settings($data, $token);
                break;

            case 'change_user_password':
                action_change_user_password($data, $token);
                break;

            case 'delete_category':
                action_delete_category($data, $token);
                break;

            case 'delete_package':
                action_delete_package($data, $token);
                break;

            // ============================================================
            // UNKNOWN ACTION
            // ============================================================

            default:
                throw new Exception("Unknown action: $action", 400);
        }

    } catch (Exception $e) {
        // Log error
        logError('API Error', $e);

        // Get error code from exception (default to 500)
        $code = $e->getCode();
        if ($code < 400 || $code >= 600) {
            $code = 500;
        }

        // Send error response
        errorResponse($e->getMessage(), $code);
    }
}

// Execute main handler
handleRequest();

?>
