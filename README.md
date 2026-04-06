# Student Management System

This is a small student management system I built for class. The app runs on XAMPP, and the notes below are the same steps I use when I set it up locally.

## Setup

### Database configuration

Open `config/database.php` and set `DB_HOST`, `DB_USER`, `DB_PASS`, and `DB_NAME`.

If you are using the default XAMPP setup, `DB_USER` is usually `root` and `DB_PASS` is usually blank.

### Import the database

The schema lives in `db/database_setup.sql`. To import it:

1. Start Apache and MySQL in XAMPP.
2. Go to http://localhost/phpmyadmin.
3. Use the Import tab to upload `db/database_setup.sql` and run it.

That creates the `student_management` database with these tables: `users`, `admin_users`, `sit_in_records`, `announcements`, and `reservations`.

## Features

This application includes the following features:

- **User registration & authentication:** Register, log in, and edit profile information.
- **Student dashboard:** View current sit-ins, announcements, and quick actions from a student view.
- **Admin dashboard:** Manage sit-in records, announcements, users, and reservations.
- **Current sit-in (active sessions):** The Current Sit-in page asks for confirmation before ending a session and updates the row in-place without a full page reload.
- **Sit-in history:** Browse historical sit-in records per student.
- **Reservations:** Create and manage seat reservations.
- **Search student:** Modal-based search for quick student lookups.
- **File uploads:** Support for student image uploads and other files under public/uploads.
- **Styles & assets:** Page styles are organized in `public/css` and images in `public/images`.

