<?php
session_start();
require_once "database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$registration_labels = [];
$registration_values = [];
$registration_result = $conn->query(
    "SELECT DATE(registration_date) AS registration_day, COUNT(*) AS total
     FROM attendees GROUP BY DATE(registration_date) ORDER BY registration_day"
);

$running_total = 0;
while ($row = $registration_result->fetch_assoc()) {
    $registration_labels[] = date("M j", strtotime($row["registration_day"]));
    $running_total += (int) $row["total"];
    $registration_values[] = $running_total;
}

$first_value = $registration_values[0] ?? 0;
$last_value = $registration_values[count($registration_values) - 1] ?? 0;
$trend_change = $last_value - $first_value;
$trend_label = $trend_change > 0 ? "up" : ($trend_change < 0 ? "down" : "steady");
$chart_points = [];
$chart_max = max($last_value, 1);
$chart_count = count($registration_values);
foreach ($registration_values as $index => $value) {
    $x = $chart_count > 1 ? 48 + ($index * (694 / ($chart_count - 1))) : 395;
    $y = 212 - (($value / $chart_max) * 180);
    $chart_points[] = ["x" => round($x, 2), "y" => round($y, 2), "value" => $value, "label" => $registration_labels[$index]];
}

$attendance = $conn->query(
    "SELECT a.id, a.registration_number, a.first_name, a.middle_name, a.last_name,
            COUNT(d.id) AS days_attended,
            SUM(CASE WHEN s.day_number = 1 THEN 1 ELSE 0 END) AS day_1,
            SUM(CASE WHEN s.day_number = 2 THEN 1 ELSE 0 END) AS day_2,
            SUM(CASE WHEN s.day_number = 3 THEN 1 ELSE 0 END) AS day_3,
            SUM(CASE WHEN s.day_number = 4 THEN 1 ELSE 0 END) AS day_4,
            SUM(CASE WHEN s.day_number = 5 THEN 1 ELSE 0 END) AS day_5,
            SUM(CASE WHEN s.day_number = 6 THEN 1 ELSE 0 END) AS day_6,
            SUM(CASE WHEN s.day_number = 7 THEN 1 ELSE 0 END) AS day_7
     FROM attendees a
     LEFT JOIN daily_checkins d ON d.attendee_id = a.id
     LEFT JOIN summit_days s ON s.id = d.summit_day_id
     GROUP BY a.id
     ORDER BY a.registration_date DESC"
);

