<?php
// doctor/consultation_note.php — Structured consultation note form
// Replaces the free-text "Add Report" modal with a proper form.
// Same auth pattern as the rest of /doctor/*: requireDoctor().
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requireDoctor();

$did = (int)$_SESSION['doctor_id'];
$aid = (int)($_GET['aid'] ?? 0);

// ── Authorization: the appointment must belong to this doctor ──
$apt = $conn->query("
    SELECT a.*, p.name AS pname, p.dob AS pdob, p.phone AS pphone, p.blood_type
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    WHERE a.id=$aid AND a.doctor_id=$did
    LIMIT 1
")->fetch_assoc();

if (!$apt) {
    header('Location: appointments.php');
    exit;
}

// ── Load existing note (if any) — supports both create + edit in one page ──
$existing = $conn->query("SELECT * FROM consultation_notes WHERE appointment_id=$aid LIMIT 1")->fetch_assoc();

// ── Handle save ──
$err = $ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chief    = clean($conn, $_POST['chief_complaint'] ?? '');
    $diag     = clean($conn, $_POST['diagnosis']       ?? '');
    $meds     = clean($conn, $_POST['medications']     ?? '');
    $invest   = clean($conn, $_POST['investigations']  ?? '');
    $advice   = clean($conn, $_POST['advice']          ?? '');
    $follow   = $_POST['follow_up_date'] ?? '';
    $follow   = (!empty($follow) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $follow)) ? $follow : null;

    if (trim($diag) === '' && trim($meds) === '' && trim($advice) === '') {
        $err = 'Please fill at least one of: Diagnosis, Medications, or Advice.';
    } else {
        $pid = (int)$apt['patient_id'];

        if ($existing) {
            // 6 fields + 1 WHERE = 7 params (6 strings, 1 int)
            $stmt = $conn->prepare("
                UPDATE consultation_notes
                   SET chief_complaint=?, diagnosis=?, medications=?, investigations=?, advice=?, follow_up_date=?
                 WHERE appointment_id=?
            ");
            $stmt->bind_param('ssssssi', $chief, $diag, $meds, $invest, $advice, $follow, $aid);
            $stmt->execute();
        } else {
            // 9 params: 3 ints (aid, did, pid) + 6 strings (5 text fields + date as string)
            $stmt = $conn->prepare("
                INSERT INTO consultation_notes
                    (appointment_id, doctor_id, patient_id, chief_complaint, diagnosis, medications, investigations, advice, follow_up_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('iiissssss', $aid, $did, $pid, $chief, $diag, $meds, $invest, $advice, $follow);
            $stmt->execute();
        }

        // Mark appointment completed (mirrors the existing doctor_report flow)
        $conn->query("UPDATE appointments SET status='completed' WHERE id=$aid AND doctor_id=$did");

        // Notify the patient (same pattern used in appointments.php)
        insertNotification(
            $conn,
            $pid,
            'patient',
            "Dr. {$_SESSION['doctor_name']} has added a consultation note for your appointment on " . fmtDate($apt['date']) . "."
        );

        header("Location: consultation_note.php?aid=$aid&ok=1");
        exit;
    }
}

if (!empty($_GET['ok'])) {
    $ok       = 'Consultation note saved. The patient has been notified.';
    $existing = $conn->query("SELECT * FROM consultation_notes WHERE appointment_id=$aid LIMIT 1")->fetch_assoc();
}

$unread = countUnread($conn, $did, 'doctor');
$page_title = 'Consultation Note';
include '../includes/header.php';
?>
<div class="layout">
 <div class="sidebar">
  <div class="sb-profile" style="text-align:center">
    <div style="margin:0 auto 8px;display:flex;justify-content:center">
      <?= doctorAvatar(['name'=>$_SESSION['doctor_name']??'','profile_image'=>$_SESSION['doctor_image']??null], 44, 'av-teal') ?>
    </div>
    <div style="font-size:13px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($_SESSION['doctor_name'])?></div>
  </div>
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
  <div class="topbar">
   <div class="flex items-center gap-3"><div class="logo-i">M</div><span class="logo-n">MediBook</span></div>
   <a href="appointments.php" style="font-size:13px;color:var(--b);font-weight:500;text-decoration:none">← Back to Appointments</a>
   <div></div>
  </div>

  <div class="page-body">
   <h1 class="page-title">Consultation Note</h1>

   <?php if ($ok):  ?><div class="alert alert-success" style="margin-bottom:18px"><?=htmlspecialchars($ok)?></div><?php endif; ?>
   <?php if ($err): ?><div class="alert alert-error"   style="margin-bottom:18px"><?=htmlspecialchars($err)?></div><?php endif; ?>

   <!-- Patient & appointment summary -->
   <div class="card" style="padding:18px;margin-bottom:18px;display:grid;grid-template-columns:repeat(4,1fr);gap:16px">
    <div>
     <div style="font-size:10px;color:var(--tx3);text-transform:uppercase;font-weight:700;margin-bottom:4px">Patient</div>
     <div style="font-size:14px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($apt['pname'])?></div>
     <?php if (!empty($apt['pdob'])): ?>
       <div style="font-size:11px;color:var(--tx3)">DOB: <?=htmlspecialchars($apt['pdob'])?></div>
     <?php endif; ?>
    </div>
    <div>
     <div style="font-size:10px;color:var(--tx3);text-transform:uppercase;font-weight:700;margin-bottom:4px">Appointment</div>
     <div style="font-size:13px;font-weight:600;color:var(--tx)"><?=fmtDate($apt['date'])?></div>
     <div style="font-size:11px;color:var(--tx3)"><?=fmt12($apt['time'])?></div>
    </div>
    <div>
     <div style="font-size:10px;color:var(--tx3);text-transform:uppercase;font-weight:700;margin-bottom:4px">Status</div>
     <div><?=statusBadge($apt['status'])?></div>
    </div>
    <div>
     <div style="font-size:10px;color:var(--tx3);text-transform:uppercase;font-weight:700;margin-bottom:4px">Reason for visit</div>
     <div style="font-size:12px;color:var(--tx2);line-height:1.5"><?=htmlspecialchars($apt['reason'] ?? '—')?></div>
    </div>
   </div>

   <!-- The form -->
   <form method="POST" class="card" style="padding:24px">
    <div style="margin-bottom:18px">
     <label style="display:block;font-size:12px;font-weight:700;color:var(--tx);margin-bottom:6px">Chief Complaint</label>
     <input type="text" name="chief_complaint" class="form-control"
            style="width:100%;padding:10px 14px;font-size:13px;border:1.5px solid var(--bd2);border-radius:8px"
            placeholder="e.g. Persistent cough for 2 weeks"
            value="<?=htmlspecialchars($existing['chief_complaint'] ?? '')?>">
    </div>

    <div style="margin-bottom:18px">
     <label style="display:block;font-size:12px;font-weight:700;color:var(--tx);margin-bottom:6px">Diagnosis</label>
     <textarea name="diagnosis" rows="3" class="form-control"
               style="width:100%;padding:10px 14px;font-size:13px;border:1.5px solid var(--bd2);border-radius:8px;font-family:inherit;resize:vertical;line-height:1.5"
               placeholder="Clinical impression / diagnosis"><?=htmlspecialchars($existing['diagnosis'] ?? '')?></textarea>
    </div>

    <div style="margin-bottom:18px">
     <label style="display:block;font-size:12px;font-weight:700;color:var(--tx);margin-bottom:6px">Medications <span style="font-weight:400;color:var(--tx3);font-size:11px">— one per line, format: Drug name, dose, frequency, duration</span></label>
     <textarea name="medications" rows="5" class="form-control"
               style="width:100%;padding:10px 14px;font-size:13px;border:1.5px solid var(--bd2);border-radius:8px;font-family:'Courier New',monospace;resize:vertical;line-height:1.6"
               placeholder="Amoxicillin 500mg, 1 capsule three times a day, 7 days&#10;Paracetamol 500mg, as needed for fever, max 4/day"><?=htmlspecialchars($existing['medications'] ?? '')?></textarea>
    </div>

    <div style="margin-bottom:18px">
     <label style="display:block;font-size:12px;font-weight:700;color:var(--tx);margin-bottom:6px">Investigations / Lab Tests <span style="font-weight:400;color:var(--tx3);font-size:11px">(optional)</span></label>
     <textarea name="investigations" rows="2" class="form-control"
               style="width:100%;padding:10px 14px;font-size:13px;border:1.5px solid var(--bd2);border-radius:8px;font-family:inherit;resize:vertical;line-height:1.5"
               placeholder="e.g. Complete blood count (CBC), chest X-ray"><?=htmlspecialchars($existing['investigations'] ?? '')?></textarea>
    </div>

    <div style="margin-bottom:18px">
     <label style="display:block;font-size:12px;font-weight:700;color:var(--tx);margin-bottom:6px">Advice</label>
     <textarea name="advice" rows="3" class="form-control"
               style="width:100%;padding:10px 14px;font-size:13px;border:1.5px solid var(--bd2);border-radius:8px;font-family:inherit;resize:vertical;line-height:1.5"
               placeholder="Lifestyle changes, rest, diet, warning signs to watch for, etc."><?=htmlspecialchars($existing['advice'] ?? '')?></textarea>
    </div>

    <div style="margin-bottom:24px;max-width:260px">
     <label style="display:block;font-size:12px;font-weight:700;color:var(--tx);margin-bottom:6px">Follow-up Date <span style="font-weight:400;color:var(--tx3);font-size:11px">(optional)</span></label>
     <input type="date" name="follow_up_date" class="form-control"
            style="width:100%;padding:10px 14px;font-size:13px;border:1.5px solid var(--bd2);border-radius:8px"
            min="<?=date('Y-m-d')?>"
            value="<?=htmlspecialchars($existing['follow_up_date'] ?? '')?>">
    </div>

    <div style="display:flex;justify-content:flex-end;gap:12px;border-top:1px solid var(--bd);padding-top:18px">
     <a href="appointments.php" class="btn-g" style="padding:11px 24px">Cancel</a>
     <button type="submit" class="btn-t" style="padding:11px 28px">
      <?= $existing ? 'Update Note' : 'Save & Complete Appointment' ?>
     </button>
    </div>
   </form>

  </div>
 </div>
</div>
</body></html>
