<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$plainTextPassword = "admin123";

$hashedPassword = password_hash($plainTextPassword, PASSWORD_DEFAULT);

echo "<h2>Password Hashing Result:</h2>";
echo "<p><strong>Original Password:</strong> " . htmlspecialchars($plainTextPassword) . "</p>";
echo "<p><strong>Hashed Password:</strong> <code>" . htmlspecialchars($hashedPassword) . "</code></p>";
echo "<p><em>Copy the hashed password string above to store in your database.</em></p>";

?>