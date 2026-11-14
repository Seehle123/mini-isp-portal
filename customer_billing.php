<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/src/db.php';

/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatBillingMonth($date): string
{
    $timestamp = strtotime((string) $date);

    if ($timestamp === false) {
        return 'Unknown month';
    }

    return date('F Y', $timestamp);
}

function formatMoney($amount): string
{
    return 'R' . number_format(
        (float) $amount,
        2
    );
}

function statusClass($status): string
{
    $allowedClasses = [
        'pending',
        'paid',
        'overdue',
        'cancelled'
    ];

    return in_array(
        $status,
        $allowedClasses,
        true
    )
        ? 'status-' . $status
        : 'status-pending';
}

/*
|--------------------------------------------------------------------------
| Get the customer ID
|--------------------------------------------------------------------------
*/

$customerId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$customerId) {
    http_response_code(400);
    exit('Invalid customer ID.');
}

/*
|--------------------------------------------------------------------------
| Load the customer
|--------------------------------------------------------------------------
*/

$customerStatement = $database->prepare(
    'SELECT
        id,
        username,
        full_name,
        email,
        plan,
        status,
        router_ip,
        created_at
     FROM customers
     WHERE id = :id'
);

$customerStatement->execute([
    'id' => $customerId
]);

$customer = $customerStatement->fetch();

if (!$customer) {
    http_response_code(404);
    exit('Customer not found.');
}

/*
|--------------------------------------------------------------------------
| Load billing summary
|--------------------------------------------------------------------------
*/

$summaryStatement = $database->prepare(
    "SELECT
        COUNT(*) AS total_records,
        COALESCE(SUM(amount), 0) AS total_billed,
        COALESCE(
            SUM(
                CASE
                    WHEN payment_status = 'paid'
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS total_paid,
        COALESCE(
            SUM(
                CASE
                    WHEN payment_status IN ('pending', 'overdue')
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS total_outstanding
     FROM billing_records
     WHERE customer_id = :customer_id"
);

$summaryStatement->execute([
    'customer_id' => $customerId
]);

$summary = $summaryStatement->fetch();

/*
|--------------------------------------------------------------------------
| Load the customer's billing records
|--------------------------------------------------------------------------
*/

$billingStatement = $database->prepare(
    'SELECT
        id,
        billing_month,
        amount,
        payment_status,
        payment_date,
        payment_reference,
        notes
     FROM billing_records
     WHERE customer_id = :customer_id
     ORDER BY
        billing_month DESC,
        id DESC'
);

$billingStatement->execute([
    'customer_id' => $customerId
]);

$billingRecords = $billingStatement->fetchAll();

$recordCount = count($billingRecords);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Billing | <?= e($customer['full_name']) ?>
    </title>

    <link
        rel="stylesheet"
        href="src/style.css"
    >
</head>

<body class="dashboard-page">

<header class="topbar">
    <div class="brand">
        Mini ISP Portal
    </div>

    <div>
        <span class="badge">
            Administrator
        </span>

        <a
            class="button"
            href="logout.php"
        >
            Log out
        </a>
    </div>
</header>

<main class="dashboard-container">

    <div class="page-heading">
        <div>
            <h1>Customer billing history</h1>

            <p class="page-subtitle">
                Billing records for
                <strong><?= e($customer['full_name']) ?></strong>.
            </p>
        </div>

        <div class="page-actions">
            <a
                class="button secondary-button"
                href="customers.php"
            >
                Customers
            </a>

            <a
                class="button secondary-button"
                href="billing.php"
            >
                All billing
            </a>

            <a
                class="button secondary-button"
                href="dashboard.php"
            >
                Dashboard
            </a>

            <button
    class="button"
    type="button"
    onclick="window.open(
        'print_statement.php?id=<?= (int) $customer['id'] ?>',
        '_blank'
    )"
>
    Print statement
</button>
            
        </div>
    </div>

    <section class="card">
        <h2>Customer details</h2>

        <div class="form-grid">
            <div class="form-group">
                <label>Full name</label>

                <p>
                    <?= e($customer['full_name']) ?>
                </p>
            </div>

            <div class="form-group">
                <label>Username</label>

                <p>
                    <?= e($customer['username']) ?>
                </p>
            </div>

            <div class="form-group">
                <label>Email</label>

                <p>
                    <?= e($customer['email']) ?>
                </p>
            </div>

            <div class="form-group">
                <label>Plan</label>

                <p>
                    <?= e($customer['plan']) ?>
                </p>
            </div>

            <div class="form-group">
                <label>Account status</label>

                <p>
                    <?php if (
                        $customer['status'] === 'enabled'
                    ): ?>
                        <span class="status-enabled">
                            Enabled
                        </span>
                    <?php else: ?>
                        <span class="status-disabled">
                            Disabled
                        </span>
                    <?php endif; ?>
                </p>
            </div>

            <div class="form-group">
                <label>Router IP</label>

                <p>
                    <?= e($customer['router_ip']) ?>
                </p>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>Billing summary</h2>

        <div class="billing-summary-grid">
            <div class="billing-summary-item">
                <span class="summary-label">
                    Billing records
                </span>

                <strong>
                    <?= (int) $summary['total_records'] ?>
                </strong>
            </div>

            <div class="billing-summary-item">
                <span class="summary-label">
                    Total billed
                </span>

                <strong>
                    <?= e(
                        formatMoney(
                            $summary['total_billed']
                        )
                    ) ?>
                </strong>
            </div>

            <div class="billing-summary-item">
                <span class="summary-label">
                    Total paid
                </span>

                <strong>
                    <?= e(
                        formatMoney(
                            $summary['total_paid']
                        )
                    ) ?>
                </strong>
            </div>

            <div class="billing-summary-item">
                <span class="summary-label">
                    Outstanding
                </span>

                <strong>
                    <?= e(
                        formatMoney(
                            $summary['total_outstanding']
                        )
                    ) ?>
                </strong>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>Billing records</h2>

        <p class="customer-count">
            Showing
            <?= $recordCount ?>
            billing record<?= $recordCount === 1 ? '' : 's' ?>.
        </p>

        <?php if ($recordCount === 0): ?>
            <p class="empty-message">
                No billing records exist for this customer yet.
            </p>

            <a
                class="button"
                href="billing.php"
            >
                Add billing record
            </a>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Billing month</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment date</th>
                            <th>Reference</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach (
                            $billingRecords as $record
                        ): ?>
                            <tr>
                                <td>
                                    <?= e(
                                        formatBillingMonth(
                                            $record[
                                                'billing_month'
                                            ]
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        formatMoney(
                                            $record['amount']
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <span
                                        class="<?= e(
                                            statusClass(
                                                $record[
                                                    'payment_status'
                                                ]
                                            )
                                        ) ?>"
                                    >
                                        <?= e(
                                            ucfirst(
                                                $record[
                                                    'payment_status'
                                                ]
                                            )
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e(
                                        $record['payment_date']
                                            ?: 'Not paid'
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $record[
                                            'payment_reference'
                                        ]
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $record['notes']
                                    ) ?>
                                </td>

                                <td class="actions-cell">
                                    <a
                                        class="button
                                        secondary-button"
                                        href="edit_billing.php?id=<?= (int) $record[
                                            'id'
                                        ] ?>"
                                    >
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</main>

<footer class="site-footer">
    Built by Sphesihle K
</footer>

</body>
</html>