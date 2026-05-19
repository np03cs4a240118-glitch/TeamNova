<?php
// patient/book_appointment.php — DABS-04 (3-step booking)
// Tasks T04-1 to T04-7
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requirePatient();

$pid  = (int)$_SESSION['patient_id'];
$step = (int)($_GET['step'] ?? 1);

// Get doctor
$doctor_id = (int)($_GET['doctor_id'] ?? $_SESSION['book_doctor_id'] ?? 0);
if (!$doctor_id) { header('Location: find_doctor.php'); exit; }

$dr = $conn->query("SELECT * FROM doctors WHERE id=$doctor_id AND status='approved' LIMIT 1")->fetch_assoc();
if (!$dr) { header('Location: find_doctor.php'); exit; }

$error = ''; $success = '';
// Payment error feedback from eSewa callback
$pay_error = $_GET['pay_error'] ?? '';
if ($pay_error === 'cancelled')     $error = ' Payment was cancelled. Your booking was not confirmed. Please try again.';
elseif ($pay_error === 'verify_failed') $error = ' eSewa payment could not be verified. Please try again or contact support.';
elseif ($pay_error)                 $error = ' Payment failed. Your booking was not confirmed. Please try again.';
// Prefill step 3 from session if returning after failed payment
if ($step === 3 && $pay_error && !empty($_SESSION['book_date'])) { /* keep session intact */ }

// STEP 1 — Select date & time
if ($step === 1 && $_SERVER['REQUEST_METHOD']==='POST') {
    $date = clean($conn, $_POST['date'] ?? '');
    $time = clean($conn, $_POST['time'] ?? '');
    if (!$date || !$time) { $error='Please select a date and time.'; }
    else {
        // T04-3: Check if slot already booked
        $check = $conn->query("SELECT id FROM appointments WHERE doctor_id=$doctor_id AND date='$date' AND time='$time' AND status!='cancelled' LIMIT 1");
        if ($check->num_rows>0) { $error='That slot is already booked. Please choose another.'; }
        else {
            $_SESSION['book_doctor_id'] = $doctor_id;
            $_SESSION['book_date'] = $date;
            $_SESSION['book_time'] = $time;
            header("Location: book_appointment.php?doctor_id=$doctor_id&step=2"); exit;
        }
    }
}

// STEP 2 — Patient details (pre-fill from profile)
if ($step === 2 && $_SERVER['REQUEST_METHOD']==='POST') {
    $reason = clean($conn, $_POST['reason'] ?? '');
    $_SESSION['book_reason'] = $reason;
    header("Location: book_appointment.php?doctor_id=$doctor_id&step=3"); exit;
}

