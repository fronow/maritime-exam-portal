🔧 Solution: Use HTTPS with Personal Access Token

SSH can be problematic with cPanel Git. HTTPS is more reliable and easier!

---

✅ STEP-BY-STEP FIX

1. Generate GitHub Personal Access Token (2 minutes)

1. Go to GitHub:


    - https://github.com/settings/tokens

2. Generate New Token:


    - Click "Generate new token" → "Generate new token (classic)"
    - Token name: cPanel Access
    - Expiration: 90 days (or longer)
    - Select scopes: ✅ repo (full control of private repositories)
    - Scroll down and click "Generate token"

3. ⚠️ COPY THE TOKEN NOW!
   ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
4. Save it somewhere safe - you won't see it again!

---

2. Remove Old Repository from cPanel

1. cPanel → Git Version Control
1. If you see your repository listed, click "Delete"
1. Confirm deletion (this only removes it from cPanel, not from GitHub!)

---

3. Create New Repository with HTTPS

1. cPanel → Git Version Control
1. Click "Create"
1. Fill in:


    - Clone URL: https://github.com/fronow/maritime-exam-portal.git
        - ⚠️ Use https:// NOT git@github.com:
    - Repository Path: /home/YOUR_CPANEL_USERNAME/repositories/maritime-exam-portal
    - Repository Name: maritime-exam-portal

4. Click "Create"

---

4. Authenticate with Token

When prompted for credentials:

- Username: fronow (your GitHub username)
- Password: Paste your Personal Access Token
  - ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
  - NOT your GitHub password!

---

5. Pull the Code

1. After creation, click "Manage"
1. Click "Pull or Deploy"
1. Click "Update from Remote"
1. Code will download to /home/YOUR_CPANEL_USERNAME/repositories/maritime-exam-portal/

---

🚀 Deploy to Public Directory

Now copy files to your public_html:

Option A: Via cPanel File Manager (Easiest)

1. cPanel → File Manager
2. Navigate to repositories/maritime-exam-portal/
3. Select and Copy:


    - api.php
    - includes/ folder (whole folder)
    - schema.sql (for reference)

4. Navigate to public_html/
5. Paste the files
6. Configure Database:


    - Open public_html/includes/db.php
    - Edit these lines:
    define('DB_HOST', 'localhost');

define('DB_NAME', 'your_actual_database_name');
define('DB_USER', 'your_actual_username');
define('DB_PASS', 'your_actual_password'); - Save

Option B: Via Terminal (Faster)

If you have Terminal access in cPanel:

# Copy backend files

cp ~/repositories/maritime-exam-portal/api.php ~/public_html/
cp -r ~/repositories/maritime-exam-portal/includes ~/public_html/

# Configure database

cp ~/public_html/includes/db.example.php ~/public_html/includes/db.php
nano ~/public_html/includes/db.php

# Edit the credentials, then Ctrl+X, Y, Enter to save

---

📋 Alternative: Manual File Upload (If Git Still Fails)

If cPanel Git continues to have issues:

1. Download from GitHub:


    - Go to https://github.com/fronow/maritime-exam-portal
    - Click green "Code" button
    - Click "Download ZIP"
    - Extract on your computer

2. Upload via cPanel:


    - cPanel → File Manager
    - Navigate to public_html/
    - Upload → Select files:
        - api.php
      - includes/ folder (zip it first, then extract after upload)

---

🔍 Troubleshooting Common Issues

Issue 1: "Permission denied (publickey)"

Solution: Use HTTPS URL instead of SSH URL

- ✅ Correct: https://github.com/fronow/maritime-exam-portal.git
- ❌ Wrong: git@github.com:fronow/maritime-exam-portal.git

Issue 2: "Authentication failed"

Solution:

- Make sure you're using Personal Access Token, NOT your GitHub password
- Token must have repo scope enabled
- Username is fronow (lowercase)

Issue 3: "Repository not found"

Solution:

- Make sure repository is public, or
- Make sure your token has access to private repos (repo scope)
- Check spelling: maritime-exam-portal (not maritine)

Issue 4: Token expired

Solution:

- Generate new token on GitHub
- Re-authenticate in cPanel

---

✅ Verify Setup

After setup, check:

1. Files Deployed:
   public_html/
   ├── api.php ✅
   └── includes/
   ├── db.php ✅ (with real credentials)
   ├── auth.php ✅
   ├── utils.php ✅
   └── actions/ ✅
2. Test API:


    - Visit: https://yourdomain.com/api.php
    - Should see: {"success":false,"error":"Action is required"}
    - This means API is working!

3. Test Database Connection:
Create public_html/test_db.php:
<?php
require_once 'includes/db.php';
try {
    $pdo = getDbConnection();
    echo "✅ Database connected!";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
4. Visit: https://yourdomain.com/test_db.php
   Should see: "✅ Database connected!"

---

🎯 Quick Setup Checklist

- Generated GitHub Personal Access Token
- Used HTTPS URL (not SSH)
- Created repository in cPanel Git
- Authenticated with token (not password)
- Pulled code successfully
- Copied api.php to public_html/
- Copied includes/ to public_html/
- Updated includes/db.php with real database credentials
- Tested API endpoint
- Tested database connection

---

🔄 Future Updates Workflow

Once this is working, when you push changes to GitHub:

# On your computer

git push origin main

# In cPanel Git Version Control

Click "Manage" → "Pull or Deploy" → "Update from Remote"

# Copy updated files to public_html

cp ~/repositories/maritime-exam-portal/api.php ~/public_html/
cp -r ~/repositories/maritime-exam-portal/includes ~/public_html/

---

💡 Why HTTPS is Better for cPanel

| Method | Pros | Cons
|
|-----------------|----------------------------------------|----------------------------------  
 ---------------------------------|
| HTTPS + Token ✅ | Easy setup, reliable, works everywhere | Need to renew token every 90
days |
| SSH Keys | No expiration | Complex setup, permission issues,  
 doesn't always work with cPanel |

Recommendation: Use HTTPS!

---

📞 Still Having Issues?

If you still get errors, tell me:

1. The exact error message from cPanel
2. The URL you're using (HTTPS or SSH?)
3. Whether you're using Personal Access Token or password

I'll help you fix it! 🚀

---

Try the HTTPS method above - it should work perfectly with cPanel! ✅
