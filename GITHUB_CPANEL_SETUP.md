# 🔗 GitHub + cPanel Integration Guide

## ✅ **Yes! You Can Connect cPanel with GitHub**

This is a **highly recommended** practice for:
- ✅ Version control and backup
- ✅ Easy updates and bug fixes
- ✅ Collaboration
- ✅ Rollback to previous versions
- ✅ Track changes over time

---

## 🎯 **Two Methods Available**

### **Method 1: cPanel Git Version Control** ⭐ (RECOMMENDED)
- Built into most cPanel hosts
- Easy push/pull with GUI
- Auto-deployment option
- **Best for: Most users**

### **Method 2: SSH + Manual Git**
- More control
- Command-line based
- Requires SSH access
- **Best for: Advanced users**

---

## 📋 **METHOD 1: cPanel Git Version Control Setup**

### **STEP 1: Create GitHub Repository** (5 min)

1. **Go to GitHub.com** and login
2. **Create New Repository:**
   - Name: `maritime-exam-portal`
   - Visibility: **Private** (recommended)
   - Don't initialize with README
   - Click "Create repository"

3. **Generate Personal Access Token:**
   - GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
   - Click "Generate new token (classic)"
   - Name: "cPanel Access"
   - Scopes: Select `repo` (full control)
   - Click "Generate token"
   - **⚠️ COPY THE TOKEN** (you won't see it again!)

4. **Note your repository details:**
   ```
   Repository URL: https://github.com/YOUR_USERNAME/maritime-exam-portal.git
   Personal Access Token: ghp_xxxxxxxxxxxxxxxxxxxx
   ```

---

### **STEP 2: Initialize Local Git Repository** (5 min)

On your local computer (D:\maritime-exam-portal):

```bash
# Navigate to project
cd D:\maritime-exam-portal

# Initialize git (if not already)
git init

# Add all files
git add .

# Commit
git commit -m "Initial commit - Maritime Exam Portal with complete backend"

# Add remote
git remote add origin https://github.com/YOUR_USERNAME/maritime-exam-portal.git

# Push to GitHub
git branch -M main
git push -u origin main
```

**Or use Git GUI:**
- Install GitHub Desktop (https://desktop.github.com/)
- Open GitHub Desktop
- File → Add Local Repository
- Select `D:\maritime-exam-portal`
- Publish to GitHub

---

### **STEP 3: Setup Git in cPanel** (10 min)

1. **Login to cPanel**

2. **Find "Git Version Control":**
   - Usually in "Files" section
   - If not visible, search "Git" in search bar
   - ⚠️ If not available, your host may not support it → Use Method 2

3. **Create New Repository:**
   - Click "Create"
   - Fill in:
     - **Clone URL:** `https://github.com/YOUR_USERNAME/maritime-exam-portal.git`
     - **Repository Path:** `/home/YOUR_CPANEL_USERNAME/repositories/maritime-exam-portal`
     - **Repository Name:** `maritime-exam-portal`
   - Click "Create"

4. **Authenticate:**
   - If prompted, enter:
     - Username: Your GitHub username
     - Password: Your Personal Access Token (ghp_xxxx)

5. **Pull Latest Code:**
   - Click "Manage" next to your repository
   - Click "Pull or Deploy" → "Update from Remote"
   - Code will be downloaded to `repositories/maritime-exam-portal/`

---

### **STEP 4: Deploy to Public Directory** (5 min)

**Option A: Symlink (Recommended)**
```bash
# SSH into cPanel (cPanel → Terminal)
cd ~/public_html

# Backup existing files
mv api.php api.php.backup
mv includes includes.backup

# Create symlinks
ln -s ~/repositories/maritime-exam-portal/api.php api.php
ln -s ~/repositories/maritime-exam-portal/includes includes

# Copy database config (not in Git)
cp ~/repositories/maritime-exam-portal/includes/db.example.php ~/repositories/maritime-exam-portal/includes/db.php
# Now edit db.php with real credentials
nano ~/repositories/maritime-exam-portal/includes/db.php
```

**Option B: Manual Copy (Simpler)**
```bash
# Copy files to public_html
cp -r ~/repositories/maritime-exam-portal/api.php ~/public_html/
cp -r ~/repositories/maritime-exam-portal/includes ~/public_html/

# Update database config
nano ~/public_html/includes/db.php
```

---

### **STEP 5: Configure Auto-Deployment** (Optional)

Enable automatic deployment when you push to GitHub:

1. **In cPanel Git Version Control:**
   - Click "Manage" next to your repository
   - Enable "Pull on Deploy"
   - Copy the webhook URL shown

2. **In GitHub Repository:**
   - Settings → Webhooks → Add webhook
   - Payload URL: (paste cPanel webhook URL)
   - Content type: `application/json`
   - Events: Just the push event
   - Click "Add webhook"

Now every time you push to GitHub, cPanel automatically pulls the changes!

---

## 🔄 **WORKFLOW: How to Update Your Live Site**

### **Making Changes and Deploying:**

1. **On Local Computer:**
   ```bash
   # Make your changes to files
   # Test locally: npm run dev

   # Stage changes
   git add .

   # Commit
   git commit -m "Fix: Corrected 25% distribution bug"

   # Push to GitHub
   git push origin main
   ```

2. **On cPanel (if auto-deploy disabled):**
   - Git Version Control → Manage
   - Click "Pull or Deploy" → "Update from Remote"

3. **Verify:**
   - Visit your site: `https://yourdomain.com`
   - Test the fix

---

## 📋 **METHOD 2: SSH + Manual Git Setup**

If cPanel Git Version Control is not available:

### **STEP 1: Enable SSH Access**

1. **cPanel → SSH Access**
2. **Generate SSH Key** (if needed)
3. **Add public key to GitHub:**
   - GitHub → Settings → SSH keys → New SSH key
   - Paste public key from cPanel

### **STEP 2: Clone Repository**

```bash
# SSH into your server
ssh username@yourserver.com

# Navigate to home directory
cd ~

# Create repositories directory
mkdir -p repositories
cd repositories

# Clone your repository
git clone git@github.com:YOUR_USERNAME/maritime-exam-portal.git

# Copy to public_html
cd maritime-exam-portal
cp -r api.php includes ~/public_html/

# Configure database
cp ~/public_html/includes/db.example.php ~/public_html/includes/db.php
nano ~/public_html/includes/db.php
```

### **STEP 3: Update Workflow**

```bash
# SSH into server
ssh username@yourserver.com

# Pull latest changes
cd ~/repositories/maritime-exam-portal
git pull origin main

# Copy updated files
cp -r api.php includes ~/public_html/
```

---

## 🔐 **IMPORTANT: Security Best Practices**

### **Files to NEVER Commit to GitHub:**

✅ **Already protected by .gitignore:**
- `includes/db.php` (contains database credentials)
- `.env` files
- `logs/` directory
- `node_modules/`

### **Safe to Commit:**
- All frontend code (`src/`, `components/`, etc.)
- Backend logic (`api.php`, `includes/actions/`)
- `includes/db.example.php` (template)
- Documentation files
- `schema.sql`

### **Best Practice:**

1. **Create `includes/db.example.php`** (already done for you!)
   - Template with placeholder credentials
   - This gets committed to GitHub

2. **Never commit `includes/db.php`**
   - Contains real database credentials
   - Listed in `.gitignore`
   - Each environment (local, production) has its own

3. **After cloning/pulling:**
   ```bash
   cp includes/db.example.php includes/db.php
   nano includes/db.php  # Add real credentials
   ```

---

## 🎯 **RECOMMENDED WORKFLOW**

### **Development Flow:**

```
┌─────────────┐
│ Local PC    │ ← Make changes here
│ (Windows)   │
└──────┬──────┘
       │ git push
       ▼
┌─────────────┐
│   GitHub    │ ← Version control & backup
│ (Remote)    │
└──────┬──────┘
       │ git pull (manual or auto)
       ▼
┌─────────────┐
│   cPanel    │ ← Production server
│ (Live Site) │
└─────────────┘
```

### **For Bug Fixes:**

1. **Find bug on live site**
2. **Pull latest code to local:**
   ```bash
   git pull origin main
   ```
3. **Fix bug locally**
4. **Test locally:**
   ```bash
   npm run dev
   ```
5. **Commit and push:**
   ```bash
   git add .
   git commit -m "Fix: Issue with test timer"
   git push origin main
   ```
6. **Deploy to cPanel:**
   - Auto-deploy: Automatic!
   - Manual: cPanel Git → Pull

---

## 📝 **USEFUL GIT COMMANDS**

### **Daily Commands:**
```bash
# Check status
git status

# See what changed
git diff

# Add all changes
git add .

# Commit with message
git commit -m "Your message here"

# Push to GitHub
git push origin main

# Pull latest from GitHub
git pull origin main
```

### **View History:**
```bash
# See commit history
git log --oneline

# See changes in specific commit
git show COMMIT_HASH
```

### **Undo Changes:**
```bash
# Discard local changes (not committed)
git checkout -- filename.php

# Undo last commit (keep changes)
git reset --soft HEAD~1

# Revert to specific commit
git revert COMMIT_HASH
```

### **Branching (Advanced):**
```bash
# Create new branch for feature
git checkout -b feature-new-grading

# Work on feature...
git add .
git commit -m "Add new grading system"

# Switch back to main
git checkout main

# Merge feature
git merge feature-new-grading

# Push branch to GitHub
git push origin feature-new-grading
```

---

## 🚨 **TROUBLESHOOTING**

### **Problem: "Permission denied (publickey)"**
**Solution:**
- Generate SSH key in cPanel
- Add public key to GitHub (Settings → SSH keys)
- Or use HTTPS with Personal Access Token

### **Problem: "Authentication failed"**
**Solution:**
- Using HTTPS: Use Personal Access Token, not password
- Generate token: GitHub → Settings → Developer settings → Personal access tokens

### **Problem: "Git Version Control not available in cPanel"**
**Solution:**
- Contact your hosting provider to enable it
- Or use Method 2 (SSH + Manual Git)

### **Problem: "Changes not showing on live site"**
**Solution:**
- Did you pull in cPanel?
- Are you editing the right files? (check symlinks)
- Clear browser cache (Ctrl+Shift+R)
- Check file permissions (should be 644 for files, 755 for directories)

---

## ✅ **VERIFICATION CHECKLIST**

After setup, verify:

**GitHub:**
- [ ] Repository created and private
- [ ] Code pushed to GitHub
- [ ] `.gitignore` working (db.php not visible)
- [ ] Personal Access Token saved securely

**cPanel:**
- [ ] Git repository cloned
- [ ] Files deployed to public_html
- [ ] Database config (db.php) updated with real credentials
- [ ] Site working: https://yourdomain.com
- [ ] Auto-deploy webhook configured (optional)

**Workflow:**
- [ ] Can make changes locally
- [ ] Can push to GitHub
- [ ] Can pull to cPanel
- [ ] Changes appear on live site

---

## 🎯 **EXAMPLE: Complete Update Workflow**

Let's say you find a bug where the timer doesn't stop at 0:

### **1. Local Development:**
```bash
# Pull latest code
git pull origin main

# Create feature branch (optional but recommended)
git checkout -b fix-timer-bug

# Fix the bug in src/components/ExamView.tsx
# Test locally
npm run dev

# Everything works!
git add src/components/ExamView.tsx
git commit -m "Fix: Timer now stops at 0 and auto-submits test"

# Push to GitHub
git push origin fix-timer-bug
```

### **2. Deploy to Production:**

**Option A: Via cPanel Git (if auto-deploy enabled):**
- Changes deploy automatically!
- Check site: https://yourdomain.com

**Option B: Via cPanel Git (manual):**
- cPanel → Git Version Control
- Manage → Pull or Deploy
- Select branch: `fix-timer-bug`
- Update from Remote

**Option C: Via SSH:**
```bash
ssh username@yourserver.com
cd ~/repositories/maritime-exam-portal
git pull origin fix-timer-bug
# Frontend: rebuild
npm run build
cp -r dist/* ~/public_html/
```

### **3. Verify Fix:**
- Visit site
- Start a test
- Let timer run to 0
- Verify auto-submit works!

### **4. Merge to Main (if using branches):**
```bash
# Locally
git checkout main
git merge fix-timer-bug
git push origin main

# Delete feature branch
git branch -d fix-timer-bug
git push origin --delete fix-timer-bug
```

---

## 🎁 **BONUS: Useful GitHub Features**

### **1. GitHub Issues**
Track bugs and features:
- Create issue: "Bug: Timer doesn't stop"
- Reference in commit: `git commit -m "Fix: Timer stops at 0 (fixes #1)"`
- Issue auto-closes when merged!

### **2. GitHub Projects**
- Create project board
- Track tasks: To Do → In Progress → Done
- Great for planning features

### **3. Release Tags**
Mark stable versions:
```bash
git tag -a v1.0.0 -m "First production release"
git push origin v1.0.0
```

### **4. README.md**
- Update with setup instructions
- Add screenshots
- Document API endpoints

---

## 📚 **SUMMARY**

**✅ You CAN connect cPanel with GitHub!**

**Best Approach:**
1. Use cPanel Git Version Control (if available)
2. Enable auto-deployment webhooks
3. Develop locally → Push to GitHub → Auto-deploy to cPanel

**Workflow:**
```
Edit locally → Test → Commit → Push → Auto-deploy! 🚀
```

**Benefits:**
- ✅ Version control and history
- ✅ Easy rollback if something breaks
- ✅ Collaborate with others
- ✅ Automatic backups
- ✅ Professional workflow

---

**Your project is now ready for professional version control!** 🎉

Need help with setup? Just ask!
