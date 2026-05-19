<?php
// patient/login_2fa.php — Step 2 of login: enter the 6-digit OTP
// Reached only after a correct password when two_factor_enabled=1.
// ============================================================
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';

// If the half-authenticated state isn't there, kick back to login.
$pending_pid   = $_SESSION['login_2fa_pending_pid']   ?? 0;
$pending_token = $_SESSION['login_2fa_pending_token'] ?? '';
$pending_email = $_SESSION['login_2fa_pending_email'] ?? '';
if (!$pending_pid || !$pending_token) {
    header('Location: login.php'); exit;
}

$error    = '';
$resent   = false;
$demo_otp = $_SESSION['login_2fa_demo_code'] ?? '';

// ── Resend code ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['resend'])) {
    $otp_code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otp_token   = bin2hex(random_bytes(16));
    $otp_expires = date('Y-m-d H:i:s', time() + 5 * 60);

    $conn->query(
        "UPDATE patients
            SET otp_code='$otp_code',
                otp_token='$otp_token',
                otp_expires='$otp_expires'
          WHERE id=$pending_pid"
    );
    $_SESSION['login_2fa_pending_token'] = $otp_token;
    $_SESSION['login_2fa_demo_code']     = $otp_code;
    $pending_token = $otp_token;
    $demo_otp      = $otp_code;
    $resent = true;
}

// ── Verify code ─────────────────────────────────────────
elseif ($_SERVER['REQUEST_METHOD']==='POST' && !isset($_POST['resend'])) {
    $entered = preg_replace('/\D/', '', $_POST['otp'] ?? '');

    if (strlen($entered) !== 6) {
        $error = 'Please enter the 6-digit code.';
    } else {
        $token_safe = $conn->real_escape_string($pending_token);
        $r = $conn->query(
            "SELECT id, name, otp_code, otp_expires
               FROM patients
              WHERE id=$pending_pid AND otp_token='$token_safe'
              LIMIT 1"
        );

        if ($r->num_rows !== 1) {
            $error = 'Session expired. Please log in again.';
        } else {
            $p = $r->fetch_assoc();
            if (strtotime($p['otp_expires']) < time()) {
                $error = 'Code expired. Click "Resend code" to get a new one.';
            } elseif ($entered !== $p['otp_code']) {
                $error = 'Incorrect code. Try again.';
            } else {
                // Success — consume the OTP, complete the login.
                $conn->query(
                    "UPDATE patients
                        SET otp_code=NULL, otp_token=NULL, otp_expires=NULL
                      WHERE id=$pending_pid"
                );
                $_SESSION['patient_id']   = (int)$p['id'];
                $_SESSION['patient_name'] = $p['name'];

                unset(
                    $_SESSION['login_2fa_pending_pid'],
                    $_SESSION['login_2fa_pending_token'],
                    $_SESSION['login_2fa_pending_email'],
                    $_SESSION['login_2fa_demo_code']
                );
                header('Location: dashboard.php'); exit;
            }
        }
    }
}

$page_title = 'Verify Login — MediBook';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $page_title ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/auth.css">
<style>
  .otp-wrap{max-width:420px;margin:60px auto;padding:32px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif}
  .otp-wrap h1{font-size:22px;font-weight:800;color:#0f172a;margin-bottom:6px;text-align:center}
  .otp-wrap .lead{font-size:13px;color:#64748b;text-align:center;line-height:1.6;margin-bottom:22px}
  .otp-wrap .lead strong{color:#0f172a}
  .otp-input{
    width:100%;padding:14px;font-size:24px;font-weight:700;letter-spacing:.4em;
    border:2px solid #cbd5e1;border-radius:10px;text-align:center;outline:none;
    font-family:ui-monospace,'SF Mono',monospace;
  }
  .otp-input:focus{border-color:#1a6fd4;box-shadow:0 0 0 3px rgba(26,111,212,.15)}
  .submit{width:100%;background:#1a6fd4;color:#fff;border:none;padding:13px;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;margin-top:14px;font-family:inherit}
  .submit:hover{background:#1558b0}
  .resend-form{margin-top:14px;text-align:center}
  .resend-btn{background:none;border:none;color:#1a6fd4;font-weight:600;font-size:13px;cursor:pointer;font-family:inherit}
  .resend-btn:hover{text-decoration:underline}
  .demo-code{
    background:#fef3c7;border:1px dashed #f59e0b;color:#92400e;
    padding:13px 14px;border-radius:10px;text-align:center;margin-bottom:18px;font-size:13px;
  }
  .demo-code strong{font-family:ui-monospace,monospace;font-size:18px;letter-spacing:.2em;color:#78350f}
  .err{background:#fee2e2;color:#991b1b;padding:11px 14px;border-radius:9px;font-size:13px;margin-bottom:14px;text-align:center;font-weight:600}
  .ok {background:#dcfce7;color:#166534;padding:11px 14px;border-radius:9px;font-size:13px;margin-bottom:14px;text-align:center;font-weight:600}
  .back{display:block;text-align:center;margin-top:18px;font-size:12px;color:#64748b;text-decoration:none}
  .back:hover{color:#1a6fd4}
</style>
</head>
<body style="background:#f1f5f9;margin:0;padding:20px">

<div class="otp-wrap">
  <h1>Verify it's you</h1>
  <p class="lead">
    We sent a 6-digit code to <strong><?= htmlspecialchars($pending_email) ?></strong>.
    It expires in 5 minutes.
  </p>

  <?php if ($demo_otp): ?>
   <div class="demo-code">
    <div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;font-weight:800;margin-bottom:6px">Demo mode — your code</div>
    <strong><?= htmlspecialchars($demo_otp) ?></strong>
    <div style="font-size:11px;margin-top:6px;opacity:.85">In production this would arrive in your inbox.</div>
   </div>
  <?php endif; ?>

  <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($resent): ?><div class="ok">✓ A fresh code has been generated.</div><?php endif; ?>

  <form method="POST" autocomplete="off">
    <input type="text" name="otp" class="otp-input" maxlength="6" inputmode="numeric"
           pattern="[0-9]{6}" placeholder="••••••" required autofocus>
    <button type="submit" class="submit">Verify and continue</button>
  </form>

  <form method="POST" class="resend-form">
    <input type="hidden" name="resend" value="1">
    <button type="submit" class="resend-btn">Didn't get the code? Send a new one</button>
  </form>

  <a href="login.php" class="back">← Cancel and use a different account</a>
</div>

</body>
</html>
