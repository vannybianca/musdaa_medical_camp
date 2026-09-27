<?php
session_start();
require_once "database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$message = "";
$available_tests = ["SCD sickle cell", "Malaria", "H. pylori", "Typhoid", "Hepatitis B", "Syphilis", "Urinalysis", "HCG"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $attendee_id = (int) ($_POST["attendee_id"] ?? 0);
    $service_id = 1;
    $service_date = $_POST["service_date"] ?? date("Y-m-d");
    $selected_tests = $_POST["tests"] ?? [];
    $test_results = $_POST["test_results"] ?? [];
    $tests = implode(", ", $selected_tests);
    $temperature = ($_POST["temperature"] ?? "") !== "" ? (float) $_POST["temperature"] : null;
    $pulse = ($_POST["pulse"] ?? "") !== "" ? (int) $_POST["pulse"] : null;
    $spo2 = ($_POST["spo2"] ?? "") !== "" ? (int) $_POST["spo2"] : null;
    $urine_output = trim($_POST["urine_output"] ?? "");
    $consciousness = trim($_POST["consciousness"] ?? "");
    $result_lines = [];
    foreach ($selected_tests as $test) {
        $test_result = trim($test_results[$test] ?? "");
        $result_lines[] = $test . ": " . ($test_result !== "" ? $test_result : "Not recorded");
    }
    $result = implode("\n", $result_lines);
    $systolic = ($_POST["systolic"] ?? "") !== "" ? (int) $_POST["systolic"] : null;
    $diastolic = ($_POST["diastolic"] ?? "") !== "" ? (int) $_POST["diastolic"] : null;
    $blood_sugar = ($_POST["blood_sugar"] ?? "") !== "" ? (float) $_POST["blood_sugar"] : null;
    $right_eye = trim($_POST["right_eye"] ?? "");
    $left_eye = trim($_POST["left_eye"] ?? "");
    $notes = trim($_POST["notes"] ?? "");
    $doctor_recommendation = trim($_POST["doctor_recommendation"] ?? "");
    $referral_required = isset($_POST["referral_required"]) ? 1 : 0;
    $referral_notes = trim($_POST["referral_notes"] ?? "");
    $staff = $_SESSION["full_name"] ?? "Medical Staff";

    $stmt = $conn->prepare(
        "INSERT INTO service_records
            (attendee_id, service_id, service_date, tests, temperature, pulse,
            spo2, urine_output, consciousness, result, systolic, diastolic,
            blood_sugar, notes, doctor_recommendation, referral_required,
            referral_notes, attended_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
        $stmt->bind_param("iissdiisssiidssiss", $attendee_id, $service_id, $service_date, $tests, $temperature, $pulse, $spo2, $urine_output, $consciousness, $result, $systolic, $diastolic, $blood_sugar, $notes, $doctor_recommendation, $referral_required, $referral_notes, $staff);
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
<link rel="stylesheet" href="style.css">
</head>
<body class="service-page-body">
<header class="topbar">
    <a class="topbar-brand" href="dashboard.php">MUSDAA <span>Medical Camp</span></a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-menu">Menu</button>
    <nav class="topbar-nav" id="main-menu"><a href="dashboard.php">Dashboard</a><a href="register.php">Register attendee</a><a href="checkin.php">Daily check-in</a><a class="active" href="service_records.php" aria-current="page">Medical services</a><a href="reports.php">Reports</a><a class="logout-link" href="logout.php">Sign out</a></nav>
</header>
<main class="service-page">
    <div class="dashboard-heading"><div><a class="history-back" href="index.php" onclick="if (window.history.length > 1) { window.history.back(); return false; }">Back</a><p class="eyebrow">Clinical records</p><h1>Record a medical service</h1><p class="muted-copy">Keep each attendee's care history organized and easy to review.</p></div></div>
    <?= $message ?>
    <section class="service-layout">
        <div class="dashboard-panel">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-section full"><span>Visit details</span><small>Choose the attendee and date</small></div>
                    <div class="full"><label for="attendee_id">Attendee *</label><select id="attendee_id" name="attendee_id" required><option value="">Select attendee</option><?php while ($attendee = $attendees->fetch_assoc()): ?><option value="<?= $attendee["id"] ?>"><?= htmlspecialchars($attendee["registration_number"] . " - " . trim($attendee["first_name"] . " " . $attendee["middle_name"] . " " . $attendee["last_name"])) ?></option><?php endwhile; ?></select></div>
                    <div><label for="service_date">Service date *</label><input id="service_date" type="date" name="service_date" value="<?= date("Y-m-d") ?>" required></div>
                    <div class="form-section full"><span>Tests</span><small>Select all tests performed</small></div>
                    <div class="full test-options"><?php foreach ($available_tests as $test): ?><div class="test-result-row"><label><input type="checkbox" name="tests[]" value="<?= htmlspecialchars($test) ?>"> <?= htmlspecialchars($test) ?></label><input type="text" name="test_results[<?= htmlspecialchars($test) ?>]" placeholder="Result"></div><?php endforeach; ?></div>
                    <div class="form-section full"><span>Vitals</span><small>Record the attendee&apos;s measurements</small></div>
                    <div><label for="temperature">Temperature <small class="field-unit">(°C)</small></label><input id="temperature" type="number" name="temperature" min="0" step="0.1"></div>
                    <div><label for="pulse">Pulse <small class="field-unit">(bpm)</small></label><input id="pulse" type="number" name="pulse" min="0"></div>
                    <div><label for="systolic">Blood pressure <small class="field-unit">(systolic mmHg)</small></label><input id="systolic" type="number" name="systolic" min="0"></div>
                    <div><label for="diastolic">Blood pressure <small class="field-unit">(diastolic mmHg)</small></label><input id="diastolic" type="number" name="diastolic" min="0"></div>
                    <div><label for="spo2">SpO2 <small class="field-unit">(%)</small></label><input id="spo2" type="number" name="spo2" min="0" max="100"></div>
                    <div><label for="urine_output">Urine output</label><input id="urine_output" type="text" name="urine_output"></div>
                    <div class="full"><label for="blood_sugar">Blood glucose <small class="field-unit">(mg/dL)</small></label><input id="blood_sugar" type="number" name="blood_sugar" min="0" step="0.01"></div>
                    <div class="full"><label for="consciousness">Level of consciousness</label><select id="consciousness" name="consciousness"><option value="">Select level</option><option value="Alert">Alert</option><option value="Confused">Confused</option><option value="Drowsy">Drowsy</option><option value="Unresponsive">Unresponsive</option></select></div>
                    <div class="form-section full"><span>Test results</span><small>Each selected test is saved together with its result</small></div>
                    <div class="form-section full"><span>Doctor recommendations or prescription</span><small>Record the next steps for the attendee</small></div>
                    <div class="full"><label for="doctor_recommendation">Recommendations or prescription</label><textarea id="doctor_recommendation" name="doctor_recommendation" rows="4"></textarea></div>
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
<script src="menu.js"></script>
</body>
</html>
