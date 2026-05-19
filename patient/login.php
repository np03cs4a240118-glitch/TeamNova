<?php
// patient/login.php
// DABS-02: Patient logs in
// Tasks: T02-1 to T02-6
// ============================================================
session_start();
if (!empty($_SESSION['patient_id'])) {
    header('Location: /medibook/patient/dashboard.php'); exit;
}
require_once '../config/db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        // T02-2: SELECT patient by email
        $email_safe = $conn->real_escape_string($email);
        $result = $conn->query(
            "SELECT id, name, password, email_verified, two_factor_enabled FROM patients WHERE email='$email_safe' LIMIT 1"
        );
        // T02-3: Verify password
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                // DABSTN-82: Block login if email not verified
                if (!$row['email_verified']) {
                    $error = 'unverified';
                    $unverified_email = htmlspecialchars($email);
                } else {
                    // DABSTN-172: If 2FA is enabled, generate OTP and redirect
                    // to verification instead of completing the login.
                    if ((int)$row['two_factor_enabled'] === 1) {
                        $otp_code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                        $otp_token   = bin2hex(random_bytes(16));
                        $otp_expires = date('Y-m-d H:i:s', time() + 5 * 60); // 5 min

                        $conn->query(
                            "UPDATE patients
                                SET otp_code='$otp_code',
                                    otp_token='$otp_token',
                                    otp_expires='$otp_expires'
                              WHERE id={$row['id']}"
                        );

                        // Stash the half-authenticated state. Note: NOT setting
                        // patient_id yet — that only happens after OTP verifies.
                        $_SESSION['login_2fa_pending_pid']   = (int)$row['id'];
                        $_SESSION['login_2fa_pending_token'] = $otp_token;
                        $_SESSION['login_2fa_pending_email'] = $email;
                        $_SESSION['login_2fa_demo_code']     = $otp_code; // demo mode — shown on verify page

                        header('Location: /medibook/patient/login_2fa.php');
                        exit;
                    }

                    // Default flow — set session & redirect to dashboard
                    $_SESSION['patient_id']   = $row['id'];
                    $_SESSION['patient_name'] = $row['name'];

                    header('Location: /medibook/patient/dashboard.php');
                    exit;
                }
            } else {
                // T02-5: Show error
                $error = 'invalid';
            }
        } else {
            $error = 'invalid';
        }
    }
}

// Handle resend verification request
if (isset($_GET['resend'])) {
    require_once '../includes/functions.php';
    $email_safe = $conn->real_escape_string(trim($_GET['resend']));
    $r = $conn->query("SELECT id, email FROM patients WHERE email='$email_safe' AND email_verified=0 LIMIT 1");
    if ($r && $r->num_rows === 1) {
        $pRow = $r->fetch_assoc();
        sendVerificationEmail($conn, $pRow['id'], 'patient', $pRow['email']);
        $resent = true;
    }
}


