# ⚡ EASY SETUP GUIDE - Maritime Exam Portal

## 🎯 What's Different Now?

✅ **No database migration needed!**
✅ **Use your existing database directly**
✅ **Just import and add new tables**
✅ **Backend automatically adapts to your structure**

---

## 📋 Simple 3-Step Setup

### **STEP 1: Import Your Database** (2 min)

1. Login to **cPanel → phpMyAdmin**
2. Create database: `morskiiz_maritime` (or use existing)
3. Select the database
4. Click **Import**
5. Upload `morskiiz_dfrnw.sql` (your local file)
6. Click **Go**

✅ **Result:** All your questions and categories are now in the database!

---

### **STEP 2: Add New Tables** (2 min)

1. Still in phpMyAdmin, with `morskiiz_maritime` selected
2. Click **SQL** tab at the top
3. Copy the entire content of `add_new_tables.sql` file
4. Paste into the SQL box
5. Click **Go**

✅ **Result:** New tables added (users, test_sessions, access_requests, etc.) + your questions table now has option_a, option_b, option_c, option_d columns populated!

**What this script does:**
- Adds `name_bg`, `name_en`, `price`, `duration_days` to question_categories
- Adds `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `original_index` to questions table
- Populates these from your existing question_answer_choices table
- Creates new tables: users, test_sessions, test_answers, etc.
- Creates default admin user (admin@maritime.com / admin123)

---

### **STEP 3: Configure & Deploy Backend** (5 min)

1. **Edit `includes/db.php` locally:**
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'morskiiz_maritime');
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_password');
   ```

2. **Edit `includes/auth.php` line 20:**
   ```php
   define('TOKEN_SECRET', 'your-random-secret-key-here');
   ```
   Generate random key: https://randomkeygen.com/

3. **Upload backend files to server:**
   ```
   Path: /var/home/morskiiz/news.morskiizpit.com/

   Files to upload:
   - api.php
   - includes/
     ├── db.php (with your credentials)
     ├── db_compat.php
     ├── auth.php
     ├── utils.php
     └── actions/
         ├── auth.php
         ├── user.php
         ├── test.php
         └── admin.php
   ```

4. **Test backend:**
   Visit: `https://news.morskiizpit.com/api.php`

   Should see:
   ```json
   {"success":false,"error":"No action specified"}
   ```

✅ **Result:** Backend is working!

---

## 🖼️ STEP 4: Upload Images (5 min)

Upload your images from:
```
Local:  D:\maritime-exam-portal\uploads\images\
Server: /var/home/morskiiz/news.morskiizpit.com/uploads/images/
```

Via cPanel File Manager:
1. Navigate to `/var/home/morskiiz/news.morskiizpit.com/`
2. Create `uploads` folder
3. Inside `uploads`, create `images` folder
4. Upload all `.jpg` files

---

## 🎨 STEP 5: Deploy Frontend (5 min)

1. **Build frontend:**
   ```bash
   cd D:\maritime-exam-portal
   npm install  # if not done yet
   npm run build
   ```

2. **Upload `dist/` contents to server:**
   ```
   Server path: /var/home/morskiiz/news.morskiizpit.com/

   Upload all files from dist/:
   - index.html
   - assets/
   - (all other files)
   ```

3. **Test frontend:**
   Visit: `https://news.morskiizpit.com`

   Should see the Maritime Exam Portal homepage!

---

## ✅ STEP 6: Test Everything (5 min)

### Test 1: Admin Login
- Email: `admin@maritime.com`
- Password: `admin123`
- ✅ Should see Admin Panel with all 24 categories

### Test 2: Check Categories
- Go to Admin → Categories
- ✅ Should show question counts for each category

### Test 3: Register New User
- Logout → Register with test email
- ✅ Should create account

### Test 4: Request Access
- Login as test user → Select category → Request Access
- ✅ Should submit request

### Test 5: Approve Request
- Login as admin → Access Requests → Approve
- ✅ User gets access

### Test 6: Generate Test
- Login as test user → Go to approved category → Start Test
- ✅ Should see 60 questions with all 4 options

---

## 🔍 Verify 25% Distribution (Optional)

Run this in phpMyAdmin after generating a test:

