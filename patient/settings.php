<?php
// patient/settings.php — Account settings (currently: 2FA toggle)
// ============================================================
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requirePatient();

$pid = (int)$_SESSION['patient_id'];
$msg = '';

// ── Handle toggle ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_2fa'])) {
    $new_state = ($_POST['toggle_2fa'] === 'enable') ? 1 : 0;
    $conn->query("UPDATE patients SET two_factor_enabled=$new_state WHERE id=$pid");
    $msg = $new_state
        ? '✓ Two-factor authentication enabled. You will be asked for a 6-digit code on your next login.'
        : '✓ Two-factor authentication disabled.';
}

// ── Fetch current state ─────────────────────────────────
$me = $conn->query("SELECT name, email, two_factor_enabled FROM patients WHERE id=$pid LIMIT 1")->fetch_assoc();
$is_on = (int)$me['two_factor_enabled'] === 1;

$page_title = 'Settings';
include '../includes/header.php';
$unread = countUnread($conn, $pid, 'patient');
?>
<style>
.settings-card{background:var(--w);border:1px solid var(--bd);border-radius:14px;padding:24px;margin-bottom:18px;max-width:720px}
.settings-card h2{font-size:15px;font-weight:800;color:var(--tx);margin-bottom:4px}
.settings-card .sub{font-size:12px;color:var(--tx3);margin-bottom:18px;line-height:1.5}

.toggle-row{
  display:flex;align-items:flex-start;justify-content:space-between;gap:24px;
  padding:16px;background:var(--bg);border:1px solid var(--bd);border-radius:11px;
}
.toggle-row .info{flex:1}
.toggle-row .info .ttl{font-size:14px;font-weight:700;color:var(--tx);margin-bottom:5px;display:flex;align-items:center;gap:8px}
.toggle-row .info .desc{font-size:12px;color:var(--tx2);line-height:1.55}
.toggle-row .info .desc strong{color:var(--tx)}

.status-pill{
  display:inline-block;padding:2px 9px;border-radius:6px;font-size:10px;font-weight:800;
  text-transform:uppercase;letter-spacing:.05em;
}
.status-pill.on {background:#dcfce7;color:#166534}
.status-pill.off{background:#fee2e2;color:#991b1b}

.btn-toggle{
  border:none;cursor:pointer;font-family:inherit;font-weight:700;font-size:13px;
  padding:10px 20px;border-radius:9px;flex-shrink:0;
}
.btn-toggle.enable{background:var(--b);color:#fff}
.btn-toggle.enable:hover{background:var(--b2)}
.btn-toggle.disable{background:#fff;border:1.5px solid #fca5a5;color:#991b1b}
.btn-toggle.disable:hover{background:#fee2e2}

.alert{padding:12px 16px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:18px;border:1px solid}
.alert.success{background:#dcfce7;color:#166534;border-color:#86efac}

.tip{background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:13px 15px;font-size:12px;color:#1e40af;line-height:1.55;margin-top:14px}
.tip strong{font-weight:800}
</style>

<div class="layout">
 <!-- Sidebar -->
 <div class="sidebar">
  <div class="sb-profile">
   <div class="av av-blue" style="width:46px;height:46px;font-size:16px;margin:0 auto 8px"><?= getInitials($me['name']) ?></div>
   <div style="font-size:13px;font-weight:700;color:var(--tx);text-align:center"><?= htmlspecialchars($me['name']) ?></div>
   <div style="font-size:11px;color:var(--tx3);text-align:center;margin-top:2px">Patient</div>
  </div>
  <div class="sb-nav">
   <a class="nav-item" href="dashboard.php"> &nbsp;Dashboard</a>
   <a class="nav-item" href="find_doctor.php"> &nbsp;Find a doctor</a>
   <a class="nav-item" href="my_appointments.php"> &nbsp;My appointments</a>
   <a class="nav-item" href="medical_history.php"> &nbsp;Medical history</a>
   <a class="nav-item" href="notifications.php"> &nbsp;Notifications
    <?php if($unread>0): ?><span class="nav-badge"><?= $unread ?></span><?php endif; ?>
   </a>
   <a class="nav-item" href="my_profile.php"> &nbsp;My profile</a>
   <a class="nav-item active" href="settings.php"> &nbsp;Settings</a>
  </div>
  <div class="sb-bottom">
   <a class="nav-item" style="color:var(--tx3)" href="logout.php">↩ &nbsp;Log out</a>
  </div>
 </div>

 <!-- Main -->
 <div class="main-content">
  <div class="topbar">
   <div class="flex items-center gap-3">
    <div class="logo-i">M</div>
    <span class="logo-n">MediBook</span>
   </div>
  </div>

  <div class="page-body">
   <h1 class="page-title">Settings</h1>
   <p class="page-sub" style="margin-bottom:22px">Manage your account security and preferences.</p>

   <?php if ($msg): ?><div class="alert success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

   <!-- 2FA card -->
   <div class="settings-card">
    <h2> Security</h2>
    <div class="sub">Add an extra layer of protection to your account.</div>

    <div class="toggle-row">
     <div class="info">
      <div class="ttl">
       Two-factor authentication
       <span class="status-pill <?= $is_on ? 'on' : 'off' ?>"><?= $is_on ? 'Enabled' : 'Disabled' ?></span>
      </div>
      <div class="desc">
       <?php if ($is_on): ?>
        On every login, after entering your password you'll be asked for a 6-digit code.
        The code is sent to <strong><?= htmlspecialchars($me['email']) ?></strong> and expires in 5 minutes.
       <?php else: ?>
        When enabled, every login requires a 6-digit code in addition to your password.
        The code will be sent to <strong><?= htmlspecialchars($me['email']) ?></strong>.
       <?php endif; ?>
      </div>
     </div>
     <form method="POST" style="margin:0">
      <?php if ($is_on): ?>
       <input type="hidden" name="toggle_2fa" value="disable">
       <button type="submit" class="btn-toggle disable" onclick="return confirm('Disable two-factor authentication? Your account will only be protected by your password.')">Disable</button>
      <?php else: ?>
       <input type="hidden" name="toggle_2fa" value="enable">
       <button type="submit" class="btn-toggle enable">Enable 2FA</button>
      <?php endif; ?>
     </form>
    </div>

    <?php if (!$is_on): ?>
     <div class="tip">
      <strong>Why enable 2FA?</strong> If someone steals your password, they still can't log in without the 6-digit code from your email.
     </div>
    <?php endif; ?>
   </div>

  </div>
 </div>
</div>
</body></html>
