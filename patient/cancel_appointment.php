<?php
// patient/cancel_appointment.php — DABS-07
// Tasks T07-1 to T07-5
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requirePatient();
$pid = (int)$_SESSION['patient_id'];
$id  = (int)($_GET['id'] ?? 0);

// T07-2: Check appointment belongs to this patient
$a = $conn->query("SELECT a.*,d.name dname,d.profile_image dimage,d.specialisation,d.clinic_name FROM appointments a JOIN doctors d ON d.id=a.doctor_id WHERE a.id=$id AND a.patient_id=$pid LIMIT 1")->fetch_assoc();
if (!$a) { header('Location: my_appointments.php'); exit; }

// T07-5: Prevent cancelling past or already cancelled
if ($a['status']==='cancelled') { header('Location: my_appointments.php?msg=Already+cancelled'); exit; }
if ($a['date'] < date('Y-m-d'))  { header('Location: my_appointments.php?msg=Cannot+cancel+past+appointments'); exit; }

$cancelled = false;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    // T07-3: UPDATE status to cancelled
    $conn->query("UPDATE appointments SET status='cancelled' WHERE id=$id AND patient_id=$pid");
    // Notify doctor
    insertNotification($conn, $a['doctor_id'], 'doctor',
        " Patient {$_SESSION['patient_name']} cancelled appointment on ".fmtDate($a['date'])." at ".fmt12($a['time']).".");
    // T07-4: Show confirmation
    $cancelled = true;
}

