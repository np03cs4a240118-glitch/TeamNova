<?php
// admin/manage_patients.php — DABS-18 (T18-1 to T18-3) + DABSTN-52 (delete inactive patients)
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requireAdmin();

// DABSTN-52: Handle patient deletion
$delete_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_patient_id'])) {
  $del_id = (int)$_POST['delete_patient_id'];
  if ($del_id > 0) {
    $conn->query("DELETE FROM patients WHERE id=$del_id");
    $delete_msg = 'success';
  }
}

define('INACTIVE_DAYS', 365); // DABSTN-52: threshold

$search = clean($conn, $_GET['search'] ?? '');
$where = '1';
if ($search) $where .= " AND (p.name LIKE '%$search%' OR p.email LIKE '%$search%' OR p.phone LIKE '%$search%')";

// T18-2: SELECT all patients with appointment count + last activity date
$patients = $conn->query(
  "SELECT p.*,
      (SELECT COUNT(*) FROM appointments WHERE patient_id=p.id) AS appt_count,
      COALESCE(
       (SELECT MAX(date) FROM appointments WHERE patient_id=p.id),
       p.created_at
      ) AS last_activity
   FROM patients p WHERE $where ORDER BY p.created_at DESC"
);
$total   = $conn->query("SELECT COUNT(*) c FROM patients")->fetch_assoc()['c'];
$new_month = $conn->query("SELECT COUNT(*) c FROM patients WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetch_assoc()['c'];
$inactive = $conn->query("SELECT COUNT(*) c FROM patients WHERE COALESCE((SELECT MAX(date) FROM appointments WHERE patient_id=patients.id), patients.created_at) < DATE_SUB(CURDATE(), INTERVAL ".INACTIVE_DAYS." DAY)")->fetch_assoc()['c'];

$page_title = 'Manage Patients';
include '../includes/header.php';
?>
<div class="layout">
 <div class="sidebar-dark">
 <div class="sb-profile-dark"><div class="flex items-center gap-3"><div class="logo-i" style="width:28px;height:28px"><svg width="12" height="12" fill="none"><rect x="5" y="1" width="2" height="10" rx="1" fill="white"/><rect x="1" y="5" width="10" height="2" rx="1" fill="white"/></svg></div><span style="font-size:14px;font-weight:800;color:#F8FAFC">Admin Panel</span></div></div>
 <div class="sb-nav">
  <a class="nav-item-dark" href="dashboard.php"> &nbsp;Dashboard</a>
  <a class="nav-item-dark" href="manage_doctors.php"> &nbsp;Doctors</a>
  <a class="nav-item-dark active" href="manage_patients.php"> &nbsp;Patients</a>
  <a class="nav-item-dark" href="manage_appointments.php"> &nbsp;Bookings</a>
  <a class="nav-item-dark" href="revenue.php"> &nbsp;Revenue</a>
  
 </div>
 <div class="sb-bottom-dark"><a class="nav-item-dark" href="logout.php">↩ &nbsp;Log out</a></div>
 </div>
 <div class="main-content">
 <div class="topbar-dark">
  <div class="flex items-center gap-3"><div class="logo-i">M</div><span style="font-size:16px;font-weight:800;color:#F8FAFC">MediBook Admin</span></div>
  <form method="GET" class="flex gap-2">
  <input type="text" name="search" class="form-control" style="background:#1A2840;border-color:#2D3F56;color:#F8FAFC;width:260px;height:38px" placeholder=" Search patients..." value="<?=htmlspecialchars($search)?>">
  <button type="submit" class="btn-p btn-sm" style="height:38px">Search</button>
  <?php if($search): ?><a href="manage_patients.php" class="btn-g btn-sm" style="height:38px;display:flex;align-items:center;border-color:#2D3F56;color:#94A3B8">Clear</a><?php endif; ?>
  </form>
  <div style="width:80px"></div>
 </div>
 <div class="admin-page-body">
  <h1 class="page-title" style="color:#F8FAFC">Manage Patients</h1>

  <?php if ($delete_msg === 'success'): ?>
   <div class="alert alert-success" style="margin-bottom:16px">Patient account deleted successfully.</div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:18px">
  <div class="card-dark" style="padding:14px;text-align:center"><div style="font-size:22px;font-weight:800;color:#F8FAFC;margin-bottom:4px"><?=$total?></div><div style="font-size:11px;color:#64748B">Total patients</div></div>
  <div class="card-dark" style="padding:14px;text-align:center"><div style="font-size:22px;font-weight:800;color:#34D399;margin-bottom:4px"><?=$new_month?></div><div style="font-size:11px;color:#64748B">New this month</div></div>
  <div class="card-dark" style="padding:14px;text-align:center"><div style="font-size:22px;font-weight:800;color:#60A5FA;margin-bottom:4px"><?=$conn->query("SELECT COUNT(*) c FROM appointments WHERE date>=CURDATE()")->fetch_assoc()['c']?></div><div style="font-size:11px;color:#64748B">Active bookings</div></div>
  <div class="card-dark" style="padding:14px;text-align:center"><div style="font-size:22px;font-weight:800;color:#F87171;margin-bottom:4px"><?=$inactive?></div><div style="font-size:11px;color:#64748B">Inactive (1yr+)</div></div>
  </div>

  <!-- T18-3 + DABSTN-52: Patient list with last activity and delete -->
  <?php if($patients->num_rows===0): ?>
  <div class="card-dark" style="padding:48px;text-align:center"><div style="font-size:16px;font-weight:700;color:#F8FAFC">No patients found</div></div>
  <?php else: ?>
  <div class="card-dark" style="overflow:hidden">
  <div style="padding:13px 17px;border-bottom:1px solid #2D3F56;display:flex;justify-content:space-between">
   <span style="font-size:14px;font-weight:700;color:#F8FAFC">All Patients (<?=$patients->num_rows?>)</span>
   <span style="font-size:12px;color:#64748B">Showing <?=$patients->num_rows?> results</span>
  </div>
  <table style="width:100%;border-collapse:collapse">
   <thead><tr style="background:#111827">
   <th style="padding:9px 14px;text-align:left;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">#</th>
   <th style="padding:9px 14px;text-align:left;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Patient</th>
   <th style="padding:9px 14px;text-align:left;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Phone</th>
   <th style="padding:9px 14px;text-align:left;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Reg. Date</th>
   <th style="padding:9px 14px;text-align:left;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Appts</th>
   <th style="padding:9px 14px;text-align:left;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Last Activity</th>
   <th style="padding:9px 14px;text-align:left;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Status</th>
   <th style="padding:9px 14px;text-align:left;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748B">Action</th>
   </tr></thead>
   <tbody>
   <?php $i=1; while($p=$patients->fetch_assoc()):
    $lastActivity = $p['last_activity'];
    $daysSince  = (int)floor((time() - strtotime($lastActivity)) / 86400);
    $isInactive  = $daysSince >= INACTIVE_DAYS;
   ?>
   <tr style="border-top:1px solid #2D3F56">
    <td style="padding:11px 14px;font-size:12px;color:#64748B"><?=$i++?></td>
    <td style="padding:11px 14px">
    <div class="flex items-center gap-3">
     <div class="av av-blue" style="width:34px;height:34px;font-size:12px"><?=getInitials($p['name'])?></div>
     <div>
     <div style="font-size:13px;font-weight:700;color:#F8FAFC"><?=htmlspecialchars($p['name'])?></div>
     <div style="font-size:11px;color:#64748B"><?=htmlspecialchars($p['email'])?></div>
     </div>
    </div>
    </td>
    <td style="padding:11px 14px;font-size:12px;color:#94A3B8"><?=htmlspecialchars($p['phone']??'—')?></td>
    <td style="padding:11px 14px;font-size:12px;color:#94A3B8"><?=date('M j, Y',strtotime($p['created_at']))?></td>
    <td style="padding:11px 14px;font-size:14px;font-weight:700;color:#60A5FA"><?=$p['appt_count']?></td>
    <td style="padding:11px 14px;font-size:12px;color:#94A3B8"><?=date('M j, Y', strtotime($lastActivity))?></td>
    <td style="padding:11px 14px">
    <?php if ($isInactive): ?>
     <span style="background:#3B1515;color:#F87171;border:1px solid #7f1d1d;border-radius:6px;padding:3px 9px;font-size:10px;font-weight:700">Inactive</span>
    <?php else: ?>
     <span style="background:#0f291f;color:#34D399;border:1px solid #065F46;border-radius:6px;padding:3px 9px;font-size:10px;font-weight:700">Active</span>
    <?php endif; ?>
    </td>
    <td style="padding:11px 14px">
    <button
     onclick="confirmDelete(<?=$p['id']?>, '<?=addslashes(htmlspecialchars($p['name']))?>')"
     style="background:#3B1515;color:#F87171;border:1px solid #7f1d1d;border-radius:7px;padding:5px 12px;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s"
     onmouseover="this.style.background='#DC2626';this.style.color='#fff'"
     onmouseout="this.style.background='#3B1515';this.style.color='#F87171'">
      Delete
    </button>
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

<!-- DABSTN-52: Delete confirmation modal -->
<div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9999;align-items:center;justify-content:center">
 <div style="background:#1A2840;border:1px solid #2D3F56;border-radius:16px;padding:32px;max-width:400px;width:90%;text-align:center;box-shadow:0 25px 60px rgba(0,0,0,.5)">
 <div style="width:56px;height:56px;background:#3B1515;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto 18px"></div>
 <h3 style="font-size:18px;font-weight:800;color:#F8FAFC;margin-bottom:8px">Delete Patient Account?</h3>
 <p style="font-size:13px;color:#94A3B8;line-height:1.6;margin-bottom:6px">You are about to permanently delete:</p>
 <p id="modalPatientName" style="font-size:14px;font-weight:700;color:#F87171;margin-bottom:20px"></p>
 <p style="font-size:12px;color:#64748B;margin-bottom:24px">This will also delete all their appointments. This action <strong style="color:#F87171">cannot be undone</strong>.</p>
 <form method="POST" id="deleteForm">
  <input type="hidden" name="delete_patient_id" id="deletePatientId">
  <div style="display:flex;gap:12px;justify-content:center">
  <button type="button" onclick="closeModal()" style="flex:1;padding:11px;background:#253347;color:#94A3B8;border:1px solid #2D3F56;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer">Cancel</button>
  <button type="submit" style="flex:1;padding:11px;background:#DC2626;color:#fff;border:none;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer">Yes, Delete</button>
  </div>
 </form>
 </div>
</div>

<script>
function confirmDelete(id, name) {
 document.getElementById('deletePatientId').value = id;
 document.getElementById('modalPatientName').textContent = name;
 document.getElementById('deleteModal').style.display = 'flex';
}
function closeModal() {
 document.getElementById('deleteModal').style.display = 'none';
}
// Close on backdrop click
document.getElementById('deleteModal').addEventListener('click', function(e) {
 if (e.target === this) closeModal();
});
</script>
</body></html>
