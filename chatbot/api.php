<?php
// chatbot/api.php
// ============================================================
// Symptom-checker chatbot endpoint (BULLETPROOF v2).
// Output-buffered so even errors during require_once or before
// header() get cleanly converted to JSON. Always returns valid
// JSON — never an HTML PHP error page.
// ============================================================

// Capture all output from this point on. Anything echoed or any
// PHP error message that would normally print gets buffered, so
// we can discard it and emit clean JSON in the catch block.
ob_start();

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');
error_reporting(E_ALL);

// Convert PHP warnings/notices into exceptions
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    require_once __DIR__ . '/../config/db_connect.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/retriever.php';

    if (session_status() === PHP_SESSION_NONE) session_start();

    if (empty($_SESSION['patient_id'])) {
        ob_end_clean();
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated. Please log in.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ob_end_clean();
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
        exit;
    }

    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    $message = isset($data['message']) ? trim((string)$data['message']) : '';

    if ($message === '') {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Please describe what you are feeling.']);
        exit;
    }
    if (mb_strlen($message) > 1000) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Message too long. Keep it under 1000 characters.']);
        exit;
    }

    $retriever = new SymptomRetriever(__DIR__ . '/../data/symptom_kb.json');
    $result = $retriever->retrieve($message, 3);

    $response = [
        'disclaimer' => $retriever->disclaimer(),
        'red_flag'   => false,
        'message'    => '',
        'diseases'   => [],
        'doctors'    => [],
    ];

    if (!empty($result['red_flags'])) {
        $flag = $result['red_flags'][0];
        $response['red_flag'] = true;
        $response['red_flag_label'] = $flag['label'];
        $response['message'] = $flag['advice'];
        $specs = $flag['specialisations'] ?? [];
        $response['doctors'] = fetch_doctors_for_specialisations($conn, $specs, 3);
    } elseif (empty($result['diseases'])) {
        $response['message'] = "Sorry, we couldn't match your symptoms to any known condition. Please visit a doctor for a proper evaluation.";
        // No fallback doctor recommendation: if we can't match the symptom,
        // we shouldn't pretend a generic GP is the answer.
        $response['doctors'] = [];
    } else {
        $collectedSpecs = [];
        foreach ($result['diseases'] as $r) {
            $d = $r['disease'];
            $confidence = round($r['score'] * 100);
            if ($confidence > 99) $confidence = 99;

            $response['diseases'][] = [
                'name'             => $d['name'],
                'description'      => $d['description'],
                'advice'           => $d['advice'],
                'urgency'          => $d['urgency'],
                'specialisations'  => $d['specialisations'],
                'matched_symptoms' => $r['matched_symptoms'],
                'confidence'       => $confidence,
            ];
            foreach ($d['specialisations'] as $s) $collectedSpecs[] = $s;
        }

        $response['message'] = "Based on what you described, here are some possibilities. "
                             . "This is not a diagnosis — please confirm with a doctor.";

        $response['doctors'] = fetch_doctors_for_specialisations(
            $conn, array_values(array_unique($collectedSpecs)), 5
        );
    }

    // Discard any stray output, then emit clean JSON
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;

} catch (Throwable $e) {
    // Anything that went wrong — schema mismatch, missing file,
    // PHP warning, etc. — ends up here as a clean JSON response.
    $stray = ob_get_clean(); // capture and discard buffered output
    error_log("[chatbot] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine()
        . ($stray ? " | stray output: " . substr($stray, 0, 200) : ''));

    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode([
        'error' => 'Server error: ' . $e->getMessage(),
        'where' => basename($e->getFile()) . ':' . $e->getLine(),
        'stray' => $stray ? substr($stray, 0, 200) : null,
    ]);
    exit;
}


function fetch_doctors_for_specialisations(mysqli $conn, array $specs, int $limit = 5): array
{
    if (empty($specs)) return [];

    try {
        $likeClauses = [];
        $params = [];
        $types  = '';

        foreach ($specs as $s) {
            $stem = preg_replace('/(ology|ologist|iatry|iatrist|y|ist)$/i', '', $s);
            if (strlen($stem) < 3) $stem = $s;
            $likeClauses[] = 'specialisation LIKE ?';
            $params[] = '%' . $stem . '%';
            $types  .= 's';
        }

        // Sort: highest-rated doctors first, then most experienced, then RAND()
        // for ties. Without RAND() the same single doctor wins every fallback
        // query (e.g. Dr. Thapa always landing on top for General Medicine).
        $sql = "SELECT d.id, d.name, d.specialisation,
                       COALESCE(d.qualification, '')  AS qualification,
                       COALESCE(d.experience, 0)      AS experience,
                       COALESCE(d.clinic_name, '')    AS clinic_name,
                       COALESCE(d.clinic_address, '') AS clinic_address,
                       COALESCE(d.fee, 0)             AS fee,
                       COALESCE(ROUND(AVG(r.rating),1),0) AS avg_rating,
                       COUNT(r.id)                       AS review_count
                FROM doctors d
                LEFT JOIN reviews r ON r.doctor_id = d.id
                WHERE d.status = 'approved'
                  AND (" . implode(' OR ', $likeClauses) . ")
                GROUP BY d.id
                ORDER BY avg_rating DESC, review_count DESC, d.experience DESC, RAND()
                LIMIT ?";
        $params[] = $limit;
        $types   .= 'i';

        $stmt = $conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'id'             => (int)$r['id'],
                'name'           => $r['name'],
                'specialisation' => $r['specialisation'],
                'qualification'  => $r['qualification'],
                'experience'     => (int)$r['experience'],
                'clinic_name'    => $r['clinic_name'],
                'clinic_address' => $r['clinic_address'],
                'fee'            => (float)$r['fee'],
            ];
        }
        $stmt->close();
        return $rows;
    } catch (Throwable $e) {
        error_log("[chatbot] doctor lookup failed: " . $e->getMessage());
        return [];
    }
}