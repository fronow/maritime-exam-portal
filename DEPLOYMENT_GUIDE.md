# 🚀 MARITIME EXAM PORTAL - DEPLOYMENT GUIDE

## ✅ WHAT'S BEEN BUILT

Your complete backend is now ready! Here's what was created:

### **Backend Files Created:**
1. ✅ **includes/db.php** - Database connection with PDO
2. ✅ **includes/auth.php** - Authentication, security, password hashing
3. ✅ **includes/utils.php** - Helper functions, response formatting
4. ✅ **includes/actions/auth.php** - Login, register, get_initial_data
5. ✅ **includes/actions/user.php** - User data, request access
6. ✅ **includes/actions/test.php** - Test generation (25% distribution), submit answers, complete test
7. ✅ **includes/actions/admin.php** - All admin operations (approve, users, categories, packages, import)
8. ✅ **api.php** - Main API endpoint with routing

### **Frontend Updated:**
9. ✅ **services/storageService.ts** - Updated to work with new backend API

---

## 📋 DEPLOYMENT STEPS

### **STEP 1: Prepare Database** (5 minutes)

1. **Log into cPanel**
2. **Go to phpMyAdmin**
3. **Create Database:**
   - Click "New" in the left sidebar
   - Database name: `maritime_exam_portal` (or your cPanel prefix + this name)
   - Click "Create"

4. **Import Schema:**
   - Select your new database from the left sidebar
   - Click "Import" tab
   - Click "Choose File" and select `schema.sql` from your project
   - Click "Go" at the bottom
   - Wait for "Import has been successfully finished" message

5. **Change Default Admin Password:**
   - Click "SQL" tab
   - Run this query (replace YOUR_PASSWORD with your desired password):
     ```sql
     UPDATE users SET password_hash = '$2y$10$HASHED_PASSWORD_HERE'
     WHERE email = 'admin@maritime.com';
     ```
   - Or use this PHP script to generate a hash:
     ```php
     <?php echo password_hash('YOUR_NEW_PASSWORD', PASSWORD_BCRYPT); ?>
     ```

6. **Note Your Database Credentials:**
   - Database Host: `localhost` (usually)
   - Database Name: `maritime_exam_portal` (or with your prefix)
   - Username: (your cPanel database username)
   - Password: (your cPanel database password)

---

### **STEP 2: Configure Backend** (2 minutes)

1. **Open `includes/db.php`**
2. **Update Database Credentials (lines 20-23):**
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'cpanel_maritime_exam_portal'); // ← YOUR DATABASE NAME
   define('DB_USER', 'cpanel_db_user');               // ← YOUR DB USERNAME
   define('DB_PASS', 'your_password_here');           // ← YOUR DB PASSWORD
   ```

3. **Open `includes/auth.php`**
4. **Change Secret Key (line 20):**
   ```php
   define('TOKEN_SECRET', 'GENERATE_A_RANDOM_32_CHAR_KEY_HERE_12345678');
   ```
   - Generate a random string (use https://passwordsgenerator.net/ or similar)

---

### **STEP 3: Upload Backend to cPanel** (5 minutes)

**Using File Manager:**
1. Log into cPanel → File Manager
2. Navigate to `public_html/`
3. **Upload these files/folders:**
   - `api.php`
   - `includes/` folder (with all subfolders)
   - `schema.sql` (optional, for backup)

4. **Create `logs/` directory:**
   - In `public_html/`, create new folder: `logs`
   - Set permissions to `755`
   - This will store API error logs

5. **Set File Permissions:**
   - Files: `644`
   - Directories: `755`
   - (cPanel usually sets these correctly by default)

**Using FTP (Alternative):**
- Use FileZilla or similar FTP client
- Connect to your cPanel FTP
- Upload to `public_html/` directory

---

### **STEP 4: Build and Deploy Frontend** (5 minutes)

1. **Update API URL for Production:**
   - Open `services/storageService.ts`
   - Change line 6:
     ```typescript
     const API_URL = 'https://yourdomain.com/api.php'; // ← YOUR DOMAIN
     ```

2. **Build Production Version:**
   ```bash
   cd D:\maritime-exam-portal
   npm install   # If not already done
   npm run build
   ```
   - This creates a `dist/` folder with optimized files

3. **Upload to cPanel:**
   - Go to cPanel → File Manager → `public_html/`
   - **Delete old files** (if any): `index.html`, `assets/` folder
   - **Upload contents of `dist/` folder** (NOT the folder itself!):
     - `index.html`
     - `assets/` folder
     - Any other files from `dist/`

4. **Upload Images:**
   - Create `public_html/images/` folder if it doesn't exist
   - Create `public_html/images/questions/` subfolder
   - Upload all PNG question images (e.g., nav1.png, nav2.png, etc.)
   - Upload `logoM.png` to `public_html/images/`

---

### **STEP 5: Test the Application** (15 minutes)

#### **A. Test Backend API Directly**

1. **Create test file:** `public_html/test_api.php`
   ```php
   <?php
   // Test database connection
   require_once 'includes/db.php';

   try {
       $pdo = getDbConnection();
       echo "✅ Database connection successful!\n";

       $result = dbQuerySingle("SELECT COUNT(*) as count FROM users");
       echo "✅ Found {$result['count']} users in database\n";

       $categories = dbQuery("SELECT * FROM categories");
       echo "✅ Found " . count($categories) . " categories\n";

   } catch (Exception $e) {
       echo "❌ Error: " . $e->getMessage();
   }
   ?>
   ```

2. **Visit:** `https://yourdomain.com/test_api.php`
3. **Expected Output:**
   ```
   ✅ Database connection successful!
   ✅ Found 1 users in database
   ✅ Found 19 categories
   ```

