<?php
// doctor/appointments.php — DABS-11 (T11-1..T11-4) + DABS-14 (T14-1..T14-5)
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requireDoctor();
$did   = (int)$_SESSION['doctor_id'];
$unread= countUnread($conn,$did,'doctor');

// T14-2/T14-3: Cancel appointment
if (isset($_GET['cancel'])) {
    $aid=(int)$_GET['cancel'];
    // T14-2: Verify belongs to doctor
    $check=$conn->query("SELECT id,patient_id,date,time FROM appointments WHERE id=$aid AND doctor_id=$did LIMIT 1")->fetch_assoc();
    if ($check) {
        $conn->query("UPDATE appointments SET status='cancelled' WHERE id=$aid AND doctor_id=$did");
        // T14-4: Notify patient
        insertNotification($conn,$check['patient_id'],'patient',"Dr. {$_SESSION['doctor_name']} cancelled your appointment on ".fmtDate($check['date'])." at ".fmt12($check['time']).".");
        header('Location: appointments.php?msg=Appointment+cancelled'); exit;
    }
}
// Confirm appointment
if (isset($_GET['confirm'])) {
    $aid=(int)$_GET['confirm'];
    $conn->query("UPDATE appointments SET status='confirmed' WHERE id=$aid AND doctor_id=$did");
    header('Location: appointments.php?msg=Appointment+confirmed'); exit;
}
// Submit Report and Complete
if (isset($_POST['submit_report'])) {
    $aid = (int)$_POST['appointment_id'];
    $report = clean($conn, $_POST['doctor_report']);
    $check = $conn->query("SELECT id, patient_id FROM appointments WHERE id=$aid AND doctor_id=$did LIMIT 1")->fetch_assoc();
    if ($check) {
        $patient_id = $check['patient_id'];
        $doctor_file = null;
        // Handle optional file upload
        if (!empty($_FILES['report_file']['tmp_name'])) {
            $f = $_FILES['report_file'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf','jpg','jpeg','png']) && $f['size'] <= 5*1024*1024) {
                $dir = '../uploads/reports/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fname = time() . '_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', basename($f['name']));
                if (move_uploaded_file($f['tmp_name'], $dir . $fname)) {
                    $doctor_file = $fname;
                }
            }
        }

        if ($doctor_file !== null) {
            $stmt = $conn->prepare("UPDATE appointments SET status='completed', doctor_report=?, doctor_file=? WHERE id=?");
            $stmt->bind_param("ssi", $report, $doctor_file, $aid);
        } else {
            $stmt = $conn->prepare("UPDATE appointments SET status='completed', doctor_report=? WHERE id=?");
            $stmt->bind_param("si", $report, $aid);
        }
        $stmt->execute();

        insertNotification($conn, $patient_id, 'patient', "Dr. {$_SESSION['doctor_name']} completed your appointment and added a prescription/report.");
        header('Location: appointments.php?msg=Report+added+and+appointment+completed'); exit;
    }
}

$filter=$_GET['filter']??'all';
$where="a.doctor_id=$did";
if ($filter==='upcoming') $where.=" AND a.date>=CURDATE() AND a.status IN ('confirmed','pending')";
elseif ($filter==='pending') $where.=" AND a.status='pending'";
elseif ($filter==='today')   $where.=" AND a.date=CURDATE()";

$appts=$conn->query("SELECT a.*,p.name pname,p.phone FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE $where ORDER BY a.date DESC,a.time ASC");

