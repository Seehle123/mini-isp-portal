<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

require_once __DIR__ . '/src/db.php';

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';

if (!in_array($statusFilter, ['all', 'enabled', 'disabled'], true)) {
    $statusFilter = 'all';
}

$sql = 'SELECT
            id,
            username,
            full_name,
            email,
            plan,
            status,
            router_ip,
            created_at
        FROM customers';

$where = [];
$parameters = [];

if ($search !== '') {
    $where[] = '(
        username LIKE :search
        OR full_name LIKE :search
        OR email LIKE :search
        OR plan LIKE :search
    )';

    $parameters['search'] = '%' . $search . '%';
}

if ($statusFilter !== 'all') {
    $where[] = 'status = :status';

    $parameters['status'] = $statusFilter;
}

if (count($where) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY created_at DESC';

$statement = $database->prepare($sql);
$statement->execute($parameters);
$customers = $statement->fetchAll();

$resultCount = count($customers);

$successMessage = '';
$errorMessage = '';

if (isset($_GET['added']) && $_GET['added'] === '1') {
    $successMessage = 'Customer added successfully.';
}

if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $successMessage = 'Customer updated successfully.';
}

if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $successMessage = 'Customer deleted successfully.';
}

if (
    isset($_GET['delete_error'])
    && $_GET['delete_error'] === '1'
) {
    $errorMessage = 'The customer could not be deleted.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers | Mini ISP Portal</title>
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
        <h1>Customers</h1>
        <p class="page-subtitle">
            Manage your ISP customers.
        </p>
    </div>

    <div class="page-actions">
        <a class="button" href="add_customer.php">
            Add customer
        </a>

        <a class="button secondary-button" href="dashboard.php">
            Dashboard
        </a>

        <a class="button"
        href="billing.php"
        > Billing
        </a>
    </div>
</div>

<section class="card filter-card">
    <form
        class="filter-form"
        method="GET"
        action="customers.php"
    >
        <div class="filter-group">
            <label for="search">Search customers</label>

            <input
                type="search"
                id="search"
                name="search"
                placeholder="Username, name, email, or plan"
                value="<?= htmlspecialchars(
                    $search,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >
        </div>

        <div class="filter-group">
            <label for="status">Account status</label>

            <select id="status" name="status">
                <option
                    value="all"
                    <?= $statusFilter === 'all' ? 'selected' : '' ?>
                >
                    All statuses
                </option>
<option
                    value="enabled"
                    <?= $statusFilter === 'enabled'
                        ? 'selected'
                        : '' ?>
                >
                    Enabled only
                </option>

                <option
                    value="disabled"
                    <?= $statusFilter === 'disabled'
                        ? 'selected'
                        : '' ?>
                >
                    Disabled only
                </option>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit">Search</button>

            <a
                class="button secondary-button"
                href="customers.php"
            >
                Reset
            </a>
        </div>
    </form>
</section>



        <section class="card">
            <?php if ($errorMessage !== ''): ?>
                <p class="message error">
                    <?= htmlspecialchars(
                        $errorMessage,
                        ENT_QUOTES,
                    'UTF-8'
                ) ?>
        </p>
    <?php endif; ?>

    <?php if ($successMessage !== ''): ?>
        <p class="message success">
            <?= htmlspecialchars(
                $successMessage,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>
    <?php endif; ?>

<p class="customer-count">
    Showing <?= $resultCount ?> customer<?= $resultCount === 1 ? '' : 's' ?>.
</p>

<?php if (count($customers) === 0): ?>
    <p class="empty-message">
        No customers have been added yet.
    </p>
<?php else: ?>
    <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full name</th>
                                <th>Email</th>
                                <th>Plan</th>
                                <th>Status</th>
                                <th>Router IP</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars(
                                            $customer['username'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $customer['full_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $customer['email'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $customer['plan'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?php if ($customer['status'] === 'enabled'): ?>
                                            <span class="status-enabled">
                                                Enabled
                                            </span>
                                        <?php else: ?>
                                            <span class="status-disabled">
                                                Disabled
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $customer['router_ip'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>
                                    <td class="actions-cell">

                                    <a class="button secondary-button"
                                    href="customer_billing.php?id=<?= (int)
                                    $customer['id'] ?>"
                                    > View billing
                                        </a>
    <a
        class="button secondary-button"
        href="edit_customer.php?id=<?= (int) $customer['id'] ?>"
    >
        Edit
    </a>

    <form
        class="inline-form"
        method="POST"
        action="delete_customer.php"
        onsubmit="return confirm(
            'Are you sure you want to delete this customer?'
        );"
    >
        <input
            type="hidden"
            name="id"
            value="<?= (int) $customer['id'] ?>"
        >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $csrfToken,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <button
            class="button danger-button"
            type="submit"

        >
            Delete
        </button>
    </form>
</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <footer class="site-footer">Built by Sphesihle K
</footer>
</body>
</html>