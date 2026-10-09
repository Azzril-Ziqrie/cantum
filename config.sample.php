<?php
// Copy this file to config.php to use MySQL/MariaDB instead of the bundled SQLite file.
// Create the database + user in cPanel (MySQL Databases), import schema_mysql.sql in phpMyAdmin, then fill this in.
return [
    'driver' => 'mysql',
    'host'   => 'localhost',
    'name'   => 'DB_NAME',
    'user'   => 'DB_USER',
    'pass'   => 'DB_PASSWORD',
];
