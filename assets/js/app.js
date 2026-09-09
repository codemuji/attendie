const PunchState = Object.freeze({
  NOT_CHECKED_IN: 'NOT_CHECKED_IN',
  CHECKED_IN: 'CHECKED_IN',
  COMPLETED: 'COMPLETED'
});

const App = {
  currentTab: 'today',
  todayState: null,
  selectedMonth: '',
  timerInterval: null,
  clockInterval: null,
  elapsedSeconds: 0,
  
  init() {
    this.initTabs();
    this.initClock();
    this.loadTodayData();
    this.initMonthSelector();
    this.bindEvents();
  },

  initTabs() {
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
      item.addEventListener('click', () => {
        const tab = item.dataset.tab;
        this.switchTab(tab);
      });
    });
  },

  switchTab(tab) {
    this.currentTab = tab;
    document.querySelectorAll('.nav-item').forEach(el => {
      el.classList.toggle('active', el.dataset.tab === tab);
    });
    document.querySelectorAll('.tab-content').forEach(el => {
      el.classList.toggle('active', el.id === `tab-${tab}`);
    });

    if (tab === 'monthly') {
      this.loadMonthlyData(this.selectedMonth);
    }
  },

  initClock() {
    const clockEl = document.getElementById('liveClockDisplay');
    const dateEl = document.getElementById('liveDateDisplay');

    const updateClock = () => {
      const now = new Date();
      if (clockEl) {
        clockEl.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      }
      if (dateEl) {
        dateEl.textContent = now.toLocaleDateString([], { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' });
      }
    };

    updateClock();
    this.clockInterval = setInterval(updateClock, 1000);
  },

  async loadTodayData() {
    try {
      const res = await fetch('api/today.php');
      const data = await res.json();
      if (!data.success) throw new Error(data.error || 'Failed to fetch today data');

      this.todayState = data;
      this.renderTodayState();
    } catch (err) {
      console.error(err);
      this.showToast(err.message, 'rose');
    }
  },

  renderTodayState() {
    const data = this.todayState;
    const ringEl = document.getElementById('punchOuterRing');
    const btnEl = document.getElementById('punchMainBtn');
    const btnTitle = document.getElementById('punchBtnTitle');
    const btnHint = document.getElementById('punchBtnHint');
    const badgeEl = document.getElementById('todayStatusBadge');
    const elapsedEl = document.getElementById('elapsedTimeCounter');
    const streakValEl = document.getElementById('statStreakVal');

    if (streakValEl) {
      streakValEl.textContent = `${data.streak} Days`;
    }

    // Reset timer
    if (this.timerInterval) {
      clearInterval(this.timerInterval);
      this.timerInterval = null;
    }

    if (data.state === PunchState.NOT_CHECKED_IN) {
      ringEl.className = 'punch-outer-ring state-checkin';
      btnEl.className = 'punch-btn state-checkin';
      btnTitle.textContent = 'CHECK IN';
      btnHint.textContent = 'Tap to Clock In';
      badgeEl.className = 'status-badge-lg badge-muted';
      badgeEl.innerHTML = '<span class="status-dot"></span> Not Checked In Yet';
      elapsedEl.textContent = 'Ready for today\'s shift';
    } else if (data.state === PunchState.CHECKED_IN) {
      ringEl.className = 'punch-outer-ring state-checkout';
      btnEl.className = 'punch-btn state-checkout';
      btnTitle.textContent = 'CHECK OUT';
      btnHint.textContent = 'Tap to Clock Out';
      
      const inTimeStr = new Date(data.today_log.check_in).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      const isLate = data.today_log.status_in === 'LATE';
      const badgeClass = isLate ? 'badge-amber' : 'badge-emerald';
      const badgeText = isLate ? `In at ${inTimeStr} • LATE (+${data.today_log.late_minutes}m)` : `In at ${inTimeStr} • ON-TIME`;

      badgeEl.className = `status-badge-lg ${badgeClass}`;
      badgeEl.innerHTML = `<span class="status-dot"></span> ${badgeText}`;

      // Start elapsed timer
      this.elapsedSeconds = data.elapsed_seconds;
      this.renderElapsedTimer(elapsedEl);
      this.timerInterval = setInterval(() => {
        this.elapsedSeconds++;
        this.renderElapsedTimer(elapsedEl);
      }, 1000);
    } else if (data.state === PunchState.COMPLETED) {
      ringEl.className = 'punch-outer-ring state-completed';
      btnEl.className = 'punch-btn state-completed';
      btnTitle.textContent = 'DONE';
      btnHint.textContent = 'Locked for Today';

      const sec = data.today_log.worked_seconds;
      const h = Math.floor(sec / 3600);
      const m = Math.floor((sec % 3600) / 60);

      const statusDay = data.today_log.status_day;
      let dayBadgeClass = 'badge-emerald';
      if (statusDay === 'HALF_DAY') dayBadgeClass = 'badge-amber';
      if (statusDay === 'INCOMPLETE') dayBadgeClass = 'badge-rose';

      badgeEl.className = `status-badge-lg ${dayBadgeClass}`;
      badgeEl.innerHTML = `<span class="status-dot"></span> Completed • ${statusDay.replace('_', ' ')}`;
      elapsedEl.textContent = `Total Worked: ${h}h ${m}m today`;
    }
  },

  renderElapsedTimer(el) {
    const h = Math.floor(this.elapsedSeconds / 3600);
    const m = Math.floor((this.elapsedSeconds % 3600) / 60);
    const s = this.elapsedSeconds % 60;
    const pad = (n) => String(n).padStart(2, '0');
    el.textContent = `Active Shift: ${pad(h)}:${pad(m)}:${pad(s)}`;
  },

  bindEvents() {
    const punchBtn = document.getElementById('punchMainBtn');
    punchBtn.addEventListener('click', () => {
      this.handlePunchClick();
    });

    // Confirmation Modal buttons
    document.getElementById('confirmPunchBtn').addEventListener('click', () => {
      this.executePunch();
    });

    document.getElementById('cancelPunchBtn').addEventListener('click', () => {
      this.closeModal('punchConfirmModal');
    });

    // Drawer close buttons
    document.getElementById('closeProofDrawerBtn').addEventListener('click', () => {
      this.closeModal('proofDrawerModal');
    });

    // Close on backdrop click
    document.querySelectorAll('.modal-backdrop').forEach(modal => {
      modal.addEventListener('click', (e) => {
        if (e.target === modal) {
          modal.classList.remove('active');
        }
      });
    });
  },

  handlePunchClick() {
    const state = this.todayState ? this.todayState.state : PunchState.NOT_CHECKED_IN;
    if (state === PunchState.COMPLETED) {
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

    if (state === PunchState.NOT_CHECKED_IN) {
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
      const h = Math.floor(this.elapsedSeconds / 3600);
      const m = Math.floor((this.elapsedSeconds % 3600) / 60);
      modalPolicy.textContent = `Elapsed duration: ${h}h ${m}m (Target: 8.0h Full Day)`;
      confirmBtn.textContent = 'Confirm Check-Out';
      confirmBtn.style.background = 'var(--color-amber)';
    }

    this.openModal('punchConfirmModal');
  },

  async executePunch() {
    this.closeModal('punchConfirmModal');
    try {
      const res = await fetch('api/punch.php', {
        method: 'POST'
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.error || 'Failed to record punch');

      this.showToast(data.message, data.action === 'CHECK_IN' ? 'emerald' : 'amber');
      await this.loadTodayData();
      if (this.currentTab === 'monthly') {
        this.loadMonthlyData(this.selectedMonth);
      }
    } catch (err) {
      console.error(err);
      this.showToast(err.message, 'rose');
    }
  },

  initMonthSelector() {
    const now = new Date();
    const yyyy = now.getFullYear();
    const mm = String(now.getMonth() + 1).padStart(2, '0');
    this.selectedMonth = `${yyyy}-${mm}`;

    document.getElementById('prevMonthBtn').addEventListener('click', () => {
      this.adjustMonth(-1);
    });
    document.getElementById('nextMonthBtn').addEventListener('click', () => {
      this.adjustMonth(1);
    });
  },

  adjustMonth(delta) {
    const [y, m] = this.selectedMonth.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    this.selectedMonth = `${yyyy}-${mm}`;
    this.loadMonthlyData(this.selectedMonth);
  },

  async loadMonthlyData(monthStr) {
    try {
      const res = await fetch(`api/history.php?month=${monthStr}`);
      const data = await res.json();
      if (!data.success) throw new Error(data.error || 'Failed to fetch history');

      document.getElementById('currentMonthDisplay').textContent = data.month_name;
      document.getElementById('exportCsvBtn').href = `api/export.php?month=${monthStr}`;

      // Render summary cards
      document.getElementById('sumPresent').textContent = data.summary.total_present;
      document.getElementById('sumLate').textContent = data.summary.total_late;
      document.getElementById('sumHalfDay').textContent = data.summary.total_half_day;
      document.getElementById('sumAbsent').textContent = data.summary.total_absent;

      // Update home screen metrics
      const monthlyHoursEl = document.getElementById('statMonthlyHours');
      if (monthlyHoursEl) {
        monthlyHoursEl.textContent = data.summary.total_worked_formatted;
      }
      const onTimeRateEl = document.getElementById('statOnTimeRate');
      if (onTimeRateEl) {
        const total = data.summary.total_present;
        const onTime = total - data.summary.total_late;
        const rate = total > 0 ? ((onTime / total) * 100).toFixed(0) : '100';
        onTimeRateEl.textContent = `${rate}%`;
      }

      // Discrepancy banner
      const bannerEl = document.getElementById('discrepancyBanner');
      const discCountEl = document.getElementById('discrepancyCountText');
      if (data.summary.discrepancy_count > 0) {
        bannerEl.style.display = 'flex';
        discCountEl.textContent = `${data.summary.discrepancy_count} Discrepancy Flag(s) Identified`;
      } else {
        bannerEl.style.display = 'none';
      }

      // Render list
      this.renderTimeline(data.days);
    } catch (err) {
      console.error(err);
      this.showToast(err.message, 'rose');
    }
  },

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

      row.addEventListener('click', () => {
        this.openProofDrawer(day);
      });

      listEl.appendChild(row);
    });
  },

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
  },

  openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.add('active');
  },

  closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.remove('active');
  },

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
};

document.addEventListener('DOMContentLoaded', () => {
  App.init();
});