4. **Delete test file** after verification

#### **B. Test Frontend Application**

1. **Visit:** `https://yourdomain.com`

2. **Test User Registration:**
   - Click "Register"
   - Fill in: First Name, Last Name, Email, Password
   - Click "Register"
   - Should redirect to Functions page

3. **Test Login:**
   - Logout
   - Login with admin credentials:
     - Email: `admin@maritime.com`
     - Password: `admin123` (or your new password)
   - Should see "Admin" in header
   - Should redirect to Admin Panel

4. **Test Admin Panel:**
   - **Users Tab:** Should see registered users
   - **Categories Tab:** Should see 19 categories
   - **Packages Tab:** Should see sample package

5. **Test Category Import (CRITICAL - 25% Distribution):**
   - Go to Admin Panel → Categories
   - Select a category
   - Upload Excel file with questions
   - Should see "X questions imported successfully"
   - Verify in database:
     ```sql
     SELECT COUNT(*) FROM questions WHERE category_id = 1;
     ```

6. **Test User Flow:**
   - Logout, login as regular user
   - Go to Functions
   - Select a category
   - Click "Request Access"
   - Logout

7. **Test Admin Approval:**
   - Login as admin
   - Go to Requests tab
   - Should see pending request
   - Approve with 365 days duration
   - Should disappear from pending

8. **Test Exam Generation:**
   - Logout, login as user
   - Go to My Tests
   - Should see approved category
   - Click "Generate Test"
   - Should load 60 questions
   - Answer some questions
   - Timer should countdown from 60:00
   - Click "Finish Test" or let timer expire
   - Should see score, percentage, and grade

9. **Test 25% Distribution (VERY IMPORTANT):**
   - To verify the algorithm works:
   - Check browser console or backend logs
   - Questions 1-15 should come from first 25% of question bank
   - Questions 16-30 from second 25%
   - Questions 31-45 from third 25%
   - Questions 46-60 from fourth 25%

---

### **STEP 6: Production Configuration** (5 minutes)

#### **A. Security Settings**

1. **Disable Error Display (includes/db.php):**
   - Line 14:
     ```php
     error_reporting(E_ALL);
     ini_set('display_errors', 0); // ← Already set to 0 (good!)
     ```

2. **Restrict Direct Access:**
   - Create `includes/.htaccess`:
     ```apache
     Deny from all
     ```

3. **Enable HTTPS/SSL:**
   - cPanel → SSL/TLS Status
   - Install Let's Encrypt certificate (free!)
   - Force HTTPS redirect

4. **Update Frontend API URL to HTTPS:**
   - `services/storageService.ts` line 6:
     ```typescript
     const API_URL = 'https://yourdomain.com/api.php'; // HTTPS!
     ```

#### **B. Configure Settings**

1. **Login as Admin**
2. **Go to Settings Tab**
3. **Set:**
   - Revolut Payment Link: `https://revolut.me/yourlink`
   - Facebook Link: `https://facebook.com/yourpage`
   - Announcement: Welcome message (optional)
4. **Click Save**

---

### **STEP 7: Import Questions** (15-30 minutes)

For each category:

1. **Prepare Excel File with Format:**
   ```
   | Number | Question | Option A | Option B | Option C | Option D | Image | Correct |
   |--------|----------|----------|----------|----------|----------|-------|---------|
   | 1      | What...? | Answer 1 | Answer 2 | Answer 3 | Answer 4 | nav1.png | A    |
   | 2      | When...? | Answer 1 | Answer 2 | Answer 3 | Answer 4 |          | B    |
   ```

