<?php
// patient/payment_receipt.php
// Renders a printable payment receipt for a paid appointment.
// Browser "Print → Save as PDF" produces a clean A4 PDF; no server-side PDF lib needed.
// ============================================================
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requirePatient();

$pid = (int)$_SESSION['patient_id'];
$id = (int)($_GET['id'] ?? 0);

// Fetch appointment + doctor + patient in one go. patient_id check prevents
// a logged-in user from viewing someone else's receipt by guessing the id.
$sql = "SELECT
      a.id, a.date, a.time, a.created_at,
      a.payment_status, a.transaction_id,
      d.name AS dname, d.specialisation, d.qualification,
      d.clinic_name, d.clinic_address, d.clinic_phone, d.fee,
      p.name AS pname, p.email AS pemail, p.phone AS pphone
    FROM appointments a
    JOIN doctors d ON d.id = a.doctor_id
    JOIN patients p ON p.id = a.patient_id
    WHERE a.id = $id AND a.patient_id = $pid
    LIMIT 1";

$a = $conn->query($sql)->fetch_assoc();

// Refuse to render a receipt for a missing appointment or one that isn't paid.
if (!$a) { header('Location: my_appointments.php'); exit; }
if (($a['payment_status'] ?? 'unpaid') !== 'paid') {
  header('Location: view_appointment.php?id=' . $id);
  exit;
}

