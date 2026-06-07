<?php
require_once __DIR__ . '/auth.php';
require_login();

$username = $_SESSION['doctor_username'];
$account = load_account($username);
$doctorName = $account['username'] ?? $username;
$doctorType = $account['doctor_type'] ?? '';

$DEFAULT_TEMPLATE = "Passport section\nComplaints\nHistory of illness\nLife and dental history\nOrthodontic status\nExamination findings\nPreliminary conclusion\nTreatment plan\nRecommendations\nInformed consent\nSession summary";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MedScribe — <?= htmlspecialchars($doctorName) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<!-- TOP BAR -->
<header class="topbar">
<div class="topbar-brand">
<svg width="28" height="28" viewBox="0 0 36 36" fill="none"><rect width="36" height="36" rx="10" fill="#3b82f6"/><path d="M10 18h16M18 10v16" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/></svg>
<span>MedScribe</span>
</div>
<div class="topbar-doctor">
<span class="badge">Dr. <?= htmlspecialchars($doctorName) ?></span>
<span class="badge muted"><?= htmlspecialchars($doctorType) ?></span>
<a href="logout.php" class="btn btn-sm btn-ghost">Logout</a>
</div>
</header>

<div class="layout">

<!-- MAIN PANEL -->
<main class="main-panel">

<!-- Patient -->
<section class="card">
<h2 class="section-title">Patient</h2>
<input type="text" id="patientName" placeholder="Patient surname / name" class="field">
</section>

<!-- Consultation template -->
<section class="card">
<div class="card-header">
<h2 class="section-title">Consultation Sheet Structure</h2>
<div class="btn-group">
<button class="btn btn-sm" onclick="document.getElementById('templateFile').click()">Load .docx</button>
<input type="file" id="templateFile" accept=".docx,.txt" style="display:none" onchange="loadTemplate(this)">
<button class="btn btn-sm" onclick="resetTemplate()">Reset</button>
</div>
</div>
<textarea id="templateText" class="field template-area" rows="8"><?= htmlspecialchars($DEFAULT_TEMPLATE) ?></textarea>
</section>

<!-- Recording -->
<div class="two-col">

<section class="card group-card">
<h2 class="section-title">Doctor Voice Sample</h2>
<p id="sampleStatus" class="status-text muted">Status: no sample recorded</p>
<div class="btn-row">
<button id="recDoctorBtn" class="btn btn-accent" onclick="startDoctorRec()">Record (10s)</button>
<button id="stopDoctorBtn" class="btn btn-danger" onclick="stopDoctorRec()" disabled>Stop</button>
</div>
<progress id="doctorProgress" class="progress" value="0" max="10" style="display:none"></progress>
</section>

<section class="card group-card">
<h2 class="section-title">Doctor-Patient Dialogue</h2>
<p id="dialogueStatus" class="status-text muted">Status: not recorded</p>
<div class="btn-row">
<button id="recDialogueBtn" class="btn btn-accent" onclick="startDialogueRec()">Start Recording</button>
<button id="stopDialogueBtn" class="btn btn-danger" onclick="stopDialogueRec()" disabled>Stop</button>
</div>
<progress id="dialogueProgress" class="progress indeterminate" style="display:none"></progress>
</section>

</div>

<!-- Process -->
<button id="processBtn" class="btn btn-primary btn-large" onclick="processSession()">Process Dialogue</button>

<!-- Result -->
<section class="card" id="resultCard" style="display:none">
<div class="card-header">
<h2 class="section-title">Medical Record</h2>
<button class="btn btn-sm btn-accent" onclick="downloadResult()">Download .txt</button>
</div>
<div id="resultContent" class="result-box"></div>
</section>

<!-- Transcript -->
<section class="card" id="transcriptCard" style="display:none">
<h2 class="section-title">Full Transcript</h2>
<div id="transcriptContent" class="result-box muted-text"></div>
</section>

</main>

<!-- SIDEBAR -->
<aside class="sidebar">
<div class="sidebar-header">
<span class="section-title">Files</span>
<button class="btn btn-sm btn-ghost" onclick="loadFiles()">↻</button>
</div>
<div id="fileList" class="file-list">
<p class="muted small">Loading…</p>
</div>
</aside>

