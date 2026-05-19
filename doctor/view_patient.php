<?php
// doctor/view_patient.php — View Patient Profile & Medical History
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requireDoctor();
$did = (int)$_SESSION['doctor_id'];
$pid = (int)($_GET['id'] ?? 0);

if (!$pid) { header('Location: appointments.php'); exit; }

// Security: Check if patient has any appointment with this doctor
$check = $conn->query("SELECT id FROM appointments WHERE doctor_id=$did AND patient_id=$pid LIMIT 1");
if ($check->num_rows===0) { die('Unauthorized: You can only view patients who have booked with you.'); }

// Fetch Patient Info
$p = $conn->query("SELECT * FROM patients WHERE id=$pid LIMIT 1")->fetch_assoc();
if (!$p) { die('Patient not found'); }

// Fetch Allergies
$allergies = $conn->query("SELECT * FROM patient_allergies WHERE patient_id=$pid ORDER BY created_at DESC");

// Fetch Reports
$reports = $conn->query("SELECT * FROM patient_reports WHERE patient_id=$pid ORDER BY uploaded_at DESC");

$page_title = 'Patient Profile - ' . htmlspecialchars($p['name']);
include '../includes/header.php';
?>
<div class="layout">
 <div class="sidebar">
  <div class="sb-profile"><div class="av av-green" style="width:44px;height:44px;font-size:14px;margin:0 auto 8px"><?=getInitials($_SESSION['doctor_name'])?></div><div style="font-size:13px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($_SESSION['doctor_name'])?></div></div>
  <div class="sb-nav">
   <a class="nav-item" href="dashboard.php"> &nbsp;Dashboard</a>
   <a class="nav-item active" href="appointments.php"> &nbsp;Appointments</a>
   <a class="nav-item" href="schedule.php"> &nbsp;My Schedule</a>
   <a class="nav-item" href="availability.php"> &nbsp;Availability</a>
   <a class="nav-item" href="notifications.php"> &nbsp;Notifications</a>
  </div>
  <div class="sb-bottom"><a class="nav-item" style="color:var(--tx3)" href="logout.php">↩ &nbsp;Log out</a></div>
 </div>
 <div class="main-content">
  <div class="topbar">
   <div class="flex items-center gap-3"><div class="logo-i">M</div><span class="logo-n">MediBook</span></div>
   <a href="appointments.php" class="btn-g">← Back to Appointments</a>
  </div>
  <div class="page-body">
   <h1 class="page-title">Patient Profile</h1>
   
   <div style="display:grid;grid-template-columns:300px 1fr;gap:24px;align-items:start">
    
    <!-- Profile Card -->
    <div class="card" style="padding:24px;text-align:center">
     <div class="av av-blue" style="width:80px;height:80px;font-size:24px;margin:0 auto 16px"><?=getInitials($p['name'])?></div>
     <h2 style="font-size:18px;font-weight:800;color:var(--tx);margin-bottom:4px"><?=htmlspecialchars($p['name'])?></h2>
     <div style="font-size:13px;color:var(--tx3);margin-bottom:20px"><?=htmlspecialchars($p['email'])?></div>
     
     <div style="text-align:left;background:var(--bg);padding:16px;border-radius:10px;border:1px solid var(--bd)">
      <div style="display:flex;justify-content:space-between;margin-bottom:12px">
       <span style="font-size:12px;color:var(--tx3);font-weight:600">Blood Type</span>
       <span style="font-size:13px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($p['blood_type']??'—')?></span>
      </div>
       <div style="display:flex;justify-content:space-between;margin-bottom:12px">
       <span style="font-size:12px;color:var(--tx3);font-weight:600">Age</span>
       <span style="font-size:13px;font-weight:700;color:var(--tx)">
        <?php 
         if (!empty($p['dob'])) {
          $birthDate = new DateTime($p['dob']);
          $today = new DateTime();
          echo $today->diff($birthDate)->y . ' Years';
         } else {
          echo '—';
         }
        ?>
       </span>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:0">
       <span style="font-size:12px;color:var(--tx3);font-weight:600">Phone</span>
       <span style="font-size:13px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($p['phone']??'—')?></span>
      </div>
     </div>
    </div>
    
    <!-- Medical History Side -->
    <div>
     <div class="card" style="padding:24px;margin-bottom:24px">
      <h3 style="font-size:15px;font-weight:800;color:var(--tx);margin-bottom:16px">Medication Allergies</h3>
      <?php if($allergies->num_rows===0): ?>
       <div style="font-size:13px;color:var(--tx3)">No recorded allergies.</div>
      <?php else: ?>
       <div style="display:flex;flex-direction:column;gap:12px">
       <?php while($al=$allergies->fetch_assoc()): ?>
        <div style="padding:12px 16px;background:var(--w);border:1.5px solid var(--bd2);border-radius:8px;display:flex;justify-content:space-between;align-items:center">
         <div>
          <div style="font-weight:700;color:var(--tx);font-size:13px"><?=htmlspecialchars($al['name'])?></div>
          <?php if($al['notes']): ?><div style="font-size:12px;color:var(--tx3);margin-top:4px"><?=htmlspecialchars($al['notes'])?></div><?php endif; ?>
         </div>
         <?php
           $col = 'var(--tx3)';
           if($al['severity']==='mild') $col='#059669';
           elseif($al['severity']==='moderate') $col='#D97706';
           elseif($al['severity']==='severe') $col='#DC2626';
         ?>
         <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:<?=$col?>"><?=htmlspecialchars($al['severity'])?></div>
        </div>
       <?php endwhile; ?>
       </div>
      <?php endif; ?>
     </div>

     <div class="card" style="padding:24px">
      <h3 style="font-size:15px;font-weight:800;color:var(--tx);margin-bottom:16px">Uploaded Pre-checkup Reports</h3>
      <?php if($reports->num_rows===0): ?>
       <div style="font-size:13px;color:var(--tx3)">No reports uploaded by patient.</div>
      <?php else: ?>
       <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px">
       <?php while($rp=$reports->fetch_assoc()): 
         $rawSize = $rp['file_size'];
         $sizeStr = $rawSize>1048576 ? round($rawSize/1048576,1).' MB' : round($rawSize/1024).' KB';
       ?>
        <a href="../patient/api_reports.php?action=download&id=<?=$rp['id']?>&pid=<?=$pid?>" target="_blank" style="text-decoration:none;display:flex;align-items:center;gap:12px;padding:12px;border:1.5px solid var(--bd2);border-radius:10px;background:var(--bg)">
         <div style="width:36px;height:36px;border-radius:6px;background:#FFE4E6;color:#E11D48;display:flex;align-items:center;justify-content:center">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 7V3.5L18.5 9H13zM9 13h6v2H9v-2zm0 4h6v2H9v-2z"/></svg>
         </div>
         <div style="overflow:hidden">
          <div style="font-size:12.5px;font-weight:600;color:var(--tx);white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?=htmlspecialchars($rp['file_name'])?>"><?=htmlspecialchars($rp['file_name'])?></div>
          <div style="font-size:11px;color:var(--tx3);margin-top:2px"><?=$sizeStr?></div>
         </div>
        </a>
       <?php endwhile; ?>
       </div>
      <?php endif; ?>
     </div>
    </div>
    
   </div>

  </div>
 </div>
</div>
</body></html>
