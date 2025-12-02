# BACKEND API IMPLEMENTATION PLAN

## Overview
This document outlines the complete PHP backend API implementation for the Maritime Exam Portal.

---

## 🔧 **API ENDPOINT STRUCTURE**

**Single Endpoint:** `/api.php`
**Method:** POST (for most operations), GET (for data fetching)
**Request Format:** JSON
**Response Format:** JSON

### Request Structure
```json
{
  "action": "action_name",
  "data": { ... },
  "session_token": "optional_auth_token"
}
```

### Response Structure
```json
{
  "success": true|false,
  "data": { ... },
  "message": "Optional message",
  "error": "Error details if success=false"
}
```

---

## 📋 **COMPLETE API ACTIONS**

### 1. AUTHENTICATION ACTIONS

#### `register`
**Purpose:** Create new user account
**Request:**
```json
{
  "action": "register",
  "data": {
    "email": "user@example.com",
    "password": "password123",
    "first_name": "John",
    "last_name": "Doe"
  }
}
```
**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "email": "user@example.com",
      "first_name": "John",
      "last_name": "Doe",
      "role": "USER"
    },
    "session_token": "encrypted_token_here"
  }
}
```
**Logic:**
- Validate email format
- Check if email already exists
- Hash password with `password_hash()`
- Insert into users table
- Create session token
- Return user data (NO password)

---

#### `login`
**Purpose:** Authenticate user
**Request:**
```json
{
  "action": "login",
  "data": {
    "email": "user@example.com",
    "password": "password123"
  }
}
```
**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "email": "user@example.com",
      "first_name": "John",
      "last_name": "Doe",
      "role": "USER",
      "is_suspended": false
    },
    "session_token": "encrypted_token_here"
  }
}
```
**Logic:**
- Find user by email
- Verify password with `password_verify()`
- Check if user is suspended
- Update last_login timestamp
- Create/update session token
- Return user data + token

---

### 2. DATA LOADING ACTIONS

#### `get_initial_data`
**Purpose:** Load all public data for app initialization
**Request:**
```json
{
  "action": "get_initial_data"
}
```
**Response:**
```json
{
  "success": true,
  "data": {
    "categories": [...],
    "packages": [...],
    "settings": {
      "revolut_payment_link": "...",
      "facebook_link": "...",
      "announcement_text": "..."
    }
  }
}
```
**Logic:**
- Fetch all active categories
- Fetch all active packages with category relationships
- Fetch global settings
- No authentication required

---

#### `get_user_data`
**Purpose:** Load user-specific data (categories, test history)
**Auth:** Required
**Request:**
```json
{
  "action": "get_user_data",
  "session_token": "..."
}
```
**Response:**
```json
{
  "success": true,
  "data": {
    "approved_categories": [...],
    "requested_categories": [...],
    "test_sessions": [...],
    "category_expiry": {
      "1": "2025-12-31T23:59:59Z",
      "2": "2025-12-31T23:59:59Z"
    }
  }
}
```

---

#### `get_admin_data`
**Purpose:** Load all admin panel data
**Auth:** Admin only
**Request:**
```json
{
  "action": "get_admin_data",
  "session_token": "..."
}
```
**Response:**
```json
{
  "success": true,
  "data": {
    "users": [...],
    "pending_requests": [...],
    "all_categories": [...],
    "all_packages": [...],
    "settings": {...}
  }
}
```

---

### 3. USER ACTIONS

#### `request_access`
**Purpose:** User requests access to categories/packages
**Auth:** User
**Request:**
```json
{
  "action": "request_access",
  "session_token": "...",
  "data": {
    "category_ids": [1, 2, 3],
    "package_ids": [1]
  }
}
```
**Response:**
```json
{
  "success": true,
  "message": "Access request submitted successfully"
}
```
**Logic:**
- For each category/package, create entry in access_requests table
- Status = PENDING
- Return success

---