</div>

<!-- PROGRESS OVERLAY -->
<div id="overlay" class="overlay" style="display:none">
<div class="overlay-box">
<div class="spinner"></div>
<p id="overlayMsg">Processing…</p>
<progress id="overlayProgress" class="progress" value="0" max="100"></progress>
</div>
</div>

<script>
// Auto-redirect to HTTPS for microphone access
//if (location.protocol === 'http:' && location.hostname !== 'localhost') {
// location.replace('https://' + location.host + location.pathname + location.search);
//}

// ── STATE ──────────────────────────────────────────────────────────────────
let doctorStream = null;
let doctorRec = null;
let doctorTimer = null;
let doctorSeconds = 0;
let dialogueStream = null;
let dialogueRec = null;
let dialogueTimer = null;
let dialogueSeconds = 0;

let transcriptText = '';
let resultText = '';

const DEFAULT_TEMPLATE = <?= json_encode($DEFAULT_TEMPLATE) ?>;

// ── TEMPLATE ───────────────────────────────────────────────────────────────
function resetTemplate() {
document.getElementById('templateText').value = DEFAULT_TEMPLATE;
}

function loadTemplate(input) {
const file = input.files[0];
if (!file) return;
const reader = new FileReader();
reader.onload = e => {
// For .txt files load directly; .docx shows placeholder
if (file.name.endsWith('.txt')) {
document.getElementById('templateText').value = e.target.result;
} else {
alert('For .docx: please paste the structure text manually into the text area, or use a .txt file.');
}
};
reader.readAsText(file);
input.value = '';
}

// ── DOCTOR VOICE RECORDING ─────────────────────────────────────────────────
async function startDoctorRec() {
try {
doctorStream = await (navigator.mediaDevices || {}).getUserMedia({ audio: true });
doctorRec = new MediaRecorder(doctorStream);
const chunks = [];
doctorRec.ondataavailable = e => chunks.push(e.data);
doctorRec.onstop = () => {
doctorStream.getTracks().forEach(t => t.stop());
document.getElementById('sampleStatus').textContent = 'Status: Sample recorded';
document.getElementById('sampleStatus').className = 'status-text success';
};
doctorRec.start();
doctorSeconds = 0;
document.getElementById('doctorProgress').style.display = '';
document.getElementById('doctorProgress').value = 0;
document.getElementById('recDoctorBtn').disabled = true;
document.getElementById('stopDoctorBtn').disabled = false;
document.getElementById('sampleStatus').textContent = 'Status: Recording…';

doctorTimer = setInterval(() => {
doctorSeconds++;
document.getElementById('doctorProgress').value = doctorSeconds;
if (doctorSeconds >= 10) stopDoctorRec();
}, 1000);
} catch(e) {
alert('Microphone access denied: ' + e.message);
}
}

function stopDoctorRec() {
clearInterval(doctorTimer);
if (doctorRec && doctorRec.state !== 'inactive') doctorRec.stop();
document.getElementById('recDoctorBtn').disabled = false;
document.getElementById('stopDoctorBtn').disabled = true;
document.getElementById('doctorProgress').style.display = 'none';
}

// ── DIALOGUE RECORDING ─────────────────────────────────────────────────────
async function startDialogueRec() {
transcriptText = '';
try {
dialogueStream = await navigator.mediaDevices.getUserMedia({ audio: true });
dialogueRec = new MediaRecorder(dialogueStream);
dialogueRec.onstop = () => {
dialogueStream.getTracks().forEach(t => t.stop());
};
dialogueRec.start();

dialogueSeconds = 0;
document.getElementById('dialogueProgress').style.display = '';
document.getElementById('recDialogueBtn').disabled = true;
document.getElementById('stopDialogueBtn').disabled = false;
document.getElementById('dialogueStatus').textContent = 'Status: Recording…';
document.getElementById('dialogueStatus').className = 'status-text danger';

// Web Speech API for live transcription
if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
window._sr = new SR();
window._sr.continuous = true;
window._sr.interimResults = false;
window._sr.lang = document.getElementById('langSelect')?.value || 'ru-RU';
window._sr.onresult = e => {
for (let i = e.resultIndex; i < e.results.length; i++) {
if (e.results[i].isFinal) {
transcriptText += e.results[i][0].transcript + ' ';
}
}
};
window._sr.onerror = err => console.warn('SR error:', err.error);
window._sr.start();
} else {
alert('Web Speech API not supported. Use Chrome for live transcription.');
}

dialogueTimer = setInterval(() => {
dialogueSeconds++;
document.getElementById('dialogueStatus').textContent = `Status: Recording… ${dialogueSeconds}s`;
}, 1000);

} catch(e) {
alert('Microphone access denied: ' + e.message);
}
}

