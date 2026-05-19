<?php
// includes/functions.php
// DB-10: Session checks, DB-11: Input sanitization
// Notification helpers for DABS-20, 21, 22, 23, 24
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

// ── Sanitize input (DB-11) ──────────────────────────────────
function clean($conn, $data) {
    return $conn->real_escape_string(htmlspecialchars(strip_tags(trim($data))));
}

// ── Session guards (DB-10) ──────────────────────────────────
function requirePatient() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['patient_id'])) {
        header('Location: /medibook/patient/login.php');
        exit;
    }
}
function requireDoctor() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['doctor_id'])) {
        header('Location: /medibook/doctor/login.php');
        exit;
    }
}
function requireAdmin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['admin_id'])) {
        header('Location: /medibook/admin/login.php');
        exit;
    }
}

// ── Insert notification (T20-1, T21-1, T22-2) ──────────────
function insertNotification($conn, $user_id, $user_type, $message) {
    $user_id   = (int)$user_id;
    $user_type = $conn->real_escape_string($user_type);
    $message   = $conn->real_escape_string($message);
    $conn->query(
        "INSERT INTO notifications (user_id, user_type, message)
         VALUES ($user_id, '$user_type', '$message')"
    );
}

// ── Count unread notifications ──────────────────────────────
function countUnread($conn, $user_id, $user_type) {
    $user_id   = (int)$user_id;
    $user_type = $conn->real_escape_string($user_type);
    $r = $conn->query(
        "SELECT COUNT(*) AS c FROM notifications
         WHERE user_id=$user_id AND user_type='$user_type' AND is_read=0"
    );
    return $r->fetch_assoc()['c'] ?? 0;
}

// ── DABS-22: Check and insert 1-day reminders (T22-1 to T22-4) ─
function checkAndInsertReminders($conn) {
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $r = $conn->query(
        "SELECT a.id, a.patient_id, a.date, a.time,
                d.name AS doctor_name
         FROM appointments a
         JOIN doctors d ON d.id = a.doctor_id
         WHERE a.date = '$tomorrow'
           AND a.status = 'confirmed'"
    );
    while ($row = $r->fetch_assoc()) {
        // Avoid duplicate reminders
        $appt_id = (int)$row['id'];
        $check = $conn->query(
            "SELECT id FROM notifications
             WHERE user_id={$row['patient_id']}
               AND user_type='patient'
               AND message LIKE '%reminder%appt_id:{$appt_id}%'"
        );
        if ($check->num_rows === 0) {
            $msg = "⏰ Reminder: Your appointment with Dr. {$row['doctor_name']} is tomorrow at {$row['time']}. [appt_id:{$appt_id}]";
            insertNotification($conn, $row['patient_id'], 'patient', $msg);
        }
    }
}

// ── DABSTN-82: Email Verification (demo mode — shows link in browser) ──
/**
 * Generates a secure token, saves it to the DB, and stores the
 * verification link in the session so it can be displayed in the browser
 * (mirrors the forgot-password "demo mode" approach).
 *
 * @param mysqli $conn
 * @param int    $user_id
 * @param string $user_type  'patient' | 'doctor'
 * @param string $email
 * @return string  The generated token
 */
function sendVerificationEmail($conn, $user_id, $user_type, $email) {
    if (session_status() === PHP_SESSION_NONE) session_start();

    $token   = bin2hex(random_bytes(32)); // 64-char hex token
    $user_id = (int)$user_id;
    $table   = ($user_type === 'doctor') ? 'doctors' : 'patients';
    $t       = $conn->real_escape_string($token);

    $conn->query("UPDATE $table SET verification_token='$t', email_verified=0 WHERE id=$user_id");

    $link = "http://localhost/medibook/{$user_type}/verify_email.php?token={$token}";

    // Store link in session for in-browser display (demo mode)
    $_SESSION['demo_verification_link']  = $link;
    $_SESSION['demo_verification_email'] = $email;

    return $token;
}

// ── Badge HTML helper ───────────────────────────────────────
function statusBadge($status) {
    $map = [
        'confirmed'  => ['#D1FAE5','#065F46','Confirmed'],
        'pending'    => ['#FEF3C7','#92400E','Pending'],
        'cancelled'  => ['#FEE2E2','#DC2626','Cancelled'],
        'completed'  => ['#F1F5F9','#475569','Completed'],
    ];
    $s = $map[$status] ?? ['#F1F5F9','#475569',ucfirst($status)];
    return "<span style='background:{$s[0]};color:{$s[1]};border-radius:6px;padding:3px 10px;font-size:11px;font-weight:700'>{$s[2]}</span>";
}

// ── Format time to 12h ──────────────────────────────────────
function fmt12($time) {
    return date('g:i A', strtotime($time));
}
// ── Format date readable ────────────────────────────────────
function fmtDate($date) {
    return date('D, M j, Y', strtotime($date));
}

// ── Get Initials ────────────────────────────────────────────
function getInitials($name) {
    // Remove "Dr. ", "Dr ", etc.
    $name = preg_replace('/^Dr\.?\s+/i', '', trim($name));
    $parts = explode(' ', preg_replace('/\s+/', ' ', $name));
    
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
    } elseif (count($parts) == 1 && !empty($parts[0])) {
        return strtoupper(substr($parts[0], 0, 2));
    }
    return 'U';
}

// ── Doctor avatar (image if uploaded, initials otherwise) ──
// Pass either:
//   - an associative array with 'name' and optionally 'profile_image' keys
//     (typical from a SELECT * FROM doctors query)
//   - just a doctor name as a string (initials only — no DB-driven image)
//
// $size_px: rendered width/height in pixels. Font size scales accordingly.
// $extra_class: extra CSS class on the wrapper (e.g. 'av-blue', 'av-teal')
//               so callers keep the same colour palette they had before.
function doctorAvatar($doctor, int $size_px = 44, string $extra_class = 'av-blue'): string {
    $img = '';
    $name = '';

    if (is_array($doctor)) {
        $name = $doctor['name'] ?? '';
        $img  = $doctor['profile_image'] ?? '';
    } else {
        $name = (string)$doctor;
    }

    $font_size = max(10, (int)round($size_px * 0.34));

    // Has the doctor uploaded a picture, AND does the file actually exist on disk?
    // The file-exists check matters: a db row may point to a deleted file.
    if ($img !== '') {
        $on_disk = __DIR__ . '/../' . $img;
        if (is_file($on_disk)) {
            $url = '/medibook/' . htmlspecialchars($img, ENT_QUOTES);
            return '<div class="av ' . htmlspecialchars($extra_class, ENT_QUOTES) .
                   '" style="width:' . $size_px . 'px;height:' . $size_px .
                   'px;overflow:hidden;padding:0;background:transparent">' .
                   '<img src="' . $url . '" alt="" style="width:100%;height:100%;object-fit:cover;display:block">' .
                   '</div>';
        }
    }

    // Fallback to initials (existing visual style)
    return '<div class="av ' . htmlspecialchars($extra_class, ENT_QUOTES) .
           '" style="width:' . $size_px . 'px;height:' . $size_px .
           'px;font-size:' . $font_size . 'px">' .
           htmlspecialchars(getInitials($name)) .
           '</div>';
}