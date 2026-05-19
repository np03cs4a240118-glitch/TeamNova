<?php
// patient/forgot_password.php — Forgot Password
session_start();
if (!empty($_SESSION['patient_id'])) {
    header('Location: /medibook/patient/dashboard.php'); exit;
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
        $r = $conn->query("SELECT id, name, email FROM patients WHERE email='$email' LIMIT 1");
        if ($r->num_rows === 0) {
            $success = 'If that email is registered, a reset link has been sent.';
        } else {
            $row = $r->fetch_assoc();

            // 2FA: Generate OTP and redirect to verification page
            $otp_code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $otp_token   = bin2hex(random_bytes(32));
            $otp_expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));
            $pid = (int)$row['id'];
            $t = $conn->real_escape_string($otp_token);
            $conn->query("UPDATE patients SET otp_code='$otp_code', otp_token='$t', otp_expires='$otp_expires' WHERE id=$pid");

            // Store pending 2FA info in session for forgot-password flow
            $_SESSION['pending_reset_2fa'] = [
                'patient_id'   => $row['id'],
                'patient_name' => $row['name'],
                'otp_token'    => $otp_token,
                'email'        => $row['email'],
                'otp_code'     => $otp_code   // demo mode — shown via QR
            ];

            header('Location: /medibook/patient/verify_2fa.php');
            exit;
        }
    }
}

$page_title = 'Forgot Password';
$extra_css  = ['auth.css'];
include '../includes/header.php';
?>
<div style="min-height:100vh;background:linear-gradient(145deg,#0A1628,#1A3A6B 55%,#1A6FD4);display:flex;align-items:center;justify-content:center;padding:40px 20px">
 <div style="width:100%;max-width:440px">
  <div class="flex items-center gap-3" style="justify-content:center;margin-bottom:32px">
   <div class="logo-i">M</div>
   <span class="logo-n-white">MediBook</span>
  </div>
  <div style="background:#fff;border-radius:16px;padding:36px">
   <div style="text-align:center;margin-bottom:24px">
    <div style="font-size:40px;margin-bottom:12px"></div>
    <h1 style="font-size:22px;font-weight:800;color:var(--tx);margin-bottom:6px">Forgot your password?</h1>
    <p style="font-size:13px;color:var(--tx3)">Enter your email and we'll verify your identity before resetting</p>
   </div>
   <?php if ($error): ?><div class="alert alert-error"><?=htmlspecialchars($error)?></div><?php endif; ?>
   <?php if ($success): ?>
    <div class="alert alert-success"><?=htmlspecialchars($success)?></div>
   <?php else: ?>
    <form method="POST" action="">
     <div class="form-group">
      <label>Email address</label>
      <input type="email" name="email" class="form-control" placeholder="Enter your registered email" required value="<?=htmlspecialchars($_POST['email'] ?? '')?>">
     </div>
     <button type="submit" class="btn-p btn-full" style="padding:13px;font-size:14px;border-radius:10px;margin-bottom:16px">Continue</button>
    </form>
   <?php endif; ?>
   <p style="text-align:center;font-size:13px;color:var(--tx3)">Remember your password? <a href="login.php" style="color:var(--b);font-weight:700;text-decoration:none">Back to login</a></p>
  </div>
 </div>
</div>
<?php include '../includes/footer.php'; ?>
