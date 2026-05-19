<?php
// patient/dashboard.php — DABS-05, DABS-22, DABS-23
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requirePatient();

$pid  = (int)$_SESSION['patient_id'];
$name = $_SESSION['patient_name'];

// DABS-22: Check and insert 1-day reminders on page load (T22-3)
checkAndInsertReminders($conn);

// T05-2: Upcoming appointments
$upcoming = $conn->query(
    "SELECT a.*, d.name AS dname, d.specialisation, d.clinic_name
     FROM appointments a JOIN doctors d ON d.id=a.doctor_id
     WHERE a.patient_id=$pid AND a.date >= CURDATE()
       AND a.status IN ('confirmed','pending')
     ORDER BY a.date ASC, a.time ASC LIMIT 5"
);

// T05-4: Past appointments
$past = $conn->query(
    "SELECT a.*, d.name AS dname, d.specialisation
     FROM appointments a JOIN doctors d ON d.id=a.doctor_id
     WHERE a.patient_id=$pid AND (a.date < CURDATE() OR a.status IN ('cancelled','completed'))
     ORDER BY a.date DESC LIMIT 3"
);

// Stats
$total = $conn->query("SELECT COUNT(*) c FROM appointments WHERE patient_id=$pid")->fetch_assoc()['c'];
$upcoming_count = $conn->query("SELECT COUNT(*) c FROM appointments WHERE patient_id=$pid AND date>=CURDATE() AND status IN ('confirmed','pending')")->fetch_assoc()['c'];
$unread = countUnread($conn, $pid, 'patient');

// Notifications (T23-1, T23-5)
$notifs = $conn->query(
    "SELECT * FROM notifications WHERE user_id=$pid AND user_type='patient'
     ORDER BY created_at DESC LIMIT 5"
);

