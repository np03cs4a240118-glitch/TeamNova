<?php
// index.php — MediBook Landing Page
$page_title = 'Book Doctor Appointments in Nepal';
include 'includes/header.php';
$msg = $_GET['msg'] ?? '';
?>
<style>
.hero{background:linear-gradient(145deg,#0A1628,#1A3A6B 55%,#1A6FD4);min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 40px;text-align:center}
.spec-pill{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:999px;padding:8px 18px;font-size:13px;color:rgba(255,255,255,.85);font-weight:500;display:inline-block;margin:5px;cursor:pointer;transition:background .15s}
.spec-pill:hover{background:rgba(255,255,255,.2)}
.flow-card{background:var(--w);border:1px solid var(--bd);border-radius:16px;padding:32px 28px;text-align:center;cursor:pointer;transition:all .2s;text-decoration:none}
.flow-card:hover{box-shadow:0 12px 40px rgba(0,0,0,.12);transform:translateY(-3px)}
</style>

<div class="hero">
 <!-- Nav -->
 <div style="position:fixed;top:0;left:0;right:0;z-index:100;background:rgba(10,22,40,.95);backdrop-filter:blur(12px);border-bottom:1px solid rgba(255,255,255,.1);height:60px;display:flex;align-items:center;justify-content:space-between;padding:0 44px">
  <div class="flex items-center gap-2"><div class="logo-i">M</div><span style="font-size:16px;font-weight:800;color:#F8FAFC">MediBook</span></div>
  <div class="flex gap-2"><a href="patient/login.php" class="btn-g" style="border-color:rgba(255,255,255,.2);color:#94A3B8">Patient login</a><a href="doctor/login.php" class="btn-g" style="border-color:rgba(255,255,255,.2);color:#94A3B8">Doctor login</a><a href="admin/login.php" class="btn-g" style="border-color:rgba(255,255,255,.2);color:#94A3B8">Admin</a><a href="patient/register.php" class="btn-p">Sign up free</a></div>
 </div>

 <?php if($msg==='account_deleted'): ?><div class="alert alert-success" style="position:fixed;top:70px;left:50%;transform:translateX(-50%);z-index:200">Account deleted successfully.</div><?php endif; ?>

 <div style="margin-top:60px;max-width:700px">
  <div style="display:inline-block;background:rgba(13,158,122,.2);border:1px solid rgba(13,158,122,.3);border-radius:999px;padding:6px 18px;font-size:13px;color:#34D399;font-weight:600;margin-bottom:22px"> Nepal's online doctor booking platform</div>
  <h1 style="font-size:54px;font-weight:800;color:#fff;line-height:1.1;margin-bottom:18px;letter-spacing:-.5px">Book doctors,<br>skip the queue.</h1>
  <p style="font-size:18px;color:rgba(255,255,255,.65);line-height:1.7;margin-bottom:40px;max-width:520px;margin-left:auto;margin-right:auto">Search verified doctors, pick a time, confirm in 2 minutes. Free for patients.</p>
  <div class="flex gap-3" style="justify-content:center;margin-bottom:50px;flex-wrap:wrap">
   <a href="patient/register.php" class="btn-p" style="padding:14px 32px;font-size:16px;border-radius:12px">Get started free</a>
   <a href="doctor/register.php" class="btn-g" style="padding:14px 32px;font-size:16px;border-radius:12px;border-color:rgba(255,255,255,.3);color:#fff">Register as doctor</a>
  </div>
  <div style="display:flex;gap:12px;justify-content:center;margin-bottom:50px">
   <span class="spec-pill"> Cardiology</span>
   <span class="spec-pill"> Neurology</span>
   <span class="spec-pill"> Dentistry</span>
   <span class="spec-pill"> Dermatology</span>
   <span class="spec-pill"> Pediatrics</span>
   <span class="spec-pill"> Orthopedics</span>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px;max-width:600px;margin:0 auto">
   <div style="text-align:center;padding:20px;background:rgba(255,255,255,.07);border-radius:14px;border:1px solid rgba(255,255,255,.12)"><div style="font-size:26px;font-weight:800;color:#fff;margin-bottom:4px">50+</div><div style="font-size:12px;color:rgba(255,255,255,.6)">Verified doctors</div></div>
   <div style="text-align:center;padding:20px;background:rgba(255,255,255,.07);border-radius:14px;border:1px solid rgba(255,255,255,.12)"><div style="font-size:26px;font-weight:800;color:#fff;margin-bottom:4px">2 min</div><div style="font-size:12px;color:rgba(255,255,255,.6)">Average booking</div></div>
   <div style="text-align:center;padding:20px;background:rgba(255,255,255,.07);border-radius:14px;border:1px solid rgba(255,255,255,.12)"><div style="font-size:26px;font-weight:800;color:#fff;margin-bottom:4px">Trusted</div><div style="font-size:12px;color:rgba(255,255,255,.6)">By patients</div></div>
  </div>
 </div>
</div>

<!-- Who section -->
<div style="background:var(--bg);padding:80px 40px">
 <h2 style="text-align:center;font-size:36px;font-weight:800;color:var(--tx);margin-bottom:14px;letter-spacing:-.3px">Who is MediBook for?</h2>
 <p style="text-align:center;font-size:16px;color:var(--tx3);margin-bottom:50px">One platform, three portals</p>
 <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;max-width:900px;margin:0 auto">
  <a href="patient/register.php" class="flow-card">
   <div style="font-size:48px;margin-bottom:16px"></div>
   <div style="font-size:18px;font-weight:800;color:var(--tx);margin-bottom:8px">Patients</div>
   <div style="font-size:13px;color:var(--tx3);margin-bottom:20px;line-height:1.6">Search doctors, book appointments, manage your health history</div>
   <div class="btn-p" style="font-size:13px;padding:9px 24px">Register free →</div>
  </a>
  <a href="doctor/register.php" class="flow-card">
   <div style="font-size:48px;margin-bottom:16px"></div>
   <div style="font-size:18px;font-weight:800;color:var(--tx);margin-bottom:8px">Doctors</div>
   <div style="font-size:13px;color:var(--tx3);margin-bottom:20px;line-height:1.6">Manage your schedule, accept bookings, view patient details</div>
   <div class="btn-t" style="font-size:13px;padding:9px 24px;background:linear-gradient(135deg,var(--t),#065F46)">Join as doctor →</div>
  </a>
  <a href="admin/login.php" class="flow-card">
   <div style="font-size:48px;margin-bottom:16px">⚙️</div>
   <div style="font-size:18px;font-weight:800;color:var(--tx);margin-bottom:8px">Admins</div>
   <div style="font-size:13px;color:var(--tx3);margin-bottom:20px;line-height:1.6">Approve doctors, manage all bookings and users, view analytics</div>
   <div class="btn-g" style="font-size:13px;padding:9px 24px">Admin login →</div>
  </a>
 </div>
</div>
</body></html>
