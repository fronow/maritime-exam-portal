# 🎯 Admin Quick Reference - Maritime Exam Portal

Quick reference for common admin tasks after deployment.

---

## 🔐 Default Admin Credentials

```
Email: admin@maritime.com
Password: admin123
```

⚠️ **IMPORTANT:** Change this password immediately after first login!

---

## 📊 Common Admin Tasks

### 1. Change Admin Password

**Via SQL (phpMyAdmin):**
```sql
-- Generate new password hash (use online bcrypt generator or PHP)
UPDATE users
SET password_hash = '$2y$10$YOUR_NEW_BCRYPT_HASH_HERE'
WHERE email = 'admin@maritime.com';
```

**Bcrypt Hash Generator:** https://bcrypt-generator.com/ (cost: 10)

---

### 2. Check Question Statistics

```sql
-- Total questions
SELECT COUNT(*) as total_questions FROM questions;

-- Questions per category
SELECT
    c.name_bg as category,
    c.question_count,
    COUNT(q.id) as actual_count
FROM categories c
LEFT JOIN questions q ON c.id = q.category_id
GROUP BY c.id
ORDER BY c.id;

-- Categories without questions
SELECT name_bg FROM categories WHERE question_count = 0;
```

---

### 3. View Recent Test Sessions

```sql
SELECT
    ts.id,
    u.email as user_email,
    c.name_bg as category,
    ts.score,
    ts.total_questions,
    DATE_FORMAT(ts.started_at, '%Y-%m-%d %H:%i') as test_date,
    ts.completed
FROM test_sessions ts
JOIN users u ON ts.user_id = u.id
JOIN categories c ON ts.category_id = c.id
ORDER BY ts.started_at DESC
LIMIT 20;
```

---

### 4. View Pending Access Requests

```sql
SELECT
    ar.id,
    u.email,
    u.first_name,
    u.last_name,
    c.name_bg as category,
    ar.status,
    DATE_FORMAT(ar.requested_at, '%Y-%m-%d %H:%i') as requested
FROM access_requests ar
JOIN users u ON ar.user_id = u.id
JOIN categories c ON ar.category_id = c.id
WHERE ar.status = 'PENDING'
ORDER BY ar.requested_at ASC;
```

---

### 5. Grant Manual Access to User

If you need to manually grant access without approval workflow:

```sql
-- First, get user ID and category ID
SELECT id, email FROM users WHERE email = 'user@example.com';
SELECT id, name_bg FROM categories WHERE name_bg LIKE '%Navigation%';

-- Grant access (expires in 365 days)
INSERT INTO user_categories (user_id, category_id, expires_at)
VALUES (USER_ID, CATEGORY_ID, DATE_ADD(NOW(), INTERVAL 365 DAY));
```

---

### 6. Extend User Access

```sql
-- Extend by 365 days from current expiry
UPDATE user_categories
SET expires_at = DATE_ADD(expires_at, INTERVAL 365 DAY)
WHERE user_id = USER_ID AND category_id = CATEGORY_ID;

-- Extend by 365 days from today
UPDATE user_categories
SET expires_at = DATE_ADD(NOW(), INTERVAL 365 DAY)
WHERE user_id = USER_ID AND category_id = CATEGORY_ID;
```

---

### 7. View User Category Access

```sql
SELECT
    u.email,
    c.name_bg as category,
    DATE_FORMAT(uc.granted_at, '%Y-%m-%d') as granted,
    DATE_FORMAT(uc.expires_at, '%Y-%m-%d') as expires,
    CASE
        WHEN uc.expires_at < NOW() THEN 'EXPIRED'
        WHEN uc.expires_at < DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 'EXPIRING SOON'
        ELSE 'ACTIVE'
    END as status
FROM user_categories uc
JOIN users u ON uc.user_id = u.id
JOIN categories c ON uc.category_id = c.id
ORDER BY uc.expires_at ASC;
```

---

### 8. Suspend/Unsuspend User

```sql
-- Suspend user
UPDATE users SET suspended = TRUE WHERE email = 'user@example.com';

-- Unsuspend user
UPDATE users SET suspended = FALSE WHERE email = 'user@example.com';

-- Check suspended users
SELECT id, email, first_name, last_name, suspended
FROM users
WHERE suspended = TRUE;
```

---

### 9. Delete Test Session (and answers)

```sql
-- Delete specific test session
DELETE FROM test_sessions WHERE id = SESSION_ID;
-- Answers are automatically deleted due to CASCADE

-- Delete all incomplete tests older than 7 days
DELETE FROM test_sessions
WHERE completed = FALSE
AND started_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
```

---

### 10. Check Database Integrity

```sql
-- Questions without valid category
SELECT q.id, q.question_text
FROM questions q
LEFT JOIN categories c ON q.category_id = c.id
WHERE c.id IS NULL;

-- Questions without correct answer
SELECT id, question_text, correct_answer
FROM questions
WHERE correct_answer NOT IN ('A', 'B', 'C', 'D')
LIMIT 20;

-- Categories with wrong question count
SELECT
    c.id,
    c.name_bg,
    c.question_count as stored_count,
    COUNT(q.id) as actual_count,
    (c.question_count - COUNT(q.id)) as difference
FROM categories c
LEFT JOIN questions q ON c.id = q.category_id
GROUP BY c.id
HAVING stored_count != actual_count;
```

---

### 11. Fix Category Question Counts

