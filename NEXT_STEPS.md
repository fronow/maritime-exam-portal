# 🎯 NEXT STEPS - Maritime Exam Portal Deployment

## ✅ What's Ready

- ✅ Backend API code (18 endpoints) - **Pushed to GitHub**
- ✅ Frontend code - **Already built**
- ✅ Database schema - **Ready to migrate**
- ✅ Migration script - **Created and tested**
- ✅ Documentation - **Complete**

---

## 🚀 Deployment Checklist (In Order)

### 1️⃣ **DATABASE MIGRATION** ⚡ START HERE

**Time:** 10-15 minutes

1. Make sure both databases are in cPanel phpMyAdmin:
   - `morskiiz_dfrnw` (your old database with real questions)
   - `morskiiz_maritime` (new empty database from schema.sql)

2. Edit `migrate_old_database.php` locally:
   - **Line 14:** Set OLD database password
   - **Line 20:** Set NEW database password

3. Upload `migrate_old_database.php` to server:
   ```
   Path: /var/home/morskiiz/news.morskiizpit.com/migrate_old_database.php
   ```

4. Run migration in browser:
   ```
   https://news.morskiizpit.com/migrate_old_database.php
   ```

5. Wait for completion (2-5 minutes)

6. **Verify in phpMyAdmin:**
   ```sql
   SELECT COUNT(*) FROM questions;
   -- Expected: ~22,562 questions
   ```

7. **DELETE** migration script from server (security)

📖 **Detailed Guide:** See `MIGRATION_GUIDE.md`

---

### 2️⃣ **UPLOAD QUESTION IMAGES**

**Time:** 5-10 minutes

