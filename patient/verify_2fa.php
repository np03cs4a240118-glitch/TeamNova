<?php
// patient/verify_2fa.php
// Two-Factor Authentication — OTP verification for forgot-password flow
// ============================================================
session_start();
require_once '../config/db_connect.php';

// Must have a pending reset 2FA session from forgot_password.php
if (empty($_SESSION['pending_reset_2fa'])) {
  header('Location: /medibook/patient/forgot_password.php');
  exit;
}

$pending = $_SESSION['pending_reset_2fa'];
$error  = '';
$success = '';

// ── Handle OTP verification ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
  $entered_otp = trim($_POST['otp_code'] ?? '');

  if (strlen($entered_otp) !== 6 || !ctype_digit($entered_otp)) {
    $error = 'Please enter a valid 6-digit code.';
  } else {
    $pid = (int)$pending['patient_id'];
    $token_safe = $conn->real_escape_string($pending['otp_token']);
    $result = $conn->query(
      "SELECT otp_code, otp_expires FROM patients WHERE id=$pid AND otp_token='$token_safe' LIMIT 1"
    );

    if ($result && $result->num_rows === 1) {
      $row = $result->fetch_assoc();

      // Check expiry
      if (strtotime($row['otp_expires']) < time()) {
        $error = 'This code has expired. Please request a new one.';
      } elseif ($entered_otp === $row['otp_code']) {
        // OTP valid — generate reset token and redirect to reset page
        // Clear OTP columns
        $conn->query("UPDATE patients SET otp_code=NULL, otp_token=NULL, otp_expires=NULL WHERE id=$pid");

        // Generate reset token
        $reset_token  = bin2hex(random_bytes(32));
        $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $t = $conn->real_escape_string($reset_token);
        $conn->query("UPDATE patients SET reset_token='$t', reset_expires='$reset_expires' WHERE id=$pid");

        // Clean up pending 2FA data
        unset($_SESSION['pending_reset_2fa']);

        // Redirect to reset password page
        header("Location: /medibook/patient/reset_password.php?token=$reset_token");
        exit;
      } else {
        $error = 'Incorrect code. Please try again.';
      }
    } else {
      $error = 'Session expired. Please try again.';
      unset($_SESSION['pending_reset_2fa']);
    }
  }
}

// ── Handle Resend OTP ───────────────────────────────────────
if (isset($_POST['resend_otp'])) {
  $pid = (int)$pending['patient_id'];
  $new_otp   = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
  $new_token  = bin2hex(random_bytes(32));
  $new_expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));
  $t = $conn->real_escape_string($new_token);
  $conn->query("UPDATE patients SET otp_code='$new_otp', otp_token='$t', otp_expires='$new_expires' WHERE id=$pid");

  // Update session with new token & code
  $_SESSION['pending_reset_2fa']['otp_token'] = $new_token;
  $_SESSION['pending_reset_2fa']['otp_code'] = $new_otp;
  $pending = $_SESSION['pending_reset_2fa'];

  $success = 'A new verification code has been generated.';
}

$page_title = 'Verify Identity';
include '../includes/header.php';

