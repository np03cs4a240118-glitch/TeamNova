<?php
// admin/manage_doctors.php — DABS-16 (approve), DABS-17 (view all), DABS-19 (delete)
// Tasks: T16-1..T16-5, T17-1..T17-4, T19-1..T19-4
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requireAdmin();

$msg = '';

// T16-4: Approve doctor
if (isset($_GET['approve'])) {
  $id = (int)$_GET['approve'];
  $conn->query("UPDATE doctors SET status='approved' WHERE id=$id");
  $dr = $conn->query("SELECT id,name FROM doctors WHERE id=$id LIMIT 1")->fetch_assoc();
  if ($dr) {
    // T16-5: Notify doctor
    insertNotification($conn, $dr['id'], 'doctor',
      " Your MediBook doctor account has been approved! You can now log in and accept appointments.");
  }
  $msg = 'Doctor approved successfully.';
}

// Suspend
if (isset($_GET['suspend'])) {
  $id = (int)$_GET['suspend'];
  $conn->query("UPDATE doctors SET status='suspended' WHERE id=$id");
  $msg = 'Doctor suspended.';
}

// T19-3: Delete doctor
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  $conn->query("DELETE FROM appointments WHERE doctor_id=$id");
  $conn->query("DELETE FROM notifications WHERE user_id=$id AND user_type='doctor'");
  $conn->query("DELETE FROM doctors WHERE id=$id");
  // T19-4: Redirect with success
  header('Location: manage_doctors.php?msg=Doctor+deleted+successfully'); exit;
}

// T17-4: Filter
$filter = $_GET['filter'] ?? 'all';
$search = clean($conn, $_GET['search'] ?? '');
$where = '1';
if ($filter === 'pending')  $where = "status='pending'";
elseif ($filter === 'approved') $where = "status='approved'";
elseif ($filter === 'suspended') $where = "status='suspended'";
if ($search) $where .= " AND (name LIKE '%$search%' OR specialisation LIKE '%$search%' OR email LIKE '%$search%')";

// T17-2: SELECT all doctors
$doctors = $conn->query("SELECT d.*, (SELECT COUNT(*) FROM appointments WHERE doctor_id=d.id) AS appt_count FROM doctors d WHERE $where ORDER BY created_at DESC");

$total  = $conn->query("SELECT COUNT(*) c FROM doctors")->fetch_assoc()['c'];
$pending = $conn->query("SELECT COUNT(*) c FROM doctors WHERE status='pending'")->fetch_assoc()['c'];
$approved= $conn->query("SELECT COUNT(*) c FROM doctors WHERE status='approved'")->fetch_assoc()['c'];

