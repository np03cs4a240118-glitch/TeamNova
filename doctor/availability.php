<?php
// doctor/availability.php — DABS-13 (T13-1 to T13-4)
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requireDoctor();
$did = (int)$_SESSION['doctor_id'];
$dr  = $conn->query("SELECT * FROM doctors WHERE id=$did LIMIT 1")->fetch_assoc();

// Parse saved availability JSON
$avail = json_decode($dr['availability'] ?? '{}', true);
$days  = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
$msg   = '';

// T13-2: Save availability on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_avail = [];
    foreach ($days as $day) {
        $new_avail[$day] = [
            'enabled' => isset($_POST[$day . '_enabled']),
            'start'   => $_POST[$day . '_start'] ?? '09:00',
            'end'     => $_POST[$day . '_end']   ?? '17:00',
            'max'     => (int)($_POST[$day . '_max'] ?? 8),
        ];
    }
    $new_avail['slot_duration'] = (int)($_POST['slot_duration'] ?? 30);
    $new_avail['notice_hours']  = (int)($_POST['notice_hours']  ?? 2);

    $json = $conn->real_escape_string(json_encode($new_avail));
    $conn->query("UPDATE doctors SET availability='$json' WHERE id=$did");
    $avail = $new_avail;
    // T13-3: Success message
    $msg = 'Availability saved successfully!';
}

$sel_duration = (int)($avail['slot_duration'] ?? 30);
$notice_hours = (int)($avail['notice_hours']  ?? 2);

$page_title = 'Availability Settings';
include '../includes/header.php';
$unread = countUnread($conn, $did, 'doctor');
?>
<style>
/* Toggle switch */
.tog-wrap {
  width: 42px; height: 24px; border-radius: 99px;
  background: var(--bg2); border: 2px solid var(--bd2);
  position: relative; cursor: pointer;
  transition: background .25s, border-color .25s;
  flex-shrink: 0;
}
.tog-wrap.on {
  background: var(--t);
  border-color: var(--t);
}
.tog-knob {
  position: absolute; top: 2px; left: 2px;
  width: 16px; height: 16px; border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 4px rgba(0,0,0,.25);
  transition: transform .25s;
}
.tog-wrap.on .tog-knob { transform: translateX(18px); }

.day-row {
  display: grid;
  grid-template-columns: 48px 110px 120px 24px 120px 16px 90px 40px;
  gap: 10px; align-items: center;
  padding: 12px 0; border-bottom: 1px solid var(--bg2);
  transition: opacity .2s;
}
.day-row.off { opacity: .4; }

.dur-pill {
  border: 1.5px solid var(--bd2); border-radius: 999px;
  padding: 8px 22px; font-size: 13px; color: var(--tx2);
  background: var(--w); cursor: pointer; font-family: 'Outfit', sans-serif;
  transition: all .15s; font-weight: 400;
}
.dur-pill.active {
  background: var(--b); border-color: var(--b);
  color: #fff; font-weight: 700;
}
</style>

