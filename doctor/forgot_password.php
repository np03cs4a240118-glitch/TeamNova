<?php
// doctor/forgot_password.php — Forgot Password
session_start();
if (!empty($_SESSION['doctor_id'])) {
    header('Location: /medibook/doctor/dashboard.php'); exit;
}
require_once '../config/db_connect.php';
require_once '../includes/functions.php';

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean($conn, $_POST['email'] ?? '');
    if (!$email) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $r = $conn->query("SELECT id, name FROM doctors WHERE email='$email' LIMIT 1");
        if ($r->num_rows === 0) {
            $success = 'If that email is registered, a reset link has been sent.';
        } else {
            $row     = $r->fetch_assoc();
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $conn->query("UPDATE doctors SET reset_token='$token', reset_expires='$expires' WHERE id={$row['id']}");
            $reset_link = "http://localhost/medibook/doctor/reset_password.php?token=$token";
            $success = 'Reset link generated!';
            $_SESSION['demo_reset_link'] = $reset_link;
            $_SESSION['demo_reset_name'] = $row['name'];
        }
    }
}

$page_title = 'Forgot Password';
$extra_css  = ['auth.css'];
include '../includes/header.php';
?>
<div class="auth-split">
 <div class="auth-left">
  <div class="flex items-center" style="gap:9px;margin-bottom:28px"><div class="logo-i">M</div><span class="logo-n-white">MediBook</span></div>
  <h1 style="font-size:36px;font-weight:800;color:#fff;line-height:1.15;margin-bottom:14px">Doctor Portal</h1>
  <p style="font-size:14px;color:rgba(255,255,255,.65);line-height:1.75;margin-bottom:32px">Manage your appointments, schedule, and patient interactions all in one place.</p>
 </div>
 <div class="auth-right">
  <div style="text-align:center;margin-bottom:24px">

   <h1 style="font-size:22px;font-weight:800;color:var(--tx);margin-bottom:6px">Forgot your password?</h1>
   <p style="font-size:13px;color:var(--tx3)">Enter your email and we will send you a reset link</p>
  </div>
  <?php if ($error): ?><div class="alert alert-error">&#9888;&#65039; <?=htmlspecialchars($error)?></div><?php endif; ?>
  <?php if ($success): ?>
   <div class="alert alert-success">&#9989; <?=htmlspecialchars($success)?></div>
   <?php if (!empty($_SESSION['demo_reset_link'])): ?>
    <div style="background:var(--b3);border:1px solid var(--b4);border-radius:10px;padding:16px;margin-bottom:20px">
     <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--b);margin-bottom:8px">Demo mode - reset link (normally sent by email)</div>
     <div style="font-size:12px;color:var(--tx2);margin-bottom:10px">Hi <?=htmlspecialchars($_SESSION['demo_reset_name'])?>, click the link below to reset your password:</div>
     <a href="<?=htmlspecialchars($_SESSION['demo_reset_link'])?>" style="font-size:13px;color:var(--b);font-weight:700;word-break:break-all"><?=htmlspecialchars($_SESSION['demo_reset_link'])?></a>
    </div>
    <a href="<?=htmlspecialchars($_SESSION['demo_reset_link'])?>" class="btn-t btn-full" style="padding:14px;font-size:14px;border-radius:10px;margin-bottom:16px;background:linear-gradient(135deg,var(--t),#065F46);text-decoration:none;display:inline-block;text-align:center;box-sizing:border-box">Reset my password &rarr;</a>
    <?php unset($_SESSION['demo_reset_link'], $_SESSION['demo_reset_name']); ?>
   <?php endif; ?>
  <?php else: ?>
   <form method="POST" action="">
    <div class="form-group">
     <label>Email address</label>
     <input type="email" name="email" class="form-control" placeholder="Enter your registered email" required value="<?=htmlspecialchars($_POST['email'] ?? '')?>">
    </div>
    <button type="submit" class="btn-t btn-full" style="padding:14px;font-size:14px;border-radius:10px;margin-bottom:16px;background:linear-gradient(135deg,var(--t),#065F46)">Send reset link</button>
   </form>
  <?php endif; ?>
  <p style="text-align:center;font-size:13px;color:var(--tx3)">Remember your password? <a href="login.php" style="color:var(--t);font-weight:700;text-decoration:none">Back to login</a></p>
 </div>
</div>
<?php include '../includes/footer.php'; ?>
