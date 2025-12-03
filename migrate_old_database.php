<?php
/**
 * Database Migration Script
 * Converts old database structure to new backend structure
 *
 * OLD Structure: questions + question_answer_choices (separate tables)
 * NEW Structure: questions with all options in one row
 */

// Old database connection (source)
define('OLD_DB_HOST', 'localhost');
define('OLD_DB_NAME', 'morskiiz_dfrnw');  // Your old database
define('OLD_DB_USER', 'morskiiz_maritime_user');
define('OLD_DB_PASS', 'YOUR_PASSWORD');

// New database connection (destination)
define('NEW_DB_HOST', 'localhost');
define('NEW_DB_NAME', 'morskiiz_maritime');  // Your new database
define('NEW_DB_USER', 'morskiiz_maritime_user');
define('NEW_DB_PASS', 'YOUR_PASSWORD');

try {
    // Connect to OLD database
    $oldDb = new PDO(
        "mysql:host=" . OLD_DB_HOST . ";dbname=" . OLD_DB_NAME . ";charset=utf8mb4",
        OLD_DB_USER,
        OLD_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Connect to NEW database
    $newDb = new PDO(
        "mysql:host=" . NEW_DB_HOST . ";dbname=" . NEW_DB_NAME . ";charset=utf8mb4",
        NEW_DB_USER,
        NEW_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "<h1>Database Migration - Old to New Structure</h1>";
    echo "<hr>";

    // Step 1: Migrate Categories
    echo "<h2>Step 1: Migrating Categories...</h2>";

    $oldCategories = $oldDb->query("SELECT * FROM question_categories")->fetchAll();
    $categoryMap = []; // Map old ID to new ID

    foreach ($oldCategories as $oldCat) {
        // Check if category already exists in new DB
        $stmt = $newDb->prepare("SELECT id FROM categories WHERE name_bg = ? OR name_en = ?");
        $stmt->execute([$oldCat['category'] ?? '', $oldCat['category'] ?? '']);
        $existing = $stmt->fetch();

        if ($existing) {
            $categoryMap[$oldCat['id']] = $existing['id'];
            echo "Category already exists: {$oldCat['category']} (ID: {$existing['id']})<br>";
        } else {
            // Create new category
            $stmt = $newDb->prepare("
                INSERT INTO categories (name_bg, name_en, price, duration_days, exam_duration_minutes)
                VALUES (?, ?, 25.00, 365, 60)
            ");
            $stmt->execute([
                $oldCat['category'] ?? 'Unknown Category',
                $oldCat['category'] ?? 'Unknown Category'
            ]);
            $newId = $newDb->lastInsertId();
            $categoryMap[$oldCat['id']] = $newId;
            echo "Created category: {$oldCat['category']} (New ID: {$newId})<br>";
        }
    }

    echo "<p><strong>✅ Categories migrated: " . count($categoryMap) . "</strong></p>";
    echo "<hr>";

    // Step 2: Migrate Questions
    echo "<h2>Step 2: Migrating Questions...</h2>";

    $oldQuestions = $oldDb->query("SELECT * FROM questions ORDER BY id")->fetchAll();
    $migratedCount = 0;
    $errors = 0;

    foreach ($oldQuestions as $idx => $oldQ) {
        try {
            $questionId = $oldQ['id'];
            $categoryId = $oldQ['question_category_id'];

            // Get new category ID
            if (!isset($categoryMap[$categoryId])) {
                echo "⚠️ Question {$questionId}: Category {$categoryId} not found. Skipping...<br>";
                $errors++;
                continue;
            }
            $newCategoryId = $categoryMap[$categoryId];

            // Get answer choices
            $stmt = $oldDb->prepare("SELECT * FROM question_answer_choices WHERE question_id = ? ORDER BY id");
            $stmt->execute([$questionId]);
            $choices = $stmt->fetchAll();

            if (count($choices) < 4) {
                echo "⚠️ Question {$questionId}: Less than 4 answers. Skipping...<br>";
                $errors++;
                continue;
            }

            // Map choices to A, B, C, D
            $options = ['A' => '', 'B' => '', 'C' => '', 'D' => ''];
            $correctAnswer = null;
            $letters = ['A', 'B', 'C', 'D'];

            for ($i = 0; $i < min(4, count($choices)); $i++) {
                $options[$letters[$i]] = $choices[$i]['choice'];
                if ($choices[$i]['is_correct'] == 1) {
                    $correctAnswer = $letters[$i];
                }
            }

            if (!$correctAnswer) {
                echo "⚠️ Question {$questionId}: No correct answer marked. Using A as default.<br>";
                $correctAnswer = 'A';
            }

            // Insert into new database
            $stmt = $newDb->prepare("
                INSERT INTO questions
                (category_id, original_index, question_text, option_a, option_b, option_c, option_d, correct_answer, image_filename)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $newCategoryId,
                $idx + 1, // original_index for 25% distribution
                $oldQ['question'],
                $options['A'],
                $options['B'],
                $options['C'],
                $options['D'],
                $correctAnswer,
                $oldQ['question_image'] ?? null
            ]);

            $migratedCount++;

            // Show progress every 100 questions
            if ($migratedCount % 100 == 0) {
                echo "Migrated {$migratedCount} questions...<br>";
                flush();
            }

        } catch (Exception $e) {
            echo "❌ Error migrating question {$questionId}: " . $e->getMessage() . "<br>";
            $errors++;
        }
    }

    echo "<p><strong>✅ Questions migrated: {$migratedCount}</strong></p>";
    if ($errors > 0) {
        echo "<p><strong>⚠️ Errors: {$errors}</strong></p>";
    }
    echo "<hr>";

    // Step 3: Update question counts
    echo "<h2>Step 3: Updating question counts...</h2>";

    foreach ($categoryMap as $oldId => $newId) {
        $stmt = $newDb->prepare("SELECT COUNT(*) as count FROM questions WHERE category_id = ?");
        $stmt->execute([$newId]);
        $count = $stmt->fetch()['count'];

        $stmt = $newDb->prepare("UPDATE categories SET question_count = ? WHERE id = ?");
        $stmt->execute([$count, $newId]);

        echo "Category {$newId}: {$count} questions<br>";
    }

    echo "<hr>";
    echo "<h2>✅ Migration Complete!</h2>";
    echo "<p><strong>Total questions migrated: {$migratedCount}</strong></p>";
    echo "<p><strong>Total categories: " . count($categoryMap) . "</strong></p>";

    // Verification
    echo "<hr>";
    echo "<h3>Verification:</h3>";
    $total = $newDb->query("SELECT COUNT(*) as count FROM questions")->fetch()['count'];
    echo "Total questions in new database: {$total}<br>";

    $categories = $newDb->query("SELECT id, name_en, question_count FROM categories")->fetchAll();
    echo "<table border='1' style='margin-top:10px;'>";
    echo "<tr><th>ID</th><th>Category</th><th>Questions</th></tr>";
    foreach ($categories as $cat) {
        echo "<tr><td>{$cat['id']}</td><td>{$cat['name_en']}</td><td>{$cat['question_count']}</td></tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "<h2>❌ Migration Failed</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

?>
