<?php
// patient/my_appointments.php — DABS-05
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requirePatient();
$pid   = (int)$_SESSION['patient_id'];
$filter = $_GET['filter'] ?? 'all';
$unread = countUnread($conn, $pid, 'patient');

$where = "a.patient_id=$pid";
if      ($filter === 'upcoming')  $where .= " AND a.date >= CURDATE() AND a.status IN ('pending','confirmed')";
elseif  ($filter === 'past')      $where .= " AND a.status = 'completed'";
elseif  ($filter === 'cancelled') $where .= " AND a.status = 'cancelled'";
// 'all' (default) imposes no extra status filter — shows the patient's full history.

$appts = $conn->query(
    "SELECT a.*, d.name AS dname, d.specialisation, d.clinic_name, d.fee
     FROM appointments a JOIN doctors d ON d.id=a.doctor_id
     WHERE $where ORDER BY a.date DESC, a.time DESC"
);

$page_title = 'My Appointments';
include '../includes/header.php';
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
     <a class="nav-item" href="settings.php"> &nbsp;Settings</a>
  </div>
  <div class="sb-bottom"><a class="nav-item" style="color:var(--tx3)" href="logout.php">↩ &nbsp;Log out</a></div>
 </div>
 <div class="main-content">
  <div class="topbar">
   <div class="flex items-center gap-3"><div class="logo-i">M</div><span class="logo-n">MediBook</span></div>
   <a href="find_doctor.php" class="btn-p btn-sm">+ New booking</a>
  </div>
  <div class="page-body">
   <h1 class="page-title">My Appointments</h1>
   <!-- Filter tabs -->
   <div style="display:flex;gap:0;border-bottom:2px solid var(--bd);margin-bottom:20px">
    <?php foreach(['all'=>'All','upcoming'=>'Upcoming','past'=>'Past','cancelled'=>'Cancelled'] as $k=>$v): ?>
     <a href="?filter=<?=$k?>" style="padding:9px 18px;font-size:13px;text-decoration:none;font-weight:<?=$filter===$k?700:400?>;color:<?=$filter===$k?'var(--b)':'var(--tx3)'?>;border-bottom:<?=$filter===$k?'2.5px solid var(--b)':0?>;margin-bottom:<?=$filter===$k?'-2px':'0'?>"><?=$v?></a>
    <?php endforeach; ?>
   </div>

   <?php if(!empty($_GET['msg'])): ?><div class="alert alert-success"><?=htmlspecialchars($_GET['msg'])?></div><?php endif; ?>

   <?php if($appts->num_rows===0): ?>
    <div class="card" style="padding:48px;text-align:center"><div style="font-size:17px;font-weight:700;margin-bottom:8px;color:var(--tx)">No appointments found</div><a href="find_doctor.php" class="btn-p" style="margin-top:12px;display:inline-block">Book your first appointment</a></div>
   <?php else: ?>
   <div class="card" style="overflow:hidden">
    <table class="table">
     <thead><tr><th>Doctor</th><th>Date &amp; Time</th><th>Location</th><th>Status</th><th>Fee</th><th>Actions</th></tr></thead>
     <tbody>
     <?php while($a=$appts->fetch_assoc()): ?>
      <tr>
       <td><div class="flex items-center gap-3"><div class="av av-blue" style="width:34px;height:34px;font-size:11px"><?=getInitials($a['dname'])?></div><div><div style="font-weight:700;color:var(--tx)"><?=htmlspecialchars($a['dname'])?></div><div style="font-size:11px;color:var(--tx3)"><?=htmlspecialchars($a['specialisation'])?></div></div></div></td>
       <td><div style="font-weight:600;color:var(--tx)"><?=fmtDate($a['date'])?></div><div style="font-size:12px;color:var(--tx3)"><?=fmt12($a['time'])?></div></td>
       <td style="font-size:13px"><?=htmlspecialchars($a['clinic_name']??'Hospital')?></td>
       <td><?=statusBadge($a['status'])?></td>
       <td style="font-weight:600;color:var(--tx)">NPR <?=number_format($a['fee'])?></td>
       <td>
        <div class="flex gap-2">
         <a href="view_appointment.php?id=<?=$a['id']?>" class="btn-ot btn-xs">View</a>
         <?php if(in_array($a['status'],['confirmed','pending']) && $a['date']>=date('Y-m-d')): ?>
          <a href="cancel_appointment.php?id=<?=$a['id']?>" class="btn-r-out btn-xs">Cancel</a>
         <?php endif; ?>
        </div>
       </td>
      </tr>
     <?php endwhile; ?>
     </tbody>
    </table>
   </div>
   <?php endif; ?>
  </div>
 </div>
</div>
</body></html>