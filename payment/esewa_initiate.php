<?php
// payment/esewa_initiate.php — eSewa ePay v2
// Generates HMAC-SHA256 signature and submits payment form to eSewa
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requirePatient();

$pid     = (int)$_SESSION['patient_id'];
// Read appointment_id from session — set by book_appointment.php Step 3 handler
$appt_id = (int)($_SESSION['esewa_appt_id'] ?? $_POST['appointment_id'] ?? 0);

if (!$appt_id) { header('Location: ../patient/my_appointments.php'); exit; }

// Fetch appointment & verify it belongs to this patient
$a = $conn->query(
    "SELECT a.*, d.fee FROM appointments a
     JOIN doctors d ON d.id = a.doctor_id
     WHERE a.id = $appt_id AND a.patient_id = $pid LIMIT 1"
)->fetch_assoc();

if (!$a) { header('Location: ../patient/my_appointments.php'); exit; }

// ── eSewa v2 Credentials ───────────────────────────────────────────────────
define('ESEWA_PRODUCT_CODE',  'EPAYTEST');
define('ESEWA_SECRET_KEY',    '8gBm/:&EnhH.1/q');

// NOTE: eSewa sandbox rejects localhost callback URLs.
// Use the local mock for development/demo. Switch to the real URL when deployed to a live server.
//define('ESEWA_PAYMENT_URL', 'http://localhost/medibook/payment/mock_esewa.php');
 define('ESEWA_PAYMENT_URL', 'https://rc-epay.esewa.com.np/api/epay/main/v2/form'); // ← real sandbox (needs live HTTPS URL)
 define('ESEWA_PAYMENT_URL', 'https://epay.esewa.com.np/api/epay/main/v2/form');    // ← production

// ── Build parameters ───────────────────────────────────────────────────────
$total_amount = number_format((float)$a['fee'], 2, '.', '');
// Unique transaction UUID: date-time + appointment id (alphanumeric & hyphen only)
$transaction_uuid = date('Ymd-His') . '-' . $appt_id;

// Store uuid in session so callback can use it for status check
$_SESSION['esewa_txn_uuid'] = $transaction_uuid;

$success_url = 'http://localhost/medibook/payment/esewa_callback.php';
$failure_url = 'http://localhost/medibook/payment/esewa_callback.php?status=failed&appt_id=' . $appt_id;

// ── HMAC-SHA256 Signature ─────────────────────────────────────────────────
// Message format: total_amount=X,transaction_uuid=Y,product_code=Z
$message   = "total_amount={$total_amount},transaction_uuid={$transaction_uuid},product_code=" . ESEWA_PRODUCT_CODE;
$signature = base64_encode(hash_hmac('sha256', $message, ESEWA_SECRET_KEY, true));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Redirecting to eSewa…</title>
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Inter',sans-serif;background:#0f172a;display:flex;align-items:center;justify-content:center;min-height:100vh;flex-direction:column;gap:20px}
    .spinner{width:52px;height:52px;border:4px solid #334155;border-top-color:#60BB46;border-radius:50%;animation:spin .8s linear infinite}
    @keyframes spin{to{transform:rotate(360deg)}}
    p{color:#94a3b8;font-size:15px}
    strong{color:#60BB46}
  </style>
</head>
<body>
  <div class="spinner"></div>
  <p>Redirecting you to <strong>eSewa</strong>…</p>

  <!-- eSewa v2 payment form (auto-submits) -->
  <form id="esewaForm" method="POST" action="<?= ESEWA_PAYMENT_URL ?>">
    <input type="hidden" name="amount"                   value="<?= $total_amount ?>">
    <input type="hidden" name="tax_amount"               value="0">
    <input type="hidden" name="total_amount"             value="<?= $total_amount ?>">
    <input type="hidden" name="transaction_uuid"         value="<?= htmlspecialchars($transaction_uuid) ?>">
    <input type="hidden" name="product_code"             value="<?= ESEWA_PRODUCT_CODE ?>">
    <input type="hidden" name="product_service_charge"   value="0">
    <input type="hidden" name="product_delivery_charge"  value="0">
    <input type="hidden" name="success_url"              value="<?= htmlspecialchars($success_url) ?>">
    <input type="hidden" name="failure_url"              value="<?= htmlspecialchars($failure_url) ?>">
    <input type="hidden" name="signed_field_names"       value="total_amount,transaction_uuid,product_code">
    <input type="hidden" name="signature"                value="<?= htmlspecialchars($signature) ?>">
  </form>
  <script>document.getElementById('esewaForm').submit();</script>
</body>
</html>
