<?php
// patient/rate_appointment.php — Submit a rating + comment for a completed appointment
// (DABSTN-176)
// ============================================================
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requirePatient();

$pid = (int)$_SESSION['patient_id'];
$id  = (int)($_GET['id'] ?? 0);

// Fetch the appointment + doctor, restricted to this patient.
$a = $conn->query(
    "SELECT a.id, a.doctor_id, a.status, a.date, a.time,
            d.name AS dname, d.profile_image AS dimage, d.specialisation, d.clinic_name
       FROM appointments a
       JOIN doctors d ON d.id = a.doctor_id
      WHERE a.id = $id AND a.patient_id = $pid
      LIMIT 1"
)->fetch_assoc();

if (!$a) { header('Location: my_appointments.php'); exit; }

// You can only review a completed visit. Anything else and we bounce back.
if ($a['status'] !== 'completed') {
    header('Location: view_appointment.php?id=' . $id);
    exit;
}

// If a review already exists, redirect — no editing in this scope.
$existing = $conn->query("SELECT id FROM reviews WHERE appointment_id={$a['id']} LIMIT 1")->fetch_assoc();
if ($existing) {
    header('Location: view_appointment.php?id=' . $id);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating  = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $error = 'Please pick a rating between 1 and 5 stars.';
    } elseif (mb_strlen($comment) > 500) {
        $error = 'Comment must be 500 characters or fewer.';
    } else {
        $comment_safe = $conn->real_escape_string($comment);
        $sql = "INSERT INTO reviews (appointment_id, patient_id, doctor_id, rating, comment)
                VALUES ({$a['id']}, $pid, {$a['doctor_id']}, $rating, "
              . ($comment === '' ? 'NULL' : "'$comment_safe'") . ")";

        if ($conn->query($sql)) {
            // Notify the doctor
            $pname = $conn->query("SELECT name FROM patients WHERE id=$pid LIMIT 1")->fetch_assoc()['name'];
            insertNotification($conn, (int)$a['doctor_id'], 'doctor',
                "⭐ New review from $pname — $rating/5 for your " . fmtDate($a['date']) . " appointment.");

            header('Location: view_appointment.php?id=' . $id . '&review_thanks=1');
            exit;
        } else {
            // Most likely the UNIQUE on appointment_id fired (race condition with another tab)
            $error = 'Could not save review (duplicate). Refresh and try again.';
        }
    }
}

$page_title = 'Rate your visit';
include '../includes/header.php';
$unread = countUnread($conn, $pid, 'patient');
?>
<style>
/* Star rating input — purely CSS, no JS dependency */
.star-input{
  display:inline-flex;flex-direction:row-reverse;justify-content:center;
  gap:6px;margin:8px 0 18px;
}
.star-input input{display:none}
.star-input label{
  font-size:42px;line-height:1;cursor:pointer;color:#cbd5e1;transition:color .15s;
  user-select:none;
}
/* When a label is hovered, fill it and all earlier siblings (which are
   visually to its right because of row-reverse). */
