<?php
// patient/view_appointment.php — DABS-05
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requirePatient();
$pid  = (int)$_SESSION['patient_id'];
$id   = (int)($_GET['id'] ?? 0);
$confirmed = !empty($_GET['confirmed']);
$paid      = !empty($_GET['paid']);

$a = $conn->query("SELECT a.*,d.name dname,d.profile_image dimage,d.specialisation,d.qualification,d.clinic_name,d.clinic_address,d.fee FROM appointments a JOIN doctors d ON d.id=a.doctor_id WHERE a.id=$id AND a.patient_id=$pid LIMIT 1")->fetch_assoc();
if (!$a) { header('Location: my_appointments.php'); exit; }

// DABSTN-176: aggregate rating for this doctor + look up this appointment's review (if any).
$rating_row = $conn->query(
    "SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS rating_count
       FROM reviews WHERE doctor_id={$a['doctor_id']}"
)->fetch_assoc();
$avg_rating   = $rating_row['avg_rating']   ?? null;     // null if no reviews yet
$rating_count = (int)($rating_row['rating_count'] ?? 0);

$my_review = $conn->query(
    "SELECT rating, comment, created_at FROM reviews WHERE appointment_id=$id LIMIT 1"
)->fetch_assoc();
$review_thanks = !empty($_GET['review_thanks']);

