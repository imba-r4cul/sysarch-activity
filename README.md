# Student Management System

Hey everyone! Here is a quick guide on how to get our system up and running on your own PC (super useful for when we run this in the computer lab).

## 🚀 Setup Instructions

### 1. Database Configuration
First things first, we need to let the code talk to your local XAMPP SQL database. The connection settings are supposed to be inside `includes/database.php`, but since my password isn't the same as yours, I didn't push mine to GitHub.

Here is what you need to do:
1.  Go to the `includes` folder. You will see a template file called `database.example.php`.
2.  Make a copy of it and rename the copy to exactly `database.php`.
3.  Open up your new `database.php` and update the credentials (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`) to match your local XAMPP setup. *(If you are just using default XAMPP, you usually just leave the password blank!)*

- SCHOOL PURPOSE ONLY THAT'S WHY I PUSHED THIS ON GITHUB. (BUT I GUESS IT'S A BAD PRACTICE)

### 2. Importing the Database Structure
Next, you need the actual tables for the code to work. I exported the clean table structure into the `database_setup.sql` file so you guys don't have to create the columns manually. Don't worry, it doesn't contain any real user data, just the structure!

1.  Make sure your XAMPP is running (both Apache and MySQL).
2.  Open up your browser and go to `http://localhost/phpmyadmin`.
3.  Look at the very top menu and click on the **Import** tab.
4.  Click **Choose File** and select the `database_setup.sql` file from our project folder.
5.  Scroll down to the bottom and hit **Import** (or **Go**).

And that's it! That file automatically creates the `student_management` database and the `users` table with all the correct fields. We are good to go!

## Profile Picture Support

The app now supports profile picture upload and delete from the Edit Profile page.

### If you are using an existing database

Run this SQL once in phpMyAdmin:

```sql
ALTER TABLE `users`
ADD COLUMN `profile_image` varchar(255) DEFAULT NULL AFTER `address`;
```

### Default image behavior

- New users without an uploaded image will use `public/images/edit-profile.png`.
- In Dashboard, clicking the profile image opens the Edit Profile page.
- Uploaded images are saved in `public/uploads/`.
