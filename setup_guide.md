
# Maritime Portal Setup Guide

## 1. Deploy Backend (Etnhost / cPanel)

1.  **Upload `api.php`**:
    *   Open cPanel File Manager -> `public_html`.
    *   Upload the `api.php` file from this project.
    *   **Edit `api.php`** and set your `$db_name`, `$username`, and `$password` at the top.

2.  **Upload Images**:
    *   Create a folder `images` inside `public_html`.
    *   Upload all question images (e.g., `nav1.png`) there.

3.  **Setup Database**:
    *   Open cPanel -> **phpMyAdmin**.
    *   Select your database.
    *   Click **SQL** tab.
    *   Paste the content of `schema.sql` and run it.

## 2. Migrate Old Tests (From existing DB)

To copy questions from your old table to the new `questions` table:

1.  Identify the **Category ID** you want to import (e.g., `cat-0` for Navigation). You can find these by creating the categories in the Admin Panel first and checking their IDs, or checking `constants.ts`.
2.  Run the following SQL in phpMyAdmin (change values as needed):

```sql
INSERT INTO questions (category_id, question_text, option_a, option_b, option_c, option_d, correct_answer, image_file, original_index)
SELECT 
    'cat-0',                  -- TARGET CATEGORY ID (Change this for each category!)
    your_old_question_col,    -- Old column name for Question
    your_old_a_col,           -- Old column for Answer A
    your_old_b_col,           -- Old column for Answer B
    your_old_c_col,           -- Old column for Answer C
    your_old_d_col,           -- Old column for Answer D
    your_old_correct_col,     -- Old column for Correct ('A','B','C','D')
    your_old_image_col,       -- Old column for Image
    your_old_id_col           -- Old ID (Important for the 25% logic!)
FROM your_old_table_name      -- Old table name
WHERE your_old_category_col = 'Navigation' -- Filter for specific category
```

3.  Repeat for each category, changing `'cat-0'` to `'cat-1'`, etc.

4.  **Update Question Counts**:
    After importing, run this command to update the visible counts in the app:
    ```sql
    UPDATE categories c
    SET question_count = (SELECT COUNT(*) FROM questions q WHERE q.category_id = c.id);
    ```

## 3. Connect App

1.  Open `services/storageService.ts`.
2.  Change `API_URL` to your full domain:
    ```typescript
    const API_URL = 'https://your-site.com/api.php';
    ```
3.  Build the app (`npm run build`) and upload the build files to `public_html`.
