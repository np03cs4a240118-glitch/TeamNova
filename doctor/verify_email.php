<?php
// doctor/verify_email.php — DABSTN-82
// Handles the verification link clicked from the doctor's email (or log file)
session_start();
require_once '../config/db_connect.php';

$token  = trim($_GET['token'] ?? '');
$status = 'invalid'; // 'verified' | 'already' | 'invalid'

if ($token && strlen($token) === 64) {
    $t = $conn->real_escape_string($token);
    $r = $conn->query("SELECT id, name, email_verified, status AS acct_status FROM doctors WHERE verification_token='$t' LIMIT 1");
    if ($r && $r->num_rows === 1) {
        $row = $r->fetch_assoc();
        if ($row['email_verified'] == 1) {
            $status = 'already';
        } else {
            $conn->query("UPDATE doctors SET email_verified=1, verification_token=NULL WHERE id={$row['id']}");
            $status = 'verified';
            $pending = ($row['acct_status'] === 'pending');
        }
    }
}

$page_title = 'Email Verification';
include '../includes/header.php';
?>
<div style="min-height:80vh;display:flex;align-items:center;justify-content:center;padding:40px 20px">
  <div style="max-width:460px;width:100%;text-align:center">
    <?php if ($status === 'verified'): ?>
      <div style="width:72px;height:72px;background:linear-gradient(135deg,#34D399,#059669);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 24px">✓</div>
      <h1 style="font-size:26px;font-weight:800;color:var(--tx);margin-bottom:10px">Email Verified!</h1>
      <?php if (!empty($pending)): ?>
        <p style="font-size:14px;color:var(--tx3);line-height:1.7;margin-bottom:28px">Your email is verified. Your account is still <strong>pending admin approval</strong> — you'll be able to log in once an admin reviews your profile.</p>
      <?php else: ?>
        <p style="font-size:14px;color:var(--tx3);line-height:1.7;margin-bottom:28px">Your email has been verified and your account is approved. You can now log in.</p>
      <?php endif; ?>
      <a href="/medibook/doctor/login.php" class="btn-t" style="padding:12px 32px;font-size:14px;border-radius:10px;text-decoration:none;display:inline-block;background:linear-gradient(135deg,var(--t),#065F46);color:#fff">Go to Doctor Login →</a>

    <?php elseif ($status === 'already'): ?>
      <div style="width:72px;height:72px;background:linear-gradient(135deg,#60A5FA,#2563EB);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 24px">ℹ</div>
      <h1 style="font-size:26px;font-weight:800;color:var(--tx);margin-bottom:10px">Already Verified</h1>
      <p style="font-size:14px;color:var(--tx3);line-height:1.7;margin-bottom:28px">Your email is already verified.</p>
      <a href="/medibook/doctor/login.php" class="btn-t" style="padding:12px 32px;font-size:14px;border-radius:10px;text-decoration:none;display:inline-block;background:linear-gradient(135deg,var(--t),#065F46);color:#fff">Go to Doctor Login →</a>

    <?php else: ?>
      <div style="width:72px;height:72px;background:linear-gradient(135deg,#F87171,#DC2626);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 24px">✕</div>
      <h1 style="font-size:26px;font-weight:800;color:var(--tx);margin-bottom:10px">Invalid Link</h1>
      <p style="font-size:14px;color:var(--tx3);line-height:1.7;margin-bottom:28px">This verification link is invalid or has already been used. Please re-register or contact support.</p>
      <a href="/medibook/doctor/register.php" class="btn-t" style="padding:12px 32px;font-size:14px;border-radius:10px;text-decoration:none;display:inline-block;background:linear-gradient(135deg,var(--t),#065F46);color:#fff">Back to Register</a>
    <?php endif; ?>
  </div>
</div>
</body></html>
