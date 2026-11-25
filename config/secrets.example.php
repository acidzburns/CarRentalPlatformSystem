<?php
// ─────────────────────────────────────────────────────────────
// Template for config/secrets.php — copy it and fill in real values.
//   1. Copy this file and name the copy  secrets.php  (same folder)
//   2. Put the real passwords in secrets.php
// secrets.php is in .gitignore, so it never goes to GitHub.
// This example file has no real passwords, so it is safe to commit.
// ─────────────────────────────────────────────────────────────

return [
    // Oracle database
    'db_password' => 'car',

    // Gmail SMTP (use a Gmail "App password", not your normal password)
    'smtp_host'      => 'smtp.gmail.com',
    'smtp_username'  => 'carrentalplatformsystem@gmail.com',
    'smtp_password'  => 'rxem kgzh wsit xnep',
    'mail_from'      => 'carrentalplatformsystem@gmail.com',
    'mail_from_name' => 'Car Rental Platform',
];