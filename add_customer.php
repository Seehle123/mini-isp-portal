<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/src/db.php';

/*
|--------------------------------------------------------------------------
| Create a CSRF token for this session
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['csrf_token'])
    || !is_string($_SESSION['csrf_token'])
) {
    $_SESSION['csrf_token'] = bin2hex(
        random_bytes(32)
    );
}

$errors = [];

$username = '';
$fullName = '';
$email = '';
$plan = '';
$routerIp = '';
$status = 'enabled';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (
        !is_string($submittedToken)
        || !is_string($sessionToken)
        || $submittedToken === ''
        || $sessionToken === ''
        || !hash_equals(
            $sessionToken,
            $submittedToken
        )
    ) {
        http_response_code(403);
        exit('Invalid security token.');
    }

    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $plan = trim($_POST['plan'] ?? '');
    $routerIp = trim($_POST['router_ip'] ?? '');
    $status = $_POST['status'] ?? 'enabled';

    if ($username === '') {
        $errors[] = 'Username is required.';
    }

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }

    if ($plan === '') {
        $errors[] = 'Internet plan is required.';
    }

    if (!in_array($status, ['enabled', 'disabled'], true)) {
        $errors[] = 'Invalid customer status.';
    }

    if (count($errors) === 0) {
        $sql = 'INSERT INTO customers
                (username, full_name, email, plan, status, router_ip)
                VALUES
                (:username, :full_name, :email, :plan, :status, :router_ip)';

        try {
            $statement = $database->prepare($sql);

            $statement->execute([
                'username' => $username,
                'full_name' => $fullName,
                'email' => $email,
                'plan' => $plan,
                'status' => $status,
                'router_ip' => $routerIp !== '' ? $routerIp : null
            ]);

            header('Location: customers.php?added=1');
            exit;
        } catch (PDOException $error) {
            $errors[] = 'The customer could not be added. The username or email may already exist.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Customer | Mini ISP Portal</title>
    <link rel="stylesheet" href="src/style.css">
</head>
<body class="dashboard-page">
    <header class="topbar">
        <div class="brand">Mini ISP Portal</div>

        <div>
            <span class="badge">Administrator</span>
            <a class="button" href="logout.php">Log out</a>
        </div>
    </header>

    <main class="dashboard-container">
        <div class="page-heading">
            <div>
                <h1>Add Customer</h1>
                <p class="page-subtitle">
                    Create a new customer account.
                </p>
            </div>

            <div class="page-actions">
                <a class="button secondary-button" href="customers.php">
                    Back to customers
                </a>
            </div>
        </div>

        <section class="card form-card">
            <?php if (count($errors) > 0): ?>
                <div class="message error">
                    <?php foreach ($errors as $error): ?>
                        <p>
                            <?= htmlspecialchars(
                                $error,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="add_customer.php">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['csrf_token'],
                    ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Username</label>

                        <input
                            type="text"
                            id="username"
                            name="username"
                            value="<?= htmlspecialchars(
                                $username,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="full_name">Full name</label>

                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            value="<?= htmlspecialchars(
                                $fullName,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="email">Email address</label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= htmlspecialchars(
                                $email,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="plan">Internet plan</label>

                        <input
                            type="text"
                            id="plan"
                            name="plan"
                            placeholder="Example: Home 20 Mbps"
                            value="<?= htmlspecialchars(
                                $plan,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="router_ip">Router IP address</label>

                        <input
                            type="text"
                            id="router_ip"
                            name="router_ip"
                            placeholder="Example: 192.168.1.10"
                            value="<?= htmlspecialchars(
                                $routerIp,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="status">Account status</label>

                        <select id="status" name="status">
                            <option
                                value="enabled"
                                <?= $status === 'enabled'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Enabled
                            </option>

                            <option
                                value="disabled"
                                <?= $status === 'disabled'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Disabled
                            </option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit">Add customer</button>

                    <a
                        class="button secondary-button"
                        href="customers.php"
                    >
                        Cancel
                    </a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>