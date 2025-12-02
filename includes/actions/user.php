<?php
/**
 * User API Actions
 * Maritime Exam Portal
 *
 * Handles user-specific operations
 */

// Prevent direct access
if (!defined('API_ACCESS')) {
    http_response_code(403);
    die('Direct access not permitted');
}

/**
 * Get user-specific data (categories, test history, expiry)
 * @param array $data Request data
 * @param string $token Session token
 */
function action_get_user_data($data, $token) {
    $user = requireAuth($token);
    $userId = $user['id'];

    // Get user's approved categories with expiry
    $sql = "SELECT uc.category_id, uc.expires_at, c.name_bg, c.name_en
            FROM user_categories uc
            JOIN categories c ON uc.category_id = c.id
            WHERE uc.user_id = ?";

    $approvedCategories = dbQuery($sql, [$userId]);

    // Build category expiry map and approved category list
    $categoryExpiry = [];
    $approvedCategoryIds = [];

    foreach ($approvedCategories as $cat) {
        $categoryExpiry[$cat['category_id']] = formatDate($cat['expires_at']);
        $approvedCategoryIds[] = (int)$cat['category_id'];
    }

    // Get user's pending requests
    $sql = "SELECT ar.category_id, ar.package_id
            FROM access_requests ar
            WHERE ar.user_id = ? AND ar.status = 'PENDING'";

    $requests = dbQuery($sql, [$userId]);

    $requestedCategories = [];
    $requestedPackages = [];

    foreach ($requests as $req) {
        if ($req['category_id']) {
            $requestedCategories[] = (int)$req['category_id'];
        }
        if ($req['package_id']) {
            $requestedPackages[] = (int)$req['package_id'];
        }
    }

    // Get user's test history (completed tests only)
    $sql = "SELECT ts.*, c.name_bg as category_name_bg, c.name_en as category_name_en
            FROM test_sessions ts
            JOIN categories c ON ts.category_id = c.id
            WHERE ts.user_id = ? AND ts.is_completed = TRUE
            ORDER BY ts.start_time DESC
            LIMIT 50";

    $testSessions = dbQuery($sql, [$userId]);
    $testSessions = array_map('prepareSessionData', $testSessions);

    successResponse([
        'approvedCategories' => $approvedCategoryIds,
        'requestedCategories' => $requestedCategories,
        'requestedPackages' => $requestedPackages,
        'categoryExpiry' => $categoryExpiry,
        'testSessions' => $testSessions
    ]);
}

/**
 * Request access to categories or packages
 * @param array $data Request data
 * @param string $token Session token
 */
function action_request_access($data, $token) {
    $user = requireAuth($token);
    $userId = $user['id'];

    $categoryIds = $data['category_ids'] ?? [];
    $packageIds = $data['package_ids'] ?? [];

    if (empty($categoryIds) && empty($packageIds)) {
        throw new Exception('No categories or packages selected', 400);
    }

    // Insert access requests for categories
    foreach ($categoryIds as $categoryId) {
        // Check if already requested or approved
        $sql = "SELECT COUNT(*) as count FROM access_requests
                WHERE user_id = ? AND category_id = ? AND status = 'PENDING'";
        $result = dbQuerySingle($sql, [$userId, $categoryId]);

        if ($result['count'] == 0) {
            // Check if already has access
            $sql = "SELECT COUNT(*) as count FROM user_categories
                    WHERE user_id = ? AND category_id = ? AND expires_at > NOW()";
            $result = dbQuerySingle($sql, [$userId, $categoryId]);

            if ($result['count'] == 0) {
                // Insert request
                $sql = "INSERT INTO access_requests (user_id, category_id, status)
                        VALUES (?, ?, 'PENDING')";
                dbExecute($sql, [$userId, $categoryId]);
            }
        }
    }

    // Insert access requests for packages
    foreach ($packageIds as $packageId) {
        // Check if already requested
        $sql = "SELECT COUNT(*) as count FROM access_requests
                WHERE user_id = ? AND package_id = ? AND status = 'PENDING'";
        $result = dbQuerySingle($sql, [$userId, $packageId]);

        if ($result['count'] == 0) {
            $sql = "INSERT INTO access_requests (user_id, package_id, status)
                    VALUES (?, ?, 'PENDING')";
            dbExecute($sql, [$userId, $packageId]);
        }
    }

    successResponse(null, 'Access request submitted successfully');
}

?>
