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

/*
|--------------------------------------------------------------------------
| Get customer ID
|--------------------------------------------------------------------------
*/
$customerId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (
    $customerId === false
    || $customerId === null
    || $customerId <= 0
) {
    http_response_code(400);
    exit('Invalid customer ID.');
}

/*
|--------------------------------------------------------------------------
| Load customer
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
        router_ip
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
| Load billing records
|--------------------------------------------------------------------------
*/

$billingStatement = $database->prepare(
    'SELECT
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

$statementDate = date('d F Y');
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
        Billing Statement | <?= e($customer['full_name']) ?>
    </title>

    <link
        rel="stylesheet"
        href="src/style.css"
    >

    <style>
        .statement-page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 32px 20px;
        }

        .statement-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            margin-bottom: 32px;
        }

.statement-title h1 {
            margin-bottom: 8px;
        }

        .statement-meta {
            text-align: right;
            color: #64748b;
        }

        .statement-customer {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(220px, 1fr)
            );
            gap: 16px;
            margin-bottom: 28px;
        }

        .statement-customer-item {
            padding: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
        }

        .statement-customer-item strong {
            display: block;
            margin-top: 6px;
        }

.statement-summary {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(180px, 1fr)
            );
            gap: 16px;
            margin-bottom: 28px;
        }

        .statement-summary-item {
            padding: 18px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }

        .statement-summary-item span {
            display: block;
            color: #64748b;
            font-size: 0.9rem;
        }

        .statement-summary-item strong {
            display: block;
            margin-top: 8px;
            font-size: 1.25rem;
        }

.statement-footer {
            margin-top: 32px;
            padding-top: 18px;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 0.9rem;
        }

        .status-pending,
        .status-paid,
        .status-overdue,
        .status-cancelled {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-pending {
            color: #92400e;
            background: #fef3c7;
        }

        .status-paid {
            color: #166534;
            background: #dcfce7;
        }

        .status-overdue {
            color: #991b1b;
            background: #fee2e2;
        }

.status-cancelled {
            color: #475569;
            background: #e2e8f0;
        }

        @media print {
            body {
                background: #ffffff;
            }

            .no-print {
                display: none !important;
            }

            .statement-page {
                max-width: none;
                padding: 0;
            }

            .statement-customer-item,
            .statement-summary-item {
                break-inside: avoid;
            }

            .data-table {
                font-size: 0.85rem;
            }

            a {
                color: #000000;
                text-decoration: none;
            }
        }
    </style>
</head>

<body>

<main class="statement-page">

    <div class="statement-header">
        <div class="statement-title">
            <h1>Mini ISP Portal</h1>

            <p>
                Customer billing statement
            </p>
        </div>

        <div class="statement-meta">
            <p>
                <strong>Statement date</strong>
            </p>

            <p>
                <?= e($statementDate) ?>
            </p>

            <button
                class="button no-print"
                type="button"
                onclick="window.print()"
            >
                Print
            </button>

            <a
                class="button secondary-button no-print"
                href="customer_billing.php?id=<?= (int) $customer['id'] ?>"
            >
Back
            </a>
        </div>
    </div>

    <section class="statement-customer">
        <div class="statement-customer-item">
            <span>Full name</span>

            <strong>
                <?= e($customer['full_name']) ?>
            </strong>
        </div>

        <div class="statement-customer-item">
            <span>Username</span>

            <strong>
                <?= e($customer['username']) ?>
            </strong>
        </div>

        <div class="statement-customer-item">
            <span>Email</span>

            <strong>
                <?= e($customer['email']) ?>
            </strong>
        </div>

        <div class="statement-customer-item">
            <span>Plan</span>

<strong>
                <?= e($customer['plan']) ?>
            </strong>
        </div>

        <div class="statement-customer-item">
            <span>Router IP</span>

            <strong>
                <?= e($customer['router_ip']) ?>
            </strong>
        </div>
    </section>

    <section class="statement-summary">
        <div class="statement-summary-item">
            <span>Total billing records</span>

            <strong>
                <?= (int) $summary['total_records'] ?>
            </strong>
        </div>

        <div class="statement-summary-item">
            <span>Total billed</span>

            <strong>
                <?= e(
                    formatMoney(
                        $summary['total_billed']
                    )
                ) ?>
            </strong>
        </div>

<div class="statement-summary-item">
            <span>Total paid</span>

            <strong>
                <?= e(
                    formatMoney(
                        $summary['total_paid']
                    )
                ) ?>
            </strong>
        </div>

        <div class="statement-summary-item">
            <span>Outstanding balance</span>

            <strong>
                <?= e(
                    formatMoney(
                        $summary['total_outstanding']
                    )
                ) ?>
            </strong>
        </div>
    </section>

    <section>
        <h2>Billing records</h2>

        <?php if (count($billingRecords) === 0): ?>
            <p>
                No billing records exist for this customer.
            </p>
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <footer class="statement-footer">
        <p>
            This statement was generated by Mini ISP Portal.
        </p>
    </footer>

</main>

</body>
</html>
