<?php
// patient/medical_history.php — Medical History, Allergies & Reports
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requirePatient();
$pid = (int)$_SESSION['patient_id'];

// Completed appointments
$records = $conn->query(
    "SELECT a.*,d.name dname,d.specialisation,d.clinic_name
     FROM appointments a JOIN doctors d ON d.id=a.doctor_id
     WHERE a.patient_id=$pid AND (a.status IN ('completed', 'cancelled') OR a.date < CURDATE())
     ORDER BY a.date DESC"
);

// Allergies
$allergies = $conn->query("SELECT * FROM patient_allergies WHERE patient_id=$pid ORDER BY created_at DESC");

// Reports
$reports = $conn->query("SELECT * FROM patient_reports WHERE patient_id=$pid ORDER BY uploaded_at DESC");

$page_title='Medical History & Records'; include '../includes/header.php';
$unread=countUnread($conn,$pid,'patient');
?>
<style>
/* ── Allergy pills ── */
.allergy-pill{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:999px;font-size:12px;font-weight:600;cursor:default;transition:all .15s;position:relative}
.allergy-pill .pill-actions{display:none;gap:4px;margin-left:4px}
.allergy-pill:hover .pill-actions{display:inline-flex}
.pill-mild{background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0}
.pill-moderate{background:#FEF3C7;color:#92400E;border:1px solid #FDE68A}
.pill-severe{background:#FEE2E2;color:#DC2626;border:1px solid #FCA5A5}
.pill-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.pill-dot-mild{background:#059669}
.pill-dot-moderate{background:#D97706}
.pill-dot-severe{background:#DC2626}
.pill-btn{background:none;border:none;cursor:pointer;font-size:11px;padding:0 2px;opacity:.5;transition:opacity .15s;font-family:'Outfit',sans-serif}
.pill-btn:hover{opacity:1}

/* ── Report card ── */
.report-card{background:var(--bg);border:1.5px solid var(--bd);border-radius:12px;padding:16px;text-align:center;transition:all .2s;position:relative;min-height:110px;display:flex;flex-direction:column;align-items:center;justify-content:center}
.report-card:hover{border-color:var(--b);box-shadow:0 4px 14px rgba(26,111,212,.12)}
.report-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:8px;font-size:18px}
.report-icon-pdf{background:#FEE2E2;color:#DC2626}
.report-icon-img{background:#EBF3FF;color:#1A6FD4}
.report-name{font-size:11px;color:var(--tx);font-weight:600;word-break:break-all;line-height:1.3}
.report-size{font-size:10px;color:var(--tx3);margin-top:3px}
.report-actions{position:absolute;top:6px;right:6px;display:none;gap:4px}
.report-card:hover .report-actions{display:flex}
.report-act-btn{width:24px;height:24px;border-radius:6px;border:1px solid var(--bd2);background:var(--w);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:12px;transition:all .15s}
.report-act-btn:hover{border-color:var(--b);color:var(--b)}
.report-act-btn.del:hover{border-color:var(--r);color:var(--r)}

/* ── Upload zone ── */
.upload-zone{border:2px dashed var(--bd2);border-radius:12px;padding:24px;text-align:center;cursor:pointer;transition:all .2s}
.upload-zone:hover{border-color:var(--b);background:var(--b3)}
.upload-zone.dragging{border-color:var(--b);background:var(--b3)}

/* ── Modal ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center}
.modal-overlay.show{display:flex}
.modal-box{background:var(--w);border-radius:16px;padding:28px;width:90%;max-width:420px;box-shadow:0 25px 60px rgba(0,0,0,.2);animation:modalIn .2s ease}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}

/* ── Section headers ── */
.section-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.section-title{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--tx3)}
.add-btn{background:var(--b3);color:var(--b);border:1.5px solid var(--b4);border-radius:8px;padding:5px 14px;font-size:11px;font-weight:700;cursor:pointer;font-family:'Outfit',sans-serif;transition:all .15s;text-decoration:none}
.add-btn:hover{background:var(--b);color:#fff;border-color:var(--b)}
</style>

<div class="layout">
 <div class="sidebar">
  <div class="sb-profile"><div class="av av-blue" style="width:44px;height:44px;font-size:14px;margin:0 auto 8px"><?=getInitials($_SESSION['patient_name'])?></div><div style="font-size:13px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($_SESSION['patient_name'])?></div></div>
  <div class="sb-nav">
   <a class="nav-item" href="dashboard.php"> &nbsp;Dashboard</a>
   <a class="nav-item" href="find_doctor.php"> &nbsp;Find a Doctor</a>
   <a class="nav-item" href="my_appointments.php"> &nbsp;My Appointments</a>
   <a class="nav-item active" href="medical_history.php"> &nbsp;Medical Records</a>
   <a class="nav-item" href="my_profile.php"> &nbsp;My Profile</a>
   <a class="nav-item" href="notifications.php"> &nbsp;Notifications<?php if($unread>0):?><span class="nav-badge"><?=$unread?></span><?php endif?></a>
     <a class="nav-item" href="settings.php"> &nbsp;Settings</a>
  </div>
  <div class="sb-bottom"><a class="nav-item" style="color:var(--tx3)" href="logout.php">↩ &nbsp;Log out</a></div>
 </div>
 <div class="main-content">
  <div class="topbar"><div class="flex items-center gap-3"><div class="logo-i">M</div><span class="logo-n">MediBook</span></div><span style="font-size:13px;color:var(--b);font-weight:600">Medical Records</span><div></div></div>
  <div class="page-body">
   <h1 class="page-title">Medical Records</h1>
   <p class="page-sub">Your visit history, allergies, and uploaded reports</p>

   <!-- ═══ ALLERGIES & REPORTS GRID ═══ -->
   <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:24px">

    <!-- ── Allergies Card ── -->
    <div class="card" style="padding:20px">
     <div class="section-hdr">
      <span class="section-title">Medication Allergies</span>
      <button class="add-btn" onclick="openAllergyModal()">+ Add</button>
     </div>
     <div id="allergyList" style="display:flex;flex-wrap:wrap;gap:8px;min-height:40px">
      <?php if($allergies->num_rows === 0): ?>
       <div id="noAllergies" style="font-size:13px;color:var(--tx3);padding:12px 0">No allergies recorded</div>
      <?php else: ?>
       <?php while($al = $allergies->fetch_assoc()): ?>
        <div class="allergy-pill pill-<?=$al['severity']?>" id="allergy-<?=$al['id']?>" data-id="<?=$al['id']?>" data-name="<?=htmlspecialchars($al['name'])?>" data-severity="<?=$al['severity']?>" data-notes="<?=htmlspecialchars($al['notes']??'')?>">
         <span class="pill-dot pill-dot-<?=$al['severity']?>"></span>
         <?=htmlspecialchars($al['name'])?>
         <span class="pill-actions">
          <button class="pill-btn" onclick="editAllergy(<?=$al['id']?>)" title="Edit">&#9998;</button>
          <button class="pill-btn" onclick="deleteAllergy(<?=$al['id']?>)" title="Delete" style="color:var(--r)">&times;</button>
         </span>
        </div>
       <?php endwhile; ?>
      <?php endif; ?>
     </div>
     <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--bd)">
      <div style="display:flex;gap:12px;font-size:11px;color:var(--tx3)">
       <span><span class="pill-dot pill-dot-mild" style="display:inline-block;vertical-align:middle;margin-right:4px"></span>Mild</span>
       <span><span class="pill-dot pill-dot-moderate" style="display:inline-block;vertical-align:middle;margin-right:4px"></span>Moderate</span>
       <span><span class="pill-dot pill-dot-severe" style="display:inline-block;vertical-align:middle;margin-right:4px"></span>Severe</span>
      </div>
     </div>
    </div>

    <!-- ── Reports Card ── -->
    <div class="card" style="padding:20px">
     <div class="section-hdr">
      <span class="section-title">Uploaded Reports</span>
      <button class="add-btn" onclick="document.getElementById('fileInput').click()">Upload Report</button>
      <input type="file" id="fileInput" accept=".pdf,.jpg,.jpeg,.png" style="display:none" onchange="uploadReport(this)">
     </div>
     <div id="reportGrid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;min-height:80px">
      <?php if($reports->num_rows === 0): ?>
       <div id="noReports" style="grid-column:1/-1;font-size:13px;color:var(--tx3);padding:12px 0;text-align:center">No reports uploaded</div>
      <?php else: ?>
       <?php while($rp = $reports->fetch_assoc()):
        $isPdf = str_contains($rp['file_type'], 'pdf');
        $sizeStr = $rp['file_size'] >= 1048576 ? round($rp['file_size']/1048576,1).' MB' : round($rp['file_size']/1024).' KB';
       ?>
        <div class="report-card" id="report-<?=$rp['id']?>">
         <div class="report-actions">
          <a href="api_reports.php?action=download&id=<?=$rp['id']?>&inline=1" target="_blank" class="report-act-btn" title="View">
           <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </a>
          <button class="report-act-btn del" onclick="deleteReport(<?=$rp['id']?>)" title="Delete">&times;</button>
         </div>
         <div class="report-icon <?=$isPdf?'report-icon-pdf':'report-icon-img'?>">
          <?php if($isPdf): ?>
           <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 7V3.5L18.5 9H13zM9 13h6v2H9v-2zm0 4h6v2H9v-2z"/></svg>
          <?php else: ?>
           <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
          <?php endif; ?>
         </div>
         <div class="report-name"><?=htmlspecialchars(strlen($rp['file_name'])>20 ? substr($rp['file_name'],0,17).'...' : $rp['file_name'])?></div>
         <div class="report-size"><?=$sizeStr?></div>
        </div>
       <?php endwhile; ?>
      <?php endif; ?>
     </div>

     <!-- Upload drop zone -->
     <div class="upload-zone" id="dropZone" style="margin-top:14px"
          onclick="document.getElementById('fileInput').click()"
          ondragover="event.preventDefault();this.classList.add('dragging')"
          ondragleave="this.classList.remove('dragging')"
          ondrop="event.preventDefault();this.classList.remove('dragging');handleDrop(event)">
      <div style="font-size:13px;color:var(--tx3);font-weight:500">
       <span style="color:var(--b);font-weight:700">Click to upload</span> or drag and drop
      </div>
      <div style="font-size:11px;color:var(--tx3);margin-top:4px">PDF, JPG, PNG — Max 5 MB</div>
     </div>
     <div id="uploadStatus" style="display:none;margin-top:10px;font-size:12px;font-weight:600"></div>
    </div>
   </div>

   <!-- ═══ COMPLETED VISITS TABLE ═══ -->
   <div style="margin-bottom:8px">
    <h2 style="font-size:15px;font-weight:700;color:var(--tx);margin-bottom:4px">Visit History</h2>
    <p style="font-size:12px;color:var(--tx3);margin-bottom:14px">All your completed appointments</p>
   </div>
   <?php if($records->num_rows===0): ?>
    <div class="card" style="padding:48px;text-align:center"><div style="font-size:16px;font-weight:700;color:var(--tx)">No completed visits yet</div><div style="color:var(--tx3);margin-top:6px">Completed appointments will appear here</div></div>
   <?php else: ?>
   <div class="card" style="overflow:hidden">
    <table class="table">
     <thead><tr><th>Doctor</th><th>Specialisation</th><th>Date &amp; Time</th><th>Location</th><th>Status</th><th>Actions</th></tr></thead>
     <tbody>
     <?php while($r=$records->fetch_assoc()): ?>
       <tr>
        <td><div style="font-weight:700;color:var(--tx)"><?=htmlspecialchars($r['dname'])?></div></td>
        <td><?=htmlspecialchars($r['specialisation'])?></td>
        <td><?=fmtDate($r['date'])?> · <?=fmt12($r['time'])?></td>
        <td><?=htmlspecialchars($r['clinic_name']??'—')?></td>
        <td><?=statusBadge($r['status'])?></td>
        <td>
         <?php if(!empty($r['doctor_report']) || !empty($r['doctor_file'])): ?>
          <button class="btn-g btn-xs" onclick='viewDoctorReport(<?=json_encode(htmlspecialchars($r['doctor_report']??'', ENT_QUOTES, "UTF-8"))?>, <?=json_encode(!empty($r['doctor_file']) ? "api_reports.php?action=download_doctor_file&file=".htmlspecialchars($r['doctor_file']) : null)?>)'>View Report</button>
         <?php endif; ?>
        </td>
       </tr>
     <?php endwhile; ?>
     </tbody>
    </table>
   </div>
   <?php endif; ?>
  </div>
 </div>
</div>

<!-- ═══ ALLERGY MODAL ═══ -->
<div class="modal-overlay" id="allergyModal">
 <div class="modal-box">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
   <h3 id="allergyModalTitle" style="font-size:17px;font-weight:800;color:var(--tx)">Add Allergy</h3>
   <button onclick="closeAllergyModal()" style="background:none;border:none;font-size:20px;color:var(--tx3);cursor:pointer">&times;</button>
  </div>
  <form id="allergyForm" onsubmit="return submitAllergy(event)">
   <input type="hidden" id="allergyId" value="">
   <div class="form-group">
    <label>Allergy name *</label>
    <input type="text" id="allergyName" class="form-control" placeholder="e.g. Penicillin, Pollen, Latex" required>
   </div>
   <div class="form-group">
    <label>Severity</label>
    <select id="allergySeverity" class="form-control form-select">
     <option value="mild">Mild</option>
     <option value="moderate">Moderate</option>
     <option value="severe">Severe</option>
    </select>
   </div>
   <div class="form-group">
    <label>Notes (optional)</label>
    <textarea id="allergyNotes" class="form-control form-textarea" style="height:70px" placeholder="Any additional details..."></textarea>
   </div>
   <div style="display:flex;gap:10px">
    <button type="button" class="btn-g" style="flex:1;padding:12px" onclick="closeAllergyModal()">Cancel</button>
    <button type="submit" class="btn-p" style="flex:1;padding:12px" id="allergySubmitBtn">Add Allergy</button>
   </div>
  </form>
 </div>
</div>

<script>
// ═══════════════════════════════════════════════════
// ALLERGIES
// ═══════════════════════════════════════════════════
function openAllergyModal(id) {
  document.getElementById('allergyModal').classList.add('show');
  if (id) {
    // Edit mode
    var el = document.getElementById('allergy-'+id);
    document.getElementById('allergyModalTitle').textContent = 'Edit Allergy';
    document.getElementById('allergySubmitBtn').textContent = 'Save Changes';
    document.getElementById('allergyId').value = id;
    document.getElementById('allergyName').value = el.dataset.name;
    document.getElementById('allergySeverity').value = el.dataset.severity;
    document.getElementById('allergyNotes').value = el.dataset.notes;
  } else {
    // Add mode
    document.getElementById('allergyModalTitle').textContent = 'Add Allergy';
    document.getElementById('allergySubmitBtn').textContent = 'Add Allergy';
    document.getElementById('allergyId').value = '';
    document.getElementById('allergyName').value = '';
    document.getElementById('allergySeverity').value = 'mild';
    document.getElementById('allergyNotes').value = '';
  }
}

function closeAllergyModal() {
  document.getElementById('allergyModal').classList.remove('show');
}

function editAllergy(id) {
  openAllergyModal(id);
}

function deleteAllergy(id) {
  if (!confirm('Remove this allergy?')) return;
  fetch('api_allergies.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'action=delete&id='+id
  }).then(r => r.json()).then(d => {
    if (d.success) {
      var el = document.getElementById('allergy-'+id);
      if (el) el.remove();
      if (document.querySelectorAll('.allergy-pill').length === 0) {
        document.getElementById('allergyList').innerHTML = '<div id="noAllergies" style="font-size:13px;color:var(--tx3);padding:12px 0">No allergies recorded</div>';
      }
    }
  });
}

function submitAllergy(e) {
  e.preventDefault();
  var id       = document.getElementById('allergyId').value;
  var name     = document.getElementById('allergyName').value.trim();
  var severity = document.getElementById('allergySeverity').value;
  var notes    = document.getElementById('allergyNotes').value.trim();

  if (!name) return;

  var action = id ? 'edit' : 'add';
  var body   = 'action='+action+'&name='+encodeURIComponent(name)+'&severity='+severity+'&notes='+encodeURIComponent(notes);
  if (id) body += '&id='+id;

  fetch('api_allergies.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: body
  }).then(r => r.json()).then(d => {
    if (d.success) {
      closeAllergyModal();
      // Refresh page to show updated state
      location.reload();
    } else {
      alert(d.error || 'Something went wrong');
    }
  });
}

// ═══════════════════════════════════════════════════
// REPORTS
// ═══════════════════════════════════════════════════
function uploadReport(input) {
  if (!input.files || !input.files[0]) return;
  doUpload(input.files[0]);
  input.value = '';
}

function handleDrop(e) {
  var files = e.dataTransfer.files;
  if (files && files[0]) doUpload(files[0]);
}

function doUpload(file) {
  var status = document.getElementById('uploadStatus');
  status.style.display = 'block';
  status.style.color = 'var(--b)';
  status.textContent = 'Uploading ' + file.name + '...';

  // Validate client-side
  var allowed = ['application/pdf','image/jpeg','image/png','image/jpg'];
  if (allowed.indexOf(file.type) === -1) {
    status.style.color = 'var(--r)';
    status.textContent = 'Only PDF, JPG, PNG files are allowed.';
    return;
  }
  if (file.size > 5 * 1024 * 1024) {
    status.style.color = 'var(--r)';
    status.textContent = 'File is too large. Max 5 MB.';
    return;
  }

  var fd = new FormData();
  fd.append('action', 'upload');
  fd.append('report', file);

  fetch('api_reports.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        status.style.color = 'var(--t)';
        status.textContent = 'Uploaded successfully!';
        setTimeout(function() { location.reload(); }, 600);
      } else {
        status.style.color = 'var(--r)';
        status.textContent = d.error || 'Upload failed.';
      }
    })
    .catch(function() {
      status.style.color = 'var(--r)';
      status.textContent = 'Network error. Try again.';
    });
}

function deleteReport(id) {
  if (!confirm('Delete this report permanently?')) return;
  fetch('api_reports.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'action=delete&id='+id
  }).then(r => r.json()).then(d => {
    if (d.success) {
      var el = document.getElementById('report-'+id);
      if (el) el.remove();
      var grid = document.getElementById('reportGrid');
      if (grid.querySelectorAll('.report-card').length === 0) {
        grid.innerHTML = '<div id="noReports" style="grid-column:1/-1;font-size:13px;color:var(--tx3);padding:12px 0;text-align:center">No reports uploaded</div>';
      }
    }
  });
}

