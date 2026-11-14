<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$adminEmail = $_SESSION['admin_email'] ?? '';

require_once __DIR__ . '/src/db.php';

/*
|--------------------------------------------------------------------------
| Get customer totals
|--------------------------------------------------------------------------
*/

$totalCustomersStatement = $database->query(
    'SELECT COUNT(*) FROM customers'
);

$totalCustomers = (int) $totalCustomersStatement->fetchColumn();

$activeCustomersStatement = $database->query(
    "SELECT COUNT(*)
     FROM customers
     WHERE status = 'enabled'"
);

$activeCustomers = (int) $activeCustomersStatement->fetchColumn();

$disabledCustomersStatement = $database->query(
    "SELECT COUNT(*)
     FROM customers
     WHERE status = 'disabled'"
);

$disabledCustomers = (int) $disabledCustomersStatement->fetchColumn();

/*
|--------------------------------------------------------------------------
| Get billing totals
|--------------------------------------------------------------------------
*/

$billingSummarySql = "
    SELECT
        COUNT(*) AS total_billing_records,

        COALESCE(
            SUM(amount),
            0
        ) AS total_billed,

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
                    WHEN payment_status = 'pending'
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS total_pending,

        COALESCE(
            SUM(
                CASE
                    WHEN payment_status = 'overdue'
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS total_overdue,

        COALESCE(
            SUM(
                CASE
                    WHEN payment_status IN ('pending', 'overdue')
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS total_outstanding,

        COALESCE(
            SUM(
                CASE
                    WHEN payment_status = 'overdue'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS overdue_records

    FROM billing_records
";

$billingSummaryStatement = $database->query(
    $billingSummarySql
);

$billingSummary = $billingSummaryStatement->fetch();

/*
|--------------------------------------------------------------------------
| Get recent billing activity
|--------------------------------------------------------------------------
*/

$recentBillingStatement = $database->query(
    "SELECT
        billing_records.id,
        billing_records.billing_month,
        billing_records.amount,
        billing_records.payment_status,
        billing_records.payment_date,
        customers.full_name,
        customers.username
     FROM billing_records
     INNER JOIN customers
        ON billing_records.customer_id = customers.id
     ORDER BY
        billing_records.billing_month DESC,
        billing_records.id DESC
     LIMIT 5"
);

$recentBillingRecords = $recentBillingStatement->fetchAll();

/*
|--------------------------------------------------------------------------
| Format money
|--------------------------------------------------------------------------
*/

function formatMoney($amount): string
{
    return 'R' . number_format(
        (float) $amount,
        2
    );
}

function statusClass($status): string
{
    $allowedStatuses = [
        'pending',
        'paid',
        'overdue',
        'cancelled'
    ];

    if (!in_array($status, $allowedStatuses, true)) {
        return 'status-pending';
    }

    return 'status-' . $status;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Admin Dashboard | Mini ISP Portal</title>

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

    <section class="welcome-card">
        <h1>Administrator Dashboard</h1>

        <p>
            Welcome,
            <?= htmlspecialchars(
                $adminEmail,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>

        <p>
            You are logged in successfully.
        </p>

        <div class="page-actions">
            <a
                class="button"
                href="customers.php"
            >
                View customers
            </a>

            <a
                class="button"
                href="billing.php"
            >
                View billing
            </a>
        </div>
    </section>

    <section class="dashboard-summary">

        <div class="summary-card">
            <span class="summary-label">
                Total customers
            </span>

            <strong class="summary-value">
                <?= $totalCustomers ?>
            </strong>
        </div>

        <div class="summary-card">
            <span class="summary-label">
                Active customers
            </span>

            <strong class="summary-value">
                <?= $activeCustomers ?>
            </strong>
        </div>

        <div class="summary-card">
            <span class="summary-label">
                Disabled customers
            </span>

            <strong class="summary-value">
                <?= $disabledCustomers ?>
            </strong>
        </div>

        <div class="summary-card">
            <span class="summary-label">
                Billing records
            </span>

            <strong class="summary-value">
                <?= (int) $billingSummary[
                    'total_billing_records'
                ] ?>
            </strong>
        </div>

        <div class="summary-card">
            <span class="summary-label">
                Total billed
            </span>

            <strong class="summary-value">
                <?= formatMoney(
                    $billingSummary['total_billed']
                ) ?>
            </strong>
        </div>

        <div class="summary-card summary-paid">
            <span class="summary-label">
                Total paid
            </span>

            <strong class="summary-value">
                <?= formatMoney(
                    $billingSummary['total_paid']
                ) ?>
            </strong>
        </div>

        <div class="summary-card summary-pending">
            <span class="summary-label">
                Pending amount
            </span>

            <strong class="summary-value">
                <?= formatMoney(
                    $billingSummary['total_pending']
                ) ?>
            </strong>
        </div>

        <div class="summary-card summary-outstanding">
            <span class="summary-label">
                Outstanding amount
            </span>

            <strong class="summary-value">
                <?= formatMoney(
                    $billingSummary['total_outstanding']
                ) ?>
            </strong>
        </div>

        <div class="summary-card summary-overdue">
            <span class="summary-label">
                Overdue amount
            </span>

            <strong class="summary-value">
                <?= formatMoney(
                    $billingSummary['total_overdue']
                ) ?>
            </strong>
        </div>

        <div class="summary-card summary-overdue">
            <span class="summary-label">
                Overdue records
            </span>

            <strong class="summary-value">
                <?= (int) $billingSummary[
                    'overdue_records'
                ] ?>
            </strong>
        </div>

    </section>

    <section class="card">
        <div class="page-heading">
            <div>
                <h2>Recent billing activity</h2>

                <p class="page-subtitle">
                    The five most recent customer billing records.
                </p>
            </div>

            <div class="page-actions">
                <a
                    class="button secondary-button"
                    href="billing.php"
                >
                    View all billing
                </a>
            </div>
        </div>

        <?php if (count($recentBillingRecords) === 0): ?>
            <p class="empty-message">
                No billing records have been added yet.
            </p>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Billing month</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment date</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach (
                            $recentBillingRecords as $record
                        ): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $record['full_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </strong>

                                    <small>
                                        <?= htmlspecialchars(
                                            $record['username'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </small>
                                </td>

                                <td>
                                    <?php
                                    $billingTimestamp = strtotime(
                                        $record['billing_month']
                                    );

                                    $billingMonth = $billingTimestamp !== false
                                        ? date(
                                            'F Y',
                                            $billingTimestamp
                                        )
                                        : 'Unknown month';
                                    ?>

                                    <?= htmlspecialchars(
                                        $billingMonth,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= formatMoney(
                                        $record['amount']
                                    ) ?>
                                </td>

                                <td>
                                    <span
                                        class="<?= htmlspecialchars(
                                            statusClass(
                                                $record['payment_status']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >
                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $record['payment_status']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $record['payment_date']
                                            ?: 'Not paid',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <a
                                        class="button secondary-button"
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

    <section class="card dashboard-links">
        <h2>Quick actions</h2>

        <div class="page-actions">
            <a
                class="button"
                href="add_customer.php"
            >
                Add customer
            </a>

            <a
                class="button"
                href="billing.php"
            >
                Add billing record
            </a>

            <a
                class="button secondary-button"
                href="customers.php"
            >
                Manage customers
            </a>
        </div>
    </section>

</main>

<footer class="site-footer">
    Built by Sphesihle K
</footer>

</body>
</html>