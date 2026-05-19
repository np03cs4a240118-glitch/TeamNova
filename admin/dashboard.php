<?php
// admin/dashboard.php — Admin overview
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requireAdmin();

$total_doctors   = $conn->query("SELECT COUNT(*) c FROM doctors")->fetch_assoc()['c'];
$active_doctors  = $conn->query("SELECT COUNT(*) c FROM doctors WHERE status='approved'")->fetch_assoc()['c'];
$pending_doctors = $conn->query("SELECT COUNT(*) c FROM doctors WHERE status='pending'")->fetch_assoc()['c'];
$total_patients  = $conn->query("SELECT COUNT(*) c FROM patients")->fetch_assoc()['c'];
$today_appts     = $conn->query("SELECT COUNT(*) c FROM appointments WHERE date=CURDATE()")->fetch_assoc()['c'];
$month_appts     = $conn->query("SELECT COUNT(*) c FROM appointments WHERE MONTH(date)=MONTH(CURDATE()) AND YEAR(date)=YEAR(CURDATE())")->fetch_assoc()['c'];

// Recent bookings
$recent = $conn->query(
    "SELECT a.*,p.name pname,d.name dname,d.specialisation
     FROM appointments a
     JOIN patients p ON p.id=a.patient_id
     JOIN doctors d ON d.id=a.doctor_id
     ORDER BY a.created_at DESC LIMIT 8"
);

// Pending doctor approvals
$pending_list = $conn->query(
    "SELECT * FROM doctors WHERE status='pending' ORDER BY created_at DESC LIMIT 5"
);

