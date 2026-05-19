<?php
// patient/register.php
// DABS-01: Patient registers an account
// + Visit-informed: explicit consent to Terms + Privacy before submitting personal/health data.
// ============================================================
session_start();
if (!empty($_SESSION['patient_id'])) {
    header('Location: /medibook/patient/dashboard.php');
    exit;
}
require_once '../config/db_connect.php';
require_once '../includes/functions.php';

// Current policy versions. Bump these whenever you materially change the policy text.
// The version a user accepted is recorded permanently in patient_consents so you can
// prove what they originally agreed to even after you publish a new version.
define('TERMS_VERSION',   'v1.0');
define('PRIVACY_VERSION', 'v1.0');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = clean($conn, $_POST['name'] ?? '');
    $email    = clean($conn, $_POST['email'] ?? '');
    $phone    = clean($conn, $_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Consent: required (Terms + Privacy bundled into one mandatory accept), and optional marketing.
    $accepted_terms_privacy = !empty($_POST['accept_terms_privacy']);
    $accepted_marketing     = !empty($_POST['accept_marketing']);

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Name, email and password are required.';
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!$accepted_terms_privacy) {
        // Server-side enforcement. The HTML "required" attribute is not enough —
        // a user who disables JS or posts directly to this endpoint must still be blocked.
        $error = 'You must accept the Terms of Service and Privacy Policy to create an account.';
    } else {
        // T01-2: Check if email already exists
        $check = $conn->query("SELECT id FROM patients WHERE email='$email' LIMIT 1");
        if ($check->num_rows > 0) {
            $error = 'An account with this email already exists.';
        } else {
            // T01-3: Hash password
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            // T01-4: INSERT patient record (email_verified=0 by default)
            $conn->query(
                "INSERT INTO patients (name, email, password, phone, email_verified)
                 VALUES ('$name', '$email', '$hashed', '$phone', 0)"
            );
            $new_id = $conn->insert_id;

            // ── Record consent audit trail ────────────────────────────
            // Captured so we can prove what each user accepted, in case the policy changes later.
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
            $tv = TERMS_VERSION;
            $pv = PRIVACY_VERSION;
            $mkt = $accepted_marketing ? 1 : 0;

            $stmt = $conn->prepare(
                "INSERT INTO patient_consents
                   (patient_id, terms_version, privacy_version, accepted_terms, accepted_privacy, accepted_marketing, ip_address, user_agent)
                 VALUES (?, ?, ?, 1, 1, ?, ?, ?)"
            );
            $stmt->bind_param('ississ', $new_id, $tv, $pv, $mkt, $ip, $ua);
            $stmt->execute();

            // DABSTN-82: Generate verification token and log link to file
            sendVerificationEmail($conn, $new_id, 'patient', $email);
            // T01-5: Show success — user must verify email before logging in
            $success = 'Account created! Please check your email to verify your account before logging in.';
        }
    }
}

