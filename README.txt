EVCHARGEPLUS LOCAL SETUP
=========================

1. Put this entire "evchargeplus" folder inside your XAMPP htdocs folder:
   C:\xampp\htdocs\evchargeplus

2. Start XAMPP:
   - Apache: Start
   - MySQL: Start

3. Open phpMyAdmin:
   http://localhost/phpmyadmin/

4. Import the database:
   - Create/select database "evchargeplus"
   - Import your existing evchargeplus.sql
   Your SQL file already contains the 12 tables and sample data.

5. Edit:
   evchargeplus/config/database.php

   If your MySQL root user has a password, change:
   const DB_PASS = '';
   to your root password.

6. Test:
   http://localhost/evchargeplus/

7. Test database connection:
   http://localhost/evchargeplus/health.php

8. Existing sample user:
   admin@evcharge.com
   admin123

   Existing users from the SQL can also log in. The application supports the old
   plain-text sample passwords once and automatically converts them to password
   hashes after successful login.

WHAT WORKS
-----------
- Registration and login/logout
- Password hashing for new accounts
- Station listing
- Charger availability
- Vehicle management
- Charger booking with overlap protection
- Booking cancellation
- User dashboard
- Station feedback
- Reward-point display
- Admin dashboard
- Admin charger status changes
- Admin booking status changes
- PDO prepared statements
- CSRF protection
- Responsive UI

PAYMENTS / REAL CHARGING
------------------------
The database contains payments and charging-session tables, but this starter
application does NOT pretend to process real money or control a physical charger.
For real UPI/card payments, connect a legitimate payment gateway and use its
server-side verification/webhooks. For real charger hardware, connect the
station/OCPP backend separately.

If you get a blank page:
- Check Apache and MySQL are running.
- Open health.php.
- Check PHP/Apache error logs.
- Confirm DB_PASS in config/database.php.