// Mask email for display: t***@example.com
$email_parts = explode('@', $pending['email']);
$masked = substr($email_parts[0], 0, 1) . str_repeat('•', max(3, strlen($email_parts[0]) - 1)) . '@' . $email_parts[1];
?>
<div class="auth-split">
 <div class="auth-left">
  <div class="flex items-center" style="gap:9px;margin-bottom:30px">
   <div class="logo-i">M</div>
   <span class="logo-n-white">MediBook</span>
  </div>
  <h1 style="font-size:36px;font-weight:800;color:#fff;line-height:1.15;margin-bottom:14px;letter-spacing:-.5px">Verify your identity.</h1>
  <p style="font-size:14px;color:rgba(255,255,255,.65);line-height:1.75;margin-bottom:36px;max-width:320px">For your security, we need to verify your identity before allowing you to reset your password.</p>
  <div style="display:flex;flex-direction:column;gap:14px;margin-bottom:32px">
   <div style="display:flex;align-items:center;gap:14px">
    <div style="width:38px;height:38px;background:rgba(13,158,122,.25);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0"></div>
    <div><div style="font-size:13px;font-weight:700;color:#fff;margin-bottom:2px">Two-Factor Authentication</div><div style="font-size:12px;color:rgba(255,255,255,.5)">Extra layer of protection for your account</div></div>
   </div>
   <div style="display:flex;align-items:center;gap:14px">
    <div style="width:38px;height:38px;background:rgba(245,158,11,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">⏱️</div>
    <div><div style="font-size:13px;font-weight:700;color:#fff;margin-bottom:2px">Code expires in 5 minutes</div><div style="font-size:12px;color:rgba(255,255,255,.5)">Request a new code if it expires</div></div>
   </div>
   <div style="display:flex;align-items:center;gap:14px">
    <div style="width:38px;height:38px;background:rgba(139,92,246,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0"></div>
    <div><div style="font-size:13px;font-weight:700;color:#fff;margin-bottom:2px">Scan QR code</div><div style="font-size:12px;color:rgba(255,255,255,.5)">Use your phone camera to reveal the code</div></div>
   </div>
  </div>
 </div>

 <div class="auth-right">
  <div style="max-width:400px;margin:0 auto;width:100%">
   <div style="text-align:center;margin-bottom:28px">
    <div style="width:64px;height:64px;background:linear-gradient(135deg,var(--b),var(--b2));border-radius:18px;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:28px;box-shadow:0 6px 20px rgba(26,111,212,.3)"></div>
    <h2 style="font-size:22px;font-weight:800;color:var(--tx);margin-bottom:6px">Verify your identity</h2>
    <p style="font-size:13px;color:var(--tx3)">Scan the QR code to get your 6-digit verification code</p>
   </div>

   <!-- Button to open QR code in a new tab -->
   <div style="margin-bottom:20px">
    <a href="/medibook/patient/qr_2fa.php" target="_blank" id="qr-tab-btn"
      style="display:flex;align-items:center;justify-content:center;gap:10px;
         background:linear-gradient(135deg,#EBF3FF,#F0F7FF);border:1.5px solid var(--b4);
         border-radius:12px;padding:16px;text-decoration:none;
         transition:all .2s;cursor:pointer"
      onmouseover="this.style.borderColor='var(--b)';this.style.boxShadow='0 4px 16px rgba(26,111,212,.15)'"
      onmouseout="this.style.borderColor='var(--b4)';this.style.boxShadow='none'">
     <div style="width:42px;height:42px;background:linear-gradient(135deg,var(--b),var(--b2));border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;box-shadow:0 3px 10px rgba(26,111,212,.3)"></div>
     <div style="text-align:left">
      <div style="font-size:14px;font-weight:700;color:var(--tx)">Open QR Code</div>
      <div style="font-size:11px;color:var(--tx3)">Opens in a new tab — scan with your phone camera</div>
     </div>
     <div style="font-size:16px;color:var(--b);margin-left:auto">↗</div>
    </a>
   </div>

   <?php if ($success): ?>
    <div class="alert alert-success"> <?= htmlspecialchars($success) ?></div>
   <?php endif; ?>

   <?php if ($error): ?>
    <div class="alert alert-error"> <?= htmlspecialchars($error) ?></div>
   <?php endif; ?>

   <form method="POST" action="">
    <div class="form-group">
     <label>Verification Code</label>
     <div style="display:flex;gap:8px">
      <?php for ($i = 0; $i < 6; $i++): ?>
       <input type="text"
           class="otp-digit form-control"
           maxlength="1"
           inputmode="numeric"
           pattern="[0-9]"
           style="text-align:center;font-size:22px;font-weight:800;padding:0;height:56px;width:100%;letter-spacing:0"
           required
           <?= $i === 0 ? 'autofocus' : '' ?>
       >
      <?php endfor; ?>
     </div>
     <!-- Hidden field holds the combined OTP value -->
     <input type="hidden" name="otp_code" id="otp_combined">
    </div>

    <button type="submit" name="verify_otp" class="btn-p btn-full" style="padding:14px;font-size:14px;border-radius:10px;margin-bottom:14px">Verify &amp; Reset Password</button>
   </form>

   <form method="POST" action="" style="text-align:center;margin-bottom:16px">
    <button type="submit" name="resend_otp" class="btn-g btn-full" style="padding:12px;font-size:13px;border-radius:10px"> Generate New Code</button>
    <div style="font-size:11px;color:var(--tx3);margin-top:6px">This will invalidate the current QR — reopen the QR tab after</div>
   </form>

   <div style="text-align:center">
    <a href="/medibook/patient/forgot_password.php" style="font-size:12px;color:var(--tx3);text-decoration:none">&larr; Back to forgot password</a>
   </div>
  </div>
 </div>
</div>

<script>
// ── Auto-open QR tab on first load ──────────────────────────
(function(){
 if (!sessionStorage.getItem('qr_2fa_opened')) {
  var qrWin = window.open('/medibook/patient/qr_2fa.php', '_blank');
  if (qrWin) sessionStorage.setItem('qr_2fa_opened', '1');
 }
})();

// ── OTP input auto-advance & combine ────────────────────────
(function(){
 const digits = document.querySelectorAll('.otp-digit');
 const hidden = document.getElementById('otp_combined');

 function combine(){
  hidden.value = Array.from(digits).map(d => d.value).join('');
 }

 digits.forEach((el, i) => {
  el.addEventListener('input', () => {
   el.value = el.value.replace(/\D/g, '').slice(0,1);
   combine();
   if (el.value && i < digits.length - 1) digits[i+1].focus();
  });

  el.addEventListener('keydown', (e) => {
   if (e.key === 'Backspace' && !el.value && i > 0) {
    digits[i-1].focus();
    digits[i-1].value = '';
    combine();
   }
  });

  // Handle paste of full OTP
  el.addEventListener('paste', (e) => {
   e.preventDefault();
   const pasted = (e.clipboardData.getData('text') || '').replace(/\D/g,'').slice(0,6);
   pasted.split('').forEach((ch, idx) => {
    if (digits[idx]) digits[idx].value = ch;
   });
   combine();
   if (pasted.length > 0) digits[Math.min(pasted.length, digits.length) - 1].focus();
  });
 });
})();
</script>
</body>
</html>
