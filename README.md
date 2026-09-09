# Attendie ⏱️

> **A mobile-first, personal attendance verification system built for employees to maintain indisputable proof of their work hours and counter employer payroll discrepancies.**

---

## 🎯 Why I Built It (The Use Case)

At many startups and fast-paced companies, employee attendance is tracked by automated biometric machines, access card scanners, or corporate HR software. Unfortunately, these systems frequently suffer from **month-end calculation discrepancies**:

- **False Late Marks**: Arriving within the company's 15-minute grace window (e.g. 10:04 AM for a 10:00 AM shift) but getting marked "Late" due to biometric machine queue delays or clock-sync drift.
- **Unwarranted Half-Days**: Completing an entire 8-hour workday, only to have the system log an incomplete exit or drop a record, cutting pay by half a day.
- **Phantom Absences**: Missing records being automatically flagged as unapproved absences.
- **Zero Employee Leverage**: When month-end payroll discrepancies arise, HR asks for proof. Without an independent, verifiable personal log, the employee has no factual ground to argue and is forced to accept salary deductions or policy warnings.

**Attendie was built to solve this exact problem.**

It gives employees an ultra-fast, single-tap personal verification app on their mobile phone. It records immutable server-timestamped check-in and check-out events, evaluates them against startup shift policies in real time, and produces a concrete **Discrepancy Proof Report** to present to HR and management.

---

## ✨ Key Capabilities

- **⚡ One-Tap Real-Time Punching**:
  Arrive at the office, tap once, and your arrival is sealed with an immutable server timestamp. No tedious form filling.
- **🛡️ Accidental Tap Safeguard**:
  A rapid two-step confirmation bottom-sheet (`Confirm Check-In at 10:04 AM? [Confirm] / [Cancel]`) prevents accidental pocket or glance taps from locking incorrect timestamps.
- **⏱️ Live Active Shift Stopwatch**:
  Once checked in, the home screen transforms into an active shift telemetry hub displaying a live elapsed timer (`HH:MM:SS`) and your current shift progress.
- **📊 Real-Time Shift Policy Rules**:
  Punches are automatically classified against standard corporate rules:
  - **Shift Start**: 10:00 AM
  - **Grace Cutoff**: 10:15 AM ($\le$ 10:15 AM is **On-Time**; $>$ 10:15 AM is flagged **Late** with exact minutes calculated)
  - **Target End**: 6:30 PM
  - **Full Day**: $\ge$ 8.0 hours worked
  - **Half Day**: $\ge$ 4.0 hours worked ($<$ 4.0 hours marked Incomplete)
  - **Work Week**: Monday through Saturday (Sunday is recognized as a scheduled rest day)
- **🔍 Discrepancy Claims Banner & Evidence Drawer**:
  The Monthly Log view flags any discrepancy (late arrivals, half-days, un-punched workdays). Tapping any day opens an **Audit Evidence Drawer** detailing exact timestamps vs. policy criteria to present to HR during disputes.
- **📥 One-Click CSV Proof Export**:
  Download a formatted audit report (`attendie_report_YYYY-MM.csv`) directly from your phone to email or attach to an HR ticket.
- **🌙 Obsidian Kinetic Dark Theme**:
  Engineered with high-contrast tactile elements, OLED-optimized backgrounds (`#0b0f19`), and glowing emerald/amber status indicators designed for rapid outdoor or office mobile viewing.

---

## 🏗️ Architecture & Engineering Highlights

Attendie is engineered with **deep module architecture** and zero framework bloat:

```
attendie/
├── api/                   # Thin HTTP delegation endpoints
│   ├── today.php          # 1-line delegation -> $attendanceTracker->today()
│   ├── punch.php          # 1-line delegation -> $attendanceTracker->punch()
│   ├── history.php        # 1-line delegation -> $attendanceTracker->monthlyReport()
│   └── export.php         # 1-line delegation -> CSV stream from monthlyReport()
├── assets/
│   ├── css/
│   │   └── style.css      # Obsidian Kinetic design system (Vanilla CSS)
│   └── js/
│       ├── store.js       # Deep Headless Store (Zero DOM, reactive observer)
│       └── app.js         # Decoupled View Adapter (DOM & event handler)
├── config/
│   └── db.php             # Auto-migrating MySQL PDO connection & timezone
├── src/
│   └── AttendanceTracker.php # Deep Domain Module (Workday lifecycle & policies)
├── index.php              # Mobile-first PWA-ready HTML entrypoint
├── CONTEXT.md             # Domain model & ubiquitous language glossary
└── SPEC.md                # 15 detailed user stories & technical spec
```

