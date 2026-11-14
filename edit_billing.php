<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(
        random_bytes(32)
    );
}

$csrfToken = $_SESSION['csrf_token'];

require_once __DIR__ . '/src/db.php';

$allowedStatuses = [
    'pending',
    'paid',
    'overdue',
    'cancelled'
];

$errorMessage = '';

/*
|--------------------------------------------------------------------------
| Get the billing record ID
|--------------------------------------------------------------------------
*/

$billingId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (
    $billingId === false
    || $billingId === null
    || $billingId <= 0
) {
    http_response_code(400);
    exit('Invalid billing record ID.');
}

/*
|--------------------------------------------------------------------------
| Load the existing billing record
|--------------------------------------------------------------------------
*/

$recordStatement = $database->prepare(
    'SELECT
        id,
        customer_id,
        billing_month,
        amount,
        payment_status,
        payment_date,
        payment_reference,
        notes
     FROM billing_records
     WHERE id = :id'
);

$recordStatement->execute([
    'id' => $billingId
]);

$billingRecord = $recordStatement->fetch();

if (!$billingRecord) {
    http_response_code(404);
    exit('Billing record not found.');
}

/*
|--------------------------------------------------------------------------
| Prepare form values
|--------------------------------------------------------------------------
*/

$formCustomerId = (string) $billingRecord['customer_id'];

$formBillingMonth = substr(
    (string) $billingRecord['billing_month'],
    0,
    7
);

$formAmount = (string) $billingRecord['amount'];

$formPaymentStatus =
    (string) $billingRecord['payment_status'];

$formPaymentDate =
    (string) ($billingRecord['payment_date'] ?? '');

$formPaymentReference =
    (string) ($billingRecord['payment_reference'] ?? '');

$formNotes =
    (string) ($billingRecord['notes'] ?? '');

/*
|--------------------------------------------------------------------------
| Process the update
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
    $errorMessage =
        'Invalid security token. Please try again.';
} else {
        $postedCustomerId = filter_input(
            INPUT_POST,
            'customer_id',
            FILTER_VALIDATE_INT
        );

        $customerExists = false;

if (
    $postedCustomerId !== false
    && $postedCustomerId !== null
    && $postedCustomerId > 0
) {
    $customerCheckStatement = $database->prepare(
        'SELECT COUNT(*)
         FROM customers
         WHERE id = :id'
    );

    $customerCheckStatement->execute([
        'id' => $postedCustomerId
    ]);

    $customerExists = (
        (int) $customerCheckStatement->fetchColumn() > 0
    );
}

        $postedBillingMonth = trim(
            $_POST['billing_month'] ?? ''
        );

        $postedAmount = trim(
            $_POST['amount'] ?? ''
        );

        $postedPaymentStatus = trim(
            $_POST['payment_status'] ?? ''
        );

        $postedPaymentDate = trim(
            $_POST['payment_date'] ?? ''
        );

        $postedPaymentReference = trim(
            $_POST['payment_reference'] ?? ''
        );

        $postedNotes = trim(
            $_POST['notes'] ?? ''
        );

        /*
        |--------------------------------------------------------------------------
        | Keep submitted values in the form if validation fails
        |--------------------------------------------------------------------------
        */

        $formCustomerId = $postedCustomerId
            ? (string) $postedCustomerId
            : '';

        $formBillingMonth = $postedBillingMonth;
        $formAmount = $postedAmount;
        $formPaymentStatus = $postedPaymentStatus;
        $formPaymentDate = $postedPaymentDate;
        $formPaymentReference = $postedPaymentReference;
        $formNotes = $postedNotes;

        /*
        |--------------------------------------------------------------------------
        | Validate the submitted values
        |--------------------------------------------------------------------------
        */

        if (
            $postedCustomerId === false
            || $postedCustomerId === null
            || $postedCustomerId <= 0
        ) {
            $errorMessage =
                'Please select a valid customer.';
        } elseif (!$customerExists) {
            $errorMessage =
                'The selected customer does not exist.';
        } elseif (
            !preg_match(
                '/^\d{4}-\d{2}$/',
                $postedBillingMonth
            )
        ) {
            $errorMessage =
                'Please select a valid billing month.';
        } elseif (
            $postedAmount === ''
            || !is_numeric($postedAmount)
            || (float) $postedAmount < 0
        ) {
            $errorMessage =
                'Please enter a valid amount.';
        } elseif (
            !in_array(
                $postedPaymentStatus,
                $allowedStatuses,
                true
            )
        ) {
            $errorMessage =
                'Please select a valid payment status.';
        } elseif (
            $postedPaymentDate !== ''
            && !preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $postedPaymentDate
            )
        ) {
            $errorMessage =
                'Please enter a valid payment date.';
        } elseif (
            strlen($postedPaymentReference) > 100
        ) {
            $errorMessage =
                'The payment reference is too long.';
        } else {
            $billingMonthDate =
                $postedBillingMonth . '-01';

            /*
            |--------------------------------------------------------------------------
            | Check for duplicate customer and billing month
            |--------------------------------------------------------------------------
            */

            $duplicateStatement = $database->prepare(
                'SELECT COUNT(*)
                 FROM billing_records
                 WHERE customer_id = :customer_id
                   AND billing_month = :billing_month
                   AND id != :id'
            );

            $duplicateStatement->execute([
                'customer_id' => $postedCustomerId,
                'billing_month' => $billingMonthDate,
                'id' => $billingId
            ]);

            $duplicateCount = (int) (
                $duplicateStatement->fetchColumn()
            );

            if ($duplicateCount > 0) {
                $errorMessage =
                    'A billing record already exists for '
                    . 'this customer and month.';
            } else {
                try {
                    $updateStatement = $database->prepare(
                        'UPDATE billing_records
                         SET
                            customer_id = :customer_id,
                            billing_month = :billing_month,
                            amount = :amount,
                            payment_status = :payment_status,
                            payment_date = :payment_date,
                            payment_reference =
                                :payment_reference,
                            notes = :notes
                         WHERE id = :id'
                    );

                    $updateStatement->execute([
                        'customer_id' => $postedCustomerId,
                        'billing_month' => $billingMonthDate,
                        'amount' => number_format(
                            (float) $postedAmount,
                            2,
                            '.',
                            ''
                        ),
                        'payment_status' =>
                            $postedPaymentStatus,
                        'payment_date' =>
                            $postedPaymentDate !== ''
                                ? $postedPaymentDate
                                : null,
                        'payment_reference' =>
                            $postedPaymentReference !== ''
                                ? $postedPaymentReference
                                : null,
                        'notes' =>
                            $postedNotes !== ''
                                ? $postedNotes
                                : null,
                        'id' => $billingId
                    ]);

                    header(
                        'Location: billing.php?updated=1'
                    );
                    exit;
                } catch (PDOException $error) {
                    $errorMessage =
                        'The billing record could not be updated.';
                }
            }
        }
    }
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

