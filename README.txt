MUSDAA MEDICAL CAMP REGISTRATION SYSTEM
=========================================

TECHNOLOGY
----------
PHP + MySQL + XAMPP

SETUP
-----
1. Copy the "musdaa_medical_camp" folder into:
   C:\xampp\htdocs\

2. Open XAMPP Control Panel.

3. Start:
   - Apache
   - MySQL

4. Open phpMyAdmin:
   http://localhost/phpmyadmin

5. Import database.sql.

   If the database was already imported, run database_upgrade.sql instead
   to add attendee contact fields, structured medical measurements,
   referrals, audit logs, and the Report Officer role.

6. Open:
   http://localhost/musdaa_medical_camp/

REGISTRATION
------------
http://localhost/musdaa_medical_camp/registration/register.php

DAILY CHECK-IN
--------------
http://localhost/musdaa_medical_camp/checkin/checkin.php

IMPORTANT
---------
The current users table contains a development-only admin account:
Username: admin
Password: admin123

Do not use this password for a real deployment. We will add secure
password hashing and proper login/access control before the system
is used with real medical-camp data.

The system currently supports:
- Attendee registration
- Unique registration numbers
- Emergency contacts
- 7 summit days
- Daily check-in tracking
- Medical service definitions
- Medical service records
- Basic user roles
- Registration growth and seven-day attendance reports
- Medical service record entry

The data model is documented in ERD.md.

NEXT DEVELOPMENT STEPS
----------------------
1. Secure login and role-based access
2. Admin dashboard
3. Search attendees
4. Medical service recording
5. Attendance reports
6. Daily statistics
7. Export/print reports
8. Data privacy and security improvements
