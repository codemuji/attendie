/**
 * Attendie - Headless Telemetry & State Store
 * Manages real-time intervals, state invariants, and network sync with zero DOM coupling.
 */

const PunchState = Object.freeze({
  NOT_CHECKED_IN: 'NOT_CHECKED_IN',
  CHECKED_IN: 'CHECKED_IN',
  COMPLETED: 'COMPLETED'
});

class AttendanceStore {
  constructor(options = {}) {
    this.apiBase = options.apiBase || 'api';
    this.listeners = new Set();
    
    const now = new Date();
    const yyyy = now.getFullYear();
    const mm = String(now.getMonth() + 1).padStart(2, '0');

    this.state = {
      punchState: PunchState.NOT_CHECKED_IN,
      todayLog: null,
      elapsedSeconds: 0,
      elapsedFormatted: '00:00:00',
      clockTime: '--:--:--',
      clockDate: 'Loading Date...',
      settings: null,
      streak: 0,
      selectedMonth: `${yyyy}-${mm}`,
      monthlyData: null,
      isLoading: false,
      error: null
    };

    this.clockInterval = null;
    this.shiftTimerInterval = null;
  }

  /**
   * Subscribe to state updates. Returns unsubscribe function.
   */
  subscribe(listener) {
    this.listeners.add(listener);
    listener(this.getState());
    return () => this.listeners.delete(listener);
  }

  getState() {
    return { ...this.state };
  }

  privateNotify() {
    const currentState = this.getState();
    this.listeners.forEach(fn => {
      try {
        fn(currentState);
      } catch (err) {
        console.error('AttendanceStore listener error:', err);
      }
    });
  }

  setState(partial) {
    this.state = { ...this.state, ...partial };
    this.privateNotify();
  }

  /**
   * Initialize store: starts live clock and fetches initial today and monthly state
   */
  async init() {
    this.startClockTicker();
    await this.fetchToday();
    await this.fetchMonth(this.state.selectedMonth);
  }

  startClockTicker() {
    if (this.clockInterval) clearInterval(this.clockInterval);

    const tick = () => {
      const now = new Date();
      const clockTime = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      const clockDate = now.toLocaleDateString([], { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' });
      this.setState({ clockTime, clockDate });
    };

    tick();
    this.clockInterval = setInterval(tick, 1000);
  }

  startShiftTimer(initialSeconds) {
    if (this.shiftTimerInterval) clearInterval(this.shiftTimerInterval);
    
    let sec = initialSeconds;
    const formatTime = (s) => {
      const h = Math.floor(s / 3600);
      const m = Math.floor((s % 3600) / 60);
      const secRemain = s % 60;
      const pad = (n) => String(n).padStart(2, '0');
      return `${pad(h)}:${pad(m)}:${pad(secRemain)}`;
    };

    this.setState({ elapsedSeconds: sec, elapsedFormatted: formatTime(sec) });

    this.shiftTimerInterval = setInterval(() => {
      sec++;
      this.setState({ elapsedSeconds: sec, elapsedFormatted: formatTime(sec) });
    }, 1000);
  }

  stopShiftTimer() {
    if (this.shiftTimerInterval) {
      clearInterval(this.shiftTimerInterval);
      this.shiftTimerInterval = null;
    }
  }

  /**
   * Fetch today's punch state and shift rules
   */
  async fetchToday() {
    try {
      this.setState({ isLoading: true, error: null });
      const res = await fetch(`${this.apiBase}/today.php`);
      const data = await res.json();
      if (!data.success) throw new Error(data.error || 'Failed to fetch today attendance');

      let punchState = PunchState.NOT_CHECKED_IN;
      if (data.state === 'CHECKED_IN') punchState = PunchState.CHECKED_IN;
      if (data.state === 'COMPLETED') punchState = PunchState.COMPLETED;

      if (punchState === PunchState.CHECKED_IN) {
        this.startShiftTimer(data.elapsed_seconds || 0);
      } else {
        this.stopShiftTimer();
      }

      this.setState({
        punchState,
        todayLog: data.today_log,
        settings: data.settings,
        streak: data.streak,
        elapsedSeconds: data.elapsed_seconds || 0,
        isLoading: false
      });
    } catch (err) {
      this.setState({ error: err.message, isLoading: false });
      throw err;
    }
  }

  /**
   * Execute atomic Check-In or Check-Out
   */
  async punch() {
    try {
      this.setState({ isLoading: true, error: null });
      const res = await fetch(`${this.apiBase}/punch.php`, { method: 'POST' });
      const data = await res.json();
      if (!data.success) throw new Error(data.error || 'Punch request failed');

      // Refresh both today and monthly data to maintain sync
      await this.fetchToday();
      await this.fetchMonth(this.state.selectedMonth);

      return data;
    } catch (err) {
      this.setState({ error: err.message, isLoading: false });
      throw err;
    }
  }

  /**
   * Set and fetch attendance matrix for a given month (YYYY-MM)
   */
  async fetchMonth(monthStr) {
    try {
      this.setState({ selectedMonth: monthStr, isLoading: true, error: null });
      const res = await fetch(`${this.apiBase}/history.php?month=${monthStr}`);
      const data = await res.json();
      if (!data.success) throw new Error(data.error || 'Failed to load month report');

      this.setState({
        monthlyData: data,
        isLoading: false
      });
      return data;
    } catch (err) {
      this.setState({ error: err.message, isLoading: false });
      throw err;
    }
  }

  /**
   * Adjust active month by a delta (+1 or -1)
   */
  async adjustMonth(delta) {
    const [y, m] = this.state.selectedMonth.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    return this.fetchMonth(`${yyyy}-${mm}`);
  }

  destroy() {
    if (this.clockInterval) clearInterval(this.clockInterval);
    if (this.shiftTimerInterval) clearInterval(this.shiftTimerInterval);
    this.listeners.clear();
  }
}

// Attach to window for global access
window.PunchState = PunchState;
window.AttendanceStore = AttendanceStore;
