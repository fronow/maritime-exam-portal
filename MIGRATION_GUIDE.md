# 📦 Database Migration Guide

This guide will help you migrate your existing database (`morskiiz_dfrnw`) to the new backend structure.

---

## 🎯 What This Migration Does

**Converts:**
- **OLD Structure:** `questions` + `question_answer_choices` (separate tables, 4 rows per question)
- **NEW Structure:** `questions` with all options in one row (option_a, option_b, option_c, option_d)

**Preserves:**
- All question text and images
- Categories (maps old IDs to new IDs)
- Correct answer identification
- Original question order (for 25% distribution algorithm)

---

## ⚠️ Before You Start

### Prerequisites

1. ✅ You have **TWO databases**:
   - Old database: `morskiiz_dfrnw` (your current production database)
   - New database: `morskiiz_maritime` (created from schema.sql)

2. ✅ Both databases are imported into cPanel phpMyAdmin

3. ✅ You have the database user credentials

---

## 📋 Step-by-Step Migration

### Step 1: Configure Database Credentials

Edit `migrate_old_database.php` and update these lines:

```php
// Lines 11-14: OLD database connection (source)
define('OLD_DB_HOST', 'localhost');
define('OLD_DB_NAME', 'morskiiz_dfrnw');  // Your old database
define('OLD_DB_USER', 'morskiiz_maritime_user');
define('OLD_DB_PASS', 'YOUR_ACTUAL_PASSWORD');  // ⚠️ CHANGE THIS!

// Lines 17-20: NEW database connection (destination)
define('NEW_DB_HOST', 'localhost');
define('NEW_DB_NAME', 'morskiiz_maritime');  // Your new database
define('NEW_DB_USER', 'morskiiz_maritime_user');
define('NEW_DB_PASS', 'YOUR_ACTUAL_PASSWORD');  // ⚠️ CHANGE THIS!
```

**IMPORTANT:** Replace `YOUR_PASSWORD` with your actual database password on both sections!

---

### Step 2: Upload Migration Script to Server

**Option A: Via cPanel File Manager**
1. Login to cPanel
2. Go to **File Manager**
3. Navigate to `/var/home/morskiiz/news.morskiizpit.com/`
4. Click **Upload**
5. Upload `migrate_old_database.php`
6. Set permissions to **644** (right-click → Change Permissions)

**Option B: Via Git Pull (if you set up Git in cPanel)**
```bash
cd /var/home/morskiiz/news.morskiizpit.com
git pull origin main
```

---

### Step 3: Run Migration Script

1. Open your web browser
2. Visit: **`https://news.morskiizpit.com/migrate_old_database.php`**
3. Wait for the migration to complete (may take 2-5 minutes)

**Expected Output:**
```
Database Migration - Old to New Structure
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Step 1: Migrating Categories...
Created category: Навигация - Оперативно ниво (New ID: 1)
Created category: Товарно дело - Оперативно ниво (New ID: 2)
...
✅ Categories migrated: 24

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Step 2: Migrating Questions...
Migrated 100 questions...
Migrated 200 questions...
...
✅ Questions migrated: 22562

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Step 3: Updating question counts...
Category 1: 945 questions
Category 2: 823 questions
...

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✅ Migration Complete!
Total questions migrated: 22562
Total categories: 24

Verification:
Total questions in new database: 22562
```

---

### Step 4: Verify Migration Success

**Check via phpMyAdmin:**
```sql
-- Check total questions
SELECT COUNT(*) FROM questions;

-- Check questions per category
SELECT c.name_bg, COUNT(q.id) as question_count
FROM categories c
LEFT JOIN questions q ON c.id = q.category_id
GROUP BY c.id;

-- Check sample question with all options
SELECT question_text, option_a, option_b, option_c, option_d, correct_answer
FROM questions
LIMIT 5;
```

---

### Step 5: Upload Question Images

Your question images are currently in the `uploads/` folder locally. You need to upload them to the server.

