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

$successMessage = '';
$errorMessage = '';

$allowedStatuses = [
    'pending',
    'paid',
    'overdue',
    'cancelled'
];

/*
|--------------------------------------------------------------------------
| Add or delete billing records
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($postedToken)
    || !is_string($csrfToken)
    || $postedToken === ''
    || $csrfToken === ''
    || !hash_equals(
        $csrfToken,
        $postedToken
    )
) {
    $errorMessage = 'Invalid security token. Please try again.';
} else {
        $action = $_POST['action'] ?? '';

        /*
        |--------------------------------------------------------------------------
        | Add billing record
        |--------------------------------------------------------------------------
        */
        if ($action === 'add') {
            $customerId = filter_input(
                INPUT_POST,
                'customer_id',
                FILTER_VALIDATE_INT
            );

            $billingMonth = trim(
                $_POST['billing_month'] ?? ''
            );

            $amount = trim(
                $_POST['amount'] ?? ''
            );

            $paymentStatus = trim(
                $_POST['payment_status'] ?? 'pending'
            );

            $paymentDate = trim(
                $_POST['payment_date'] ?? ''
            );

            $paymentReference = trim(
                $_POST['payment_reference'] ?? ''
            );

            $notes = trim(
                $_POST['notes'] ?? ''
            );

            if (!$customerId) {
                $errorMessage = 'Please select a customer.';
            } elseif (
                !preg_match(
                    '/^\d{4}-\d{2}$/',
                    $billingMonth
                )
            ) {
                $errorMessage = 'Please select a valid billing month.';
            } elseif (
                $amount === ''
                || !is_numeric($amount)
                || (float) $amount < 0
            ) {
                $errorMessage = 'Please enter a valid amount.';
            } elseif (
                !in_array(
                    $paymentStatus,
                    $allowedStatuses,
                    true
                )
            ) {
                $errorMessage = 'Please select a valid payment status.';
            } elseif (
                $paymentDate !== ''
                && !preg_match(
                    '/^\d{4}-\d{2}-\d{2}$/',
                    $paymentDate
                )
            ) {
                $errorMessage = 'Please enter a valid payment date.';
            } else {
                $billingMonthDate = $billingMonth . '-01';

                try {
                    $insertSql = '
                        INSERT INTO billing_records (
                            customer_id,
                            billing_month,
                            amount,
                            payment_status,
                            payment_date,
                            payment_reference,
                            notes
                        ) VALUES (
                            :customer_id,
                            :billing_month,
                            :amount,
                            :payment_status,
                            :payment_date,
                            :payment_reference,
                            :notes
                        )
                    ';

                    $insertStatement = $database->prepare(
                        $insertSql
                    );

                    $insertStatement->execute([
                        'customer_id' => $customerId,
                        'billing_month' => $billingMonthDate,
                        'amount' => number_format(
                            (float) $amount,
                            2,
                            '.',
                            ''
                        ),
                        'payment_status' => $paymentStatus,
                        'payment_date' => $paymentDate !== ''
                            ? $paymentDate
                            : null,
                        'payment_reference' =>
                            $paymentReference !== ''
                                ? $paymentReference
                                : null,
                        'notes' => $notes !== ''
                            ? $notes
                            : null
                    ]);

                    header(
                        'Location: billing.php?added=1'
                    );
                    exit;
                } catch (PDOException $error) {
                    if (
                        $error->getCode() === '23000'
                    ) {
                        $errorMessage =
                            'A billing record already exists for '
                            . 'this customer and month.';
                    } else {
                        $errorMessage =
                            'The billing record could not be added.';
                    }
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Delete billing record
        |--------------------------------------------------------------------------
        */
        if ($action === 'delete') {
            $billingId = filter_input(
                INPUT_POST,
                'billing_id',
                FILTER_VALIDATE_INT
            );

            if (!$billingId) {
                $errorMessage =
                    'The billing record ID is invalid.';
            } else {
                $deleteStatement = $database->prepare(
                    'DELETE FROM billing_records
                     WHERE id = :id'
                );

                $deleteStatement->execute([
                    'id' => $billingId
                ]);

                header(
                    'Location: billing.php?deleted=1'
                );
                exit;
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Success messages
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['added'])
    && $_GET['added'] === '1'
) {
    $successMessage =
        'Billing record added successfully.';
} elseif (
    isset($_GET['deleted'])
    && $_GET['deleted'] === '1'
) {
    $successMessage =
        'Billing record deleted successfully.';
} elseif (
    isset($_GET['updated'])
    && $_GET['updated'] === '1'
) {
    $successMessage =
        'Billing record updated successfully.';
}


/*
|--------------------------------------------------------------------------
| Load customers for the dropdown
|--------------------------------------------------------------------------
*/

$customerStatement = $database->query(
    'SELECT
        id,
        username,
        full_name
     FROM customers
     ORDER BY full_name ASC, username ASC'
);

$customers = $customerStatement->fetchAll();

/*
|--------------------------------------------------------------------------
| Billing filters
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

$statusFilter = $_GET['status'] ?? 'all';

$allowedStatusFilters = [
    'all',
    'pending',
    'paid',
    'overdue',
    'cancelled'
];

if (
    !in_array(
        $statusFilter,
        $allowedStatusFilters,
        true
    )
) {
    $statusFilter = 'all';
}

$billingMonthFilter = trim(
    $_GET['billing_month'] ?? ''
);

if (
    $billingMonthFilter !== ''
    && !preg_match(
        '/^\d{4}-\d{2}$/',
        $billingMonthFilter
    )
) {
    $billingMonthFilter = '';
}

/*
|--------------------------------------------------------------------------
| Load filtered billing records
|--------------------------------------------------------------------------
*/

$billingSql = '
    SELECT
        billing_records.id,
        billing_records.customer_id,
        billing_records.billing_month,
        billing_records.amount,
        billing_records.payment_status,
        billing_records.payment_date,
        billing_records.payment_reference,
        billing_records.notes,
        customers.username,
        customers.full_name
    FROM billing_records
    INNER JOIN customers
        ON billing_records.customer_id = customers.id
';

$where = [];
$parameters = [];

if ($search !== '') {
    $where[] = '(
        customers.username LIKE :username_search
        OR customers.full_name LIKE :name_search
    )';

    $searchValue = '%' . $search . '%';

    $parameters['username_search'] = $searchValue;
    $parameters['name_search'] = $searchValue;
}

if ($statusFilter !== 'all') {
    $where[] = '
        billing_records.payment_status = :payment_status
    ';

    $parameters['payment_status'] = $statusFilter;
}

if ($billingMonthFilter !== '') {
    $where[] = '
        billing_records.billing_month = :billing_month
    ';

    $parameters['billing_month'] =
        $billingMonthFilter . '-01';
}

if (count($where) > 0) {
    $billingSql .= ' WHERE ' . implode(
        ' AND ',
        $where
    );
}

$billingSql .= '
    ORDER BY
        billing_records.billing_month DESC,
        billing_records.id DESC
';

$billingStatement = $database->prepare($billingSql);
$billingStatement->execute($parameters);

$billingRecords = $billingStatement->fetchAll();

$recordCount = count($billingRecords);


function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatBillingMonth(string $date): string
{
    return date('F Y', strtotime($date));
}

function formatMoney(string $amount): string
{
    return 'R' . number_format(
        (float) $amount,
        2
    );
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

    <title>Billing | Mini ISP Portal</title>

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
            <h1>Billing records</h1>

            <p class="page-subtitle">
                Manage customer billing and payment records.
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
                href="dashboard.php"
            >
                Dashboard
            </a>
        </div>

        <section class="card filter-card">
    <form
        class="filter-form"
        method="GET"
        action="billing.php"
    >
        <div class="filter-group">
            <label for="search">
                Search customer
            </label>

            <input
                type="search"
                id="search"
                name="search"
                placeholder="Name or username"
                value="<?= e($search) ?>"
            >
        </div>

        <div class="filter-group">
            <label for="status">
                Payment status
            </label>

            <select
                id="status"
                name="status"
            >
                <option
                    value="all"
                    <?= $statusFilter === 'all'
                        ? 'selected'
                        : '' ?>
                >
                    All statuses
                </option>

                <option
                    value="pending"
                    <?= $statusFilter === 'pending'
                        ? 'selected'
                        : '' ?>
                >
                    Pending
                </option>

                <option
                    value="paid"
                    <?= $statusFilter === 'paid'
                        ? 'selected'
                        : '' ?>
                >
                    Paid
                </option>

                <option
                    value="overdue"
                    <?= $statusFilter === 'overdue'
                        ? 'selected'
                        : '' ?>
                >
                    Overdue
                </option>

                <option
                    value="cancelled"
                    <?= $statusFilter === 'cancelled'
                        ? 'selected'
                        : '' ?>
                >
                    Cancelled
                </option>
            </select>
        </div>

        <div class="filter-group">
            <label for="billing_month">
                Billing month
            </label>

            <input
                type="month"
                id="billing_month"
                name="billing_month"
                value="<?= e($billingMonthFilter) ?>"
            >
        </div>

        <div class="filter-actions">
            <button
                class="button"
                type="submit"
            >
                Filter
            </button>

            <a
                class="button secondary-button"
                href="billing.php"
            >
                Reset
            </a>
        </div>
    </form>
</section>

    </div>

    <?php if ($errorMessage !== ''): ?>
        <p class="message error">
            <?= e($errorMessage) ?>
        </p>
    <?php endif; ?>

    <?php if ($successMessage !== ''): ?>
        <p class="message success">
            <?= e($successMessage) ?>
        </p>
    <?php endif; ?>

    <section class="card">
        <h2>Add billing record</h2>

        <?php if (count($customers) === 0): ?>
            <p class="empty-message">
                Add a customer before creating a billing record.
            </p>
        <?php else: ?>
            <form
                method="POST"
                action="billing.php"
                class="billing-form"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrfToken) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="add"
                >

                <div class="form-grid">
                    <div class="form-group">
                        <label for="customer_id">
                            Customer
                        </label>

                        <select
                            id="customer_id"
                            name="customer_id"
                            required
                        >
                            <option value="">
                                Select a customer
                            </option>

                            <?php foreach ($customers as $customer): ?>
                                <option
                                    value="<?= (int) $customer['id'] ?>"
                                >
                                    <?= e(
                                        $customer['full_name']
                                    ) ?>
                                    -
                                    <?= e(
                                        $customer['username']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="billing_month">
                            Billing month
                        </label>

                        <input
                            type="month"
                            id="billing_month"
                            name="billing_month"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="amount">
                            Amount
                        </label>

                        <input
                            type="number"
                            id="amount"
                            name="amount"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="payment_status">
                            Payment status
                        </label>

                        <select
                            id="payment_status"
                            name="payment_status"
                            required
                        >
                            <option value="pending">
                                Pending
                            </option>

                            <option value="paid">
                                Paid
                            </option>

                            <option value="overdue">
                                Overdue
                            </option>

                            <option value="cancelled">
                                Cancelled
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="payment_date">
                            Payment date
                        </label>

                        <input
                            type="date"
                            id="payment_date"
                            name="payment_date"
                        >
                    </div>

                    <div class="form-group">
                        <label for="payment_reference">
                            Payment reference
                        </label>

                        <input
                            type="text"
                            id="payment_reference"
                            name="payment_reference"
                            maxlength="100"
                            placeholder="Optional reference"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">
                        Notes
                    </label>

                    <textarea
                        id="notes"
                        name="notes"
                        rows="3"
                        placeholder="Optional billing notes"
                    ></textarea>
                </div>

                <button
                    class="button"
                    type="submit"
                >
                    Add billing record
                </button>
            </form>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Billing history</h2>

        <p class="customer-count">
            Showing
            <?= $recordCount ?>
            billing record<?= $recordCount === 1 ? '' : 's' ?>.
            <?php if (
                $search !== ''
                || $statusFilter !== 'all'
                || $billingMonthFilter !== ''
            ): ?>
            matching your filters
            <?php endif; ?>
        </p>

        <?php if ($recordCount === 0): ?>
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
                                    <strong>
                                        <?= e(
                                            $record['full_name']
                                        ) ?>
                                    </strong>
                                    <br>
                                    <small>
                                        <?= e(
                                            $record['username']
                                        ) ?>
                                    </small>
                                </td>

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
                                    <?= e(
                                        ucfirst(
                                            $record[
                                                'payment_status'
                                            ]
                                        )
                                    ) ?>
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
                                      class="button secondary-button"
                                      href="edit_billing.php?id=<?= (int) $record['id'] ?>"
                                    >
                                  Edit
                                </a>

                              <form
                                        method="POST"
                                        action="billing.php"
                                        onsubmit="return confirm(
                                            'Delete this billing record?'
                                        );"
                                    >
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e(
                                                $csrfToken
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="billing_id"
                                            value="<?= (int) $record[
                                                'id'
                                            ] ?>"
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

<footer class="site-footer">
    Built by Sphesihle K
</footer>

</body>
</html>