// STEP 3 — Review: branch on chosen payment_method
if ($step === 3 && $_SERVER['REQUEST_METHOD']==='POST') {
    $date   = $_SESSION['book_date']   ?? '';
    $time   = $_SESSION['book_time']   ?? '';
    $reason = $_SESSION['book_reason'] ?? '';
    $method = $_POST['payment_method'] ?? '';

    if (!$date || !$time) { header("Location: book_appointment.php?doctor_id=$doctor_id&step=1"); exit; }
    if (!in_array($method, ['esewa', 'cash'], true)) {
        $error = 'Please choose a payment method.';
    }

    if (!$error && $method === 'esewa') {
        // eSewa flow: pending + unpaid; payment_method='esewa'.
        // Confirmation flips to status='confirmed', payment_status='paid' inside esewa_callback.php.
        $conn->query(
            "INSERT INTO appointments
                (patient_id, doctor_id, date, time, reason, status, payment_status, payment_method)
             VALUES
                ($pid, $doctor_id, '$date', '$time', '$reason', 'pending', 'unpaid', 'esewa')"
        );
        $appt_id = $conn->insert_id;
        $_SESSION['book_appt_id']  = $appt_id;
        $_SESSION['esewa_appt_id'] = $appt_id;
        header("Location: ../payment/esewa_initiate.php");
        exit;
    }

    if (!$error && $method === 'cash') {
        // Cash-on-arrival: confirm immediately, mark unpaid, payment_method='cash'.
        // Doctor will collect at the clinic and (in a follow-up feature) mark paid.
        $conn->query(
            "INSERT INTO appointments
                (patient_id, doctor_id, date, time, reason, status, payment_status, payment_method)
             VALUES
                ($pid, $doctor_id, '$date', '$time', '$reason', 'confirmed', 'unpaid', 'cash')"
        );
        $appt_id = $conn->insert_id;

        // Notify both sides, mirroring the esewa_callback notification pattern
        $pname = $conn->query("SELECT name FROM patients WHERE id=$pid LIMIT 1")->fetch_assoc()['name'];
        insertNotification($conn, $pid, 'patient',
            "✅ Booking confirmed! Dr. {$dr['name']} on " . fmtDate($date) . " at " . fmt12($time) . ". Pay NPR " . number_format($dr['fee']) . " in cash at the clinic.");
        insertNotification($conn, $doctor_id, 'doctor',
            "📅 New appointment from $pname on " . fmtDate($date) . " at " . fmt12($time) . " — payment: cash on arrival.");

        // Clear booking-session crumbs
        unset($_SESSION['book_date'], $_SESSION['book_time'],
              $_SESSION['book_reason'], $_SESSION['book_doctor_id'], $_SESSION['book_appt_id']);

        header("Location: view_appointment.php?id=$appt_id&confirmed=1");
        exit;
    }
}

// T04-2: Get booked slots for selected date
$booked_slots = [];
$sel_date = $_SESSION['book_date'] ?? date('Y-m-d');
$slots_res = $conn->query(
    "SELECT time FROM appointments WHERE doctor_id=$doctor_id AND date='$sel_date' AND status!='cancelled'"
);
while($s=$slots_res->fetch_assoc()) $booked_slots[] = $s['time'];

// Generate time slots from availability
function generateSlots($avail_json, $day_name) {
    $avail = json_decode($avail_json ?: '{}', true);
    $day   = strtolower($day_name);
    if (empty($avail[$day]) || !$avail[$day]['enabled']) return [];
    $start = $avail[$day]['start'] ?? '09:00';
    $end   = $avail[$day]['end']   ?? '17:00';
    $slots = [];
    $cur = strtotime($start);
    $fin = strtotime($end);
    while ($cur < $fin) {
        $slots[] = date('H:i:s', $cur);
        $cur += 30 * 60; // 30-min slots
    }
    return $slots;
}