// Close modal on overlay click
document.getElementById('allergyModal').addEventListener('click', function(e) {
  if (e.target === this) closeAllergyModal();
});
</script>

<!-- View Doctor Report Modal -->
<div id="drReportModal" class="modal-overlay">
 <div class="modal-box" style="max-width:500px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
   <h3 style="font-size:17px;font-weight:800;color:var(--tx)">Doctor's Report</h3>
   <button onclick="document.getElementById('drReportModal').classList.remove('show')" style="background:none;border:none;font-size:20px;color:var(--tx3);cursor:pointer">&times;</button>
  </div>
  <div id="dr-content" style="font-size:14px;line-height:1.6;color:var(--tx2);background:var(--bg);padding:16px;border-radius:10px;border:1px solid var(--bd);max-height:300px;overflow-y:auto;white-space:pre-wrap;margin-bottom:16px"></div>
  <div id="dr-file-btn" style="display:none;margin-bottom:20px">
   <a href="#" id="dr-file-link" target="_blank" class="btn-g" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:12px;border:1.5px solid var(--bd2);background:var(--w)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Download Attached File</a>
  </div>
  <div style="display:flex;justify-content:flex-end">
   <button type="button" class="btn-g" onclick="document.getElementById('drReportModal').classList.remove('show')" style="padding:10px 20px">Close</button>
  </div>
 </div>
</div>

<script>
function viewDoctorReport(text, fileUrl) {
  var content = document.getElementById('dr-content');
  if(text) {
    content.innerText = text;
    content.style.display = 'block';
  } else {
    content.style.display = 'none';
  }

  var fileBtn = document.getElementById('dr-file-btn');
  var fileLink = document.getElementById('dr-file-link');
  if(fileUrl) {
    fileLink.href = fileUrl;
    fileBtn.style.display = 'block';
  } else {
    fileBtn.style.display = 'none';
  }
  
  document.getElementById('drReportModal').classList.add('show');
}
document.getElementById('drReportModal').addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('show');
});
</script>

</body></html>
