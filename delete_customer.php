<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: customers.php');
    exit;
}

$customerId = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

$submittedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';

if (
    $customerId === false
    || $customerId === null
    || $customerId <= 0
) {
    header('Location: customers.php?delete_error=1');
    exit;
}

if (
    !is_string($submittedToken)
    || !is_string($sessionToken)
    || $sessionToken === ''
    || $submittedToken === ''
    || !hash_equals(
        $sessionToken,
        $submittedToken
    )
) {
    http_response_code(403);
    exit('Invalid request.');
}

require_once __DIR__ . '/src/db.php';

$sql = 'DELETE FROM customers WHERE id = :id';

try {
    $statement = $database->prepare($sql);

    $statement->execute([
        'id' => $customerId
    ]);

    header('Location: customers.php?deleted=1');
    exit;
} catch (PDOException $error) {
    header('Location: customers.php?delete_error=1');
    exit;
}