# Accounting LMS Prototype

A PHP/MySQL web prototype based on the Accounting LMS project proposal.

## Included
- Role-based login (Admin, Faculty, Student) with TOTP-style 2FA; disabled/pending accounts cannot sign in
- Prototype-style dashboard and shared sidebar layout (`layout.php` + `app.css`) on every page
- Dashboard: journal counts, total assets, recent journal activity, coursework snapshot (students/faculty), user counts (admin)
- Accounting: Chart of Accounts, Journal Entries (debit = credit validation), General Ledger, Trial Balance (CSV export), Financial Reports
- Learning: courses, modules, assignments and task submissions, grades
- Notifications, User Management (add / enable / disable), Audit Trail (admin)

## Requirements
- PHP 8.1+
- MySQL 8+
- Apache/XAMPP

## Setup
1. Copy the `accounting_lms` folder into `htdocs`.
2. Create a MySQL database named `accounting_lms`.
3. Import `database.sql`. (Existing database? Run the commented migration statements at the bottom of the file instead.)
4. Edit `config.php` with your MySQL credentials.
5. Open `http://localhost/accounting_lms/`.
6. Use the seeded accounts:
   - admin@example.com / password
   - faculty@example.com / password
   - student@example.com / password

For the seeded accounts, the demo 2FA code is `123456`.
