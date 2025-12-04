# 🚀 DEPLOYMENT CHECKLIST - Security-Enhanced API

## ⚠️ CRITICAL: Update These Values Before Deployment

### 1. Database Password (Line 40)
```php
define('DB_PASS', 'YOUR_PASSWORD'); // ⚠️ UPDATE THIS!
```

**Action:** Replace `YOUR_PASSWORD` with your actual database password (the one that worked in simple_api.php)

---

### 2. Encryption Keys (Lines 115, 116, 125, 126)

**Line 115 & 125:**
```php
$key = hash('sha256', 'YOUR_SECRET_KEY_HERE_CHANGE_THIS', true); // ⚠️ CHANGE THIS!
```

**Line 116 & 126:**
```php
$iv = substr(hash('sha256', 'YOUR_IV_HERE_CHANGE_THIS'), 0, 16);
```

**Action:** Replace with random secure strings.

**Example secure values:**
```php
// Line 115 & 125:
$key = hash('sha256', 'k9Bx2m7Lp4Wn8Vq5Rf3Tj6Yh1Zc0Sg', true);

// Line 116 & 126:
$iv = substr(hash('sha256', 'Nq8Lr5Mt2Pk9Vw6Xj3Bc7Hf1Zy4Rd'), 0, 16);
```

⚠️ **Generate your own random strings at:** https://randomkeygen.com/

---

## 📝 Step-by-Step Deployment

### Step 1: Update complete_api.php Locally (3 min)

1. Open `D:\maritime-exam-portal\complete_api.php`
2. Line 40: Update database password
3. Lines 115 & 125: Update secret key (use same value in both places)
4. Lines 116 & 126: Update IV key (use same value in both places)
5. Save file

---

### Step 2: Update Frontend API URL (2 min)

1. Open `D:\maritime-exam-portal\src\services\storageService.ts`
2. Line 6: Update to:
   ```typescript
   const API_URL = 'https://news.morskiizpit.com/complete_api.php';
   ```
3. Save file

---

### Step 3: Build Frontend (2 min)

```bash
cd D:\maritime-exam-portal
npm run build
```

---

### Step 4: Upload to Server (5 min)

**Via cPanel File Manager:**

1. **Upload complete_api.php**
   - Navigate to: `/var/home/morskiiz/news.morskiizpit.com/`
   - Upload `complete_api.php`
   - ✅ This replaces the old api.php

2. **Upload frontend build**
   - Navigate to: `/var/home/morskiiz/news.morskiizpit.com/`
   - Delete old `index.html` and `assets/` folder
   - Upload all files from `D:\maritime-exam-portal\dist\`
   - ✅ New frontend with updated API URL

---

### Step 5: Test Backend (2 min)

**Test 1: API Endpoint**
Visit: `https://news.morskiizpit.com/complete_api.php`

**Expected response:**
```json
{"success":false,"error":"Invalid request method"}
```

✅ **This means API is working!**

---

**Test 2: Get Initial Data**
Visit: `https://news.morskiizpit.com/complete_api.php?action=get_initial_data`

**Expected response:**
```json
{
  "success": true,
  "data": {
    "categories": [...],
    "packages": [...],
    "settings": {...}
  }
}
```

✅ **This means database connection is working!**

---

### Step 6: Test Security Features (5 min)

**Test 1: Weak Password Validation**
1. Go to: `https://news.morskiizpit.com`
2. Click "Register"
3. Try password: `weak` (all lowercase, no numbers)
4. ✅ **Should show error:** "Password must be at least 8 characters"

---

**Test 2: Invalid Email**
1. Try email: `not-an-email`
2. ✅ **Should show error:** "Invalid email address"

---

**Test 3: Valid Registration**
1. Email: `test@example.com`
2. Password: `Test1234` (8+ chars, uppercase, lowercase, number)
3. First Name: `Test`
4. Last Name: `User`
5. ✅ **Should succeed** and log you in

---

**Test 4: Rate Limiting**
1. Try to register **6 times in a row** with different emails
2. ✅ **6th attempt should fail with:** "Too many registration attempts. Please try again later."

---

