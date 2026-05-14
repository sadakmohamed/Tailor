# TailorPro — Tailor Management System

A complete, multi-tenant web-based Tailor Management System MVP.
Built with **PHP 8**, **MySQL**, **Tailwind CSS**, and **Vanilla JS**.

---

## 🚀 Quick Setup (XAMPP / WAMP / Laragon)

### Step 1 — Copy Project
Place this entire `Tailor/` folder inside `htdocs/` (XAMPP) or `www/` (WAMP):

```
C:\xampp\htdocs\Tailor\           ← Windows XAMPP
/Applications/XAMPP/htdocs/Tailor/  ← macOS XAMPP
```

### Step 2 — Create the Database
1. Open **phpMyAdmin** → `http://localhost/phpmyadmin`
2. Click **Import** → choose `database/tailor_db.sql`
3. Click **Go**

### Step 3 — Configure Database Credentials
Open `config/db.php` and set your MySQL credentials:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');    // your MySQL username
define('DB_PASS', '');        // your MySQL password
define('DB_NAME', 'tailor_db');
```

### Step 4 — Create Superadmin Account
Open in browser: **http://localhost/Tailor/setup.php**

- Enter your name, email, and password
- Click **Create Superadmin**
- **⚠️ DELETE `setup.php` after completing this step!**

### Step 5 — Login
Open: **http://localhost/Tailor/**

Use the superadmin credentials you just created.

---

## 👥 User Roles

| Role | Access |
|---|---|
| **Super Admin** | Create companies, create admins, reset passwords, view all |
| **Admin** | Full access: customers, orders, staff, categories, reports |
| **Staff** | Same as admin but **cannot** manage staff |

---

## ✨ Features

### Super Admin
- Dashboard with total companies, admins, staff, customers
- Create tailor companies with admin accounts
- Toggle company active/inactive
- Reset admin passwords

### Admin & Staff
- **Dashboard**: KPI cards, today's appointments, recent customers, search
- **Customers**: Full list with search + filter (all / has balance / today's pickup)
- **Add Customer**: Customer info + multiple garment orders + measurements + appointment + payment
- **View Customer**: Orders, measurements, payment history, add payment, update status
- **Staff Management** (admin only): Add, activate/deactivate, reset passwords
- **Categories** (admin only): Add, rename, delete garment categories
- **Reports** (admin only): Revenue chart, orders by category, pending payments, appointments
- **Invoice/Receipt**: Printable with company branding

### Order Features
- Add multiple garment categories per customer visit
- 6 measurement fields per order: Neck, Shoulder Width, Arm Length, Body Height, Body Width, Arm Width
- Order statuses: Pending → In Progress → Completed → Delivered

### Payment Features
- Partial + full payments
- Payment method: Cash, Card, Mobile
- Live balance calculator on add-customer form
- Balance tracking per customer

### Appointments
- Schedule pickup date + time
- Quick shortcuts: +1 day, +2 days, +3 days, +1 week
- Overdue/today highlights on dashboard

---

## 📁 File Structure

```
Tailor/
├── index.php               ← Login page
├── logout.php
├── setup.php               ← Run once, then DELETE
├── config/
│   └── db.php              ← Database config + constants
├── auth/
│   ├── login.php           ← Login handler
│   └── middleware.php      ← Session guard + role helpers
├── includes/
│   ├── header.php          ← Shared HTML layout (top)
│   ├── footer.php          ← Shared HTML layout (bottom)
│   └── sidebar_nav.php     ← Role-based sidebar links
├── superadmin/
│   ├── dashboard.php       ← Companies list
│   └── create_company.php  ← Create company + admin
├── admin/
│   ├── dashboard.php       ← Main dashboard
│   ├── customers.php       ← Customer list
│   ├── add_customer.php    ← Add customer + orders
│   ├── view_customer.php   ← Customer detail
│   ├── staff.php           ← Staff management
│   ├── categories.php      ← Category management
│   ├── reports.php         ← Reports + charts
│   └── invoice.php         ← Printable invoice/receipt
├── assets/
│   ├── css/custom.css      ← Custom styles + print
│   └── js/app.js           ← Sidebar, helpers
└── database/
    └── tailor_db.sql       ← Full database schema
```

---

## 🔒 Security Notes

- All passwords hashed with PHP's `password_hash()` (bcrypt)
- All database queries use PDO prepared statements (SQL injection safe)
- Session-based authentication with role isolation
- Multi-tenant: each company's data is isolated

---

## 🖨 Invoice & Receipt

From any customer's detail page:
- Click **🖨 Invoice** for the full order invoice with measurements
- Click **🧾 Receipt** for payment confirmation
- Click **🖨 Print** to send to printer

---

## 💱 Currency

Change the currency symbol per-company. Default is `$`.
Admins see the company's currency on all pages.

---

## 🛠 Extending the MVP

- Add SMS/email notifications for appointments
- Add photo upload for customers
- Add multi-branch per company
- Add online payment gateway integration
