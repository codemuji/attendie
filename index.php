<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <meta name="theme-color" content="#0b0f19">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title>Attendie - Personal Attendance & Dispute Record</title>
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

  <div class="app-container">
    <!-- Top Header -->
    <header class="app-header">
      <div class="brand-section">
        <div class="brand-icon">
          <!-- Fingerprint SVG icon -->
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 4"/>
            <path d="M14 13.12c0 2.38 0 6.38-1 8.88"/>
            <path d="M2 12a10 10 0 0 1 18-6"/>
            <path d="M4.93 19.07A10 10 0 0 1 2 12"/>
            <path d="M7 12a5 5 0 0 1 5-5"/>
            <path d="M17 12a5 5 0 0 0-2-3.87"/>
            <path d="M19.07 4.93A10 10 0 0 1 22 12c0 2.12-.66 4.09-1.78 5.71"/>
          </svg>
        </div>
        <span class="brand-title">Attendie</span>
      </div>

      <div class="header-status">
        <span class="status-dot"></span>
        <span>Audit Active</span>
      </div>
    </header>

    <!-- Tab 1: Today (Punch Screen) -->
    <main id="tab-today" class="tab-content active">
      <!-- Live Clock & Date -->
      <section class="live-clock-card">
        <div id="liveDateDisplay" class="live-date-label">Loading Date...</div>
        <div id="liveClockDisplay" class="live-time-display">--:--:--</div>
      </section>

      <!-- Shift Policy Rules Card -->
      <div class="policy-card">
        <div class="policy-header">
          <span class="policy-title">Shift Policy Window</span>
          <span class="policy-badge">Mon – Sat</span>
        </div>
        <div class="policy-items">
          <div class="policy-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span>Start: <strong>10:00 AM</strong></span>
          </div>
          <div class="policy-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>Grace: <strong>10:15 AM</strong></span>
          </div>
          <div class="policy-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 8 14"/></svg>
            <span>End: <strong>06:30 PM</strong></span>
          </div>
          <div class="policy-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            <span>Full Day: <strong>8.0 Hours</strong></span>
          </div>
        </div>
      </div>

      <!-- Tactile Punch Button Container -->
      <div class="punch-container">
        <div id="punchOuterRing" class="punch-outer-ring state-checkin">
          <button id="punchMainBtn" class="punch-btn state-checkin" type="button">
            <!-- Icon -->
            <svg class="punch-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M12 2v10M18.4 6.6a9 9 0 1 1-12.77.01"/>
            </svg>
            <span id="punchBtnTitle" class="punch-btn-title">CHECK IN</span>
            <span id="punchBtnHint" class="punch-btn-hint">Tap to Clock In</span>
          </button>
        </div>

        <!-- Live Status Pill & Timer -->
        <div class="status-pill-banner">
          <div id="todayStatusBadge" class="status-badge-lg badge-muted">
            <span class="status-dot"></span> Loading Status...
          </div>
          <div id="elapsedTimeCounter" class="elapsed-time-counter">Ready for today's shift</div>
        </div>
      </div>

      <!-- Quick Metrics Row -->
      <div class="metrics-row">
        <div class="metric-card">
          <div id="statStreakVal" class="metric-val emerald">--</div>
          <div class="metric-lbl">Attendance Streak</div>
        </div>
        <div class="metric-card">
          <div id="statOnTimeRate" class="metric-val amber">--</div>
          <div class="metric-lbl">On-Time Rate</div>
        </div>
        <div class="metric-card">
          <div id="statMonthlyHours" class="metric-val">--</div>
          <div class="metric-lbl">Total Hours</div>
        </div>
      </div>
    </main>

    <!-- Tab 2: Monthly Log & Dispute Reports -->
    <main id="tab-monthly" class="tab-content">
      <!-- Month Selector Header -->
      <div class="month-selector-bar">
        <button id="prevMonthBtn" class="month-nav-btn" type="button" aria-label="Previous Month">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <span id="currentMonthDisplay" class="month-title">--</span>
        <button id="nextMonthBtn" class="month-nav-btn" type="button" aria-label="Next Month">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
      </div>

      <!-- Monthly 4-Metric Grid -->
      <div class="metrics-row" style="grid-template-columns: repeat(4, 1fr); margin-top: 0; margin-bottom: 16px;">
        <div class="metric-card">
          <div id="sumPresent" class="metric-val emerald">--</div>
          <div class="metric-lbl">Present</div>
        </div>
        <div class="metric-card">
          <div id="sumLate" class="metric-val amber">--</div>
          <div class="metric-lbl">Late</div>
        </div>
        <div class="metric-card">
          <div id="sumHalfDay" class="metric-val amber">--</div>
          <div class="metric-lbl">Half-Day</div>
        </div>
        <div class="metric-card">
          <div id="sumAbsent" class="metric-val rose">--</div>
          <div class="metric-lbl">Absent</div>
        </div>
      </div>

      <!-- Discrepancy Claims Alert Banner -->
      <div id="discrepancyBanner" class="discrepancy-banner" style="display:none;">
        <div class="discrepancy-banner-top">
          <div class="discrepancy-icon-wrap">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          </div>
          <div class="discrepancy-banner-info">
            <h4 id="discrepancyCountText">Discrepancy Flags Identified</h4>
            <p>Export your audit proof to counter company discrepancies.</p>
          </div>
        </div>
        <a id="exportCsvBtn" class="export-btn" href="api/export.php" download>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          <span>Export Proof Report (CSV)</span>
        </a>
      </div>

      <!-- Daily Attendance Timeline -->
      <div class="timeline-section-title">Daily Attendance Timeline</div>
      <div id="attendanceList" class="attendance-list">
        <!-- Rows dynamically injected via JS -->
      </div>
    </main>

    <!-- Bottom Navigation Bar -->
    <nav class="bottom-nav">
      <button class="nav-item active" data-tab="today" type="button">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <span>Today</span>
      </button>
      <button class="nav-item" data-tab="monthly" type="button">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span>Monthly Log</span>
      </button>
    </nav>
  </div>

  <!-- Modal 1: Confirmation Prompt (Safeguard against accidental taps) -->
  <div id="punchConfirmModal" class="modal-backdrop">
    <div class="bottom-sheet">
      <div class="sheet-handle"></div>
      <div id="punchModalTitle" class="sheet-title">Confirm Action</div>
      <div id="punchModalSub" class="sheet-subtitle">Please verify your punch details.</div>
      
      <div class="sheet-info-box">
        <div class="sheet-info-row">
          <span class="label">Server Timestamp:</span>
          <span id="punchModalTime" class="val">--:--:--</span>
        </div>
        <div class="sheet-info-row">
          <span class="label">Shift Rule:</span>
          <span id="punchModalPolicy" class="val">10:00 AM (15m Grace)</span>
        </div>
      </div>

      <div class="sheet-actions">
        <button id="confirmPunchBtn" class="sheet-btn-primary" type="button">Confirm</button>
        <button id="cancelPunchBtn" class="sheet-btn-cancel" type="button">Cancel</button>
      </div>
    </div>
  </div>

  <!-- Modal 2: Discrepancy Evidence Drawer (Detailed proof to show HR) -->
  <div id="proofDrawerModal" class="modal-backdrop">
    <div class="bottom-sheet">
      <div class="sheet-handle"></div>
      <div class="sheet-title">Dispute Evidence Record</div>
      <div id="proofDrawerDate" class="sheet-subtitle">--</div>

      <div class="sheet-info-box">
        <div class="sheet-info-row">
          <span class="label">Recorded Check-In:</span>
          <span id="proofCheckIn" class="val">--</span>
        </div>
        <div class="sheet-info-row">
          <span class="label">Recorded Check-Out:</span>
          <span id="proofCheckOut" class="val">--</span>
        </div>
        <div class="sheet-info-row">
          <span class="label">Total Worked Duration:</span>
          <span id="proofDuration" class="val">--</span>
        </div>
        <div class="sheet-info-row">
          <span class="label">System Classification:</span>
          <span id="proofStatus" class="val">--</span>
        </div>
      </div>

      <div id="proofDiscrepancyBox" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 14px; padding: 12px; margin-bottom: 16px;">
        <div style="font-size: 0.75rem; font-weight: 700; color: #fbbf24; text-transform: uppercase; margin-bottom: 4px;">Audit Dispute Note:</div>
        <div id="proofDiscrepancyReason" style="font-size: 0.85rem; color: #f8fafc;">--</div>
      </div>

      <div class="sheet-actions">
        <button id="closeProofDrawerBtn" class="sheet-btn-cancel" type="button">Done / Close</button>
      </div>
    </div>
  </div>

  <!-- Toast Notification Container -->
  <div id="toastContainer" class="toast-container"></div>

  <script src="assets/js/app.js"></script>
</body>
</html>
