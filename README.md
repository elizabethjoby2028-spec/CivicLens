# CivicLens — Report. Track. Resolve.

[![PHP Version](https://img.shields.io/badge/PHP-8.1%20%7C%208.2-blue.svg)](https://www.php.net/)
[![Database](https://img.shields.io/badge/MySQL-5.7%2B%20%7C%20MariaDB-orange.svg)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Security](https://img.shields.io/badge/Security-CSRF%20%7C%20Bcrypt%20%7C%20Prepared%20Statements-brightgreen.svg)]()

> A secure, responsive, full-stack civic issue reporting and municipal resolution platform built with PHP, MySQL, HTML5, Vanilla CSS, and JavaScript. Designed to run locally with XAMPP and deploy to production with modern security practices.

---

## 🏛️ Project Overview & Architecture

CivicLens streamlines the communication pipeline between citizens and municipal authorities across six essential civic categories:
1. **Water** (Leakages, contamination, pipeline bursts, supply disruptions)
2. **Road** (Potholes, broken signals, cratering, obstruction)
3. **Food** (Adulteration, expired goods, restaurant hygiene breaches)
4. **Waste** (Illegal dumping, overflowing bins, uncollected refuse)
5. **Drugs / Substance Abuse** (*Operation Thoofan* narcotics alerts & hotspots)
6. **Other Civic Issues** (Streetlighting, public parks, hazards)

### Core User Portals & Separation of Concerns

```
                                  [ Welcome Page: index.php ]
                                         /             \
                  [ User / Citizen Option ]           [ Admin Option ]
                             |                               |
                   [ portal.php ]                   [ admin_login.php ]
                     /         \                             |
     [ Free Citizen Report ]   [ Anonymous (₹9) ]     [ admin_dashboard.php ]
               |                       |              (City-wide oversight,
      [ Citizen Dashboard ]   [ Razorpay Gateway ]     assignment, moderation)
               |                       |
               \________    ___________/
                        \  /
             [ Municipal Authority Panel ]
               (authority_dashboard.php)
                          |
             [ Solved -> 10-Day Window ]
               /                      \
   [ Option A: Satisfied ]    [ Option B: Not Satisfied ]
   (Complaint Closed cleanly) (Complaint Reopened + Alert)
```

---

## ✨ Features Summary

### 1. Welcome & Role Selection (`index.php`)
- Clean Navy blue (`#0a2540`), white, and light blue aesthetic.
- Modern typography, accessible contrast, responsive cards.
- **English & Malayalam (മലയാളം)** bilingual language switcher.
- Strict entry separation between Citizen Portal and Secure Admin Login without exposing internal categories on welcome.

### 2. Citizen Portal & Dashboard (`portal.php`, `register.php`, `login.php`, `dashboard.php`)
- Free complaint submission for registered citizens with optional photograph attachments.
- Secure session management with session ID regeneration and brute-force rate limiting.
- In-app notification center (`notifications.php`) with unread counters.
- Private complaint history (citizens cannot view or access other citizens' complaints).

### 3. Paid Anonymous Complaint System (`anonymous.php`, `payment.php`)
- No citizen registration or login required.
- **₹9 nominal submission fee** to deter spam.
- Backend Razorpay test-mode integration with order generation and cryptographic HMAC-SHA256 signature verification.
- Idempotent payment processing preventing duplicate complaint generation.
- Generates a **unique complaint ID** (`CLN-YYYYMMDD-XXXX`) and a **32-character high-entropy secret tracking credential**.
- Only the bcrypt hash of the tracking credential is saved in the database.
- Complainant tracks progress securely at `track_complaint.php`.

### 4. 10-Day Satisfaction Feedback Workflow (`feedback.php`)
- When authorities resolve an issue, the complaint enters **`Solved`** status and triggers a **10-day feedback countdown**.
- Complainant receives two transparent choices:
  - **Option A (Satisfied):** Closes the complaint cleanly with `satisfaction_status = 'Satisfied'` and closure reason recorded.
  - **Option B (Not Satisfied):** Reopens the complaint to **`Reopened`**, increments reopen counter, notifies responsible authority, and requires remedial action.
- **10-Day Server-Side Scheduled Closure (`cron/close_expired_complaints.php`):** If no response is received in 10 days, the server automatically closes the issue with `satisfaction_status = 'No Response'`. **Silence is never treated as confirmed satisfaction.**

### 5. Municipal Authority Management & Subscription (`authority_dashboard.php`, `authority_subscription.php`)
- Dedicated authority login (`authority_login.php`) for municipal departments (KWA, PWD Roads, Sanitation, Food Safety, Anti-Narcotics).
- Filter assigned issues, view timeline history, and provide official resolution notes.
- **₹5,000 / year official subscription** model with start/end date tracking and backend payment validation. Expired subscriptions restrict dashboard status modification until renewed.

### 6. Local Business Digital Marketing Spotlight (`advertisements.php`, `business_dashboard.php`)
- Verified local businesses can sponsor community-oriented announcements in **"Discover Local Businesses Near You"**.
- Clearly labeled with **"Sponsored"** badges, segregated from civic reports.
- Select target service areas and packages (*Starter Local* ₹799/7d, *Community Growth* ₹2,499/30d, *Regional Premium* ₹5,999/90d).
- Privacy-preserving impression tracking and safe outbound click redirection (`api/track_click.php`) with URL validation against Open Redirect / SSRF vulnerabilities.
- Real-time Click-Through Rate (CTR) calculations.

### 7. Administrator Command Center (`admin_dashboard.php`, `admin_ads.php`)
- City-wide metrics, complaint status distributions, and collected revenue figures.
- Live search and multi-criteria filters (Category, Status, Location, Priority).
- Direct assignment of complaints to municipal authorities.
- Authority account activation, deactivation, and manual subscription verification.
- Local business verification and advertisement approval / rejection workflow.
- Priority review queue for citizen-reopened cases.

---

## 🔒 Security Specifications

- **Password Hashing:** Standard PHP `password_hash()` and `password_verify()` using `PASSWORD_DEFAULT` (Bcrypt).
- **No Plaintext Passwords / Credentials:** All anonymous tracking credentials and passwords are stored exclusively as hashes.
- **SQL Injection Prevention:** 100% prepared PDO statements with bound parameters across all database interactions.
- **Cross-Site Scripting (XSS):** Context-aware output escaping via custom `e()` helper (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`).
- **Cross-Site Request Forgery (CSRF):** Cryptographic token generation per session verified on all state-altering POST requests.
- **Secure File Uploads:** Uploaded photographs are validated using PHP `finfo` for MIME types (`image/jpeg`, `image/png`, `image/webp`), checked for 5MB limits, and saved with randomly generated unique filenames.
- **Rate Limiting:** IP and account-based rate limiting (5 failed attempts locks for 15 minutes) on sensitive authentication endpoints.
- **Payment Verification:** Backend HMAC-SHA256 signature verification comparing `razorpay_order_id . '|' . razorpay_payment_id` against `RAZORPAY_KEY_SECRET`.

---

## 💻 System Requirements

- **Web Server:** Apache 2.4+ (included with XAMPP) with `mod_rewrite` enabled
- **PHP:** PHP 8.1 or PHP 8.2 (with extensions `pdo_mysql`, `curl`, `mbstring`, `fileinfo`)
- **Database:** MySQL 5.7+ or MariaDB 10.4+ (included with XAMPP)
- **Browser:** Any modern standards-compliant browser (Chrome, Firefox, Edge, Safari)

---

## 🚀 Local Installation with XAMPP

### Step 1: Copy Project to XAMPP htdocs
Copy the `CivicLens` project directory into your XAMPP web root:
```bash
# Windows default path:
C:\xampp\htdocs\CivicLens
```

### Step 2: Configure Environment (`.env`)
Copy `.env.example` to create your local `.env`:
```bash
cp .env.example .env
```
Ensure your database parameters in `.env` match your local XAMPP configuration:
```env
APP_NAME=CivicLens
APP_URL=http://localhost/CivicLens
DB_HOST=localhost
DB_PORT=3306
DB_NAME=civiclens_db
DB_USER=root
DB_PASS=
```

### Step 3: Initialize Database Schema
1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open your terminal or phpMyAdmin (`http://localhost/phpmyadmin`).
3. Import the database schema and seed data:
```bash
# Using MySQL command line:
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seed.sql
```
*Alternatively, in phpMyAdmin create database `civiclens_db`, click "Import", and import `database/schema.sql` followed by `database/seed.sql`.*

### Step 4: Create Initial Administrator Account
Per specification, no administrator account is seeded with a default or public password.
1. Open your browser and navigate to:
   ```
   http://localhost/CivicLens/setup_admin.php
   ```
2. Enter your Full Name, Username, Admin Email, and a strong Password (minimum 8 characters).
3. Click **Create Superadmin Account**. Once created, this initial setup page locks automatically.

### Step 5: Start Using CivicLens
- **Welcome / Portal Selector:** `http://localhost/CivicLens/index.php`
- **Citizen Portal:** `http://localhost/CivicLens/portal.php`
- **Administrator Login:** `http://localhost/CivicLens/admin_login.php`
- **Municipal Authority Login:** `http://localhost/CivicLens/authority_login.php`
- **Local Business Portal:** `http://localhost/CivicLens/business_login.php`

---

## 💳 Razorpay Test-Mode Configuration

CivicLens uses Razorpay for paid anonymous complaints (₹9), authority subscriptions (₹5,000/yr), and commercial advertising packages.

To configure Razorpay Test Credentials:
1. Sign up for a free developer account at [https://razorpay.com](https://razorpay.com).
2. Navigate to **Settings → API Keys** in test mode.
3. Generate your **Test Key ID** (`rzp_test_...`) and **Test Key Secret**.
4. Update `.env`:
   ```env
   RAZORPAY_KEY_ID=rzp_test_your_key_id
   RAZORPAY_KEY_SECRET=your_test_key_secret
   ```
*If credentials are not yet added, the platform gracefully alerts users in test-mode with clean documentation and prevents unverified payments from creating false records.*

---

## ⏰ Scheduled Tasks (Cron Jobs)

CivicLens includes two server-side automated background tasks in `cron/`:

### 1. 10-Day Feedback Expiration Sweeper
Closes solved complaints whose 10-day citizen review window elapsed without response:
```bash
php cron/close_expired_complaints.php
```

### 2. Campaign and Subscription Expiry Sweeper
Expires ended advertisements and elapsed authority subscriptions:
```bash
php cron/check_subscriptions_and_ads.php
```

### Automation Setup:
- **Windows Task Scheduler:**
  - Create a Basic Task to trigger daily.
  - Action: Start a program: `C:\xampp\php\php.exe`
  - Arguments: `C:\xampp\htdocs\CivicLens\cron\close_expired_complaints.php`
- **Linux Crontab:**
  ```crontab
  0 2 * * * /usr/bin/php /var/www/html/CivicLens/cron/close_expired_complaints.php > /dev/null 2>&1
  0 3 * * * /usr/bin/php /var/www/html/CivicLens/cron/check_subscriptions_and_ads.php > /dev/null 2>&1
  ```

---

## 🧪 Testing and Verification Results

Automated unit and integration tests are located in `tests/`. Run tests anytime from the CLI:

```bash
php tests/run_tests.php
```

### Test Suite Execution Output:
```
======================================================
 CivicLens Automated Test Suite
======================================================

Running: Security & Cryptographic Primitives Tests...
  [PASS] password_hash and password_verify succeed on correct password
  [PASS] password_verify rejects incorrect password
  [PASS] Anonymous tracking token has 32-character hex entropy (128 bits)
  [PASS] Tracking credential verification succeeds against bcrypt hash
  [PASS] Tracking verification rejects spoofed token
  [PASS] sanitize_input trims whitespace correctly
  [PASS] e() escapes HTML entities to prevent XSS

Running: Complaint ID Structure Tests...
  [PASS] Complaint ID matches standardized regex format (CLN-YYYYMMDD-XXXX)
  [PASS] High entropy: 100 randomly generated IDs are unique without collision

Running: Feedback State Transitions Tests...
  [PASS] Satisfied feedback transitions status to Closed
  [PASS] Satisfied feedback records satisfaction as Satisfied
  [PASS] Satisfied feedback sets closure reason to Satisfied
  [PASS] Not Satisfied feedback reopens complaint
  [PASS] Not Satisfied feedback records satisfaction as Not Satisfied
  [PASS] Reopen count increments upon dissatisfaction

Running: 10-Day Deadline Calculation Tests...
  [PASS] 10-day feedback period exactly equals 864,000 seconds (10 days)
  [PASS] Cron detects expired deadline when timestamp is past 10 days
  [PASS] Scheduled expiration marks satisfaction as No Response, not Satisfied
  [PASS] Scheduled expiration sets closure reason to No Response

Running: Advertisement Analytics & CTR Tests...
  [PASS] CTR calculation accurate for 125 clicks / 2500 impressions: 5%
  [PASS] CTR handles division by zero safely when impressions are 0

Running: Git Hygiene & Secret Exclusion Tests...
  [PASS] .gitignore file exists in repository root
  [PASS] .gitignore explicitly excludes .env
  [PASS] .gitignore excludes user-uploaded complaint attachments
  [PASS] .env.example exists in repository root
  [PASS] .env.example contains placeholder values without live secrets

------------------------------------------------------
Results: 26 Passed, 0 Failed
======================================================
```

**PHP Syntax Linting:**
- Checked all 43 PHP application files using `php -l`.
- **Result:** `0 syntax errors detected`.

---

## 📦 External Integrations Status

| Integration | Configuration Method | Status | Fallback / Behavior |
| :--- | :--- | :--- | :--- |
| **Razorpay Payments** | `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET` in `.env` | Ready for Keys | Cleanly alerts test-mode disabled, prevents unverified submissions |
| **Transactional Email** | `MAIL_ENABLED=true`, SMTP credentials in `.env` | Optional | In-app notification center is active in database regardless |
| **SMS Gateway** | `SMS_ENABLED=true`, API credentials in `.env` | Optional | In-app notification center handles alerts natively |

---

## 🐙 Push to GitHub Instructions

Follow these exact Git commands to initialize and push CivicLens to your GitHub repository:

```bash
# 1. Initialize git repository if not already done
git init

# 2. Verify that .env and private uploads are untracked
git status

# 3. Stage all verified project files
git add .

# 4. Create your initial commit
git commit -m "feat: complete CivicLens full-stack civic reporting platform"

# 5. Set main branch
git branch -M main

# 6. Add your GitHub remote repository (replace with your repo URL)
git remote add origin https://github.com/YOUR_USERNAME/CivicLens.git

# 7. Push to GitHub
git push -u origin main
```

---

## 📄 License
This project is licensed under the MIT License — see the [LICENSE](LICENSE) file for details.