### Architectural Principles Applied:
1. **Deep Backend Module (`src/AttendanceTracker.php`)**:
   All database queries, state machine transitions (Not Checked In $\rightarrow$ Checked In $\rightarrow$ Completed), and policy calculations are encapsulated behind a minimal 3-method interface (`today()`, `punch()`, `monthlyReport()`). API endpoints are clean one-line adapters.
2. **Headless Frontend Store (`assets/js/store.js`)**:
   Timekeeping intervals, clock syncing, and data caching live in a reactive, headless `AttendanceStore` with zero DOM coupling. The UI (`assets/js/app.js`) acts strictly as a view adapter across the seam.
3. **Zero Build Step & Total Portability**:
   Built with vanilla HTML5, CSS3, ES6, and native PHP with PDO. Runs out-of-the-box in local Laragon and drops seamlessly into any cPanel / shared LAMP hosting without requiring node build pipelines or compilation.

---

## 🚀 Getting Started

### Prerequisites
- **Web Server**: Apache or Nginx (Laragon, XAMPP, or Linux LAMP)
- **PHP**: 7.4 or 8.x
- **MySQL**: 5.7+ or MariaDB 10.x+

### Local Setup (Laragon)
1. Clone or place the folder into your web root:
   ```bash
   c:\laragon\www\attendie
   ```
2. Ensure Apache and MySQL are running in Laragon.
3. Open your browser and navigate to:
   ```
   http://localhost/attendie/
   ```
   *The database (`attendie`) and all required tables (`attendance_logs`, `settings`) are automatically created on first visit!*

### Accessing on Mobile Phone (At Work)
To use Attendie as an actual mobile app on your smartphone while at the office:

1. **Option A: Local Wi-Fi (Same Network)**:
   - Find your computer's local IP address (`ipconfig` $\rightarrow$ e.g., `192.168.1.45`).
   - Open your mobile browser and navigate to `http://192.168.1.45/attendie/`.
   - Tap your browser's menu and select **"Add to Home Screen"** to install it as a full-screen app.
2. **Option B: Free Secure Tunnel (Cloudflare Tunnel or Ngrok)**:
   - Run `ngrok http 80` or use Cloudflare Tunnel to generate a public HTTPS link accessible from cellular mobile data anywhere.
3. **Option C: Deploy to cPanel / Web Host**:
   - Upload the project files to your domain's `public_html/attendie/` folder.
   - Configure database credentials in [`config/db.php`](config/db.php) (or set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` environment variables).

---

## 📋 Shift Policy Reference Table

| Rule | Configuration | Policy Classification |
| :--- | :--- | :--- |
| **Shift Start** | `10:00 AM` | Official expected start time |
| **Grace Window** | `+15 minutes` | Punches $\le$ `10:15:00 AM` classified as **`ON_TIME`** |
| **Late Cutoff** | `> 10:15 AM` | Classified as **`LATE`** with exact minutes past grace calculated |
| **Full-Day Minimum** | `8.0 Hours` | Duration $\ge$ 8.0h classified as **`FULL_DAY`** |
| **Half-Day Minimum** | `4.0 Hours` | Duration $\ge$ 4.0h but $<$ 8.0h classified as **`HALF_DAY`** |
| **Incomplete / Short**| `< 4.0 Hours` | Duration $<$ 4.0h flagged as **`INCOMPLETE`** |
| **Working Days** | Mon – Sat | Un-punched days marked **`ABSENT`** |
| **Weekly Rest Day** | Sunday | Automatically marked **`SCHEDULED OFF`** |

---

## 📄 License
Personal Project — Built for employee accountability and independent attendance verification.
