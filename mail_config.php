<?php
// mail_config.php - configure SMTP settings for PHPMailer
// Copy this file to the project and set the constants below to your SMTP provider values.

// SMTP server host. For local development with XAMPP, use 'localhost'.
// To use an external SMTP provider, set these values appropriately.
define('SMTP_HOST', 'localhost');
// SMTP username (leave empty for unauthenticated/local SMTP)
define('SMTP_USER', '');
// SMTP password (leave empty for unauthenticated/local SMTP)
define('SMTP_PASS', '');
// SMTP port (25 for local, 587 for TLS, 465 for SSL)
define('SMTP_PORT', 25);
// SMTP secure: 'tls' or 'ssl' (or empty for none)
define('SMTP_SECURE', '');

// From address and name used for outgoing verification emails
define('MAIL_FROM', 'irapapa7@gmail.com');
define('MAIL_FROM_NAME', 'REVCOM');

// Optional: override `isHTML` for messages (true to send HTML emails)
define('MAIL_IS_HTML', true);

// NOTE: install PHPMailer via composer in project root:
// composer require phpmailer/phpmailer

?>
