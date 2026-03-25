# Student Management System

This is a small student management system I put together for class. Below are the steps I use to run it locally with XAMPP.

## Setup instructions

### Database configuration

Copy `config/database.example.php` to `config/database.php` and update these values: `DB_HOST`, `DB_USER`, `DB_PASS`, and `DB_NAME`.

Steps:

1. Open the `config` folder and copy `database.example.php` to `database.php`.
2. Edit `config/database.php` and set your database credentials.

If you're using default XAMPP, `DB_USER` is usually `root` and `DB_PASS` is usually blank.

### Importing the database

The database schema is in `db/database_setup.sql`. To import:

1. Start Apache and MySQL from XAMPP.
2. Open http://localhost/phpmyadmin.
3. Use the Import tab to upload `db/database_setup.sql` and run it.

That creates the `student_management` database and the `users` table used by the app.

## Profile picture support

The app supports uploading and deleting profile pictures from the Edit Profile page.

If you already have a database, run this once in phpMyAdmin:

```sql
ALTER TABLE `users`
ADD COLUMN `profile_image` varchar(255) DEFAULT NULL AFTER `address`;
```

Default behavior:

- Users without a photo use `public/images/edit-profile.png`.
- Clicking the profile image in the dashboard opens the Edit Profile page.
- Uploaded images are stored in `public/uploads/` (this folder is ignored in version control).