$page_title='My Appointments — Doctor'; include '../includes/header.php';
?>
<div class="layout">
 <div class="sidebar">
  <div class="sb-profile" style="text-align:center"><div style="margin:0 auto 8px;display:flex;justify-content:center"><?= doctorAvatar(['name'=>$_SESSION['doctor_name']??'','profile_image'=>$_SESSION['doctor_image']??null], 44, 'av-teal') ?></div><div style="font-size:13px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($_SESSION['doctor_name'])?></div></div>
  <div class="sb-nav">
   <a class="nav-item" href="dashboard.php"> &nbsp;Dashboard</a>
   <a class="nav-item active" href="appointments.php"> &nbsp;Appointments</a>
   <a class="nav-item" href="schedule.php"> &nbsp;My Schedule</a>
   <a class="nav-item" href="availability.php"> &nbsp;Availability</a>
   <a class="nav-item" href="notifications.php"> &nbsp;Notifications<?php if($unread>0):?><span class="nav-badge"><?=$unread?></span><?php endif?></a>
  </div>
  <div class="sb-bottom"><a class="nav-item" style="color:var(--tx3)" href="logout.php">↩ &nbsp;Log out</a></div>
 </div>
 <div class="main-content">
  <div class="topbar"><div class="flex items-center gap-3"><div class="logo-i">M</div><span class="logo-n">MediBook</span></div><div></div><div></div></div>
  <div class="page-body">
   <h1 class="page-title">My Appointments</h1>
   <?php if(!empty($_GET['msg'])): ?><div class="alert alert-success"> <?=htmlspecialchars($_GET['msg'])?></div><?php endif; ?>
   <!-- Filter tabs -->
   <div style="display:flex;gap:0;border-bottom:2px solid var(--bd);margin-bottom:20px">
    <?php foreach(['all'=>'All','today'=>'Today','upcoming'=>'Upcoming','pending'=>'Pending'] as $k=>$v): ?>
     <a href="?filter=<?=$k?>" style="padding:9px 18px;font-size:13px;text-decoration:none;font-weight:<?=$filter===$k?700:400?>;color:<?=$filter===$k?'var(--b)':'var(--tx3)'?>;border-bottom:<?=$filter===$k?'2.5px solid var(--b)':0?>;margin-bottom:<?=$filter===$k?'-2px':0?>"><?=$v?></a>
    <?php endforeach; ?>
   </div>
   <?php if($appts->num_rows===0): ?>
    <div class="card" style="padding:48px;text-align:center"><div style="font-size:40px;margin-bottom:12px">📅</div><div style="font-size:16px;font-weight:700;color:var(--tx)">No appointments found</div></div>
   <?php else: ?>
   <div class="card" style="overflow:hidden">
    <table class="table">
     <thead><tr><th>Time</th><th>Patient</th><th>Date</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead>
     <tbody>
     <?php while($a=$appts->fetch_assoc()): ?>
      <tr style="<?=$a['status']==='pending'?'background:var(--am2)':''?>">
       <td style="font-weight:700;color:<?=$a['status']==='pending'?'var(--am)':'var(--b2)'?>"><?=fmt12($a['time'])?></td>
        <td><a href="view_patient.php?id=<?=$a['patient_id']?>" style="text-decoration:none;color:inherit"><div class="flex items-center gap-2"><div class="av av-blue" style="width:32px;height:32px;font-size:11px"><?=getInitials($a['pname'])?></div><div><div style="font-weight:600;color:var(--tx)"><?=htmlspecialchars($a['pname'])?></div><div style="font-size:11px;color:var(--tx3)"><?=htmlspecialchars($a['phone']??'')?></div></div></div></a></td>
       <td><?=fmtDate($a['date'])?></td>
       <td style="font-size:12px;color:var(--tx3);max-width:150px"><?=htmlspecialchars(substr($a['reason']??'—',0,50))?></td>
       <td><?=statusBadge($a['status'])?></td>
       <td>
        <div class="flex gap-2">
         <?php if($a['status']==='pending'): ?>
          <a href="?confirm=<?=$a['id']?>&filter=<?=$filter?>" class="btn-t btn-xs" style="background:linear-gradient(135deg,var(--t),#065F46)">Confirm</a>
         <?php endif; ?>
        <?php if($a['status']==='confirmed'): ?>
 <a href="consultation_note.php?aid=<?=$a['id']?>" class="btn-t btn-xs">Add Note</a>