2. **Admin Panel → Categories Tab**
3. **Select Category**
4. **Upload Excel File**
5. **Verify Import:**
   - Should show "X questions imported"
   - Category question count should update

---

## 🔍 TROUBLESHOOTING

### **Problem: "Database connection failed"**
**Solution:**
- Check database credentials in `includes/db.php`
- Verify database exists in phpMyAdmin
- Check if database user has permissions

### **Problem: "Backend unavailable. Switching to Mock Mode"**
**Solution:**
- Check if `api.php` exists in `public_html/`
- Visit `https://yourdomain.com/api.php` directly - should show JSON error
- Check server error logs in cPanel
- Verify all `includes/` files uploaded correctly

### **Problem: "Invalid JSON request"**
**Solution:**
- Check browser console for request details
- Verify `Content-Type: application/json` header
- Check api.php line 14 for correct error log path

### **Problem: "Session token expired"**
**Solution:**
- User needs to login again
- Check `TOKEN_EXPIRY` in `includes/auth.php` (default 7 days)

### **Problem: "Not enough questions to generate test"**
**Solution:**
- Import at least 60 questions for that category
- Check database: `SELECT COUNT(*) FROM questions WHERE category_id = X`

### **Problem: "25% distribution not working correctly"**
**Solution:**
- Verify `original_index` field is set correctly in questions table
- Questions should be numbered 1, 2, 3, ... in order
- Check `includes/utils.php` line 35-55 for algorithm

---

## 📊 DATABASE MAINTENANCE

### **Backup Database (Weekly):**
```bash
# Via cPanel: phpMyAdmin → Export → Go
# Or via command line:
mysqldump -u username -p maritime_exam_portal > backup_$(date +%Y%m%d).sql
```

### **Clear Old Test Sessions (Monthly):**
```sql
DELETE FROM test_sessions
WHERE is_completed = TRUE
AND start_time < DATE_SUB(NOW(), INTERVAL 6 MONTH);
```

### **View Statistics:**
```sql
-- Total users
SELECT COUNT(*) FROM users WHERE role = 'USER';

-- Total tests completed
SELECT COUNT(*) FROM test_sessions WHERE is_completed = TRUE;

-- Average scores per category
SELECT c.name_en, AVG(ts.percentage) as avg_score
FROM test_sessions ts
JOIN categories c ON ts.category_id = c.id
WHERE ts.is_completed = TRUE
GROUP BY c.id;
```

---

## 🎯 NEXT STEPS AFTER DEPLOYMENT

1. **Change Admin Password** (CRITICAL!)
2. **Import All Question Banks**
3. **Configure Payment Link**
4. **Test Complete User Flow End-to-End**
5. **Set Up Automated Backups**
6. **Monitor Error Logs** (`logs/api_errors.log`)
7. **Test on Mobile Devices**
8. **Add Custom Domain (if not done)**

---

## 📱 MOBILE TESTING

Test on actual devices:
- iOS Safari
- Android Chrome
- Different screen sizes (phone, tablet)
- Portrait and landscape orientations
- Test timer functionality
- Test image loading

---

## 🔐 SECURITY CHECKLIST

- [ ] Changed default admin password
- [ ] Updated `TOKEN_SECRET` in auth.php
- [ ] HTTPS/SSL enabled
- [ ] Error display disabled in production
- [ ] Database credentials secured
- [ ] `includes/` directory protected
- [ ] Regular backups configured
- [ ] Audit logs enabled

---

## 📞 SUPPORT

If you encounter issues:

1. **Check Error Logs:**
   - cPanel → Errors
   - `public_html/logs/api_errors.log`

2. **Browser Console:**
   - F12 → Console tab
   - Look for red errors

3. **Test API Directly:**
   - Use Postman or curl to test endpoints

4. **Database Check:**
   - phpMyAdmin → Run test queries

---

## ✅ DEPLOYMENT COMPLETE!

Your Maritime Exam Portal is now live! 🎉

**Admin Login:**
- URL: `https://yourdomain.com`
- Email: `admin@maritime.com`
- Password: (your new password)

**Features Working:**
- ✅ User registration and login
- ✅ Category browsing
- ✅ Access requests
- ✅ Admin approval system
- ✅ Question import from Excel
- ✅ Test generation with 25% distribution
- ✅ 60-minute timed exams
- ✅ Auto-scoring and grading
- ✅ Bilingual support (BG/EN)
- ✅ Revolut payment integration
- ✅ Category expiry management

**Ready for production!** 🚀

---

**Document Version:** 1.0
**Last Updated:** December 1, 2025