$day_counts = array_fill(1, 7, 0);
$day_result = $conn->query(
    "SELECT s.day_number, COUNT(d.id) AS total
     FROM summit_days s LEFT JOIN daily_checkins d ON d.summit_day_id = s.id
     GROUP BY s.day_number ORDER BY s.day_number"
);
while ($row = $day_result->fetch_assoc()) {
    $day_counts[(int) $row["day_number"]] = (int) $row["total"];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports | MUSDAA Medical Camp</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="reports-page-body">
<header class="topbar">
    <a class="topbar-brand" href="dashboard.php">MUSDAA <span>Medical Camp</span></a>
    <nav class="topbar-nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="registration/register.php">Register attendee</a>
        <a href="checkin/checkin.php">Daily check-in</a>
        <a href="service_records.php">Medical services</a>
        <a class="active" href="reports.php" aria-current="page">Reports</a>
        <a class="logout-link" href="logout.php">Sign out</a>
    </nav>
</header>
<main class="dashboard reports-page">
    <div class="dashboard-heading">
        <div><a class="history-back" href="index.php" onclick="if (window.history.length > 1) { window.history.back(); return false; }">Back</a><p class="eyebrow">Reports and analytics</p><h1>Camp performance</h1><p class="muted-copy">Track registration growth and seven-day attendance at a glance.</p></div>
    </div>

    <section class="stat-grid report-stats">
        <div class="stat-card report-primary"><span class="report-stat-label"><i class="report-icon" aria-hidden="true">&#128100;</i>Total registered</span><strong><?= $last_value ?></strong><small>All registrations</small></div>
        <div class="stat-card"><span class="report-stat-label"><i class="report-icon" aria-hidden="true">&#8593;</i>Registration trend</span><strong class="trend-<?= $trend_label ?>"><?= $trend_change > 0 ? "+" : "" ?><?= $trend_change ?> <small class="trend-arrow" aria-hidden="true">&#8593;</small></strong><small>Since first registration day</small></div>
        <div class="stat-card"><span class="report-stat-label"><i class="report-icon" aria-hidden="true">&#10003;</i>Day 1 check-ins</span><strong><?= $day_counts[1] ?></strong><small>Attendance recorded</small></div>
        <div class="stat-card"><span class="report-stat-label"><i class="report-icon" aria-hidden="true">&#10003;</i>Day 7 check-ins</span><strong><?= $day_counts[7] ?></strong><small>Attendance recorded</small></div>
    </section>

    <section class="dashboard-panel chart-panel">
        <div class="panel-heading"><div><p class="eyebrow">Registration pace</p><h2>Attendee growth</h2></div><span class="panel-count">Cumulative registrations</span></div>
        <div class="chart-wrap">
        <?php if ($chart_count >= 2): ?>
            <svg class="growth-chart" viewBox="0 0 760 260" role="img" aria-label="Cumulative attendee growth chart">
                <line class="chart-gridline" x1="48" y1="32" x2="742" y2="32"></line>
                <line class="chart-gridline" x1="48" y1="122" x2="742" y2="122"></line>
                <line class="chart-axis" x1="48" y1="212" x2="742" y2="212"></line>
                <text class="chart-axis-label" x="10" y="216">0</text>
                <text class="chart-axis-label" x="10" y="36"><?= $chart_max ?></text>
                <polyline class="chart-area" points="48,212 <?= implode(" ", array_map(fn($point) => $point["x"] . "," . $point["y"], $chart_points)) ?> 742,212"></polyline>
                <polyline class="chart-line" points="<?= implode(" ", array_map(fn($point) => $point["x"] . "," . $point["y"], $chart_points)) ?>"></polyline>
                <?php foreach ($chart_points as $point): ?>
                    <circle class="chart-point" cx="<?= $point["x"] ?>" cy="<?= $point["y"] ?>" r="5" tabindex="0">
                        <title><?= htmlspecialchars($point["label"] . ": " . $point["value"] . " cumulative registrations") ?></title>
                    </circle>
                    <text class="chart-x-label" x="<?= $point["x"] ?>" y="238" text-anchor="middle"><?= htmlspecialchars($point["label"]) ?></text>
                <?php endforeach; ?>
            </svg>
        <?php else: ?>
            <div class="chart-empty"><strong>Not enough data yet</strong><span>Check back after more registrations to see growth over time.</span></div>
        <?php endif; ?>
        </div>
    </section>

    <section class="dashboard-panel attendance-panel">
        <div class="panel-heading"><div><p class="eyebrow">Seven-day attendance</p><h2>Attendance matrix</h2></div><span class="panel-count">Days attended / 7</span></div>
        <div class="table-wrap">
            <table class="attendance-table">
                <thead><tr><th>Attendee</th><th>Day 1</th><th>Day 2</th><th>Day 3</th><th>Day 4</th><th>Day 5</th><th>Day 6</th><th>Day 7</th><th>Rate</th></tr></thead>
                <tbody>
                <?php while ($person = $attendance->fetch_assoc()): ?>
                    <?php $attended = (int) $person["days_attended"]; ?>
                    <tr>
                        <td><strong><?= htmlspecialchars(trim($person["first_name"] . " " . $person["middle_name"] . " " . $person["last_name"])) ?></strong><small class="table-subtext"><?= htmlspecialchars($person["registration_number"]) ?></small></td>
                        <?php for ($day = 1; $day <= 7; $day++): ?><td class="attendance-mark <?= $person["day_" . $day] ? "present" : "absent" ?>"><?= $person["day_" . $day] ? "&#10003;" : "&mdash;" ?></td><?php endfor; ?>
                        <td><strong><?= round(($attended / 7) * 100) ?>%</strong><small class="table-subtext"><?= $attended ?>/7 days</small></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
