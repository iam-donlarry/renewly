# Development Rules & Engineering Standards — Renewly

## 1. Core Development Principles & Design Tokens
- **Font Family**: Primary Google Font: `'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;`
- **Brand Primary Color**: `#12b1b0` (Hover: `#0f9d9c`, Light tint: `#e0f2f2`)
- **Plain PHP Architecture**: Modular, permission-driven, secure, clean separation between frontend UI markup and backend calculations/data logic.

## 2. Sidebar Navigation Architecture (Expense App Pattern)
- Structured matching `c:\xampp\htdocs\expense\includes\sidebar.php`:
  - Section headers: `<div class="sidebar-section-header">Section Name</div>`
  - Expandable nav groups: `<details class="nav-group" <?= $is_active ? 'open' : '' ?>>` with `<summary class="nav-group-summary">` and `<i data-lucide="chevron-right" class="caret-icon"></i>`
  - Sub-menus: `<div class="sub-menu"><a href="..." class="sub-nav-link <?= $active ?>">Nav Text</a></div>`
  - Dynamic pending action badges: `<span class="nav-badge"><?= $count ?></span>`
  - Strict RBAC permission guards around nav items: `<?php if (hasPermission('...')): ?>`

## 3. Mandatory Development Rules (Summary)
1. **Clean Modular Plain PHP**: No heavy MVC frameworks. Centralized AJAX (`ajax/`), centralized API (`api/`), clean URL routing via `.htaccess`.
2. **Backend Authoritative Financial Logic**: All financial calculations (proration, seat adjustments, payment schedule generation, exchange rate conversions) must be executed on the backend using `DECIMAL(15,4)`. Never trust client JS.
3. **Price Snapshotting**: Contract line items snapshot unit prices at creation. Product catalog price changes never alter active/historical contracts.
4. **Mid-term Modifications**: Additions calculate immediate daily proration charges. Reductions queue `queued_quantity` for effective execution at renewal without altering active term billing prematurely.
5. **Persistent Payment Schedules**: Installment payment rows retain payment status (`paid`, `pending`, `overdue`) across contract updates.
6. **Idempotent Reminder Scanning**: Cron job checks `reminder_logs` to prevent duplicate notifications.
7. **Permission-Driven RBAC**: Enforce `hasPermission($key)` on all UI links AND backend endpoints. No hardcoded role string checks.
8. **UI/UX Excellence**: Custom toast notifications (`components/toast.php`), custom modal/offcanvas confirm dialogs (no native `alert()`), Lucide icons exclusively, responsive data tables with loading and empty states.
9. **Single Source of Truth Database Schema**: Any database modifications, table additions, or column schema changes must be updated directly in `database/schema.sql` to maintain absolute schema integrity. Avoid creating disposable patch scripts.
