<?php
// patient/notifications.php — DABS-23, DABS-24 (T23-1,T23-3,T23-4,T23-5,T24-1,T24-2,T24-3)
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requirePatient();
$pid = (int)$_SESSION['patient_id'];

// T24-2: Mark as read
if (isset($_GET['mark_read'])) {
  $nid = (int)$_GET['mark_read'];
  $conn->query("UPDATE notifications SET is_read=1 WHERE id=$nid AND user_id=$pid AND user_type='patient'");
  header('Location: notifications.php'); exit;
}
if (isset($_GET['mark_all'])) {
  $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$pid AND user_type='patient'");
  header('Location: notifications.php'); exit;
}

// T23-3, T23-5: SELECT sorted by recent
$notifs = $conn->query("SELECT * FROM notifications WHERE user_id=$pid AND user_type='patient' ORDER BY created_at DESC");
$unread = countUnread($conn,$pid,'patient');

$page_title='Notifications'; include '../includes/header.php';
?>
<div class="layout">
 <div class="sidebar">
 <div class="sb-profile"><div class="av av-blue" style="width:44px;height:44px;font-size:14px;margin:0 auto 8px"><?=getInitials($_SESSION['patient_name'])?></div><div style="font-size:13px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($_SESSION['patient_name'])?></div></div>
 <div class="sb-nav">
  <a class="nav-item" href="dashboard.php"> &nbsp;Dashboard</a>
  <a class="nav-item" href="find_doctor.php"> &nbsp;Find a Doctor</a>
  <a class="nav-item" href="my_appointments.php"> &nbsp;My Appointments</a>
  <a class="nav-item" href="medical_history.php"> &nbsp;Medical Records</a>
  <a class="nav-item" href="my_profile.php"> &nbsp;My Profile</a>
  <a class="nav-item active" href="notifications.php"> &nbsp;Notifications<?php if($unread>0):?><span class="nav-badge"><?=$unread?></span><?php endif?></a>
    <a class="nav-item" href="settings.php"> &nbsp;Settings</a>
  </div>
 <div class="sb-bottom"><a class="nav-item" style="color:var(--tx3)" href="logout.php">↩ &nbsp;Log out</a></div>
 </div>
 <div class="main-content">
 <div class="topbar"><div class="flex items-center gap-3"><div class="logo-i">M</div><span class="logo-n">MediBook</span></div><div></div><div></div></div>
 <div class="page-body">
  <div class="flex justify-between items-center" style="margin-bottom:20px">
  <div><h1 class="page-title">Notifications <?php if($unread>0):?><span class="nav-badge" style="font-size:13px;padding:2px 9px"><?=$unread?></span><?php endif?></h1><p class="page-sub">All your alerts and reminders</p></div>
  <?php if($unread>0): ?><a href="?mark_all=1" class="btn-g btn-sm">Mark all as read</a><?php endif; ?>
  </div>
  <?php if($notifs->num_rows===0): ?>
  <div class="card" style="padding:48px;text-align:center"><div style="font-size:16px;font-weight:700;color:var(--tx)">No notifications</div></div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:8px">
  <?php while($n=$notifs->fetch_assoc()): ?>
   <!-- T23-4: Highlight unread differently -->
   <div class="card" style="padding:16px;<?=$n['is_read']?'':'background:var(--b3);border-color:var(--b4);'?>">
   <div class="flex items-center gap-3">
    <div style="width:40px;height:40px;border-radius:10px;background:<?=$n['is_read']?'var(--bg2)':'var(--b3)'?>;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">
    
    </div>
    <div style="flex:1">
    <div style="font-size:13px;color:var(--tx2);line-height:1.5"><?=htmlspecialchars($n['message'])?></div>
    <div style="font-size:11px;color:var(--tx3);margin-top:3px"><?=date('M j, Y g:i A',strtotime($n['created_at']))?></div>
    </div>
    <!-- T24-1: Mark as read button -->
    <?php if(!$n['is_read']): ?>
    <a href="?mark_read=<?=$n['id']?>" class="btn-g btn-xs" style="white-space:nowrap">Mark read</a>
    <?php else: ?><span style="font-size:11px;color:var(--tx3)">Read</span><?php endif; ?>
   </div>
   </div>
  <?php endwhile; ?>
  </div>
  <?php endif; ?>
 </div>
 </div>
</div></body></html>
