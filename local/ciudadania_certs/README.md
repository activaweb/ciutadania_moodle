# CiutadanIA Certifications Plugin

A Moodle local plugin that creates certification snapshots when users complete payment activities, enabling progressive certification of course modules.

## What does it do?

This plugin allows you to offer **incremental certifications** in a course:

- Users complete modules progressively
- Each time they pay, a **snapshot** is created with the currently approved modules
- The free diploma always shows **all current approved modules** (dynamic)
- The official certificate shows **only modules from the last payment** (frozen snapshot)

## How it works

### 1. Automatic snapshot creation

When a user completes a **payment activity (URL)**, the plugin automatically:

1. Detects the completion via event observer
2. Gets all modules with grade ≥70/100 currently approved
3. Saves a snapshot in the database with:
   - User ID
   - Course ID
   - Payment activity ID
   - JSON with certified modules
   - Total hours (modules × 2)
   - Creation timestamp

### 2. Integration with customcert

The plugin integrates with the **approvedmodules** element of customcert, which now has two modes:

- **Current mode**: Shows all currently approved modules (for free diploma)
- **Certified mode**: Shows only modules from the last payment snapshot (for official certificate)

## Installation

The plugin installs automatically via Moodle's upgrade system:

```bash
php admin/cli/upgrade.php --non-interactive
```

This creates the table `mdlu8_ciudadania_certifications` with the following structure:

```sql
CREATE TABLE mdlu8_ciudadania_certifications (
  id BIGINT(10) PRIMARY KEY AUTO_INCREMENT,
  userid BIGINT(10) NOT NULL,
  courseid BIGINT(10) NOT NULL,
  paymentcmid BIGINT(10) NOT NULL,
  modules_json LONGTEXT NOT NULL,
  total_hours BIGINT(10) DEFAULT 0,
  total_modules BIGINT(10) DEFAULT 0,
  timecreated BIGINT(10) NOT NULL
);
```

## Course configuration

### Activity 1: Free Diploma (dynamic)

1. Create a **customcert** activity
2. Add restriction: Complete 4 mandatory modules
3. Add element **"Approved modules"**
   - Mode: **"Current approved modules (dynamic)"**
   - Show grades: Your choice

### Activity 2: Payment URL (single, reusable)

1. Create a **URL** activity
2. Point to your payment gateway
3. Add restrictions:
   - Complete 4 mandatory modules
   - 30 days since enrollment
   - DNI/NIE field not empty
4. **Important**: Name or URL must contain one of:
   - "pagament", "payment", "pago"
   - "certificat oficial"

### Activity 3: Official Certificate (single)

1. Create a **customcert** activity
2. Add restriction: Payment completed
3. Add element **"Approved modules"**
   - Mode: **"Last certified modules (from last payment)"** ⭐
   - Show grades: Your choice

## User flow example

```
Day 1: User completes 4 mandatory modules
   ↓
   Free diploma available (shows 4 modules, 8h)

Day 35: User completes 2 more modules (total 6)
   ↓
   Free diploma updates automatically (shows 6 modules, 12h)
   Payment URL becomes available
   ↓
   User pays (1st time)
   ↓
   Plugin creates snapshot: 6 modules, 12h
   Official certificate available (shows 6 modules, 12h)

Day 50: User completes 3 more modules (total 9)
   ↓
   Free diploma updates (shows 9 modules, 18h)
   Official certificate still shows 6 modules (frozen)
   ↓
   User pays (2nd time)
   ↓
   Plugin creates new snapshot: 9 modules, 18h
   Official certificate now shows 9 modules, 18h
```

## Useful SQL queries

### View all certifications

```sql
SELECT
    u.firstname, u.lastname, u.email,
    c.fullname as course,
    cc.total_modules, cc.total_hours,
    FROM_UNIXTIME(cc.timecreated) as payment_date,
    cc.modules_json
FROM mdlu8_ciudadania_certifications cc
JOIN mdlu8_user u ON cc.userid = u.id
JOIN mdlu8_course c ON cc.courseid = c.id
ORDER BY cc.timecreated DESC;
```

### View certification history for a specific user

```sql
SELECT
    total_modules, total_hours,
    FROM_UNIXTIME(timecreated) as payment_date,
    modules_json
FROM mdlu8_ciudadania_certifications
WHERE userid = ? AND courseid = ?
ORDER BY timecreated DESC;
```

### Count certifications per course

```sql
SELECT
    c.fullname,
    COUNT(*) as total_certifications,
    COUNT(DISTINCT cc.userid) as unique_users
FROM mdlu8_ciudadania_certifications cc
JOIN mdlu8_course c ON cc.courseid = c.id
GROUP BY c.id, c.fullname;
```

## Technical details

### Payment detection

The observer (`classes/observer.php`) listens to the event:
- `\core\event\course_module_completion_updated`

And creates a snapshot when:
1. Completion state is COMPLETE or COMPLETE_PASS
2. Module is of type URL
3. URL name or address contains payment keywords

### Snapshot Manager API

```php
// Create snapshot
$snapshotid = \local_ciudadania_certs\snapshot_manager::create_snapshot(
    $userid,
    $courseid,
    $paymentcmid
);

// Get last certified modules
$modules = \local_ciudadania_certs\snapshot_manager::get_certified_modules(
    $userid,
    $courseid
);

// Get all snapshots for a user
$snapshots = \local_ciudadania_certs\snapshot_manager::get_all_snapshots(
    $userid,
    $courseid
);

// Check if user has certifications
$has = \local_ciudadania_certs\snapshot_manager::has_certifications(
    $userid,
    $courseid
);
```

## Troubleshooting

### Snapshot not created after payment

1. Check if URL activity contains payment keywords
2. Verify completion is being tracked correctly
3. Check Moodle logs: `Site administration → Reports → Logs`
4. Look for: "Certification snapshot created for user X"

### Certificate shows no modules

1. Verify user has completed at least one payment
2. Check snapshot exists in database
3. Ensure customcert element is in "Certified" mode

### Want to manually create snapshot

```php
// Execute in Moodle CLI or scheduled task
require_once(__DIR__.'/config.php');
$userid = 123;    // User ID
$courseid = 5;    // Course ID
$paymentcmid = 54; // Payment activity CM ID

$result = \local_ciudadania_certs\snapshot_manager::create_snapshot(
    $userid,
    $courseid,
    $paymentcmid
);

echo "Snapshot created: " . $result;
```

## Version

- **Version:** 1.0
- **Requires:** Moodle 4.1+
- **Maturity:** STABLE

## Author

Created for the CiutadanIA project.

## License

GPL v3 or later