Your images are in: `D:\maritime-exam-portal\uploads\images\`

**Upload to server:**
```
Local:  D:\maritime-exam-portal\uploads\images\
Server: /var/home/morskiiz/news.morskiizpit.com/uploads/images/
```

**Via cPanel File Manager:**
1. Navigate to `/var/home/morskiiz/news.morskiizpit.com/`
2. Create folder: `uploads`
3. Inside `uploads`, create folder: `images`
4. Upload all `.jpg` files from your local `uploads/images/` folder

**Image Count:** Check how many images you have locally:
```powershell
# Run in PowerShell:
(Get-ChildItem "D:\maritime-exam-portal\uploads\images\" -File).Count
```

---

### 3️⃣ **CONFIGURE BACKEND**

**Time:** 5 minutes

Edit `includes/db.php` on the server (or locally then push):

```php
// Line 17-20: Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'morskiiz_maritime');
define('DB_USER', 'morskiiz_maritime_user');
define('DB_PASS', 'YOUR_ACTUAL_PASSWORD');  // ⚠️ CHANGE THIS!
```

Edit `includes/auth.php` on the server:
```php
// Line 20: Change secret key for security
define('TOKEN_SECRET', 'YOUR_RANDOM_SECRET_KEY_HERE');
```

Generate random secret: https://randomkeygen.com/ (use "256-bit WEP Keys")

---

### 4️⃣ **DEPLOY BACKEND TO SERVER**

**Time:** 5 minutes

**Option A: Manual Upload via cPanel File Manager**

Upload these files to `/var/home/morskiiz/news.morskiizpit.com/`:
```
api.php
includes/
  ├── db.php (with your credentials)
  ├── auth.php (with your secret key)
  ├── utils.php
  └── actions/
      ├── auth.php
      ├── user.php
      ├── test.php
      └── admin.php
```

**Option B: Git Pull (if Git is set up in cPanel)**
```bash
cd /var/home/morskiiz/news.morskiizpit.com
git pull origin main
# Then manually edit includes/db.php with credentials
```

---

### 5️⃣ **TEST BACKEND API**

**Time:** 2 minutes

Visit in browser:
```
https://news.morskiizpit.com/api.php
```

**Expected Response:**
```json
{
  "success": false,
  "error": "No action specified"
}
```

**If you see this:** ✅ Backend is working!

**If you see error:** Check `logs/api_errors.log` on server

---

### 6️⃣ **DEPLOY FRONTEND**

**Time:** 5 minutes

1. **Build frontend locally:**
   ```bash
   cd D:\maritime-exam-portal
   npm run build
   ```

2. **Upload dist/ contents to server:**
   ```
   Local:  D:\maritime-exam-portal\dist\*
   Server: /var/home/morskiiz/news.morskiizpit.com/
   ```

   Upload all files from `dist/` folder:
   - index.html
   - assets/
   - (any other files)

3. **Test frontend:**
   ```
   https://news.morskiizpit.com
   ```

   You should see the Maritime Exam Portal home page

---

### 7️⃣ **COMPLETE SYSTEM TEST**

**Time:** 10 minutes

Test the entire flow:

1. **Admin Login:**
   - Email: `admin@maritime.com`
   - Password: `admin123`
   - ✅ Should see Admin Panel

2. **Check Categories:**
   - Go to Admin → Categories
   - ✅ Should see all 24 categories with question counts

3. **Register New User:**
   - Logout
   - Register with test email
   - ✅ Should create account successfully

4. **Request Access:**
   - Select a category
   - Click "Request Access"
   - ✅ Should submit request

5. **Approve Request (as Admin):**
   - Login as admin
   - Go to "Access Requests"
   - Approve the request
   - ✅ User should get access

6. **Generate Test (as User):**
   - Login as test user
   - Go to the approved category
   - Click "Generate Test"
   - ✅ Should see 60 questions

7. **Verify 25% Distribution:**
   Run this SQL in phpMyAdmin:
   ```sql
   SELECT q.original_index
   FROM test_sessions ts
   JOIN test_answers ta ON ts.id = ta.session_id
   JOIN questions q ON ta.question_id = q.id
   WHERE ts.id = (SELECT MAX(id) FROM test_sessions)
   ORDER BY ta.id;
   ```

   ✅ Questions should be balanced across index ranges

8. **Complete Test:**
   - Answer some questions
   - Submit test
   - ✅ Should see results

---

## 🔧 Configuration Updates

### Frontend API URL

After testing on subdomain, update for production:

**File:** `services/storageService.ts` (line 6)

```typescript
// Testing on subdomain:
const API_URL = 'https://news.morskiizpit.com/api.php';

// Production (after testing):
const API_URL = 'https://morskiizpit.com/api.php';
```

---

## 📊 Expected Results After Migration

### Database Stats
```
Categories: 24
Total Questions: ~22,562
Categories with BG names: 12
Categories with EN names: 12
```

### Questions Distribution Example
```
Category: Навигация - Оперативно ниво
Questions: ~945
Test generates: 60 questions (15 from each quartile)
```

### Images
```
Total images: ~500-1000 (depends on your data)
Format: .jpg, .png
Paths: uploads/images/XXXgr.jpg
```

---

## 🆘 Troubleshooting Quick Reference

| Issue | Solution |
|-------|----------|
| Backend API returns error | Check `logs/api_errors.log` |
| Frontend shows "Mock Mode" | Check API_URL in storageService.ts |
| Database connection fails | Verify credentials in includes/db.php |
| Images not displaying | Check upload path and file permissions |
| Migration timeout | Increase PHP max_execution_time in cPanel |
| No questions in test | Run migration script first |

---

## 📁 Important Files Reference

| File | Purpose | Location |
|------|---------|----------|
| `MIGRATION_GUIDE.md` | Detailed migration instructions | Root |
| `migrate_old_database.php` | Database migration script | Upload to server |
| `includes/db.php` | Database credentials | Edit before upload |
| `includes/auth.php` | Security settings | Change TOKEN_SECRET |
| `services/storageService.ts` | API endpoint config | Change API_URL for production |
| `.gitignore` | Protected files | Prevents committing db.php |

---

## 🎉 After Everything Works on Subdomain

### Deploy to Main Domain

1. Copy everything from `/var/home/morskiiz/news.morskiizpit.com/`
   to `/var/home/morskiiz/public_html/` (or main domain folder)

2. Update API_URL in frontend:
   ```typescript
   const API_URL = 'https://morskiizpit.com/api.php';
   ```

3. Rebuild and redeploy frontend

4. Test complete flow on main domain

---

## 📞 Support Resources

- **Build Summary:** `BUILD_COMPLETE.md`
- **Deployment Guide:** `DEPLOYMENT_GUIDE.md`
- **GitHub Setup:** `GITHUB_CPANEL_SETUP.md`
- **API Documentation:** `BACKEND_API_PLAN.md`
- **Quick Start:** `QUICK_START.md`

---

## ✅ Success Checklist

- [ ] Migration completed (22,562 questions)
- [ ] All images uploaded (~500-1000 files)
- [ ] Backend API responding correctly
- [ ] Frontend loads on subdomain
- [ ] Admin can login
- [ ] Users can register
- [ ] Access requests work
- [ ] Test generation works
- [ ] 60 questions generated per test
- [ ] 25% distribution verified
- [ ] Questions display with images
- [ ] Timer works correctly
- [ ] Test submission works
- [ ] Results page shows score
- [ ] Ready to deploy to main domain

---

**Current Status:** Ready for database migration! ⚡

**Start with:** Step 1 - Database Migration

**Estimated Total Time:** 1-2 hours for complete deployment

**Good luck!** 🚀
