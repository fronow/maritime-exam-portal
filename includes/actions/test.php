<?php
/**
 * Test API Actions
 * Maritime Exam Portal
 *
 * Handles test generation, answers, and completion
 */

// Prevent direct access
if (!defined('API_ACCESS')) {
    http_response_code(403);
    die('Direct access not permitted');
}

/**
 * Generate test with 60 questions using 25% distribution algorithm
 * @param array $data Request data
 * @param string $token Session token
 */
function action_generate_test($data, $token) {
    $user = requireAuth($token);
    $userId = $user['id'];

    validateRequiredFields($data, ['category_id']);
    $categoryId = (int)$data['category_id'];

    // Check if user has access to this category
    $sql = "SELECT * FROM user_categories
            WHERE user_id = ? AND category_id = ? AND expires_at > NOW()";

    $access = dbQuerySingle($sql, [$userId, $categoryId]);

    if (!$access) {
        throw new Exception('You do not have access to this category or your access has expired', 403);
    }

    // Get category details
    $category = getCategoryById($categoryId);

    if (!$category) {
        throw new Exception('Category not found', 404);
    }

    // Get all questions for this category, sorted by original_index
    $allQuestions = getQuestionsForCategory($categoryId);

    if (count($allQuestions) < 60) {
        throw new Exception('Not enough questions in this category to generate a test', 400);
    }

    // Apply 25% distribution algorithm
    $selectedQuestions = generate25PercentDistribution($allQuestions, 60);

    // Create test session
    $questionIds = array_column($selectedQuestions, 'id');
    $questionsDataJson = json_encode($questionIds);

    $sql = "INSERT INTO test_sessions (user_id, category_id, start_time, total_questions, questions_data)
            VALUES (?, ?, NOW(), 60, ?)";

    $sessionId = dbInsert($sql, [$userId, $categoryId, $questionsDataJson]);

    // Insert empty answers for all questions
    foreach ($questionIds as $questionId) {
        $sql = "INSERT INTO test_answers (session_id, question_id)
                VALUES (?, ?)";
        dbExecute($sql, [$sessionId, $questionId]);
    }

    // Prepare questions for response (without correct answers)
    $questions = [];
    foreach ($selectedQuestions as $q) {
        $questions[] = prepareQuestionData($q, false);
    }

    successResponse([
        'session_id' => $sessionId,
        'questions' => $questions,
        'start_time' => formatDate(date('Y-m-d H:i:s')),
        'duration_minutes' => (int)$category['exam_duration_minutes']
    ]);
}

/**
 * Submit answer for a question during test
 * @param array $data Request data
 * @param string $token Session token
 */
function action_submit_answer($data, $token) {
    $user = requireAuth($token);
    $userId = $user['id'];

    validateRequiredFields($data, ['session_id', 'question_id', 'selected_answer']);

    $sessionId = (int)$data['session_id'];
    $questionId = (int)$data['question_id'];
    $selectedAnswer = strtoupper($data['selected_answer']);

    // Validate answer format
    if (!in_array($selectedAnswer, ['A', 'B', 'C', 'D'])) {
        throw new Exception('Invalid answer format', 400);
    }

    // Verify session belongs to user
    $sql = "SELECT * FROM test_sessions WHERE id = ? AND user_id = ?";
    $session = dbQuerySingle($sql, [$sessionId, $userId]);

    if (!$session) {
        throw new Exception('Test session not found', 404);
    }

    // Check if test is already completed
    if ($session['is_completed']) {
        throw new Exception('Test has already been completed', 400);
    }

    // Update answer
    $sql = "UPDATE test_answers
            SET selected_answer = ?, answered_at = NOW()
            WHERE session_id = ? AND question_id = ?";

    dbExecute($sql, [$selectedAnswer, $sessionId, $questionId]);

    successResponse(null, 'Answer saved');
}

/**
 * Complete test and calculate score
 * @param array $data Request data
 * @param string $token Session token
 */
