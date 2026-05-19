<?php
// patient/reset_password.php — Reset Password
session_start();
if (!empty($_SESSION['patient_id'])) {
    header('Location: /medibook/patient/dashboard.php'); exit;
}
require_once '../config/db_connect.php';
require_once '../includes/functions.php';

$error = ''; $success = ''; $valid = false;
$token = clean($conn, $_GET['token'] ?? $_POST['token'] ?? '');
if (!$token) { header('Location: forgot_password.php'); exit; }

$now = date('Y-m-d H:i:s');
$r   = $conn->query("SELECT id, name FROM patients WHERE reset_token='$token' AND reset_expires > '$now' LIMIT 1");

if ($r->num_rows === 0) {
    $error = 'This reset link is invalid or has expired. Please request a new one.';
} else {
    $patient = $r->fetch_assoc();
    $valid   = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $password = $_POST['password']         ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $id     = (int)$patient['id'];
        $conn->query("UPDATE patients SET password='$hashed', reset_token=NULL, reset_expires=NULL WHERE id=$id");
        $success = 'Password reset successfully! You can now log in.';
        $valid   = false;
    }
}

$page_title = 'Reset Password';
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
   <?php if ($success): ?>
    <div style="text-align:center">
     <div style="font-size:48px;margin-bottom:16px"></div>
     <h1 style="font-size:22px;font-weight:800;color:var(--tx);margin-bottom:8px">Password Reset!</h1>
     <p style="font-size:14px;color:var(--tx3);margin-bottom:20px">Your password has been updated successfully.</p>
     <div class="alert alert-success"><?=htmlspecialchars($success)?></div>
     <a href="login.php" class="btn-p btn-full" style="padding:13px;font-size:14px;border-radius:10px">Log in with new password &rarr;</a>
    </div>
   <?php elseif (!$valid): ?>
    <div style="text-align:center">
     <div style="font-size:48px;margin-bottom:16px"></div>
     <h1 style="font-size:22px;font-weight:800;color:var(--tx);margin-bottom:8px">Link expired</h1>
     <div class="alert alert-error"><?=htmlspecialchars($error)?></div>
     <a href="forgot_password.php" class="btn-p btn-full" style="padding:13px;font-size:14px;border-radius:10px">Request a new link</a>
    </div>
   <?php else: ?>
    <div style="text-align:center;margin-bottom:24px">
     <div style="font-size:40px;margin-bottom:12px">&#128274;</div>
     <h1 style="font-size:22px;font-weight:800;color:var(--tx);margin-bottom:6px">Set new password</h1>
     <p style="font-size:13px;color:var(--tx3)">Hi <?=htmlspecialchars($patient['name'])?>, choose a strong password</p>
    </div>
    <?php if ($error): ?><div class="alert alert-error"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <form method="POST" action="">
     <input type="hidden" name="token" value="<?=htmlspecialchars($token)?>">
     <div class="form-group" style="position:relative">
      <label>New password *</label>
      <input type="password" name="password" id="password" class="form-control" placeholder="At least 6 characters" required>
      <button type="button" onclick="togglePw('password',this)" style="position:absolute;right:12px;top:35px;background:none;border:none;color:var(--tx3);cursor:pointer;font-size:12px;font-family:'Outfit',sans-serif">Show</button>
      <div style="height:4px;background:var(--bg2);border-radius:3px;margin-top:8px;overflow:hidden"><div id="strengthBar" style="height:100%;width:0%;border-radius:3px;transition:all .3s"></div></div>
      <div id="strengthLabel" style="font-size:11px;font-weight:600;margin-top:3px"></div>
     </div>
     <div class="form-group" style="position:relative">
      <label>Confirm new password *</label>
      <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Repeat new password" required>
      <button type="button" onclick="togglePw('confirm_password',this)" style="position:absolute;right:12px;top:35px;background:none;border:none;color:var(--tx3);cursor:pointer;font-size:12px;font-family:'Outfit',sans-serif">Show</button>
      <div id="matchMsg" style="font-size:12px;margin-top:5px"></div>
     </div>
     <button type="submit" class="btn-p btn-full" style="padding:13px;font-size:14px;border-radius:10px;margin-bottom:14px">Reset password</button>
    </form>
    <p style="text-align:center;font-size:13px;color:var(--tx3)"><a href="login.php" style="color:var(--b);font-weight:700;text-decoration:none">&larr; Back to login</a></p>
   <?php endif; ?>
  </div>
 </div>
</div>
<script>
document.getElementById('password')?.addEventListener('input',function(){
 var v=this.value,score=0;
 if(v.length>=6)score++;if(/[A-Z]/.test(v))score++;if(/[0-9]/.test(v))score++;if(/[^a-zA-Z0-9]/.test(v))score++;
 var c=['','#EF4444','#F59E0B','#10B981','#059669'],l=['','Weak','Fair','Good','Strong'],w=['0%','25%','50%','75%','100%'];
 document.getElementById('strengthBar').style.width=w[score];
 document.getElementById('strengthBar').style.background=c[score];
 document.getElementById('strengthLabel').textContent=score>0?l[score]:'';
 document.getElementById('strengthLabel').style.color=c[score];
});
document.getElementById('confirm_password')?.addEventListener('input',function(){
 var pw=document.getElementById('password').value,msg=document.getElementById('matchMsg');
 if(this.value===pw&&this.value!==''){msg.textContent='✓ Passwords match';msg.style.color='var(--t)';}
 else if(this.value.length>0){msg.textContent='✗ Passwords do not match';msg.style.color='var(--r)';}
 else msg.textContent='';
});
function togglePw(id,btn){var f=document.getElementById(id);f.type=f.type==='password'?'text':'password';btn.textContent=f.type==='password'?'Show':'Hide';}
</script>
<?php include '../includes/footer.php'; ?>