$page_title = 'Appointment Detail';
include '../includes/header.php';
$unread = countUnread($conn, $pid, 'patient');
?>
<div class="layout">
 <div class="sidebar">
  <div class="sb-profile"><div class="av av-blue" style="width:44px;height:44px;font-size:14px;margin:0 auto 8px"><?=getInitials($_SESSION['patient_name'])?></div><div style="font-size:13px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($_SESSION['patient_name'])?></div></div>
  <div class="sb-nav">
   <a class="nav-item" href="dashboard.php"> &nbsp;Dashboard</a>
   <a class="nav-item active" href="my_appointments.php"> &nbsp;My Appointments</a>
   <a class="nav-item" href="find_doctor.php"> &nbsp;Find a Doctor</a>
   <a class="nav-item" href="medical_history.php"> &nbsp;Medical Records</a>
   <a class="nav-item" href="my_profile.php"> &nbsp;My Profile</a>
   <a class="nav-item" href="settings.php"> &nbsp;Settings</a>
   <a class="nav-item" href="notifications.php"> &nbsp;Notifications<?php if($unread>0):?><span class="nav-badge"><?=$unread?></span><?php endif?></a>
  </div>
  <div class="sb-bottom"><a class="nav-item" style="color:var(--tx3)" href="logout.php">↩ &nbsp;Log out</a></div>
 </div>
 <div class="main-content">
  <div class="topbar">
   <div class="flex items-center gap-3"><div class="logo-i">M</div><span class="logo-n">MediBook</span></div>
   <a href="my_appointments.php" style="font-size:13px;color:var(--b);font-weight:500;text-decoration:none">← Back to My Appointments</a>
   <div class="flex gap-2">
    <?php if(in_array($a['status'],['confirmed','pending']) && $a['date']>=date('Y-m-d')): ?>
     <a href="cancel_appointment.php?id=<?=$id?>" class="btn-r-out btn-sm">Cancel appointment</a>
    <?php endif; ?>
   </div>
  </div>
  <div class="page-body">

   <?php if($confirmed && $paid): ?>
    <div style="background:linear-gradient(135deg,#dcfce7,#bbf7d0);border:1.5px solid #22c55e;border-radius:12px;padding:14px 20px;display:flex;align-items:center;gap:14px;margin-bottom:18px">
     <div style="background:#16a34a;border-radius:8px;width:34px;height:34px;display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;font-size:16px;flex-shrink:0">e</div>
     <div><div style="font-size:14px;font-weight:800;color:#166534">Payment successful via eSewa!</div><div style="font-size:12px;color:#15803d;margin-top:2px">Your appointment is confirmed. A notification has been sent to you and your doctor.</div></div>
    </div>
   <?php elseif($confirmed): ?>
    <div class="alert alert-success" style="font-size:14px;margin-bottom:22px">Booking confirmed! Your appointment has been sent to <?=htmlspecialchars($a['dname'])?> for confirmation.</div>
   <?php endif; ?>

   <!-- Status banner -->
   <?php if($a['status']==='confirmed'): ?>
   <div style="background:linear-gradient(135deg,#D1FAE5,#A7F3D0);border:1px solid var(--t3);border-radius:14px;padding:16px 22px;display:flex;align-items:center;justify-content:space-between;margin-bottom:22px">
    <div class="flex items-center gap-3"><div style="width:42px;height:42px;border-radius:50%;background:var(--t);display:flex;align-items:center;justify-content:center;font-size:20px">✓</div><div><div style="font-size:15px;font-weight:800;color:#065F46">Appointment Confirmed</div><div style="font-size:12px;color:var(--t);margin-top:2px">Booking ID: <strong>#MB-<?=str_pad($a['id'],4,'0',STR_PAD_LEFT)?></strong></div></div></div>
    <?php if($a['date']>=date('Y-m-d')): ?><a href="cancel_appointment.php?id=<?=$id?>" class="btn-r-out btn-sm">Cancel</a><?php endif; ?>
   </div>
   <?php elseif($a['status']==='cancelled'): ?>
   <div class="alert alert-error" style="margin-bottom:22px;font-size:14px">✕ This appointment was cancelled.</div>
   <?php endif; ?>

   <div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start">
    <div>
     <!-- Doctor card -->
     <div class="card" style="padding:20px;margin-bottom:16px">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--tx3);margin-bottom:14px">Doctor</div>
      <div class="flex gap-4 items-center">
       <?= doctorAvatar(['name'=>$a['dname'],'profile_image'=>$a['dimage']??null], 64, 'av-blue') ?>
       <div style="flex:1"><div style="font-size:17px;font-weight:800;color:var(--tx);margin-bottom:4px"><?=htmlspecialchars($a['dname'])?></div><div style="font-size:13px;color:var(--tx3)"><?=htmlspecialchars($a['specialisation'])?> · <?=htmlspecialchars($a['qualification']??'')?></div><div style="margin-top:4px"><?php if ($avg_rating !== null): ?><span style="color:#f59e0b;font-weight:700">★ <?= $avg_rating ?></span> <span style="font-size:12px;color:var(--tx3)">(<?= $rating_count ?> review<?= $rating_count==1?'':'s' ?>) · <?=htmlspecialchars($a['clinic_name']??'')?></span><?php else: ?><span style="font-size:12px;color:var(--tx3)">No reviews yet · <?=htmlspecialchars($a['clinic_name']??'')?></span><?php endif; ?></div></div>
      </div>
     </div>

     <!-- Appointment details -->
     <div class="card" style="padding:20px;margin-bottom:16px">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--tx3);margin-bottom:14px">Appointment details</div>
      <div class="grid-2" style="gap:13px">
       <div style="padding:14px;background:var(--bg);border-radius:10px;border:1px solid var(--bd)"><div style="font-size:9px;color:var(--tx3);text-transform:uppercase;margin-bottom:5px">Date</div><div style="font-size:15px;font-weight:800;color:var(--tx)"><?=fmtDate($a['date'])?></div></div>
       <div style="padding:14px;background:var(--bg);border-radius:10px;border:1px solid var(--bd)"><div style="font-size:9px;color:var(--tx3);text-transform:uppercase;margin-bottom:5px">Time</div><div style="font-size:15px;font-weight:800;color:var(--tx)"><?=fmt12($a['time'])?></div><div style="font-size:12px;color:var(--tx3)">30 min slot</div></div>
       <div style="padding:14px;background:var(--bg);border-radius:10px;border:1px solid var(--bd)"><div style="font-size:9px;color:var(--tx3);text-transform:uppercase;margin-bottom:5px">Location</div><div style="font-size:14px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($a['clinic_name']??'Hospital')?></div><?php if($a['clinic_address']): ?><div style="font-size:12px;color:var(--tx3)"><?=htmlspecialchars($a['clinic_address'])?></div><?php endif; ?></div>
       <div style="padding:14px;background:var(--bg);border-radius:10px;border:1px solid var(--bd)"><div style="font-size:9px;color:var(--tx3);text-transform:uppercase;margin-bottom:5px">Status</div><?=statusBadge($a['status'])?></div>
      </div>
     </div>

     <!-- Reason -->
     <?php if($a['reason']): ?>
     <div class="card" style="padding:20px;margin-bottom:16px">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--tx3);margin-bottom:10px">Reason for visit</div>
      <p style="font-size:13px;color:var(--tx2);line-height:1.65"><?=nl2br(htmlspecialchars($a['reason']))?></p>
     </div>
     <?php endif; ?>
     
       <?php
