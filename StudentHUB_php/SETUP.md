# Student Hub database setup

1. Start **Apache** and **MySQL** in XAMPP.
2. Open phpMyAdmin at `http://localhost/phpmyadmin`.
3. Import `StudentHUB_php/studenthub_schema.sql`.
4. Open the project through Apache, for example `http://localhost/STUDENTHUB/`.
   Do not open the HTML files with `file:///`, because PHP cannot run that way.
5. Create an account on the Register page, then log in with the same email,
   password, and role.

The default database settings are in `StudentHUB_php/db.php`:

```php
host: 127.0.0.1
database: student_hub
user: root
password: empty
```

To check that registration worked, run this in phpMyAdmin:

```sql
SELECT user_id, full_name, email, role, course, created_at
FROM users;
```

Passwords are saved as secure hashes, so the original password will not be
visible in the table.