**Via cPanel File Manager:**
1. Navigate to `/var/home/morskiiz/news.morskiizpit.com/`
2. Create folder: `images` (if it doesn't exist)
3. Inside `images`, create folder: `questions`
4. Upload all files from your local `uploads/images/` folder to `/var/home/morskiiz/news.morskiizpit.com/images/questions/`

**Important:** The database stores image paths like `uploads/images/524gr.jpg`. You have two options:

**Option A (Recommended):** Create matching structure on server:
```
/var/home/morskiiz/news.morskiizpit.com/uploads/images/
```
Upload all images to this path.

**Option B:** Update image paths in database:
```sql
UPDATE questions
SET image_filename = REPLACE(image_filename, 'uploads/images/', 'images/questions/')
WHERE image_filename IS NOT NULL;
```

---

### Step 6: Delete Migration Script (Security)

After successful migration:
1. Go to cPanel File Manager
2. Delete `migrate_old_database.php` from the server
3. **Reason:** This file contains database credentials and should not remain publicly accessible

---

## 🔍 Troubleshooting

### Issue: "Connection refused" or "Access denied"
**Solution:** Check database credentials in migrate_old_database.php (lines 14 and 20)

### Issue: "Table 'questions' doesn't exist"
**Solution:**
1. Make sure you imported `schema.sql` into the NEW database
2. Verify database name is correct: `morskiiz_maritime`

### Issue: "Question X: Less than 4 answers. Skipping..."
**Solution:** This is normal. Some questions in your old database may have incomplete data. The script will skip them and continue.

### Issue: "Question X: No correct answer marked. Using A as default."
**Solution:** Some questions don't have `is_correct = 1` set. The script defaults to option A. You can manually fix these later via Admin Panel.

### Issue: Migration takes too long / timeout
**Solution:**
1. Increase PHP max_execution_time in cPanel → PHP Settings
2. Set to at least 600 seconds (10 minutes)
3. Or run migration in smaller batches (edit script to add LIMIT)

---

## 📊 What Happens During Migration

### Category Mapping
```
Old ID → New ID
1      → 1   (Навигация - Оперативно ниво)
2      → 2   (Товарно дело - Оперативно ниво)
3      → 3   (Грижа за лицата на борда - Оперативно ниво)
...
```

### Question Transformation
```
OLD STRUCTURE:
questions table:
  id: 22562, question: "Кой канал се използват...", question_category_id: 3

question_answer_choices table (4 separate rows):
  question_id: 22562, choice: "Channel 70", is_correct: 1
  question_id: 22562, choice: "Channel 16", is_correct: 0
  question_id: 22562, choice: "Channel 13", is_correct: 0
  question_id: 22562, choice: "Channel 6", is_correct: 0

NEW STRUCTURE:
questions table (single row):
  id: 1, category_id: 3, original_index: 1,
  question_text: "Кой канал се използват...",
  option_a: "Channel 70", option_b: "Channel 16",
  option_c: "Channel 13", option_d: "Channel 6",
  correct_answer: "A",
  image_filename: NULL
```

---

## ✅ After Migration Checklist

- [ ] All questions migrated (check count)
- [ ] Categories mapped correctly
- [ ] Sample questions display all 4 options
- [ ] Correct answers are marked properly
- [ ] Question images uploaded to server
- [ ] Images display in test (test with sample question)
- [ ] Migration script deleted from server
- [ ] Test generation works (Admin Panel → Import Questions)
- [ ] 25% distribution working (verify via SQL query)

---

## 🚀 Next Steps

After successful migration:

1. **Configure Backend:**
   - Edit `includes/db.php` with production database credentials
   - Change `TOKEN_SECRET` in `includes/auth.php`

2. **Deploy Frontend:**
   - Run `npm run build`
   - Upload `dist/` contents to subdomain

3. **Test Complete Flow:**
   - Register user → Request access → Admin approve → Generate test → Complete test

4. **Deploy to Production:**
   - Copy everything from subdomain to main domain
   - Update `API_URL` in `services/storageService.ts` to production URL

---

## 🆘 Need Help?

If you encounter issues:
1. Check browser console (F12) for errors
2. Check server error logs: `/var/home/morskiiz/news.morskiizpit.com/logs/api_errors.log`
3. Verify database connections in phpMyAdmin
4. Check PHP error logs in cPanel → Error Log

---

**Good luck with your migration!** 🎉
