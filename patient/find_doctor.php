<?php
// patient/find_doctor.php — DABS-03
// Enhanced with smart search + relevant recommendations
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requirePatient();

$pid    = (int)$_SESSION['patient_id'];
$search = clean($conn, $_GET['search'] ?? '');
$spec   = clean($conn, $_GET['specialisation'] ?? '');
$sort   = clean($conn, $_GET['sort'] ?? 'relevance');

// ── Build search WHERE clause ─────────────────────────────
$where = "d.status='approved'";

if ($search !== '') {
    // Search across name, specialisation, qualification, clinic, bio
    $where .= " AND (
        d.name           LIKE '%$search%' OR
        d.specialisation LIKE '%$search%' OR
        d.qualification  LIKE '%$search%' OR
        d.clinic_name    LIKE '%$search%' OR
        d.bio            LIKE '%$search%'
    )";
}

if ($spec !== '') {
    $where .= " AND d.specialisation='$spec'";
}

// ── Sort logic ────────────────────────────────────────────
$order = match($sort) {
    'fee_low'    => 'd.fee ASC',
    'fee_high'   => 'd.fee DESC',
    'experience' => 'd.experience DESC',
    'popular'    => 'total_appts DESC',
    default      => 'total_appts DESC, d.experience DESC', // relevance
};

// ── Main doctor query ─────────────────────────────────────
// LEFT JOIN reviews so we can show real avg_rating per doctor.
// COUNT(DISTINCT ...) on both joins is critical: joining two LEFT-JOINed
// tables creates a Cartesian product per doctor, so plain COUNT()
// would multiply the appointment count by the review count.
$doctors = $conn->query(
    "SELECT d.*,
            COUNT(DISTINCT a.id)               AS total_appts,
            COALESCE(ROUND(AVG(r.rating),1),0) AS avg_rating,
            COUNT(DISTINCT r.id)               AS review_count
     FROM doctors d
     LEFT JOIN appointments a ON a.doctor_id=d.id AND a.status='completed'
     LEFT JOIN reviews      r ON r.doctor_id=d.id
     WHERE $where
     GROUP BY d.id
     ORDER BY $order"
);

// ── Specialisations dropdown ──────────────────────────────
$specs = $conn->query(
    "SELECT DISTINCT specialisation FROM doctors
     WHERE status='approved' ORDER BY specialisation"
);

// ── Smart recommendations ─────────────────────────────────
// Based on patient's past appointment history
// If patient visited a cardiologist before → recommend more cardiologists
$recommended = [];

if ($search === '' && $spec === '') {
    // Get patient's most visited specialisations
    $hist = $conn->query(
        "SELECT d.specialisation, COUNT(*) AS visits
         FROM appointments a
         JOIN doctors d ON d.id = a.doctor_id
         WHERE a.patient_id = $pid
         GROUP BY d.specialisation
         ORDER BY visits DESC
         LIMIT 3"
    );

    if ($hist->num_rows > 0) {
        // Build recommended doctors based on past specialisations
        $specs_visited = [];
        while ($h = $hist->fetch_assoc()) {
            $specs_visited[] = "'" . $conn->real_escape_string($h['specialisation']) . "'";
        }
        $specs_in = implode(',', $specs_visited);

        $rec = $conn->query(
            "SELECT d.*,
                    COUNT(DISTINCT a.id)               AS total_appts,
                    COALESCE(ROUND(AVG(r.rating),1),0) AS avg_rating,
                    COUNT(DISTINCT r.id)               AS review_count
             FROM doctors d
             LEFT JOIN appointments a ON a.doctor_id=d.id AND a.status='completed'
             LEFT JOIN reviews      r ON r.doctor_id=d.id
             WHERE d.status='approved'
             AND d.specialisation IN ($specs_in)
             AND d.id NOT IN (
                 SELECT DISTINCT doctor_id FROM appointments
                 WHERE patient_id=$pid
             )
             GROUP BY d.id
             ORDER BY total_appts DESC, d.experience DESC
             LIMIT 3"
        );
        while ($r = $rec->fetch_assoc()) {
            $recommended[] = $r;
        }
    }

    // If no history → recommend most popular doctors
    if (empty($recommended)) {
        $rec = $conn->query(
            "SELECT d.*,
                    COUNT(DISTINCT a.id)               AS total_appts,
                    COALESCE(ROUND(AVG(r.rating),1),0) AS avg_rating,
                    COUNT(DISTINCT r.id)               AS review_count
             FROM doctors d
             LEFT JOIN appointments a ON a.doctor_id=d.id AND a.status='completed'
             LEFT JOIN reviews      r ON r.doctor_id=d.id
             WHERE d.status='approved'
             GROUP BY d.id
             ORDER BY total_appts DESC, d.experience DESC
             LIMIT 3"
        );
        while ($r = $rec->fetch_assoc()) {
            $recommended[] = $r;
        }
    }
}

