<?php
/**
 * Database Compatibility Layer
 * Maritime Exam Portal
 *
 * Maps old database structure to new backend expectations
 */

// Prevent direct access
if (!defined('API_ACCESS')) {
    http_response_code(403);
    die('Direct access not permitted');
}

/**
 * Get all categories with proper field mapping
 */
function getCategories() {
    $sql = "SELECT
                id,
                name_bg,
                name_en,
                category as name_bg_fallback,
                price,
                duration_days,
                exam_duration_minutes,
                question_count,
                created_at,
                updated_at
            FROM question_categories
            ORDER BY id";

    $categories = dbQuery($sql, []);

    // Map to expected format
    foreach ($categories as &$cat) {
        // If name_bg is empty, use category field
        if (empty($cat['name_bg'])) {
            $cat['name_bg'] = $cat['name_bg_fallback'] ?? '';
        }
        if (empty($cat['name_en'])) {
            $cat['name_en'] = $cat['name_bg_fallback'] ?? '';
        }
        unset($cat['name_bg_fallback']);
    }

    return $categories;
}

/**
 * Get single category by ID
 */
function getCategoryById($categoryId) {
    $sql = "SELECT
                id,
                name_bg,
                name_en,
                category as name_bg_fallback,
                price,
                duration_days,
                exam_duration_minutes,
                question_count
            FROM question_categories
            WHERE id = ?";

    $cat = dbQuerySingle($sql, [$categoryId]);

    if ($cat) {
        // If name_bg is empty, use category field
        if (empty($cat['name_bg'])) {
            $cat['name_bg'] = $cat['name_bg_fallback'] ?? '';
        }
        if (empty($cat['name_en'])) {
            $cat['name_en'] = $cat['name_bg_fallback'] ?? '';
        }
        unset($cat['name_bg_fallback']);
    }

    return $cat;
}

/**
 * Get questions for a category with options
 */
function getQuestionsForCategory($categoryId) {
    $sql = "SELECT
                id,
                question_category_id as category_id,
                question as question_text,
                question_image as image_filename,
                option_a,
                option_b,
                option_c,
                option_d,
                correct_answer,
                original_index,
                created_at,
                updated_at
            FROM questions
            WHERE question_category_id = ?
            ORDER BY original_index";

    return dbQuery($sql, [$categoryId]);
}

/**
 * Get single question by ID with options
 */
function getQuestionById($questionId) {
    $sql = "SELECT
                id,
                question_category_id as category_id,
                question as question_text,
                question_image as image_filename,
                option_a,
                option_b,
                option_c,
                option_d,
                correct_answer,
                original_index
            FROM questions
            WHERE id = ?";

    return dbQuerySingle($sql, [$questionId]);
}

/**
 * Prepare question data for API response
 * @param array $question Question data from database
 * @param bool $includeCorrectAnswer Whether to include correct answer (false for active tests)
 */
function prepareQuestionData($question, $includeCorrectAnswer = false) {
    $data = [
        'id' => (int)$question['id'],
        'categoryId' => (int)($question['category_id'] ?? $question['question_category_id']),
        'text' => $question['question_text'] ?? $question['question'],
        'optionA' => $question['option_a'] ?? '',
        'optionB' => $question['option_b'] ?? '',
        'optionC' => $question['option_c'] ?? '',
        'optionD' => $question['option_d'] ?? '',
        'imageFilename' => $question['image_filename'] ?? $question['question_image'] ?? null,
        'originalIndex' => (int)($question['original_index'] ?? 0)
    ];

    if ($includeCorrectAnswer) {
        $data['correctAnswer'] = $question['correct_answer'];
    }

    return $data;
}

?>
