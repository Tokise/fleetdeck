<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login_page();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>FleetDeck — Fleet &amp; Transportation Management</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="role-<?= htmlspecialchars($user['role']) ?>">

<div class="app" id="appRoot">
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-mark"><span class="dot"></span>FleetDeck</div>
      <div class="brand-sub">Fleet &amp; Transportation Management</div>
    </div>
    <nav class="modules" id="navList">
      <div class="nav-group-label">Overview</div>
      <a href="#dashboard" class="nav-item" data-view="dashboard">
        <span class="badge grey">OV</span>
        <span class="label">Dashboard</span>
      </a>

      <div class="nav-group-label">Fleet &amp; Transportation Management</div>
      <a href="#fvm" class="nav-item" data-view="fvm">
        <span class="badge amber">FVM</span>
        <span class="label">Fleet &amp; Vehicle Management</span>
      </a>
      <a href="#vrds" class="nav-item" data-view="vrds">
        <span class="badge amber">VRDS</span>
        <span class="label">Reservation &amp; Dispatch</span>
      </a>
      <a href="#dtpm" class="nav-item" data-view="dtpm">
        <span class="badge violet">DTPM</span>
        <span class="label">Driver &amp; Trip Performance</span>
      </a>
      <a href="#fms" class="nav-item" data-view="fms">
        <span class="badge teal">FMS</span>
        <span class="label">Fuel Management</span>
      </a>
      <a href="#tcao" class="nav-item" data-view="tcao">
        <span class="badge teal">TCAO</span>
        <span class="label">Cost Analysis &amp; Optimization</span>
      </a>
      <a href="#rpo" class="nav-item" data-view="rpo">
        <span class="badge sky">RPO</span>
        <span class="label">Route Planning</span>
      </a>
      <a href="#mfca" class="nav-item" data-view="mfca">
        <span class="badge grey">MFCA</span>
        <span class="label">Mobile Fleet Command<small>Optional</small></span>
      </a>

      <div class="nav-group-label">System</div>
      <a href="#archive" class="nav-item" data-view="archive">
        <span class="badge grey">ARC</span>
        <span class="label">Archive</span>
      </a>
    </nav>
    <div class="sidebar-signout">
      <a href="logout.php" class="btn-signout" title="Sign out"><span>⎋</span> Sign Out</a>
    </div>
    <div class="sidebar-foot">
      Data stored in MySQL (<?= htmlspecialchars(DB_NAME) ?> database) via PHP — running locally under XAMPP.
    </div>
  </aside>

  <main class="content">

    <div class="app-topbar">
      <div class="app-topbar-avatar"><?= htmlspecialchars(strtoupper(substr($user['username'], 0, 1))) ?></div>
      <div class="app-topbar-info">
        <div class="app-topbar-name"><?= htmlspecialchars($user['label']) ?></div>
        <div class="app-topbar-role"><?= htmlspecialchars(ucfirst($user['role'])) ?></div>
      </div>
    </div>

    <!-- DASHBOARD -->
    <section class="view" id="view-dashboard">
      <div class="topbar">
        <div>
          <h1>Operations Overview</h1>
          <div class="crumb">Fleet &amp; Transportation Management <b>/ Dashboard</b></div>
        </div>
      </div>

      <div class="welcome-banner">
        <div class="welcome-banner-text">
          <h2>Welcome to FleetDeck</h2>
          <p>A centralized fleet &amp; transportation management platform designed to manage vehicles, dispatch, drivers, fuel, and route records.</p>
          <span class="welcome-tag">FLEETDECK &middot; OPERATIONS</span>
        </div>
        <div class="welcome-banner-mark">FD</div>
      </div>

      <div class="kpi-grid" id="kpiGrid"></div>

      <div class="section-label"><span>System Modules</span><small>7 core modules</small></div>
      <div class="module-grid">
        <a href="#fvm" class="module-card"><span class="module-icon">FVM</span><span class="module-title">Fleet &amp; Vehicle</span><span class="module-sub">Registry &amp; status</span></a>
        <a href="#vrds" class="module-card"><span class="module-icon">VRDS</span><span class="module-title">Reservation &amp; Dispatch</span><span class="module-sub">Trips &amp; approvals</span></a>
        <a href="#dtpm" class="module-card"><span class="module-icon">DTPM</span><span class="module-title">Driver Performance</span><span class="module-sub">On-time &amp; safety</span></a>
        <a href="#fms" class="module-card"><span class="module-icon">FMS</span><span class="module-title">Fuel Management</span><span class="module-sub">Logs &amp; cost/liter</span></a>
        <a href="#tcao" class="module-card"><span class="module-icon">TCAO</span><span class="module-title">Cost Optimization</span><span class="module-sub">Spend breakdown</span></a>
        <a href="#rpo" class="module-card"><span class="module-icon">RPO</span><span class="module-title">Route Planning</span><span class="module-sub">Distance &amp; ETA</span></a>
        <a href="#mfca" class="module-card"><span class="module-icon">MFCA</span><span class="module-title">Mobile Command</span><span class="module-sub">Field companion app</span></a>
      </div>

      <div class="dash-grid">
        <div class="panel">
          <h2>Fuel spend, last 7 logged entries<small>Pulled from the Fuel Management module</small></h2>
          <canvas id="fuelChart"></canvas>
        </div>
        <div class="panel">
          <h2>Recent activity<small>Latest changes across modules</small></h2>
          <ul class="activity-list" id="activityList"></ul>
        </div>
      </div>
    </section>

    <!-- FVM -->
    <section class="view" id="view-fvm">
      <div class="topbar">
        <div>
          <h1>Fleet &amp; Vehicle Management</h1>
          <div class="crumb">FTM <b>/ FVM</b> — vehicle registry, status &amp; odometer tracking</div>
        </div>
        <div class="topbar-actions admin-only"><button class="btn primary" onclick="openModal('vehicle')">+ Add vehicle</button></div>
      </div>
      <div class="module-toolbar">
        <input class="search-box" placeholder="Search plate no, make, model…" oninput="renderVehicles(this.value)">
      </div>
      <div class="table-wrap"><div id="vehiclesTable"></div></div>
    </section>

    <!-- VRDS -->
    <section class="view" id="view-vrds">
      <div class="topbar">
        <div>
          <h1>Vehicle Reservation &amp; Dispatch</h1>
          <div class="crumb">FTM <b>/ VRDS</b> — trip requests, approvals &amp; dispatch status</div>
        </div>
        <div class="topbar-actions admin-only"><button class="btn primary" onclick="openModal('reservation')">+ New reservation</button></div>
      </div>
      <div class="table-wrap"><div id="reservationsTable"></div></div>
    </section>

    <!-- DTPM -->
    <section class="view" id="view-dtpm">
      <div class="topbar">
        <div>
          <h1>Driver &amp; Trip Performance Monitoring</h1>
          <div class="crumb">FTM <b>/ DTPM</b> — on-time rate, safety score, trips completed</div>
        </div>
        <div class="topbar-actions admin-only"><button class="btn primary" onclick="openModal('driver')">+ Add driver</button></div>
      </div>
      <div class="table-wrap"><div id="driversTable"></div></div>
    </section>

    <!-- FMS -->
    <section class="view" id="view-fms">
      <div class="topbar">
        <div>
          <h1>Fuel Management System</h1>
          <div class="crumb">FTM <b>/ FMS</b> — fuel logs by vehicle, cost per liter</div>
        </div>
        <div class="topbar-actions admin-only"><button class="btn primary" onclick="openModal('fuel')">+ Log fuel entry</button></div>
      </div>
      <div class="table-wrap"><div id="fuelTable"></div></div>
    </section>

    <!-- TCAO -->
    <section class="view" id="view-tcao">
      <div class="topbar">
        <div>
          <h1>Transport Cost Analysis &amp; Optimization</h1>
          <div class="crumb">FTM <b>/ TCAO</b> — cost breakdown, computed from fuel logs</div>
        </div>
      </div>
      <div class="dash-grid">
        <div class="panel">
          <h2>Fuel cost by vehicle<small>Sum of logged fuel spend per plate number</small></h2>
          <canvas id="costChart"></canvas>
        </div>
        <div class="panel">
          <h2>Optimization flags<small>Vehicles spending above the fleet average</small></h2>
          <div id="flagList"></div>
        </div>
      </div>
    </section>

    <!-- RPO -->
    <section class="view" id="view-rpo">
      <div class="topbar">
        <div>
          <h1>Route Planning &amp; Optimization</h1>
          <div class="crumb">FTM <b>/ RPO</b> — planned routes, distance &amp; status</div>
        </div>
        <div class="topbar-actions admin-only"><button class="btn primary" onclick="openModal('route')">+ Add route</button></div>
      </div>
      <div class="table-wrap"><div id="routesTable"></div></div>
    </section>

    <!-- MFCA -->
    <section class="view" id="view-mfca">
      <div class="topbar">
        <div>
          <h1>Mobile Fleet Command App</h1>
          <div class="crumb">FTM <b>/ MFCA</b> — optional companion module</div>
        </div>
      </div>
      <div class="placeholder-panel">
        <div class="glyph">MFCA</div>
        <h3>Not built yet — marked optional in the module list</h3>
        <p>This would be a lightweight mobile view for drivers and dispatchers: accept a dispatch, log fuel and odometer from the field, and send a live location ping. It's scoped out of this PHP pass; the sections below are what it would eventually talk to.</p>
        <div class="feature-chip-row">
          <span class="feature-chip">Driver check-in</span>
          <span class="feature-chip">Push dispatch alerts</span>
          <span class="feature-chip">Field fuel logging</span>
          <span class="feature-chip">Live GPS ping</span>
        </div>
      </div>
    </section>

    <!-- ARCHIVE -->
    <section class="view" id="view-archive">
      <div class="topbar">
        <div>
          <h1>Archive</h1>
          <div class="crumb">System <b>/ Archive</b> — records removed from other modules, restorable by admin</div>
        </div>
        <div class="topbar-actions admin-only"><button class="btn danger-ghost" onclick="clearArchive()">Empty archive</button></div>
      </div>
      <div class="table-wrap"><div id="archiveTable"></div></div>
    </section>

  </main>
</div>

<div class="modal-backdrop" id="modalBackdrop">
  <div class="modal">
    <div class="modal-head">
      <h3 id="modalTitle">Add</h3>
      <button class="modal-close" onclick="closeModal()">&times;</button>
    </div>
    <div class="modal-body" id="modalBody"></div>
    <div class="modal-foot">
      <button class="btn" onclick="closeModal()">Cancel</button>
      <button class="btn primary" id="modalSaveBtn">Save</button>
    </div>
  </div>
</div>

<div id="toast"></div>

<script>
  // Session info rendered server-side, read by assets/app.js to decide
  // whether admin-only actions are allowed to call the API at all.
  window.SESSION = {
    username: <?= json_encode($user['username']) ?>,
    role: <?= json_encode($user['role']) ?>,
    label: <?= json_encode($user['label']) ?>
  };
</script>
<script src="assets/app.js"></script>
</body>
</html>
