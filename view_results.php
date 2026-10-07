<?php
/**
 * view_results.php — Student Result Viewer
 * Students visit: yoursite.com/view_results.php?code=CS301&semester=Semester1&year=2024/2025
 * Or use the search form on this page directly.
 */

$resultsFile = __DIR__ . '/results_data.csv';

// ---- Helper: read CSV into array ----
function readResults($file) {
  if (!file_exists($file)) return [];
  $rows = [];
  $handle = fopen($file, 'r');
  $header = fgetcsv($handle); // skip header
  while (($row = fgetcsv($handle)) !== false) {
    if (count($row) >= 16) {
      $rows[] = [
        'submitted_at'  => $row[0],
        'lecturer_name' => $row[1],
        'lecturer_id'   => $row[2],
        'course_name'   => $row[3],
        'course_code'   => $row[4],
        'semester'      => $row[5],
        'academic_year' => $row[6],
        'exam_type'     => $row[7],
        'exam_date'     => $row[8],
        'total_marks'   => $row[9],
        'student_id'    => $row[10],
        'student_name'  => $row[11],
        'marks'         => $row[12],
        'grade'         => $row[13],
        'gpa'           => $row[14],
        'status'        => $row[15],
        'release_date'  => $row[17] ?? '',
        'visibility'    => $row[18] ?? 'students',
      ];
    }
  }
  fclose($handle);
  return $rows;
}

// ---- Sanitize input ----
function s($v) { return htmlspecialchars(strip_tags(trim($v ?? '')), ENT_QUOTES, 'UTF-8'); }

$searchStudentId   = s($_GET['student_id']   ?? $_POST['student_id']   ?? '');
$searchCourseCode  = s($_GET['code']         ?? $_POST['code']         ?? '');
$searchSemester    = s($_GET['semester']     ?? $_POST['semester']     ?? '');
$searchYear        = s($_GET['year']         ?? $_POST['year']         ?? '');

$results   = [];
$searched  = false;
$error     = '';

if ($searchStudentId || $searchCourseCode) {
  $searched = true;
  $all = readResults($resultsFile);
  $now = new DateTime();

  foreach ($all as $r) {
    // Check release date — only show if released
    if (!empty($r['release_date'])) {
      try {
        $releaseTime = new DateTime($r['release_date']);
        if ($now < $releaseTime) continue; // not yet released
      } catch (Exception $e) {}
    }

    // Filter by student ID and optionally course/semester/year
    $matchStudent = $searchStudentId
      ? strtolower($r['student_id']) === strtolower($searchStudentId)
      : true;
    $matchCourse = $searchCourseCode
      ? strtolower($r['course_code']) === strtolower($searchCourseCode)
      : true;
    $matchSemester = $searchSemester
      ? strtolower($r['semester']) === strtolower($searchSemester)
      : true;
    $matchYear = $searchYear
      ? $r['academic_year'] === $searchYear
      : true;

    if ($matchStudent && $matchCourse && $matchSemester && $matchYear) {
      $results[] = $r;
    }
  }

  if (empty($results) && !$error) {
    $error = 'No results found. Please check your Student ID or the results may not have been released yet.';
  }
}

