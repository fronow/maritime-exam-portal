# ⚡ QUICK START GUIDE - Maritime Exam Portal

## 🎯 **What You Have**

✅ Complete PHP backend (18 API endpoints)
✅ React frontend (fully responsive)
✅ MySQL database schema
✅ GitHub integration ready
✅ Documentation complete

---

## 🚀 **3-Step Quick Deploy**

### **1️⃣ DATABASE (5 min)**
```
cPanel → phpMyAdmin → Import schema.sql → Done!
```

### **2️⃣ BACKEND (5 min)**
```
Edit includes/db.php (add DB credentials)
Upload api.php + includes/ to public_html/
```

### **3️⃣ FRONTEND (5 min)**
```
npm run build
Upload dist/ contents to public_html/
```

**🎉 Done! Visit your site!**

---

## 📁 **Important Files**

| File | Purpose |
|------|---------|
| `BUILD_COMPLETE.md` | What was built ⭐ START HERE |
| `DEPLOYMENT_GUIDE.md` | Step-by-step deployment |
| `GITHUB_CPANEL_SETUP.md` | GitHub + cPanel integration |
| `schema.sql` | Database setup |
| `includes/db.php` | Configure database credentials |

---

## 🔧 **Critical Config**

**Must Update:**
1. `includes/db.php` lines 17-20 → Database credentials
2. `includes/auth.php` line 20 → Change secret key
3. `services/storageService.ts` line 6 → Production URL

---

## 🔗 **GitHub Setup (Optional but Recommended)**

**Quick Steps:**
```bash
# 1. Create GitHub repo (private)
# 2. Local computer:
git init
git add .
git commit -m "Initial commit"
git remote add origin https://github.com/YOUR_USERNAME/maritime-exam-portal.git
git push -u origin main

# 3. cPanel → Git Version Control → Clone from GitHub
```

**Full guide:** See `GITHUB_CPANEL_SETUP.md`

---

## ✅ **After Deployment**

1. Change admin password
2. Import questions (Excel)
3. Test complete flow:
   - Register → Request access → Admin approve → Generate test → Complete test
4. Configure payment link in Settings

---

## 🆘 **Need Help?**

**Error?** Check:
1. Browser console (F12)
2. `logs/api_errors.log` on server
3. `DEPLOYMENT_GUIDE.md` troubleshooting section

---

## 📊 **Test the 25% Distribution**

```sql
-- After generating a test, verify questions come from correct ranges:
SELECT ta.question_id, q.original_index
FROM test_answers ta
JOIN questions q ON ta.question_id = q.id
WHERE ta.session_id = YOUR_SESSION_ID
ORDER BY ta.id;

-- Questions 1-15 should have original_index 1-100
-- Questions 16-30 should have original_index 101-200
-- Questions 31-45 should have original_index 201-300
-- Questions 46-60 should have original_index 301-400
```

---

## 🎉 **You're Ready!**

**Time to deploy:** ~60 minutes
**Documentation:** Complete
**Support:** All guides included

**Let's go! 🚀**

---

## 📞 **Quick Links**

- Admin login: `https://yourdomain.com` → admin@maritime.com
- Test API: `https://yourdomain.com/api.php`
- phpMyAdmin: cPanel → phpMyAdmin
- File Manager: cPanel → File Manager
- Error logs: `public_html/logs/api_errors.log`

---

**Good luck with your deployment!** ⚓🚢
