# Admin Control Panel Setup Guide

## What's Been Added

1. **New `admin.php` file** - Complete admin panel with 3 sections:
   - Dashboard Overview (stats)
   - User Management (make/remove admins, delete users)
   - Review Management (delete reviews)

2. **Database Changes** - `is_admin` column added to tbl_users table (default: 0)

3. **Login Update** - Stores admin status in session

4. **Dashboard Update** - Shows admin shield icon if user is admin

## Setup Instructions

### Step 1: Update Your Database

Run this SQL query in phpMyAdmin or your database client:

```sql
ALTER TABLE tbl_users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0;
```

### Step 2: Make Your First Admin

Run this SQL to make your first admin (example: user with id=1):

```sql
UPDATE tbl_users SET is_admin=1 WHERE user_id=1;
```

_Replace id=1 with your actual user ID_

**Or** use phpMyAdmin:

1. Open the `users` table
2. Find your user row
3. Edit the `is_admin` column from 0 to 1
4. Click Save

### Step 3: Access Admin Panel

1. Log in to your account (the one you set as admin)
2. You'll see a **shield icon** in the top header
3. Click it to access the admin panel, or go directly to: `http://yoursite.com/admin.php`

## Admin Features

### Dashboard Tab

- View total users, reviews, admins, and watchlist items

### Manage Users Tab

- View all registered users
- Promote users to admin
- Remove admin privileges
- Delete user accounts (cascades to their reviews & watchlist)

### Manage Reviews Tab

- View all movie reviews
- Delete inappropriate reviews
- See review timestamps and ratings

## Security Notes

✅ Admin panel checks for admin status on each visit  
✅ Only admins can access the admin panel  
✅ Cannot delete yourself as admin  
✅ All actions are logged by the database timestamps

## File Changes Made

1. **admin.php** - New admin panel (CREATED)
2. **rv.sql** - Added is_admin column
3. **login.php** - Now fetches is_admin status
4. **dashboard.php** - Shows admin icon for admins

## Testing

```
1. Create a test account
2. Run: UPDATE tbl_users SET is_admin=1 WHERE username='testuser';
3. Log in with that account
4. You should see the shield icon on the dashboard
5. Click the shield to access the admin panel
```

## Troubleshooting

**Admin icon not showing?**

- Make sure you ran the ALTER TABLE query
- Confirm is_admin is set to 1 in the users table
- Log out and log back in

**Can't access admin.php?**

- Check the is_admin value in database (should be 1)
- Refresh the page
- Try clearing browser cache

**Changes not saving?**

- Check PHP error logs
- Verify database connection is working
- Make sure SQL queries have no syntax errors