<div class="layout">
 <div class="sidebar">
  <div class="sb-profile" style="text-align:center">
   <div style="margin:0 auto 8px;display:flex;justify-content:center"><?= doctorAvatar($dr, 44, 'av-teal') ?></div>
   <div style="font-size:13px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($dr['name'])?></div>
   <div style="font-size:11px;color:var(--tx3)"><?=htmlspecialchars($dr['specialisation'])?></div>
  </div>
  <div class="sb-nav">
   <a class="nav-item" href="dashboard.php"> &nbsp;Dashboard</a>
   <a class="nav-item" href="appointments.php"> &nbsp;Appointments</a>
   <a class="nav-item" href="schedule.php"> &nbsp;My Schedule</a>
   <a class="nav-item active" href="availability.php"> &nbsp;Availability</a>
   <a class="nav-item" href="my_profile.php"> &nbsp;My Profile</a>
   <a class="nav-item" href="notifications.php"> &nbsp;Notifications
    <?php if($unread > 0): ?><span class="nav-badge"><?=$unread?></span><?php endif; ?>
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
   <span style="font-size:13px;color:var(--tx3)">Manage your availability</span>
   <div class="flex gap-2">
    <a href="availability.php" class="btn-g btn-sm">Discard</a>
   </div>
  </div>

  <div class="page-body">
   <h1 class="page-title">Availability Settings</h1>
   <p class="page-sub">Control when patients can book your appointments</p>

   <?php if($msg): ?>
    <div class="alert alert-success"> <?=htmlspecialchars($msg)?></div>
   <?php endif; ?>

   <form method="POST" action="" id="availForm">
    <div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start">
     <div style="display:flex;flex-direction:column;gap:16px">

      <!-- Weekly schedule card -->
      <div class="card" style="padding:22px">
       <div style="font-size:15px;font-weight:700;color:var(--tx);margin-bottom:18px">Weekly working schedule</div>

       <!-- Header row -->
       <div style="display:grid;grid-template-columns:48px 110px 120px 24px 120px 16px 90px 40px;gap:10px;padding:0 0 8px;margin-bottom:4px;border-bottom:1px solid var(--bg2)">
        <div></div>
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--tx3)">Day</div>
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--tx3)">Start</div>
        <div></div>
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--tx3)">End</div>
        <div></div>
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--tx3);text-align:center">Max pts</div>
        <div></div>
       </div>

       <?php foreach($days as $day):
        $d       = $avail[$day] ?? ['enabled'=>false,'start'=>'09:00','end'=>'17:00','max'=>8];
        $enabled = !empty($d['enabled']);
        $start   = htmlspecialchars($d['start'] ?? '09:00');
        $end     = htmlspecialchars($d['end']   ?? '17:00');
        $max     = (int)($d['max'] ?? 8);
        $rowCls  = $enabled ? 'day-row' : 'day-row off';
       ?>
       <div class="<?=$rowCls?>" id="row_<?=$day?>">

        <!-- Toggle -->
        <div class="tog-wrap <?=$enabled?'on':''?>"
             id="tog_<?=$day?>"
             onclick="toggleDay('<?=$day?>')">
         <div class="tog-knob"></div>
        </div>

        <!-- Hidden checkbox (submitted with form) -->
        <input type="checkbox"
               name="<?=$day?>_enabled"
               id="chk_<?=$day?>"
               <?=$enabled?'checked':''?>
               style="display:none">

        <!-- Day name -->
        <div style="font-size:13px;font-weight:700;color:var(--tx)"><?=ucfirst($day)?></div>

        <!-- Start time -->
        <input type="time"
               name="<?=$day?>_start"
               id="start_<?=$day?>"
               value="<?=$start?>"
               class="form-control"
               style="height:38px;font-size:13px"
               <?=$enabled?'':'disabled'?>>

        <span style="text-align:center;color:var(--tx3);font-size:13px">—</span>

        <!-- End time -->
        <input type="time"
               name="<?=$day?>_end"
               id="end_<?=$day?>"
               value="<?=$end?>"
               class="form-control"
               style="height:38px;font-size:13px"
               <?=$enabled?'':'disabled'?>>

        <span></span>

        <!-- Max patients -->
        <input type="number"
               name="<?=$day?>_max"
               id="max_<?=$day?>"
               value="<?=$max?>"
               min="1" max="30"
               class="form-control"
               style="height:38px;font-size:13px;text-align:center"
               <?=$enabled?'':'disabled'?>>

        <span style="font-size:12px;color:var(--tx3)">pts</span>
       </div>
       <?php endforeach; ?>
      </div>

      <!-- Slot duration -->
      <div class="card" style="padding:22px">
       <div style="font-size:15px;font-weight:700;color:var(--tx);margin-bottom:14px">Appointment slot duration</div>
       <input type="hidden" name="slot_duration" id="slot_duration" value="<?=$sel_duration?>">
       <div class="flex gap-3" style="flex-wrap:wrap">
        <?php foreach([15,30,45,60] as $mins): ?>
         <div class="dur-pill <?=$sel_duration===$mins?'active':''?>"
              onclick="selectDuration(<?=$mins?>)">
          <?=$mins?> min<?=$sel_duration===$mins?' ✓':''?>
         </div>
        <?php endforeach; ?>
       </div>
      </div>

      <!-- Min notice -->
      <div class="card" style="padding:22px">
       <div style="font-size:15px;font-weight:700;color:var(--tx);margin-bottom:14px">Booking settings</div>
       <div class="flex items-center gap-3">
        <span style="font-size:13px;color:var(--tx2);flex:1">Minimum notice period (hours before appointment)</span>
        <input type="number" name="notice_hours" id="notice_hours"
               value="<?=$notice_hours?>" min="0" max="72"
               class="form-control" style="width:80px;height:38px;text-align:center">
       </div>
      </div>

      <!-- Save button -->
      <div class="flex gap-3">
       <a href="availability.php" class="btn-g">Discard changes</a>
       <button type="submit" class="btn-t" style="flex:1;background:linear-gradient(135deg,var(--t),#065F46)">
        Save all changes
       </button>
      </div>
     </div>

     <!-- Right panel -->
     <div style="display:flex;flex-direction:column;gap:14px">

      <!-- Consultation fee -->
      <div class="card" style="padding:18px;background:var(--am2);border-color:#FDE68A">
       <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--am);margin-bottom:10px">Consultation fee</div>
       <div style="font-size:28px;font-weight:800;color:var(--tx);margin-bottom:6px">
        NPR <?=number_format($dr['fee'] ?? 800)?>
       </div>
       <div style="font-size:12px;color:var(--am)">⚠ Fee is set by admin. Contact admin to request a change.</div>
      </div>

      <!-- Weekly summary -->
      <div class="card" style="padding:18px">
       <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--tx3);margin-bottom:12px">Weekly summary</div>
       <div class="flex justify-between" style="margin-bottom:8px;font-size:13px">
        <span style="color:var(--tx3)">Working days</span>
        <span style="font-weight:700" id="sum_days">
         <?=count(array_filter($days, fn($d) => !empty($avail[$d]['enabled'])))?>/ week
        </span>
       </div>
       <div class="flex justify-between" style="margin-bottom:8px;font-size:13px">
        <span style="color:var(--tx3)">Slot duration</span>
        <span style="font-weight:700" id="sum_dur"><?=$sel_duration?> min</span>
       </div>
       <div class="flex justify-between" style="margin-bottom:8px;font-size:13px">
        <span style="color:var(--tx3)">Max pts/day</span>
        <span style="font-weight:700">8</span>
       </div>
       <div class="divl" style="margin:10px 0"></div>
       <div class="flex justify-between">
        <span style="font-size:14px;font-weight:700">Total slots/week</span>
        <span style="font-size:16px;font-weight:800;color:var(--b)" id="sum_total">
         ~<?=count(array_filter($days, fn($d) => !empty($avail[$d]['enabled']))) * 8?>
        </span>
       </div>
      </div>

     </div>
    </div>
   </form>
  </div>
 </div>
