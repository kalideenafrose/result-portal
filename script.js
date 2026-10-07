// ===== STATE =====
let studentCount = 0;

// ===== INIT =====
document.addEventListener('DOMContentLoaded', () => {
  addStudent();
  addStudent();
  addStudent();
  document.getElementById('resultForm').addEventListener('submit', handleSubmit);

  // Set default release date to now + 1 hour
  const now = new Date(Date.now() + 60 * 60 * 1000);
  document.getElementById('releaseDate').value = now.toISOString().slice(0, 16);

  // Setup drag & drop
  const zone = document.getElementById('uploadZone');
  if (zone) {
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
    zone.addEventListener('drop', e => {
      e.preventDefault();
      zone.classList.remove('dragover');
      const file = e.dataTransfer.files[0];
      if (file) handleFileUpload({ target: { files: [file] } });
    });
  }
});

// ===== STEP NAVIGATION =====
function goStep(target) {
  const current = parseInt(document.querySelector('.form-panel.active').id.split('-')[1]);
  if (target > current && !validateStep(current)) return;

  document.querySelectorAll('.form-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('panel-' + target).classList.add('active');

  document.querySelectorAll('.step').forEach(s => {
    const n = parseInt(s.dataset.step);
    s.classList.remove('active', 'done');
    if (n === target) s.classList.add('active');
    else if (n < target) s.classList.add('done');
  });

  window.scrollTo({ top: 0, behavior: 'smooth' });
  if (target === 3) buildSummary();
}

// ===== VALIDATION =====
function validateStep(step) {
  let ok = true;

  if (step === 1) {
    const fields = ['courseName', 'courseCode', 'semester', 'academicYear', 'totalMarks'];
    fields.forEach(id => {
      const el = document.getElementById(id);
      const err = document.getElementById('err-' + id);
      if (!el.value.trim()) {
        el.classList.add('invalid');
        if (err) err.textContent = 'This field is required.';
        ok = false;
      } else {
        el.classList.remove('invalid');
        if (err) err.textContent = '';
      }
    });
  }

  if (step === 2) {
    const rows = document.querySelectorAll('.student-row');
    if (rows.length === 0) {
      alert('Please add at least one student.');
      return false;
    }
    rows.forEach(row => {
      const idField   = row.querySelector('[name="studentId"]');
      const nameField = row.querySelector('[name="studentName"]');
      const marksField = row.querySelector('[name="marks"]');
      [idField, nameField, marksField].forEach(f => {
        if (!f.value.trim()) { f.classList.add('invalid'); ok = false; }
        else f.classList.remove('invalid');
      });
    });
    if (!ok) alert('Please fill in all student fields (ID, Name, Marks).');
  }

  return ok;
}

// ===== GRADE CALCULATION =====
function calcGrade(marks, total) {
  if (!marks || !total || isNaN(marks) || isNaN(total))
    return { grade: '—', gpa: '—', color: 'var(--text-muted)' };
  const pct = (parseFloat(marks) / parseFloat(total)) * 100;
  if (pct >= 90) return { grade: 'A+', gpa: '4.0', color: 'var(--grade-a)' };
  if (pct >= 80) return { grade: 'A',  gpa: '4.0', color: 'var(--grade-a)' };
  if (pct >= 75) return { grade: 'A-', gpa: '3.7', color: 'var(--grade-a)' };
  if (pct >= 70) return { grade: 'B+', gpa: '3.3', color: 'var(--grade-b)' };
  if (pct >= 65) return { grade: 'B',  gpa: '3.0', color: 'var(--grade-b)' };
  if (pct >= 60) return { grade: 'B-', gpa: '2.7', color: 'var(--grade-b)' };
  if (pct >= 55) return { grade: 'C+', gpa: '2.3', color: 'var(--grade-c)' };
  if (pct >= 50) return { grade: 'C',  gpa: '2.0', color: 'var(--grade-c)' };
  if (pct >= 45) return { grade: 'D',  gpa: '1.0', color: 'var(--grade-d)' };
  return { grade: 'F', gpa: '0.0', color: 'var(--grade-f)' };
}

// ===== ADD STUDENT MANUALLY =====
function addStudent() {
  studentCount++;
  const total = document.getElementById('totalMarks').value || 100;
  const id = studentCount;

  const row = document.createElement('div');
  row.className = 'student-row';
  row.id = 'student-' + id;
  row.innerHTML = `
    <div class="field">
      <label>Student ID</label>
      <input type="text" name="studentId" placeholder="S${String(id).padStart(3,'0')}" oninput="updateGradeBadge(${id})" />
    </div>
    <div class="field">
      <label>Full Name</label>
      <input type="text" name="studentName" placeholder="Full name" oninput="updateGradeBadge(${id})" />
    </div>
    <div class="field">
      <label>Marks</label>
      <input type="number" name="marks" placeholder="0" min="0" max="${total}" oninput="updateGradeBadge(${id})" />
    </div>
    <div class="field">
      <label>Status</label>
      <select name="status">
        <option value="present">Present</option>
        <option value="absent">Absent</option>
        <option value="disqualified">DQ</option>
      </select>
    </div>
    <div id="badge-${id}" class="grade-badge">—</div>
    <button type="button" class="btn-remove" onclick="removeStudent(${id})" title="Remove">✕</button>
  `;
  document.getElementById('studentsContainer').appendChild(row);
}

function removeStudent(id) {
  const row = document.getElementById('student-' + id);
  if (row) row.remove();
}

function updateGradeBadge(id) {
  const row = document.getElementById('student-' + id);
  if (!row) return;
  const marks = row.querySelector('[name="marks"]').value;
  const total = document.getElementById('totalMarks').value || 100;
  const { grade, color } = calcGrade(marks, total);
  const badge = document.getElementById('badge-' + id);
  badge.textContent = grade;
  badge.style.color = color;
  badge.style.borderColor = color;
}

// ===== CSV UPLOAD =====
function handleFileUpload(event) {
  const file = event.target.files[0];
  if (!file) return;

  const ext = file.name.split('.').pop().toLowerCase();
  if (!['csv'].includes(ext)) {
    showUploadStatus('error', '❌ Please upload a .csv file. Download the template below to get started.');
    return;
  }

  const reader = new FileReader();
  reader.onload = e => parseCSV(e.target.result, file.name);
  reader.readAsText(file);
}

function parseCSV(text, filename) {
  const lines = text.trim().split(/\r?\n/);
  if (lines.length < 2) {
    showUploadStatus('error', '❌ File is empty or has no student data.');
    return;
  }

  const header = lines[0].toLowerCase().split(',').map(h => h.trim().replace(/"/g, ''));
  const idIdx    = header.findIndex(h => h.includes('id') || h.includes('student_id') || h.includes('reg'));
  const nameIdx  = header.findIndex(h => h.includes('name') || h.includes('student_name'));
  const marksIdx = header.findIndex(h => h.includes('mark') || h.includes('score') || h.includes('result'));

  if (idIdx === -1 || nameIdx === -1 || marksIdx === -1) {
    showUploadStatus('error', '❌ Could not find required columns. Make sure your file has: student_id, student_name, marks');
    return;
  }

  // Clear existing rows
  document.getElementById('studentsContainer').innerHTML = '';
  studentCount = 0;

  const total = document.getElementById('totalMarks').value || 100;
  let loaded = 0;

  for (let i = 1; i < lines.length; i++) {
    const line = lines[i].trim();
    if (!line) continue;

    const cols   = parseCSVLine(line);
    const sid    = (cols[idIdx]    || '').trim();
    const sname  = (cols[nameIdx]  || '').trim();
    const marks  = (cols[marksIdx] || '').trim();

    if (!sid && !sname) continue;

    studentCount++;
    const id = studentCount;
    const { grade, color } = calcGrade(marks, total);

    const row = document.createElement('div');
    row.className = 'student-row';
    row.id = 'student-' + id;
    row.innerHTML = `
      <div class="field">
        <label>Student ID</label>
        <input type="text" name="studentId" value="${escHtml(sid)}" oninput="updateGradeBadge(${id})" />
      </div>
      <div class="field">
        <label>Full Name</label>
        <input type="text" name="studentName" value="${escHtml(sname)}" oninput="updateGradeBadge(${id})" />
      </div>
      <div class="field">
        <label>Marks</label>
        <input type="number" name="marks" value="${escHtml(marks)}" min="0" max="${total}" oninput="updateGradeBadge(${id})" />
      </div>
      <div class="field">
        <label>Status</label>
        <select name="status">
          <option value="present" selected>Present</option>
          <option value="absent">Absent</option>
          <option value="disqualified">DQ</option>
        </select>
      </div>
      <div id="badge-${id}" class="grade-badge" style="color:${color};border-color:${color}">${grade}</div>
      <button type="button" class="btn-remove" onclick="removeStudent(${id})" title="Remove">✕</button>
    `;
    document.getElementById('studentsContainer').appendChild(row);
    loaded++;
  }

  if (loaded === 0) {
    showUploadStatus('error', '❌ No valid student records found in the file.');
    return;
  }

  document.getElementById('uploadCount').textContent = `✓ ${loaded} students loaded`;
  showUploadStatus('success', `✅ Successfully loaded ${loaded} student${loaded > 1 ? 's' : ''} from "${filename}". Review the rows below and correct any errors before proceeding.`);
}

function parseCSVLine(line) {
  const result = [];
  let cur = '', inQuotes = false;
  for (let i = 0; i < line.length; i++) {
    const ch = line[i];
    if (ch === '"') { inQuotes = !inQuotes; }
    else if (ch === ',' && !inQuotes) { result.push(cur); cur = ''; }
    else { cur += ch; }
  }
  result.push(cur);
  return result;
}

function showUploadStatus(type, msg) {
  const el = document.getElementById('uploadStatus');
  el.className = 'upload-status ' + type;
  el.textContent = msg;
  el.style.display = 'block';
}

function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ===== DOWNLOAD CSV TEMPLATE =====
function downloadTemplate() {
  const csv = [
    'student_id,student_name,marks',
    'S001,Ahmad bin Ali,85',
    'S002,Siti Nurhaliza,72',
    'S003,Raj Kumar,60',
    'S004,Mei Ling,91',
    'S005,John David,44',
  ].join('\n');

  const blob = new Blob([csv], { type: 'text/csv' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href     = url;
  a.download = 'student_marks_template.csv';
  a.click();
  URL.revokeObjectURL(url);
}

// ===== BUILD SUMMARY (STEP 3) =====
function buildSummary() {
  const get = id => document.getElementById(id)?.value || '—';
  const box  = document.getElementById('summaryBox');
  const rows = document.querySelectorAll('.student-row');
  const total = parseFloat(get('totalMarks')) || 100;

  let studentHTML = '';
  let marksSum = 0, passCount = 0, validCount = 0, highest = 0;

  rows.forEach(row => {
    const sid    = row.querySelector('[name="studentId"]').value   || '—';
    const sname  = row.querySelector('[name="studentName"]').value || '—';
    const smarks = row.querySelector('[name="marks"]').value;
    const sstatus = row.querySelector('[name="status"]').value;
    const { grade, color } = calcGrade(smarks, total);

    if (smarks && sstatus === 'present') {
      const m = parseFloat(smarks);
      marksSum += m;
      validCount++;
      if (m > highest) highest = m;
      if (['A+','A','A-','B+','B','B-','C+','C'].includes(grade)) passCount++;
    }

    studentHTML += `
      <div class="summary-student-row">
        <span style="font-family:var(--mono);font-size:0.8rem;color:var(--text-muted)">${sid}</span>
        <span>${sname}</span>
        <span>${smarks || (sstatus !== 'present' ? sstatus.toUpperCase() : '—')}</span>
        <span class="s-grade" style="color:${color}">${sstatus !== 'present' ? '—' : grade}</span>
      </div>`;
  });

  const avg      = validCount > 0 ? (marksSum / validCount).toFixed(1) : '—';
  const passRate = validCount > 0 ? ((passCount / validCount) * 100).toFixed(0) + '%' : '—';
  const highMark = validCount > 0 ? highest : '—';

  box.innerHTML = `
    <h3>Submission Summary</h3>
    <div class="summary-grid">
      <div class="summary-item"><label>Course</label><value>${get('courseName')} — ${get('courseCode')}</value></div>
      <div class="summary-item"><label>Semester / Year</label><value>${get('semester')}, ${get('academicYear')}</value></div>
      <div class="summary-item"><label>Total Marks</label><value>${get('totalMarks')}</value></div>
    </div>
    <div class="stats-row">
      <div class="stat-card"><div class="s-val">${rows.length}</div><div class="s-lbl">Students</div></div>
      <div class="stat-card"><div class="s-val">${avg}</div><div class="s-lbl">Class Average</div></div>
      <div class="stat-card"><div class="s-val">${passRate}</div><div class="s-lbl">Pass Rate</div></div>
      <div class="stat-card"><div class="s-val">${highMark}</div><div class="s-lbl">Highest Mark</div></div>
    </div>
    <div class="summary-students">
      <h4>Student Results</h4>
      <div class="summary-student-row" style="font-weight:600;font-size:0.78rem;color:var(--text-muted)">
        <span>Student ID</span><span>Name</span><span>Marks</span><span>Grade</span>
      </div>
      ${studentHTML}
    </div>`;
}

// ===== FORM SUBMIT =====
function handleSubmit(e) {
  e.preventDefault();

  const confirm    = document.getElementById('confirmAccuracy');
  const releaseDate = document.getElementById('releaseDate');
  let ok = true;

  if (!confirm.checked) {
    document.getElementById('err-confirm').textContent = 'You must confirm the accuracy of the results.';
    ok = false;
  } else {
    document.getElementById('err-confirm').textContent = '';
  }

  if (!releaseDate.value) {
    document.getElementById('err-releaseDate').textContent = 'Please set a release date.';
    ok = false;
  } else {
    document.getElementById('err-releaseDate').textContent = '';
  }

  if (!ok) return;

  const formData = collectFormData();
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<span>⏳</span> Submitting...';

  // Replace the setTimeout below with a real fetch() call to submit.php for production:
  // fetch('submit.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(formData) })
  //   .then(r => r.json()).then(res => { if(res.success) showSuccess(formData); else alert('Error: '+res.message); });

  setTimeout(() => showSuccess(formData), 1500);
}

function collectFormData() {
  const get = id => document.getElementById(id)?.value || '';
  const students = [];

  document.querySelectorAll('.student-row').forEach(row => {
    const marks = row.querySelector('[name="marks"]').value;
    const total = get('totalMarks') || 100;
    const { grade, gpa } = calcGrade(marks, total);
    students.push({
      studentId:   row.querySelector('[name="studentId"]').value,
      studentName: row.querySelector('[name="studentName"]').value,
      marks, grade, gpa,
      status: row.querySelector('[name="status"]').value,
    });
  });

  return {
    courseName:   get('courseName'),
    courseCode:   get('courseCode'),
    semester:     get('semester'),
    academicYear: get('academicYear'),
    totalMarks:   get('totalMarks'),
    releaseDate:  get('releaseDate'),
    visibility:   get('visibility'),
    notifyEmail:  document.getElementById('notifyEmail').checked,
    students,
    submittedAt:  new Date().toISOString(),
  };
}

function showSuccess(data) {
  document.getElementById('resultForm').style.display  = 'none';
  document.querySelector('.steps').style.display = 'none';

  document.getElementById('successMeta').innerHTML = `
    Course: <strong>${data.courseName} (${data.courseCode})</strong><br>
    Students: <strong>${data.students.length}</strong><br>
    Release Date: <strong>${new Date(data.releaseDate).toLocaleString()}</strong><br>
    Visibility: <strong>${data.visibility}</strong><br>
    Email Notification: <strong>${data.notifyEmail ? 'Yes' : 'No'}</strong>
  `;

  document.getElementById('successScreen').style.display = 'block';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
  document.getElementById('resultForm').reset();
  document.getElementById('studentsContainer').innerHTML = '';
  document.getElementById('uploadCount').textContent = '';
  document.getElementById('uploadStatus').style.display = 'none';
  studentCount = 0;
  document.getElementById('successScreen').style.display = 'none';
  document.getElementById('resultForm').style.display    = 'block';
  document.querySelector('.steps').style.display  = 'flex';

  const now = new Date(Date.now() + 60 * 60 * 1000);
  document.getElementById('releaseDate').value = now.toISOString().slice(0, 16);

  goStep(1);
  addStudent();
  addStudent();
  addStudent();
}
