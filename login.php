<?php

session_start();

require_once __DIR__ . '/src/db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $sql = 'SELECT id, email, password_hash
            FROM admin_users
            WHERE email = :email
            LIMIT 1';

    $statement = $database->prepare($sql);

    $statement->execute([
        'email' => $email
    ]);

    $admin = $statement->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_email'] = $admin['email'];

        header('Location: dashboard.php');
        exit;

    } else {
        $message = 'The email or password is incorrect.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mini ISP Portal Login</title>
    <link rel="stylesheet" href="src/style.css">
</head>

<body class="auth-page">

  <div class="auth-card">
    <div class="brand">Mini ISP Portal</div>

    <h1>Administrator Login</h1>

    <?php if($message !== ''): ?>
      <p class="message error">
        <?= htmlspecialchars($message, ENT_QUOTES,'UTF-8') ?>
    </p>
    <?php endif; ?>


    <form method="POST" action="login.php">
        <p>
            <label for="email">Email address:</label>
            <br>
            <input
                type="email"
                id="email"
                name="email"
                required
            >
        </p>

        <p>
            <label for="password">Password:</label>
            <br>
            <input
                type="password"
                id="password"
                name="password"
                required
            >
        </p>

        <button type="submit">Log in</button>
    </form>

  </div>
</body>
</html>