$cn_check = $conn->query("SELECT id FROM consultation_notes WHERE appointment_id={$a['id']} LIMIT 1");
if ($cn_check && $cn_check->num_rows > 0):
?>
 <div class="card" style="padding:20px;margin-bottom:16px;border:1.5px solid var(--t2);background:#f0fdfa">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:14px">
   <div>
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--t);margin-bottom:6px">Consultation Note</div>
    <div style="font-size:14px;font-weight:700;color:var(--tx)">Your doctor has completed a consultation note for this visit.</div>
    <div style="font-size:12px;color:var(--tx3);margin-top:2px">View the structured note or download it as a PDF.</div>
   </div>
   <a href="consultation_note.php?aid=<?=$a['id']?>" class="btn-t btn-sm" style="white-space:nowrap">View &amp; Download →</a>
  </div>
 </div>
<?php endif; ?>
     <!-- Doctor Report -->
     <?php if(!empty($a['doctor_report']) || !empty($a['doctor_file'])): ?>
     <div class="card" style="padding:20px;margin-bottom:16px;border:1.5px solid var(--t2);background:#f0xfdf">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--t);margin-bottom:10px;display:flex;align-items:center;gap:6px">
       <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
       Doctor's Report / Prescription
      </div>
      <?php if(!empty($a['doctor_report'])): ?>
      <p style="font-size:13px;color:var(--tx);line-height:1.65;white-space:pre-wrap;margin-bottom:12px"><?=htmlspecialchars($a['doctor_report'])?></p>
      <?php endif; ?>
      <?php if(!empty($a['doctor_file'])): ?>
       <a href="api_reports.php?action=download_doctor_file&file=<?=htmlspecialchars($a['doctor_file'])?>" target="_blank" class="btn-g btn-xs" style="display:inline-flex;align-items:center;gap:6px;background:var(--w)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Download Attached File</a>
      <?php endif; ?>
     </div>
     <?php endif; ?>

     <!-- DABSTN-176: review CTA / submitted review display -->
     <?php if ($a['status'] === 'completed'): ?>
       <?php if ($review_thanks && $my_review): ?>
        <div class="alert alert-success" style="margin-bottom:14px;font-size:13px">✅ Thanks for your review — it's been posted.</div>
       <?php endif; ?>

       <?php if ($my_review): ?>
        <div class="card" style="padding:18px;margin-bottom:16px;background:#fffbeb;border:1px solid #fde68a">
         <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#92400e;margin-bottom:8px">Your review</div>
         <div style="color:#f59e0b;font-size:18px;font-weight:800;letter-spacing:2px;margin-bottom:8px">
          <?= str_repeat('★', (int)$my_review['rating']) . str_repeat('☆', 5 - (int)$my_review['rating']) ?>
          <span style="font-size:13px;color:#92400e;margin-left:6px;letter-spacing:0;font-weight:700"><?= (int)$my_review['rating'] ?>/5</span>
         </div>
         <?php if (!empty($my_review['comment'])): ?>
          <div style="font-size:13px;color:var(--tx2);line-height:1.6"><?= nl2br(htmlspecialchars($my_review['comment'])) ?></div>
         <?php endif; ?>
         <div style="font-size:11px;color:var(--tx3);margin-top:8px">Submitted <?= date('M j, Y', strtotime($my_review['created_at'])) ?></div>
        </div>
       <?php else: ?>
        <a href="rate_appointment.php?id=<?= $id ?>" style="display:block;text-decoration:none;margin-bottom:16px">
         <div class="card" style="padding:18px;background:linear-gradient(135deg,#eff6ff,#dbeafe);border:1.5px solid #93c5fd;display:flex;align-items:center;gap:14px;transition:transform .12s">
          <div style="font-size:32px;line-height:1">⭐</div>
          <div style="flex:1">
           <div style="font-size:14px;font-weight:800;color:#1e40af">How was your visit?</div>
           <div style="font-size:12px;color:#1d4ed8;margin-top:2px">Rate Dr. <?= htmlspecialchars($a['dname']) ?> and help other patients.</div>
          </div>
          <div style="font-size:13px;font-weight:700;color:#1e40af">Leave a review →</div>
         </div>
        </a>
       <?php endif; ?>
     <?php endif; ?>

     <div class="flex gap-3" style="padding-bottom:8px">
      <a href="my_appointments.php" class="btn-g">← Back</a>
      <?php if(in_array($a['status'],['confirmed','pending']) && $a['date']>=date('Y-m-d')): ?>
       <a href="book_appointment.php?doctor_id=<?=$a['doctor_id']?>&step=1" class="btn-ot">Rebook</a>
       <a href="cancel_appointment.php?id=<?=$id?>" class="btn-r" style="margin-left:auto">Cancel appointment</a>
      <?php endif; ?>
     </div>
    </div>

    <!-- Right panel -->
    <div style="display:flex;flex-direction:column;gap:14px">
     <div class="card" style="padding:18px">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--tx3);margin-bottom:14px">Fee summary</div>
      <div class="flex justify-between" style="margin-bottom:8px;font-size:13px"><span style="color:var(--tx3)">Consultation fee</span><span style="font-weight:700">NPR <?=number_format($a['fee'])?></span></div>
      <div class="flex justify-between" style="font-size:12px;margin-bottom:8px"><span style="color:var(--tx3)">Platform fee</span><span style="color:var(--t);font-weight:600">Free</span></div>
      <div class="flex justify-between" style="font-size:12px;margin-bottom:14px;align-items:center"><span style="color:var(--tx3)">Payment status</span>
       <?php if(($a['payment_status']??'unpaid')==='paid'): ?>
        <span style="background:#dcfce7;color:#166534;border-radius:6px;padding:2px 10px;font-size:11px;font-weight:700;display:flex;align-items:center;gap:5px"><span style="font-weight:900">e</span> Paid via eSewa</span>
       <?php else: ?>
        <span style="background:#fef3c7;color:#92400e;border-radius:6px;padding:2px 10px;font-size:11px;font-weight:700">Unpaid</span>
       <?php endif; ?>
      </div>
      <?php if(!empty($a['transaction_id'])): ?>
      <div style="font-size:11px;color:var(--tx3);margin-bottom:14px">Transaction ID: <span style="font-family:monospace;color:var(--tx2);font-weight:600"><?=htmlspecialchars($a['transaction_id'])?></span></div>
      <?php endif; ?>
      <div class="divl" style="margin-bottom:13px"></div>
      <div class="flex justify-between"><span style="font-size:15px;font-weight:700">Total</span><span style="font-size:18px;font-weight:800;color:var(--b)">NPR <?=number_format($a['fee'])?></span></div>
     </div>
     <div class="card" style="padding:18px;background:var(--bg2)">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--tx3);margin-bottom:11px">Cancellation policy</div>
      <div style="font-size:12px;color:var(--tx2);line-height:1.65">Free cancellation up to <strong>24 hours</strong> before. 50% charge within 24 hrs. No-shows charged in full.</div>
     </div>
    </div>
   </div>
  </div>
 </div>
</div>
</body></html>