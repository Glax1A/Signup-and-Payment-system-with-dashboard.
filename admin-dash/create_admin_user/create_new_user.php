<?php
require_once '../config.php';

$username = 'admin2';
$password = 'password';
$password_hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:username, :password_hash)');
$stmt->execute(['username' => $username, 'password_hash' => $password_hash]);

echo "Admin user created successfully. NOT FOR USE IN PRODUCTION. IF FOUND, CONTACT WEBSITE ADMINISTRATOR IMMEDIATELY.";
?>




<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $conn->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:username, :password_hash)');
    
    if ($stmt->execute(['username' => $username, 'password_hash' => $password_hash])) {
        echo "Admin user created successfully.";
    } else {
        echo "Error: Could not create user.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Signup</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h2>Signup</h2>
    <form method="post" action="">
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" required>
        <br>
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>
        <br>
        <button type="submit">Signup</button>
    </form>
    <p>NOT FOR USE IN PRODUCTION. IF FOUND, CONTACT WEBSITE ADMINISTRATOR IMMEDIATELY.</p>
</body>
</html>
