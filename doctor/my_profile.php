<?php
// doctor/my_profile.php — Doctor profile view & edit (with avatar upload)
// ============================================================
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requireDoctor();

$did = (int)$_SESSION['doctor_id'];
$dr  = $conn->query("SELECT * FROM doctors WHERE id=$did LIMIT 1")->fetch_assoc();
if (!$dr) { header('Location: login.php'); exit; }

$success = '';
$error   = '';

// ── Handle POST: update text fields and (optionally) profile image ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // -- Text fields --
    $name           = clean($conn, $_POST['name']           ?? '');
    $specialisation = clean($conn, $_POST['specialisation'] ?? '');
    $qualification  = clean($conn, $_POST['qualification']  ?? '');
    $experience     = (int)($_POST['experience'] ?? 0);
    $clinic_name    = clean($conn, $_POST['clinic_name']    ?? '');
    $clinic_address = clean($conn, $_POST['clinic_address'] ?? '');
    $clinic_phone   = clean($conn, $_POST['clinic_phone']   ?? '');
    $bio            = clean($conn, $_POST['bio']            ?? '');
    $fee            = (float)($_POST['fee'] ?? 0);

    // Light validation — nothing fancy, just block obvious nonsense
    if ($name === '')           $error = 'Name is required.';
    elseif ($specialisation==='') $error = 'Specialisation is required.';
    elseif ($experience < 0 || $experience > 70) $error = 'Experience must be a number between 0 and 70.';
    elseif ($fee < 0 || $fee > 100000)            $error = 'Consultation fee looks unreasonable.';

    // -- Profile image upload (optional) --
    $new_image_path = null; // will hold relative path like "uploads/doctors/3/avatar.jpg"
    if (!$error && !empty($_FILES['profile_image']['name'])) {

        $file = $_FILES['profile_image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Image upload failed (error code ' . $file['error'] . ').';
        }
        elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = 'Image is too large. Maximum size is 5 MB.';
        }
        else {
            // Verify the file is genuinely an image — extension is not enough.
            // exif_imagetype reads the file's magic bytes, so a renamed PHP
            // file pretending to be a JPEG will fail this check.
            $imgtype = @exif_imagetype($file['tmp_name']);
            $allowed = [
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG  => 'png',
                IMAGETYPE_WEBP => 'webp',
                IMAGETYPE_GIF  => 'gif',
            ];
            if (!isset($allowed[$imgtype])) {
                $error = 'Only JPG, PNG, WEBP, or GIF images are allowed.';
            } else {
                $ext = $allowed[$imgtype];

                // Per-doctor folder, mirroring uploads/reports/{id}/ pattern
                $upload_dir = __DIR__ . '/../uploads/doctors/' . $did;
                if (!is_dir($upload_dir)) {
                    if (!@mkdir($upload_dir, 0755, true)) {
                        $error = 'Could not create upload folder. Check folder permissions.';
                    }
                }

                if (!$error) {
                    // Filename includes a timestamp so the browser doesn't
                    // serve a stale cached version after re-upload.
                    $filename     = 'avatar_' . time() . '.' . $ext;
                    $target_path  = $upload_dir . '/' . $filename;
                    $relative_path = 'uploads/doctors/' . $did . '/' . $filename;

                    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                        $error = 'Failed to save uploaded image.';
                    } else {
                        $new_image_path = $relative_path;

                        // Delete the previous avatar so we don't leak disk space
                        if (!empty($dr['profile_image'])) {
                            $old_full = __DIR__ . '/../' . $dr['profile_image'];
                            if (is_file($old_full)) @unlink($old_full);
                        }
                    }
                }
            }
        }
    }

    // -- Persist changes --
    if (!$error) {
        $img_sql = $new_image_path
            ? ", profile_image='" . $conn->real_escape_string($new_image_path) . "'"
            : '';

        $conn->query(
            "UPDATE doctors SET
                name           = '$name',
                specialisation = '$specialisation',
                qualification  = '$qualification',
                experience     = $experience,
                clinic_name    = '$clinic_name',
                clinic_address = '$clinic_address',
                clinic_phone   = '$clinic_phone',
                bio            = '$bio',
                fee            = $fee
                $img_sql
             WHERE id = $did"
        );

        $_SESSION['doctor_name'] = $name; // keep header / sidebar fresh
        $success = '✓ Profile saved successfully.';

        // Re-fetch so the form below shows freshly-saved values
        $dr = $conn->query("SELECT * FROM doctors WHERE id=$did LIMIT 1")->fetch_assoc();
    }
}