$page_title = 'Admin Dashboard';
include '../includes/header.php';
?>
<style>
.admin-bg{background:#0F1923}
</style>
<div class="layout">
 <!-- Dark sidebar -->
 <div class="sidebar-dark">
  <div class="sb-profile-dark">
   <div class="flex items-center gap-3">
    <div class="logo-i" style="width:28px;height:28px"><svg width="12" height="12" fill="none"><rect x="5" y="1" width="2" height="10" rx="1" fill="white"/><rect x="1" y="5" width="10" height="2" rx="1" fill="white"/></svg></div>
    <span style="font-size:14px;font-weight:800;color:#F8FAFC">Admin Panel</span>
   </div>
  </div>
  <div class="sb-nav">
   <a class="nav-item-dark active" href="dashboard.php"> &nbsp;Dashboard</a>
   <a class="nav-item-dark" href="manage_doctors.php"> &nbsp;Doctors</a>
   <a class="nav-item-dark" href="manage_patients.php"> &nbsp;Patients</a>
   <a class="nav-item-dark" href="manage_appointments.php"> &nbsp;Bookings</a>
   <a class="nav-item-dark" href="revenue.php"> &nbsp;Revenue</a>
  </div>
  <div class="sb-bottom-dark">
   <a class="nav-item-dark" href="logout.php">↩ &nbsp;Log out</a>
  </div>
 </div>

 <!-- Main -->
 <div class="main-content">
  <div class="topbar-dark">
   <div class="flex items-center gap-3">
    <div class="logo-i">M</div>
    <span style="font-size:16px;font-weight:800;color:#F8FAFC;letter-spacing:-.2px">MediBook Admin</span>
   </div>
   <span style="font-size:13px;color:#64748B"><?=date('l, F j, Y')?></span>
   <div class="flex gap-2">
    <?php if($pending_doctors>0): ?>
     <a href="manage_doctors.php?filter=pending" class="btn-ot btn-sm" style="color:#FCD34D;border-color:#F59E0B">⏳ <?=$pending_doctors?> pending</a>
    <?php endif; ?>
    <a href="manage_doctors.php" class="btn-p btn-sm">+ Add Doctor</a>
   </div>
  </div>

  <div class="admin-page-body">
   <h1 class="page-title" style="color:#F8FAFC">Dashboard</h1>
   <p class="page-sub" style="color:#64748B;margin-bottom:22px">System overview for <?=date('F Y')?></p>

   <!-- Stats grid -->
   <div class="stat-grid" style="grid-template-columns:repeat(6,1fr);margin-bottom:22px">
    <div class="card-dark" style="padding:16px;text-align:center"><div style="font-size:22px;margin-bottom:7px"></div><div style="font-size:24px;font-weight:800;color:#F8FAFC;margin-bottom:4px"><?=$total_doctors?></div><div style="font-size:11px;color:#64748B">Doctors</div><?php if($active_doctors): ?><div style="font-size:10px;color:#34D399;font-weight:600;margin-top:4px">↑ <?=$active_doctors?> active</div><?php endif ?></div>
    <div class="card-dark" style="padding:16px;text-align:center"><div style="font-size:22px;margin-bottom:7px"></div><div style="font-size:24px;font-weight:800;color:#F8FAFC;margin-bottom:4px"><?=$total_patients?></div><div style="font-size:11px;color:#64748B">Patients</div></div>
    <div class="card-dark" style="padding:16px;text-align:center"><div style="font-size:22px;margin-bottom:7px"></div><div style="font-size:24px;font-weight:800;color:#60A5FA;margin-bottom:4px"><?=$today_appts?></div><div style="font-size:11px;color:#64748B">Today</div></div>
    <div class="card-dark" style="padding:16px;text-align:center"><div style="font-size:22px;margin-bottom:7px"></div><div style="font-size:24px;font-weight:800;color:#F8FAFC;margin-bottom:4px"><?=$month_appts?></div><div style="font-size:11px;color:#64748B">This month</div></div>
    <div style="background:linear-gradient(135deg,#1A3A6B,#1A6FD4);border:1px solid #2563EB;border-radius:13px;padding:16px;text-align:center"><div style="font-size:22px;margin-bottom:7px"></div><div style="font-size:24px;font-weight:800;color:#FCD34D;margin-bottom:4px"><?=$pending_doctors?></div><div style="font-size:11px;color:#93C5FD">Pending approvals</div></div>
    <div style="background:linear-gradient(135deg,#064E3B,#0D9E7A);border:1px solid #059669;border-radius:13px;padding:16px;text-align:center"><div style="font-size:22px;margin-bottom:7px"></div><div style="font-size:24px;font-weight:800;color:#6EE7B7;margin-bottom:4px"><?=$active_doctors?></div><div style="font-size:11px;color:#34D399">Approved doctors</div></div>
   </div>

   <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <!-- Recent bookings -->
    <div class="card-dark" style="overflow:hidden">
     <div class="card-header-dark">
      <span style="font-size:14px;font-weight:700;color:#F8FAFC">Recent bookings</span>
      <a href="manage_appointments.php" style="font-size:12px;color:#60A5FA;font-weight:600;text-decoration:none">View all →</a>
     </div>
     <table class="table" style="width:100%">
      <thead><tr style="background:#111827"><th class="table-dark" style="padding:9px 14px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Patient</th><th class="table-dark" style="padding:9px 14px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Doctor</th><th class="table-dark" style="padding:9px 14px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Date</th><th class="table-dark" style="padding:9px 14px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Status</th></tr></thead>
      <tbody>
       <?php while($a=$recent->fetch_assoc()): ?>
        <tr style="border-top:1px solid #2D3F56">
         <td style="padding:11px 14px;font-size:13px;color:#D1D5DB"><?=htmlspecialchars($a['pname'])?></td>
         <td style="padding:11px 14px;font-size:13px;color:#D1D5DB"><?=htmlspecialchars($a['dname'])?></td>
         <td style="padding:11px 14px;font-size:11px;color:#94A3B8"><?=date('M j',strtotime($a['date']))?></td>
         <td style="padding:11px 14px"><?=statusBadge($a['status'])?></td>
        </tr>
       <?php endwhile; ?>
      </tbody>
     </table>
    </div>

    <!-- Pending approvals -->
    <div class="card-dark">
     <div class="card-header-dark">
      <span style="font-size:14px;font-weight:700;color:#F8FAFC">Pending doctor approvals</span>
      <?php if($pending_doctors>0): ?><span style="background:#F59E0B;color:#1A1A1A;border-radius:7px;padding:3px 11px;font-size:11px;font-weight:700"><?=$pending_doctors?> waiting</span><?php endif; ?>
     </div>
     <div style="padding:16px;display:flex;flex-direction:column;gap:11px">
      <?php if($pending_list->num_rows===0): ?>
       <div style="text-align:center;padding:20px;color:#64748B">No pending approvals </div>
      <?php else: while($d=$pending_list->fetch_assoc()): ?>
       <div style="display:flex;align-items:center;gap:13px;padding:13px;background:#111827;border-radius:10px">
        <div class="av av-blue" style="width:40px;height:40px;font-size:14px"><?=getInitials($d['name'])?></div>
        <div style="flex:1">
         <div style="font-size:13px;font-weight:700;color:#F8FAFC"><?=htmlspecialchars($d['name'])?></div>
         <div style="font-size:12px;color:#64748B"><?=htmlspecialchars($d['specialisation'])?> · <?=htmlspecialchars($d['qualification']??'')?></div>
        </div>
        <a href="manage_doctors.php?reject=<?=$d['id']?>" style="background:transparent;color:#EF4444;border:1px solid #EF4444;border-radius:7px;padding:5px 12px;font-size:11px;text-decoration:none;cursor:pointer" onclick="return confirm('Reject this doctor?')">Reject</a>
        <a href="manage_doctors.php?approve=<?=$d['id']?>" style="background:#059669;color:#fff;border:none;border-radius:7px;padding:6px 13px;font-size:11px;font-weight:700;text-decoration:none">Approve ✓</a>
       </div>
      <?php endwhile; endif; ?>
      <?php if($pending_doctors>5): ?><a href="manage_doctors.php?filter=pending" style="text-align:center;padding:10px;font-size:12px;color:#60A5FA;text-decoration:none;display:block">View all <?=$pending_doctors?> pending →</a><?php endif; ?>
     </div>
    </div>
   </div>
  </div>
 </div>
</div>
</body></html>