**Test 5: Login Rate Limiting**
1. Try to login with wrong password **21 times in a row**
2. ✅ **21st attempt should fail with:** "Too many login attempts. Please try again later."

---

### Step 7: Test All Features (10 min)

**Test Admin Panel:**
1. Login as admin: `admin@maritime.com` / `admin123`
2. Go to Settings → Update Revolut link
3. Save
4. ✅ **Check phpMyAdmin → settings table** - should be saved

---

**Test Categories:**
1. Admin Panel → Categories
2. ✅ **Should show all 24 categories with question counts**

---

**Test User Registration:**
1. Logout → Register new user
2. ✅ **Should create account and save to database**

---

**Test Access Requests:**
1. Login as test user → Request access to category
2. Login as admin → Approve request with custom expiration date
3. ✅ **Should grant access**

---

**Test Generate Test:**
1. Login as user with approved category
2. Click "Start Test"
3. ✅ **Should show 60 questions from database**

---

**Test Complete Test:**
1. Answer all 60 questions
2. Submit test
3. ✅ **Should save results to database**
4. ✅ **Check phpMyAdmin → test_sessions table**

---

**Test Auto-Cleanup:**
1. Complete **26 tests** in same category
2. ✅ **Check phpMyAdmin → test_sessions table**
3. ✅ **Should only have 25 most recent tests**

---

## 🔒 Security Features Enabled

✅ **HTTPS Enforcement** - Forces secure connections
✅ **CORS Protection** - Only allows your domain
✅ **Rate Limiting** - 5 registrations/hour, 20 logins/hour per IP
✅ **Email Validation** - Validates email format
✅ **Password Strength** - Requires 8+ chars, uppercase, lowercase, number
✅ **Input Sanitization** - Prevents XSS attacks
✅ **SQL Injection Protection** - Uses prepared statements
✅ **Encrypted Tokens** - AES-256-CBC encryption
✅ **Token Expiration** - Tokens expire after 7 days
✅ **Password Hashing** - bcrypt with cost 12
✅ **Audit Logging** - Logs failed login attempts

---

## 🆘 Troubleshooting

### Backend returns 500 error
**Check:** `/var/home/morskiiz/news.morskiizpit.com/logs/api_errors.log`

### "Access denied" database error
**Fix:** Update DB_PASS in complete_api.php line 40

### Frontend shows "Mock Mode"
**Fix:** Rebuild frontend with updated storageService.ts

### Registration succeeds but nothing in database
**Check:** Database password is correct in complete_api.php

### Rate limiting not working
**Check:** audit_log table exists in database (should be created by add_new_tables.sql)

---

## ✅ Final Checklist

- [ ] Updated DB_PASS in complete_api.php
- [ ] Updated encryption keys in complete_api.php (lines 115, 116, 125, 126)
- [ ] Updated API_URL in storageService.ts
- [ ] Built frontend with `npm run build`
- [ ] Uploaded complete_api.php to server
- [ ] Uploaded dist/ contents to server
- [ ] Tested backend endpoint
- [ ] Tested database connection
- [ ] Tested weak password rejection
- [ ] Tested valid registration
- [ ] Tested rate limiting
- [ ] Tested admin panel settings save
- [ ] Tested all categories display
- [ ] Tested access request flow
- [ ] Tested test generation
- [ ] Tested test completion
- [ ] Tested auto-cleanup (25 tests limit)

---

## 🎯 What's Different from Before?

### Old api.php Problems:
- ❌ 508 Loop errors
- ❌ Complex includes causing issues
- ❌ No security features
- ❌ No rate limiting
- ❌ No password validation
- ❌ Weak token generation

### New complete_api.php:
- ✅ Single-file design (no loops)
- ✅ All endpoints working
- ✅ Comprehensive security
- ✅ Rate limiting enabled
- ✅ Strong password validation
- ✅ Encrypted tokens with expiration
- ✅ Audit logging
- ✅ Auto-cleanup of old tests

---

## 📞 Need Help?

If something doesn't work:
1. Check `logs/api_errors.log` on server
2. Check browser console for errors
3. Verify database password is correct
4. Verify encryption keys are updated
5. Verify frontend rebuilt and uploaded

**Your database with 22,562 questions is ready to use!**

🚀 **Ready to deploy!**