// Grade color helper
function gradeColor($grade) {
  if (in_array($grade, ['A+','A','A-'])) return '#16a34a';
  if (in_array($grade, ['B+','B','B-'])) return '#1a6ef5';
  if (in_array($grade, ['C+','C']))       return '#d97706';
  if ($grade === 'D')                     return '#ea580c';
  if ($grade === 'F')                     return '#dc2626';
  return '#6b7a99';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>View My Results — ResultPortal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{
      --bg:#f0f4f9;--surface:#fff;--surface2:#f5f7fb;--border:#dde3ee;
      --accent:#1a6ef5;--text:#1a2035;--text-muted:#6b7a99;
      --success:#16a34a;--error:#dc2626;
      --shadow:0 4px 18px rgba(26,32,64,0.09);
      --radius:14px;--radius-sm:8px;
      --font:'Sora',sans-serif;--mono:'JetBrains Mono',monospace;
    }
    body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;font-size:15px}

    /* HEADER */
    .header{background:rgba(255,255,255,0.92);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);padding:14px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:99;box-shadow:0 1px 4px rgba(26,32,64,0.07)}
    .logo{display:flex;align-items:center;gap:9px;font-size:1.1rem;font-weight:700}
    .header-badge{background:rgba(26,110,245,0.10);border:1px solid rgba(26,110,245,0.25);color:var(--accent);font-size:0.75rem;font-weight:600;padding:4px 12px;border-radius:20px}

    /* HERO */
    .hero{padding:52px 24px 36px;text-align:center;background:linear-gradient(135deg,rgba(26,110,245,0.04),rgba(109,40,217,0.03))}
    .hero-tag{display:inline-block;font-family:var(--mono);font-size:0.72rem;letter-spacing:2px;text-transform:uppercase;color:var(--accent);background:rgba(26,110,245,0.08);border:1px solid rgba(26,110,245,0.2);padding:4px 14px;border-radius:20px;margin-bottom:18px}
    .hero h1{font-size:clamp(1.6rem,4vw,2.4rem);font-weight:700;letter-spacing:-0.8px;margin-bottom:10px;background:linear-gradient(135deg,#1a2035 30%,#1a6ef5 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
    .hero p{color:var(--text-muted);font-size:0.95rem;max-width:440px;margin:0 auto}

    /* SEARCH CARD */
    .main{max-width:780px;margin:0 auto;padding:0 24px 60px}
    .search-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:32px;box-shadow:var(--shadow);margin-bottom:28px}
    .search-card h2{font-size:1.1rem;font-weight:700;margin-bottom:6px}
    .search-card p{color:var(--text-muted);font-size:0.88rem;margin-bottom:22px}

    .field{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
    .field label{font-size:0.78rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.3px}
    .field input,.field select{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-family:var(--font);font-size:0.92rem;padding:10px 14px;outline:none;width:100%;transition:border-color 0.2s,box-shadow 0.2s;-webkit-appearance:none}
    .field input:focus,.field select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(26,110,245,0.12);background:#fff}

    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .req{color:var(--accent)}

    .btn-search{background:var(--accent);color:#fff;border:none;border-radius:var(--radius-sm);padding:12px 32px;font-family:var(--font);font-size:0.95rem;font-weight:600;cursor:pointer;width:100%;margin-top:6px;transition:all 0.2s;box-shadow:0 4px 14px rgba(26,110,245,0.25)}
    .btn-search:hover{background:#1558d0;transform:translateY(-1px)}

    /* SHARE LINK BOX */
    .share-box{background:rgba(26,110,245,0.05);border:1px solid rgba(26,110,245,0.18);border-radius:var(--radius-sm);padding:14px 18px;margin-bottom:22px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
    .share-box span{font-size:0.83rem;color:var(--text-muted);flex:1}
    .share-box code{font-family:var(--mono);font-size:0.78rem;color:var(--accent);background:rgba(26,110,245,0.08);padding:2px 8px;border-radius:4px;word-break:break-all}
    .btn-copy{background:var(--accent);color:#fff;border:none;border-radius:6px;padding:6px 14px;font-size:0.8rem;font-weight:600;cursor:pointer;font-family:var(--font);transition:background 0.2s}
    .btn-copy:hover{background:#1558d0}

    /* ERROR */
    .alert-error{background:rgba(220,38,38,0.07);border:1px solid rgba(220,38,38,0.2);color:var(--error);border-radius:var(--radius-sm);padding:14px 18px;font-size:0.88rem;margin-bottom:20px}

    /* RESULTS TABLE */
    .results-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
    .results-header h3{font-size:1rem;font-weight:700}
    .results-count{font-size:0.8rem;color:var(--text-muted);font-family:var(--mono)}

    .result-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:24px;margin-bottom:16px;box-shadow:var(--shadow);transition:box-shadow 0.2s}
    .result-card:hover{box-shadow:0 6px 24px rgba(26,32,64,0.13)}

    .card-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;gap:12px}
    .card-course{flex:1}
    .card-course h4{font-size:1rem;font-weight:700;margin-bottom:3px}
    .card-course span{font-size:0.82rem;color:var(--text-muted)}
    .grade-big{font-family:var(--mono);font-size:2.2rem;font-weight:700;line-height:1}
    .gpa-label{font-size:0.72rem;color:var(--text-muted);text-align:center;margin-top:3px}

    .card-details{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;border-top:1px solid var(--border);padding-top:14px}
    .detail-item label{font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.3px;display:block}
    .detail-item value{font-size:0.9rem;font-weight:600;font-family:var(--mono)}

    .status-badge{display:inline-block;padding:2px 10px;border-radius:20px;font-size:0.75rem;font-weight:600}
    .status-present{background:rgba(22,163,74,0.1);color:var(--success)}
    .status-absent{background:rgba(220,38,38,0.1);color:var(--error)}
    .status-disqualified{background:rgba(107,122,153,0.12);color:var(--text-muted)}

    /* FOOTER */
    .footer{text-align:center;padding:22px;font-size:0.8rem;color:var(--text-muted);border-top:1px solid var(--border);background:#fff}

    @media(max-width:600px){.grid-2{grid-template-columns:1fr}.card-details{grid-template-columns:1fr 1fr}.card-top{flex-direction:column}}
  </style>
</head>
<body>

<header class="header">
  <div class="logo">📋 <span>ResultPortal</span></div>
  <div class="header-badge">Student View</div>
</header>

<section class="hero">
  <div class="hero-tag">Check Your Results</div>
  <h1>View Examination Results</h1>
  <p>Enter your Student ID to view your released results.</p>
</section>

<main class="main">

  <!-- Search Form -->
  <div class="search-card">
    <h2>Search Your Results</h2>
    <p>Enter your Student ID and optionally filter by course or semester.</p>

    <form method="GET" action="">
      <div class="field">
        <label for="student_id">Student ID <span class="req">*</span></label>
        <input type="text" id="student_id" name="student_id" value="<?= s($searchStudentId) ?>" placeholder="e.g. S001" required />
      </div>
      <div class="grid-2">
        <div class="field">
          <label for="code">Course Code (optional)</label>
          <input type="text" id="code" name="code" value="<?= s($searchCourseCode) ?>" placeholder="e.g. CS301" />
        </div>
        <div class="field">
          <label for="year">Academic Year (optional)</label>
          <input type="text" id="year" name="year" value="<?= s($searchYear) ?>" placeholder="e.g. 2024/2025" />
        </div>
      </div>
      <div class="field">
        <label for="semester">Semester (optional)</label>
        <select id="semester" name="semester">
          <option value="">All Semesters</option>
          <option value="Semester 1" <?= $searchSemester==='Semester 1'?'selected':'' ?>>Semester 1</option>
          <option value="Semester 2" <?= $searchSemester==='Semester 2'?'selected':'' ?>>Semester 2</option>
          <option value="Semester 3" <?= $searchSemester==='Semester 3'?'selected':'' ?>>Semester 3</option>
          <option value="Semester 4" <?= $searchSemester==='Semester 4'?'selected':'' ?>>Semester 4</option>
          <option value="Semester 5" <?= $searchSemester==='Semester 5'?'selected':'' ?>>Semester 5</option>
          <option value="Semester 6" <?= $searchSemester==='Semester 6'?'selected':'' ?>>Semester 6</option>
        </select>
      </div>
      <button type="submit" class="btn-search">🔍 Search Results</button>
    </form>
  </div>

  <?php if ($error): ?>
    <div class="alert-error">⚠️ <?= $error ?></div>
  <?php endif; ?>

  <?php if ($searched && !empty($results)): ?>

    <!-- Share Link Info Box -->
    <?php
      $shareUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
      $shareUrl .= '?student_id=' . urlencode($searchStudentId);
      if ($searchCourseCode) $shareUrl .= '&code=' . urlencode($searchCourseCode);
      if ($searchSemester)   $shareUrl .= '&semester=' . urlencode($searchSemester);
      if ($searchYear)       $shareUrl .= '&year=' . urlencode($searchYear);
    ?>
    <div class="share-box">
      <span>🔗 Your result link:</span>
      <code id="shareUrl"><?= s($shareUrl) ?></code>
      <button class="btn-copy" onclick="copyLink()">Copy Link</button>
    </div>

    <!-- Results -->
    <div class="results-header">
      <h3>Your Results</h3>
      <span class="results-count"><?= count($results) ?> record(s) found</span>
    </div>

    <?php foreach ($results as $r): ?>
      <?php $gc = gradeColor($r['grade']); ?>
      <div class="result-card">
        <div class="card-top">
          <div class="card-course">
            <h4><?= s($r['course_name']) ?></h4>
            <span><?= s($r['course_code']) ?> &nbsp;·&nbsp; <?= s($r['semester']) ?> &nbsp;·&nbsp; <?= s($r['academic_year']) ?></span>
          </div>
          <div style="text-align:center">
            <div class="grade-big" style="color:<?= $gc ?>"><?= s($r['grade']) ?></div>
            <div class="gpa-label">GPA <?= s($r['gpa']) ?></div>
          </div>
        </div>
        <div class="card-details">
          <div class="detail-item">
            <label>Student ID</label>
            <value><?= s($r['student_id']) ?></value>
          </div>
          <div class="detail-item">
            <label>Marks</label>
            <value><?= s($r['marks']) ?> / <?= s($r['total_marks']) ?></value>
          </div>
          <div class="detail-item">
            <label>Percentage</label>
            <value><?= $r['total_marks'] > 0 ? round(($r['marks']/$r['total_marks'])*100, 1).'%' : '—' ?></value>
          </div>
          <div class="detail-item">
            <label>Status</label>
            <value>
              <span class="status-badge status-<?= s($r['status']) ?>">
                <?= ucfirst(s($r['status'])) ?>
              </span>
            </value>
          </div>
          <div class="detail-item">
            <label>Student Name</label>
            <value style="font-family:var(--font)"><?= s($r['student_name']) ?></value>
          </div>
          <div class="detail-item">
            <label>Released On</label>
            <value style="font-family:var(--font);font-size:0.78rem"><?= $r['release_date'] ? date('d M Y, H:i', strtotime($r['release_date'])) : '—' ?></value>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

  <?php endif; ?>

</main>

<footer class="footer">
  ResultPortal &copy; <?= date('Y') ?> — Secure Academic Result Management System
</footer>

<script>
function copyLink() {
  const url = document.getElementById('shareUrl').textContent;
  navigator.clipboard.writeText(url).then(() => {
    const btn = document.querySelector('.btn-copy');
    btn.textContent = '✓ Copied!';
    setTimeout(() => btn.textContent = 'Copy Link', 2000);
  });
}
</script>
</body>
</html>
