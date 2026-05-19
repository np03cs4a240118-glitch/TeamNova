<?php
// patient/api_reports.php — AJAX API for medical report uploads
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';

// Must be logged in as patient OR doctor
if (empty($_SESSION['patient_id']) && empty($_SESSION['doctor_id'])) {
    if (isset($_GET['action']) && $_GET['action'] === 'download') {
        http_response_code(403);
        die('Unauthorized');
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$is_doctor = !empty($_SESSION['doctor_id']);
$pid = $is_doctor ? (int)($_GET['pid'] ?? $_POST['pid'] ?? 0) : (int)$_SESSION['patient_id'];

if (!$pid) {
    http_response_code(400); die('Missing patient ID');
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Upload directory
$uploadBase = __DIR__ . '/../uploads/reports/' . $pid;

switch ($action) {

    // ── List all reports ────────────────────────────────────
    case 'list':
        header('Content-Type: application/json');
        $r = $conn->query("SELECT * FROM patient_reports WHERE patient_id=$pid ORDER BY uploaded_at DESC");
        $rows = [];
        while ($row = $r->fetch_assoc()) $rows[] = $row;
        echo json_encode(['success' => true, 'reports' => $rows]);
        break;

    // ── Upload a report ─────────────────────────────────────
    case 'upload':
        if($is_doctor){die('Unauthorized');}
        header('Content-Type: application/json');

        if (empty($_FILES['report']) || $_FILES['report']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error.']);
            exit;
        }

        $file = $_FILES['report'];
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
        $maxSize = 5 * 1024 * 1024; // 5 MB

        // Validate type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedTypes)) {
            echo json_encode(['success' => false, 'error' => 'Only PDF, JPG, and PNG files are allowed.']);
            exit;
        }

        // Validate size
        if ($file['size'] > $maxSize) {
            echo json_encode(['success' => false, 'error' => 'File size must be under 5 MB.']);
            exit;
        }

        // Create upload directory
        if (!is_dir($uploadBase)) {
            mkdir($uploadBase, 0755, true);
        }

        // Generate safe filename
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
        $destPath = $uploadBase . '/' . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'error' => 'Failed to save file.']);
            exit;
        }

        // Store relative path in DB
        $relPath  = 'uploads/reports/' . $pid . '/' . $safeName;
        $origName = $conn->real_escape_string($file['name']);
        $relPathE = $conn->real_escape_string($relPath);
        $mimeE    = $conn->real_escape_string($mime);
        $size     = (int)$file['size'];

        $conn->query(
            "INSERT INTO patient_reports (patient_id, file_name, file_path, file_type, file_size)
             VALUES ($pid, '$origName', '$relPathE', '$mimeE', $size)"
        );
        $newId = $conn->insert_id;
        echo json_encode(['success' => true, 'id' => $newId, 'file_name' => $file['name']]);
        break;

    // ── Download / view a report ────────────────────────────
    case 'download':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); die('Missing ID'); }

        $r = $conn->query("SELECT * FROM patient_reports WHERE id=$id AND patient_id=$pid LIMIT 1");
        if ($r->num_rows === 0) { http_response_code(404); die('Not found'); }

        $row  = $r->fetch_assoc();
        $path = __DIR__ . '/../' . $row['file_path'];
        if (!file_exists($path)) { http_response_code(404); die('File not found on disk'); }

        $inline = isset($_GET['inline']);
        header('Content-Type: ' . $row['file_type']);
        header('Content-Length: ' . filesize($path));
        if ($inline) {
            header('Content-Disposition: inline; filename="' . $row['file_name'] . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $row['file_name'] . '"');
        }
        readfile($path);
        exit;

    // ── Delete a report ─────────────────────────────────────
    
    // ── Download doctor file ────────────────────────────
    case 'download_doctor_file':
        $file = basename($_GET['file'] ?? '');
        if (!$file) { http_response_code(400); die('Missing file name'); }

        $fileE = $conn->real_escape_string($file);
        $r = $conn->query("SELECT id FROM appointments WHERE patient_id=$pid AND doctor_file='$fileE' LIMIT 1");
        if ($r->num_rows === 0) { http_response_code(403); die('Unauthorized or Not found'); }

        $path = __DIR__ . '/../uploads/reports/' . $file;
        if (!file_exists($path)) { http_response_code(404); die('File not found on disk'); }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = 'application/octet-stream';
        if ($ext === 'pdf') $mime = 'application/pdf';
        elseif (in_array($ext, ['jpg', 'jpeg'])) $mime = 'image/jpeg';
        elseif ($ext === 'png') $mime = 'image/png';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="doctor_report_' . $file . '"');
        readfile($path);
        exit;

    case 'delete':
        if($is_doctor){die('Unauthorized');}
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID is required.']);
            exit;
        }

        // Get file path first
        $r = $conn->query("SELECT file_path FROM patient_reports WHERE id=$id AND patient_id=$pid LIMIT 1");
        if ($r->num_rows === 1) {
            $row  = $r->fetch_assoc();
            $path = __DIR__ . '/../' . $row['file_path'];
            if (file_exists($path)) unlink($path);
            $conn->query("DELETE FROM patient_reports WHERE id=$id AND patient_id=$pid");
        }
        echo json_encode(['success' => true]);
        break;

    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
}
