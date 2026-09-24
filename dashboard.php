<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$search = trim($_GET["search"] ?? "");
$stats = [];

$queries = [
    "attendees" => "SELECT COUNT(*) AS total FROM attendees",
    "checkins" => "SELECT COUNT(*) AS total FROM daily_checkins WHERE DATE(checkin_time) = CURDATE()",
    "services" => "SELECT COUNT(*) AS total FROM service_records WHERE service_date = CURDATE()",
    "days" => "SELECT COUNT(*) AS total FROM summit_days WHERE event_date >= CURDATE()"
];

foreach ($queries as $key => $query) {
    $result = $conn->query($query);
    $stats[$key] = (int) $result->fetch_assoc()["total"];
}

if ($search !== "") {
    $stmt = $conn->prepare(
        "SELECT id, registration_number, first_name, middle_name, last_name, phone, course
         FROM attendees
         WHERE registration_number LIKE ?
            OR first_name LIKE ?
            OR middle_name LIKE ?
            OR last_name LIKE ?
            OR phone LIKE ?
         ORDER BY registration_date DESC
         LIMIT 30"
    );
    $term = "%" . $search . "%";
    $stmt->bind_param("sssss", $term, $term, $term, $term, $term);
} else {
    $stmt = $conn->prepare(
        "SELECT id, registration_number, first_name, middle_name, last_name, phone, course
         FROM attendees ORDER BY registration_date DESC LIMIT 8"
    );
}
$stmt->execute();
$attendees = $stmt->get_result();

$recent = $conn->query(
    "SELECT a.first_name, a.last_name, s.day_number, d.checkin_time
     FROM daily_checkins d
     JOIN attendees a ON a.id = d.attendee_id
     JOIN summit_days s ON s.id = d.summit_day_id
     ORDER BY d.checkin_time DESC LIMIT 8"
);
$recent_items = $recent ? $recent->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | MUSDAA Medical Camp</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-page">
<header class="topbar">
    <a class="topbar-brand" href="dashboard.php">MUSDAA <span>Medical Camp</span></a>
    <nav class="topbar-nav">
        <a class="active" href="dashboard.php" aria-current="page">Dashboard</a>
        <a href="registration/register.php">Register attendee</a>
        <a href="checkin/checkin.php">Daily check-in</a>
        <a href="service_records.php">Medical services</a>
        <a href="reports.php">Reports</a>
        <a class="logout-link" href="logout.php"><span class="user-avatar" aria-hidden="true">M</span>Sign out</a>
    </nav>
</header>
<main class="dashboard">
    <div class="dashboard-heading">
        <div><a class="history-back" href="index.php" onclick="if (window.history.length > 1) { window.history.back(); return false; }">Back</a>
            <p class="eyebrow">Operations overview</p>
            <h1>Good day, <?= htmlspecialchars($_SESSION["full_name"] ?? "staff") ?></h1>
            <p class="muted-copy">Manage attendees, attendance, and medical care from one place.</p>
        </div>
        <a class="button-link compact-button" href="registration/register.php">+ Register attendee</a>
    </div>

    <section class="stat-grid" aria-label="Camp statistics">
        <div class="stat-card"><span>Registered attendees</span><strong><?= $stats["attendees"] ?></strong><small>All registrations</small></div>
        <div class="stat-card"><span>Check-ins today</span><strong><?= $stats["checkins"] ?></strong><small>Recorded today</small></div>
        <div class="stat-card"><span>Services today</span><strong><?= $stats["services"] ?></strong><small>Clinical visits today</small></div>
        <div class="stat-card"><span>Upcoming camp days</span><strong><?= $stats["days"] ?></strong><small>Remaining on schedule</small></div>
    </section>

    <section class="dashboard-grid">
        <div class="dashboard-panel">
            <div class="panel-heading">
                <div><p class="eyebrow">People directory</p><h2>Attendees</h2></div>
                <span class="panel-count"><?= $search !== "" ? "Search results" : "Latest registrations" ?></span>
            </div>
            <form class="search-form" method="GET">
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, phone, or registration number">
                <button type="submit">Search</button>
            </form>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Registration</th><th>Name</th><th>Phone</th><th>Course</th></tr></thead>
                    <tbody>
                    <?php while ($attendee = $attendees->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($attendee["registration_number"]) ?></td>
                            <td><?= htmlspecialchars(trim($attendee["first_name"] . " " . $attendee["middle_name"] . " " . $attendee["last_name"])) ?></td>
                            <td><?= htmlspecialchars($attendee["phone"]) ?></td>
                            <td class="course-cell"><?= htmlspecialchars($attendee["course"] ?: "-") ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="dashboard-panel">
            <div class="panel-heading"><div><p class="eyebrow">Live activity</p><h2>Recent check-ins</h2></div></div>
            <div class="activity-list">
            <?php foreach ($recent_items as $item): ?>
                <div class="activity-item">
                    <span class="status-dot"></span>
                    <div><strong><?= htmlspecialchars($item["first_name"] . " " . $item["last_name"]) ?></strong><small>Day <?= (int) $item["day_number"] ?> · <?= htmlspecialchars(date("g:i A", strtotime($item["checkin_time"]))) ?></small></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$recent_items): ?>
                <div class="empty-state"><span class="empty-state-icon" aria-hidden="true">+</span><strong>No check-ins yet</strong><small>Recent attendance activity will appear here.</small></div>
            <?php elseif (count($recent_items) < 3): ?>
                <div class="activity-summary">Showing the latest <?= count($recent_items) ?> check-in<?= count($recent_items) === 1 ? "" : "s" ?>.</div>
            <?php endif; ?>
            </div>
        </aside>
    </section>
</main>
</body>
</html>
