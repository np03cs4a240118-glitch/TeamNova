<?php
// patient/api_allergies.php — AJAX API for medication allergies
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Must be logged in as patient
if (empty($_SESSION['patient_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$pid    = (int)$_SESSION['patient_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ── List all allergies ──────────────────────────────────
    case 'list':
        $r = $conn->query("SELECT * FROM patient_allergies WHERE patient_id=$pid ORDER BY created_at DESC");
        $rows = [];
        while ($row = $r->fetch_assoc()) $rows[] = $row;
        echo json_encode(['success' => true, 'allergies' => $rows]);
        break;

    // ── Add a new allergy ───────────────────────────────────
    case 'add':
        $name     = clean($conn, $_POST['name'] ?? '');
        $severity = clean($conn, $_POST['severity'] ?? 'mild');
        $notes    = clean($conn, $_POST['notes'] ?? '');

        if (!$name) {
            echo json_encode(['success' => false, 'error' => 'Allergy name is required.']);
            exit;
        }
        if (!in_array($severity, ['mild', 'moderate', 'severe'])) $severity = 'mild';

        $conn->query(
            "INSERT INTO patient_allergies (patient_id, name, severity, notes)
             VALUES ($pid, '$name', '$severity', '$notes')"
        );
        $newId = $conn->insert_id;
        echo json_encode(['success' => true, 'id' => $newId]);
        break;

    // ── Edit an existing allergy ────────────────────────────
    case 'edit':
        $id       = (int)($_POST['id'] ?? 0);
        $name     = clean($conn, $_POST['name'] ?? '');
        $severity = clean($conn, $_POST['severity'] ?? 'mild');
        $notes    = clean($conn, $_POST['notes'] ?? '');

        if (!$id || !$name) {
            echo json_encode(['success' => false, 'error' => 'ID and name are required.']);
            exit;
        }
        if (!in_array($severity, ['mild', 'moderate', 'severe'])) $severity = 'mild';

        $conn->query(
            "UPDATE patient_allergies SET name='$name', severity='$severity', notes='$notes'
             WHERE id=$id AND patient_id=$pid"
        );
        echo json_encode(['success' => true]);
        break;

    // ── Delete an allergy ───────────────────────────────────
    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID is required.']);
            exit;
        }
        $conn->query("DELETE FROM patient_allergies WHERE id=$id AND patient_id=$pid");
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
}