function stopDialogueRec() {
clearInterval(dialogueTimer);
if (dialogueRec && dialogueRec.state !== 'inactive') dialogueRec.stop();
if (window._sr) { try { window._sr.stop(); } catch(e) {} }
document.getElementById('recDialogueBtn').disabled = false;
document.getElementById('stopDialogueBtn').disabled = true;
document.getElementById('dialogueProgress').style.display = 'none';
document.getElementById('dialogueStatus').textContent = 'Status: Dialogue saved';
document.getElementById('dialogueStatus').className = 'status-text success';
}

// ── PROCESS ────────────────────────────────────────────────────────────────
async function processSession() {
const patient = document.getElementById('patientName').value.trim();
const template = document.getElementById('templateText').value.trim();

if (!patient) return alert('Enter patient name first.');
if (!transcriptText.trim()) return alert('Record a dialogue first.');

showOverlay('Polishing transcript…', 20);

try {
// Step 1: Polish
let res = await apiCall('polish_transcript', { text: transcriptText });
const polished = res.result;

setOverlay('Structuring medical record…', 60);

// Step 2: Generate history
res = await apiCall('generate_history', { text: polished, template });
resultText = res.result;

setOverlay('Saving files…', 85);

// Step 3: Save server-side
res = await apiCall('save_report', { surname: patient, content: resultText, transcript: polished });

setOverlay('Done!', 100);
setTimeout(hideOverlay, 500);

// Show result
document.getElementById('resultContent').innerText = resultText;
document.getElementById('transcriptContent').innerText = polished;
document.getElementById('resultCard').style.display = '';
document.getElementById('transcriptCard').style.display = '';

loadFiles();

} catch(e) {
hideOverlay();
alert('Error: ' + e.message);
}
}

async function apiCall(action, data) {
const res = await fetch('api.php', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ action, ...data }),
});
const json = await res.json();
if (json.error) throw new Error(json.error);
return json;
}

// ── DOWNLOAD ───────────────────────────────────────────────────────────────
function downloadResult() {
const blob = new Blob([resultText], { type: 'text/plain;charset=utf-8' });
const a = document.createElement('a');
a.href = URL.createObjectURL(blob);
a.download = 'medical_record_' + Date.now() + '.txt';
a.click();
}

// ── FILES SIDEBAR ──────────────────────────────────────────────────────────
async function loadFiles() {
try {
const res = await fetch('files.php');
const json = await res.json();
const list = document.getElementById('fileList');
if (!json.files || json.files.length === 0) {
list.innerHTML = '<p class="muted small">No files yet.</p>';
return;
}
list.innerHTML = json.files.map(f =>
`<div class="file-item">
<span class="file-icon">📄</span>
<span class="file-name" title="${escHtml(f.name)}">${escHtml(f.name)}</span>
<span class="file-date">${escHtml(f.date)}</span>
</div>`
).join('');
} catch(e) {
document.getElementById('fileList').innerHTML = '<p class="muted small">Could not load files.</p>';
}
}

function escHtml(s) {
return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── OVERLAY ────────────────────────────────────────────────────────────────
function showOverlay(msg, pct) {
document.getElementById('overlay').style.display = '';
document.getElementById('overlayMsg').textContent = msg;
document.getElementById('overlayProgress').value = pct;
}
function setOverlay(msg, pct) {
document.getElementById('overlayMsg').textContent = msg;
document.getElementById('overlayProgress').value = pct;
}
function hideOverlay() {
document.getElementById('overlay').style.display = 'none';
}

// ── INIT ───────────────────────────────────────────────────────────────────
loadFiles();
</script>

</body>
</html>