$page_title = 'My Dashboard';
include '../includes/header.php';
?>
<div class="layout">
 <!-- Sidebar -->
 <div class="sidebar">
  <div class="sb-profile">
   <div class="av av-blue" style="width:46px;height:46px;font-size:16px;margin:0 auto 8px"><?= getInitials($name) ?></div>
   <div style="font-size:13px;font-weight:700;color:var(--tx)"><?= htmlspecialchars($name) ?></div>
   <div style="font-size:11px;color:var(--tx3);margin-top:2px">Patient</div>
  </div>
  <div class="sb-nav">
   <a class="nav-item active" href="dashboard.php"> &nbsp;Dashboard</a>
    <a class="nav-item" href="find_doctor.php"> &nbsp;Find a Doctor</a>
   <a class="nav-item" href="my_appointments.php"> &nbsp;My Appointments</a>
  
   <a class="nav-item" href="medical_history.php"> &nbsp;Medical Records</a>
   <a class="nav-item" href="my_profile.php"> &nbsp;My Profile</a>
   <a class="nav-item" href="notifications.php"> &nbsp;Notifications
    <?php if($unread>0):?><span class="nav-badge"><?=$unread?></span><?php endif?>
   </a>
     <a class="nav-item" href="settings.php"> &nbsp;Settings</a>
  </div>
  <div class="sb-bottom">
   <a class="nav-item" style="color:var(--tx3)" href="logout.php">↩ &nbsp;Log out</a>
  </div>
 </div>

 <!-- Main -->
 <div class="main-content">
  <div class="topbar">
   <div class="flex items-center gap-3">
    <div class="logo-i">M</div>
    <span class="logo-n">MediBook</span>
   </div>
   <a class="btn-p btn-sm" href="find_doctor.php">+ Book appointment</a>
  </div>

  <div class="page-body">
   <div class="flex justify-between items-center" style="margin-bottom:20px">
    <div>
     <h1 class="page-title">Good day, <?= htmlspecialchars(explode(' ',$name)[0]) ?></h1>
     <p class="page-sub"><?= date('l, F j, Y') ?></p>
    </div>
   </div>

   <!-- Stats -->
   <div class="stat-grid" style="grid-template-columns:repeat(4,1fr)">
    <div class="stat-card"><div style="font-size:26px;margin-bottom:8px"></div><div class="stat-num"><?=$upcoming_count?></div><div class="stat-lbl">Upcoming</div></div>
    <div class="stat-card"><div style="font-size:26px;margin-bottom:8px"></div><div class="stat-num"><?=$total?></div><div class="stat-lbl">Total visits</div></div>
    <div class="stat-card"><div style="font-size:26px;margin-bottom:8px"></div><div class="stat-num"><?=$unread?></div><div class="stat-lbl">Notifications</div><div class="stat-sub" style="color:var(--b)">Unread</div></div>
    <div class="stat-card" style="background:linear-gradient(135deg,var(--b),var(--b2));border-color:var(--b2)"><div style="font-size:26px;margin-bottom:8px">➕</div><div class="stat-num" style="color:#fff">Book</div><div class="stat-lbl" style="color:rgba(255,255,255,.7)"><a href="find_doctor.php" style="color:#fff;text-decoration:none;font-weight:700">Find doctors →</a></div></div>
   </div>

   <div class="grid-2" style="gap:20px;align-items:start">
    <div>
     <!-- Upcoming appointments -->
     <div class="card" style="margin-bottom:16px">
      <div class="card-header">
       <span style="font-size:14px;font-weight:700;color:var(--tx)">Upcoming appointments</span>
       <a href="my_appointments.php" style="font-size:12px;color:var(--b);font-weight:600;text-decoration:none">View all →</a>
      </div>
      <div style="padding:0 4px">
       <?php if($upcoming->num_rows===0): ?>
        <div style="padding:28px;text-align:center;color:var(--tx3)">
         <div style="font-size:32px;margin-bottom:10px"></div>
         <div style="font-weight:600;margin-bottom:6px">No upcoming appointments</div>
         <a href="find_doctor.php" class="btn-p btn-sm" style="margin-top:8px;display:inline-block">Book now</a>
        </div>
       <?php else: while($a=$upcoming->fetch_assoc()): ?>
        <a href="view_appointment.php?id=<?=$a['id']?>" style="text-decoration:none">
         <div class="flex items-center gap-3" style="padding:14px 16px;border-bottom:1px solid var(--bg2);transition:background .15s" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background='transparent'">
          <div style="background:var(--b3);border:1px solid var(--b4);border-radius:9px;padding:8px 12px;text-align:center;flex-shrink:0">
           <div style="font-size:9px;font-weight:700;color:var(--b);text-transform:uppercase"><?=date('M',strtotime($a['date']))?></div>
           <div style="font-size:20px;font-weight:800;color:var(--b2);line-height:1"><?=date('j',strtotime($a['date']))?></div>
          </div>
          <div class="av av-blue" style="width:38px;height:38px;font-size:13px"><?=getInitials($a['dname'])?></div>
          <div style="flex:1">
           <div style="font-size:13px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($a['dname'])?></div>
           <div style="font-size:12px;color:var(--tx3);margin-top:2px"><?=fmt12($a['time'])?> · <?=htmlspecialchars($a['clinic_name']?:'Hospital')?></div>
          </div>
          <?=statusBadge($a['status'])?>
         </div>
        </a>
       <?php endwhile; endif; ?>
      </div>
     </div>

     <!-- Past appointments preview -->
     <?php if($past->num_rows>0): ?>
     <div class="card">
      <div class="card-header">
       <span style="font-size:14px;font-weight:700;color:var(--tx)">Recent past visits</span>
       <a href="medical_history.php" style="font-size:12px;color:var(--b);font-weight:600;text-decoration:none">Full history →</a>
      </div>
      <?php while($a=$past->fetch_assoc()): ?>
       <div class="flex items-center gap-3" style="padding:12px 16px;border-bottom:1px solid var(--bg2)">
        <div class="av" style="width:36px;height:36px;font-size:12px;background:var(--bg2);color:var(--tx3)"><?=getInitials($a['dname'])?></div>
        <div style="flex:1"><div style="font-size:13px;font-weight:600;color:var(--tx)"><?=htmlspecialchars($a['dname'])?></div><div style="font-size:12px;color:var(--tx3)"><?=fmtDate($a['date'])?></div></div>
        <?=statusBadge($a['status'])?>
       </div>
      <?php endwhile; ?>
     </div>
     <?php endif; ?>
    </div>

    <!-- Notifications panel -->
    <div>
     <div class="card">
      <div class="card-header">
       <span style="font-size:14px;font-weight:700;color:var(--tx)">Notifications <?php if($unread>0):?><span class="nav-badge"><?=$unread?></span><?php endif?></span>
       <a href="notifications.php" style="font-size:12px;color:var(--b);font-weight:600;text-decoration:none">See all →</a>
      </div>
      <div style="padding:12px 16px">
       <?php $notifs->data_seek(0); $count=0; while($n=$notifs->fetch_assoc()): $count++; ?>
        <div class="notif-item <?=$n['is_read']?'read':'unread'?>" style="margin-bottom:8px">
         <div class="notif-icon" style="background:var(--b3)"></div>
         <div style="flex:1">
          <div style="font-size:12px;color:var(--tx2);line-height:1.5"><?=htmlspecialchars($n['message'])?></div>
          <div style="font-size:10px;color:var(--tx3);margin-top:3px"><?=date('M j, g:i A',strtotime($n['created_at']))?></div>
         </div>
         <?php if(!$n['is_read']): ?><span style="width:8px;height:8px;border-radius:50%;background:var(--b);flex-shrink:0;margin-top:4px"></span><?php endif?>
        </div>
       <?php endwhile; ?>
       <?php if($count===0): ?>
        <div style="text-align:center;padding:20px;color:var(--tx3)">No notifications yet</div>
       <?php endif; ?>
      </div>
     </div>
    </div>
   </div>
  </div>
 </div>
</div>
</body></html>
