<?php
/**
 * Admin API Actions
 * Maritime Exam Portal
 *
 * Handles admin-only operations
 */

// Prevent direct access
if (!defined('API_ACCESS')) {
    http_response_code(403);
    die('Direct access not permitted');
}

/**
 * Get all admin data (users, requests, categories, packages)
 * @param array $data Request data
 * @param string $token Session token
 */
function action_get_admin_data($data, $token) {
    $admin = requireAdmin($token);

    // Get all users
    $sql = "SELECT id, email, first_name, last_name, role, is_suspended, created_at, last_login
            FROM users
            ORDER BY created_at DESC";
    $users = dbQuery($sql);
    $users = array_map('prepareUserData', $users);

    // Get pending requests with user and category/package info
    $sql = "SELECT * FROM v_pending_requests";
    $pendingRequests = dbQuery($sql);

    // Get all categories
    $sql = "SELECT * FROM categories ORDER BY name_en";
    $categories = dbQuery($sql);
    $categories = array_map('prepareCategoryData', $categories);

    // Get all packages with category IDs
    $sql = "SELECT * FROM packages ORDER BY name_en";
    $packages = dbQuery($sql);

    foreach ($packages as &$package) {
        $sql = "SELECT category_id FROM package_categories WHERE package_id = ?";
        $catRows = dbQuery($sql, [$package['id']]);
        $categoryIds = array_column($catRows, 'category_id');
        $package = preparePackageData($package, $categoryIds);
    }

    // Get all settings
    $settings = getAllSettings();

    successResponse([
        'users' => $users,
        'pendingRequests' => $pendingRequests,
        'categories' => $categories,
        'packages' => $packages,
        'settings' => $settings
    ]);
}

/**
 * Approve access request
 * @param array $data Request data
 * @param string $token Session token
 */
function action_approve_request($data, $token) {
    $admin = requireAdmin($token);

    validateRequiredFields($data, ['request_id', 'duration_days']);

    $requestId = (int)$data['request_id'];
    $durationDays = (int)$data['duration_days'];

    // Get request details
    $sql = "SELECT * FROM access_requests WHERE id = ?";
    $request = dbQuerySingle($sql, [$requestId]);

    if (!$request) {
        throw new Exception('Request not found', 404);
    }

    if ($request['status'] !== 'PENDING') {
        throw new Exception('Request has already been processed', 400);
    }

    // Calculate expiry date
    $expiresAt = addDays($durationDays);

    dbBeginTransaction();

    try {
        // Update request status
        $sql = "UPDATE access_requests
                SET status = 'APPROVED', processed_at = NOW(), processed_by = ?
                WHERE id = ?";
        dbExecute($sql, [$admin['id'], $requestId]);

        // Grant access based on request type
        if ($request['category_id']) {
            // Single category request
            $sql = "INSERT INTO user_categories (user_id, category_id, granted_at, expires_at, granted_by)
                    VALUES (?, ?, NOW(), ?, ?)
                    ON DUPLICATE KEY UPDATE expires_at = ?, granted_by = ?";

            dbExecute($sql, [
                $request['user_id'],
                $request['category_id'],
                $expiresAt,
                $admin['id'],
                $expiresAt,
                $admin['id']
            ]);
        } elseif ($request['package_id']) {
            // Package request - grant all categories in package
            $sql = "SELECT category_id FROM package_categories WHERE package_id = ?";
            $categories = dbQuery($sql, [$request['package_id']]);

            foreach ($categories as $cat) {
                $sql = "INSERT INTO user_categories (user_id, category_id, granted_at, expires_at, granted_by)
                        VALUES (?, ?, NOW(), ?, ?)
                        ON DUPLICATE KEY UPDATE expires_at = ?, granted_by = ?";

                dbExecute($sql, [
                    $request['user_id'],
                    $cat['category_id'],
                    $expiresAt,
                    $admin['id'],
                    $expiresAt,
                    $admin['id']
                ]);
            }
        }

        dbCommit();

        // Log audit
        logAudit($admin['id'], 'APPROVE_REQUEST', 'access_request', $requestId, [
            'user_id' => $request['user_id'],
            'category_id' => $request['category_id'],
            'package_id' => $request['package_id'],
            'duration_days' => $durationDays
        ]);

        successResponse(null, 'Request approved successfully');

    } catch (Exception $e) {
        dbRollback();
        throw $e;
    }
}

/**
 * Reject access request
 * @param array $data Request data
 * @param string $token Session token
 */
function action_reject_request($data, $token) {
    $admin = requireAdmin($token);

    validateRequiredFields($data, ['request_id']);

    $requestId = (int)$data['request_id'];
    $notes = $data['notes'] ?? '';

    $sql = "UPDATE access_requests
            SET status = 'REJECTED', processed_at = NOW(), processed_by = ?, notes = ?
            WHERE id = ? AND status = 'PENDING'";

    $affected = dbExecute($sql, [$admin['id'], $notes, $requestId]);

    if ($affected === 0) {
        throw new Exception('Request not found or already processed', 404);
    }

    logAudit($admin['id'], 'REJECT_REQUEST', 'access_request', $requestId, ['notes' => $notes]);

    successResponse(null, 'Request rejected');
}

