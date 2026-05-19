<?php
// patient/delete_account.php — DABS-08 (T08-1 to T08-5)
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requirePatient();
$pid = (int)$_SESSION['patient_id'];

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['confirm']??'')===('DELETE')) {
    // T08-4: DELETE appointments (cascade via FK, but explicit for clarity)
    $conn->query("DELETE FROM appointments WHERE patient_id=$pid");
    $conn->query("DELETE FROM notifications WHERE user_id=$pid AND user_type='patient'");
    // T08-3: DELETE patient
    $conn->query("DELETE FROM patients WHERE id=$pid");
    // T08-5: Destroy session + redirect
    session_destroy();
    header('Location: /medibook/index.php?msg=account_deleted'); exit;
}

$page_title='Delete Account'; include '../includes/header.php';
?>
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg);padding:40px 20px">
 <div style="width:100%;max-width:480px">
  <div class="card" style="padding:36px;text-align:center">
   <div style="font-size:44px;margin-bottom:16px"></div>
   <h1 style="font-size:22px;font-weight:800;color:var(--tx);margin-bottom:8px">Delete your account</h1>
   <p style="font-size:14px;color:var(--tx3);margin-bottom:24px;line-height:1.65">This will permanently delete your account and all associated appointments. <strong>This action cannot be undone.</strong></p>
   <div class="alert alert-error" style="text-align:left;margin-bottom:24px">All your appointments and data will be permanently deleted.</div>
   <!-- T08-2: Confirmation step -->
   <form method="POST">
    <div class="form-group" style="text-align:left">
     <label>Type <strong>DELETE</strong> to confirm</label>
     <input type="text" name="confirm" class="form-control" placeholder="Type DELETE" required pattern="DELETE">
    </div>
    <button type="submit" class="btn-r btn-full" style="padding:13px;font-size:14px;margin-bottom:12px">Delete my account permanently</button>
    <a href="dashboard.php" class="btn-g btn-full" style="padding:12px;font-size:13px;text-align:center;display:block">Cancel — keep my account</a>
   </form>
  </div>
 </div>
</div></body></html>