// ── Resolve avatar URL with graceful fallback to initials ──────────
$avatar_url = '';
if (!empty($dr['profile_image'])) {
    $on_disk = __DIR__ . '/../' . $dr['profile_image'];
    if (is_file($on_disk)) {
        // /medibook/uploads/... — path relative to the web app root
        $avatar_url = '/medibook/' . $dr['profile_image'];
    }
}

// Stats for header
$total_appts     = (int)$conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$did")->fetch_assoc()['c'];
$completed_appts = (int)$conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$did AND status='completed'")->fetch_assoc()['c'];
$member_since    = date('M Y', strtotime($dr['created_at']));

$page_title = 'My Profile';
include '../includes/header.php';
$unread = countUnread($conn, $did, 'doctor');
?>
<style>
.profile-wrap{display:grid;grid-template-columns:320px 1fr;gap:22px;align-items:start}

/* Left card — avatar + read-only meta */
.avatar-card{background:var(--w);border:1px solid var(--bd);border-radius:14px;padding:24px;text-align:center;position:sticky;top:20px}
.avatar-frame{
  width:150px;height:150px;border-radius:50%;margin:0 auto 16px;
  border:3px solid var(--b3);overflow:hidden;
  background:linear-gradient(135deg,var(--b),var(--b2));
  display:flex;align-items:center;justify-content:center;
  font-size:48px;font-weight:800;color:#fff;
}
.avatar-frame img{width:100%;height:100%;object-fit:cover}
.avatar-name{font-size:18px;font-weight:800;color:var(--tx);margin-bottom:3px}
.avatar-spec{font-size:13px;color:var(--tx3);margin-bottom:18px}

.read-only-row{display:flex;justify-content:space-between;padding:10px 0;border-top:1px solid var(--bd);font-size:12px;text-align:left}
.read-only-row .lbl{color:var(--tx3);font-weight:600}
.read-only-row .val{color:var(--tx);font-weight:700;text-align:right}