#### `generate_test`
**Purpose:** Generate 60 random questions for a test
**Auth:** User
**Request:**
```json
{
  "action": "generate_test",
  "session_token": "...",
  "data": {
    "category_id": 1
  }
}
```
**Response:**
```json
{
  "success": true,
  "data": {
    "session_id": 123,
    "questions": [
      {
        "id": 45,
        "question_text": "...",
        "option_a": "...",
        "option_b": "...",
        "option_c": "...",
        "option_d": "...",
        "image_filename": "nav1.png"
      },
      // ... 59 more questions
    ],
    "start_time": "2025-12-01T10:00:00Z",
    "duration_minutes": 60
  }
}
```
**Logic:**
- Check user has access to category
- Check access not expired
- Get all questions for category
- Apply 25% distribution algorithm:
  - Divide questions into 4 equal chunks by original_index
  - Select 15 random from each chunk
  - Total = 60 questions
- Create test_session record
- Store question IDs in questions_data JSON field
- Return questions WITHOUT correct_answer

---

#### `submit_answer`
**Purpose:** Save answer for a question during test
**Auth:** User
**Request:**
```json
{
  "action": "submit_answer",
  "session_token": "...",
  "data": {
    "session_id": 123,
    "question_id": 45,
    "selected_answer": "B"
  }
}
```
**Response:**
```json
{
  "success": true
}
```
**Logic:**
- Verify session belongs to user
- Insert/update test_answers record
- Don't check correctness yet (revealed on completion)

---

#### `complete_test`
**Purpose:** Finalize test and calculate score
**Auth:** User
**Request:**
```json
{
  "action": "complete_test",
  "session_token": "...",
  "data": {
    "session_id": 123
  }
}
```
**Response:**
```json
{
  "success": true,
  "data": {
    "score": 48,
    "total_questions": 60,
    "percentage": 80.00,
    "grade": "Very Good",
    "answers": [
      {
        "question_id": 45,
        "selected_answer": "B",
        "correct_answer": "A",
        "is_correct": false
      },
      // ... all 60 answers
    ]
  }
}
```
**Logic:**
- Get all test_answers for session
- Compare with questions.correct_answer
- Update is_correct in test_answers
- Calculate score
- Calculate percentage
- Determine grade:
  - < 50%: Fail
  - 50-59%: Pass
  - 60-74%: Good
  - 75-89%: Very Good
  - 90-100%: Excellent
- Update test_session: is_completed=true, score, percentage, grade, end_time
- Return results with all answers

---

### 4. ADMIN ACTIONS

#### `approve_request`
**Purpose:** Admin approves category access request
**Auth:** Admin
**Request:**
```json
{
  "action": "approve_request",
  "session_token": "...",
  "data": {
    "request_id": 5,
    "duration_days": 365
  }
}
```
**Response:**
```json
{
  "success": true
}
```
**Logic:**
- Get request details
- Update access_requests: status=APPROVED, processed_at=NOW, processed_by=admin_id
- Calculate expiry: NOW + duration_days
- If category request:
  - Insert/update user_categories with expires_at
- If package request:
  - Insert/update user_categories for ALL categories in package
- Send notification email (optional)

---

#### `reject_request`
**Purpose:** Admin rejects access request
**Auth:** Admin
**Request:**
```json
{
  "action": "reject_request",
  "session_token": "...",
  "data": {
    "request_id": 5,
    "notes": "Payment not received"
  }
}
```
**Logic:**
- Update access_requests: status=REJECTED, processed_at=NOW, notes

---

#### `toggle_suspend_user`
**Purpose:** Suspend or activate user account
**Auth:** Admin
**Request:**
```json
{
  "action": "toggle_suspend_user",
  "session_token": "...",
  "data": {
    "user_id": 10,
    "suspend": true
  }
}
```
**Logic:**
- Update users.is_suspended
- Log to audit_log

---