$page_title = 'Cancel Appointment';
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
   <a class="nav-item" href="notifications.php"> &nbsp;Notifications<?php if($unread>0):?><span class="nav-badge"><?=$unread?></span><?php endif?></a>
  </div>
  <div class="sb-bottom"><a class="nav-item" style="color:var(--tx3)" href="logout.php">↩ &nbsp;Log out</a></div>
 </div>
 <div class="main-content">
  <div class="topbar">
   <div class="flex items-center gap-3"><div class="logo-i">M</div><span class="logo-n">MediBook</span></div>
   <?php if(!$cancelled): ?><a href="view_appointment.php?id=<?=$id?>" style="font-size:13px;color:var(--b);font-weight:500;text-decoration:none">← Back to appointment</a><?php endif; ?>
   <div style="width:80px"></div>
  </div>
  <div class="page-body" style="display:flex;justify-content:center">
   <div style="width:100%;max-width:680px">

   <?php if(!$cancelled): ?>
    <!-- Cancel confirmation form -->
    <div style="text-align:center;margin-bottom:28px">
     <div style="width:70px;height:70px;border-radius:50%;background:var(--r2);border:3px solid var(--r3);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:30px"></div>
     <div style="font-size:22px;font-weight:800;color:var(--tx);margin-bottom:8px">Cancel this appointment?</div>
     <div style="font-size:14px;color:var(--tx3)">Please review the details before confirming cancellation.</div>
    </div>

    <!-- Appointment summary -->
    <div class="card" style="padding:20px;margin-bottom:16px">
     <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--tx3);margin-bottom:14px">Appointment being cancelled</div>
     <div class="flex gap-4 items-center" style="margin-bottom:16px">
      <?= doctorAvatar(['name'=>$a['dname'],'profile_image'=>$a['dimage']??null], 52, 'av-blue') ?>
      <div style="flex:1"><div style="font-size:15px;font-weight:800;color:var(--tx)"><?=htmlspecialchars($a['dname'])?></div><div style="font-size:13px;color:var(--tx3)"><?=htmlspecialchars($a['specialisation'])?> · <?=htmlspecialchars($a['clinic_name']??'')?></div></div>
      <?=statusBadge($a['status'])?>
     </div>
     <div class="divl" style="margin-bottom:14px"></div>
     <div class="grid-3" style="gap:12px">
      <div style="padding:12px;background:var(--bg);border-radius:9px;border:1px solid var(--bd)"><div style="font-size:9px;color:var(--tx3);text-transform:uppercase;margin-bottom:5px">Date</div><div style="font-size:14px;font-weight:700;color:var(--tx)"><?=fmtDate($a['date'])?></div></div>
      <div style="padding:12px;background:var(--bg);border-radius:9px;border:1px solid var(--bd)"><div style="font-size:9px;color:var(--tx3);text-transform:uppercase;margin-bottom:5px">Time</div><div style="font-size:14px;font-weight:700;color:var(--tx)"><?=fmt12($a['time'])?></div></div>
      <div style="padding:12px;background:var(--bg);border-radius:9px;border:1px solid var(--bd)"><div style="font-size:9px;color:var(--tx3);text-transform:uppercase;margin-bottom:5px">Booking ID</div><div style="font-size:14px;font-weight:700;color:var(--b)">#MB-<?=str_pad($a['id'],4,'0',STR_PAD_LEFT)?></div></div>
     </div>
    </div>

    <!-- Policy notice -->
    <?php $hours_until = (strtotime($a['date'].' '.$a['time']) - time()) / 3600; ?>
    <?php if($hours_until >= 24): ?>
     <div style="background:var(--t2);border:1px solid var(--t3);border-radius:13px;padding:16px 20px;display:flex;gap:13px;align-items:flex-start;margin-bottom:16px"><div style="font-size:22px"></div><div><div style="font-size:13px;font-weight:700;color:#065F46;margin-bottom:4px">Free cancellation — No charge applies</div><div style="font-size:12px;color:var(--t)">Your appointment is more than 24 hours away.</div></div></div>
    <?php else: ?>
     <div class="alert alert-warning" style="margin-bottom:16px"> Less than 24 hours to appointment. A 50% cancellation fee may apply.</div>
    <?php endif; ?>

    <form method="POST" action="">
     <div class="card" style="padding:20px;margin-bottom:16px">
      <div style="font-size:13px;font-weight:700;color:var(--tx);margin-bottom:14px">Why are you cancelling? <span style="font-size:12px;font-weight:400;color:var(--tx3)">(optional)</span></div>
      <div style="display:flex;flex-direction:column;gap:9px;margin-bottom:14px">
       <?php $reasons=['I found a more convenient time slot','Feeling better, no longer need appointment','Emergency / Personal reasons','Want to switch to a different doctor','Other']; ?>
       <?php foreach($reasons as $i=>$r): ?>
        <label style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--bg);border-radius:9px;border:1.5px solid var(--bd);cursor:pointer" onmouseover="this.style.borderColor='var(--r)'" onmouseout="if(!this.querySelector('input').checked)this.style.borderColor='var(--bd)'">
         <input type="radio" name="reason" value="<?=htmlspecialchars($r)?>" style="accent-color:var(--r);width:15px;height:15px">
         <span style="font-size:13px;color:var(--tx2)"><?=$r?></span>
        </label>
       <?php endforeach; ?>
      </div>
      <div><label style="font-size:12px;font-weight:700;color:var(--tx2);display:block;margin-bottom:6px">Additional notes (optional)</label><textarea name="notes" class="form-control form-textarea" placeholder="Any extra details..." style="height:72px"></textarea></div>
     </div>

     <!-- What happens -->
     <div class="card" style="padding:18px;background:var(--bg2);margin-bottom:20px">
      <div style="font-size:13px;font-weight:700;color:var(--tx);margin-bottom:12px">What happens after cancellation</div>
      <div style="display:flex;flex-direction:column;gap:10px">
       <div class="flex gap-3 items-start"><div class="av av-blue" style="width:22px;height:22px;font-size:11px;flex-shrink:0">1</div><div style="font-size:13px;color:var(--tx2)">Booking cancelled immediately, slot freed</div></div>
       <div class="flex gap-3 items-start"><div class="av av-teal" style="width:22px;height:22px;font-size:11px;flex-shrink:0">2</div><div style="font-size:13px;color:var(--tx2)">Confirmation sent via notification</div></div>
       <div class="flex gap-3 items-start"><div class="av" style="width:22px;height:22px;font-size:11px;flex-shrink:0;background:var(--am);color:#fff">3</div><div style="font-size:13px;color:var(--tx2)">Doctor is notified of the cancellation</div></div>
      </div>
     </div>

     <div class="flex gap-3">
      <a href="view_appointment.php?id=<?=$id?>" class="btn-g" style="flex:1;text-align:center;padding:13px;font-size:14px;border-radius:10px">← Keep my appointment</a>
      <button type="submit" class="btn-r" style="flex:1;padding:13px;font-size:14px;border-radius:10px">Confirm cancellation</button>
     </div>
    </form>

   <?php else: ?>
    <!-- CANCELLED SUCCESS (T07-4) -->
    <div style="text-align:center;padding:20px 0">
     <div style="width:80px;height:80px;border-radius:50%;background:var(--r2);border:3px solid var(--r3);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:34px">✕</div>
     <div style="font-size:24px;font-weight:800;color:var(--tx);margin-bottom:7px">Appointment Cancelled</div>
     <div style="font-size:14px;color:var(--tx3);margin-bottom:36px;max-width:460px;margin-left:auto;margin-right:auto">Your appointment with <?=htmlspecialchars($a['dname'])?> has been successfully cancelled.</div>

     <div class="card" style="padding:22px;margin-bottom:16px;text-align:left;border-left:5px solid var(--r)">
      <div class="flex justify-between items-center" style="margin-bottom:14px"><div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--tx3)">Cancelled appointment</div><span class="badge badge-red">Cancelled</span></div>
      <div class="flex gap-4 items-center" style="margin-bottom:14px"><div class="av" style="width:50px;height:50px;font-size:16px;background:var(--bg2);color:var(--tx3)"><?=getInitials($a['dname'])?></div><div><div style="font-size:14px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($a['dname'])?></div><div style="font-size:12px;color:var(--tx3)"><?=htmlspecialchars($a['specialisation'])?></div></div></div>
      <div class="divl" style="margin-bottom:13px"></div>
      <div class="grid-2" style="gap:11px">
       <div style="padding:11px;background:var(--bg);border-radius:9px"><div style="font-size:9px;color:var(--tx3);text-transform:uppercase;margin-bottom:4px">Date (cancelled)</div><div style="font-size:13px;font-weight:700;color:var(--tx);text-decoration:line-through;opacity:.5"><?=fmtDate($a['date'])?></div></div>
       <div style="padding:11px;background:var(--bg);border-radius:9px"><div style="font-size:9px;color:var(--tx3);text-transform:uppercase;margin-bottom:4px">Time</div><div style="font-size:13px;font-weight:700;color:var(--tx);text-decoration:line-through;opacity:.5"><?=fmt12($a['time'])?></div></div>
      </div>
     </div>

     <div class="flex gap-3" style="margin-top:6px">
      <a href="my_appointments.php" class="btn-g" style="flex:1;padding:13px;font-size:13px;border-radius:10px;text-align:center">View my appointments</a>
      <a href="book_appointment.php?doctor_id=<?=$a['doctor_id']?>&step=1" class="btn-p" style="flex:1;padding:13px;font-size:13px;border-radius:10px;text-align:center">Rebook</a>
      <a href="find_doctor.php" class="btn-g" style="flex:1;padding:13px;font-size:13px;border-radius:10px;text-align:center">Find another doctor</a>
     </div>
    </div>
   <?php endif; ?>
   </div>
  </div>
 </div>
</div>
</body></html>