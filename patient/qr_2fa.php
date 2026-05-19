<?php
// patient/qr_2fa.php
// Shows a QR code encoding the current 2FA OTP for scanning
// ============================================================
session_start();

// Must have a pending reset 2FA session
if (empty($_SESSION['pending_reset_2fa']) || empty($_SESSION['pending_reset_2fa']['otp_code'])) {
  echo '<h2 style="text-align:center;margin-top:80px;font-family:sans-serif">Session expired. Please try again.</h2>';
  echo '<p style="text-align:center;margin-top:12px"><a href="/medibook/patient/forgot_password.php">Back to Forgot Password</a></p>';
  exit;
}

$otp  = $_SESSION['pending_reset_2fa']['otp_code'];
$email = $_SESSION['pending_reset_2fa']['email'];

// Mask email for display
$parts = explode('@', $email);
$masked = substr($parts[0], 0, 1) . str_repeat('•', max(3, strlen($parts[0]) - 1)) . '@' . $parts[1];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Scan QR Code — MediBook 2FA</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{
 font-family:'Outfit',sans-serif;
 min-height:100vh;
 display:flex;
 align-items:center;
 justify-content:center;
 background:linear-gradient(145deg,#0A1628 0%,#1A3A6B 50%,#1A6FD4 100%);
 padding:24px;
}
.qr-card{
 background:#fff;
 border-radius:24px;
 padding:48px 44px;
 max-width:420px;
 width:100%;
 text-align:center;
 box-shadow:0 20px 60px rgba(0,0,0,.3);
 animation:slideUp .5s ease-out;
}
@keyframes slideUp{
 from{opacity:0;transform:translateY(30px)}
 to{opacity:1;transform:translateY(0)}
}
.qr-icon{
 width:64px;height:64px;
 background:linear-gradient(135deg,#1A6FD4,#0D4FA0);
 border-radius:18px;
 display:flex;align-items:center;justify-content:center;
 margin:0 auto 20px;
 font-size:28px;
 box-shadow:0 6px 20px rgba(26,111,212,.35);
}
.qr-title{font-size:22px;font-weight:800;color:#0F172A;margin-bottom:6px}
.qr-sub{font-size:13px;color:#94A3B8;margin-bottom:28px;line-height:1.6}
.qr-box{
 background:#F8FAFC;
 border:2px dashed #E2E8F0;
 border-radius:16px;
 padding:28px;
 margin-bottom:24px;
 display:flex;
 align-items:center;
 justify-content:center;
}
#qrcode{display:inline-block}
#qrcode canvas{border-radius:8px}
.qr-instructions{
 background:linear-gradient(135deg,#EBF3FF,#F0F7FF);
 border:1.5px solid #D0E7FF;
 border-radius:12px;
 padding:16px;
 margin-bottom:20px;
}
.qr-step{
 display:flex;align-items:flex-start;gap:10px;
 text-align:left;margin-bottom:10px;
 font-size:13px;color:#475569;
}
.qr-step:last-child{margin-bottom:0}
.step-num{
 width:22px;height:22px;
 background:#1A6FD4;color:#fff;
 border-radius:50%;
 display:flex;align-items:center;justify-content:center;
 font-size:11px;font-weight:800;
 flex-shrink:0;
 margin-top:1px;
}
.timer{
 font-size:13px;color:#94A3B8;
 margin-bottom:12px;
}
.timer strong{
 color:#D97706;
 font-weight:700;
}
.close-hint{
 font-size:12px;
 color:#94A3B8;
 margin-top:8px;
}
.close-hint a{
 color:#1A6FD4;
 font-weight:600;
 text-decoration:none;
}
.pulse{
 animation:pulse 2s ease-in-out infinite;
}
@keyframes pulse{
 0%,100%{box-shadow:0 0 0 0 rgba(26,111,212,.3)}
 50%{box-shadow:0 0 0 12px rgba(26,111,212,0)}
}
</style>
</head>
<body>

<div class="qr-card">
 <div class="qr-icon"></div>
 <div class="qr-title">Scan QR Code</div>
 <div class="qr-sub">Open your phone camera or any QR scanner app<br>to reveal your verification code</div>

 <div class="qr-box pulse">
  <div id="qrcode"></div>
 </div>

 <div class="qr-instructions">
  <div class="qr-step">
   <div class="step-num">1</div>
   <div>Open the <strong>camera app</strong> or a QR scanner on your phone</div>
  </div>
  <div class="qr-step">
   <div class="step-num">2</div>
   <div>Point it at the QR code above to reveal your <strong>6-digit code</strong></div>
  </div>
  <div class="qr-step">
   <div class="step-num">3</div>
   <div>Go back to the verification tab and <strong>enter the code</strong></div>
  </div>
 </div>

 <div class="timer">Code expires in <strong id="countdown">5:00</strong></div>

 <div class="close-hint">
  Account: <strong><?= htmlspecialchars($masked) ?></strong><br>
  You can <a href="#" onclick="window.close();return false">close this tab</a> after scanning
 </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
// Generate QR code with the OTP
const otpCode = <?= json_encode($otp) ?>;
new QRCode(document.getElementById('qrcode'), {
 text: 'MediBook Verification Code: ' + otpCode,
 width: 200,
 height: 200,
 colorDark: '#0F172A',
 colorLight: '#ffffff',
 correctLevel: QRCode.CorrectLevel.H
});

// Countdown timer
(function(){
 let seconds = 5 * 60;
 const el = document.getElementById('countdown');
 const tick = setInterval(() => {
  seconds--;
  if (seconds <= 0) {
   clearInterval(tick);
   el.textContent = 'Expired';
   el.style.color = '#DC2626';
   return;
  }
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  el.textContent = m + ':' + String(s).padStart(2, '0');
  if (seconds <= 60) el.style.color = '#DC2626';
 }, 1000);
})();
</script>
</body>
</html>