.star-input label:hover,
.star-input label:hover ~ label,
.star-input input:checked ~ label{ color:#f59e0b }

.rate-card{
  max-width:580px;margin:0 auto;background:var(--w);
  border:1px solid var(--bd);border-radius:14px;padding:34px 32px;text-align:center;
}
.rate-card h1{font-size:20px;font-weight:800;color:var(--tx);margin-bottom:6px}
.rate-card .sub{font-size:13px;color:var(--tx3);margin-bottom:18px}
.rate-card .doc{
  background:var(--bg);border-radius:10px;padding:14px 16px;margin-bottom:22px;
  display:flex;align-items:center;gap:12px;text-align:left;
}
.rate-card .doc-name{font-size:14px;font-weight:800;color:var(--tx)}
.rate-card .doc-spec{font-size:12px;color:var(--tx3)}
.rate-card textarea{
  width:100%;min-height:96px;border:1.5px solid var(--bd2);border-radius:10px;
  padding:12px 14px;font-size:13px;font-family:inherit;color:var(--tx);
  resize:vertical;line-height:1.55;outline:none;
}
.rate-card textarea:focus{border-color:var(--b);box-shadow:0 0 0 3px rgba(26,111,212,.12)}
.rate-card .char-count{text-align:right;font-size:11px;color:var(--tx3);margin-top:4px}
.rate-card .actions{display:flex;justify-content:space-between;align-items:center;margin-top:18px}
.rate-card .btn-submit{
  background:var(--b);color:#fff;border:none;padding:11px 26px;border-radius:9px;
  font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;
}
.rate-card .btn-submit:disabled{background:#cbd5e1;cursor:not-allowed}
.alert-error{background:#fee2e2;color:#991b1b;padding:11px 14px;border-radius:9px;font-size:13px;margin-bottom:14px}
</style>

<div class="layout">
 <div class="sidebar">
  <div class="sb-profile"><div class="av av-blue" style="width:44px;height:44px;font-size:14px;margin:0 auto 8px"><?= getInitials($_SESSION['patient_name']) ?></div><div style="font-size:13px;font-weight:700;color:var(--tx)"><?= htmlspecialchars($_SESSION['patient_name']) ?></div></div>
  <div class="sb-nav">
   <a class="nav-item" href="dashboard.php"> &nbsp;Dashboard</a>
   <a class="nav-item active" href="my_appointments.php"> &nbsp;My Appointments</a>
   <a class="nav-item" href="find_doctor.php"> &nbsp;Find a Doctor</a>
   <a class="nav-item" href="medical_history.php"> &nbsp;Medical Records</a>
   <a class="nav-item" href="my_profile.php"> &nbsp;My Profile</a>
   <a class="nav-item" href="notifications.php"> &nbsp;Notifications<?php if($unread>0):?><span class="nav-badge"><?= $unread ?></span><?php endif?></a>
  </div>
  <div class="sb-bottom"><a class="nav-item" style="color:var(--tx3)" href="logout.php">↩ &nbsp;Log out</a></div>
 </div>
 <div class="main-content">
  <div class="topbar">
   <div class="flex items-center gap-3"><div class="logo-i">M</div><span class="logo-n">MediBook</span></div>
   <a href="view_appointment.php?id=<?= $id ?>" style="font-size:13px;color:var(--b);font-weight:500;text-decoration:none">← Back to appointment</a>
  </div>

  <div class="page-body">
   <div class="rate-card">
    <h1>How was your visit?</h1>
    <div class="sub">Your feedback helps other patients choose the right doctor.</div>

    <div class="doc">
     <?= doctorAvatar(['name'=>$a['dname'],'profile_image'=>$a['dimage']??null], 44, 'av-blue') ?>
     <div style="flex:1">
      <div class="doc-name"><?= htmlspecialchars($a['dname']) ?></div>
      <div class="doc-spec"><?= htmlspecialchars($a['specialisation']) ?> · <?= fmtDate($a['date']) ?></div>
     </div>
    </div>

    <?php if ($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST">
     <div style="font-size:12px;color:var(--tx3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Your rating</div>
     <div class="star-input">
      <input type="radio" id="r5" name="rating" value="5"><label for="r5" title="Excellent">★</label>
      <input type="radio" id="r4" name="rating" value="4"><label for="r4" title="Good">★</label>
      <input type="radio" id="r3" name="rating" value="3"><label for="r3" title="Okay">★</label>
      <input type="radio" id="r2" name="rating" value="2"><label for="r2" title="Poor">★</label>
      <input type="radio" id="r1" name="rating" value="1"><label for="r1" title="Bad">★</label>
     </div>

     <div style="font-size:12px;color:var(--tx3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;text-align:left;margin-bottom:6px">
      Comment <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--tx3)">(optional)</span>
     </div>
     <textarea name="comment" maxlength="500" placeholder="What stood out about this visit? Was the doctor attentive? Was the wait reasonable?"
               oninput="document.getElementById('cc').textContent = this.value.length"></textarea>
     <div class="char-count"><span id="cc">0</span> / 500</div>

     <div class="actions">
      <a href="view_appointment.php?id=<?= $id ?>" style="font-size:13px;color:var(--tx3);text-decoration:none;font-weight:600">Cancel</a>
      <button type="submit" class="btn-submit">Submit review</button>
     </div>
    </form>
   </div>
  </div>
 </div>
</div>
</body></html>