```sql
SELECT q.original_index, q.question
FROM test_sessions ts
JOIN test_answers ta ON ts.id = ta.session_id
JOIN questions q ON ta.question_id = q.id
WHERE ts.id = (SELECT MAX(id) FROM test_sessions)
ORDER BY ta.id;
```

Questions should be distributed across all index ranges (1-100, 101-200, 201-300, 301-400, etc.)

---

## 🎯 What the Backend Does Automatically

The new **db_compat.php** compatibility layer automatically:
- Maps `question_categories` to `categories` (what backend expects)
- Maps `question` field to `question_text`
- Maps `question_image` to `image_filename`
- Maps `question_category_id` to `category_id`
- Uses the new option_a/b/c/d fields (populated from question_answer_choices)
- Keeps your original question_answer_choices table intact (not deleted)

**You don't need to change your database structure manually!**

---

## 📊 Database Tables After Setup

### Your Original Tables (Unchanged):
- ✅ `questions` - Now has additional columns
- ✅ `question_answer_choices` - Kept as-is
- ✅ `question_categories` - Now has additional columns

### New Tables Added:
- ✅ `users` - User accounts
- ✅ `user_categories` - Access control
- ✅ `access_requests` - Access requests
- ✅ `test_sessions` - Active/completed tests
- ✅ `test_answers` - Test answers
- ✅ `packages` - Package bundles
- ✅ `package_categories` - Package-category mapping
- ✅ `settings` - Global settings
- ✅ `audit_log` - Admin action logs

---

## 🔧 Admin Features

After deployment, you can:
- ✅ View all users
- ✅ Approve/reject access requests
- ✅ Set custom prices for each category
- ✅ Set exam duration per category
- ✅ Suspend/unsuspend users
- ✅ View test results
- ✅ Change settings (Revolut link, Facebook link)
- ✅ Create package bundles

**Set Prices:**
```sql
-- Update price for specific category
UPDATE question_categories
SET price = 30.00
WHERE id = 1;

-- Update exam duration (minutes)
UPDATE question_categories
SET exam_duration_minutes = 90
WHERE id = 1;

-- Or use Admin Panel → Categories → Edit
```

---

## 🆘 Troubleshooting

### Backend returns error
**Check:** `/var/home/morskiizpit.com/logs/api_errors.log` on server

### Categories show wrong characters
**Fix:** Run this in phpMyAdmin:
```sql
ALTER DATABASE morskiiz_maritime CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Images not displaying
**Check:**
1. Path is correct: `/var/home/morskiizpit.com/uploads/images/`
2. File permissions are 644
3. Image filenames in database match actual files

### No questions in test
**Fix:** Run `add_new_tables.sql` again - it populates option_a/b/c/d fields

### Frontend shows "Mock Mode"
**Fix:** Check `services/storageService.ts` line 6 - should be:
```typescript
const API_URL = 'https://news.morskiizpit.com/api.php';
```

---

## ⏱️ Total Time Estimate

| Step | Time |
|------|------|
| Import database | 2 min |
| Add new tables | 2 min |
| Configure & deploy backend | 5 min |
| Upload images | 5 min |
| Deploy frontend | 5 min |
| Test everything | 5 min |
| **TOTAL** | **~25 minutes** |

---

## 📞 Quick Reference

| Resource | URL/Path |
|----------|----------|
| Admin Panel | https://news.morskiizpit.com |
| API Endpoint | https://news.morskiizpit.com/api.php |
| phpMyAdmin | cPanel → phpMyAdmin |
| Error Logs | /var/home/morskiiz/news.morskiizpit.com/logs/api_errors.log |
| Backend Files | /var/home/morskiiz/news.morskiizpit.com/ |
| Images | /var/home/morskiiz/news.morskiizpit.com/uploads/images/ |

---

## 🎉 After Testing on Subdomain

When everything works, deploy to main domain:

1. Copy everything from `/var/home/morskiiz/news.morskiizpit.com/`
   to `/var/home/morskiiz/public_html/` (or main domain folder)

2. Update `services/storageService.ts` line 6:
   ```typescript
   const API_URL = 'https://morskiizpit.com/api.php';
   ```

3. Rebuild and redeploy frontend

---

**Your database structure is preserved!**
**No complicated migration!**
**Everything works with your existing data!**

🚀 **Ready to go!**
