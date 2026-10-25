<?php
// Copy this file to C:\xampp\private\koken-mail.php (outside htdocs).
// Fill credentials locally. Never commit the real password or App Password.
return [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'starttls',
    'username' => '',       // Gmail address
    'password' => '',       // Google App Password, not your regular account password
    'from_address' => '',   // Same Gmail address or a verified Gmail alias
    'from_name' => 'Köken Fındık',
];