#### `save_category`
**Purpose:** Create or update category
**Auth:** Admin
**Request:**
```json
{
  "action": "save_category",
  "session_token": "...",
  "data": {
    "id": null, // null for new, number for edit
    "name_bg": "...",
    "name_en": "...",
    "price": 25.00,
    "duration_days": 365,
    "exam_duration_minutes": 60
  }
}
```
**Logic:**
- If id is null: INSERT
- If id exists: UPDATE
- Return saved category

---

#### `save_package`
**Purpose:** Create or update package
**Auth:** Admin
**Request:**
```json
{
  "action": "save_package",
  "session_token": "...",
  "data": {
    "id": null,
    "name_bg": "...",
    "name_en": "...",
    "price": 150.00,
    "duration_days": 365,
    "category_ids": [1, 2, 3]
  }
}
```
**Logic:**
- INSERT/UPDATE packages table
- Delete old package_categories entries
- Insert new package_categories entries

---

#### `import_questions`
**Purpose:** Bulk import questions from Excel upload
**Auth:** Admin
**Request:**
```json
{
  "action": "import_questions",
  "session_token": "...",
  "data": {
    "category_id": 1,
    "questions": [
      {
        "original_index": 1,
        "question_text": "...",
        "option_a": "...",
        "option_b": "...",
        "option_c": "...",
        "option_d": "...",
        "correct_answer": "A",
        "image_filename": "nav1.png"
      },
      // ... more questions
    ]
  }
}
```
**Logic:**
- Option 1: DELETE all existing questions for category, then INSERT all
- Option 2: Upsert based on category_id + original_index
- Update categories.question_count
- Return count of imported questions

---

#### `save_settings`
**Purpose:** Update global settings
**Auth:** Admin
**Request:**
```json
{
  "action": "save_settings",
  "session_token": "...",
  "data": {
    "revolut_payment_link": "...",
    "facebook_link": "...",
    "announcement_text": "..."
  }
}
```
**Logic:**
- For each key-value pair, UPDATE settings table
- Use ON DUPLICATE KEY UPDATE for upsert

---

#### `change_user_password`
**Purpose:** Admin changes user password
**Auth:** Admin
**Request:**
```json
{
  "action": "change_user_password",
  "session_token": "...",
  "data": {
    "user_id": 10,
    "new_password": "newpassword123"
  }
}
```
**Logic:**
- Hash new password
- Update users.password_hash
- Log to audit_log

---

## 🔐 **AUTHENTICATION & SECURITY**

### Session Management
```php
// Option 1: PHP Sessions
session_start();
$_SESSION['user_id'] = $user['id'];
$_SESSION['role'] = $user['role'];

// Option 2: JWT Tokens (recommended)
// Use Firebase JWT library or simple token
$token = base64_encode(json_encode([
  'user_id' => $user['id'],
  'role' => $user['role'],
  'exp' => time() + (7 * 24 * 60 * 60) // 7 days
]));
```

### Middleware Functions
```php
function authenticateUser($token) {
  // Decode token or check session
  // Return user object or null
}

function requireAuth($token) {
  $user = authenticateUser($token);
  if (!$user) {
    return errorResponse('Unauthorized');
  }
  return $user;
}

function requireAdmin($token) {
  $user = requireAuth($token);
  if ($user['role'] !== 'ADMIN') {
    return errorResponse('Admin access required');
  }
  return $user;
}
```

### Input Sanitization
```php
function sanitizeInput($data) {
  if (is_array($data)) {
    return array_map('sanitizeInput', $data);
  }
  return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
```

### SQL Injection Prevention
```php
// Always use prepared statements
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
```

---

## 📁 **FILE STRUCTURE**

```
api.php               # Main endpoint, routes actions
includes/
  ├── db.php          # Database connection
  ├── auth.php        # Authentication functions
  ├── actions/
  │   ├── auth.php    # Login, register
  │   ├── user.php    # User actions
  │   ├── admin.php   # Admin actions
  │   └── test.php    # Test generation and submission
  └── utils.php       # Helper functions
```

