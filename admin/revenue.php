<?php
// admin/revenue.php — Revenue dashboard + full transaction log
// Shows paid appointments only. Dates are based on appointments.created_at
// (close proxy for "paid date" — see note below about adding a paid_at column).
// ============================================================
session_start();
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
requireAdmin();

// ── INPUT (filters) ─────────────────────────────────────────
// Month must be YYYY-MM. Anything else falls back to current month.
$ym = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ym)) $ym = date('Y-m');

$clinic_filter = trim($_GET['clinic'] ?? '');
$name_search   = trim($_GET['search'] ?? '');

$ym_safe       = $conn->real_escape_string($ym);
$clinic_safe   = $conn->real_escape_string($clinic_filter);
$name_safe     = $conn->real_escape_string($name_search);

// ── KPI: top-of-page metrics ────────────────────────────────
// "This month" uses the filtered month, not necessarily today's month.
$kpi_month = $conn->query(
    "SELECT COALESCE(SUM(d.fee),0) AS rev,
            COUNT(*)              AS txns,
            COALESCE(AVG(d.fee),0) AS avg_val
       FROM appointments a
       JOIN doctors d ON d.id = a.doctor_id
      WHERE a.payment_status = 'paid'
        AND DATE_FORMAT(a.created_at, '%Y-%m') = '$ym_safe'"
)->fetch_assoc();

$kpi_alltime = $conn->query(
    "SELECT COALESCE(SUM(d.fee),0) AS rev, COUNT(*) AS txns
       FROM appointments a
       JOIN doctors d ON d.id = a.doctor_id
      WHERE a.payment_status = 'paid'"
)->fetch_assoc();

// ── Monthly trend, last 12 months ───────────────────────────
$trend = $conn->query(
    "SELECT DATE_FORMAT(a.created_at, '%Y-%m') AS ym,
            SUM(d.fee)                          AS rev,
            COUNT(*)                            AS txns
       FROM appointments a
       JOIN doctors d ON d.id = a.doctor_id
      WHERE a.payment_status = 'paid'
        AND a.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
      GROUP BY ym
      ORDER BY ym ASC"
);
$trend_rows = [];
while ($r = $trend->fetch_assoc()) $trend_rows[] = $r;
$trend_max = max(array_column($trend_rows, 'rev') ?: [1]);  // for bar scaling

// ── Revenue by clinic for the selected month ────────────────
$by_clinic = $conn->query(
    "SELECT d.clinic_name,
            COUNT(DISTINCT d.id) AS doctors,
            COUNT(*)             AS txns,
            SUM(d.fee)           AS rev
       FROM appointments a
       JOIN doctors d ON d.id = a.doctor_id
      WHERE a.payment_status = 'paid'
        AND DATE_FORMAT(a.created_at, '%Y-%m') = '$ym_safe'
      GROUP BY d.clinic_name
      ORDER BY rev DESC"
);

// ── Distinct clinics (for the filter dropdown) ──────────────
$clinics = $conn->query(
    "SELECT DISTINCT clinic_name FROM doctors WHERE clinic_name IS NOT NULL AND clinic_name <> '' ORDER BY clinic_name"
);

// ── Transaction list (filtered) ─────────────────────────────
$where = ["a.payment_status='paid'", "DATE_FORMAT(a.created_at,'%Y-%m')='$ym_safe'"];
if ($clinic_filter !== '') $where[] = "d.clinic_name = '$clinic_safe'";
if ($name_search !== '')   $where[] = "(p.name LIKE '%$name_safe%' OR d.name LIKE '%$name_safe%')";
$where_sql = implode(' AND ', $where);

$txns = $conn->query(
    "SELECT a.id, a.created_at, a.transaction_id,
            a.date, a.time,
            p.name  AS pname, p.email AS pemail,
            d.name  AS dname, d.specialisation, d.clinic_name, d.fee
       FROM appointments a
       JOIN patients p ON p.id = a.patient_id
       JOIN doctors  d ON d.id = a.doctor_id
      WHERE $where_sql
      ORDER BY a.created_at DESC
      LIMIT 200"
);

// Other admin counters (for sidebar consistency with dashboard.php)
$pending_doctors = (int)$conn->query("SELECT COUNT(*) c FROM doctors WHERE status='pending'")->fetch_assoc()['c'];

// Pretty month label
$ym_label = date('F Y', strtotime($ym . '-01'));