</div>

<script>
// ── Toggle a day on/off ────────────────────────────────────
function toggleDay(day) {
  var chk    = document.getElementById('chk_'   + day);
  var tog    = document.getElementById('tog_'   + day);
  var row    = document.getElementById('row_'   + day);
  var start  = document.getElementById('start_' + day);
  var end    = document.getElementById('end_'   + day);
  var max    = document.getElementById('max_'   + day);

  // Flip checked state
  chk.checked = !chk.checked;
  var on = chk.checked;

  // Update toggle visual
  tog.classList.toggle('on', on);

  // Update row opacity
  row.classList.toggle('off', !on);

  // Enable or disable inputs
  start.disabled = !on;
  end.disabled   = !on;
  max.disabled   = !on;

  // Update summary
  updateSummary();
}

// ── Select slot duration ───────────────────────────────────
function selectDuration(mins) {
  // Update hidden input
  document.getElementById('slot_duration').value = mins;

  // Update pill styles
  document.querySelectorAll('.dur-pill').forEach(function(pill) {
    pill.classList.remove('active');
    pill.textContent = pill.textContent.replace(' ✓','');
  });
  event.target.classList.add('active');
  event.target.textContent = mins + ' min ✓';

  // Update summary
  document.getElementById('sum_dur').textContent = mins + ' min';
  updateSummary();
}

// ── Update weekly summary panel ────────────────────────────
function updateSummary() {
  var days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
  var working = 0;
  days.forEach(function(day) {
    var chk = document.getElementById('chk_' + day);
    if (chk && chk.checked) working++;
  });

  document.getElementById('sum_days').textContent  = working + ' / week';
  document.getElementById('sum_total').textContent = '~' + (working * 8);
}

// ── Run summary on page load ───────────────────────────────
updateSummary();
</script>

</body>
</html>