$page_title = 'Manage Doctors';
include '../includes/header.php';
?>
<div class="layout">
 <div class="sidebar-dark">
 <div class="sb-profile-dark"><div class="flex items-center gap-3"><div class="logo-i" style="width:28px;height:28px"><svg width="12" height="12" fill="none"><rect x="5" y="1" width="2" height="10" rx="1" fill="white"/><rect x="1" y="5" width="10" height="2" rx="1" fill="white"/></svg></div><span style="font-size:14px;font-weight:800;color:#F8FAFC">Admin Panel</span></div></div>
 <div class="sb-nav">
  <a class="nav-item-dark" href="dashboard.php"> &nbsp;Dashboard</a>
  <a class="nav-item-dark active" href="manage_doctors.php"> &nbsp;Doctors</a>
  <a class="nav-item-dark" href="manage_patients.php"> &nbsp;Patients</a>
  <a class="nav-item-dark" href="manage_appointments.php"> &nbsp;Bookings</a>
  <a class="nav-item-dark" href="revenue.php"> &nbsp;Revenue</a>
 </div>
 <div class="sb-bottom-dark"><a class="nav-item-dark" href="logout.php">↩ &nbsp;Log out</a></div>
 </div>
 <div class="main-content">
 <div class="topbar-dark">
  <div class="flex items-center gap-3"><div class="logo-i">M</div><span style="font-size:16px;font-weight:800;color:#F8FAFC">MediBook Admin</span></div>
  <form method="GET" action="" style="display:flex;gap:9px">
  <input type="text" name="search" class="form-control" style="background:#1A2840;border-color:#2D3F56;color:#F8FAFC;width:240px;height:38px" placeholder=" Search doctors..." value="<?=htmlspecialchars($search)?>">
  <input type="hidden" name="filter" value="<?=htmlspecialchars($filter)?>">
  <button type="submit" class="btn-p btn-sm" style="height:38px">Search</button>
  </form>
  <a href="manage_doctors.php?add=1" class="btn-p btn-sm">+ Add Doctor</a>
 </div>
 <div class="admin-page-body">
  <h1 class="page-title" style="color:#F8FAFC">Manage Doctors</h1>

  <?php if(!empty($_GET['msg'])||$msg): ?><div class="alert alert-success"> <?=htmlspecialchars($_GET['msg']??$msg)?></div><?php endif; ?>

  <!-- Stats -->
  <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:18px">
  <div class="card-dark" style="padding:14px;text-align:center"><div style="font-size:22px;font-weight:800;color:#F8FAFC;margin-bottom:4px"><?=$total?></div><div style="font-size:11px;color:#64748B">Total</div></div>
  <div class="card-dark" style="padding:14px;text-align:center"><div style="font-size:22px;font-weight:800;color:#34D399;margin-bottom:4px"><?=$approved?></div><div style="font-size:11px;color:#64748B">Approved</div></div>
  <div class="card-dark" style="padding:14px;text-align:center"><div style="font-size:22px;font-weight:800;color:#FCD34D;margin-bottom:4px"><?=$pending?></div><div style="font-size:11px;color:#64748B">Pending</div></div>
  <div class="card-dark" style="padding:14px;text-align:center"><div style="font-size:22px;font-weight:800;color:#EF4444;margin-bottom:4px"><?=$conn->query("SELECT COUNT(*) c FROM doctors WHERE status='suspended'")->fetch_assoc()['c']?></div><div style="font-size:11px;color:#64748B">Suspended</div></div>
  </div>

  <!-- T17-4: Filter tabs -->
  <div style="display:flex;gap:0;border-bottom:2px solid #2D3F56;margin-bottom:18px">
  <?php foreach(['all'=>'All','pending'=>'Pending','approved'=>'Approved','suspended'=>'Suspended'] as $k=>$v): ?>
   <a href="?filter=<?=$k?><?=$search?"&search=$search":''?>" style="padding:9px 18px;font-size:13px;text-decoration:none;font-weight:<?=$filter===$k?700:400?>;color:<?=$filter===$k?'#60A5FA':'#64748B'?>;border-bottom:<?=$filter===$k?'2.5px solid var(--b)':0?>;margin-bottom:<?=$filter===$k?'-2px':0?>"><?=$v?></a>
  <?php endforeach; ?>
  </div>

  <!-- T17-3: Doctor list table -->
  <?php if($doctors->num_rows===0): ?>
  <div class="card-dark" style="padding:48px;text-align:center"><div style="font-size:16px;font-weight:700;color:#F8FAFC">No doctors found</div></div>
  <?php else: ?>
  <div class="card-dark" style="overflow:hidden">
  <div style="padding:13px 17px;border-bottom:1px solid #2D3F56;display:flex;justify-content:space-between">
   <span style="font-size:14px;font-weight:700;color:#F8FAFC">Doctors (<?=$doctors->num_rows?>)</span>
  </div>
  <table style="width:100%;border-collapse:collapse">
   <thead><tr style="background:#111827">
   <th style="padding:9px 14px;text-align:left;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Doctor</th>
   <th style="padding:9px 14px;text-align:left;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Specialisation</th>
   <th style="padding:9px 14px;text-align:left;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Clinic</th>
   <th style="padding:9px 14px;text-align:left;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Appointments</th>
   <th style="padding:9px 14px;text-align:left;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Status</th>
   <th style="padding:9px 14px;text-align:left;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Registered</th>
   <th style="padding:9px 14px;text-align:left;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Actions</th>
   </tr></thead>
   <tbody>
   <?php while($d=$doctors->fetch_assoc()): ?>
   <tr style="border-top:1px solid #2D3F56">
    <td style="padding:12px 14px">
    <div class="flex items-center gap-3">
     <div class="av av-blue" style="width:36px;height:36px;font-size:12px"><?=getInitials($d['name'])?></div>
     <div>
     <div style="font-size:13px;font-weight:700;color:#F8FAFC"><?=htmlspecialchars($d['name'])?></div>
     <div style="font-size:11px;color:#64748B"><?=htmlspecialchars($d['email'])?></div>
     </div>
    </div>
    </td>
    <td style="padding:12px 14px;font-size:13px;color:#D1D5DB"><?=htmlspecialchars($d['specialisation'])?></td>
    <td style="padding:12px 14px;font-size:12px;color:#94A3B8"><?=htmlspecialchars($d['clinic_name']??'—')?></td>
    <td style="padding:12px 14px;font-size:13px;font-weight:700;color:#60A5FA"><?=$d['appt_count']?></td>
    <td style="padding:12px 14px"><?=statusBadge($d['status'])?></td>
    <td style="padding:12px 14px;font-size:12px;color:#94A3B8"><?=date('M j, Y',strtotime($d['created_at']))?></td>
    <td style="padding:12px 14px">
    <div class="flex gap-2" style="flex-wrap:wrap">
     <?php if($d['status']==='pending'): ?>
     <a href="?approve=<?=$d['id']?>&filter=<?=$filter?>" style="background:#059669;color:#fff;border:none;border-radius:7px;padding:5px 12px;font-size:11px;font-weight:700;text-decoration:none">Approve</a>
     <a href="?reject=<?=$d['id']?>&filter=<?=$filter?>" style="background:transparent;color:#EF4444;border:1px solid #EF4444;border-radius:7px;padding:5px 11px;font-size:11px;text-decoration:none" onclick="return confirm('Reject this doctor?')">Reject</a>
     <?php elseif($d['status']==='approved'): ?>
     <a href="?suspend=<?=$d['id']?>&filter=<?=$filter?>" style="background:transparent;color:#FCD34D;border:1px solid #F59E0B;border-radius:7px;padding:5px 11px;font-size:11px;text-decoration:none" onclick="return confirm('Suspend this doctor?')">Suspend</a>
     <?php elseif($d['status']==='suspended'): ?>
     <a href="?approve=<?=$d['id']?>&filter=<?=$filter?>" style="background:#059669;color:#fff;border:none;border-radius:7px;padding:5px 12px;font-size:11px;font-weight:700;text-decoration:none">Reactivate</a>
     <?php endif; ?>
     <!-- T19-1: Delete button -->
     <a href="?delete=<?=$d['id']?>&filter=<?=$filter?>" style="background:transparent;color:#EF4444;border:1px solid #EF4444;border-radius:7px;padding:5px 10px;font-size:11px;text-decoration:none" onclick="return confirm('Delete this doctor permanently? All their appointments will also be deleted.')">Delete</a>
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