$page_title = 'Revenue & Transactions';
include '../includes/header.php';
?>
<style>
.admin-bg{background:#0F1923}

/* Page-local styles — mirror admin/dashboard.php conventions */
.kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}
.kpi{background:#1E293B;border:1px solid #334155;border-radius:12px;padding:18px}
.kpi .lbl{font-size:11px;color:#64748B;text-transform:uppercase;letter-spacing:.06em;font-weight:700;margin-bottom:8px}
.kpi .val{font-size:24px;font-weight:800;color:#F8FAFC;line-height:1.2}
.kpi .sub{font-size:11px;color:#94A3B8;margin-top:6px}
.kpi.accent .val{color:#34D399}

.r-card{background:#1E293B;border:1px solid #334155;border-radius:12px;padding:20px;margin-bottom:22px}
.r-card h2{font-size:14px;font-weight:700;color:#F8FAFC;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center}
.r-card h2 .badge{font-size:11px;background:#334155;color:#94A3B8;padding:3px 10px;border-radius:6px;font-weight:600}

/* Monthly trend bars */
.trend-row{display:grid;grid-template-columns:80px 1fr 100px 70px;gap:12px;align-items:center;padding:7px 0;font-size:12px;color:#CBD5E1}
.trend-row .month{color:#94A3B8;font-weight:600}
.trend-row .bar-wrap{background:#0F1923;border-radius:5px;height:18px;overflow:hidden;border:1px solid #334155}
.trend-row .bar{height:100%;background:linear-gradient(90deg,#3B82F6,#60A5FA);border-radius:4px;min-width:2px}
.trend-row .amt{text-align:right;font-weight:700;color:#F8FAFC}
.trend-row .count{text-align:right;color:#64748B}
.trend-row.empty{color:#475569}
.trend-row.empty .bar{background:#334155}

/* Filters */
.filter-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;align-items:end}
.filter-bar label{display:block;font-size:10px;color:#64748B;text-transform:uppercase;letter-spacing:.06em;font-weight:700;margin-bottom:5px}
.filter-bar select, .filter-bar input{
  background:#1E293B;border:1px solid #334155;color:#F8FAFC;
  padding:8px 11px;border-radius:7px;font-size:13px;font-family:inherit;
  min-width:170px;
}
.filter-bar input{min-width:220px}
.filter-bar .btn-apply{
  background:#3B82F6;color:#fff;border:none;padding:8px 18px;
  border-radius:7px;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit;
}
.filter-bar .btn-clear{
  background:transparent;color:#94A3B8;border:1px solid #334155;
  padding:8px 14px;border-radius:7px;font-size:13px;text-decoration:none;font-weight:600;
}

/* Tables */
.r-table{width:100%;border-collapse:collapse;font-size:13px}
.r-table thead th{
  text-align:left;background:#0F1923;color:#94A3B8;font-weight:700;
  font-size:11px;text-transform:uppercase;letter-spacing:.05em;
  padding:11px 13px;border-bottom:1px solid #334155;
}
.r-table thead th.right{text-align:right}
.r-table tbody td{padding:13px;color:#E2E8F0;border-bottom:1px solid #1E293B;vertical-align:top}
.r-table tbody td.right{text-align:right;font-weight:700;color:#F8FAFC}
.r-table tbody tr:hover{background:#0F1923}
.r-table .mono{font-family:ui-monospace,'SF Mono',Menlo,Consolas,monospace;font-size:12px;color:#94A3B8}
.r-table .sub{font-size:11px;color:#64748B;margin-top:2px}

.empty-state{text-align:center;padding:40px 20px;color:#64748B;font-size:13px}
.export-link{font-size:12px;color:#60A5FA;text-decoration:none;font-weight:600}
.export-link:hover{text-decoration:underline}

@media (max-width:1100px){
  .kpi-grid{grid-template-columns:repeat(2,1fr)}
}
</style>

<div class="layout">
 <!-- Sidebar (matches admin/dashboard.php) -->
 <div class="sidebar-dark">
  <div class="sb-profile-dark">
   <div class="flex items-center gap-3">
    <div class="logo-i" style="width:28px;height:28px"><svg width="12" height="12" fill="none"><rect x="5" y="1" width="2" height="10" rx="1" fill="white"/><rect x="1" y="5" width="10" height="2" rx="1" fill="white"/></svg></div>
    <span style="font-size:14px;font-weight:800;color:#F8FAFC">Admin Panel</span>
   </div>
  </div>
  <div class="sb-nav">
   <a class="nav-item-dark" href="dashboard.php"> &nbsp;Dashboard</a>
   <a class="nav-item-dark" href="manage_doctors.php"> &nbsp;Doctors</a>
   <a class="nav-item-dark" href="manage_patients.php"> &nbsp;Patients</a>
   <a class="nav-item-dark" href="manage_appointments.php"> &nbsp;Bookings</a>
   <a class="nav-item-dark active" href="revenue.php"> &nbsp;Revenue</a>
  </div>
  <div class="sb-bottom-dark">
   <a class="nav-item-dark" href="logout.php">↩ &nbsp;Log out</a>
  </div>
 </div>

 <!-- Main -->
 <div class="main-content">
  <div class="topbar-dark">
   <div class="flex items-center gap-3">
    <div class="logo-i">M</div>
    <span style="font-size:16px;font-weight:800;color:#F8FAFC;letter-spacing:-.2px">MediBook Admin</span>
   </div>
   <span style="font-size:13px;color:#64748B"><?= date('l, F j, Y') ?></span>
   <div class="flex gap-2">
    <?php if($pending_doctors>0): ?>
     <a href="manage_doctors.php?filter=pending" class="btn-ot btn-sm" style="color:#FCD34D;border-color:#F59E0B">⏳ <?= $pending_doctors ?> pending</a>
    <?php endif; ?>
   </div>
  </div>

  <div class="admin-page-body">
   <h1 class="page-title" style="color:#F8FAFC">Revenue &amp; Transactions</h1>
   <p class="page-sub" style="color:#64748B;margin-bottom:22px">Showing data for <strong style="color:#F8FAFC"><?= htmlspecialchars($ym_label) ?></strong></p>

   <!-- KPI cards -->
   <div class="kpi-grid">
    <div class="kpi accent">
     <div class="lbl">Revenue · <?= htmlspecialchars($ym_label) ?></div>
     <div class="val">NPR <?= number_format((float)$kpi_month['rev']) ?></div>
     <div class="sub">From <?= (int)$kpi_month['txns'] ?> paid transaction<?= $kpi_month['txns']==1?'':'s' ?></div>
    </div>
    <div class="kpi">
     <div class="lbl">Avg. transaction</div>
     <div class="val">NPR <?= $kpi_month['txns'] > 0 ? number_format((float)$kpi_month['avg_val']) : '0' ?></div>
     <div class="sub"><?= htmlspecialchars($ym_label) ?></div>
    </div>
    <div class="kpi">
     <div class="lbl">Lifetime revenue</div>
     <div class="val">NPR <?= number_format((float)$kpi_alltime['rev']) ?></div>
     <div class="sub"><?= (int)$kpi_alltime['txns'] ?> total transactions</div>
    </div>
    <div class="kpi">
     <div class="lbl">Paid this month</div>
     <div class="val"><?= (int)$kpi_month['txns'] ?></div>
     <div class="sub">eSewa transactions</div>
    </div>
   </div>

   <!-- Monthly trend -->
   <div class="r-card">
    <h2>Monthly trend (last 12 months) <span class="badge">all clinics</span></h2>
    <?php if (empty($trend_rows)): ?>
     <div class="empty-state">No paid transactions in the last 12 months yet.</div>
    <?php else: ?>
     <?php foreach ($trend_rows as $row):
        $width_pct = $trend_max > 0 ? ($row['rev'] / $trend_max) * 100 : 0;
        $row_label = date('M Y', strtotime($row['ym'] . '-01'));
        $is_current = $row['ym'] === $ym;
     ?>
      <div class="trend-row<?= $is_current ? '' : '' ?>">
       <div class="month"><?= htmlspecialchars($row_label) ?></div>
       <div class="bar-wrap"><div class="bar" style="width:<?= number_format($width_pct, 1) ?>%"></div></div>
       <div class="amt">NPR <?= number_format((float)$row['rev']) ?></div>
       <div class="count"><?= (int)$row['txns'] ?> txn</div>
      </div>
     <?php endforeach; ?>
    <?php endif; ?>
   </div>

   <!-- Filters -->
   <form method="GET" class="filter-bar">
    <div>
     <label>Month</label>
     <select name="month">
      <?php
       // List the last 24 months as options
       for ($i = 0; $i < 24; $i++) {
         $opt_ym  = date('Y-m', strtotime("-$i months"));
         $opt_lbl = date('F Y', strtotime("-$i months"));
         $sel = $opt_ym === $ym ? 'selected' : '';
         echo "<option value='$opt_ym' $sel>$opt_lbl</option>";
       }
      ?>
     </select>
    </div>
    <div>
     <label>Hospital / clinic</label>
     <select name="clinic">
      <option value="">All clinics</option>
      <?php while ($c = $clinics->fetch_assoc()): ?>
       <option value="<?= htmlspecialchars($c['clinic_name']) ?>" <?= $c['clinic_name']===$clinic_filter?'selected':'' ?>>
        <?= htmlspecialchars($c['clinic_name']) ?>
       </option>
      <?php endwhile; ?>
     </select>
    </div>
    <div>
     <label>Search (patient / doctor name)</label>
     <input type="text" name="search" value="<?= htmlspecialchars($name_search) ?>" placeholder="Aarav, Dr. Basnet…">
    </div>
    <button type="submit" class="btn-apply">Apply filters</button>
    <?php if ($clinic_filter !== '' || $name_search !== ''): ?>
     <a href="revenue.php?month=<?= htmlspecialchars($ym) ?>" class="btn-clear">Clear</a>
    <?php endif; ?>
   </form>

   <!-- Revenue by clinic -->
   <div class="r-card">
    <h2>Revenue by hospital — <?= htmlspecialchars($ym_label) ?></h2>
    <?php if ($by_clinic->num_rows === 0): ?>
     <div class="empty-state">No paid transactions for any clinic this month.</div>
    <?php else: ?>
     <table class="r-table">
      <thead>
       <tr>
        <th>Hospital / Clinic</th>
        <th class="right">Doctors</th>
        <th class="right">Transactions</th>
        <th class="right">Revenue (NPR)</th>
        <th class="right">% of total</th>
       </tr>
      </thead>
      <tbody>
       <?php
         $month_total = (float)$kpi_month['rev'];
         while ($c = $by_clinic->fetch_assoc()):
            $pct = $month_total > 0 ? ((float)$c['rev'] / $month_total) * 100 : 0;
       ?>
        <tr>
         <td><strong><?= htmlspecialchars($c['clinic_name'] ?: '— unspecified —') ?></strong></td>
         <td class="right"><?= (int)$c['doctors'] ?></td>
         <td class="right"><?= (int)$c['txns'] ?></td>
         <td class="right"><?= number_format((float)$c['rev']) ?></td>
         <td class="right" style="color:#94A3B8;font-weight:600"><?= number_format($pct, 1) ?>%</td>
        </tr>
       <?php endwhile; ?>
      </tbody>
     </table>
    <?php endif; ?>
   </div>

   <!-- Transaction details -->
   <div class="r-card">
    <h2>
     Transaction log
     <span class="badge"><?= $txns->num_rows ?> result<?= $txns->num_rows==1?'':'s' ?>
      <?= $txns->num_rows >= 200 ? ' (capped)' : '' ?>
     </span>
    </h2>

    <?php if ($txns->num_rows === 0): ?>
     <div class="empty-state">No transactions match the current filters.</div>
    <?php else: ?>
     <table class="r-table">
      <thead>
       <tr>
        <th>Paid on</th>
        <th>Patient</th>
        <th>Doctor / Specialty</th>
        <th>Hospital</th>
        <th>Booking</th>
        <th class="right">Amount</th>
       </tr>
      </thead>
      <tbody>
       <?php while ($t = $txns->fetch_assoc()): ?>
        <tr>
         <td>
          <?= date('d M Y', strtotime($t['created_at'])) ?>
          <div class="sub"><?= date('g:i A', strtotime($t['created_at'])) ?></div>
         </td>
         <td>
          <?= htmlspecialchars($t['pname']) ?>
          <div class="sub"><?= htmlspecialchars($t['pemail']) ?></div>
         </td>
         <td>
          <?= htmlspecialchars($t['dname']) ?>
          <div class="sub"><?= htmlspecialchars($t['specialisation']) ?></div>
         </td>
         <td><?= htmlspecialchars($t['clinic_name'] ?: '—') ?></td>
         <td>
          <span class="mono">MB-<?= str_pad($t['id'],4,'0',STR_PAD_LEFT) ?></span>
          <div class="sub"><?= fmtDate($t['date']) ?></div>
         </td>
         <td class="right">NPR <?= number_format((float)$t['fee']) ?></td>
        </tr>
       <?php endwhile; ?>
      </tbody>
     </table>
     <?php if ($txns->num_rows >= 200): ?>
      <div style="margin-top:12px;font-size:12px;color:#94A3B8">
       Showing the most recent 200 transactions. Narrow your filters to see older entries.
      </div>
     <?php endif; ?>
    <?php endif; ?>
   </div>

  </div>
 </div>
</div>

</body></html>