$page_title = 'Patient Login';
include '../includes/header.php';
?>
<div class="auth-split">
  <div class="auth-left">
    <div class="flex items-center" style="gap:9px;margin-bottom:30px">
      <div class="logo-i">M</div>
      <span class="logo-n-white">MediBook</span>
    </div>
    <h1 style="font-size:36px;font-weight:800;color:#fff;line-height:1.15;margin-bottom:14px;letter-spacing:-.5px">Welcome back.</h1>
    <p style="font-size:14px;color:rgba(255,255,255,.65);line-height:1.75;margin-bottom:36px;max-width:320px">Your health journey continues here. Book appointments, manage your visits, and stay connected.</p>
    <div style="display:flex;flex-direction:column;gap:14px;margin-bottom:32px">
      <div style="display:flex;align-items:center;gap:14px">
        <div style="width:38px;height:38px;background:rgba(13,158,122,.25);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0"></div>
        <div><div style="font-size:13px;font-weight:700;color:#fff;margin-bottom:2px">50+ verified doctors</div><div style="font-size:12px;color:rgba(255,255,255,.5)">Across 20+ specialities in Nepal</div></div>
      </div>
      <div style="display:flex;align-items:center;gap:14px">
        <div style="width:38px;height:38px;background:rgba(245,158,11,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0"></div>
        <div><div style="font-size:13px;font-weight:700;color:#fff;margin-bottom:2px">Instant confirmation</div><div style="font-size:12px;color:rgba(255,255,255,.5)">Book in under 2 minutes</div></div>
      </div>
      <div style="display:flex;align-items:center;gap:14px">
        <div style="width:38px;height:38px;background:rgba(139,92,246,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0"></div>
        <div><div style="font-size:13px;font-weight:700;color:#fff;margin-bottom:2px">Always free for patients</div><div style="font-size:12px;color:rgba(255,255,255,.5)">No hidden fees, ever</div></div>
      </div>
    </div>
  </div>

  <div class="auth-right">
    <div class="auth-tabs">
      <a href="/medibook/patient/register.php" class="auth-tab">Sign up</a>
      <a href="/medibook/patient/login.php" class="auth-tab active">Log in</a>
    </div>
    <h2 style="font-size:22px;font-weight:800;color:var(--tx);margin-bottom:6px">Log in to your account</h2>
    <p style="font-size:13px;color:var(--tx3);margin-bottom:24px">Enter your credentials to access your dashboard</p>

    <?php if (!empty($resent)): ?>
      <div class="alert alert-success">✅ Verification link generated!</div>
      <?php if (!empty($_SESSION['demo_verification_link'])): ?>
        <div style="background:var(--b3);border:1px solid var(--b4);border-radius:10px;padding:16px;margin-bottom:16px">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--b);margin-bottom:8px">Demo mode — verification link (normally sent by email)</div>
          <div style="font-size:12px;color:var(--tx2);margin-bottom:10px">Click the link below to verify your email address:</div>
          <a href="<?= htmlspecialchars($_SESSION['demo_verification_link']) ?>" style="font-size:13px;color:var(--b);font-weight:700;word-break:break-all"><?= htmlspecialchars($_SESSION['demo_verification_link']) ?></a>
        </div>
        <a href="<?= htmlspecialchars($_SESSION['demo_verification_link']) ?>" class="btn-p btn-full" style="display:block;text-align:center;padding:13px;font-size:14px;border-radius:10px;margin-bottom:14px;text-decoration:none">Verify my email &rarr;</a>
        <?php unset($_SESSION['demo_verification_link'], $_SESSION['demo_verification_email']); ?>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($error === 'unverified'): ?>
      <div class="alert" style="background:#FEF3C7;border:1px solid #FDE68A;color:#92400E;border-radius:10px;padding:14px 16px;font-size:13px;margin-bottom:16px">
        <strong>Please verify your email first.</strong><br>
        Check your inbox for the verification link, or
        <a href="?resend=<?= urlencode($unverified_email ?? '') ?>" style="color:#b45309;font-weight:700">resend it</a>.
      </div>
    <?php elseif ($error === 'invalid'): ?>
      <div class="alert alert-error">Invalid email or password. Please try again.</div>
    <?php elseif ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- T02-1: Login form -->
    <form method="POST" action="">
      <div class="form-group">
        <label>Email address</label>
        <input type="email" name="email" class="form-control" placeholder="email@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
      </div>
      <div style="text-align:right;margin-bottom:20px">
      <a href="forgot_password.php" style="font-size:12px;color:var(--b);font-weight:600;text-decoration:none">Forgot password?</a>
      </div>
      <button type="submit" class="btn-p btn-full" style="padding:14px;font-size:14px;border-radius:10px;margin-bottom:16px">Log in</button>
    </form>
    <p style="text-align:center;font-size:13px;color:var(--tx3)">
      Don't have an account? <a href="/medibook/patient/register.php" style="color:var(--b);font-weight:700;text-decoration:none">Sign up free</a>
    </p>
    <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--bd)">
      <p style="font-size:12px;color:var(--tx3);margin-bottom:6px">
        <a href="/medibook/doctor/login.php" style="color:var(--tx3);text-decoration:none">Are you a doctor? Log in here →</a>
      </p>
      <p style="font-size:12px;color:var(--tx3)">
        <a href="/medibook/admin/login.php" style="color:var(--tx3);text-decoration:none">Admin portal →</a>
      </p>
    </div>
  </div>
</div>
</body>
</html>