// Receipt number = MB-RCPT- + zero-padded appointment id.
$receipt_no = 'MB-RCPT-' . str_pad($a['id'], 5, '0', STR_PAD_LEFT);
$booking_no = 'MB-' . str_pad($a['id'], 4, '0', STR_PAD_LEFT);
$paid_on  = $a['created_at']; // we don't store paid_at separately; created_at is close enough
                 // for a same-session payment. See note in chat about adding paid_at.
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payment Receipt — <?= htmlspecialchars($receipt_no) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
 /* ─────────── Screen styles ─────────── */
 *{margin:0;padding:0;box-sizing:border-box}
 body{
  font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;
  background:#f1f5f9;color:#0f172a;padding:30px 20px;
  -webkit-font-smoothing:antialiased;
 }
 .actions{
  max-width:780px;margin:0 auto 18px;display:flex;justify-content:space-between;align-items:center;
 }
 .actions a, .actions button{
  background:#fff;border:1px solid #cbd5e1;color:#334155;font-family:inherit;
  padding:9px 16px;border-radius:8px;font-size:13px;font-weight:600;
  cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;
 }
 .actions .primary{
  background:#2563eb;border-color:#2563eb;color:#fff;
 }
 .actions .primary:hover{background:#1d4ed8}

 .receipt{
  max-width:780px;margin:0 auto;background:#fff;
  border-radius:14px;overflow:hidden;
  box-shadow:0 10px 40px rgba(15,23,42,.08);
 }

 .hdr{
  background:linear-gradient(135deg,#1e3a8a,#2563eb);color:#fff;
  padding:32px 40px;display:flex;justify-content:space-between;align-items:flex-start;
 }
 .hdr-brand{display:flex;align-items:center;gap:12px}
 .hdr-logo{
  width:46px;height:46px;background:#fff;border-radius:10px;
  display:flex;align-items:center;justify-content:center;
  font-weight:900;font-size:22px;color:#1e3a8a;
 }
 .hdr-name{font-size:22px;font-weight:800;letter-spacing:-.3px}
 .hdr-tag{font-size:11px;color:rgba(255,255,255,.75);margin-top:1px}
 .hdr-right{text-align:right}
 .hdr-right .lbl{font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.7)}
 .hdr-right .val{font-size:14px;font-weight:700;margin-top:2px}

 .paid-stamp{
  margin:0 40px;padding:14px 18px;
  background:linear-gradient(135deg,#dcfce7,#bbf7d0);
  border:1.5px solid #22c55e;border-radius:10px;
  display:flex;align-items:center;gap:14px;
  margin-top:-22px;position:relative;z-index:2;
 }
 .paid-stamp .check{
  width:34px;height:34px;background:#16a34a;color:#fff;
  border-radius:50%;display:flex;align-items:center;justify-content:center;
  font-weight:900;font-size:18px;flex-shrink:0;
 }
 .paid-stamp .ttl{font-size:14px;font-weight:800;color:#166534}
 .paid-stamp .sub{font-size:12px;color:#15803d}

 .body{padding:30px 40px}

 .meta-grid{
  display:grid;grid-template-columns:1fr 1fr;gap:24px;
  padding:18px 0;border-bottom:1px dashed #e2e8f0;margin-bottom:22px;
 }
 .meta-block .lbl{
  font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
  color:#64748b;margin-bottom:6px;
 }
 .meta-block .val{font-size:13px;font-weight:600;color:#0f172a;line-height:1.55}
 .meta-block .val span.muted{color:#64748b;font-weight:500}

 .section-ttl{
  font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
  color:#64748b;margin-bottom:10px;
 }

 .table{
  width:100%;border-collapse:collapse;margin-bottom:24px;
  font-size:13px;
 }
 .table thead th{
  text-align:left;font-weight:700;font-size:11px;text-transform:uppercase;
  letter-spacing:.05em;color:#64748b;background:#f8fafc;
  padding:11px 14px;border-bottom:1.5px solid #e2e8f0;
 }
 .table thead th.right{text-align:right}
 .table tbody td{
  padding:14px;border-bottom:1px solid #f1f5f9;color:#0f172a;
  vertical-align:top;
 }
 .table tbody td.right{text-align:right;font-weight:600}
 .table .desc-main{font-weight:700;margin-bottom:3px}
 .table .desc-sub{font-size:12px;color:#64748b;line-height:1.5}

 .totals{margin-left:auto;width:300px}
 .totals .row{
  display:flex;justify-content:space-between;padding:7px 0;font-size:13px;color:#475569;
 }
 .totals .row.grand{
  border-top:2px solid #0f172a;margin-top:8px;padding-top:13px;
  font-size:16px;font-weight:800;color:#0f172a;
 }
 .totals .row.grand .amt{color:#1e3a8a}

 .pay-info{
  background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;
  padding:16px 18px;margin-top:22px;
  display:grid;grid-template-columns:1fr 1fr;gap:16px;
 }
 .pay-info .lbl{font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:700;margin-bottom:4px}
 .pay-info .val{font-size:13px;font-weight:700;color:#0f172a}
 .pay-info .val.mono{font-family:ui-monospace,'SF Mono',Menlo,Consolas,monospace;font-size:12px;word-break:break-all}
 .pay-info .esewa{display:inline-flex;align-items:center;gap:6px;color:#16a34a}
 .pay-info .esewa .e{
  width:22px;height:22px;background:#60BB46;color:#fff;border-radius:5px;
  display:inline-flex;align-items:center;justify-content:center;font-weight:900;font-size:13px;
 }

 .ftr{
  background:#f8fafc;padding:22px 40px;text-align:center;
  border-top:1px solid #e2e8f0;
 }
 .ftr .thanks{font-size:13px;font-weight:700;color:#0f172a;margin-bottom:6px}
 .ftr .note{font-size:11px;color:#64748b;line-height:1.65;max-width:520px;margin:0 auto}
 .ftr .contact{font-size:11px;color:#64748b;margin-top:10px}
 .ftr .contact a{color:#2563eb;text-decoration:none}

 /* ─────────── Print styles ─────────── */
 @media print {
  @page { size: A4; margin: 12mm; }
  body { background: #fff; padding: 0; }
  .actions, .no-print { display: none !important; }
  .receipt { box-shadow: none; border-radius: 0; max-width: 100%; }
  .hdr { background: #1e3a8a !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .paid-stamp, .table thead th, .pay-info, .ftr {
   -webkit-print-color-adjust: exact; print-color-adjust: exact;
  }
 }
</style>
</head>
<body>

 <!-- Action bar (hidden in print) -->
 <div class="actions no-print">
  <a href="view_appointment.php?id=<?= $id ?>">← Back to appointment</a>
  <button type="button" class="primary" onclick="window.print()">
    &nbsp;Print / Save as PDF
  </button>
 </div>

 <div class="receipt">
  <!-- Header -->
  <div class="hdr">
   <div class="hdr-brand">
    <div class="hdr-logo">M</div>
    <div>
     <div class="hdr-name">MediBook</div>
     <div class="hdr-tag">Doctor Appointment &amp; Booking System</div>
    </div>
   </div>
   <div class="hdr-right">
    <div class="lbl">Receipt No.</div>
    <div class="val"><?= htmlspecialchars($receipt_no) ?></div>
    <div class="lbl" style="margin-top:10px">Issued</div>
    <div class="val"><?= date('d M Y, g:i A', strtotime($paid_on)) ?></div>
   </div>
  </div>

  <!-- Paid stamp -->
  <div class="paid-stamp">
   <div class="check">✓</div>
   <div>
    <div class="ttl">Payment Received</div>
    <div class="sub">Your appointment is confirmed and this serves as your official receipt.</div>
   </div>
  </div>

  <!-- Body -->
  <div class="body">

   <!-- Patient + Doctor meta -->
   <div class="meta-grid">
    <div class="meta-block">
     <div class="lbl">Billed To</div>
     <div class="val">
      <?= htmlspecialchars($a['pname']) ?><br>
      <span class="muted"><?= htmlspecialchars($a['pemail']) ?></span><br>
      <?php if (!empty($a['pphone'])): ?>
       <span class="muted"><?= htmlspecialchars($a['pphone']) ?></span>
      <?php endif; ?>
     </div>
    </div>
    <div class="meta-block">
     <div class="lbl">Service Provider</div>
     <div class="val">
      <?= htmlspecialchars($a['dname']) ?><br>
      <span class="muted"><?= htmlspecialchars($a['specialisation']) ?>
       <?= $a['qualification'] ? ' · ' . htmlspecialchars($a['qualification']) : '' ?>
      </span><br>
      <?php if ($a['clinic_name']): ?>
       <span class="muted"><?= htmlspecialchars($a['clinic_name']) ?></span><br>
      <?php endif; ?>
      <?php if ($a['clinic_address']): ?>
       <span class="muted"><?= htmlspecialchars($a['clinic_address']) ?></span>
      <?php endif; ?>
     </div>
    </div>
   </div>

   <!-- Itemised charges -->
   <div class="section-ttl">Service Details</div>
   <table class="table">
    <thead>
     <tr>
      <th style="width:55%">Description</th>
      <th>Date &amp; Time</th>
      <th class="right">Amount</th>
     </tr>
    </thead>
    <tbody>
     <tr>
      <td>
       <div class="desc-main">Consultation — <?= htmlspecialchars($a['specialisation']) ?></div>
       <div class="desc-sub">
        With <?= htmlspecialchars($a['dname']) ?><br>
        Booking ID: <strong><?= $booking_no ?></strong>
       </div>
      </td>
      <td>
       <?= fmtDate($a['date']) ?><br>
       <span style="color:#64748b;font-size:12px"><?= fmt12($a['time']) ?></span>
      </td>
      <td class="right">NPR <?= number_format($a['fee'], 2) ?></td>
     </tr>
    </tbody>
   </table>

   <!-- Totals -->
   <div class="totals">
    <div class="row"><span>Subtotal</span><span>NPR <?= number_format($a['fee'], 2) ?></span></div>
    <div class="row"><span>Platform fee</span><span>Free</span></div>
    <div class="row"><span>Tax</span><span>NPR 0.00</span></div>
    <div class="row grand"><span>Total Paid</span><span class="amt">NPR <?= number_format($a['fee'], 2) ?></span></div>
   </div>

   <!-- Payment info -->
   <div class="pay-info">
    <div>
     <div class="lbl">Payment Method</div>
     <div class="val esewa"><span class="e">e</span>eSewa Wallet</div>
    </div>
    <div>
     <div class="lbl">Payment Status</div>
     <div class="val" style="color:#16a34a">PAID</div>
    </div>
    <div style="grid-column:1 / -1">
     <div class="lbl">eSewa Transaction Reference</div>
     <div class="val mono"><?= htmlspecialchars($a['transaction_id'] ?: '—') ?></div>
    </div>
   </div>

  </div>

  <!-- Footer -->
  <div class="ftr">
   <div class="thanks">Thank you for choosing MediBook 🩺</div>
   <div class="contact">
    support@medibook.np &nbsp;·&nbsp; +977 1 4000000 &nbsp;·&nbsp; medibook.np
   </div>
  </div>
 </div>

</body>
</html>