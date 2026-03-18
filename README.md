# Internship Result Management System

This project now matches the coursework requirements more closely:

- PHP + MySQL backend
- Role-based login for `Admin` and `Assessor`
- Admin management for student records, internship details, and assessor accounts
- Assessor result entry with fixed weightages
- Result viewing with search/filter support

## Main files

- `index.php`
  Login page.
- `student_management.php`
  Student management and internship assignment page.
- `user_management.php`
  Admin page to create, edit, and delete assessor accounts.
- `result_entry.php`
  Assessment entry page with automatic final mark calculation.
- `results.php`
  Result viewing page for admin and assessors.
- `MySql.sql`
  Main MySQL database script for the PHP system.

## Default accounts

- Admin: `admin01` / `Admin@123`
- Assessor: `assessor01` / `Assess@123`

## MAMP run steps

1. Copy the whole project folder into your MAMP web root, usually `C:\MAMP\htdocs\database`.
2. Start `Apache` and `MySQL` in MAMP.
3. Open `phpMyAdmin` from MAMP.
4. Import [COMP1044_database.sql](/d:/database/COMP1044_database.sql) into MySQL.
5. Open [config.php](/d:/database/config.php) and confirm the database settings match your MAMP setup.
   Default MAMP values are usually:
   `host=localhost`, `port=3306`, `username=root`, `password=root`
6. In the browser, open:
   `http://localhost/database/index.php`

If your Apache port is not `80`, append your actual Apache port to the URL.

## Submission-ready files prepared

- `COMP1044_ERD.pdf`
- `COMP1044_database.sql`
- `COMP1044_SRC.zip`
- `COMP1044_WBS.pdf`
- `COMP1044_CW_Gx`
- `COMP1044_CW_Gx.zip`

## Important note

`COMP1044_WBS.pdf` is a generated template. You still need to replace the placeholder member names, contribution text, and signatures with your real group information before final submission.