```sql
-- Update all category question counts
UPDATE categories c
SET question_count = (
    SELECT COUNT(*) FROM questions WHERE category_id = c.id
);
```

---

### 12. View Test Answer Details

```sql
-- View all answers for a specific test
SELECT
    q.question_text,
    ta.user_answer,
    q.correct_answer,
    CASE
        WHEN ta.user_answer = q.correct_answer THEN 'CORRECT'
        ELSE 'WRONG'
    END as result
FROM test_answers ta
JOIN questions q ON ta.question_id = q.id
WHERE ta.session_id = SESSION_ID
ORDER BY ta.id;
```

---

### 13. Export Category Statistics

```sql
-- Detailed category stats
SELECT
    c.name_bg,
    c.name_en,
    c.question_count,
    c.price,
    c.exam_duration_minutes,
    COUNT(DISTINCT uc.user_id) as active_users,
    COUNT(DISTINCT ts.id) as total_tests,
    AVG(ts.score) as avg_score,
    MAX(ts.score) as highest_score
FROM categories c
LEFT JOIN user_categories uc ON c.id = uc.category_id AND uc.expires_at > NOW()
LEFT JOIN test_sessions ts ON c.id = ts.category_id AND ts.completed = TRUE
GROUP BY c.id
ORDER BY c.id;
```

---

### 14. Find Users Without Active Access

```sql
SELECT
    u.id,
    u.email,
    u.first_name,
    u.last_name,
    DATE_FORMAT(u.created_at, '%Y-%m-%d') as registered
FROM users u
LEFT JOIN user_categories uc ON u.id = uc.user_id AND uc.expires_at > NOW()
WHERE u.role = 'USER'
AND uc.id IS NULL
ORDER BY u.created_at DESC;
```

---

### 15. View Audit Log (Admin Actions)

```sql
SELECT
    al.id,
    u.email as admin_email,
    al.action,
    al.details,
    DATE_FORMAT(al.created_at, '%Y-%m-%d %H:%i:%s') as timestamp
FROM audit_log al
JOIN users u ON al.user_id = u.id
ORDER BY al.created_at DESC
LIMIT 50;
```

---

## 🔧 Configuration Files

### Update Payment Link

**Via Admin Panel:** Settings → Revolut Payment Link

**Via SQL:**
```sql
UPDATE settings
SET value = 'https://revolut.me/YOUR_LINK'
WHERE key_name = 'revolut_link';
```

---

### Update Exam Settings

```sql
-- Update default exam duration (in minutes)
UPDATE categories
SET exam_duration_minutes = 90
WHERE id = CATEGORY_ID;

-- Update category price
UPDATE categories
SET price = 30.00
WHERE id = CATEGORY_ID;

-- Update access duration (in days)
UPDATE categories
SET duration_days = 180
WHERE id = CATEGORY_ID;
```

---

## 📊 Performance Monitoring

### Slow Query Detection

```sql
-- Find categories with most questions (may slow down test generation)
SELECT name_bg, question_count
FROM categories
WHERE question_count > 1000
ORDER BY question_count DESC;
```

### Database Size

```sql
-- Check table sizes
SELECT
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.TABLES
WHERE table_schema = 'morskiiz_maritime'
ORDER BY (data_length + index_length) DESC;
```

---

## 🚨 Emergency Procedures

### System is Down - Quick Checks

1. **Check API endpoint:**
   ```
   https://news.morskiizpit.com/api.php
   ```
   Should return: `{"success":false,"error":"No action specified"}`

2. **Check database connection:**
   - Login to phpMyAdmin
   - Verify database exists
   - Check if tables are present

3. **Check error logs:**
   ```
   cPanel → File Manager → logs/api_errors.log
   ```

4. **Check PHP errors:**
   ```
   cPanel → Errors (under Metrics section)
   ```

---

### Reset Everything (Nuclear Option)

⚠️ **This will delete ALL data!**

```sql
-- Drop all tables
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS test_answers;
DROP TABLE IF EXISTS test_sessions;
DROP TABLE IF EXISTS user_categories;
DROP TABLE IF EXISTS access_requests;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS package_categories;
DROP TABLE IF EXISTS packages;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS settings;

-- Re-import schema.sql
-- Re-run migration script
```

---

## 📞 Quick Links

| Resource | Location |
|----------|----------|
| Admin Panel | `https://news.morskiizpit.com` → Login as admin |
| phpMyAdmin | cPanel → phpMyAdmin → morskiiz_maritime |
| API Endpoint | `https://news.morskiizpit.com/api.php` |
| Error Logs | cPanel → File Manager → `/logs/api_errors.log` |
| Backend Files | `/var/home/morskiiz/news.morskiizpit.com/` |

---

## 🎓 SQL Cheat Sheet

```sql
-- Count records
SELECT COUNT(*) FROM table_name;

-- Recent records
SELECT * FROM table_name ORDER BY created_at DESC LIMIT 10;

-- Search
SELECT * FROM table_name WHERE column LIKE '%search%';

-- Date filtering
SELECT * FROM table_name
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Join example
SELECT t1.*, t2.name
FROM table1 t1
JOIN table2 t2 ON t1.id = t2.table1_id;
```

---

**Need more help?** Check the full documentation:
- `BUILD_COMPLETE.md` - System overview
- `BACKEND_API_PLAN.md` - API documentation
- `DATABASE_SCHEMA_DIAGRAM.md` - Database structure

---

**Last Updated:** 2024-12-03