$page_title = 'Patient Register';
include '../includes/header.php';
?>
<div class="auth-split">
  <!-- Left brand panel -->
  <div class="auth-left">
    <div class="flex items-center mb-8" style="margin-bottom:28px">
      <div class="logo-i">M</div>
      <span class="logo-n-white">MediBook</span>
    </div>
    <h1 style="font-size:34px;font-weight:800;color:#fff;line-height:1.2;margin-bottom:14px;letter-spacing:-.5px">Your health,<br>your schedule.</h1>
    <p style="font-size:14px;color:rgba(255,255,255,.65);line-height:1.75;margin-bottom:36px">Skip the queue. Book verified doctors in Nepal in under 2 minutes.</p>
    <div class="grid-3" style="margin-bottom:28px;gap:14px">
      <div style="text-align:center;padding:16px;background:rgba(255,255,255,.08);border-radius:13px;border:1px solid rgba(255,255,255,.15)">
        <div style="font-size:22px;font-weight:800;color:#fff;margin-bottom:4px">50+</div>
        <div style="font-size:11px;color:rgba(255,255,255,.6)">Doctors</div>
      </div>
      <div style="text-align:center;padding:16px;background:rgba(255,255,255,.08);border-radius:13px;border:1px solid rgba(255,255,255,.15)">
        <div style="font-size:22px;font-weight:800;color:#fff;margin-bottom:4px">20+</div>
        <div style="font-size:11px;color:rgba(255,255,255,.6)">Specialities</div>
      </div>
      <div style="text-align:center;padding:16px;background:rgba(255,255,255,.08);border-radius:13px;border:1px solid rgba(255,255,255,.15)">
        <div style="font-size:22px;font-weight:800;color:#fff;margin-bottom:4px">Trusted</div>
        <div style="font-size:11px;color:rgba(255,255,255,.6)">By patients</div>
      </div>
    </div>
  </div>

  <!-- Right form -->
  <div class="auth-right">
    <div class="auth-tabs">
      <a href="/medibook/patient/register.php" class="auth-tab active">Sign up</a>
      <a href="/medibook/patient/login.php" class="auth-tab">Log in</a>
    </div>
    <h2 style="font-size:22px;font-weight:800;color:var(--tx);margin-bottom:6px">Create your account</h2>
    <p style="font-size:13px;color:var(--tx3);margin-bottom:22px">Join MediBook and book your first appointment today</p>

    <?php if ($error): ?>
      <div class="alert alert-error"> <?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"> <?= $success ?></div>
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


    <!-- T01-1: Registration form -->
    <form method="POST" action="">
      <div class="form-group">
        <label>Full name *</label>
        <input type="text" name="name" class="form-control" placeholder="Enter your full name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Email address *</label>
        <input type="email" name="email" class="form-control" placeholder="email@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Phone number</label>
        <input type="tel" name="phone" class="form-control" placeholder="+977 98XXXXXXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
      </div>
      <div class="grid-2" style="gap:12px">
        <div class="form-group">
          <label>Password *</label>
          <input type="password" name="password" class="form-control" placeholder="Min 6 characters" required>
        </div>
        <div class="form-group">
          <label>Confirm password *</label>
          <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
        </div>
      </div>

      <!-- ── Consent block ───────────────────────────────────────── -->
      <div style="background:var(--bg);border:1px solid var(--bd);border-radius:10px;padding:14px 16px;margin-bottom:18px">

        <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;margin-bottom:10px">
          <input type="checkbox" name="accept_terms_privacy" value="1" required
                 style="margin-top:3px;width:16px;height:16px;flex-shrink:0;accent-color:var(--b);cursor:pointer"
                 <?= !empty($_POST['accept_terms_privacy']) ? 'checked' : '' ?>>
          <span style="font-size:12.5px;color:var(--tx2);line-height:1.55">
            I have read and agree to the
            <a href="#" onclick="event.preventDefault();document.getElementById('policyModal').style.display='flex';document.getElementById('policyModal').scrollTop=0" style="color:var(--b);font-weight:700;text-decoration:underline">Terms of Service and Privacy Policy</a>,
            and I understand that MediBook will collect and process my personal and health-related information to provide appointment services.
            <span style="color:var(--r);font-weight:700">*</span>
          </span>
        </label>


      </div>

      <button type="submit" class="btn-p btn-full" style="padding:14px;font-size:14px;border-radius:10px;margin-bottom:14px">Create account</button>
    </form>
    <p style="text-align:center;font-size:13px;color:var(--tx3)">
      Already have an account? <a href="/medibook/patient/login.php" style="color:var(--b);font-weight:700;text-decoration:none">Log in</a>
    </p>
  </div>
</div>