/**
 * Toggle user suspension
 * @param array $data Request data
 * @param string $token Session token
 */
function action_toggle_suspend_user($data, $token) {
    $admin = requireAdmin($token);

    validateRequiredFields($data, ['user_id', 'suspend']);

    $userId = (int)$data['user_id'];
    $suspend = (bool)$data['suspend'];

    // Prevent admin from suspending themselves
    if ($userId === $admin['id']) {
        throw new Exception('You cannot suspend your own account', 400);
    }

    $sql = "UPDATE users SET is_suspended = ? WHERE id = ?";
    dbExecute($sql, [$suspend ? 1 : 0, $userId]);

    logAudit($admin['id'], $suspend ? 'SUSPEND_USER' : 'ACTIVATE_USER', 'user', $userId);

    successResponse(null, $suspend ? 'User suspended' : 'User activated');
}

/**
 * Save category (create or update)
 * @param array $data Request data
 * @param string $token Session token
 */
function action_save_category($data, $token) {
    $admin = requireAdmin($token);

    validateRequiredFields($data, ['name_bg', 'name_en', 'price', 'duration_days', 'exam_duration_minutes']);

    $id = isset($data['id']) ? (int)$data['id'] : null;
    $nameBg = sanitizeInput($data['name_bg']);
    $nameEn = sanitizeInput($data['name_en']);
    $price = (float)$data['price'];
    $durationDays = (int)$data['duration_days'];
    $examDurationMinutes = (int)$data['exam_duration_minutes'];
    $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : true;

    if ($id) {
        // Update existing category
        $sql = "UPDATE categories
                SET name_bg = ?, name_en = ?, price = ?, duration_days = ?,
                    exam_duration_minutes = ?, is_active = ?
                WHERE id = ?";

        dbExecute($sql, [$nameBg, $nameEn, $price, $durationDays, $examDurationMinutes, $isActive ? 1 : 0, $id]);

        logAudit($admin['id'], 'UPDATE_CATEGORY', 'category', $id);

        successResponse(['id' => $id], 'Category updated');

    } else {
        // Create new category
        $sql = "INSERT INTO categories (name_bg, name_en, price, duration_days, exam_duration_minutes, is_active)
                VALUES (?, ?, ?, ?, ?, ?)";

        $newId = dbInsert($sql, [$nameBg, $nameEn, $price, $durationDays, $examDurationMinutes, $isActive ? 1 : 0]);

        logAudit($admin['id'], 'CREATE_CATEGORY', 'category', $newId);

        successResponse(['id' => $newId], 'Category created');
    }
}

/**
 * Save package (create or update)
 * @param array $data Request data
 * @param string $token Session token
 */
function action_save_package($data, $token) {
    $admin = requireAdmin($token);

    validateRequiredFields($data, ['name_bg', 'name_en', 'price', 'duration_days', 'category_ids']);

    $id = isset($data['id']) ? (int)$data['id'] : null;
    $nameBg = sanitizeInput($data['name_bg']);
    $nameEn = sanitizeInput($data['name_en']);
    $descriptionBg = sanitizeInput($data['description_bg'] ?? '');
    $descriptionEn = sanitizeInput($data['description_en'] ?? '');
    $price = (float)$data['price'];
    $durationDays = (int)$data['duration_days'];
    $categoryIds = $data['category_ids'];
    $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : true;

    if (empty($categoryIds) || !is_array($categoryIds)) {
        throw new Exception('At least one category must be selected', 400);
    }

    dbBeginTransaction();

    try {
        if ($id) {
            // Update existing package
            $sql = "UPDATE packages
                    SET name_bg = ?, name_en = ?, description_bg = ?, description_en = ?,
                        price = ?, duration_days = ?, is_active = ?
                    WHERE id = ?";

            dbExecute($sql, [$nameBg, $nameEn, $descriptionBg, $descriptionEn, $price, $durationDays, $isActive ? 1 : 0, $id]);

            // Delete old category associations
            $sql = "DELETE FROM package_categories WHERE package_id = ?";
            dbExecute($sql, [$id]);

            $packageId = $id;

        } else {
            // Create new package
            $sql = "INSERT INTO packages (name_bg, name_en, description_bg, description_en, price, duration_days, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

            $packageId = dbInsert($sql, [$nameBg, $nameEn, $descriptionBg, $descriptionEn, $price, $durationDays, $isActive ? 1 : 0]);
        }

        // Insert category associations
        foreach ($categoryIds as $categoryId) {
            $sql = "INSERT INTO package_categories (package_id, category_id) VALUES (?, ?)";
            dbExecute($sql, [$packageId, (int)$categoryId]);
        }

        dbCommit();

        logAudit($admin['id'], $id ? 'UPDATE_PACKAGE' : 'CREATE_PACKAGE', 'package', $packageId);

        successResponse(['id' => $packageId], $id ? 'Package updated' : 'Package created');

    } catch (Exception $e) {
        dbRollback();
        throw $e;
    }
}

