# Sit-in lab system

I built this system to handle student sit-ins and lab reservations without having to mess around with paper logs. It lets students grab a spot in the lab, see announcements, and track their hours, while admins can manage the whole thing from a dashboard.

## Setup

Since this is a PHP app, you'll need the usual XAMPP setup (Apache and MySQL).

### 1. The Code

Just clone this into your `htdocs` folder.

```bash
git clone https://github.com/imba-r4cul/sysarch-activity.git
```

I've got it set up so `index.php` in the root just points you to the `public/` folder, so you don't really have to worry about where the entry point is.

### 2. The Database

1.  Fire up MySQL in XAMPP and go to phpMyAdmin.
2.  Create a database called `student_management`.
3.  Import the SQL file from `/db/database_setup.sql`. It'll create all the tables you need (users, records, reservations, etc.).
4.  **Admin Login:** If you want a quick way to get into the admin side without manually hashing passwords in the DB, run the `db/seed_admin.php` script once. It'll create a default admin for you.

### 3. Config

If you're using the default XAMPP credentials (root/no password), you're good to go. If not, open up `config/database.php` and swap these around:

```php
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

## How it's organized

- `public/`: This is where all the actual pages live. CSS, JS, and the dashboards.
- `config/`: Just the DB connection logic.
- `db/`: The schema and the seeder script.
- `index.php`: The "front door" that redirects you to the public index.

## A couple of things to watch out for

If the app can't connect to the database, the first thing I'd check is the `DB_NAME` in `config/database.php` matches what you created in phpMyAdmin. Sometimes XAMPP's MySQL port changes too, but usually it's fine.

If you change any of the branding colors, check `notes.md` first. I've listed a few "protected" hex codes there that are used everywhere, so changing them might break the look of the dashboards.
