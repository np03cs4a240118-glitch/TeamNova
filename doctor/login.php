<?php
// doctor/login.php — DABS-10 (T10-1 to T10-6) + DABSTN-82 (email verification gate)
session_start();
if (!empty($_SESSION['doctor_id'])) { header('Location: /medibook/doctor/dashboard.php'); exit; }
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
$error=''; $unverified_email=''; $resent=false;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $email = trim($_POST['email']??'');
    $pw    = $_POST['password']??'';
    if (!$email||!$pw) { $error='Enter email and password.'; }
    else {
        $es = $conn->real_escape_string($email);
        $r  = $conn->query("SELECT id,name,password,status,email_verified,profile_image FROM doctors WHERE email='$es' LIMIT 1");
        if ($r->num_rows===1) {
            $row=$r->fetch_assoc();
            // T10-3: Check status approved
            if ($row['status']==='pending')      $error='Your account is awaiting admin approval.';
            elseif ($row['status']==='suspended') $error='Your account has been suspended.';
            elseif (!password_verify($pw,$row['password'])) $error='invalid'; // T10-5
            elseif (!$row['email_verified']) {    // DABSTN-82: email gate
                $error='unverified';
                $unverified_email = htmlspecialchars($email);
            } else {
                // T10-4: Start session, T10-6: Redirect
                $_SESSION['doctor_id']=$row['id']; $_SESSION['doctor_name']=$row['name'];
                $_SESSION['doctor_image']=$row['profile_image'] ?? null;
                header('Location: /medibook/doctor/dashboard.php'); exit;
            }
        } else $error='invalid';
    }
}
// Handle resend verification
if (isset($_GET['resend'])) {
    $es = $conn->real_escape_string(trim($_GET['resend']));
    $r  = $conn->query("SELECT id, email FROM doctors WHERE email='$es' AND email_verified=0 LIMIT 1");
    if ($r && $r->num_rows===1) {
        $dr = $r->fetch_assoc();
        sendVerificationEmail($conn, $dr['id'], 'doctor', $dr['email']);
        $resent = true;
    }
}
$page_title='Doctor Login'; include '../includes/header.php';
?>
<div class="auth-split">
 <div class="auth-left">
  <div class="flex items-center" style="gap:9px;margin-bottom:28px"><div class="logo-i">M</div><span class="logo-n-white">MediBook</span></div>
  <h1 style="font-size:36px;font-weight:800;color:#fff;line-height:1.15;margin-bottom:14px">Doctor Portal</h1>
  <p style="font-size:14px;color:rgba(255,255,255,.65);line-height:1.75;margin-bottom:32px">Manage your appointments, schedule, and patient interactions all in one place.</p>
 </div>
 <div class="auth-right">
  <h2 style="font-size:22px;font-weight:800;color:var(--tx);margin-bottom:6px">Doctor Login</h2>
  <p style="font-size:13px;color:var(--tx3);margin-bottom:24px">Sign in to your doctor dashboard</p>

  <?php if ($resent): ?>
    <div class="alert alert-success">✅ Verification link generated!</div>
    <?php if (!empty($_SESSION['demo_verification_link'])): ?>
      <div style="background:var(--b3);border:1px solid var(--b4);border-radius:10px;padding:16px;margin-bottom:16px">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--b);margin-bottom:8px">Demo mode — verification link (normally sent by email)</div>
        <div style="font-size:12px;color:var(--tx2);margin-bottom:10px">Click the link below to verify your email address:</div>
        <a href="<?= htmlspecialchars($_SESSION['demo_verification_link']) ?>" style="font-size:13px;color:var(--b);font-weight:700;word-break:break-all"><?= htmlspecialchars($_SESSION['demo_verification_link']) ?></a>
      </div>
      <a href="<?= htmlspecialchars($_SESSION['demo_verification_link']) ?>" class="btn-p btn-full" style="display:block;text-align:center;padding:13px;font-size:14px;border-radius:10px;margin-bottom:14px;text-decoration:none">Verify my email &rarr;</a>
      <?php unset($_SESSION['demo_verification_link'], $_SESSION['demo_verification_email']); ?>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($error==='unverified'): ?>
    <div class="alert" style="background:#FEF3C7;border:1px solid #FDE68A;color:#92400E;border-radius:10px;padding:14px 16px;font-size:13px;margin-bottom:16px">
      <strong>📧 Please verify your email first.</strong><br>
      Check your inbox for the verification link, or
      <a href="?resend=<?= urlencode($unverified_email) ?>" style="color:#b45309;font-weight:700">resend it</a>.
    </div>
  <?php elseif ($error==='invalid'): ?>
    <div class="alert alert-error">Invalid email or password.</div>
  <?php elseif ($error): ?>
    <div class="alert alert-error"><?=htmlspecialchars($error)?></div>
  <?php endif; ?>

  <form method="POST">
   <div class="form-group"><label>Email address</label><input type="email" name="email" class="form-control" required value="<?=htmlspecialchars($_POST['email']??'')?>"></div>
   <div class="form-group"><label>Password</label><input type="password" name="password" class="form-control" required></div>

<div style="text-align:right;margin-bottom:16px">
  <a href="forgot_password.php" style="font-size:12px;color:var(--t);font-weight:600;text-decoration:none">Forgot password?</a>
</div>

<button type="submit" class="btn-t btn-full" style="padding:14px;font-size:14px;border-radius:10px;margin-bottom:16px;background:linear-gradient(135deg,var(--t),#065F46)">Log in to Doctor Portal</button>
  </form>
  <p style="text-align:center;font-size:13px;color:var(--tx3)">New doctor? <a href="/medibook/doctor/register.php" style="color:var(--b);font-weight:700;text-decoration:none">Register here</a></p>
  <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--bd)"><a href="/medibook/patient/login.php" style="font-size:12px;color:var(--tx3);text-decoration:none">Patient login →</a></div>
 </div>
</div></body></html>