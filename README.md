# Student Management System

A system for managing student records and activities.

## 🚀 Setup Instructions

Follow these steps to get the project running on your local machine.

### 1. Database Configuration
The database connection settings are stored in `includes/database.php`. This file is ignored by Git.

To set up your database:
1.  Copy the template file: `includes/database.example.php`.
2.  Rename the copy to: `includes/database.php`.
3.  Open `includes/database.php` and update the credentials (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`) to match your local XAMPP/MySQL setup.

### 2. Importing the Database
1.  Open **phpMyAdmin**.
2.  Create a new database named `student_management`.
3.  Import the SQL dump file (if provided) into the new database.
