<?php
// doctor/schedule.php — DABS-12 (T12-1..T12-3)
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requireDoctor();
$did=$_SESSION['doctor_id'];
$sel_date=clean($conn,$_GET['date']??date('Y-m-d'));

// T12-2: SELECT availability
$dr=$conn->query("SELECT * FROM doctors WHERE id=$did LIMIT 1")->fetch_assoc();
$avail=json_decode($dr['availability']??'{}',true);

// T12-3: Booked and available slots
$booked=$conn->query("SELECT a.*,p.name pname FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE a.doctor_id=$did AND a.date='$sel_date' AND a.status!='cancelled' ORDER BY a.time ASC");
$booked_times=[];
$booked_rows=[];
while($r=$booked->fetch_assoc()){$booked_times[]=$r['time'];$booked_rows[$r['time']]=$r;}

$all_times=['09:00:00','09:30:00','10:00:00','10:30:00','11:00:00','11:30:00','12:00:00','12:30:00','13:00:00','13:30:00','14:00:00','14:30:00','15:00:00','15:30:00','16:00:00','16:30:00','17:00:00'];

$unread=countUnread($conn,$did,'doctor');
$page_title='My Schedule'; include '../includes/header.php';
?>
<div class="layout">
 <div class="sidebar">
  <div class="sb-profile" style="text-align:center"><div style="margin:0 auto 8px;display:flex;justify-content:center"><?= doctorAvatar(['name'=>$_SESSION['doctor_name']??'','profile_image'=>$_SESSION['doctor_image']??null], 44, 'av-teal') ?></div><div style="font-size:13px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($_SESSION['doctor_name'])?></div></div>
  <div class="sb-nav">
   <a class="nav-item" href="dashboard.php"> &nbsp;Dashboard</a>
   <a class="nav-item" href="appointments.php"> &nbsp;Appointments</a>
   <a class="nav-item active" href="schedule.php"> &nbsp;My Schedule</a>
   <a class="nav-item" href="availability.php"> &nbsp;Availability</a>
   <a class="nav-item" href="my_profile.php"> &nbsp;My Profile</a>
   <a class="nav-item" href="notifications.php"> &nbsp;Notifications<?php if($unread>0):?><span class="nav-badge"><?=$unread?></span><?php endif?></a>
  </div>
  <div class="sb-bottom"><a class="nav-item" style="color:var(--tx3)" href="logout.php">↩ &nbsp;Log out</a></div>
 </div>
 <div class="main-content">
  <div class="topbar"><div class="flex items-center gap-3"><div class="logo-i">M</div><span class="logo-n">MediBook</span></div><div></div><div></div></div>
  <div class="page-body">
   <div class="flex justify-between items-center" style="margin-bottom:20px"><div><h1 class="page-title">My Schedule</h1><p class="page-sub">View booked and available slots for any date</p></div></div>
   <form method="GET" class="flex items-center gap-3" style="margin-bottom:20px">
    <label style="font-size:13px;font-weight:600;color:var(--tx2)">Select date:</label>
    <input type="date" name="date" class="form-control" style="width:200px;height:40px" value="<?=htmlspecialchars($sel_date)?>" onchange="this.form.submit()">
    <span style="font-size:14px;font-weight:700;color:var(--tx)"><?=fmtDate($sel_date)?></span>
   </form>

   <div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start">
    <div class="card" style="padding:20px">
     <div class="flex justify-between items-center" style="margin-bottom:6px"><div style="font-size:14px;font-weight:700;color:var(--tx)">Time slots — <?=fmtDate($sel_date)?></div><div><span style="background:var(--t2);color:#065F46;border-radius:7px;padding:3px 11px;font-size:11px;font-weight:700"><?=count(array_diff($all_times,array_keys($booked_rows)))?> available</span> &nbsp;<span style="background:var(--am2);color:#92400E;border-radius:7px;padding:3px 11px;font-size:11px;font-weight:700"><?=count($booked_rows)?> booked</span></div></div>
     <p style="font-size:12px;color:var(--tx3);margin-bottom:18px">30 min slots · Click booked slot to view patient</p>
     <?php foreach(['Morning'=>['09:00:00','09:30:00','10:00:00','10:30:00','11:00:00','11:30:00'],'Afternoon'=>['12:00:00','12:30:00','13:00:00','13:30:00','14:00:00','14:30:00'],'Evening'=>['15:00:00','15:30:00','16:00:00','16:30:00','17:00:00']] as $grp=>$gtimes): ?>
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--tx3);margin-bottom:10px"><?=$grp?></div>
      <div class="slot-grid" style="margin-bottom:18px">
       <?php foreach($gtimes as $t): $lbl=date('g:i A',strtotime($t)); $bk=isset($booked_rows[$t]); ?>
        <?php if($bk): $row=$booked_rows[$t]; ?>
         <span title="<?=htmlspecialchars($row['pname'])?>" style="border:1.5px solid var(--am);border-radius:8px;padding:7px 14px;font-size:12px;color:#92400E;background:var(--am2);display:inline-block;cursor:pointer" title="<?=htmlspecialchars($row['pname'])?>"><?=$lbl?> 👤</span>
        <?php else: ?>
         <span style="border:1.5px solid var(--bd2);border-radius:8px;padding:7px 14px;font-size:12px;color:var(--tx3);background:var(--bg);display:inline-block"><?=$lbl?></span>
        <?php endif; ?>
       <?php endforeach; ?>
      </div>
     <?php endforeach; ?>
     <div class="flex gap-4" style="padding-top:12px;border-top:1px solid var(--bd)">
      <div style="display:flex;align-items:center;gap:7px;font-size:12px;color:var(--tx3)"><span style="border:1.5px solid var(--bd2);border-radius:5px;padding:2px 9px;font-size:11px;background:var(--bg);color:var(--tx3)">slot</span>Available</div>
      <div style="display:flex;align-items:center;gap:7px;font-size:12px;color:var(--tx3)"><span style="border:1.5px solid var(--am);border-radius:5px;padding:2px 9px;font-size:11px;background:var(--am2);color:#92400E">slot 👤</span>Booked</div>
     </div>
    </div>

    <!-- Booked patients list -->
    <div style="display:flex;flex-direction:column;gap:12px">
     <div class="card" style="padding:16px"><div style="font-size:13px;font-weight:700;color:var(--tx);margin-bottom:12px">Booked patients — <?=date('M j',strtotime($sel_date))?></div>
      <?php if(empty($booked_rows)): ?><div style="text-align:center;padding:20px;color:var(--tx3);font-size:13px">No bookings for this date</div>
      <?php else: foreach($booked_rows as $t=>$row): ?>
       <div style="display:flex;align-items:center;gap:10px;padding:10px 13px;background:<?=$row['status']==='pending'?'var(--am2)':'#F0F9FF'?>;border-left:4px solid <?=$row['status']==='pending'?'var(--am)':'var(--b)'?>;border-radius:9px;margin-bottom:8px">
        <div style="font-size:12px;font-weight:700;color:<?=$row['status']==='pending'?'var(--am)':'var(--b2)'?>;width:62px;flex-shrink:0"><?=fmt12($t)?></div>
        <div class="av av-blue" style="width:30px;height:30px;font-size:11px"><?=getInitials($row['pname'])?></div>
        <div style="flex:1"><div style="font-size:12px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($row['pname'])?></div></div>
        <?=statusBadge($row['status'])?>
       </div>
      <?php endforeach; endif; ?>
     </div>
    </div>
   </div>
  </div>
 </div>
</div></body></html>