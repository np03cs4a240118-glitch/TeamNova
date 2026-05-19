<?php
// admin/manage_appointments.php
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requireAdmin();

$search = clean($conn, $_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'all';
$where  = '1';
if ($filter === 'confirmed') $where = "a.status='confirmed'";
elseif ($filter === 'pending')  $where = "a.status='pending'";
elseif ($filter === 'cancelled') $where = "a.status='cancelled'";
elseif ($filter === 'today')   $where = "a.date=CURDATE()";
if ($search) $where .= " AND (p.name LIKE '%$search%' OR d.name LIKE '%$search%')";

$appts = $conn->query(
  "SELECT a.*,p.name pname,d.name dname,d.specialisation,d.fee
   FROM appointments a
   JOIN patients p ON p.id=a.patient_id
   JOIN doctors d ON d.id=a.doctor_id
   WHERE $where ORDER BY a.date DESC, a.time DESC"
);
$counts = [
  'all'    => $conn->query("SELECT COUNT(*) c FROM appointments")->fetch_assoc()['c'],
  'confirmed' => $conn->query("SELECT COUNT(*) c FROM appointments WHERE status='confirmed'")->fetch_assoc()['c'],
  'pending'  => $conn->query("SELECT COUNT(*) c FROM appointments WHERE status='pending'")->fetch_assoc()['c'],
  'cancelled' => $conn->query("SELECT COUNT(*) c FROM appointments WHERE status='cancelled'")->fetch_assoc()['c'],
  'today'   => $conn->query("SELECT COUNT(*) c FROM appointments WHERE date=CURDATE()")->fetch_assoc()['c'],
];

$page_title = 'Manage Appointments';
include '../includes/header.php';
?>
<div class="layout">
 <div class="sidebar-dark">
 <div class="sb-profile-dark"><div class="flex items-center gap-3"><div class="logo-i" style="width:28px;height:28px"><svg width="12" height="12" fill="none"><rect x="5" y="1" width="2" height="10" rx="1" fill="white"/><rect x="1" y="5" width="10" height="2" rx="1" fill="white"/></svg></div><span style="font-size:14px;font-weight:800;color:#F8FAFC">Admin Panel</span></div></div>
 <div class="sb-nav">
  <a class="nav-item-dark" href="dashboard.php"> &nbsp;Dashboard</a>
  <a class="nav-item-dark" href="manage_doctors.php"> &nbsp;Doctors</a>
  <a class="nav-item-dark" href="manage_patients.php"> &nbsp;Patients</a>
  <a class="nav-item-dark active" href="manage_appointments.php"> &nbsp;Bookings</a>
  <a class="nav-item-dark" href="revenue.php"> &nbsp;Revenue</a>
 </div>
 <div class="sb-bottom-dark"><a class="nav-item-dark" href="logout.php">↩ &nbsp;Log out</a></div>
 </div>
 <div class="main-content">
 <div class="topbar-dark">
  <div class="flex items-center gap-3"><div class="logo-i">M</div><span style="font-size:16px;font-weight:800;color:#F8FAFC">MediBook Admin</span></div>
  <form method="GET" class="flex gap-2">
  <input type="text" name="search" class="form-control" style="background:#1A2840;border-color:#2D3F56;color:#F8FAFC;width:250px;height:38px" placeholder=" Search patient or doctor..." value="<?=htmlspecialchars($search)?>">
  <input type="hidden" name="filter" value="<?=htmlspecialchars($filter)?>">
  <button type="submit" class="btn-p btn-sm" style="height:38px">Search</button>
  </form>
  <div style="width:80px"></div>
 </div>
 <div class="admin-page-body">
  <h1 class="page-title" style="color:#F8FAFC">Manage Appointments</h1>

  <!-- Stats -->
  <div class="stat-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:18px">
  <div class="card-dark" style="padding:12px;text-align:center"><div style="font-size:22px;font-weight:800;color:#F8FAFC;margin-bottom:3px"><?=$counts['all']?></div><div style="font-size:11px;color:#64748B">Total</div></div>
  <div class="card-dark" style="padding:12px;text-align:center"><div style="font-size:22px;font-weight:800;color:#34D399;margin-bottom:3px"><?=$counts['confirmed']?></div><div style="font-size:11px;color:#64748B">Confirmed</div></div>
  <div class="card-dark" style="padding:12px;text-align:center"><div style="font-size:22px;font-weight:800;color:#FCD34D;margin-bottom:3px"><?=$counts['pending']?></div><div style="font-size:11px;color:#64748B">Pending</div></div>
  <div class="card-dark" style="padding:12px;text-align:center"><div style="font-size:22px;font-weight:800;color:#EF4444;margin-bottom:3px"><?=$counts['cancelled']?></div><div style="font-size:11px;color:#64748B">Cancelled</div></div>
  <div class="card-dark" style="padding:12px;text-align:center"><div style="font-size:22px;font-weight:800;color:#60A5FA;margin-bottom:3px"><?=$counts['today']?></div><div style="font-size:11px;color:#64748B">Today</div></div>
  </div>

  <!-- Filter tabs -->
  <div style="display:flex;gap:0;border-bottom:2px solid #2D3F56;margin-bottom:18px">
  <?php foreach(['all'=>'All','today'=>'Today','confirmed'=>'Confirmed','pending'=>'Pending','cancelled'=>'Cancelled'] as $k=>$v): ?>
   <a href="?filter=<?=$k?><?=$search?"&search=$search":''?>" style="padding:9px 18px;font-size:13px;text-decoration:none;font-weight:<?=$filter===$k?700:400?>;color:<?=$filter===$k?'#60A5FA':'#64748B'?>;border-bottom:<?=$filter===$k?'2.5px solid var(--b)':0?>;margin-bottom:<?=$filter===$k?'-2px':0?>"><?=$v?></a>
  <?php endforeach; ?>
  </div>

  <?php if($appts->num_rows===0): ?>
  <div class="card-dark" style="padding:48px;text-align:center"><div style="font-size:16px;font-weight:700;color:#F8FAFC">No appointments found</div></div>
  <?php else: ?>
  <div class="card-dark" style="overflow:hidden">
  <div style="padding:13px 17px;border-bottom:1px solid #2D3F56"><span style="font-size:14px;font-weight:700;color:#F8FAFC">Appointments (<?=$appts->num_rows?>)</span></div>
  <table style="width:100%;border-collapse:collapse">
   <thead><tr style="background:#111827">
   <th style="padding:9px 14px;text-align:left;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">ID</th>
   <th style="padding:9px 14px;text-align:left;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Patient</th>
   <th style="padding:9px 14px;text-align:left;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Doctor</th>
   <th style="padding:9px 14px;text-align:left;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Date &amp; Time</th>
   <th style="padding:9px 14px;text-align:left;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Status</th>
   <th style="padding:9px 14px;text-align:left;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Fee</th>
   </tr></thead>
   <tbody>
   <?php while($a=$appts->fetch_assoc()): ?>
   <tr style="border-top:1px solid #2D3F56">
    <td style="padding:11px 14px;font-size:12px;color:#60A5FA;font-weight:600">#MB-<?=str_pad($a['id'],4,'0',STR_PAD_LEFT)?></td>
    <td style="padding:11px 14px"><div style="font-size:13px;color:#D1D5DB;font-weight:600"><?=htmlspecialchars($a['pname'])?></div></td>
    <td style="padding:11px 14px"><div style="font-size:13px;color:#D1D5DB"><?=htmlspecialchars($a['dname'])?></div><div style="font-size:11px;color:#64748B"><?=htmlspecialchars($a['specialisation'])?></div></td>
    <td style="padding:11px 14px"><div style="font-size:13px;font-weight:600;color:#F8FAFC"><?=fmtDate($a['date'])?></div><div style="font-size:11px;color:#94A3B8"><?=fmt12($a['time'])?></div></td>
    <td style="padding:11px 14px"><?=statusBadge($a['status'])?></td>
    <td style="padding:11px 14px;font-size:13px;font-weight:600;color:#93C5FD">NPR <?=number_format($a['fee'])?></td>
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