function escapeOutput(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
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

    <title>Edit Billing | Mini ISP Portal</title>

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
            <h1>Edit billing record</h1>

            <p class="page-subtitle">
                Update the customer billing information.
            </p>
        </div>

        <div class="page-actions">
            <a
                class="button secondary-button"
                href="billing.php"
            >
                Back to billing
            </a>

            <a
                class="button secondary-button"
                href="dashboard.php"
            >
                Dashboard
            </a>
        </div>
    </div>

    <?php if ($errorMessage !== ''): ?>
        <p class="message error">
            <?= escapeOutput($errorMessage) ?>
        </p>
    <?php endif; ?>

    <section class="card">
        <form
            method="POST"
            action="edit_billing.php?id=<?= (int) $billingId ?>"
            class="billing-form"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapeOutput($csrfToken) ?>"
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
                                <?= $formCustomerId
                                    === (string) $customer['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= escapeOutput(
                                    (string) $customer[
                                        'full_name'
                                    ]
                                ) ?>
                                -
                                <?= escapeOutput(
                                    (string) $customer[
                                        'username'
                                    ]
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
                        value="<?= escapeOutput(
                            $formBillingMonth
                        ) ?>"
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
                        value="<?= escapeOutput(
                            $formAmount
                        ) ?>"
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
                        <?php foreach (
                            $allowedStatuses as $status
                        ): ?>
                            <option
                                value="<?= escapeOutput($status) ?>"
                                <?= $formPaymentStatus === $status
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= escapeOutput(
                                    ucfirst($status)
                                ) ?>
                            </option>
                        <?php endforeach; ?>
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
                        value="<?= escapeOutput(
                            $formPaymentDate
                        ) ?>"
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
                        value="<?= escapeOutput(
                            $formPaymentReference
                        ) ?>"
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
                    rows="4"
                ><?= escapeOutput($formNotes) ?></textarea>
            </div>

            <div class="page-actions">
                <button
                    class="button"
                    type="submit"
                >
                    Save changes
                </button>

                <a
                    class="button secondary-button"
                    href="billing.php"
                >
                    Cancel
                </a>
            </div>
        </form>
    </section>

</main>

<footer class="site-footer">
    Built by Sphesihle K
</footer>

</body>
</html>