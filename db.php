<?php
// ── Database Configuration ────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Change to your MySQL username
define('DB_PASS', '');           // Change to your MySQL password
define('DB_NAME', 'apex_intern');
define('DB_CHARSET', 'utf8mb4');

// ── PDO Connection with try-catch error handling (Task 3) ────────────────────
// Switched from MySQLi to PDO to support proper exception-based error handling.
// PDO::ERRMODE_EXCEPTION ensures ALL database errors throw a PDOException,
// which we catch with try-catch blocks throughout the application.

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // throw exceptions on error
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // return associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                   // use real prepared statements
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Log the real error for the developer, show a friendly message to the user
    error_log("DB Connection Error: " . $e->getMessage());
    die(renderDbError("Unable to connect to the database. Please try again later."));
}

// ── Helper: render a styled error page ───────────────────────────────────────
function renderDbError(string $message): string {
    return '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <title>Error — ApexPlanet</title>
    <style>
      body{font-family:Segoe UI,sans-serif;background:#f0f4f3;display:flex;align-items:center;justify-content:center;min-height:100vh;}
      .box{background:#fff;border-radius:12px;padding:2.5rem;max-width:460px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.08);}
      h2{color:#c0392b;margin-bottom:.6rem;}p{color:#555;}a{color:#3aafa9;}
    </style></head>
    <body><div class="box">
      <h2>⚠️ Something went wrong</h2>
      <p>' . htmlspecialchars($message) . '</p>
      <p style="margin-top:1rem"><a href="javascript:history.back()">← Go back</a></p>
    </div></body></html>';
}
?>
