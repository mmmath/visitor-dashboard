# Visitor Dashboard - Setup Guide for Non-Technical Users

## 🎯 What This Does

This single page displays:
- **Your visitor's IP address** ✓
- **Their location** (City, Country, Coordinates) ✓
- **Today's weather** at their location ✓
- **Built-in calculator** for fun ✓
- **Stores data** in a database to speed up repeat visits ✓

## 📋 Step-by-Step Setup (Very Simple!)

### Step 1: Download Files
1. Download these 3 files from the GitHub repository:
   - `index.html` - The main page
   - `backend.php` - The data handler
   - `database.sql` - The database setup

### Step 2: Create Database
1. Log in to your **cPanel** (or hosting control panel)
2. Find **phpMyAdmin** in the left menu
3. Click on **"New"** button (or **"Create"**)
4. Enter database name: `visitor_dashboard`
5. Click **"Create"**
6. Now click on the new database name in the left menu
7. Click **"SQL"** tab at the top
8. Open the `database.sql` file with Notepad
9. Copy ALL the text
10. Paste it into the SQL box in phpMyAdmin
11. Click **"Go"** or **"Execute"**
12. ✓ Database table is created!

### Step 3: Create Database User
1. In cPanel, find **MySQL Users**
2. Click **"Create New User"**
3. Enter:
   - **Username**: `visitor_user`
   - **Password**: Create a strong password (write it down!)
4. Click **"Create User"**
5. Find **"Add User to Database"**
6. Select the user and database you just created
7. Check **ALL** permissions boxes
8. Click **"Make Changes"**

### Step 4: Update backend.php
1. Open `backend.php` with Notepad
2. Find these lines at the top:
   ```php
   $db_host = 'localhost';
   $db_user = 'your_db_user';
   $db_password = 'your_db_password';
   $db_name = 'visitor_dashboard';
   ```
3. Replace:
   - `your_db_user` → with the username you created (e.g., `visitor_user`)
   - `your_db_password` → with the password you created
   - Keep `localhost` and `visitor_dashboard` as is
4. Save the file

### Step 5: Upload Files to Your Server
1. Log in to your **File Manager** (or FTP)
2. Navigate to your **public_html** folder (or your website folder)
3. Upload these 3 files:
   - `index.html`
   - `backend.php`
   - You don't need to upload `database.sql` (already used)
4. Done! ✓

### Step 6: Test It!
1. Visit your website: `yourwebsite.com/index.html`
2. You should see:
   - Your IP address
   - Your location
   - Today's weather
   - A working calculator
3. Refresh the page - it should load faster (using cached data)
4. In phpMyAdmin, you should see your IP stored in the `visitors` table

## ❓ Troubleshooting

### "Database connection failed"
- Check your username and password in `backend.php`
- Make sure database name is correct
- Verify database user has permission to access the database

### "Weather unavailable"
- This is OK - the weather service might be temporarily down
- The page still works without weather
- Just refresh the page later

### "Blank page" or "404 error"
- Make sure you uploaded all files to the correct folder
- Check that file names match exactly:
  - `index.html` (not `index.html.txt`)
  - `backend.php` (not `backend.php.txt`)
- Ask your hosting provider about file permissions

### Page is slow on first load
- This is normal - it fetches geolocation data the first time
- Second visits are much faster (data from database)

## 🔒 Security Notes

- The page only reads your visitor's IP and location (public information)
- No passwords or sensitive data are stored
- The database is only accessed through PHP, not directly from the browser
- Consider restricting database access to your website's IP if your hosting allows

## 📞 Need Help?

Contact your hosting provider if you have issues with:
- Creating databases
- Creating database users
- Uploading files to your server
- Accessing cPanel or phpMyAdmin

---

**That's it! Your visitor dashboard is live! 🎉**