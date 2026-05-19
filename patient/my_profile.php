<?php
// patient/my_profile.php — Patient profile view & edit
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requirePatient();
$pid = (int)$_SESSION['patient_id'];

// Fetch patient data
$patient = $conn->query("SELECT * FROM patients WHERE id=$pid LIMIT 1")->fetch_assoc();

$success = '';
$error   = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = clean($conn, $_POST['name'] ?? '');
    $phone      = clean($conn, $_POST['phone'] ?? '');
    $blood_type = clean($conn, $_POST['blood_type'] ?? '');
    $dob        = clean($conn, $_POST['dob'] ?? '');
    $address    = clean($conn, $_POST['address'] ?? '');

    if (empty($name)) {
        $error = 'Name is required.';
    } else {
        $conn->query(
            "UPDATE patients SET 
             name='$name', phone='$phone', blood_type='$blood_type', 
             dob=" . ($dob ? "'$dob'" : "NULL") . ", address='$address'
             WHERE id=$pid"
        );
        $_SESSION['patient_name'] = $name;
        $success = 'Profile updated successfully.';
        // Re-fetch
        $patient = $conn->query("SELECT * FROM patients WHERE id=$pid LIMIT 1")->fetch_assoc();
    }
}

// Calculate age from DOB
$age = '';
if (!empty($patient['dob'])) {
    $birthDate = new DateTime($patient['dob']);
    $today = new DateTime();
    $age = $birthDate->diff($today)->y;
}

// Stats
$totalAppts    = $conn->query("SELECT COUNT(*) c FROM appointments WHERE patient_id=$pid")->fetch_assoc()['c'];
$completedAppts = $conn->query("SELECT COUNT(*) c FROM appointments WHERE patient_id=$pid AND status='completed'")->fetch_assoc()['c'];
$memberSince = date('M Y', strtotime($patient['created_at']));

$page_title = 'My Profile';
include '../includes/header.php';
$unread = countUnread($conn, $pid, 'patient');
?>
<style>
.profile-header{text-align:center;padding:32px 24px;border-bottom:1px solid var(--bd)}
.profile-av{width:80px;height:80px;font-size:28px;margin:0 auto 14px}
.profile-name{font-size:20px;font-weight:800;color:var(--tx);margin-bottom:2px}
.profile-sub{font-size:13px;color:var(--tx3)}
.profile-stats{display:flex;gap:0;margin-top:18px;justify-content:center}
.profile-stat{padding:0 24px;text-align:center;border-right:1px solid var(--bd)}
.profile-stat:last-child{border-right:none}
.profile-stat-num{font-size:20px;font-weight:800;color:var(--tx)}
.profile-stat-lbl{font-size:11px;color:var(--tx3);margin-top:2px}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.info-item{padding:16px;background:var(--bg);border-radius:11px;border:1px solid var(--bd)}
.info-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--tx3);margin-bottom:6px}
.info-value{font-size:14px;font-weight:600;color:var(--tx)}
.info-value.empty{color:var(--tx3);font-style:italic;font-weight:400}