<!-- ── Privacy / Terms modal ──────────────────────────────────────── -->
<div id="policyModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.65);z-index:200;align-items:flex-start;justify-content:center;padding:40px 20px;overflow-y:auto">
  <div style="background:#fff;width:100%;max-width:720px;border-radius:14px;padding:32px 36px;box-shadow:0 20px 50px rgba(0,0,0,0.2);max-height:calc(100vh - 80px);overflow-y:auto;line-height:1.65;color:var(--tx2);font-size:13.5px">

    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:18px;border-bottom:1px solid var(--bd);padding-bottom:14px">
      <div>
        <h2 style="font-size:20px;font-weight:800;color:var(--tx);margin:0">MediBook — Privacy Policy &amp; Terms of Service</h2>
        <div style="font-size:11px;color:var(--tx3);margin-top:4px">Privacy Policy <?=PRIVACY_VERSION?> · Terms <?=TERMS_VERSION?> · Last updated <?=date('F Y')?></div>
      </div>
      <button type="button" onclick="document.getElementById('policyModal').style.display='none'"
              style="background:none;border:none;font-size:24px;color:var(--tx3);cursor:pointer;line-height:1;padding:0 4px">×</button>
    </div>

    <p style="margin-bottom:14px">
      This document explains what information MediBook collects when you create a patient account, how we use it,
      who can see it, how long we keep it, and what rights you have. Please read it carefully before accepting.
    </p>

    <h3 style="font-size:14px;font-weight:800;color:var(--tx);margin:20px 0 8px">1. What information we collect</h3>
    <ul style="margin:0 0 10px 22px;padding:0">
      <li><strong>Account details:</strong> your full name, email address, phone number, and password (stored in encrypted form).</li>
      <li><strong>Health-related information:</strong> date of birth, blood type, address, allergies, uploaded medical reports, reason for each visit, and the consultation notes your doctor writes for you.</li>
      <li><strong>Appointment activity:</strong> doctors you have booked, dates, times, cancellation history, and reviews you submit.</li>
      <li><strong>Payment metadata:</strong> when you pay through a third-party gateway (e.g. eSewa), we store the transaction ID and payment status. We do <em>not</em> store your full card or bank details.</li>
      <li><strong>Technical information:</strong> IP address, browser type, and access timestamps, used for security and abuse-prevention only.</li>
    </ul>

    <h3 style="font-size:14px;font-weight:800;color:var(--tx);margin:20px 0 8px">2. How we use your information</h3>
    <ul style="margin:0 0 10px 22px;padding:0">
      <li>To create and manage your patient account.</li>
      <li>To allow you to book, reschedule, cancel, and review appointments with doctors on the platform.</li>
      <li>To share necessary information (your name, contact, reason for visit, uploaded reports) <em>only with the doctor you booked</em>, so they can provide care.</li>
      <li>To send you appointment confirmations, reminders, and follow-up notifications.</li>
      <li>To improve and secure the platform.</li>
    </ul>

    <h3 style="font-size:14px;font-weight:800;color:var(--tx);margin:20px 0 8px">3. Who can see your information</h3>
    <ul style="margin:0 0 10px 22px;padding:0">
      <li><strong>The doctor you book.</strong> They can see your profile, reason for visit, and any reports you upload — limited to the appointments you book with them.</li>
      <li><strong>MediBook administrators.</strong> They can manage accounts and resolve disputes, but do not view individual medical records unless required for support.</li>
      <li><strong>Nobody else.</strong> We do not sell your personal or health data to any third party.</li>
    </ul>

    <h3 style="font-size:14px;font-weight:800;color:var(--tx);margin:20px 0 8px">4. How long we keep your information</h3>
    <p style="margin-bottom:10px">
      Your account data is kept for as long as your account is active. If you delete your account, your personal profile is removed,
      but anonymized appointment and audit records may be retained for legal, accounting, and security purposes for up to 5 years.
    </p>

    <h3 style="font-size:14px;font-weight:800;color:var(--tx);margin:20px 0 8px">5. Your rights</h3>
    <ul style="margin:0 0 10px 22px;padding:0">
      <li><strong>Access:</strong> view all data we hold about you from your profile and medical records pages.</li>
      <li><strong>Correct:</strong> update your personal and contact details at any time.</li>
      <li><strong>Delete:</strong> delete your account from your settings page. This removes your active profile.</li>
      <li><strong>Withdraw consent:</strong> you may withdraw consent at any time by deleting your account. Doing so will end your access to the platform.</li>
    </ul>

    <h3 style="font-size:14px;font-weight:800;color:var(--tx);margin:20px 0 8px">6. Security</h3>
    <p style="margin-bottom:10px">
      We protect your information using industry-standard practices: encrypted password storage, HTTPS in transit, and access controls
      that prevent unauthorized accounts from reading your data. No system is perfectly secure, so you must also keep your password confidential.
    </p>

    <h3 style="font-size:14px;font-weight:800;color:var(--tx);margin:20px 0 8px">7. Terms of Service (summary)</h3>
    <ul style="margin:0 0 10px 22px;padding:0">
      <li>You agree to provide accurate information about yourself.</li>
      <li>MediBook facilitates appointment booking only; it does not provide medical advice. Always consult a qualified doctor for medical decisions.</li>
      <li>Misuse of the platform (fake accounts, harassment, fraudulent payments) may result in account suspension.</li>
      <li>Cancellation policy: free cancellation up to 24 hours before; 50% charge within 24 hours; no-shows charged in full.</li>
    </ul>

    <h3 style="font-size:14px;font-weight:800;color:var(--tx);margin:20px 0 8px">8. Changes to this policy</h3>
    <p style="margin-bottom:10px">
      If we materially update this policy, we will publish a new version and ask you to re-accept the next time you log in.
      Your prior consent under the version you originally accepted will remain on record.
    </p>

    <h3 style="font-size:14px;font-weight:800;color:var(--tx);margin:20px 0 8px">9. Contact</h3>
    <p style="margin-bottom:16px">
      For questions, corrections, or to exercise any of the rights above, contact MediBook Support at the email listed on our website.
    </p>

    <div style="border-top:1px solid var(--bd);padding-top:14px;margin-top:18px;display:flex;justify-content:space-between;align-items:center;gap:14px">
      <div style="font-size:11.5px;color:var(--tx3)">Tick the box on the registration form to confirm you have read and agree.</div>
      <button type="button" class="btn-p" onclick="document.getElementById('policyModal').style.display='none'" style="padding:9px 22px">Close</button>
    </div>

  </div>
</div>

</body>
</html>