$page_title = 'Book Appointment';
include '../includes/header.php';
$unread = countUnread($conn, $pid, 'patient');
?>
<style>
.step-line{flex:1;height:2px;background:var(--bg2);border-radius:2px;margin:0 10px}
.step-line.done{background:var(--t)}
.step-line.active{background:var(--b)}
.ustp{width:26px;height:26px;border-radius:50%;border:2.5px solid var(--bd2);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--tx3);flex-shrink:0}
.ustp.on{background:var(--b);border-color:var(--b);color:#fff}
.ustp.dn{background:var(--t);border-color:var(--t);color:#fff}
.day-tab{text-align:center;padding:9px 14px;border-radius:9px;border:1.5px solid var(--bd);cursor:pointer;transition:all .15s;flex:1}
.day-tab.active{background:var(--b);border-color:var(--b);color:#fff}
.day-tab.disabled{opacity:.4;cursor:not-allowed}
</style>
<div class="layout">
 <div class="sidebar">
  <div class="sb-profile"><div class="av av-blue" style="width:44px;height:44px;font-size:14px;margin:0 auto 8px"><?=getInitials($_SESSION['patient_name'])?></div><div style="font-size:13px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($_SESSION['patient_name'])?></div></div>
  <div class="sb-nav">
   <a class="nav-item" href="dashboard.php"> &nbsp;Dashboard</a>
    <a class="nav-item" href="find_doctor.php"> &nbsp;Find a Doctor</a>
   <a class="nav-item active" href="my_appointments.php"> &nbsp;My Appointments</a>
   <a class="nav-item" href="medical_history.php"> &nbsp;Medical Records</a>
   <a class="nav-item" href="my_profile.php"> &nbsp;My Profile</a>
   <a class="nav-item" href="notifications.php"> &nbsp;Notifications<?php if($unread>0):?><span class="nav-badge"><?=$unread?></span><?php endif?></a>
  </div>
  <div class="sb-bottom"><a class="nav-item" style="color:var(--tx3)" href="logout.php">↩ &nbsp;Log out</a></div>
 </div>
 <div class="main-content">
  <div class="topbar">
   <div class="flex items-center gap-3"><div class="logo-i">M</div><span class="logo-n">MediBook</span></div>
   <span style="font-size:13px;color:var(--tx3)">Step <?=$step?> of 3</span>
   <a href="find_doctor.php" class="btn-g btn-sm">Save &amp; exit</a>
  </div>

  <!-- Step indicator -->
  <div style="background:var(--w);border-bottom:1px solid var(--bd);padding:15px 32px">
   <div class="flex items-center" style="margin-bottom:10px;gap:10px">
    <div class="ustp <?=$step>=1?($step>1?'dn':'on'):''?>"><?=$step>1?'✓':'1'?></div>
    <span style="font-size:12px;font-weight:<?=$step===1?700:400?>;color:<?=$step===1?'var(--b)':'var(--tx3)'?>;<?=$step>1?'text-decoration:line-through':''?>">Select date &amp; time</span>
    <div class="step-line <?=$step>1?'done':''?>"></div>
    <div class="ustp <?=$step>=2?($step>2?'dn':'on'):''?>"><?=$step>2?'✓':'2'?></div>
    <span style="font-size:12px;font-weight:<?=$step===2?700:400?>;color:<?=$step===2?'var(--b)':'var(--tx3)'?>;<?=$step>2?'text-decoration:line-through':''?>">Patient details</span>
    <div class="step-line <?=$step>2?'active':''?>"></div>
    <div class="ustp <?=$step===3?'on':''?>">3</div>
    <span style="font-size:12px;font-weight:<?=$step===3?700:400?>;color:<?=$step===3?'var(--b)':'var(--tx3)'?>">Review &amp; confirm</span>
   </div>
   <div style="height:5px;background:var(--bg2);border-radius:3px;overflow:hidden"><div style="height:100%;background:linear-gradient(90deg,var(--b),var(--b2));width:<?=($step/3*100)?>%;border-radius:3px;transition:width .4s"></div></div>
  </div>

  <div class="page-body">
   <?php if($error): ?><div class="alert alert-error"><?=$error?></div><?php endif ?>

   <?php if($step===1): ?>
   <!-- STEP 1: T04-1 - Date & time -->
   <div style="display:grid;grid-template-columns:1fr 290px;gap:20px;align-items:start">
    <div>
     <!-- Doctor summary -->
     <div class="card" style="padding:18px;margin-bottom:16px;display:flex;gap:14px;align-items:center">
      <?= doctorAvatar($dr, 54, 'av-blue') ?>
      <div style="flex:1"><div style="font-size:15px;font-weight:800;color:var(--tx)"><?=htmlspecialchars($dr['name'])?></div><div style="font-size:12px;color:var(--tx3)"><?=htmlspecialchars($dr['specialisation'])?> · <?=htmlspecialchars($dr['qualification']??'')?></div><div style="margin-top:3px"><span class="stars">★★★★★</span> <span style="font-size:12px;color:var(--tx3)"><?=htmlspecialchars($dr['clinic_name']??'Hospital')?></span></div></div>
      <div style="text-align:right"><div style="font-size:11px;color:var(--tx3)">Consultation fee</div><div style="font-size:20px;font-weight:800;color:var(--tx)">NPR <?=number_format($dr['fee'])?></div></div>
     </div>

     <form method="POST" action="book_appointment.php?doctor_id=<?=$doctor_id?>&step=1">
      <!-- Date picker -->
      <div class="card" style="padding:18px;margin-bottom:16px">
       <div class="flex justify-between items-center" style="margin-bottom:14px">
        <div style="font-size:14px;font-weight:700;color:var(--tx)">Select date</div>
       </div>
       <div class="form-group">
        <input type="date" name="date" id="appt_date" class="form-control" min="<?=date('Y-m-d')?>" max="<?=date('Y-m-d',strtotime('+30 days'))?>" required value="<?=htmlspecialchars($sel_date)?>" style="font-size:16px;height:52px">
       </div>
      </div>

      <!-- Time slots -->
      <div class="card" style="padding:18px;margin-bottom:16px">
       <div class="flex justify-between items-center" style="margin-bottom:14px">
        <div style="font-size:14px;font-weight:700;color:var(--tx)">Select time</div>
        <span style="background:var(--t2);color:#065F46;border-radius:7px;padding:3px 12px;font-size:11px;font-weight:700">30 min slots</span>
       </div>
       <div class="slot-grid" id="slot-grid">
        <?php
        // Generate all slots for display
        $all_times = ['09:00:00','09:30:00','10:00:00','10:30:00','11:00:00','11:30:00',
                      '12:00:00','12:30:00','13:00:00','13:30:00','14:00:00','14:30:00',
                      '15:00:00','15:30:00','16:00:00','16:30:00','17:00:00'];
        foreach($all_times as $t):
          $is_booked = in_array($t, $booked_slots);
          $label = date('g:i A', strtotime($t));
        ?>
        <label style="cursor:<?=$is_booked?'not-allowed':'pointer'?>">
          <input type="radio" name="time" value="<?=$t?>" <?=$is_booked?'disabled':''?> style="display:none" required class="slot-radio">
          <span class="slot <?=$is_booked?'booked':''?>"><?=$label?></span>
        </label>
        <?php endforeach; ?>
       </div>
      </div>

      <div class="flex justify-between">
       <a href="find_doctor.php" class="btn-g">← Back</a>
       <button type="submit" class="btn-p" style="font-size:14px;padding:12px 36px">Continue → Step 2</button>
      </div>
     </form>
    </div>

    <!-- Summary card -->
    <div class="card" style="padding:18px;position:sticky;top:80px">
     <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--tx3);margin-bottom:14px">Booking summary</div>
     <div class="flex gap-3 items-center" style="margin-bottom:14px"><?= doctorAvatar($dr, 40, 'av-blue') ?><div><div style="font-size:13px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($dr['name'])?></div><div style="font-size:12px;color:var(--tx3)"><?=htmlspecialchars($dr['specialisation'])?></div></div></div>
     <div class="divl" style="margin-bottom:12px"></div>
     <div style="font-size:13px;color:var(--tx2);margin-bottom:5px"> Select date above</div>
     <div style="font-size:13px;color:var(--tx2);margin-bottom:14px"> Select a time slot</div>
     <div class="divl" style="margin-bottom:12px"></div>
     <div class="flex justify-between" style="margin-bottom:5px;font-size:13px"><span style="color:var(--tx3)">Consultation fee</span><span style="font-weight:700">NPR <?=number_format($dr['fee'])?></span></div>
     <div class="flex justify-between" style="font-size:12px"><span style="color:var(--tx3)">Platform fee</span><span style="color:var(--t);font-weight:600">Free</span></div>
    </div>
   </div>

   <?php elseif($step===2): ?>
   <!-- STEP 2: Patient details -->
   <div style="display:grid;grid-template-columns:1fr 290px;gap:20px;align-items:start">
    <div>
     <div class="card" style="padding:18px;margin-bottom:16px;border-left:4px solid var(--t)">
      <div style="font-size:12px;font-weight:700;color:var(--t);margin-bottom:6px">Step 1 complete</div>
      <div class="flex gap-4" style="font-size:13px;color:var(--tx2)"><span> <?=fmtDate($_SESSION['book_date']??'')?></span><span><?=fmt12($_SESSION['book_time']??'')?></span></div>
     </div>
     <form method="POST" action="book_appointment.php?doctor_id=<?=$doctor_id?>&step=2">
      <div class="card" style="padding:20px;margin-bottom:16px">
       <div style="font-size:14px;font-weight:700;color:var(--tx);margin-bottom:16px">Step 2 — Reason for visit</div>
       <?php $pat=$conn->query("SELECT * FROM patients WHERE id=$pid LIMIT 1")->fetch_assoc(); ?>
       <div class="grid-2" style="gap:12px;margin-bottom:12px">
        <div><label class="form-group" style="margin:0"><span style="font-size:11px;font-weight:700;color:var(--tx2);display:block;margin-bottom:5px">Full name</span><div class="form-control" style="background:var(--bg2);color:var(--tx)"><?=htmlspecialchars($pat['name'])?></div></label></div>
        <div><label class="form-group" style="margin:0"><span style="font-size:11px;font-weight:700;color:var(--tx2);display:block;margin-bottom:5px">Phone</span><div class="form-control" style="background:var(--bg2);color:var(--tx)"><?=htmlspecialchars($pat['phone']??'—')?></div></label></div>
       </div>
       <div class="form-group">
        <label>Reason for visit *</label>
        <textarea name="reason" class="form-control form-textarea" placeholder="Describe your symptoms or reason for this appointment..." required style="height:100px"><?=htmlspecialchars($_SESSION['book_reason']??'')?></textarea>
       </div>
      </div>
      <div class="flex justify-between">
       <a href="book_appointment.php?doctor_id=<?=$doctor_id?>&step=1" class="btn-g">← Back</a>
       <button type="submit" class="btn-p" style="font-size:14px;padding:12px 36px">Continue → Step 3</button>
      </div>
     </form>
    </div>
    <div class="card" style="padding:18px">
     <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--tx3);margin-bottom:14px">Booking summary</div>
     <div style="font-size:13px;color:var(--tx2);margin-bottom:5px"> <?=fmtDate($_SESSION['book_date']??'')?></div>
     <div style="font-size:13px;color:var(--tx2);margin-bottom:5px"> <?=fmt12($_SESSION['book_time']??'')?></div>
     <div style="font-size:13px;color:var(--tx2);margin-bottom:14px"> <?=htmlspecialchars($dr['clinic_name']??'Hospital')?></div>
     <div class="divl" style="margin-bottom:12px"></div>
     <div class="flex justify-between" style="font-size:14px"><span style="font-weight:700">Total</span><span style="font-weight:800;color:var(--b)">NPR <?=number_format($dr['fee'])?></span></div>
    </div>
   </div>

   <?php elseif($step===3): ?>
   <!-- STEP 3: Review & confirm -->
   <div style="display:grid;grid-template-columns:1fr 290px;gap:20px;align-items:start">
    <div>
     <div style="margin-bottom:16px"><div style="font-size:20px;font-weight:800;color:var(--tx);margin-bottom:4px">Review your booking</div><div style="font-size:13px;color:var(--tx3)">Please check all details before confirming.</div></div>

     <!-- Doctor -->
     <div class="card" style="padding:18px;margin-bottom:14px">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--tx3);margin-bottom:12px">Doctor</div>
      <div class="flex gap-3 items-center">
       <?= doctorAvatar($dr, 52, 'av-blue') ?>
       <div style="flex:1"><div style="font-size:15px;font-weight:800;color:var(--tx)"><?=htmlspecialchars($dr['name'])?></div><div style="font-size:12px;color:var(--tx3)"><?=htmlspecialchars($dr['specialisation'])?></div></div>
       <a href="book_appointment.php?doctor_id=<?=$doctor_id?>&step=1" style="font-size:12px;color:var(--b);font-weight:600;text-decoration:none">Change</a>
      </div>
     </div>

     <!-- Appointment details -->
     <div class="card" style="padding:18px;margin-bottom:14px">
      <div class="flex justify-between items-center" style="margin-bottom:12px"><div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--tx3)">Appointment details</div><a href="book_appointment.php?doctor_id=<?=$doctor_id?>&step=1" style="font-size:12px;color:var(--b);font-weight:600;text-decoration:none">Edit ✏</a></div>
      <div class="grid-2" style="gap:12px">
       <div style="padding:12px;background:var(--bg);border-radius:9px;border:1px solid var(--bd)"><div style="font-size:9px;color:var(--tx3);text-transform:uppercase;margin-bottom:5px">Date</div><div style="font-size:14px;font-weight:700;color:var(--tx)"><?=fmtDate($_SESSION['book_date']??'')?></div></div>
       <div style="padding:12px;background:var(--bg);border-radius:9px;border:1px solid var(--bd)"><div style="font-size:9px;color:var(--tx3);text-transform:uppercase;margin-bottom:5px">Time</div><div style="font-size:14px;font-weight:700;color:var(--tx)"><?=fmt12($_SESSION['book_time']??'')?></div></div>
       <div style="padding:12px;background:var(--bg);border-radius:9px;border:1px solid var(--bd)"><div style="font-size:9px;color:var(--tx3);text-transform:uppercase;margin-bottom:5px">Location</div><div style="font-size:14px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($dr['clinic_name']??'Hospital')?></div></div>
       <div style="padding:12px;background:var(--bg);border-radius:9px;border:1px solid var(--bd)"><div style="font-size:9px;color:var(--tx3);text-transform:uppercase;margin-bottom:5px">Duration</div><div style="font-size:14px;font-weight:700;color:var(--tx)">30 minutes</div></div>
      </div>
     </div>

     <!-- Patient -->
     <?php $pat=$conn->query("SELECT * FROM patients WHERE id=$pid LIMIT 1")->fetch_assoc(); ?>
     <div class="card" style="padding:18px;margin-bottom:14px">
      <div class="flex justify-between items-center" style="margin-bottom:12px"><div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--tx3)">Patient details</div><a href="book_appointment.php?doctor_id=<?=$doctor_id?>&step=2" style="font-size:12px;color:var(--b);font-weight:600;text-decoration:none">Edit ✏</a></div>
      <div class="grid-2" style="gap:11px;margin-bottom:12px">
       <div><div style="font-size:11px;color:var(--tx3);margin-bottom:3px">Name</div><div style="font-weight:700;color:var(--tx)"><?=htmlspecialchars($pat['name'])?></div></div>
       <div><div style="font-size:11px;color:var(--tx3);margin-bottom:3px">Phone</div><div style="font-weight:700;color:var(--tx)"><?=htmlspecialchars($pat['phone']??'—')?></div></div>
      </div>
      <div><div style="font-size:11px;color:var(--tx3);margin-bottom:5px">Reason for visit</div><div style="font-size:13px;color:var(--tx2);background:var(--bg);padding:11px 13px;border-radius:9px"><?=nl2br(htmlspecialchars($_SESSION['book_reason']??'Not specified'))?></div></div>
     </div>

     <!-- Payment method choice -->
     <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--tx3);margin:18px 0 10px">Choose how to pay</div>

     <!-- Option 1: Pay with eSewa -->
     <div class="card" style="padding:20px;margin-bottom:12px;border:2px solid #22c55e;background:linear-gradient(135deg,#f0fdf4,#dcfce7)">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
       <div style="background:#16a34a;border-radius:10px;width:38px;height:38px;display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;font-size:18px;flex-shrink:0">e</div>
       <div>
        <div style="font-size:13px;font-weight:800;color:#166534">Pay now with eSewa</div>
        <div style="font-size:11px;color:#15803d">Booking confirmed instantly after successful payment</div>
       </div>
       <div style="margin-left:auto;background:#166534;color:#fff;border-radius:6px;padding:3px 10px;font-size:10px;font-weight:700">SECURE</div>
      </div>
      <div style="background:#fff;border-radius:9px;padding:11px 14px;margin-bottom:14px;border:1px solid #bbf7d0;display:flex;justify-content:space-between;align-items:center">
       <span style="font-size:12px;color:#166534;font-weight:600">Amount to pay now</span>
       <span style="font-size:20px;font-weight:900;color:#15803d">NPR <?=number_format($dr['fee'])?></span>
      </div>
      <form method="POST" action="book_appointment.php?doctor_id=<?=$doctor_id?>&step=3">
       <input type="hidden" name="payment_method" value="esewa">
       <button type="submit" style="width:100%;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;border:none;border-radius:10px;padding:14px;font-size:14px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;box-shadow:0 4px 14px rgba(34,197,94,.35)">
        <span style="background:#fff;color:#15803d;border-radius:5px;padding:1px 8px;font-size:14px;font-weight:900">e</span>
        Pay NPR <?=number_format($dr['fee'])?> with eSewa &nbsp;&rarr;
       </button>
      </form>
     </div>

     <!-- Option 2: Pay cash at clinic (simple button) -->
     <form method="POST" action="book_appointment.php?doctor_id=<?=$doctor_id?>&step=3" style="margin-bottom:16px">
      <input type="hidden" name="payment_method" value="cash">
      <button type="submit" class="btn-g" style="width:100%;padding:13px;font-size:13px;font-weight:700">
       Pay cash at clinic
      </button>
     </form>

     <!-- Back link -->
     <div style="margin-bottom:16px"><a href="book_appointment.php?doctor_id=<?=$doctor_id?>&step=2" class="btn-g" style="padding:11px 18px">&larr; Back</a></div>
    </div>

    <div style="display:flex;flex-direction:column;gap:13px">
     <div class="card" style="padding:17px">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--tx3);margin-bottom:14px">Fee summary</div>
      <div class="flex justify-between" style="margin-bottom:8px;font-size:13px"><span style="color:var(--tx3)">Consultation fee</span><span style="font-weight:700">NPR <?=number_format($dr['fee'])?></span></div>
      <div class="flex justify-between" style="margin-bottom:8px;font-size:12px"><span style="color:var(--tx3)">Platform fee</span><span style="color:var(--t);font-weight:600">Free</span></div>
      <div class="divl" style="margin:12px 0"></div>
      <div class="flex justify-between"><span style="font-size:15px;font-weight:700">Total</span><span style="font-size:18px;font-weight:800;color:var(--b)">NPR <?=number_format($dr['fee'])?></span></div>
     </div>
     <div class="card" style="padding:15px">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--tx3);margin-bottom:12px">What happens next</div>
      <div style="display:flex;flex-direction:column;gap:10px">
       <div class="flex gap-3 items-start"><div class="av av-blue" style="width:22px;height:22px;font-size:11px;flex-shrink:0">1</div><div style="font-size:12px;color:var(--tx2)">Doctor reviews &amp; confirms</div></div>
       <div class="flex gap-3 items-start"><div class="av av-teal" style="width:22px;height:22px;font-size:11px;flex-shrink:0">2</div><div style="font-size:12px;color:var(--tx2)">SMS + email confirmation sent</div></div>
       <div class="flex gap-3 items-start"><div class="av" style="width:22px;height:22px;font-size:11px;flex-shrink:0;background:var(--am);color:#fff">3</div><div style="font-size:12px;color:var(--tx2)">Reminder 1 day before</div></div>
      </div>
     </div>
    </div>
   </div>
   <?php endif; ?>
  </div>
 </div>
</div>
<script>
// Highlight selected slot
document.querySelectorAll('.slot-radio').forEach(r => {
  r.addEventListener('change', function() {
    document.querySelectorAll('.slot').forEach(s => s.classList.remove('selected'));
    if(this.checked) this.nextElementSibling.classList.add('selected');
  });
});
</script>
</body></html>