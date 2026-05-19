<?php
// doctor/register.php — DABS-09 (T09-1 to T09-4)
session_start();
if (!empty($_SESSION['doctor_id'])) { header('Location: /medibook/doctor/dashboard.php'); exit; }
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
$error=''; $success='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $name  = clean($conn,$_POST['name']??'');
  $email = clean($conn,$_POST['email']??'');
  $spec  = clean($conn,$_POST['specialisation']??'');
  $qual  = clean($conn,$_POST['qualification']??'');
  $phone = clean($conn,$_POST['phone']??'');
  $clinic = clean($conn,$_POST['clinic_name']??'');
  $pw   = $_POST['password']??'';
  $cpw  = $_POST['confirm_password']??'';
  if (!$name||!$email||!$spec||!$pw) $error='All required fields must be filled.';
  elseif (!filter_var($_POST['email'],FILTER_VALIDATE_EMAIL)) $error='Invalid email address.';
  elseif (strlen($pw)<6) $error='Password must be at least 6 characters.';
  elseif ($pw!==$cpw) $error='Passwords do not match.';
  else {
    $check=$conn->query("SELECT id FROM doctors WHERE email='$email' LIMIT 1");
    if ($check->num_rows>0) $error='Email already registered.';
    else {
      $hashed=password_hash($pw,PASSWORD_DEFAULT);
      // T09-2: INSERT with status=pending, email_verified=0
      $conn->query("INSERT INTO doctors (name,email,password,specialisation,qualification,clinic_name,clinic_phone,status,email_verified) VALUES ('$name','$email','$hashed','$spec','$qual','$clinic','$clinic_phone','pending',0)");
      // DABSTN-82: Generate verification token and log link
      $new_id = $conn->insert_id;
      sendVerificationEmail($conn, $new_id, 'doctor', $email);
      $success='Registration submitted! Please also verify your email (check your inbox). An admin will then review and approve your profile.'; // T09-3
    }
  }
}
$page_title='Doctor Registration'; include '../includes/header.php';
?>
<div class="auth-split">
 <div class="auth-left">
 <div class="flex items-center" style="gap:9px;margin-bottom:28px"><div class="logo-i">M</div><span class="logo-n-white">MediBook</span></div>
 <h1 style="font-size:34px;font-weight:800;color:#fff;line-height:1.2;margin-bottom:14px">Join as a Doctor</h1>
 <p style="font-size:14px;color:rgba(255,255,255,.65);line-height:1.75;margin-bottom:36px">Reach more patients across Nepal. Register and get approved to start accepting bookings.</p>
 <div class="alert" style="background:rgba(245,158,11,.2);color:#FDE68A;border:1px solid rgba(245,158,11,.3);margin-bottom:0"> After registration, an admin will review and approve your profile before you can log in.</div>
 </div>
 <div class="auth-right" style="overflow-y:auto">
 <h2 style="font-size:20px;font-weight:800;color:var(--tx);margin-bottom:6px">Doctor Registration</h2>
 <p style="font-size:13px;color:var(--tx3);margin-bottom:20px">Fill in your professional details</p>
 <?php if($error): ?><div class="alert alert-error">⚠️ <?=$error?></div><?php endif; ?>
 <?php if($success): ?>
  <div class="alert alert-success"> <?=$success?><br><a href="/medibook/doctor/login.php" style="color:#065F46;font-weight:700">Go to login →</a></div>
  <?php if (!empty($_SESSION['demo_verification_link'])): ?>
   <div style="background:var(--b3);border:1px solid var(--b4);border-radius:10px;padding:16px;margin-bottom:20px">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--b);margin-bottom:8px">Demo mode — verification link (normally sent by email)</div>
    <div style="font-size:12px;color:var(--tx2);margin-bottom:10px">Click the link below to verify your email address:</div>
    <a href="<?= htmlspecialchars($_SESSION['demo_verification_link']) ?>" style="font-size:13px;color:var(--b);font-weight:700;word-break:break-all"><?= htmlspecialchars($_SESSION['demo_verification_link']) ?></a>
   </div>
   <a href="<?= htmlspecialchars($_SESSION['demo_verification_link']) ?>" class="btn-p btn-full" style="display:block;text-align:center;padding:13px;font-size:14px;border-radius:10px;margin-bottom:14px;text-decoration:none">Verify my email &rarr;</a>
   <?php unset($_SESSION['demo_verification_link'], $_SESSION['demo_verification_email']); ?>
  <?php endif; ?>
 <?php endif; ?>

 <?php if(!$success): ?>
 <form method="POST">
  <div class="grid-2" style="gap:12px"><div class="form-group"><label>Full name *</label><input type="text" name="name" class="form-control" required value="<?=htmlspecialchars($_POST['name']??'')?>"></div><div class="form-group"><label>Specialisation *</label><input type="text" name="specialisation" class="form-control" required value="<?=htmlspecialchars($_POST['specialisation']??'')?>"></div></div>
  <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-control" required value="<?=htmlspecialchars($_POST['email']??'')?>"></div>
  <div class="grid-2" style="gap:12px"><div class="form-group"><label>Qualification</label><input type="text" name="qualification" class="form-control" placeholder="MBBS, MD..." value="<?=htmlspecialchars($_POST['qualification']??'')?>"></div><div class="form-group"><label>Phone</label><input type="tel" name="phone" class="form-control" value="<?=htmlspecialchars($_POST['phone']??'')?>"></div></div>
  <div class="form-group"><label>Clinic / Hospital name</label><input type="text" name="clinic_name" class="form-control" value="<?=htmlspecialchars($_POST['clinic_name']??'')?>"></div>
  <div class="grid-2" style="gap:12px"><div class="form-group"><label>Password *</label><input type="password" name="password" class="form-control" required></div><div class="form-group"><label>Confirm password *</label><input type="password" name="confirm_password" class="form-control" required></div></div>
  <button type="submit" class="btn-p btn-full" style="padding:13px;font-size:14px;border-radius:10px">Submit registration</button>
 </form>
 <p style="text-align:center;font-size:13px;color:var(--tx3);margin-top:14px">Already approved? <a href="/medibook/doctor/login.php" style="color:var(--b);font-weight:700;text-decoration:none">Log in</a></p>
 <?php endif; ?>
 </div>
</div></body></html>
