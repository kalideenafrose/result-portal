<?php
/**
 * submit.php — Result Release Backend
 * Receives JSON from the frontend and saves to a CSV/database.
 * 
 * SETUP: Place this file alongside index.html on your PHP server.
 * For production: connect to a MySQL database instead of CSV.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    http_response_code(405);
    exit();
}

// Read JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    http_response_code(400);
    exit();
}

// ===== VALIDATE REQUIRED FIELDS =====
$required = ['lecturerName', 'lecturerId', 'courseName', 'courseCode', 'semester', 'academicYear', 'examType', 'examDate', 'totalMarks', 'students'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        http_response_code(422);
        exit();
    }
}

if (!is_array($data['students']) || count($data['students']) === 0) {
    echo json_encode(['success' => false, 'message' => 'No student results provided.']);
    http_response_code(422);
    exit();
}

// ===== SANITIZE =====
function sanitize($val) {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

$course = [
    'lecturer_name'  => sanitize($data['lecturerName']),
    'lecturer_id'    => sanitize($data['lecturerId']),
    'course_name'    => sanitize($data['courseName']),
    'course_code'    => sanitize($data['courseCode']),
    'semester'       => sanitize($data['semester']),
    'academic_year'  => sanitize($data['academicYear']),
    'exam_type'      => sanitize($data['examType']),
    'exam_date'      => sanitize($data['examDate']),
    'total_marks'    => (float)$data['totalMarks'],
    'remarks'        => sanitize($data['remarks'] ?? ''),
    'release_date'   => sanitize($data['releaseDate'] ?? ''),
    'visibility'     => sanitize($data['visibility'] ?? 'students'),
    'notify_email'   => (bool)($data['notifyEmail'] ?? false),
    'submitted_at'   => date('Y-m-d H:i:s'),
];

// ===== OPTION A: Save to CSV file =====
// (Simple, no database needed. Good for small setups.)
$csvFile = __DIR__ . '/results_data.csv';
$isNew = !file_exists($csvFile);
$fp = fopen($csvFile, 'a');

if ($fp === false) {
    echo json_encode(['success' => false, 'message' => 'Could not write results file.']);
    http_response_code(500);
    exit();
}

// Write header row on first run
if ($isNew) {
    fputcsv($fp, ['submitted_at', 'lecturer_name', 'lecturer_id', 'course_name', 'course_code',
        'semester', 'academic_year', 'exam_type', 'exam_date', 'total_marks',
        'student_id', 'student_name', 'marks', 'grade', 'gpa', 'status', 'remarks',
        'release_date', 'visibility', 'notify_email']);
}

// Write one row per student
foreach ($data['students'] as $student) {
    $row = [
        $course['submitted_at'],
        $course['lecturer_name'],
        $course['lecturer_id'],
        $course['course_name'],
        $course['course_code'],
        $course['semester'],
        $course['academic_year'],
        $course['exam_type'],
        $course['exam_date'],
        $course['total_marks'],
        sanitize($student['studentId'] ?? ''),
        sanitize($student['studentName'] ?? ''),
        (float)($student['marks'] ?? 0),
        sanitize($student['grade'] ?? ''),
        sanitize($student['gpa'] ?? ''),
        sanitize($student['status'] ?? ''),
        $course['remarks'],
        $course['release_date'],
        $course['visibility'],
        $course['notify_email'] ? 'Yes' : 'No',
    ];
    fputcsv($fp, $row);
}

fclose($fp);

// ===== OPTION B: Save to MySQL Database =====
// Uncomment and configure to use MySQL instead of CSV.
/*
$dsn  = 'mysql:host=localhost;dbname=results_db;charset=utf8mb4';
$user = 'db_user';
$pass = 'db_password';

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Create table if not exists (run once)
    $pdo->exec("CREATE TABLE IF NOT EXISTS results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        submitted_at DATETIME,
        lecturer_name VARCHAR(150),
        lecturer_id VARCHAR(50),
        course_name VARCHAR(200),
        course_code VARCHAR(50),
        semester VARCHAR(50),
        academic_year VARCHAR(20),
        exam_type VARCHAR(50),
        exam_date DATE,
        total_marks FLOAT,
        student_id VARCHAR(50),
        student_name VARCHAR(150),
        marks FLOAT,
        grade VARCHAR(5),
        gpa VARCHAR(5),
        status VARCHAR(20),
        remarks TEXT,
        release_date DATETIME,
        visibility VARCHAR(30),
        notify_email TINYINT(1)
    )");

    $stmt = $pdo->prepare("INSERT INTO results
        (submitted_at, lecturer_name, lecturer_id, course_name, course_code, semester, academic_year,
         exam_type, exam_date, total_marks, student_id, student_name, marks, grade, gpa,
         status, remarks, release_date, visibility, notify_email)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    foreach ($data['students'] as $student) {
        $stmt->execute([
            $course['submitted_at'], $course['lecturer_name'], $course['lecturer_id'],
            $course['course_name'], $course['course_code'], $course['semester'],
            $course['academic_year'], $course['exam_type'], $course['exam_date'],
            $course['total_marks'],
            sanitize($student['studentId'] ?? ''),
            sanitize($student['studentName'] ?? ''),
            (float)($student['marks'] ?? 0),
            sanitize($student['grade'] ?? ''),
            sanitize($student['gpa'] ?? ''),
            sanitize($student['status'] ?? ''),
            $course['remarks'], $course['release_date'],
            $course['visibility'], $course['notify_email'] ? 1 : 0
        ]);
    }
} catch (PDOException $e) {
    error_log('DB Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Check server logs.']);
    http_response_code(500);
    exit();
}
*/

// ===== OPTIONAL: Send notification email =====
if ($course['notify_email']) {
    // Example: send email using PHP mail() — replace with PHPMailer for production
    // $to = 'students@university.edu';
    // $subject = "Results Released: {$course['course_name']}";
    // $body = "Results for {$course['course_name']} ({$course['exam_type']}) have been released.";
    // mail($to, $subject, $body);
}

// ===== GENERATE REFERENCE NUMBER =====
$ref = strtoupper($course['course_code']) . '-' . date('Ymd') . '-' . rand(1000, 9999);

echo json_encode([
    'success'    => true,
    'message'    => 'Results submitted successfully.',
    'reference'  => $ref,
    'students'   => count($data['students']),
    'course'     => $course['course_name'],
    'submitted'  => $course['submitted_at'],
]);
?>
