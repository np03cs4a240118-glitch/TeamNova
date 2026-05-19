<?php
// patient/consultation_note.php — Read-only consultation note view + Print/Download
// Patient can only see notes for THEIR OWN appointments (a.patient_id = $pid).
// "Download as PDF" uses the browser's print dialog → Save as PDF. No PHP library needed.
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requirePatient();

$pid = (int)$_SESSION['patient_id'];
$aid = (int)($_GET['aid'] ?? 0);

// ── Authorization: appointment must belong to this patient ──
$row = $conn->query("
    SELECT a.id AS appointment_id, a.date AS appt_date, a.time AS appt_time, a.reason, a.status,
           p.id AS patient_id, p.name AS patient_name, p.dob AS patient_dob, p.phone AS patient_phone, p.blood_type,
           d.name AS doctor_name, d.specialisation, d.qualification,
           d.clinic_name, d.clinic_address, d.clinic_phone,
           cn.id AS note_id, cn.chief_complaint, cn.diagnosis, cn.medications,
           cn.investigations, cn.advice, cn.follow_up_date, cn.created_at AS note_created,
           cn.updated_at AS note_updated
      FROM appointments a
      JOIN patients p ON p.id = a.patient_id
      JOIN doctors  d ON d.id = a.doctor_id
 LEFT JOIN consultation_notes cn ON cn.appointment_id = a.id
     WHERE a.id = $aid AND a.patient_id = $pid
     LIMIT 1
")->fetch_assoc();

if (!$row) {
    header('Location: my_appointments.php');
    exit;
}

$has_note = !empty($row['note_id']);

// Calculate patient age from DOB
$age = '—';
if (!empty($row['patient_dob']) && $row['patient_dob'] !== '0000-00-00') {
    try {
        $dob_dt = new DateTime($row['patient_dob']);
        $age = $dob_dt->diff(new DateTime('today'))->y . ' yrs';
    } catch (Exception $e) { /* leave as — */ }
}

$unread = countUnread($conn, $pid, 'patient');
$page_title = 'Consultation Note';
include '../includes/header.php';
?>
<style>
/* Print-only styles: hide app chrome, format note as a clean document */
@media print {
    body { background:#fff !important; }
    .sidebar, .topbar, .no-print { display:none !important; }
    .main-content { margin-left:0 !important; }
    .page-body { padding:0 !important; background:#fff !important; }
    .printable-note { box-shadow:none !important; border:none !important; padding:24px !important; }
    .pn-section { page-break-inside: avoid; }
    @page { margin: 14mm 14mm 14mm 14mm; }
}
.pn-h { font-size:10.5px; letter-spacing:1.2px; text-transform:uppercase; color:var(--tx3); font-weight:700; margin-bottom:6px }
.pn-body { font-size:13.5px; color:var(--tx); line-height:1.6; white-space:pre-wrap }
.pn-section { margin-bottom:18px; padding-bottom:14px; border-bottom:1px dashed var(--bd) }
.pn-section:last-of-type { border-bottom:0 }
</style>

<div class="layout">
 <div class="sidebar no-print">
  <div class="sb-profile">
    <div class="av av-blue" style="width:44px;height:44px;font-size:14px;margin:0 auto 8px"><?=getInitials($_SESSION['patient_name'])?></div>
    <div style="font-size:13px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($_SESSION['patient_name'])?></div>
  </div>
  <div class="sb-nav">
   <a class="nav-item" href="dashboard.php"> &nbsp;Dashboard</a>
   <a class="nav-item active" href="my_appointments.php"> &nbsp;My Appointments</a>
   <a class="nav-item" href="find_doctor.php"> &nbsp;Find a Doctor</a>
   <a class="nav-item" href="medical_history.php"> &nbsp;Medical Records</a>
   <a class="nav-item" href="my_profile.php"> &nbsp;My Profile</a>
   <a class="nav-item" href="settings.php"> &nbsp;Settings</a>
   <a class="nav-item" href="notifications.php"> &nbsp;Notifications<?php if($unread>0):?><span class="nav-badge"><?=$unread?></span><?php endif?></a>
  </div>
  <div class="sb-bottom"><a class="nav-item" style="color:var(--tx3)" href="logout.php">↩ &nbsp;Log out</a></div>
 </div>

 <div class="main-content">
  <div class="topbar no-print">
   <div class="flex items-center gap-3"><div class="logo-i">M</div><span class="logo-n">MediBook</span></div>
   <a href="view_appointment.php?id=<?=$aid?>" style="font-size:13px;color:var(--b);font-weight:500;text-decoration:none">← Back to Appointment</a>
   <div>
     <?php if ($has_note): ?>
       <button onclick="window.print()" class="btn-t" style="padding:9px 18px;display:inline-flex;align-items:center;gap:6px">
         <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
         Download as PDF
       </button>
     <?php endif; ?>
   </div>
  </div>

  <div class="page-body">

   <?php if (!$has_note): ?>
    <div class="card" style="padding:48px;text-align:center">
     <div style="font-size:40px;margin-bottom:12px">📋</div>
     <div style="font-size:16px;font-weight:700;color:var(--tx);margin-bottom:6px">No consultation note yet</div>
     <div style="font-size:13px;color:var(--tx3)">Your doctor hasn't added a consultation note for this appointment.</div>
     <a href="view_appointment.php?id=<?=$aid?>" class="btn-g" style="margin-top:16px;display:inline-block">← Back to appointment</a>
    </div>
   <?php else: ?>

    <!-- Printable note card -->
    <div class="card printable-note" style="padding:32px;max-width:780px;margin:0 auto">

     <!-- Letterhead -->
     <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid var(--b);padding-bottom:14px;margin-bottom:20px">
      <div>
       <div style="font-size:22px;font-weight:800;color:var(--b);letter-spacing:-0.3px">MediBook</div>
       <div style="font-size:11px;color:var(--tx3);margin-top:2px">Doctor Appointment &amp; Booking System</div>
      </div>
      <div style="text-align:right">
       <div style="font-size:14px;font-weight:800;color:var(--tx)">Consultation Note</div>
       <div style="font-size:11px;color:var(--tx3);margin-top:2px">Ref: #MB-<?=str_pad($row['appointment_id'],4,'0',STR_PAD_LEFT)?></div>
      </div>
     </div>

     <!-- Doctor & Patient blocks -->
     <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:22px">
      <div>
       <div class="pn-h">Doctor</div>
       <div style="font-size:14px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($row['doctor_name'])?></div>
       <div style="font-size:12px;color:var(--tx2);margin-top:2px"><?=htmlspecialchars($row['specialisation'])?><?php if(!empty($row['qualification'])): ?> · <?=htmlspecialchars($row['qualification'])?><?php endif; ?></div>
       <?php if (!empty($row['clinic_name'])): ?>
         <div style="font-size:11.5px;color:var(--tx3);margin-top:4px"><?=htmlspecialchars($row['clinic_name'])?></div>
       <?php endif; ?>
       <?php if (!empty($row['clinic_address'])): ?>
         <div style="font-size:11.5px;color:var(--tx3)"><?=htmlspecialchars($row['clinic_address'])?></div>
       <?php endif; ?>
      </div>
      <div>
       <div class="pn-h">Patient</div>
       <div style="font-size:14px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($row['patient_name'])?></div>
       <div style="font-size:12px;color:var(--tx2);margin-top:2px">
         Age: <?=htmlspecialchars($age)?>
         <?php if (!empty($row['blood_type'])): ?> · Blood Group: <?=htmlspecialchars($row['blood_type'])?><?php endif; ?>
       </div>
       <?php if (!empty($row['patient_phone'])): ?>
         <div style="font-size:11.5px;color:var(--tx3);margin-top:4px">Phone: <?=htmlspecialchars($row['patient_phone'])?></div>
       <?php endif; ?>
      </div>
     </div>

     <!-- Visit details -->
     <div style="background:var(--bg);padding:12px 16px;border-radius:8px;margin-bottom:22px;display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
      <div>
       <div style="font-size:10px;color:var(--tx3);text-transform:uppercase;font-weight:700">Date of visit</div>
       <div style="font-size:13px;font-weight:600;color:var(--tx)"><?=fmtDate($row['appt_date'])?></div>
      </div>
      <div>
       <div style="font-size:10px;color:var(--tx3);text-transform:uppercase;font-weight:700">Time</div>
       <div style="font-size:13px;font-weight:600;color:var(--tx)"><?=fmt12($row['appt_time'])?></div>
      </div>
      <div>
       <div style="font-size:10px;color:var(--tx3);text-transform:uppercase;font-weight:700">Note issued</div>
       <div style="font-size:13px;font-weight:600;color:var(--tx)"><?=date('M j, Y', strtotime($row['note_updated'] ?? $row['note_created']))?></div>
      </div>
     </div>

     <!-- Sections -->
     <?php if (!empty($row['chief_complaint'])): ?>
       <div class="pn-section">
         <div class="pn-h">Chief Complaint</div>
         <div class="pn-body"><?=htmlspecialchars($row['chief_complaint'])?></div>
       </div>
     <?php endif; ?>

     <?php if (!empty($row['diagnosis'])): ?>
       <div class="pn-section">
         <div class="pn-h">Diagnosis</div>
         <div class="pn-body"><?=htmlspecialchars($row['diagnosis'])?></div>
       </div>
     <?php endif; ?>

     <?php if (!empty($row['medications'])): ?>
       <div class="pn-section">
         <div class="pn-h">Medications (Rx)</div>
         <div class="pn-body" style="font-family:'Courier New',monospace;font-size:12.5px"><?=htmlspecialchars($row['medications'])?></div>
       </div>
     <?php endif; ?>

     <?php if (!empty($row['investigations'])): ?>
       <div class="pn-section">
         <div class="pn-h">Investigations / Lab Tests</div>
         <div class="pn-body"><?=htmlspecialchars($row['investigations'])?></div>
       </div>
     <?php endif; ?>

     <?php if (!empty($row['advice'])): ?>
       <div class="pn-section">
         <div class="pn-h">Advice</div>
         <div class="pn-body"><?=htmlspecialchars($row['advice'])?></div>
       </div>
     <?php endif; ?>

     <?php if (!empty($row['follow_up_date'])): ?>
       <div class="pn-section">
         <div class="pn-h">Follow-up</div>
         <div class="pn-body" style="font-weight:700">Please return on <?=fmtDate($row['follow_up_date'])?></div>
       </div>
     <?php endif; ?>

     <!-- Signature block -->
     <div style="margin-top:36px;display:flex;justify-content:flex-end">
      <div style="text-align:center;min-width:240px">
       <div style="border-bottom:1px solid var(--tx2);margin-bottom:6px;padding-top:30px"></div>
       <div style="font-size:12px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($row['doctor_name'])?></div>
       <div style="font-size:10.5px;color:var(--tx3);margin-top:2px">Digital signature on file</div>
      </div>
     </div>

     <!-- Footer disclaimer -->
     <div style="margin-top:24px;padding-top:14px;border-top:1px solid var(--bd);font-size:10px;color:var(--tx3);line-height:1.5;text-align:center">
      This is a digitally generated consultation note from MediBook. For verification or questions, contact <?=htmlspecialchars($row['clinic_name'] ?: 'your provider')?>.<br>
      Document ID: MB-CN-<?=str_pad($row['note_id'],6,'0',STR_PAD_LEFT)?>
     </div>

    </div>

   <?php endif; ?>

  </div>
 </div>
</div>
</body></html>
