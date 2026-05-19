<?php
// payment/esewa_callback.php — eSewa ePay v2
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requirePatient();

$pid = (int)$_SESSION['patient_id'];

// ── Write debug log so we can see exactly what eSewa sends ─────────────────
$log = date('Y-m-d H:i:s') . " | GET: " . json_encode($_GET) . "\n";
file_put_contents(__DIR__ . '/esewa_debug.log', $log, FILE_APPEND);

// ── Detect success vs failure by presence of 'data' param ─────────────────
// eSewa v2 appends ?data=BASE64JSON to success_url on success.
// On failure it just redirects to failure_url with no data param.
if (!isset($_GET['data'])) {
  // FAILURE / CANCELLATION
  $appt_id  = (int)($_GET['appt_id'] ?? $_SESSION['esewa_appt_id'] ?? 0);
  $doctor_id = 0;
  if ($appt_id) {
    $row = $conn->query("SELECT doctor_id FROM appointments WHERE id=$appt_id AND patient_id=$pid LIMIT 1")->fetch_assoc();
    $doctor_id = (int)($row['doctor_id'] ?? 0);
    $conn->query("DELETE FROM appointments WHERE id=$appt_id AND patient_id=$pid AND payment_status='unpaid'");
  }
  unset($_SESSION['esewa_appt_id'], $_SESSION['esewa_txn_uuid']);
  $loc = $doctor_id
    ? "http://localhost/medibook/patient/book_appointment.php?doctor_id={$doctor_id}&step=3&pay_error=cancelled"
    : "http://localhost/medibook/patient/my_appointments.php";
  header("Location: $loc"); exit;
}

// ── SUCCESS path ───────────────────────────────────────────────────────────
$decoded = base64_decode($_GET['data']);
$response = json_decode($decoded, true);

// Log decoded response
file_put_contents(__DIR__ . '/esewa_debug.log', date('Y-m-d H:i:s') . " | DECODED: " . $decoded . "\n", FILE_APPEND);

if (!$response) {
  header('Location: http://localhost/medibook/patient/my_appointments.php?pay_error=decode_failed'); exit;
}

$txn_status   = $response['status']      ?? '';
$transaction_uuid = $response['transaction_uuid'] ?? ($_SESSION['esewa_txn_uuid'] ?? '');
$ref_id     = $response['transaction_code'] ?? '';
$resp_signature = $response['signature']     ?? '';

// ── Verify HMAC signature from eSewa response ──────────────────────────────
$secret = '8gBm/:&EnhH.1/q';

$signed_fields = $response['signed_field_names'] ?? 'transaction_code,status,total_amount,transaction_uuid,product_code,signed_field_names';
$field_arr = explode(',', $signed_fields);
$msg_parts = [];
foreach ($field_arr as $f) {
  $f = trim($f);
  $msg_parts[] = "{$f}=" . ($response[$f] ?? '');
}
$message   = implode(',', $msg_parts);
$expected_sig = base64_encode(hash_hmac('sha256', $message, $secret, true));
$sig_valid  = hash_equals($expected_sig, $resp_signature);

// Log verification result
file_put_contents(__DIR__ . '/esewa_debug.log',
  date('Y-m-d H:i:s') . " | status=$txn_status | sig_valid=" . ($sig_valid?'YES':'NO') . " | ref=$ref_id\n", FILE_APPEND);

// ── Get appointment id ─────────────────────────────────────────────────────
$appt_id = (int)($_SESSION['esewa_appt_id'] ?? 0);
if (!$appt_id && $transaction_uuid) {
  $parts  = explode('-', $transaction_uuid);
  $appt_id = (int)end($parts);
}

if (!$appt_id) {
  header('Location: http://localhost/medibook/patient/my_appointments.php?pay_error=missing_id'); exit;
}

// Fetch appointment
$a = $conn->query(
  "SELECT a.*, d.fee, d.name AS dname FROM appointments a
   JOIN doctors d ON d.id = a.doctor_id
   WHERE a.id = $appt_id AND a.patient_id = $pid LIMIT 1"
)->fetch_assoc();

if (!$a) {
  header("Location: http://localhost/medibook/patient/view_appointment.php?id=$appt_id"); exit;
}

$doctor_id = (int)$a['doctor_id'];

// ── Also verify via eSewa Status Check API ─────────────────────────────────
$fee    = number_format((float)$a['fee'], 2, '.', '');
$status_url = 'https://rc.esewa.com.np/api/epay/transaction/status/?' . http_build_query([
  'product_code'   => 'EPAYTEST',
  'total_amount'   => $fee,
  'transaction_uuid' => $transaction_uuid,
]);
$ctx    = stream_context_create(['http' => ['timeout' => 10]]);
$status_raw = @file_get_contents($status_url, false, $ctx);
$status_data = $status_raw ? json_decode($status_raw, true) : null;
$api_complete = ($status_data && ($status_data['status'] ?? '') === 'COMPLETE');

file_put_contents(__DIR__ . '/esewa_debug.log',
  date('Y-m-d H:i:s') . " | API check: " . ($status_raw ?: 'UNREACHABLE') . "\n", FILE_APPEND);

// Accept if: eSewa response says COMPLETE + signature valid, OR API confirms COMPLETE
$verified = ($txn_status === 'COMPLETE' && $sig_valid) || $api_complete;

if ($api_complete && !empty($status_data['ref_id'])) {
  $ref_id = $status_data['ref_id'];
}

if ($verified) {
  $safe_ref = $conn->real_escape_string($ref_id);
  $conn->query(
    "UPDATE appointments SET status='confirmed', payment_status='paid', transaction_id='$safe_ref'
     WHERE id=$appt_id AND patient_id=$pid"
  );

  $pname = $conn->query("SELECT name FROM patients WHERE id=$pid LIMIT 1")->fetch_assoc()['name'];
  insertNotification($conn, $pid, 'patient',
    " Booking confirmed! Dr. {$a['dname']} on " . fmtDate($a['date']) . " at " . fmt12($a['time']) . ". eSewa Ref: $ref_id.");
  insertNotification($conn, $doctor_id, 'doctor',
    " New paid appointment from $pname on " . fmtDate($a['date']) . " at " . fmt12($a['time']) . ".");

  unset($_SESSION['esewa_appt_id'], $_SESSION['esewa_txn_uuid'],
     $_SESSION['book_date'], $_SESSION['book_time'],
     $_SESSION['book_reason'], $_SESSION['book_doctor_id'], $_SESSION['book_appt_id']);

  header("Location: http://localhost/medibook/patient/view_appointment.php?id=$appt_id&confirmed=1&paid=1");
  exit;

} else {
  // Signature mismatch / not COMPLETE — delete pending slot
  $conn->query("DELETE FROM appointments WHERE id=$appt_id AND patient_id=$pid AND payment_status='unpaid'");
  unset($_SESSION['esewa_appt_id'], $_SESSION['esewa_txn_uuid']);
  header("Location: http://localhost/medibook/patient/book_appointment.php?doctor_id={$doctor_id}&step=3&pay_error=verify_failed");
  exit;
}
