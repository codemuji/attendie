# Attendie Specification

## Problem Statement

As an employee at a startup, my attendance and working hours are tracked by an internal system that frequently suffers from calculation discrepancies at the end of the month. The company's tracking system often records inaccurate check-in and check-out times, incorrectly marking me as Late (past the 10:15 AM grace cutoff), Half-Day (under 4.0 or 8.0 hours), or Absent. Because I have had no verifiable, independent record of the exact timestamps when I arrived and departed, I lack concrete evidence to dispute these erroneous marks with management and HR.

## Solution

Attendie is a lightweight, mobile-first personal attendance verification web app. It allows me to log an indisputable Check-In and Check-Out timestamp in a single tap on my mobile device as soon as I arrive at or leave the office. Attendie compares my actual punch timestamps against the startup's shift policy (10:00 AM shift start, 15-minute grace period until 10:15 AM, 6:30 PM shift end, 8.0 hours Full Day, 4.0 hours Half Day, Monday-Saturday schedule) and computes real-time status badges (On-Time, Late, Full Day, Half Day, Absent). It provides an on-screen monthly report and dispute evidence view to quickly demonstrate my actual work history to HR.

## User Stories

1. As an employee arriving at work, I want to open Attendie on my phone and see the current live date and time, so that I know the exact server time before punching.
2. As an employee arriving at work, I want a prominent, one-tap "Check In" button that records the real-time server timestamp, so that I can log my arrival in under two seconds.
3. As a user tapping "Check In", I want a swift confirmation modal displaying the exact recorded timestamp (e.g. "Confirm Check-In at 10:04 AM?"), so that I don't accidentally log an erroneous punch with an accidental pocket tap.
4. As an employee who arrives between 10:00 AM and 10:15 AM, I want Attendie to label my check-in as "On-Time" under the 15-minute grace window, so that I know my attendance is compliant with company policy.
5. As an employee who arrives after 10:15 AM, I want Attendie to label my check-in as "Late" with the exact minutes past grace (e.g. "+7m Late"), so that I know when a late mark by the company is valid or when it is disputed.
6. As an active worker during the day, I want the home screen to display an active shift counter showing elapsed hours and minutes since check-in, so that I know how much time I have worked today.
7. As an employee preparing to leave work, I want the action button to dynamically switch to a high-visibility "Check Out" button, so that I can clock out with one tap.
8. As a user tapping "Check Out", I want a confirmation modal showing my total hours worked today, so that I am certain I have fulfilled my 8-hour requirement before locking my departure.
9. As an employee who has completed both punches for the day, I want the button to lock into a "Completed" state, so that duplicate or accidental punches cannot overwrite my valid record.
10. As an employee checking my attendance at month-end, I want a dedicated "Monthly Log & Reports" screen, so that I can review my entire attendance calendar and daily timeline.
11. As an employee reviewing past days, I want every row to display the date, check-in time, check-out time, total duration, and color-coded status badges (On-Time, Late, Full Day, Half Day, Absent), so that I can scan discrepancies instantly.
12. As an employee working Monday through Saturday, I want un-punched workdays to be marked as "Absent" while Sundays are marked as "Scheduled Day Off", so that my attendance percentage is accurately calculated.
13. As an employee disputing a discrepancy with HR, I want to tap on any disputed day to view a dedicated "Discrepancy Proof & Evidence" drawer, so that I can show HR the exact verified timestamps and total duration on my screen.
14. As an employee needing an offline record, I want an "Export Proof Report" feature to download my monthly log as a CSV or formatted printable view, so that I can email it directly to HR.
15. As a mobile phone user, I want the web app to feature a dark mode aesthetic with high-contrast buttons and readable typography, so that it is frictionless to use in any lighting condition.

## Implementation Decisions

### Architecture & Tech Stack
- **Server Stack**: Standard PHP (version 7.4+ / 8.x compatible) running on Apache (native Laragon environment `c:\laragon\www\attendie` and direct drop-in compatibility for cPanel / LAMP hosting).
- **Database**: MySQL database (`attendie`) accessed via PHP PDO with prepared statements for security and stability.
- **Frontend**: Vanilla HTML5, CSS3, and modern vanilla JavaScript (ES6+). Zero bulky heavy frameworks; lightning-fast mobile loading and 60fps touch responsiveness.
- **Design System**: "Obsidian Kinetic" dark mode derived from Stitch MCP design system assets (`#0b0f19` canvas, `#111827` cards, `#10b981` emerald presence glow, `#f59e0b` amber alert, `#ef4444` rose absent tags, Plus Jakarta Sans typography).

### Database Schema

```sql
CREATE TABLE IF NOT EXISTS `attendance_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `log_date` DATE NOT NULL UNIQUE,
  `check_in` DATETIME DEFAULT NULL,
  `check_out` DATETIME DEFAULT NULL,
  `worked_seconds` INT DEFAULT 0,
  `status_in` ENUM('ON_TIME', 'LATE') DEFAULT 'ON_TIME',
  `status_day` ENUM('FULL_DAY', 'HALF_DAY', 'INCOMPLETE', 'ABSENT') DEFAULT 'INCOMPLETE',
  `late_minutes` INT DEFAULT 0,
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`log_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
  `key_name` VARCHAR(64) PRIMARY KEY,
  `key_value` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Shift Policy Constants (Default Seed)
- `shift_start_time`: `"10:00:00"`
- `grace_period_minutes`: `"15"` (Cutoff: 10:15:00)
- `shift_end_time`: `"18:30:00"`
- `full_day_hours`: `"8.0"`
- `half_day_hours`: `"4.0"`
- `work_days`: `"Mon,Tue,Wed,Thu,Fri,Sat"`

### API Endpoints
1. `GET api/today.php`: Returns today's punch state, server time, elapsed seconds, and policy.
2. `POST api/punch.php`: Handles Check-In (if no record today) or Check-Out (if checked-in but not checked-out). Uses atomic database operations and server timestamps.
3. `GET api/history.php?month=YYYY-MM`: Returns the full calendar matrix of days for the selected month, including worked logs, calculated statuses, totals (present days, late days, half-days, absences), and discrepancy highlights.
4. `GET api/export.php?month=YYYY-MM`: Generates a clean CSV file download formatted for HR dispute submission.

## Testing Decisions

- Test the server endpoints using automated PHP CLI / cURL requests.
- Verify status classification boundaries:
  - 10:00 AM -> ON_TIME
  - 10:15 AM -> ON_TIME
  - 10:16 AM -> LATE (+1m)
  - 8 hours 00 mins -> FULL_DAY
  - 4 hours 05 mins -> HALF_DAY
  - 3 hours 50 mins -> INCOMPLETE
- Verify database constraint: exactly 1 log per calendar date.
- Verify UI behavior in browser subagent / responsive mobile viewport.

## Out of Scope

- Multi-tenant enterprise organization roster management (Attendie is strictly tailored for personal employee sovereignty).
- Multiple break punch pairs per day (lunch in/out).
- Biometric hardware driver integration (uses mobile browser one-tap confirmation).

## Further Notes

- Full offline UI shell and responsive viewport meta tag configured for native Add to Home Screen (PWA manifest ready).
- Simple `db.php` configuration allows seamless database switching when moving from Laragon (`localhost`, root, blank password) to cPanel MySQL.
