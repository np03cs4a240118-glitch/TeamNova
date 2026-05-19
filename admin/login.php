<?php
// admin/login.php — DABS-15 (T15-1 to T15-5)
session_start();
if (!empty($_SESSION['admin_id'])) { header('Location: /medibook/admin/dashboard.php'); exit; }
require_once '../config/db_connect.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'Enter username and password.';
    } else {
        // T15-2: SELECT admin record
        $us = $conn->real_escape_string($username);
        $r  = $conn->query("SELECT id, username, password FROM admins WHERE username='$us' LIMIT 1");
        if ($r->num_rows === 1) {
            $row = $r->fetch_assoc();
            // T15-3: Verify password
            if (password_verify($password, $row['password'])) {
                $_SESSION['admin_id']       = $row['id'];
                $_SESSION['admin_username'] = $row['username'];
                // T15-4: Redirect to dashboard
                header('Location: /medibook/admin/dashboard.php'); exit;
            }
        }
        // T15-5: Error
        $error = 'Invalid username or password.';
    }
}

$page_title = 'Admin Login';
include '../includes/header.php';
?>
<div style="min-height:100vh;background:#0A1628;display:flex;align-items:center;justify-content:center;padding:40px 20px">
 <div style="width:100%;max-width:400px">
  <div style="text-align:center;margin-bottom:32px">
   <div class="logo-i" style="margin:0 auto 14px;width:44px;height:44px;border-radius:12px"><svg width="18" height="18" fill="none"><rect x="8" y="1" width="2" height="16" rx="1" fill="white"/><rect x="1" y="8" width="16" height="2" rx="1" fill="white"/></svg></div>
   <div style="font-size:24px;font-weight:800;color:#F8FAFC;letter-spacing:-.3px">MediBook Admin</div>
   <div style="font-size:13px;color:#64748B;margin-top:5px">Secure administrator portal</div>
  </div>
  <div style="background:#1A2840;border:1px solid #2D3F56;border-radius:16px;padding:32px">
   <?php if($error): ?><div class="alert alert-error" style="margin-bottom:18px">⚠️ <?=htmlspecialchars($error)?></div><?php endif; ?>
   <form method="POST">
    <div class="form-group">
     <label style="color:#94A3B8">Username</label>
     <input type="text" name="username" class="form-control" style="background:#111827;border-color:#2D3F56;color:#F8FAFC" required value="<?=htmlspecialchars($_POST['username']??'')?>">
    </div>
    <div class="form-group">
     <label style="color:#94A3B8">Password</label>
     <input type="password" name="password" class="form-control" style="background:#111827;border-color:#2D3F56;color:#F8FAFC" required>
    </div>
    <button type="submit" class="btn-p btn-full" style="padding:13px;font-size:14px;border-radius:10px;margin-top:8px">Log in to Admin Portal</button>
   </form>
   
  </div>
  <div style="text-align:center;margin-top:20px">
   <a href="/medibook/patient/login.php" style="font-size:12px;color:#475569;text-decoration:none">← Patient login</a>
   &nbsp;·&nbsp;
   <a href="/medibook/doctor/login.php" style="font-size:12px;color:#475569;text-decoration:none">Doctor login →</a>
  </div>
 </div>
</div>
</body></html>
