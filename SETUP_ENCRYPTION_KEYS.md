# 🔐 SETUP ENCRYPTION KEYS - MUST DO BEFORE DEPLOYING

## 📋 SUMMARY

**File to Edit:** `complete_api.php`
**Lines to Update:** 40, 115, 116, 125, 126
**Time Required:** 2 minutes

---

## ⚠️ CRITICAL: DO NOT COMMIT REAL KEYS TO GITHUB!

✅ **GOOD NEWS:** `complete_api.php` is already in `.gitignore`
✅ **Template file created:** `complete_api.example.php` (safe to commit)

---

## 🎯 WHAT TO UPDATE

### **1️⃣ Database Password (Line 40)**

**Find:**
```php
define('DB_PASS', 'YOUR_PASSWORD'); // ⚠️ UPDATE THIS!
```

**Replace with:**
```php
define('DB_PASS', 'p=y+HHPAnm*&'); // Your actual password
```

---

### **2️⃣ Secret Key (Lines 115 AND 125 - MUST BE IDENTICAL)**

**Find (Line 115):**
```php
$key = hash('sha256', 'YOUR_SECRET_KEY_HERE_CHANGE_THIS', true); // ⚠️ CHANGE THIS!
```

**Find (Line 125):**
```php
$key = hash('sha256', 'YOUR_SECRET_KEY_HERE_CHANGE_THIS', true);
```

**Replace BOTH with same random string:**

Go to https://randomkeygen.com/ → Copy a "Fort Knox Password"

**Example (generate your own!):**
```php
$key = hash('sha256', 'xQ7mK2vB9nL4pR6tY8wZ1jH3gF5cM7vX2kL5qP8wR3zT6yH4jG1bN9cM7vX2kL5', true);
```

⚠️ **MUST USE SAME VALUE in line 115 AND line 125!**

---

### **3️⃣ IV Key (Lines 116 AND 126 - MUST BE IDENTICAL)**

**Find (Line 116):**
```php
$iv = substr(hash('sha256', 'YOUR_IV_HERE_CHANGE_THIS'), 0, 16);
```

**Find (Line 126):**
```php
$iv = substr(hash('sha256', 'YOUR_IV_HERE_CHANGE_THIS'), 0, 16);
```

**Replace BOTH with same random string (DIFFERENT from Secret Key):**

Go to https://randomkeygen.com/ → Copy another "Fort Knox Password"

**Example (generate your own!):**
```php
$iv = substr(hash('sha256', 'hF8pL3rN6mQ9tK2vW7xY4bJ5cZ1hF8pL3rN6mQ9tK2vW7xY4bJ5cZ1hF8pL3rN6'), 0, 16);
```

⚠️ **MUST USE SAME VALUE in line 116 AND line 126!**

---

## 🎲 HOW TO GENERATE RANDOM KEYS

### **Method 1: Online Generator (Easiest)**

1. Visit: https://randomkeygen.com/
2. Scroll to **"Fort Knox Passwords"**
3. Copy one password → Use for Secret Key
4. Copy another password → Use for IV Key

### **Method 2: PowerShell**

```powershell
# Run this twice to get 2 different keys
-join ((65..90) + (97..122) + (48..57) | Get-Random -Count 50 | % {[char]$_})
```

---

## ✅ CHECKLIST

- [ ] Line 40: Updated with your database password
- [ ] Line 115: Updated with random Secret Key
- [ ] Line 125: **SAME** Secret Key as line 115
- [ ] Line 116: Updated with random IV Key (different from Secret Key)
- [ ] Line 126: **SAME** IV Key as line 116
- [ ] Saved `complete_api.php`
- [ ] **DO NOT** commit `complete_api.php` to git (it's already in .gitignore)

---

## 📝 EXAMPLE (COMPLETE)

```php
// Line 40
define('DB_PASS', 'p=y+HHPAnm*&');

// Lines 115 & 125 (SAME VALUE)
$key = hash('sha256', 'xQ7mK2vB9nL4pR6tY8wZ1jH3gF5cM7vX2kL5qP8wR3', true);

// Lines 116 & 126 (SAME VALUE, but DIFFERENT from above)
$iv = substr(hash('sha256', 'hF8pL3rN6mQ9tK2vW7xY4bJ5cZ1hF8pL3rN6mQ9'), 0, 16);
```

⚠️ **GENERATE YOUR OWN KEYS - DON'T USE THESE EXAMPLES!**

---

## 🚀 AFTER UPDATING

### **Deploy to Server:**

1. Upload `complete_api.php` (with your real keys) to:
   `/var/home/morskiiz/news.morskiizpit.com/`

2. Build and upload frontend:
   ```bash
   npm run build
   ```
   Upload `dist/` contents to same location

### **For GitHub:**

`complete_api.php` is already in `.gitignore`, so your real keys won't be committed!

You can safely commit:
- ✅ `complete_api.example.php` (template with placeholder values)
- ✅ All documentation files
- ✅ Frontend files
- ✅ Other backend files

```bash
git add .
git commit -m "Add security enhancements and documentation"
git push
```

---

## 🔒 SECURITY NOTES

**WHY we need different keys:**
- **Secret Key** = Used to encrypt session tokens
- **IV Key** = Initialization Vector for encryption algorithm
- Both must be random and secret
- Both must stay the same in both places (115=125, 116=126)
- If you change them, all existing user sessions become invalid (users need to login again)

**WHY we can't commit to GitHub:**
- If someone gets your keys, they can:
  - Forge session tokens
  - Impersonate any user (including admin)
  - Access your database

**Best Practice:**
- ✅ Keep production keys on server only
- ✅ Use `.gitignore` to prevent accidental commits
- ✅ Use different keys for dev/staging/production
- ✅ Rotate keys every 6-12 months

---

## 🆘 TROUBLESHOOTING

**"Users can't login after updating keys"**
- This is normal! Changing keys invalidates all existing sessions
- Users just need to login again
- Their passwords are safe (stored separately with bcrypt)

**"I accidentally committed real keys to GitHub"**
1. Immediately generate NEW keys
2. Update `complete_api.php` with new keys
3. Deploy new version to server
4. Remove file from git history:
   ```bash
   git rm --cached complete_api.php
   git commit -m "Remove sensitive file"
   git push
   ```

**"I forgot which lines to update"**
- Line 40: Database password
- Lines 115 & 125: Secret Key (SAME in both)
- Lines 116 & 126: IV Key (SAME in both)

---

## ⏱️ TIME REQUIRED

- Generate 2 random keys: **30 seconds**
- Update 5 lines in file: **1 minute**
- Save and upload to server: **30 seconds**
- **Total: 2 minutes**

---

🔐 **Keep your keys SECRET and SECURE!**