// ── Popular specialisations for quick filter pills ────────
$popular_specs = $conn->query(
    "SELECT d.specialisation, COUNT(a.id) AS appt_count
     FROM doctors d
     LEFT JOIN appointments a ON a.doctor_id=d.id
     WHERE d.status='approved'
     GROUP BY d.specialisation
     ORDER BY appt_count DESC
     LIMIT 8"
);

$page_title = 'Find a Doctor';
include '../includes/header.php';
$unread = countUnread($conn, $pid, 'patient');

// Avatar colours
$av_colors = ['#1A6FD4','#0D9E7A','#D97706','#7C3AED','#DC2626','#0891B2','#059669','#B45309'];
$color_index = 0;
?>

<style>
/* Search bar */
.search-bar {
  display:flex; gap:12px; align-items:center;
  background:var(--w); border:1.5px solid var(--bd2);
  border-radius:14px; padding:10px 16px;
  box-shadow:0 2px 8px rgba(0,0,0,.06);
  transition:border-color .2s;
}
.search-bar:focus-within { border-color:var(--b); box-shadow:0 0 0 3px rgba(26,111,212,.1); }
.search-bar input {
  border:none; outline:none; background:none;
  font-family:'Outfit',sans-serif; font-size:16px;
  color:var(--tx); width:360px;
}
.search-bar input::placeholder { color:var(--tx3); }

