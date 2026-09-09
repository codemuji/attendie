# Domain Model: Attendie

Attendie is a personal attendance verification system designed to provide indisputable records of daily work hours and dispute startup attendance discrepancies.

## Glossary

### Shift Policy
The set of corporate attendance rules used as the baseline for evaluating daily attendance performance.
- **Scheduled Start Time**: 10:00 AM.
- **Grace Window**: 15 minutes (arrival at or before 10:15 AM is classified as **On-Time**; arrival after 10:15 AM is classified as **Late**).
- **Scheduled End Time**: 6:30 PM.
- **Full-Day Minimum**: 8.0 hours of total worked duration.
- **Half-Day Minimum**: 4.0 hours of total worked duration (under 4.0 hours is marked as Incomplete/Absent).
- **Working Week**: Monday through Saturday (Sunday is the sole scheduled weekly day off). Un-punched Monday-Saturday workdays are flagged as **Absent**.

### Punch Safeguard & Modal Confirmation
- A two-step intentional interaction: tapping the primary punch button triggers a momentary prompt with explicit time and action (`Confirm Check-In at 10:04 AM? [Confirm] / [Cancel]`), preventing inadvertent pocket or glance taps from locking permanent timestamps.

### Workday
A calendar date representing a work period.
- **Active Workday**: A day currently in progress where a Check-In has been recorded but not yet checked out.
- **Completed Workday**: A day where both Check-In and Check-Out timestamps have been sealed.
- **Absent Day**: A scheduled working day where no punches were recorded.

### Punch
A strictly immutable, timestamped event recorded via a single tap on the mobile screen.
- **Check-In**: The opening punch marking the user's arrival at work for the day.
- **Check-Out**: The closing punch marking the user's departure for the day.
- **Punch Invariance**: Punches capture the real-time server timestamp at the instant of button activation. No manual time manipulation or retroactive additions.

### Work Session
The interval between the daily Check-In and Check-Out. Exactly one session is permitted per calendar day.
- **Worked Duration**: The elapsed time between Check-In and Check-Out.

### Attendance Classification / Status
The calculated evaluation of a workday based on Punch timestamps compared against the Shift Policy:
- **On-Time**: Check-In <= 10:15 AM.
- **Late**: Check-In > 10:15 AM.
- **Full Day**: Worked Duration >= 8.0 hours.
- **Half Day**: 4.0 hours <= Worked Duration < 8.0 hours.
- **Under Hours**: Check-Out earlier than policy or total duration insufficient.

### Discrepancy Evidence Record
The monthly visual breakdown showing actual timestamps, total hours, and status badges. Serves as personal proof to challenge inaccurate employer payroll or attendance reports.
