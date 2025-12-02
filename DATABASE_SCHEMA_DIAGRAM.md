# DATABASE SCHEMA - VISUAL DIAGRAM

## 📊 Entity Relationship Diagram (Text Format)

```
┌─────────────────────────────────────────────────────────────────────┐
│                         USERS TABLE                                  │
├─────────────────────────────────────────────────────────────────────┤
│ PK │ id (INT)                                                        │
│    │ email (VARCHAR) UNIQUE                                          │
│    │ password_hash (VARCHAR)                                         │
│    │ first_name (VARCHAR)                                            │
│    │ last_name (VARCHAR)                                             │
│    │ role (ENUM: 'USER', 'ADMIN')                                    │
│    │ is_suspended (BOOLEAN)                                          │
│    │ created_at (TIMESTAMP)                                          │
│    │ updated_at (TIMESTAMP)                                          │
│    │ last_login (TIMESTAMP)                                          │
└──┬───────────────────────┬──────────────────────┬───────────────────┘
   │                       │                      │
   │ 1                     │ 1                    │ 1
   │                       │                      │
   │ N                     │ N                    │ N
   │                       │                      │
┌──┴─────────────┐  ┌──────┴──────────────┐  ┌───┴───────────────────┐
│ ACCESS_REQUESTS│  │  USER_CATEGORIES    │  │   TEST_SESSIONS       │
├────────────────┤  ├─────────────────────┤  ├───────────────────────┤
│PK│id           │  │PK│id                │  │PK│id                  │
│FK│user_id      │  │FK│user_id           │  │FK│user_id             │
│FK│category_id  │  │FK│category_id       │  │FK│category_id         │
│FK│package_id   │  │  │granted_at        │  │  │start_time          │
│  │status       │  │  │expires_at        │  │  │end_time            │
│  │requested_at │  │FK│granted_by        │  │  │duration_seconds    │
│  │processed_at │  └─────────────────────┘  │  │score               │
│FK│processed_by │           │               │  │total_questions     │
│  │notes        │           │               │  │percentage          │
└────────┬───────┘           │               │  │grade               │
         │                   │               │  │is_completed        │
         │                   │               │  │questions_data(JSON)│
         │                   │               └───┬───────────────────┘
         │                   │                   │
         │                   │                   │ 1
         │                   │                   │
         │                   │                   │ N
         │                   │                   │
         │                   │               ┌───┴───────────────────┐
         │                   │               │   TEST_ANSWERS        │
         │                   │               ├───────────────────────┤
         │                   │               │PK│id                  │
         │                   │               │FK│session_id          │
         │                   │               │FK│question_id         │
         │                   │               │  │selected_answer     │
         │                   │               │  │is_correct          │
         │                   │               │  │answered_at         │
         │                   │               └───────┬───────────────┘
         │                   │                       │
         │ FK                │ FK                    │ FK
         │                   │                       │
         ▼                   ▼                       ▼
┌─────────────────────────────────────────────────────────────────────┐
│                       CATEGORIES TABLE                               │
├─────────────────────────────────────────────────────────────────────┤
│ PK │ id (INT)                                                        │
│    │ name_bg (VARCHAR)                                               │
│    │ name_en (VARCHAR)                                               │
│    │ price (DECIMAL)                                                 │
│    │ duration_days (INT)                                             │
│    │ question_count (INT)                                            │
│    │ exam_duration_minutes (INT)                                     │
│    │ is_active (BOOLEAN)                                             │
│    │ created_at (TIMESTAMP)                                          │
│    │ updated_at (TIMESTAMP)                                          │
└──┬──────────────────────────────────────────────┬───────────────────┘
   │                                              │
   │ 1                                            │ N
   │                                              │
   │ N                                            ▼
   │                                     ┌────────────────────┐
   │                                     │    QUESTIONS       │
   │                                     ├────────────────────┤
   │                                     │PK│id               │
   │                                     │FK│category_id      │
   │                                     │  │original_index   │
   │                                     │  │question_text    │
   │                                     │  │option_a         │
   │                                     │  │option_b         │
   │                                     │  │option_c         │
   │                                     │  │option_d         │
   │                                     │  │correct_answer   │
   │                                     │  │image_filename   │
   │                                     │  │created_at       │
   │                                     │  │updated_at       │
   │                                     └────────────────────┘
   │
   ▼
┌───────────────────────────────────────┐
│     PACKAGE_CATEGORIES (Junction)     │
├───────────────────────────────────────┤
│ FK │ package_id                       │
│ FK │ category_id                      │
│    │ PRIMARY KEY (both)               │
└───────────┬───────────────────────────┘
            │
            │ FK
            │
            ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         PACKAGES TABLE                               │
├─────────────────────────────────────────────────────────────────────┤
│ PK │ id (INT)                                                        │
│    │ name_bg (VARCHAR)                                               │
│    │ name_en (VARCHAR)                                               │
│    │ description_bg (TEXT)                                           │
│    │ description_en (TEXT)                                           │
│    │ price (DECIMAL)                                                 │
│    │ duration_days (INT)                                             │
│    │ is_active (BOOLEAN)                                             │
│    │ created_at (TIMESTAMP)                                          │
│    │ updated_at (TIMESTAMP)                                          │
└─────────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────────┐
│                         SETTINGS TABLE                               │
├─────────────────────────────────────────────────────────────────────┤
│ PK │ id (INT)                                                        │
│    │ setting_key (VARCHAR) UNIQUE                                    │
│    │ setting_value (TEXT)                                            │
│    │ description (VARCHAR)                                           │
│    │ updated_at (TIMESTAMP)                                          │
└─────────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────────┐
│                       AUDIT_LOG TABLE                                │
├─────────────────────────────────────────────────────────────────────┤
│ PK │ id (INT)                                                        │
│ FK │ user_id (INT) - references users.id                             │
│    │ action (VARCHAR)                                                │
│    │ entity_type (VARCHAR)                                           │
│    │ entity_id (INT)                                                 │
│    │ details (JSON)                                                  │
│    │ ip_address (VARCHAR)                                            │
│    │ created_at (TIMESTAMP)                                          │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 🔗 RELATIONSHIP SUMMARY

### One-to-Many Relationships (1:N)

1. **USERS → ACCESS_REQUESTS**
   - One user can make many access requests
   - `users.id` → `access_requests.user_id`

2. **USERS → USER_CATEGORIES**
   - One user can have access to many categories
   - `users.id` → `user_categories.user_id`

3. **USERS → TEST_SESSIONS**
   - One user can have many test sessions
   - `users.id` → `test_sessions.user_id`

4. **CATEGORIES → QUESTIONS**
   - One category has many questions
   - `categories.id` → `questions.category_id`

5. **CATEGORIES → ACCESS_REQUESTS**
   - One category can be requested many times
   - `categories.id` → `access_requests.category_id`

6. **CATEGORIES → USER_CATEGORIES**
   - One category can be assigned to many users
   - `categories.id` → `user_categories.category_id`

7. **CATEGORIES → TEST_SESSIONS**
   - One category can have many test sessions
   - `categories.id` → `test_sessions.category_id`

8. **PACKAGES → ACCESS_REQUESTS**
   - One package can be requested many times
   - `packages.id` → `access_requests.package_id`

9. **TEST_SESSIONS → TEST_ANSWERS**
   - One test session has many answers (60 answers)
   - `test_sessions.id` → `test_answers.session_id`

10. **QUESTIONS → TEST_ANSWERS**
    - One question can be answered many times
    - `questions.id` → `test_answers.question_id`

11. **USERS (as admin) → ACCESS_REQUESTS (processed_by)**
    - One admin can process many requests
    - `users.id` → `access_requests.processed_by`

12. **USERS (as admin) → USER_CATEGORIES (granted_by)**
    - One admin can grant many category accesses
    - `users.id` → `user_categories.granted_by`

### Many-to-Many Relationships (M:N)

1. **PACKAGES ↔ CATEGORIES** (via PACKAGE_CATEGORIES)
   - One package contains many categories
   - One category can be in many packages
   - Junction table: `package_categories`

---

## 📋 TABLE DETAILS

### USERS (User Accounts)
**Purpose:** Store all user accounts (both students and administrators)
**Primary Key:** `id`
**Unique Keys:** `email`
**Indexes:** email, role, is_suspended

### CATEGORIES (Exam Categories/Functions)
**Purpose:** Store all exam categories (Navigation, Cargo handling, etc.)
**Primary Key:** `id`
**Indexes:** is_active, FULLTEXT(name_bg, name_en)
**Sample Count:** 19+ categories

### PACKAGES (Category Bundles)
**Purpose:** Store bundled offers with multiple categories at discounted price
**Primary Key:** `id`
**Example:** "Full Package - Operational Level" includes 3+ categories for €150

### PACKAGE_CATEGORIES (Junction Table)
**Purpose:** Link packages to their included categories
**Primary Key:** Composite (package_id, category_id)
**Relationship:** M:N between packages and categories

### QUESTIONS (Exam Questions)
**Purpose:** Store all exam questions for all categories
**Primary Key:** `id`
**Foreign Keys:** category_id
**Indexes:** category_id, (category_id + original_index)
**Fields:**
- `original_index`: Position in original question set (used for 25% distribution)
- `image_filename`: Optional PNG image (e.g., "nav1.png")
- `correct_answer`: ENUM('A','B','C','D')

### ACCESS_REQUESTS (User Requests)
**Purpose:** Track user requests for category/package access
**Primary Key:** `id`
**Foreign Keys:** user_id, category_id, package_id
**Status Values:** PENDING, APPROVED, REJECTED
**Constraint:** Either category_id OR package_id must be set (not both)

### USER_CATEGORIES (Access Grants)
**Purpose:** Track which categories users have access to and when they expire
**Primary Key:** `id`
**Foreign Keys:** user_id, category_id, granted_by
**Unique:** (user_id, category_id) - user can't have duplicate access to same category
**Important Fields:**
- `granted_at`: When access was granted
- `expires_at`: When access expires (checked before allowing test)
- `granted_by`: Which admin approved the request

### TEST_SESSIONS (Test Attempts)
**Purpose:** Store each test attempt with metadata and results
**Primary Key:** `id`
**Foreign Keys:** user_id, category_id
**Important Fields:**
- `questions_data`: JSON array of 60 question IDs in order
- `start_time`: When test started
- `end_time`: When test finished
- `duration_seconds`: Actual time taken
- `score`: Number of correct answers (0-60)
- `percentage`: Score percentage (0.00-100.00)
- `grade`: Text grade (Fail, Pass, Good, Very Good, Excellent)
- `is_completed`: Whether test was submitted

### TEST_ANSWERS (Individual Answers)
**Purpose:** Store each answer in a test session
**Primary Key:** `id`
**Foreign Keys:** session_id, question_id
**Unique:** (session_id, question_id) - one answer per question per test
**Fields:**
- `selected_answer`: User's choice (A/B/C/D)
- `is_correct`: Whether answer was correct (calculated on completion)
- `answered_at`: Timestamp when answered

### SETTINGS (Global Settings)
**Purpose:** Store application-wide settings
**Primary Key:** `id`
**Unique:** `setting_key`
**Sample Settings:**
- `revolut_payment_link`: Payment URL
- `facebook_link`: Social media link
- `announcement_text`: Banner text
- `site_name_bg`, `site_name_en`: Site titles
- `max_test_attempts`: Limit per category (0=unlimited)
- `passing_score`: Minimum percentage to pass

### AUDIT_LOG (Security/Admin Actions)
**Purpose:** Track important admin actions for security and compliance
**Primary Key:** `id`
**Foreign Keys:** user_id
**Fields:**
- `action`: What was done (e.g., "APPROVE_REQUEST", "SUSPEND_USER")
- `entity_type`: What was affected (e.g., "user", "category")
- `entity_id`: ID of affected entity
- `details`: JSON with additional info
- `ip_address`: IP of admin who performed action

---

## 📊 USEFUL VIEWS (Pre-created)

### v_user_active_categories
**Purpose:** Quick lookup of active category assignments
**Columns:** user info, category info, status (ACTIVE/EXPIRED), days_remaining
**Use Case:** Display user's "My Tests" page

### v_user_test_stats
**Purpose:** Test performance statistics by user and category
**Columns:** total_attempts, avg_percentage, best_percentage, passed_attempts
**Use Case:** Analytics and reporting

### v_pending_requests
**Purpose:** All pending access requests with details
**Columns:** user info, category/package info, prices, requested_at
**Use Case:** Admin "Requests" tab

---

## 🔍 SAMPLE QUERIES

### Get User's Active Categories
```sql
SELECT * FROM v_user_active_categories
WHERE user_id = ? AND status = 'ACTIVE';
```

### Get Pending Requests for Admin
```sql
SELECT * FROM v_pending_requests
ORDER BY requested_at ASC;
```

### Check if User Can Take Test
```sql
SELECT * FROM user_categories
WHERE user_id = ? AND category_id = ? AND expires_at > NOW();
```

### Get Test History for User
```sql
SELECT ts.*, c.name_en, c.name_bg
FROM test_sessions ts
JOIN categories c ON ts.category_id = c.id
WHERE ts.user_id = ? AND ts.is_completed = TRUE
ORDER BY ts.start_time DESC
LIMIT 20;
```

### Get Questions for Test Generation
```sql
SELECT * FROM questions
WHERE category_id = ?
ORDER BY original_index;
-- Then apply 25% distribution in PHP
```

### Calculate Test Score
```sql
SELECT
  COUNT(*) as total,
  SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct
FROM test_answers
WHERE session_id = ?;
```

---

## 📈 INDEX STRATEGY

### Primary Indexes (Auto-created)
- All `id` columns (primary keys)

### Foreign Key Indexes (Auto-created)
- All foreign key columns for JOIN performance

### Custom Indexes
- `users.email` (UNIQUE) - Fast login lookup
- `users.role` - Filter by role
- `users.is_suspended` - Filter active users
- `categories.is_active` - Filter active categories
- `questions.category_id` - Fast question lookup
- `questions.(category_id, original_index)` - 25% distribution
- `test_sessions.user_id` - User's test history
- `test_sessions.is_completed` - Filter completed tests
- `access_requests.status` - Filter pending requests

### Full-Text Indexes
- `categories.(name_bg, name_en)` - Search categories by name

---

## 💾 STORAGE ESTIMATES

### Small Deployment (100 users, 10 categories)
- Users: ~10 KB
- Categories: ~5 KB
- Questions (400 per category): ~1-2 MB
- Test Sessions (10 per user): ~100 KB
- Test Answers (600 per user): ~500 KB
- **Total:** ~3-4 MB

### Medium Deployment (1,000 users, 20 categories)
- Users: ~100 KB
- Categories: ~10 KB
- Questions (400 per category): ~3-4 MB
- Test Sessions (10 per user): ~1 MB
- Test Answers (600 per user): ~5 MB
- **Total:** ~10-15 MB

### Large Deployment (10,000 users, 20 categories)
- Users: ~1 MB
- Categories: ~10 KB
- Questions: ~4 MB
- Test Sessions: ~10 MB
- Test Answers: ~50 MB
- **Total:** ~70-100 MB

**Image Storage:**
- 400 questions × 20 categories × 100KB per image ≈ **800 MB**

---

## 🔄 DATA LIFECYCLE

### User Registration → Test Completion Flow

```
1. User registers
   ├─ INSERT INTO users

2. User browses categories
   ├─ SELECT FROM categories WHERE is_active = TRUE

3. User requests access
   ├─ INSERT INTO access_requests (status=PENDING)

4. Admin approves
   ├─ UPDATE access_requests (status=APPROVED)
   └─ INSERT INTO user_categories (expires_at = NOW() + duration)

5. User generates test
   ├─ SELECT FROM questions WHERE category_id = ?
   ├─ Apply 25% distribution algorithm
   ├─ INSERT INTO test_sessions (questions_data = JSON)
   └─ Return 60 questions

6. User answers questions
   ├─ INSERT/UPDATE test_answers (per question)

7. User completes test (or timer expires)
   ├─ SELECT questions with correct_answer
   ├─ UPDATE test_answers (is_correct = comparison)
   ├─ Calculate score, percentage, grade
   └─ UPDATE test_sessions (is_completed=TRUE, score, grade)

8. User views results
   ├─ SELECT FROM test_sessions JOIN test_answers
```

---

## END OF DATABASE SCHEMA DIAGRAM