<?php endif; ?>
         <?php if(in_array($a['status'],['confirmed','pending']) && $a['date']>=date('Y-m-d')): ?>
          <a href="?cancel=<?=$a['id']?>&filter=<?=$filter?>" class="btn-r-out btn-xs" onclick="return confirm('Cancel this appointment?')">Cancel</a>
         <?php endif; ?>
        <?php if($a['status']==='completed'): ?>
 <a href="consultation_note.php?aid=<?=$a['id']?>" class="btn-g btn-xs">View / Edit Note</a>
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

<!-- Add Report Modal -->
<div id="reportModal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:99;align-items:center;justify-content:center;padding:20px">
 <div class="card" style="width:100%;max-width:650px;padding:28px;border-radius:16px;box-shadow:0 20px 40px rgba(0,0,0,0.1)">
  <h3 style="font-size:20px;font-weight:800;color:var(--tx);margin-bottom:8px">Complete Appointment</h3>
  <div style="font-size:14px;color:var(--tx3);margin-bottom:20px">Write a prescription, notes, or report for <span id="rm-pname" style="font-weight:700;color:var(--tx)"></span>. This will be visible to the patient.</div>
  <form method="POST" enctype="multipart/form-data">
   <input type="hidden" name="appointment_id" id="rm-aid">
   <textarea name="doctor_report" id="rm-report" class="form-control" style="width:100%;height:280px;padding:16px;font-family:'Outfit',sans-serif;font-size:14px;border-radius:10px;border:1.5px solid var(--bd2);resize:vertical;margin-bottom:16px;line-height:1.6" required placeholder="Write your clinical notes or prescription here..."></textarea>
   
   <div style="margin-bottom:24px;background:var(--bg);padding:16px;border-radius:10px;border:1px solid var(--bd)">
    <div style="font-size:12px;font-weight:700;color:var(--tx);margin-bottom:8px">Attach File / Scan (Optional)</div>
    <input type="file" name="report_file" class="form-control" accept=".pdf,.png,.jpg,.jpeg" style="padding:10px;height:auto;font-size:13px;background:var(--w)">
    <div style="font-size:11.5px;color:var(--tx3);margin-top:6px">PDF, JPG, or PNG (Max 5MB). Once submitted, this upload will be automatically linked to the patient's medical history.</div>
   </div>

   <div style="display:flex;justify-content:flex-end;gap:12px">
    <button type="button" class="btn-g" onclick="document.getElementById('reportModal').style.display='none'" style="padding:11px 24px">Cancel</button>
    <button type="submit" name="submit_report" class="btn-t" style="padding:11px 28px">Submit & Complete</button>
   </div>
  </form>
 </div>
</div>

<!-- View Report Modal -->
<div id="viewReportModal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:99;align-items:center;justify-content:center;padding:20px">
 <div class="card" style="width:100%;max-width:500px;padding:24px;border-radius:16px;box-shadow:0 20px 40px rgba(0,0,0,0.1)">
  <h3 style="font-size:18px;font-weight:800;color:var(--tx);margin-bottom:16px">Prescription / Report</h3>
  <div id="vr-content" style="font-size:13px;line-height:1.6;color:var(--tx2);background:var(--bg);padding:16px;border-radius:10px;border:1px solid var(--bd);max-height:300px;overflow-y:auto;white-space:pre-wrap"></div>
  <div style="display:flex;justify-content:flex-end;margin-top:20px">
   <button type="button" class="btn-g" onclick="document.getElementById('viewReportModal').style.display='none'">Close</button>
  </div>
 </div>
</div>

<script>
function openReportModal(aid, pname, existingText = '') {
 document.getElementById('rm-aid').value = aid;
 document.getElementById('rm-pname').innerText = pname;
 document.getElementById('rm-report').value = existingText;
 document.getElementById('reportModal').style.display = 'flex';
}
function viewReport(reportText) {
 document.getElementById('vr-content').innerText = reportText;
 document.getElementById('viewReportModal').style.display = 'flex';
}
</script>

</body></html>