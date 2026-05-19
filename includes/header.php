<?php
// includes/header.php  — shared <head> + CSS
// Call: include once at top of each page.
// Pass $page_title before including.
// ============================================================
if (!isset($page_title)) $page_title = 'MediBook';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= htmlspecialchars($page_title) ?> — MediBook</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --b:#1A6FD4;--b2:#0D4FA0;--b3:#EBF3FF;--b4:#D0E7FF;
  --t:#0D9E7A;--t2:#E3F8F2;--t3:#9FE1CB;
  --am:#D97706;--am2:#FEF3C7;
  --r:#DC2626;--r2:#FEE2E2;--r3:#FCA5A5;
  --navy:#0A1628;
  --tx:#0F172A;--tx2:#475569;--tx3:#94A3B8;
  --bd:#E2E8F0;--bd2:#CBD5E1;
  --bg:#F8FAFC;--bg2:#F1F5F9;--w:#fff;
  --sh:0 1px 3px rgba(0,0,0,.08);
  --shm:0 4px 16px rgba(0,0,0,.10);
}
body{font-family:'Outfit',sans-serif;background:var(--bg);color:var(--tx);min-height:100vh}

/* ── Layout ── */
.layout{display:flex;min-height:100vh}
.sidebar{width:220px;background:var(--w);border-right:1px solid var(--bd);flex-shrink:0;display:flex;flex-direction:column;position:fixed;height:100vh;top:0;left:0;z-index:50}
.sidebar-dark{width:220px;background:#0F1923;border-right:1px solid #1E2D42;flex-shrink:0;display:flex;flex-direction:column;position:fixed;height:100vh;top:0;left:0;z-index:50}
.main-content{margin-left:220px;flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{height:76px;background:var(--w);border-bottom:1px solid var(--bd);display:flex;align-items:center;justify-content:space-between;padding:0 36px;position:sticky;top:0;z-index:40}
.topbar-dark{height:76px;background:var(--navy);border-bottom:1px solid #1E2D42;display:flex;align-items:center;justify-content:space-between;padding:0 36px;position:sticky;top:0;z-index:40}
.page-body{padding:28px;flex:1;background:var(--bg)}

/* ── Logo ── */
.logo-i{width:42px;height:42px;background:linear-gradient(135deg,var(--b),var(--b2));border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#fff;font-weight:800;font-size:24px}
.logo-n{font-size:21px;font-weight:800;color:var(--tx);margin-left:12px;letter-spacing:-.2px}
.logo-n-white{font-size:21px;font-weight:800;color:#F8FAFC;margin-left:12px;letter-spacing:-.2px}

/* ── Sidebar nav ── */
.sb-profile{padding:16px 18px 14px;border-bottom:1px solid var(--bd);text-align:center}
.sb-profile-dark{padding:16px 18px 14px;border-bottom:1px solid #1E2D42}
.sb-nav{padding:10px 10px;flex:1;overflow-y:auto}
.sb-bottom{padding:10px 10px;border-top:1px solid var(--bd)}
.sb-bottom-dark{padding:10px 10px;border-top:1px solid #1E2D42}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 13px;border-radius:9px;font-size:13px;color:var(--tx2);font-weight:500;cursor:pointer;margin-bottom:2px;text-decoration:none;transition:background .15s}
.nav-item:hover{background:var(--bg2)}
.nav-item.active{background:var(--b3);color:var(--b);font-weight:700}
.nav-item-dark{display:flex;align-items:center;gap:10px;padding:9px 13px;border-radius:9px;font-size:13px;color:#64748B;font-weight:500;cursor:pointer;margin-bottom:2px;text-decoration:none;transition:background .15s}
.nav-item-dark:hover{background:rgba(255,255,255,.05);color:#D1D5DB}
.nav-item-dark.active{background:rgba(26,111,212,.2);color:#60A5FA;font-weight:700}
.nav-badge{background:var(--b);color:#fff;border-radius:99px;padding:1px 7px;font-size:10px;font-weight:700;margin-left:auto}

/* ── Avatar ── */
.av{border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0}
.av-blue{background:linear-gradient(135deg,var(--b),var(--b2));color:#fff}
.av-teal{background:linear-gradient(135deg,var(--t),#065F46);color:#fff}
.av-amber{background:var(--am2);color:#92400E}

/* ── Cards ── */
.card{background:var(--w);border:1px solid var(--bd);border-radius:13px;box-shadow:var(--sh)}
.card-dark{background:#1A2840;border:1px solid #2D3F56;border-radius:13px}
.card-body{padding:20px}
.card-header{padding:16px 20px;border-bottom:1px solid var(--bd);display:flex;align-items:center;justify-content:space-between}
.card-header-dark{padding:16px 20px;border-bottom:1px solid #2D3F56;display:flex;align-items:center;justify-content:space-between}

/* ── Buttons ── */
.btn-p{background:linear-gradient(135deg,var(--b),var(--b2));border:none;border-radius:9px;padding:10px 22px;font-size:13px;font-weight:700;color:#fff;cursor:pointer;box-shadow:0 3px 12px rgba(26,111,212,.35);font-family:'Outfit',sans-serif;white-space:nowrap;text-decoration:none;display:inline-block;text-align:center;transition:opacity .15s}
.btn-p:hover{opacity:.88}
.btn-t{background:linear-gradient(135deg,var(--t),#065F46);border:none;border-radius:9px;padding:10px 22px;font-size:13px;font-weight:700;color:#fff;cursor:pointer;font-family:'Outfit',sans-serif;text-decoration:none;display:inline-block}
.btn-g{background:transparent;border:1.5px solid var(--bd2);border-radius:9px;padding:9px 20px;font-size:12px;font-weight:600;color:var(--tx);cursor:pointer;font-family:'Outfit',sans-serif;text-decoration:none;display:inline-block;transition:border-color .15s}
.btn-g:hover{border-color:var(--b);color:var(--b)}
.btn-ot{background:transparent;border:1.5px solid var(--b);border-radius:9px;padding:9px 20px;font-size:12px;font-weight:600;color:var(--b);cursor:pointer;font-family:'Outfit',sans-serif;text-decoration:none;display:inline-block}
.btn-r{background:var(--r);border:none;border-radius:9px;padding:10px 22px;font-size:13px;font-weight:700;color:#fff;cursor:pointer;font-family:'Outfit',sans-serif;box-shadow:0 3px 12px rgba(220,38,38,.3);text-decoration:none;display:inline-block}
.btn-r-out{background:transparent;border:1.5px solid var(--r);border-radius:9px;padding:9px 20px;font-size:12px;font-weight:600;color:var(--r);cursor:pointer;font-family:'Outfit',sans-serif;text-decoration:none;display:inline-block}
.btn-sm{padding:5px 14px;font-size:11px;border-radius:7px}
.btn-xs{padding:4px 10px;font-size:10px;border-radius:6px}
.btn-full{width:100%;display:block;text-align:center}

/* ── Forms ── */
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:12px;font-weight:700;color:var(--tx2);margin-bottom:6px}
.form-control{width:100%;background:var(--bg);border:1.5px solid var(--bd2);border-radius:9px;height:44px;padding:0 14px;font-size:13px;color:var(--tx);font-family:'Outfit',sans-serif;outline:none;transition:border-color .15s}
.form-control:focus{border-color:var(--b);box-shadow:0 0 0 3px rgba(26,111,212,.12)}
.form-textarea{height:100px;padding:12px 14px;resize:vertical;width:100%}
.form-select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%2394A3B8' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center}
.form-error{color:var(--r);font-size:12px;margin-top:5px;font-weight:500}
.form-success{color:var(--t);font-size:12px;margin-top:5px;font-weight:500}

/* ── Alerts ── */
.alert{padding:14px 18px;border-radius:10px;margin-bottom:18px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:11px}
.alert-success{background:var(--t2);color:#065F46;border:1px solid var(--t3)}
.alert-error{background:var(--r2);color:var(--r);border:1px solid var(--r3)}
.alert-info{background:var(--b3);color:var(--b2);border:1px solid var(--b4)}
.alert-warning{background:var(--am2);color:#92400E;border:1px solid #FDE68A}

/* ── Badges ── */
.badge{border-radius:6px;padding:3px 10px;font-size:11px;font-weight:700;display:inline-block}
.badge-green{background:#D1FAE5;color:#065F46}
.badge-amber{background:var(--am2);color:#92400E}
.badge-red{background:var(--r2);color:var(--r)}
.badge-grey{background:var(--bg2);color:var(--tx2)}
.badge-blue{background:var(--b3);color:var(--b2)}

/* ── Tables ── */
.table{width:100%;border-collapse:collapse}
.table th{padding:10px 14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--tx3);background:var(--bg);border-bottom:1px solid var(--bd);text-align:left}
.table td{padding:12px 14px;font-size:13px;color:var(--tx2);border-bottom:1px solid var(--bd)}
.table tr:last-child td{border-bottom:none}
.table tr:hover td{background:var(--bg)}
.table-dark th{background:#111827;color:#64748B;padding:10px 14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;text-align:left}
.table-dark td{padding:12px 14px;font-size:13px;color:#D1D5DB;border-bottom:1px solid #2D3F56}

/* ── Stat cards ── */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:24px}
.stat-card{background:var(--w);border:1px solid var(--bd);border-radius:13px;padding:18px;text-align:center;box-shadow:var(--sh)}
.stat-num{font-size:28px;font-weight:800;color:var(--tx);line-height:1;margin-bottom:5px}
.stat-lbl{font-size:12px;color:var(--tx3);font-weight:500}
.stat-sub{font-size:11px;font-weight:600;margin-top:5px}

/* ── Page titles ── */
.page-title{font-size:22px;font-weight:800;color:var(--tx);margin-bottom:4px;letter-spacing:-.3px}
.page-sub{font-size:13px;color:var(--tx3);margin-bottom:24px}

/* ── Auth pages ── */
.auth-split{display:grid;grid-template-columns:1fr 1fr;min-height:100vh}
.auth-left{background:linear-gradient(145deg,#0A1628,#1A3A6B 55%,#1A6FD4);padding:60px 52px;display:flex;flex-direction:column;justify-content:center}
.auth-right{background:var(--w);padding:52px 60px;display:flex;flex-direction:column;justify-content:center}
.auth-tabs{display:flex;border-bottom:2px solid var(--bd);margin-bottom:22px}
.auth-tab{padding:10px 20px;font-size:14px;color:var(--tx3);cursor:pointer;text-decoration:none;font-weight:500}
.auth-tab.active{border-bottom:3px solid var(--b);margin-bottom:-2px;color:var(--b);font-weight:700}

/* ── Dark admin pages ── */
.admin-bg{background:#0F1923;min-height:100vh}
.admin-page-body{padding:28px;background:#0F1923;flex:1}

/* ── Stars ── */
.stars{color:#F59E0B}

/* ── Divider ── */
.divl{height:1px;background:var(--bd)}
.divl-dark{height:1px;background:#2D3F56}

/* ── Time slots ── */
.slot-grid{display:flex;flex-wrap:wrap;gap:8px}
.slot{border:1.5px solid var(--bd2);border-radius:8px;padding:7px 14px;font-size:12px;color:var(--tx2);background:var(--w);cursor:pointer;font-weight:500;font-family:'Outfit',sans-serif;transition:all .15s}
.slot:hover{border-color:var(--b);color:var(--b)}
.slot.booked{background:var(--bg2);border-color:var(--bd);color:var(--tx3);cursor:not-allowed}
.slot.selected{background:var(--b);color:#fff;border-color:var(--b);font-weight:700}

/* ── Calendar ── */
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:4px}
.cal-cell{height:34px;display:flex;align-items:center;justify-content:center;font-size:12px;border-radius:7px;cursor:pointer;font-weight:500;color:var(--tx2)}
.cal-hd{font-size:10px;font-weight:700;color:var(--tx3);cursor:default}
.cal-avail{background:var(--t2);color:#065F46;font-weight:700;border:1.5px solid var(--t3)}
.cal-sel{background:var(--b);color:#fff;font-weight:800}
.cal-block{background:var(--r2);border:1.5px solid var(--r3);color:var(--r)}
.cal-other{color:var(--tx3);opacity:.3;cursor:default}
.cal-off{background:var(--bg2);color:var(--tx3);opacity:.5;cursor:not-allowed}

/* ── Notification item ── */
.notif-item{display:flex;gap:12px;padding:12px 16px;border-radius:10px;margin-bottom:8px;transition:background .15s}
.notif-item.unread{background:var(--b3)}
.notif-item.read{background:var(--bg)}
.notif-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}

/* ── Responsive ── */
@media (max-width:768px){
  .sidebar,.sidebar-dark{display:none}
  .main-content{margin-left:0}
  .auth-split{grid-template-columns:1fr}
  .auth-left{display:none}
  .stat-grid{grid-template-columns:1fr 1fr}
}

/* ── Utils ── */
.text-center{text-align:center}
.text-right{text-align:right}
.mt-4{margin-top:16px}.mt-8{margin-top:32px}.mb-4{margin-bottom:16px}.mb-8{margin-bottom:32px}
.flex{display:flex}.items-center{align-items:center}.justify-between{justify-content:space-between}.gap-2{gap:8px}.gap-3{gap:12px}.gap-4{gap:16px}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.w-full{width:100%}
.fw-700{font-weight:700}.fw-800{font-weight:800}
.fs-12{font-size:12px}.fs-13{font-size:13px}.fs-14{font-size:14px}
.color-tx3{color:var(--tx3)}.color-b{color:var(--b)}.color-r{color:var(--r)}.color-t{color:var(--t)}
</style>
</head>
<body>
<?php
// Load chatbot widget for logged-in patients only
if (!empty($_SESSION['patient_id'])) {
    include_once __DIR__ . '/../chatbot/widget.php';
}
?>