/**
 * Import questions from Excel data
 * @param array $data Request data
 * @param string $token Session token
 */
function action_import_questions($data, $token) {
    $admin = requireAdmin($token);

    validateRequiredFields($data, ['category_id', 'questions']);

    $categoryId = (int)$data['category_id'];
    $questions = $data['questions'];
    $replaceExisting = isset($data['replace_existing']) ? (bool)$data['replace_existing'] : true;

    if (!is_array($questions) || empty($questions)) {
        throw new Exception('No questions provided', 400);
    }

    // Verify category exists
    $sql = "SELECT * FROM categories WHERE id = ?";
    $category = dbQuerySingle($sql, [$categoryId]);

    if (!$category) {
        throw new Exception('Category not found', 404);
    }

    dbBeginTransaction();

    try {
        // Delete existing questions if replacing
        if ($replaceExisting) {
            $sql = "DELETE FROM questions WHERE category_id = ?";
            dbExecute($sql, [$categoryId]);
        }

        // Insert new questions
        $importedCount = 0;

        foreach ($questions as $q) {
            // Validate question data
            if (empty($q['question_text']) || empty($q['option_a']) || empty($q['option_b']) ||
                empty($q['option_c']) || empty($q['option_d']) || empty($q['correct_answer'])) {
                continue; // Skip invalid questions
            }

            $sql = "INSERT INTO questions
                    (category_id, original_index, question_text, option_a, option_b, option_c, option_d, correct_answer, image_filename)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            dbExecute($sql, [
                $categoryId,
                (int)($q['original_index'] ?? $importedCount + 1),
                $q['question_text'],
                $q['option_a'],
                $q['option_b'],
                $q['option_c'],
                $q['option_d'],
                strtoupper($q['correct_answer']),
                $q['image_filename'] ?? null
            ]);

            $importedCount++;
        }

        // Update category question count
        $sql = "UPDATE categories SET question_count = ? WHERE id = ?";
        dbExecute($sql, [$importedCount, $categoryId]);

        dbCommit();

        logAudit($admin['id'], 'IMPORT_QUESTIONS', 'category', $categoryId, ['count' => $importedCount]);

        successResponse(['imported_count' => $importedCount], "$importedCount questions imported successfully");

    } catch (Exception $e) {
        dbRollback();
        throw $e;
    }
}

/**
 * Save global settings
 * @param array $data Request data
 * @param string $token Session token
 */
function action_save_settings($data, $token) {
    $admin = requireAdmin($token);

    $settings = $data['settings'] ?? [];

    if (empty($settings)) {
        throw new Exception('No settings provided', 400);
    }

    foreach ($settings as $key => $value) {
        setSetting($key, $value);
    }

    logAudit($admin['id'], 'UPDATE_SETTINGS', null, null, $settings);

    successResponse(null, 'Settings saved successfully');
}

/**
 * Change user password (admin only)
 * @param array $data Request data
 * @param string $token Session token
 */
function action_change_user_password($data, $token) {
    $admin = requireAdmin($token);

    validateRequiredFields($data, ['user_id', 'new_password']);

    $userId = (int)$data['user_id'];
    $newPassword = $data['new_password'];

    // Validate password
    $passwordCheck = validatePassword($newPassword);
    if (!$passwordCheck['valid']) {
        throw new Exception($passwordCheck['message'], 400);
    }

    // Hash new password
    $passwordHash = hashPassword($newPassword);

    $sql = "UPDATE users SET password_hash = ? WHERE id = ?";
    dbExecute($sql, [$passwordHash, $userId]);

    logAudit($admin['id'], 'CHANGE_PASSWORD', 'user', $userId);

    successResponse(null, 'Password changed successfully');
}

/**
 * Delete category (admin only)
 * @param array $data Request data
 * @param string $token Session token
 */
function action_delete_category($data, $token) {
    $admin = requireAdmin($token);

    validateRequiredFields($data, ['category_id']);

    $categoryId = (int)$data['category_id'];

    // Check if category has users or tests
    $sql = "SELECT COUNT(*) as count FROM user_categories WHERE category_id = ?";
    $result = dbQuerySingle($sql, [$categoryId]);

    if ($result['count'] > 0) {
        throw new Exception('Cannot delete category with active users. Deactivate it instead.', 400);
    }

    $sql = "DELETE FROM categories WHERE id = ?";
    dbExecute($sql, [$categoryId]);

    logAudit($admin['id'], 'DELETE_CATEGORY', 'category', $categoryId);

    successResponse(null, 'Category deleted');
}

/**
 * Delete package (admin only)
 * @param array $data Request data
 * @param string $token Session token
 */
function action_delete_package($data, $token) {
    $admin = requireAdmin($token);

    validateRequiredFields($data, ['package_id']);

    $packageId = (int)$data['package_id'];

    $sql = "DELETE FROM packages WHERE id = ?";
    dbExecute($sql, [$packageId]);

    logAudit($admin['id'], 'DELETE_PACKAGE', 'package', $packageId);

    successResponse(null, 'Package deleted');
}

?>
