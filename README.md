# Renewly 🔄

**Renewly** is an internal, in-house SaaS and Software License Subscription Tracking System. It is designed to help internal teams proactively track client software subscriptions (e.g., Microsoft 365, Adobe CC, AWS, Google Workspace), manage renewal schedules, calculate prorated mid-cycle additions, queue downgrades, and automatically trigger client notifications **well before vendors send expiration or renewal notices**.

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
- Automated background check script (`cron/daily_check.php`) evaluates active subscriptions against expiration dates.
- Cycle-aware warning stages tailored to billing frequency:
  - **Monthly Subscriptions**: 7-day, 3-day, 1-day alerts.
  - **Quarterly Subscriptions**: 14-day, 7-day, 3-day, 1-day alerts.
  - **Yearly Subscriptions**: 30-day, 14-day, 7-day, 1-day alerts.
- Automatic status transitions to **Expiring** (within 30 days) and **Lapsed** (past grace period).

### 🏢 Client Organization Directory
- Centralized directory of client companies (`organizations`) with primary contact email and history.
- Real-time KPI tracking for active client accounts.

### 📦 Vendor & Product Catalog
- Maintain software vendors (`vendors`) and standardized software titles/licenses (`products`).
- Support for custom pricing models (`flat` rate vs. `per_user` seat pricing).

### 🛒 Multi-Item Subscription Management & Co-Terming
- Group multiple software licenses under a single master subscription contract.
- **Mid-Cycle Additions**: Real-time prorated invoice calculations (`api/preview_modification.php` & `api/commit_modification.php`) for mid-term additions co-termed to the current billing cycle end date.
- **Queued Downgrades**: Quantity reductions are queued (`renewal_quantity`) to take effect upon renewal without disrupting current billing terms.

### 💵 Multi-Currency & Scheduled Payment Tracking
- Native support for multi-currency billing (USD, NGN, EUR, GBP, etc.).
- Global exchange rate configuration with subscription-level rate overrides.
- Automated payment schedule generator (`subscription_payments`) creating recurring cycle installments (monthly, quarterly, yearly).

### 🛡️ Approval Queue & Audit Trail
- Multi-tier contract approval workflow (`pending`, `approved`, `rejected`) for administrator verification.
- Complete system audit trail (`activity_logs`) recording contract creations, status updates, and modifications.

---

## 🛠️ Technology Stack

- **Backend**: PHP 8.0+ (PDO MySQL Driver)
- **Database**: MySQL 5.7+ / MariaDB 10.4+
- **Frontend**: HTML5, Vanilla JavaScript, Bootstrap 5, Lucide Icons, Modern Shadcn-inspired UI
- **Environment**: Apache / XAMPP / WAMP / Local Web Server

---

## 📂 Directory & Database Architecture

```
Renewly/
├── admin/                     # Administrative Interface Pages
│   ├── activity.php           # Audit trail logs viewer
│   ├── approvals.php          # Subscription approval queue
│   ├── companies.php          # Client organization management
│   ├── dashboard.php          # Main KPI & analytics overview
│   ├── products.php           # Product catalog management
│   ├── settings.php           # Global configurations & exchange rates
│   ├── subscriptions.php      # Master subscription & item CRUD
│   └── vendors.php            # Vendor directory management
├── api/                       # RESTful JSON Endpoints
│   ├── commit_modification.php# Commits prorated item adjustments & invoice schedules
│   ├── fetch_expiring.php     # Dashboard KPI stats & expiring subscriptions feed
│   ├── get_subscription.php   # Detailed subscription, item & payment schedule lookup
│   └── preview_modification.php# Real-time proration calculator for license changes
├── cron/                      # Background Tasks
│   └── daily_check.php        # Daily expiration checker & reminder engine
├── database/                  # Database Core
│   ├── db.php                 # PDO database connection configuration
│   └── schema.sql             # Baseline SQL schema & initial dataset
├── includes/                  # UI Layout Component Headers/Footers
│   ├── header.php
│   ├── footer.php
│   └── sidebar.php
├── login.php                  # Authentication Portal
└── register.php               # Account Registration Portal
```

### Database Schema (10 Core Tables)

1. **`organizations`**: Client company details and email contacts.
2. **`vendors`**: Software suppliers (e.g., Microsoft, Adobe, AWS).
3. **`users`**: System users and administrators.
4. **`products`**: Software products/licenses with default costs and pricing models.
5. **`subscriptions`**: Master subscription contracts (billing cycle, currency, exchange rate, start/expiry dates, grace periods).
6. **`subscription_items`**: Line-item breakdown per subscription (quantity, renewal quantity, unit cost, total cost, status).
7. **`subscription_payments`**: Installment payment schedule records and due dates.
8. **`reminders_log`**: Log of automated reminder notifications sent to clients/account managers.
9. **`activity_logs`**: System audit trail.
10. **`app_settings`**: Global system preferences and default exchange rates.

---

## 🚀 Setup & Installation

### 1. Prerequisites
- [XAMPP](https://www.apachefriends.org/) (or any Apache + PHP 8.0+ + MySQL environment).

### 2. Installation Steps

1. **Clone/Copy Project to Web Root**:
   Place the `Renewly` directory in your web server root (e.g., `C:\xampp\htdocs\Renewly`).

2. **Configure Database Connection**:
   Open `database/db.php` and verify/update your MySQL credentials:
   ```php
   $host = 'localhost';
   $db   = 'renewly'; // or slrs_db
   $user = 'root';
   $pass = '';
   ```

3. **Import Database Schema**:
   - Open phpMyAdmin (`http://localhost/phpmyadmin`) or your MySQL client.
   - Create a database named `renewly`.
   - Import the `database/schema.sql` file into the `renewly` database.

4. **Access the Application**:
   Navigate to `http://localhost/Renewly/admin/dashboard.php` in your browser.

---

## ⏰ Setting Up Daily Automated Reminders (Cron Job)

To ensure proactive reminders run automatically every day:

### Windows (Task Scheduler):
Create a scheduled task running daily at 08:00 AM:
```powershell
php.exe -f "C:\xampp\htdocs\Renewly\cron\daily_check.php"
```

### Linux (Crontab):
Add the following line to your crontab (`crontab -e`):
```bash
0 8 * * * /usr/bin/php /var/www/html/Renewly/cron/daily_check.php >> /var/log/renewly_cron.log 2>&1
```

---

## 💡 Usage Workflow

1. **Setup Catalog & Clients**: Add your software vendors, products, and client companies under **Vendors**, **Products**, and **Companies**.
2. **Create Subscriptions**: Create multi-item subscription contracts under **Subscriptions**, specifying billing cycles and currency.
3. **Monitor Expiring Subscriptions**: Track the **Dashboard** and **Critical / Expiring** cards for upcoming renewals.
4. **Modify Active Contracts**: When a client requests additional seats mid-term, use the **Modify** tool to automatically prorate costs until the next billing date.
5. **Automated Reminders**: Relax knowing that `cron/daily_check.php` will alert your team and clients well before vendor renewal cutoffs!

---

## 📄 License
Internal In-House Proprietary Application. All rights reserved.
