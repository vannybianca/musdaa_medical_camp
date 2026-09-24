<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $attendee_id = (int) ($_POST["attendee_id"] ?? 0);
    $service_id = (int) ($_POST["service_id"] ?? 0);
    $service_date = $_POST["service_date"] ?? date("Y-m-d");
    $result = trim($_POST["result"] ?? "");
    $systolic = ($_POST["systolic"] ?? "") !== "" ? (int) $_POST["systolic"] : null;
    $diastolic = ($_POST["diastolic"] ?? "") !== "" ? (int) $_POST["diastolic"] : null;
    $blood_sugar = ($_POST["blood_sugar"] ?? "") !== "" ? (float) $_POST["blood_sugar"] : null;
    $right_eye = trim($_POST["right_eye"] ?? "");
    $left_eye = trim($_POST["left_eye"] ?? "");
    $notes = trim($_POST["notes"] ?? "");
    $referral_required = isset($_POST["referral_required"]) ? 1 : 0;
    $referral_notes = trim($_POST["referral_notes"] ?? "");
    $staff = $_SESSION["full_name"] ?? "Medical Staff";

    $stmt = $conn->prepare(
        "INSERT INTO service_records
         (attendee_id, service_id, service_date, result, systolic, diastolic,
          blood_sugar, right_eye, left_eye, notes, referral_required,
          referral_notes, attended_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("iissiidsssiss", $attendee_id, $service_id, $service_date, $result, $systolic, $diastolic, $blood_sugar, $right_eye, $left_eye, $notes, $referral_required, $referral_notes, $staff);
    if ($stmt->execute()) {
        if ($referral_required && $referral_notes !== "") {
            $service_record_id = $stmt->insert_id;
            $referral = $conn->prepare("INSERT INTO referrals (attendee_id, service_record_id, referral_reason, created_by) VALUES (?, ?, ?, ?)");
            $referral->bind_param("iiss", $attendee_id, $service_record_id, $referral_notes, $staff);
            $referral->execute();
        }
        $message = "<div class='success'>Medical service record saved successfully.</div>";
    } else {
        $message = "<div class='error'>Could not save this service record.</div>";
    }
}

$attendees = $conn->query(
    "SELECT id, registration_number, first_name, middle_name, last_name
     FROM attendees ORDER BY first_name, last_name"
);
$services = $conn->query("SELECT id, service_name FROM medical_services ORDER BY service_name");
$records = $conn->query(
    "SELECT a.first_name, a.last_name, m.service_name, r.service_date, r.result, r.attended_by
     FROM service_records r
     JOIN attendees a ON a.id = r.attendee_id
     JOIN medical_services m ON m.id = r.service_id
     ORDER BY r.created_at DESC LIMIT 15"
);
$recent_records = $records ? $records->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Medical Services | MUSDAA Medical Camp</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="service-page-body">
<header class="topbar">
    <a class="topbar-brand" href="dashboard.php">MUSDAA <span>Medical Camp</span></a>
    <nav class="topbar-nav"><a href="dashboard.php">Dashboard</a><a href="registration/register.php">Register attendee</a><a href="checkin/checkin.php">Daily check-in</a><a class="active" href="service_records.php" aria-current="page">Medical services</a><a href="reports.php">Reports</a><a class="logout-link" href="logout.php">Sign out</a></nav>
</header>
<main class="service-page">
    <div class="dashboard-heading"><div><a class="history-back" href="index.php" onclick="if (window.history.length > 1) { window.history.back(); return false; }">Back</a><p class="eyebrow">Clinical records</p><h1>Record a medical service</h1><p class="muted-copy">Keep each attendee's care history organized and easy to review.</p></div></div>
    <?= $message ?>
    <section class="service-layout">
        <div class="dashboard-panel">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-section full"><span>Visit details</span><small>Choose the attendee, service, and date</small></div>
                    <div class="full"><label for="attendee_id">Attendee *</label><select id="attendee_id" name="attendee_id" required><option value="">Select attendee</option><?php while ($attendee = $attendees->fetch_assoc()): ?><option value="<?= $attendee["id"] ?>"><?= htmlspecialchars($attendee["registration_number"] . " - " . trim($attendee["first_name"] . " " . $attendee["middle_name"] . " " . $attendee["last_name"])) ?></option><?php endwhile; ?></select></div>
                    <div><label for="service_id">Medical service *</label><select id="service_id" name="service_id" required><option value="">Select service</option><?php while ($service = $services->fetch_assoc()): ?><option value="<?= $service["id"] ?>"><?= htmlspecialchars($service["service_name"]) ?></option><?php endwhile; ?></select></div>
                    <div><label for="service_date">Service date *</label><input id="service_date" type="date" name="service_date" value="<?= date("Y-m-d") ?>" required></div>
                    <div class="form-section full"><span>Clinical findings</span><small>Capture structured measurements alongside the main result</small></div>
                    <div class="full"><label for="result">Result or measurement</label><input id="result" type="text" name="result" placeholder="e.g. BP 120/80, Negative, Referred"></div>
                    <div><label for="systolic">Systolic <small class="field-unit">(mmHg)</small></label><input id="systolic" type="number" name="systolic" min="0"></div>
                    <div><label for="diastolic">Diastolic <small class="field-unit">(mmHg)</small></label><input id="diastolic" type="number" name="diastolic" min="0"></div>
                    <div><label for="blood_sugar">Blood sugar <small class="field-unit">(mg/dL)</small></label><input id="blood_sugar" type="number" name="blood_sugar" min="0" step="0.01"></div>
                    <div><label for="right_eye">Right eye</label><input id="right_eye" type="text" name="right_eye"></div>
                    <div><label for="left_eye">Left eye</label><input id="left_eye" type="text" name="left_eye"></div>
                    <div class="form-section full"><span>Follow-up</span><small>Record notes and flag referrals for later action</small></div>
                    <div class="full"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="4" placeholder="Add relevant clinical notes"></textarea></div>
                    <div class="full"><label><input id="referral_required" type="checkbox" name="referral_required"> Referral required</label></div>
                    <div class="full referral-notes-field" id="referral_notes_field"><label for="referral_notes">Referral notes</label><textarea id="referral_notes" name="referral_notes" rows="3"></textarea></div>
                    <div class="full"><button type="submit">Save service record <span aria-hidden="true">&#8594;</span></button></div>
                </div>
            </form>
        </div>
        <div class="dashboard-panel service-history-panel"><div class="panel-heading"><div><p class="eyebrow">History</p><h2>Recent services</h2></div><span class="panel-count"><?= count($recent_records) ?> records</span></div><div class="table-wrap"><table><thead><tr><th>Attendee</th><th>Service</th><th>Date</th></tr></thead><tbody><?php foreach ($recent_records as $record): ?><tr><td><?= htmlspecialchars($record["first_name"] . " " . $record["last_name"]) ?></td><td><?= htmlspecialchars($record["service_name"]) ?><small class="table-subtext"><?= htmlspecialchars($record["result"] ?: "No result entered") ?></small></td><td><?= htmlspecialchars(date("M j, Y", strtotime($record["service_date"]))) ?></td></tr><?php endforeach; ?></tbody></table><?php if (!$recent_records): ?><div class="empty-state"><span class="empty-state-icon" aria-hidden="true">+</span><strong>No recent services recorded yet</strong><small>Saved clinical records will appear here.</small></div><?php endif; ?></div><p class="service-history-footer">Showing the latest 15 service records</p></div>
    </section>
    <a class="back-link" href="dashboard.php">Back to dashboard</a>
</main>
<script>
const referralRequired = document.getElementById('referral_required');
const referralNotesField = document.getElementById('referral_notes_field');

function toggleReferralNotes() {
    referralNotesField.classList.toggle('is-visible', referralRequired.checked);
    referralNotesField.setAttribute('aria-hidden', referralRequired.checked ? 'false' : 'true');
}

referralRequired.addEventListener('change', toggleReferralNotes);
toggleReferralNotes();
</script>
</body>
</html>