function action_complete_test($data, $token) {
    $user = requireAuth($token);
    $userId = $user['id'];

    validateRequiredFields($data, ['session_id']);
    $sessionId = (int)$data['session_id'];

    // Verify session belongs to user
    $sql = "SELECT * FROM test_sessions WHERE id = ? AND user_id = ?";
    $session = dbQuerySingle($sql, [$sessionId, $userId]);

    if (!$session) {
        throw new Exception('Test session not found', 404);
    }

    // Check if already completed
    if ($session['is_completed']) {
        throw new Exception('Test has already been completed', 400);
    }

    // Get all answers with correct answers from questions
    $sql = "SELECT ta.question_id, ta.selected_answer, q.correct_answer
            FROM test_answers ta
            JOIN questions q ON ta.question_id = q.id
            WHERE ta.session_id = ?";

    $answers = dbQuery($sql, [$sessionId]);

    // Calculate score
    $totalQuestions = count($answers);
    $correctCount = 0;
    $answersWithCorrectness = [];

    foreach ($answers as $answer) {
        $isCorrect = ($answer['selected_answer'] === $answer['correct_answer']);

        if ($isCorrect) {
            $correctCount++;
        }

        // Update is_correct in database
        $sql = "UPDATE test_answers
                SET is_correct = ?
                WHERE session_id = ? AND question_id = ?";

        dbExecute($sql, [$isCorrect ? 1 : 0, $sessionId, $answer['question_id']]);

        $answersWithCorrectness[] = [
            'questionId' => (int)$answer['question_id'],
            'selectedAnswer' => $answer['selected_answer'],
            'correctAnswer' => $answer['correct_answer'],
            'isCorrect' => $isCorrect
        ];
    }

    // Calculate percentage and grade
    $percentage = ($correctCount / $totalQuestions) * 100;
    $grade = calculateGrade($percentage);

    // Calculate duration
    $startTime = new DateTime($session['start_time']);
    $endTime = new DateTime();
    $durationSeconds = $endTime->getTimestamp() - $startTime->getTimestamp();

    // Update test session
    $sql = "UPDATE test_sessions
            SET is_completed = TRUE,
                end_time = NOW(),
                score = ?,
                percentage = ?,
                grade = ?,
                duration_seconds = ?
            WHERE id = ?";

    dbExecute($sql, [$correctCount, $percentage, $grade, $durationSeconds, $sessionId]);

    // Return results
    successResponse([
        'score' => $correctCount,
        'total_questions' => $totalQuestions,
        'percentage' => round($percentage, 2),
        'grade' => $grade,
        'duration_seconds' => $durationSeconds,
        'answers' => $answersWithCorrectness
    ]);
}

/**
 * Get active test session (if user has an incomplete test)
 * @param array $data Request data
 * @param string $token Session token
 */
function action_get_active_session($data, $token) {
    $user = requireAuth($token);
    $userId = $user['id'];

    validateRequiredFields($data, ['category_id']);
    $categoryId = (int)$data['category_id'];

    // Get active session for this category
    $sql = "SELECT * FROM test_sessions
            WHERE user_id = ? AND category_id = ? AND is_completed = FALSE
            ORDER BY start_time DESC
            LIMIT 1";

    $session = dbQuerySingle($sql, [$userId, $categoryId]);

    if (!$session) {
        successResponse(['hasActiveSession' => false]);
        return;
    }

    // Get questions for this session
    $questionIds = json_decode($session['questions_data'], true);

    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
    $sql = "SELECT * FROM questions WHERE id IN ($placeholders)";
    $questions = dbQuery($sql, $questionIds);

    // Maintain order from questions_data
    $questionsOrdered = [];
    foreach ($questionIds as $qId) {
        foreach ($questions as $q) {
            if ($q['id'] == $qId) {
                $questionsOrdered[] = prepareQuestionData($q, false);
                break;
            }
        }
    }

    // Get saved answers
    $sql = "SELECT question_id, selected_answer FROM test_answers
            WHERE session_id = ?";
    $answers = dbQuery($sql, [$session['id']]);

    $answersMap = [];
    foreach ($answers as $ans) {
        if ($ans['selected_answer']) {
            $answersMap[(string)$ans['question_id']] = $ans['selected_answer'];
        }
    }

    successResponse([
        'hasActiveSession' => true,
        'sessionId' => (int)$session['id'],
        'questions' => $questionsOrdered,
        'answers' => $answersMap,
        'startTime' => formatDate($session['start_time'])
    ]);
}

?>
