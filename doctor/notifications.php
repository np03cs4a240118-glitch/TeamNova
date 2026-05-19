<?php
// doctor/notifications.php — DABS-23, DABS-24
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requireDoctor();
$did=(int)$_SESSION['doctor_id'];
if(isset($_GET['mark_read'])){ $nid=(int)$_GET['mark_read']; $conn->query("UPDATE notifications SET is_read=1 WHERE id=$nid AND user_id=$did AND user_type='doctor'"); header('Location: notifications.php'); exit; }
if(isset($_GET['mark_all'])){ $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$did AND user_type='doctor'"); header('Location: notifications.php'); exit; }
$notifs=$conn->query("SELECT * FROM notifications WHERE user_id=$did AND user_type='doctor' ORDER BY created_at DESC");
$unread=countUnread($conn,$did,'doctor');
$page_title='Notifications'; include '../includes/header.php';
?>
<div class="layout">
 <div class="sidebar">
  <div class="sb-profile" style="text-align:center"><div style="margin:0 auto 8px;display:flex;justify-content:center"><?= doctorAvatar(['name'=>$_SESSION['doctor_name']??'','profile_image'=>$_SESSION['doctor_image']??null], 44, 'av-teal') ?></div><div style="font-size:13px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($_SESSION['doctor_name'])?></div></div>
  <div class="sb-nav">
   <a class="nav-item" href="dashboard.php"> &nbsp;Dashboard</a>
   <a class="nav-item" href="appointments.php"> &nbsp;Appointments</a>
   <a class="nav-item" href="schedule.php"> &nbsp;My Schedule</a>
   <a class="nav-item" href="availability.php"> &nbsp;Availability</a>
   <a class="nav-item" href="my_profile.php"> &nbsp;My Profile</a>
   <a class="nav-item active" href="notifications.php"> &nbsp;Notifications<?php if($unread>0):?><span class="nav-badge"><?=$unread?></span><?php endif?></a>
  </div>
  <div class="sb-bottom"><a class="nav-item" style="color:var(--tx3)" href="logout.php">↩ &nbsp;Log out</a></div>
 </div>
 <div class="main-content">
  <div class="topbar"><div class="flex items-center gap-3"><div class="logo-i">M</div><span class="logo-n">MediBook</span></div><div></div><div></div></div>
  <div class="page-body">
   <div class="flex justify-between items-center" style="margin-bottom:20px">
    <div><h1 class="page-title">Notifications<?php if($unread>0):?> <span class="nav-badge" style="font-size:13px;padding:2px 9px"><?=$unread?></span><?php endif?></h1><p class="page-sub">All alerts and booking updates</p></div>
    <?php if($unread>0): ?><a href="?mark_all=1" class="btn-g btn-sm">Mark all as read</a><?php endif; ?>
   </div>
   <?php if($notifs->num_rows===0): ?><div class="card" style="padding:48px;text-align:center"><div style="font-size:40px;margin-bottom:12px">🔔</div><div style="font-size:16px;font-weight:700;color:var(--tx)">No notifications</div></div>
   <?php else: while($n=$notifs->fetch_assoc()): ?>
    <div class="card" style="padding:16px;margin-bottom:8px;<?=$n['is_read']?'':'background:var(--t2);border-color:var(--t3);'?>">
     <div class="flex items-center gap-3">
      <div style="width:40px;height:40px;border-radius:10px;background:<?=$n['is_read']?'var(--bg2)':'var(--t2)'?>;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0"><?=str_contains($n['message'],'📅')?'📅':(str_contains($n['message'],'❌')?'❌':'🔔')?></div>
      <div style="flex:1"><div style="font-size:13px;color:var(--tx2);line-height:1.5"><?=htmlspecialchars($n['message'])?></div><div style="font-size:11px;color:var(--tx3);margin-top:3px"><?=date('M j, Y g:i A',strtotime($n['created_at']))?></div></div>
      <?php if(!$n['is_read']): ?><a href="?mark_read=<?=$n['id']?>" class="btn-g btn-xs">Mark read</a><?php else: ?><span style="font-size:11px;color:var(--tx3)">Read</span><?php endif; ?>
     </div>
    </div>
   <?php endwhile; endif; ?>
  </div>
 </div>
</div></body></html>