/* Spec pills */
.spec-pill {
  border:1.5px solid var(--bd2); border-radius:999px;
  padding:6px 16px; font-size:12px; color:var(--tx2);
  background:var(--w); cursor:pointer; text-decoration:none;
  transition:all .15s; white-space:nowrap; font-weight:500;
  display:inline-block;
}
.spec-pill:hover { border-color:var(--b); color:var(--b); background:var(--b3); }
.spec-pill.active { background:var(--b); border-color:var(--b); color:#fff; font-weight:700; }

/* Doctor card */
.doc-card {
  background:var(--w); border:1px solid var(--bd);
  border-radius:14px; padding:20px;
  transition:all .2s; cursor:pointer;
  box-shadow:0 1px 4px rgba(0,0,0,.06);
}
.doc-card:hover {
  box-shadow:0 8px 28px rgba(0,0,0,.12);
  transform:translateY(-2px);
  border-color:var(--b4);
}

/* Recommended badge */
.rec-badge {
  background:linear-gradient(135deg,#FEF3C7,#FDE68A);
  color:#92400E; border:1px solid #F59E0B;
  border-radius:999px; padding:3px 11px;
  font-size:10px; font-weight:700;
  display:inline-flex; align-items:center; gap:4px;
}

/* Sort dropdown */
.sort-select {
  border:1.5px solid var(--bd2); border-radius:9px;
  padding:6px 12px; font-size:12px; font-family:'Outfit',sans-serif;
  color:var(--tx2); background:var(--w); outline:none; cursor:pointer;
}

/* Highlight matched text */
.highlight { background:#FEF9C3; border-radius:3px; padding:0 2px; font-weight:700; }

/* No results */
.no-results { text-align:center; padding:60px 20px; }
</style>

<div class="layout">
 <div class="sidebar">
  <div class="sb-profile">
   <div class="av av-blue" style="width:44px;height:44px;font-size:14px;margin:0 auto 8px">
    <?=getInitials($_SESSION['patient_name'])?>
   </div>
   <div style="font-size:13px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($_SESSION['patient_name'])?></div>
  </div>
  <div class="sb-nav">
   <a class="nav-item" href="dashboard.php"> &nbsp;Dashboard</a>
   <a class="nav-item active" href="find_doctor.php"> &nbsp;Find a Doctor</a>
   <a class="nav-item" href="my_appointments.php"> &nbsp;My Appointments</a>
   <a class="nav-item" href="medical_history.php"> &nbsp;Medical Records</a>
   <a class="nav-item" href="my_profile.php"> &nbsp;My Profile</a>
   <a class="nav-item" href="settings.php"> &nbsp;Settings</a>
   <a class="nav-item" href="notifications.php"> &nbsp;Notifications
    <?php if($unread>0):?><span class="nav-badge"><?=$unread?></span><?php endif?>
   </a>
  </div>
  <div class="sb-bottom">
   <a class="nav-item" style="color:var(--tx3)" href="logout.php">↩ &nbsp;Log out</a>
  </div>
 </div>

 <div class="main-content">
  <div class="topbar">
   <div class="flex items-center gap-3">
    <div class="logo-i">M</div>
    <span class="logo-n">MediBook</span>
   </div>

   <!-- Search bar -->
   <form method="GET" action="" id="searchForm">
    <div class="search-bar">
     <span style="font-size:16px;color:var(--tx3)"></span>
     <input type="text"
            name="search"
            id="searchInput"
            placeholder="Search by name, speciality, clinic..."
            value="<?=htmlspecialchars($search)?>"
            autocomplete="off">
     <?php if($search||$spec): ?>
      <a href="find_doctor.php" style="font-size:12px;color:var(--tx3);text-decoration:none;white-space:nowrap">✕ Clear</a>
     <?php endif; ?>
    </div>
    <!-- Hidden fields carried over -->
    <?php if($spec): ?><input type="hidden" name="specialisation" value="<?=htmlspecialchars($spec)?>"> <?php endif; ?>
    <?php if($sort !== 'relevance'): ?><input type="hidden" name="sort" value="<?=htmlspecialchars($sort)?>"> <?php endif; ?>
   </form>

   <a href="find_doctor.php" class="btn-p" style="padding:10px 24px; font-size:15px; border-radius:10px">+ Book now</a>
  </div>

  <div class="page-body">

   <!-- Page title -->
   <div class="flex justify-between items-center" style="margin-bottom:16px">
    <div>
     <h1 class="page-title">
      <?php if($search): ?>
       Results for "<?=htmlspecialchars($search)?>"
      <?php elseif($spec): ?>
       <?=htmlspecialchars($spec)?> Doctors
      <?php else: ?>
       Find a Doctor
      <?php endif; ?>
     </h1>
     <p class="page-sub">
      <?=$doctors->num_rows?> verified doctor<?=$doctors->num_rows!==1?'s':''?> found
      <?php if($search||$spec): ?>
       · <a href="find_doctor.php" style="color:var(--b);text-decoration:none;font-weight:600">See all doctors</a>
      <?php endif; ?>
     </p>
    </div>

    <!-- Sort -->
    <form method="GET" id="sortForm" style="display:flex;align-items:center;gap:8px">
     <?php if($search): ?><input type="hidden" name="search" value="<?=htmlspecialchars($search)?>"><?php endif; ?>
     <?php if($spec):   ?><input type="hidden" name="specialisation" value="<?=htmlspecialchars($spec)?>">  <?php endif; ?>
     <label style="font-size:12px;color:var(--tx3);font-weight:600">Sort by</label>
     <select name="sort" class="sort-select" onchange="document.getElementById('sortForm').submit()">
      <option value="relevance"  <?=$sort==='relevance' ?'selected':''?>>Most popular</option>
      <option value="experience" <?=$sort==='experience'?'selected':''?>>Most experienced</option>
      <option value="fee_low"    <?=$sort==='fee_low'   ?'selected':''?>>Fee: Low to high</option>
      <option value="fee_high"   <?=$sort==='fee_high'  ?'selected':''?>>Fee: High to low</option>
     </select>
    </form>
   </div>

   <!-- Speciality quick filter pills -->
   <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;align-items:center">
    <span style="font-size:12px;color:var(--tx3);font-weight:600;white-space:nowrap">Quick filter:</span>
    <a href="find_doctor.php<?=$search?"?search=$search":''?>"
       class="spec-pill <?=$spec===''?'active':''?>">All</a>
    <?php
    $popular_specs->data_seek(0);
    while($ps = $popular_specs->fetch_assoc()):
    ?>
     <a href="find_doctor.php?specialisation=<?=urlencode($ps['specialisation'])?><?=$search?"&search=$search":''?>"
        class="spec-pill <?=$spec===$ps['specialisation']?'active':''?>">
      <?=htmlspecialchars($ps['specialisation'])?>
     </a>
    <?php endwhile; ?>
   </div>

   <!-- ── Recommendations section (shown only when no search active) ── -->
   <?php if(!empty($recommended) && $search === '' && $spec === ''): ?>
   <div style="margin-bottom:28px">
    <div class="flex items-center gap-2" style="margin-bottom:14px">
     <span style="font-size:16px"></span>
     <span style="font-size:15px;font-weight:800;color:var(--tx)">Recommended for you</span>
     <span style="font-size:12px;color:var(--tx3);margin-left:4px">Based on your visit history</span>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px">
     <?php foreach($recommended as $d): ?>
      <div class="doc-card" style="border:1.5px solid #FDE68A;background:linear-gradient(135deg,#FFFBEB,#fff)">
       <div class="flex justify-between items-start" style="margin-bottom:12px">
        <span class="rec-badge">Recommended</span>
        <span style="font-size:11px;color:var(--tx3)"><?=$d['total_appts']?> completed visits</span>
       </div>
       <div class="flex gap-3 items-center" style="margin-bottom:12px">
        <?php $col = $av_colors[$color_index % count($av_colors)]; $color_index++;
              $img = $d['profile_image'] ?? '';
              $has_img = $img && is_file(__DIR__ . '/../' . $img);
        ?>
        <?php if ($has_img): ?>
         <div class="av" style="width:50px;height:50px;overflow:hidden;padding:0;box-shadow:0 3px 10px rgba(0,0,0,.15)">
          <img src="/medibook/<?= htmlspecialchars($img) ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block">
         </div>
        <?php else: ?>
         <div class="av" style="width:50px;height:50px;font-size:16px;background:<?=$col?>;color:#fff;box-shadow:0 3px 10px rgba(0,0,0,.15)">
          <?=getInitials($d['name'])?>
         </div>
        <?php endif; ?>
        <div style="flex:1">
         <div style="font-size:14px;font-weight:800;color:var(--tx);margin-bottom:3px"><?=htmlspecialchars($d['name'])?></div>
         <div style="font-size:12px;color:var(--tx3)"><?=htmlspecialchars($d['specialisation'])?></div>
         <div style="margin-top:2px">
          <?php if ((int)$d['review_count'] > 0): ?>
           <span style="color:#f59e0b;font-weight:700;font-size:12px">★ <?= $d['avg_rating'] ?></span>
           <span style="font-size:11px;color:var(--tx3)">(<?= (int)$d['review_count'] ?>) · <?=htmlspecialchars($d['clinic_name']?:'Hospital')?></span>
          <?php else: ?>
           <span style="font-size:11px;color:var(--tx3)">No reviews yet · <?=htmlspecialchars($d['clinic_name']?:'Hospital')?></span>
          <?php endif; ?>
         </div>
        </div>
       </div>
       <div class="flex justify-between items-center">
        <div>
         <div style="font-size:11px;color:var(--tx3)">Consultation fee</div>
         <div style="font-size:16px;font-weight:800;color:var(--tx)">NPR <?=number_format($d['fee'])?></div>
        </div>
        <a href="book_appointment.php?doctor_id=<?=$d['id']?>" class="btn-p" style="font-size:12px;padding:7px 18px">Book now</a>
       </div>
      </div>
     <?php endforeach; ?>
    </div>
   </div>

   <div class="divl" style="margin-bottom:24px"></div>
   <div style="font-size:15px;font-weight:800;color:var(--tx);margin-bottom:14px">All doctors</div>
   <?php endif; ?>

   <!-- ── All doctors / search results ── -->
   <?php if($doctors->num_rows === 0): ?>
    <div class="card no-results">
     <div style="font-size:48px;margin-bottom:16px"></div>
     <div style="font-size:18px;font-weight:700;color:var(--tx);margin-bottom:8px">
      No doctors found for "<?=htmlspecialchars($search)?>"
     </div>
     <div style="font-size:14px;color:var(--tx3);margin-bottom:24px">
      Try a different name, speciality, or browse all doctors
     </div>
     <!-- Suggestions -->
     <div style="margin-bottom:20px">
      <div style="font-size:13px;font-weight:600;color:var(--tx2);margin-bottom:10px">Try searching for:</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center">
       <?php
       $suggestions = $conn->query("SELECT DISTINCT specialisation FROM doctors WHERE status='approved' ORDER BY RAND() LIMIT 5");
       while($sg = $suggestions->fetch_assoc()):
       ?>
        <a href="find_doctor.php?search=<?=urlencode($sg['specialisation'])?>"
           class="spec-pill">
         <?=htmlspecialchars($sg['specialisation'])?>
        </a>
       <?php endwhile; ?>
      </div>
     </div>
     <a href="find_doctor.php" class="btn-p" style="padding:10px 28px">Browse all doctors</a>
    </div>

   <?php else: ?>

   <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px">
    <?php while($d = $doctors->fetch_assoc()):
     $col = $av_colors[$color_index % count($av_colors)];
     $color_index++;
    ?>
     <div class="doc-card">

      <!-- Availability indicator -->
      <div class="flex justify-between items-center" style="margin-bottom:12px">
       <div style="display:flex;align-items:center;gap:5px">
        <div style="width:7px;height:7px;border-radius:50%;background:var(--t)"></div>
        <span style="font-size:11px;color:var(--t);font-weight:600">Available</span>
       </div>
       <span style="font-size:11px;color:var(--tx3)"><?=$d['total_appts']?> patients seen</span>
      </div>

      <div class="flex gap-3 items-center" style="margin-bottom:14px">
       <?php $img = $d['profile_image'] ?? '';
             $has_img = $img && is_file(__DIR__ . '/../' . $img); ?>
       <?php if ($has_img): ?>
        <div class="av" style="width:56px;height:56px;overflow:hidden;padding:0;box-shadow:0 3px 12px rgba(0,0,0,.15)">
         <img src="/medibook/<?= htmlspecialchars($img) ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block">
        </div>
       <?php else: ?>
        <div class="av" style="width:56px;height:56px;font-size:18px;background:<?=$col?>;color:#fff;box-shadow:0 3px 12px rgba(0,0,0,.15)">
         <?=getInitials($d['name'])?>
        </div>
       <?php endif; ?>
       <div style="flex:1">
        <div style="font-size:15px;font-weight:800;color:var(--tx);margin-bottom:3px">
         <?php
         // Highlight matched search term in name
         if($search) {
             echo preg_replace(
                 '/(' . preg_quote(htmlspecialchars($search), '/') . ')/i',
                 '<span class="highlight">$1</span>',
                 htmlspecialchars($d['name'])
             );
         } else {
             echo htmlspecialchars($d['name']);
         }
         ?>
        </div>
        <div style="font-size:12px;color:var(--tx3)">
         <?php
         // Highlight matched search term in specialisation
         if($search) {
             echo preg_replace(
                 '/(' . preg_quote(htmlspecialchars($search), '/') . ')/i',
                 '<span class="highlight">$1</span>',
                 htmlspecialchars($d['specialisation'])
             );
         } else {
             echo htmlspecialchars($d['specialisation']);
         }
         ?>
        </div>
        <div style="margin-top:3px">
         <?php if ((int)$d['review_count'] > 0): ?>
          <span style="color:#f59e0b;font-weight:700;font-size:12px">★ <?= $d['avg_rating'] ?></span>
          <span style="font-size:11px;color:var(--tx3)">(<?= (int)$d['review_count'] ?> review<?= $d['review_count']==1?'':'s' ?>) · <?=htmlspecialchars($d['clinic_name']?:'Hospital')?></span>
         <?php else: ?>
          <span style="font-size:11px;color:var(--tx3)">No reviews yet · <?=htmlspecialchars($d['clinic_name']?:'Hospital')?></span>
         <?php endif; ?>
        </div>
       </div>
      </div>

      <!-- Tags -->
      <div class="flex gap-2" style="margin-bottom:14px;flex-wrap:wrap">
       <?php if($d['experience']): ?>
        <span style="border:1px solid var(--bd);border-radius:999px;padding:3px 11px;font-size:11px;color:var(--tx2)"><?=$d['experience']?> yrs exp</span>
       <?php endif; ?>
       <?php if($d['qualification']): ?>
        <span style="border:1px solid var(--bd);border-radius:999px;padding:3px 11px;font-size:11px;color:var(--tx2)"><?=htmlspecialchars($d['qualification'])?></span>
       <?php endif; ?>
       <?php if($d['total_appts'] > 10): ?>
        <span style="border:1px solid var(--t3);background:var(--t2);border-radius:999px;padding:3px 11px;font-size:11px;color:#065F46;font-weight:600">Popular</span>
       <?php endif; ?>
      </div>

      <!-- Fee + Book button -->
      <div class="divl" style="margin-bottom:12px"></div>
      <div class="flex justify-between items-center">
       <div>
        <div style="font-size:11px;color:var(--tx3)">Consultation fee</div>
        <div style="font-size:18px;font-weight:800;color:var(--tx)">NPR <?=number_format($d['fee'])?></div>
       </div>
       <div class="flex gap-2">
        <a href="find_doctor.php?specialisation=<?=urlencode($d['specialisation'])?>"
           class="btn-g" style="font-size:12px;padding:7px 14px">
         More like this
        </a>
        <a href="book_appointment.php?doctor_id=<?=$d['id']?>"
           class="btn-p" style="font-size:13px;padding:8px 20px">
         Book now
        </a>
       </div>
      </div>
     </div>
    <?php endwhile; ?>
   </div>
   <?php endif; ?>

  </div>
 </div>
</div>

<script>
// ── Live search as user types (debounced) ─────────────────
const searchInput = document.getElementById('searchInput');
const searchForm  = document.getElementById('searchForm');
let debounceTimer;

searchInput.addEventListener('input', function() {
  clearTimeout(debounceTimer);
  // Wait 400ms after user stops typing then submit
  debounceTimer = setTimeout(() => {
    searchForm.submit();
  }, 400);
});

// ── Submit on Enter ────────────────────────────────────────
searchInput.addEventListener('keydown', function(e) {
  if (e.key === 'Enter') {
    clearTimeout(debounceTimer);
    searchForm.submit();
  }
});

// ── Focus search bar on page load if coming from a search ─
<?php if($search): ?>
searchInput.focus();
searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
<?php endif; ?>
</script>

</body></html>