/* Edit form */
.edit-form .form-group{margin-bottom:14px}
.edit-form label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--tx3);margin-bottom:6px}
.edit-form .form-control{background:var(--w);border:1.5px solid var(--bd2);border-radius:10px;height:44px;padding:0 14px;font-size:13px;color:var(--tx);font-family:'Outfit',sans-serif;outline:none;width:100%;transition:border-color .15s}
.edit-form .form-control:focus{border-color:var(--b);box-shadow:0 0 0 3px rgba(26,111,212,.12)}
.edit-form select.form-control{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%2394A3B8' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center}

/* Tabs */
.profile-tabs{display:flex;border-bottom:2px solid var(--bd);margin-bottom:24px}
.profile-tab{padding:10px 20px;font-size:13px;color:var(--tx3);cursor:pointer;font-weight:500;border-bottom:3px solid transparent;margin-bottom:-2px;transition:all .15s;background:none;border-top:none;border-left:none;border-right:none;font-family:'Outfit',sans-serif}
.profile-tab:hover{color:var(--tx)}
.profile-tab.active{border-bottom-color:var(--b);color:var(--b);font-weight:700}
.tab-content{display:none}
.tab-content.active{display:block}
</style>

<div class="layout">
 <div class="sidebar">
  <div class="sb-profile"><div class="av av-blue" style="width:44px;height:44px;font-size:14px;margin:0 auto 8px"><?=getInitials($_SESSION['patient_name'])?></div><div style="font-size:13px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($_SESSION['patient_name'])?></div></div>
  <div class="sb-nav">
   <a class="nav-item" href="dashboard.php"> &nbsp;Dashboard</a>
   <a class="nav-item" href="my_appointments.php"> &nbsp;My Appointments</a>
   <a class="nav-item" href="find_doctor.php"> &nbsp;Find a Doctor</a>
   <a class="nav-item" href="medical_history.php"> &nbsp;Medical Records</a>
   <a class="nav-item active" href="my_profile.php"> &nbsp;My Profile</a>
   <a class="nav-item" href="notifications.php"> &nbsp;Notifications<?php if($unread>0):?><span class="nav-badge"><?=$unread?></span><?php endif?></a>
  </div>
  <div class="sb-bottom"><a class="nav-item" style="color:var(--tx3)" href="logout.php">↩ &nbsp;Log out</a><a class="nav-item" style="color:var(--r);font-size:12px" href="delete_account.php">Delete account</a></div>
 </div>
 <div class="main-content">
  <div class="topbar"><div class="flex items-center gap-3"><div class="logo-i"><svg width="14" height="14" fill="none"><rect x="6" y="1" width="2" height="12" rx="1" fill="white"/><rect x="1" y="6" width="12" height="2" rx="1" fill="white"/></svg></div><span class="logo-n">MediBook</span></div><span style="font-size:13px;color:var(--b);font-weight:600">My Profile</span><div></div></div>
  <div class="page-body">

   <?php if($success): ?><div class="alert alert-success"><?=$success?></div><?php endif; ?>
   <?php if($error): ?><div class="alert alert-error"><?=$error?></div><?php endif; ?>

   <div style="display:grid;grid-template-columns:300px 1fr;gap:24px;align-items:start">

    <!-- ── Left: Profile Card ── -->
    <div class="card">
     <div class="profile-header">
      <div class="av av-blue profile-av"><?=getInitials($patient['name'])?></div>
      <div class="profile-name"><?=htmlspecialchars($patient['name'])?></div>
      <div class="profile-sub">
       Patient<?php if($age): ?> · <?=$age?> years old<?php endif; ?>
      </div>
      <div class="profile-stats">
       <div class="profile-stat">
        <div class="profile-stat-num"><?=$totalAppts?></div>
        <div class="profile-stat-lbl">Appointments</div>
       </div>
       <div class="profile-stat">
        <div class="profile-stat-num"><?=$completedAppts?></div>
        <div class="profile-stat-lbl">Completed</div>
       </div>
      </div>
     </div>
     <div style="padding:18px 24px">
      <div style="font-size:11px;color:var(--tx3)">Member since</div>
      <div style="font-size:13px;font-weight:600;color:var(--tx);margin-top:3px"><?=$memberSince?></div>
      <div style="margin-top:14px;font-size:11px;color:var(--tx3)">Email</div>
      <div style="font-size:13px;font-weight:600;color:var(--tx);margin-top:3px"><?=htmlspecialchars($patient['email'])?></div>
      <div style="margin-top:10px;font-size:10px;color:var(--t);font-weight:600;display:flex;align-items:center;gap:5px">
       <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
       Email verified
      </div>
     </div>
    </div>

    <!-- ── Right: Tabbed Content ── -->
    <div>
     <div class="profile-tabs">
      <button class="profile-tab active" onclick="switchTab('view')">Overview</button>
      <button class="profile-tab" onclick="switchTab('edit')">Edit Profile</button>
     </div>

     <!-- ── VIEW TAB ── -->
     <div class="tab-content active" id="tab-view">
      <div class="info-grid">
       <div class="info-item">
        <div class="info-label">Full Name</div>
        <div class="info-value"><?=htmlspecialchars($patient['name'])?></div>
       </div>
       <div class="info-item">
        <div class="info-label">Email Address</div>
        <div class="info-value"><?=htmlspecialchars($patient['email'])?></div>
       </div>
       <div class="info-item">
        <div class="info-label">Phone Number</div>
        <div class="info-value <?=empty($patient['phone'])?'empty':''?>"><?=htmlspecialchars($patient['phone'] ?: 'Not set')?></div>
       </div>
       <div class="info-item">
        <div class="info-label">Date of Birth</div>
        <div class="info-value <?=empty($patient['dob'])?'empty':''?>">
         <?=$patient['dob'] ? date('F j, Y', strtotime($patient['dob'])) : 'Not set'?>
         <?php if($age): ?><span style="font-size:12px;color:var(--tx3);font-weight:400">(<?=$age?> yrs)</span><?php endif; ?>
        </div>
       </div>
       <div class="info-item">
        <div class="info-label">Blood Type</div>
        <div class="info-value <?=empty($patient['blood_type'])?'empty':''?>">
         <?php if($patient['blood_type']): ?>
          <span style="background:var(--r2);color:var(--r);padding:3px 12px;border-radius:6px;font-size:13px;font-weight:700"><?=htmlspecialchars($patient['blood_type'])?></span>
         <?php else: ?>
          Not set
         <?php endif; ?>
        </div>
       </div>
       <div class="info-item">
        <div class="info-label">Address</div>
        <div class="info-value <?=empty($patient['address'])?'empty':''?>"><?=htmlspecialchars($patient['address'] ?: 'Not set')?></div>
       </div>
      </div>
     </div>

     <!-- ── EDIT TAB ── -->
     <div class="tab-content" id="tab-edit">
      <div class="card" style="padding:24px">
       <form method="POST" class="edit-form">
        <div class="info-grid">
         <div class="form-group">
          <label>Full name *</label>
          <input type="text" name="name" class="form-control" required value="<?=htmlspecialchars($patient['name'])?>">
         </div>
         <div class="form-group">
          <label>Email address</label>
          <input type="email" class="form-control" value="<?=htmlspecialchars($patient['email'])?>" disabled style="opacity:.6;cursor:not-allowed">
          <div style="font-size:10px;color:var(--tx3);margin-top:5px">Email cannot be changed</div>
         </div>
         <div class="form-group">
          <label>Phone number</label>
          <input type="tel" name="phone" class="form-control" placeholder="+977 98XXXXXXXX" value="<?=htmlspecialchars($patient['phone'] ?? '')?>">
         </div>
         <div class="form-group">
          <label>Date of birth</label>
          <input type="date" name="dob" class="form-control" value="<?=htmlspecialchars($patient['dob'] ?? '')?>">
         </div>
         <div class="form-group">
          <label>Blood type</label>
          <select name="blood_type" class="form-control">
           <option value="">Select blood type</option>
           <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
            <option value="<?=$bt?>" <?=($patient['blood_type']??'')===$bt?'selected':''?>><?=$bt?></option>
           <?php endforeach; ?>
          </select>
         </div>
         <div class="form-group">
          <label>Address</label>
          <input type="text" name="address" class="form-control" placeholder="City, District" value="<?=htmlspecialchars($patient['address'] ?? '')?>">
         </div>
        </div>
        <div style="display:flex;gap:12px;margin-top:20px;justify-content:flex-end">
         <button type="button" class="btn-g" onclick="switchTab('view')" style="padding:11px 24px">Cancel</button>
         <button type="submit" class="btn-p" style="padding:11px 28px">Save Changes</button>
        </div>
       </form>
      </div>
     </div>
    </div>

   </div>
  </div>
 </div>
</div>

<script>
function switchTab(tab) {
 document.querySelectorAll('.profile-tab').forEach(function(t) { t.classList.remove('active'); });
 document.querySelectorAll('.tab-content').forEach(function(t) { t.classList.remove('active'); });
 document.getElementById('tab-' + tab).classList.add('active');
 // Highlight the right tab button
 var btns = document.querySelectorAll('.profile-tab');
 if (tab === 'view') btns[0].classList.add('active');
 else btns[1].classList.add('active');
}
</script>
</body></html>