.status-badge{
  display:inline-block;padding:3px 10px;border-radius:6px;
  font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;
}
.status-approved {background:#dcfce7;color:#166534}
.status-pending  {background:#fef3c7;color:#92400e}
.status-suspended{background:#fee2e2;color:#dc2626}

/* Right card — edit form */
.form-card{background:var(--w);border:1px solid var(--bd);border-radius:14px;padding:26px}
.form-card h2{font-size:16px;font-weight:800;color:var(--tx);margin-bottom:4px}
.form-card .sub{font-size:12px;color:var(--tx3);margin-bottom:22px}

.fld-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.fld{display:block;margin-bottom:14px}
.fld.full{grid-column:1 / -1}
.fld label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--tx3);margin-bottom:6px}
.fld .hint{font-size:11px;color:var(--tx3);margin-top:4px;font-weight:400}
.fld input, .fld textarea, .fld select{
  width:100%;background:var(--w);border:1.5px solid var(--bd2);border-radius:10px;
  padding:11px 14px;font-size:13px;color:var(--tx);font-family:inherit;outline:none;
  transition:border-color .15s,box-shadow .15s;
}
.fld input:focus, .fld textarea:focus, .fld select:focus{
  border-color:var(--b);box-shadow:0 0 0 3px rgba(26,111,212,.12);
}
.fld input[readonly]{background:var(--bg);color:var(--tx3);cursor:not-allowed}
.fld textarea{min-height:90px;resize:vertical;line-height:1.5}

.upload-row{
  display:flex;gap:10px;align-items:center;margin-bottom:6px;
  padding:14px;background:var(--bg);border:1.5px dashed var(--bd2);border-radius:11px;
}
.upload-row input[type="file"]{flex:1;background:transparent;border:none;padding:0;font-size:12px}

.section-divider{
  font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;
  color:var(--tx3);margin:22px 0 12px;padding-bottom:6px;border-bottom:1px solid var(--bd);
}

.actions-row{display:flex;justify-content:space-between;align-items:center;margin-top:22px}
.btn-save{
  background:var(--b);color:#fff;border:none;padding:12px 30px;border-radius:10px;
  font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;
}
.btn-save:hover{background:var(--b2)}

.alert-success{background:#dcfce7;color:#166534;padding:12px 16px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:18px;border:1px solid #86efac}
.alert-error  {background:#fee2e2;color:#991b1b;padding:12px 16px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:18px;border:1px solid #fca5a5}

@media (max-width:920px){
  .profile-wrap{grid-template-columns:1fr}
  .avatar-card{position:static}
  .fld-grid{grid-template-columns:1fr}
}
</style>

<div class="layout">
 <!-- Sidebar -->
 <div class="sidebar">
  <div class="sb-profile" style="text-align:center">
   <?php if ($avatar_url): ?>
    <div style="width:46px;height:46px;border-radius:50%;overflow:hidden;margin:0 auto 8px;border:2px solid var(--b3)">
     <img src="<?= htmlspecialchars($avatar_url) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
    </div>
   <?php else: ?>
    <div class="av av-teal" style="width:46px;height:46px;font-size:15px;margin:0 auto 8px"><?= getInitials($dr['name']) ?></div>
   <?php endif; ?>
   <div style="font-size:13px;font-weight:700;color:var(--tx)"><?= htmlspecialchars($dr['name']) ?></div>
   <div style="font-size:11px;color:var(--tx3);margin-top:2px"><?= htmlspecialchars($dr['specialisation']) ?></div>
  </div>
  <div class="sb-nav">
   <a class="nav-item" href="dashboard.php"> &nbsp;Dashboard</a>
   <a class="nav-item" href="appointments.php"> &nbsp;Appointments</a>
   <a class="nav-item" href="schedule.php"> &nbsp;My Schedule</a>
   <a class="nav-item" href="availability.php"> &nbsp;Availability</a>
   <a class="nav-item" href="notifications.php"> &nbsp;Notifications
    <?php if($unread > 0): ?><span class="nav-badge"><?= $unread ?></span><?php endif; ?>
   </a>
   <a class="nav-item active" href="my_profile.php"> &nbsp;My Profile</a>
  </div>
  <div class="sb-bottom">
   <a class="nav-item" style="color:var(--tx3)" href="logout.php">↩ &nbsp;Log out</a>
  </div>
 </div>

 <div class="main-content">
  <div class="topbar">
   <div class="flex items-center gap-3">
    <div class="logo-i">M</div>
    <span class="logo-n">MediBook</span>
   </div>
  </div>

  <div class="page-body">
   <h1 class="page-title">My Profile</h1>
   <p class="page-sub" style="margin-bottom:22px">Manage your professional information and avatar.</p>

   <?php if ($success): ?><div class="alert-success"><?= $success ?></div><?php endif; ?>
   <?php if ($error):   ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

   <div class="profile-wrap">

    <!-- LEFT: avatar + summary card -->
    <div class="avatar-card">
     <div class="avatar-frame">
      <?php if ($avatar_url): ?>
       <img src="<?= htmlspecialchars($avatar_url) ?>" alt="<?= htmlspecialchars($dr['name']) ?>">
      <?php else: ?>
       <?= htmlspecialchars(getInitials($dr['name'])) ?>
      <?php endif; ?>
     </div>
     <div class="avatar-name"><?= htmlspecialchars($dr['name']) ?></div>
     <div class="avatar-spec"><?= htmlspecialchars($dr['specialisation']) ?></div>

     <div class="read-only-row"><span class="lbl">Status</span><span class="val">
      <span class="status-badge status-<?= htmlspecialchars($dr['status']) ?>"><?= htmlspecialchars($dr['status']) ?></span>
     </span></div>
     <div class="read-only-row"><span class="lbl">Total appointments</span><span class="val"><?= $total_appts ?></span></div>
     <div class="read-only-row"><span class="lbl">Completed</span><span class="val"><?= $completed_appts ?></span></div>
     <div class="read-only-row"><span class="lbl">Member since</span><span class="val"><?= htmlspecialchars($member_since) ?></span></div>
    </div>

    <!-- RIGHT: edit form -->
    <form method="POST" enctype="multipart/form-data" class="form-card">
     <h2>Edit profile</h2>
     <div class="sub">Changes save immediately. Email and password cannot be changed here.</div>

     <!-- Avatar upload -->
     <div class="fld">
      <label>Profile picture</label>
      <div class="upload-row">
       <input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp,image/gif">
      </div>
      <div class="hint">JPG, PNG, WEBP or GIF. Max 5 MB. Square images look best (150 × 150 or larger).</div>
     </div>

     <div class="section-divider">Personal</div>

     <div class="fld-grid">
      <div class="fld">
       <label>Full name *</label>
       <input type="text" name="name" required value="<?= htmlspecialchars($dr['name']) ?>">
      </div>
      <div class="fld">
       <label>Email (read-only)</label>
       <input type="email" readonly value="<?= htmlspecialchars($dr['email']) ?>">
       <div class="hint">Contact admin to change your email.</div>
      </div>
     </div>

     <div class="section-divider">Professional</div>

     <div class="fld-grid">
      <div class="fld">
       <label>Specialisation *</label>
       <input type="text" name="specialisation" required value="<?= htmlspecialchars($dr['specialisation']) ?>" placeholder="Cardiology, Pediatrics…">
      </div>
      <div class="fld">
       <label>Years of experience</label>
       <input type="number" name="experience" min="0" max="70" value="<?= (int)$dr['experience'] ?>">
      </div>
      <div class="fld full">
       <label>Qualifications</label>
       <input type="text" name="qualification" value="<?= htmlspecialchars($dr['qualification'] ?? '') ?>" placeholder="MBBS, MD (Cardiology) — IOM">
      </div>
      <div class="fld full">
       <label>Bio</label>
       <textarea name="bio" placeholder="A short professional summary patients will see when booking."><?= htmlspecialchars($dr['bio'] ?? '') ?></textarea>
      </div>
      <div class="fld">
       <label>Consultation fee (NPR)</label>
       <input type="number" name="fee" min="0" step="50" value="<?= (float)$dr['fee'] ?>">
       <div class="hint">Per-visit fee shown to patients during booking.</div>
      </div>
     </div>

     <div class="section-divider">Clinic / Hospital</div>

     <div class="fld-grid">
      <div class="fld">
       <label>Clinic / hospital name</label>
       <input type="text" name="clinic_name" value="<?= htmlspecialchars($dr['clinic_name'] ?? '') ?>">
      </div>
      <div class="fld">
       <label>Clinic phone</label>
       <input type="tel" name="clinic_phone" value="<?= htmlspecialchars($dr['clinic_phone'] ?? '') ?>" placeholder="+977…">
      </div>
      <div class="fld full">
       <label>Clinic address</label>
       <input type="text" name="clinic_address" value="<?= htmlspecialchars($dr['clinic_address'] ?? '') ?>">
      </div>
     </div>

     <div class="actions-row">
      <a href="dashboard.php" style="font-size:13px;color:var(--tx3);text-decoration:none;font-weight:600">← Cancel</a>
      <button type="submit" class="btn-save">Save changes</button>
     </div>
    </form>

   </div>
  </div>
 </div>
</div>

</body></html>
