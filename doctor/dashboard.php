<?php
// doctor/dashboard.php — DABS-11 (T11-1 to T11-4)
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requireDoctor();
$did   = (int)$_SESSION['doctor_id'];
$dname = $_SESSION['doctor_name'];
$dr    = $conn->query("SELECT * FROM doctors WHERE id=$did LIMIT 1")->fetch_assoc();
$unread= countUnread($conn,$did,'doctor');

// T11-2: SELECT appointments by doctor_id
$today_appts   = $conn->query("SELECT a.*,p.name pname,p.phone FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE a.doctor_id=$did AND a.date=CURDATE() ORDER BY a.time ASC");
$upcoming_count= $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$did AND date>=CURDATE() AND status IN ('confirmed','pending')")->fetch_assoc()['c'];
$today_count   = $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$did AND date=CURDATE() AND status!='cancelled'")->fetch_assoc()['c'];
$pending_count = $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$did AND status='pending'")->fetch_assoc()['c'];

// ── Fix: build dynamic styles as PHP variables first ─────
$pending_card_style = $pending_count > 0 ? 'background:var(--am2);border-color:#FDE68A' : '';
$pending_num_style  = $pending_count > 0 ? 'color:var(--am)' : '';
$last_name          = explode(' ', $dname);
$last_name          = htmlspecialchars(end($last_name));

$page_title = 'Doctor Dashboard';
include '../includes/header.php';
?>
<div class="layout">
 <div class="sidebar">
  <div class="sb-profile" style="text-align:center">
   <div style="margin:0 auto 8px;display:flex;justify-content:center"><?= doctorAvatar(['name'=>$dname,'profile_image'=>$_SESSION['doctor_image']??null], 46, 'av-teal') ?></div>
   <div style="font-size:13px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($dname)?></div>
   <div style="font-size:11px;color:var(--tx3);margin-top:2px"><?=htmlspecialchars($dr['specialisation'])?></div>
  </div>
  <div class="sb-nav">
   <a class="nav-item active" href="dashboard.php"> &nbsp;Dashboard</a>
   <a class="nav-item" href="appointments.php"> &nbsp;Appointments</a>
   <a class="nav-item" href="schedule.php"> &nbsp;My Schedule</a>
   <a class="nav-item" href="availability.php"> &nbsp;Availability</a>
   <a class="nav-item" href="my_profile.php"> &nbsp;My Profile</a>
   <a class="nav-item" href="notifications.php"> &nbsp;Notifications
    <?php if($unread > 0): ?>
     <span class="nav-badge"><?=$unread?></span>
    <?php endif; ?>
   </a>
  </div>
  <div class="sb-bottom">
   <a class="nav-item" style="color:var(--tx3)" href="logout.php">↩ &nbsp;Log out</a>
  </div>
 </div>

 <div class="main-content">
  <div class="topbar">
   <div class="flex items-center gap-3">
    <div class="logo-i">M</div>
    <span class="logo-n">MediBook</span>
   </div>
   <span style="font-size:12px;color:var(--tx3)">Doctor Portal</span>
   <a href="availability.php" class="btn-t btn-sm" style="background:linear-gradient(135deg,var(--t),#065F46)">Edit availability</a>
  </div>

  <div class="page-body">
   <div class="flex justify-between items-center" style="margin-bottom:20px">
    <div>
     <h1 class="page-title">Good day, <?=$last_name?></h1>
     <p class="page-sub"><?=date('l, F j, Y')?></p>
    </div>
    <div class="flex gap-2">
     <?php if($pending_count > 0): ?>
      <a href="appointments.php?filter=pending" class="btn-ot btn-sm"> <?=$pending_count?> pending</a>
     <?php endif; ?>
    </div>
   </div>

   <!-- Stat cards -->
   <div class="stat-grid" style="grid-template-columns:repeat(4,1fr)">

    <div class="stat-card">
    
     <div class="stat-num"><?=$today_count?></div>
     <div class="stat-lbl">Today's patients</div>
    </div>

    <div class="stat-card">
    
     <div class="stat-num"><?=$upcoming_count?></div>
     <div class="stat-lbl">Upcoming</div>
    </div>

    <!-- ✅ Fixed: style built as PHP variable above, no quotes clash -->
    <div class="stat-card" style="<?=$pending_card_style?>">
    
     <div class="stat-num" style="<?=$pending_num_style?>"><?=$pending_count?></div>
     <div class="stat-lbl">Pending confirm</div>
     <?php if($pending_count > 0): ?>
      <div class="stat-sub" style="color:var(--am)">Action needed</div>
     <?php endif; ?>
    </div>

    <div class="stat-card" style="background:linear-gradient(135deg,var(--b),var(--b2));border-color:var(--b2)">
    
     <div class="stat-num" style="color:#fff">NPR <?=number_format($today_count * ($dr['fee'] ?? 800))?></div>
     <div class="stat-lbl" style="color:rgba(255,255,255,.7)">Today's earnings</div>
    </div>

   </div>

   <!-- Today's schedule -->
   <div class="card">
    <div class="card-header">
     <span style="font-size:14px;font-weight:700;color:var(--tx)">Today's schedule — <?=date('D, M j')?></span>
     <a href="appointments.php" style="font-size:12px;color:var(--b);font-weight:600;text-decoration:none">All appointments →</a>
    </div>
    <div style="padding:0 4px">
     <?php if($today_appts->num_rows === 0): ?>
      <div style="padding:32px;text-align:center;color:var(--tx3)">
       <div style="font-size:32px;margin-bottom:10px"></div>
       No appointments scheduled for today
      </div>
     <?php else: while($a = $today_appts->fetch_assoc()): ?>

      <?php
      // ✅ Fixed: build row style as PHP variable
      $row_bg     = $a['status'] === 'pending' ? 'background:var(--am2)' : '';
      $time_color = $a['status'] === 'pending' ? 'color:var(--am)' : 'color:var(--b2)';
      ?>

      <div class="flex items-center gap-3" style="padding:13px 16px;border-bottom:1px solid var(--bg2);<?=$row_bg?>">
       <div style="font-size:13px;font-weight:700;<?=$time_color?>;width:80px;flex-shrink:0">
        <?=fmt12($a['time'])?>
       </div>
       <a href="view_patient.php?id=<?=$a['patient_id']?>" class="flex items-center gap-3" style="flex:1;text-decoration:none;color:inherit">
        <div class="av av-blue" style="width:36px;height:36px;font-size:12px">
         <?=getInitials($a['pname'])?>
        </div>
        <div style="flex:1">
         <div style="font-size:13px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($a['pname'])?></div>
         <?php if($a['reason']): ?>
          <div style="font-size:12px;color:var(--tx3)"><?=htmlspecialchars(substr($a['reason'],0,50))?></div>
         <?php endif; ?>
        </div>
       </a>
       <?=statusBadge($a['status'])?>
       <?php if($a['status'] === 'pending'): ?>
        <a href="appointments.php?confirm=<?=$a['id']?>" class="btn-t btn-xs" style="background:linear-gradient(135deg,var(--t),#065F46)">Confirm</a>
       <?php endif; ?>
       <a href="appointments.php?view=<?=$a['id']?>" class="btn-g btn-xs">View</a>
      </div>

     <?php endwhile; endif; ?>
    </div>
   </div>

  </div>
 </div>
</div>
</body>
</html>