---

## 🚀 **DEPLOYMENT CHECKLIST**

### 1. Database Setup
- [ ] Create MySQL database in cPanel
- [ ] Import schema.sql
- [ ] Change default admin password
- [ ] Update settings with real Revolut link

### 2. Backend Files
- [ ] Upload api.php and includes/ folder
- [ ] Update database credentials in db.php
- [ ] Set proper file permissions (755 for directories, 644 for files)
- [ ] Create uploads/temp/ directory with .htaccess to deny access

### 3. Frontend Configuration
- [ ] Build React app: `npm run build`
- [ ] Upload dist/ contents to public_html/
- [ ] Update API_URL in storageService.ts to production domain
- [ ] Upload images/questions/ folder with all PNG files

### 4. Security
- [ ] Enable HTTPS (SSL certificate in cPanel)
- [ ] Set secure CORS headers
- [ ] Implement rate limiting (optional)
- [ ] Set up regular database backups

### 5. Testing
- [ ] Test registration and login
- [ ] Test category request and approval
- [ ] Test question import
- [ ] Test exam generation (25% distribution)
- [ ] Test exam completion and scoring
- [ ] Test all admin functions
- [ ] Test on mobile devices

---

## 📊 **DATABASE QUERIES - KEY OPERATIONS**

### Generate Test (25% Distribution)
```sql
-- Get all questions for category
SELECT * FROM questions
WHERE category_id = ?
ORDER BY original_index;

-- In PHP: Divide into 4 chunks and select 15 random from each
```

### Check User Access
```sql
SELECT * FROM user_categories
WHERE user_id = ?
  AND category_id = ?
  AND expires_at > NOW();
```

### Get Pending Requests
```sql
SELECT ar.*, u.first_name, u.last_name, u.email,
       c.name_en, c.price
FROM access_requests ar
JOIN users u ON ar.user_id = u.id
LEFT JOIN categories c ON ar.category_id = c.id
WHERE ar.status = 'PENDING'
ORDER BY ar.requested_at ASC;
```

### Calculate Test Score
```sql
SELECT
  COUNT(*) as total_questions,
  SUM(CASE WHEN ta.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers
FROM test_answers ta
WHERE ta.session_id = ?;
```

---

## ⚡ **PERFORMANCE OPTIMIZATION**

1. **Database Indexes:** Already defined in schema
2. **Query Optimization:** Use JOINs instead of multiple queries
3. **Caching:** Cache settings and categories in memory
4. **Connection Pooling:** Reuse database connection
5. **Pagination:** Limit test_sessions results to recent 20

---

## 🛠️ **ERROR HANDLING**

```php
try {
  // Database operations
} catch (PDOException $e) {
  error_log($e->getMessage());
  return errorResponse('Database error occurred');
}

function errorResponse($message, $code = 400) {
  http_response_code($code);
  return json_encode([
    'success' => false,
    'error' => $message
  ]);
}

function successResponse($data = null, $message = null) {
  return json_encode([
    'success' => true,
    'data' => $data,
    'message' => $message
  ]);
}
```

---

## 📧 **OPTIONAL FEATURES**

### Email Notifications
- User registration confirmation
- Access request approved/rejected
- Test completion summary
- Password reset (future)

### Analytics
- Track most popular categories
- Average test scores per category
- User engagement metrics

---

## 🔄 **FUTURE ENHANCEMENTS**

1. **Payment Gateway Integration:** Automate Revolut payment verification
2. **Certificate Generation:** PDF certificates for passed exams
3. **Question Explanations:** Add explanations for correct answers
4. **Timed Question Review:** Allow users to review past tests
5. **Mobile App:** React Native version
6. **Advanced Analytics:** Detailed performance reports
7. **Multi-language Questions:** Support for Russian questions

---

## END OF BACKEND API PLAN
