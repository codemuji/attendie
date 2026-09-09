/**
 * Attendie - View Adapter & UI Interaction Layer
 * Subscribes to the headless AttendanceStore and renders DOM updates.
 */

class AttendieView {
  constructor(store) {
    this.store = store;
    this.currentTab = 'today';
    this.init();
  }

  init() {
    this.initTabs();
    this.bindEvents();
    
    // Subscribe view to headless store updates
    this.store.subscribe((state) => this.render(state));

    // Boot store
    this.store.init().catch(err => {
      this.showToast(err.message, 'rose');
    });
  }

  initTabs() {
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
      item.addEventListener('click', () => {
        const tab = item.dataset.tab;
        this.switchTab(tab);
      });
    });
  }

  switchTab(tab) {
    this.currentTab = tab;
    document.querySelectorAll('.nav-item').forEach(el => {
      el.classList.toggle('active', el.dataset.tab === tab);
    });
    document.querySelectorAll('.tab-content').forEach(el => {
      el.classList.toggle('active', el.id === `tab-${tab}`);
    });
  }

  bindEvents() {
    // Punch button click
    const punchBtn = document.getElementById('punchMainBtn');
    punchBtn.addEventListener('click', () => this.handlePunchClick());

    // Modal confirmation buttons
    document.getElementById('confirmPunchBtn').addEventListener('click', () => this.executePunch());
    document.getElementById('cancelPunchBtn').addEventListener('click', () => this.closeModal('punchConfirmModal'));

    // Drawer close buttons
    document.getElementById('closeProofDrawerBtn').addEventListener('click', () => this.closeModal('proofDrawerModal'));

    // Month Navigation
    document.getElementById('prevMonthBtn').addEventListener('click', () => this.store.adjustMonth(-1));
    document.getElementById('nextMonthBtn').addEventListener('click', () => this.store.adjustMonth(1));

    // Close on backdrop click
    document.querySelectorAll('.modal-backdrop').forEach(modal => {
      modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.remove('active');
      });
    });
  }

  handlePunchClick() {
    const { punchState, todayLog, elapsedSeconds } = this.store.getState();

    if (punchState === PunchState.COMPLETED) {
      this.showToast('Attendance for today is already completed and locked.', 'muted');
      return;
    }

    const modalTitle = document.getElementById('punchModalTitle');
    const modalSub = document.getElementById('punchModalSub');
    const modalTime = document.getElementById('punchModalTime');
    const modalPolicy = document.getElementById('punchModalPolicy');
    const confirmBtn = document.getElementById('confirmPunchBtn');

    const now = new Date();
    const timeFormatted = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });

    if (punchState === PunchState.NOT_CHECKED_IN) {
      modalTitle.textContent = 'Confirm Check-In';
      modalSub.textContent = 'Logging your arrival time for today\'s shift.';
      modalTime.textContent = timeFormatted;
      modalPolicy.textContent = 'Shift starts 10:00 AM (Grace cutoff: 10:15 AM)';
      confirmBtn.textContent = 'Confirm Check-In';
      confirmBtn.style.background = 'var(--color-primary)';
    } else {
      modalTitle.textContent = 'Confirm Check-Out';
      modalSub.textContent = 'Sealing your attendance record for today.';
      modalTime.textContent = timeFormatted;
      const h = Math.floor(elapsedSeconds / 3600);
      const m = Math.floor((elapsedSeconds % 3600) / 60);
      modalPolicy.textContent = `Elapsed duration: ${h}h ${m}m (Target: 8.0h Full Day)`;
      confirmBtn.textContent = 'Confirm Check-Out';
      confirmBtn.style.background = 'var(--color-amber)';
    }

    this.openModal('punchConfirmModal');
  }

  async executePunch() {
    this.closeModal('punchConfirmModal');
    try {
      const data = await this.store.punch();
      this.showToast(data.message, data.action === 'CHECK_IN' ? 'emerald' : 'amber');
    } catch (err) {
      this.showToast(err.message, 'rose');
    }
  }

  render(state) {
    // 1. Render Clock
    const clockEl = document.getElementById('liveClockDisplay');
    const dateEl = document.getElementById('liveDateDisplay');
    if (clockEl) clockEl.textContent = state.clockTime;
    if (dateEl) dateEl.textContent = state.clockDate;

    // 2. Render Today Screen State
    this.renderTodaySection(state);

    // 3. Render Monthly Screen State
    if (state.monthlyData) {
      this.renderMonthlySection(state);
    }
  }

  renderTodaySection(state) {
    const ringEl = document.getElementById('punchOuterRing');
    const btnEl = document.getElementById('punchMainBtn');
    const btnTitle = document.getElementById('punchBtnTitle');
    const btnHint = document.getElementById('punchBtnHint');
    const badgeEl = document.getElementById('todayStatusBadge');
    const elapsedEl = document.getElementById('elapsedTimeCounter');
    const streakValEl = document.getElementById('statStreakVal');

    if (streakValEl) streakValEl.textContent = `${state.streak} Days`;

    if (state.punchState === PunchState.NOT_CHECKED_IN) {
      ringEl.className = 'punch-outer-ring state-checkin';
      btnEl.className = 'punch-btn state-checkin';
      btnTitle.textContent = 'CHECK IN';
      btnHint.textContent = 'Tap to Clock In';
      badgeEl.className = 'status-badge-lg badge-muted';
      badgeEl.innerHTML = '<span class="status-dot"></span> Not Checked In Yet';
      elapsedEl.textContent = 'Ready for today\'s shift';
    } else if (state.punchState === PunchState.CHECKED_IN) {
      ringEl.className = 'punch-outer-ring state-checkout';
      btnEl.className = 'punch-btn state-checkout';
      btnTitle.textContent = 'CHECK OUT';
      btnHint.textContent = 'Tap to Clock Out';

      if (state.todayLog) {
        const inTimeStr = new Date(state.todayLog.check_in).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const isLate = state.todayLog.status_in === 'LATE';
        const badgeClass = isLate ? 'badge-amber' : 'badge-emerald';
        const badgeText = isLate ? `In at ${inTimeStr} • LATE (+${state.todayLog.late_minutes}m)` : `In at ${inTimeStr} • ON-TIME`;
        badgeEl.className = `status-badge-lg ${badgeClass}`;
        badgeEl.innerHTML = `<span class="status-dot"></span> ${badgeText}`;
      }

      elapsedEl.textContent = `Active Shift: ${state.elapsedFormatted}`;
    } else if (state.punchState === PunchState.COMPLETED) {
      ringEl.className = 'punch-outer-ring state-completed';
      btnEl.className = 'punch-btn state-completed';
      btnTitle.textContent = 'DONE';
      btnHint.textContent = 'Locked for Today';

      if (state.todayLog) {
        const sec = state.todayLog.worked_seconds;
        const h = Math.floor(sec / 3600);
        const m = Math.floor((sec % 3600) / 60);
        const statusDay = state.todayLog.status_day;
        let dayBadgeClass = 'badge-emerald';
        if (statusDay === 'HALF_DAY') dayBadgeClass = 'badge-amber';
        if (statusDay === 'INCOMPLETE') dayBadgeClass = 'badge-rose';

        badgeEl.className = `status-badge-lg ${dayBadgeClass}`;
        badgeEl.innerHTML = `<span class="status-dot"></span> Completed • ${statusDay.replace('_', ' ')}`;
        elapsedEl.textContent = `Total Worked: ${h}h ${m}m today`;
      }
    }
  }

  renderMonthlySection(state) {
    const data = state.monthlyData;
    document.getElementById('currentMonthDisplay').textContent = data.month_name;
    document.getElementById('exportCsvBtn').href = `api/export.php?month=${state.selectedMonth}`;

    // Summary Cards
    document.getElementById('sumPresent').textContent = data.summary.total_present;
    document.getElementById('sumLate').textContent = data.summary.total_late;
    document.getElementById('sumHalfDay').textContent = data.summary.total_half_day;
    document.getElementById('sumAbsent').textContent = data.summary.total_absent;

    // Home screen metrics
    const monthlyHoursEl = document.getElementById('statMonthlyHours');
    if (monthlyHoursEl) monthlyHoursEl.textContent = data.summary.total_worked_formatted;

    const onTimeRateEl = document.getElementById('statOnTimeRate');
    if (onTimeRateEl) {
      const total = data.summary.total_present;
      const onTime = total - data.summary.total_late;
      const rate = total > 0 ? ((onTime / total) * 100).toFixed(0) : '100';
      onTimeRateEl.textContent = `${rate}%`;
    }

    // Discrepancy Banner
    const bannerEl = document.getElementById('discrepancyBanner');
    const discCountEl = document.getElementById('discrepancyCountText');
    if (data.summary.discrepancy_count > 0) {
      bannerEl.style.display = 'flex';
      discCountEl.textContent = `${data.summary.discrepancy_count} Discrepancy Flag(s) Identified`;
    } else {
      bannerEl.style.display = 'none';
    }

    // Render daily list
    this.renderTimeline(data.days);
  }

  renderTimeline(days) {
    const listEl = document.getElementById('attendanceList');
    listEl.innerHTML = '';

    if (!days || days.length === 0) {
      listEl.innerHTML = '<div style="text-align:center; padding: 20px; color: var(--color-slate-light);">No records for this month.</div>';
      return;
    }

    days.forEach(day => {
      const row = document.createElement('div');
      row.className = `log-row ${day.discrepancy ? 'has-discrepancy' : ''}`;

      let timesHtml = '';
      if (day.is_sunday) {
        timesHtml = 'Weekly Scheduled Day Off';
      } else if (day.check_in) {
        timesHtml = `In: ${day.check_in} • Out: ${day.check_out || 'In Progress'}`;
      } else if (day.is_future) {
        timesHtml = 'Scheduled Workday';
      } else {
        timesHtml = 'No punch recorded';
      }

      const badgeColorClass = {
        emerald: 'badge-emerald',
        amber: 'badge-amber',
        rose: 'badge-rose',
        muted: 'badge-muted',
        blue: 'badge-blue'
      }[day.badge_type] || 'badge-muted';

      row.innerHTML = `
        <div class="log-left">
          <div class="log-date">${day.date_formatted}</div>
          <div class="log-times">${timesHtml}</div>
        </div>
        <div class="log-right">
          <div class="log-duration">${day.worked_formatted}</div>
          <div class="log-badge ${badgeColorClass}">${day.badge_label}</div>
        </div>
      `;

      row.addEventListener('click', () => this.openProofDrawer(day));
      listEl.appendChild(row);
    });
  }

  openProofDrawer(day) {
    document.getElementById('proofDrawerDate').textContent = `${day.day_name}, ${day.date}`;
    document.getElementById('proofCheckIn').textContent = day.check_in || 'None Recorded';
    document.getElementById('proofCheckOut').textContent = day.check_out || 'None Recorded';
    document.getElementById('proofDuration').textContent = day.worked_formatted;
    document.getElementById('proofStatus').textContent = day.badge_label;

    const disputeBox = document.getElementById('proofDiscrepancyBox');
    const disputeReason = document.getElementById('proofDiscrepancyReason');

    if (day.discrepancy && day.discrepancy_reason) {
      disputeBox.style.display = 'block';
      disputeReason.textContent = day.discrepancy_reason;
    } else {
      disputeBox.style.display = 'none';
    }

    this.openModal('proofDrawerModal');
  }

  openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.add('active');
  }

  closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.remove('active');
  }

  showToast(message, type = 'emerald') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast';
    
    let iconSvg = '';
    if (type === 'emerald') {
      iconSvg = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>`;
    } else if (type === 'amber') {
      iconSvg = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`;
    } else {
      iconSvg = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`;
    }

    toast.innerHTML = `${iconSvg} <span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(-20px)';
      toast.style.transition = 'all 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, 3200);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const store = new AttendanceStore();
  new AttendieView(store);
});
