# Renewly 🔄

**Renewly** is an enterprise SaaS and Software License Subscription Tracking System. It helps organizations proactively track client software subscriptions (e.g., Microsoft 365, Adobe CC, AWS, Google Workspace), manage renewal schedules, calculate prorated mid-cycle additions, queue seat drops, and automatically trigger client notifications **well before vendors send expiration or renewal notices**.

---

## 🎯 Purpose & Core Value Proposition

When managing software licenses and subscriptions for multiple client organizations, relying on vendor expiration notices often leads to last-minute renewals, unexpected invoice surprises, or service disruptions.

**Renewly solves this problem by:**
1. **Ahead-of-Time Reminders**: Triggering multi-stage automated alerts (30-day, 14-day, 7-day, 3-day, 1-day) to account managers and clients before the vendor deadline.
2. **Proactive Client Management**: Allowing your team to review client license usage, discuss renewals, and issue quotes before vendor lock-ins or automatic price renewals occur.
3. **Flexible Mid-Cycle Modifications**: Handling license expansion (immediate co-termed proration) and quantity reductions (queued for effective date at renewal) seamlessly.

---

## ✨ Key Features

### 📅 Proactive & Cycle-Aware Renewal Reminders
- Automated background check script evaluates active subscriptions against expiration dates.
- Cycle-aware warning stages tailored to billing frequency:
  - **Monthly Subscriptions**: 7-day, 3-day, 1-day alerts.
  - **Quarterly Subscriptions**: 14-day, 7-day, 3-day, 1-day alerts.
  - **Yearly Subscriptions**: 30-day, 14-day, 7-day, 1-day alerts.
- Automatic status transitions to **Expiring** (within 30 days) and **Lapsed** (past grace period).

### 🏢 Client Company Directory
- Centralized directory of client companies with primary contact email and history.
- Real-time KPI tracking for active client accounts.

### 📦 Vendor & Product Catalog
- Maintain software vendors and standardized software titles/licenses.
- Support for pricing models (`flat` rate vs. `per_seat` seat pricing) with price snapshot integrity.

### 🛒 Multi-Item Subscription Contracts & Co-Terming
- Group multiple software licenses under a single master subscription contract.
- **Mid-Cycle Additions**: Real-time prorated invoice calculations for mid-term additions co-termed to the current billing cycle end date.
- **Queued Downgrades**: Quantity reductions are queued (`queued_quantity`) to take effect automatically upon renewal without disrupting current billing terms.

### 💵 Multi-Currency & Scheduled Payment Tracking
- Native support for multi-currency billing (USD, NGN, EUR, GBP, etc.).
- Global exchange rate configuration with contract-level rate overrides.
- Automated payment schedule generator creating recurring cycle installments (monthly, quarterly, yearly).

### 🛡️ Approval Queue & Granular Role Matrix
- Multi-tier contract approval workflow (`pending`, `approved`, `rejected`) for administrator verification.
- Role-Based Access Control (RBAC) permission matrix mapped by module categories.
- Complete system audit trail (`audit_logs`) recording contract creations, status updates, and modifications.

---

## 🛠️ Technology Stack

- **Backend**: Modular Plain PHP 8.0+ (PDO MySQL Driver)
- **Database**: MySQL 5.7+ / MariaDB 10.4+
- **Frontend**: HTML5, Vanilla JavaScript, Bootstrap 5, Lucide Icons, Custom Design System
- **Environment**: Apache / XAMPP / Local Web Server

---

## 🚀 Setup & Installation

1. **Clone/Copy Project to Web Root**:
   Place the `Renewly` directory in your web server root (e.g., `C:\xampp\htdocs\Renewly`).

2. **Import Database Schema & Seeds**:
   - Create a database named `renewly`.
   - Import `database/schema.sql` into the `renewly` database.
   - Import `database/seeds.sql` for initial roles, permissions, and administrative account.

3. **Access the Application**:
   Navigate to `http://localhost/Renewly` in your browser.

---

## ⏰ Setting Up Daily Automated Reminders (Cron Job)

To ensure proactive reminders run automatically every day:

### Windows (Task Scheduler):
Create a scheduled task running daily at 08:00 AM:
```powershell
php.exe -f "C:\xampp\htdocs\Renewly\cron\daily_runner.php"
```

### Linux (Crontab):
Add the following line to your crontab (`crontab -e`):
```bash
0 8 * * * /usr/bin/php /var/www/html/Renewly/cron/daily_runner.php >> /var/log/renewly_cron.log 2>&1
```

---

## 📄 License
Internal In-House Proprietary Application. All rights reserved.
