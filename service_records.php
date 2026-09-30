<?php
session_start();
require_once "database.php";
require_once "report_invitation.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$message = "";
$available_tests = ["SCD sickle cell", "Malaria", "H. pylori", "Typhoid", "Hepatitis B", "HIV", "Syphilis", "Urinalysis", "HCG", "Blood group"];

if (($_GET["saved"] ?? "") === "1") {
    $message = "<div class='success'>Medical service record saved successfully.</div>";
} elseif (($_GET["saved"] ?? "") === "updated") {
    $message = "<div class='success'>Medical service record updated successfully.</div>";
} elseif (($_GET["saved"] ?? "") === "duplicate") {
    $message = "<div class='warning'>This attendee already has a medical record. No duplicate record was created.</div>";
}
$editing_record = null;
$edit_tests = [];
$edit_results = [];

if (isset($_GET["edit"])) {
    $edit_id = (int) $_GET["edit"];
    $edit_stmt = $conn->prepare("SELECT * FROM service_records WHERE id = ? LIMIT 1");
    $edit_stmt->bind_param("i", $edit_id);
    $edit_stmt->execute();
    $editing_record = $edit_stmt->get_result()->fetch_assoc();
    if ($editing_record) {
        $edit_tests = array_filter(array_map("trim", explode(",", $editing_record["tests"] ?? "")));
        foreach (preg_split("/\r?\n/", $editing_record["result"] ?? "") as $line) {
            $separator = strpos($line, ":");
            if ($separator !== false) {
                $edit_results[trim(substr($line, 0, $separator))] = trim(substr($line, $separator + 1));
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $attendee_id = (int) ($_POST["attendee_id"] ?? 0);
    $record_id = (int) ($_POST["record_id"] ?? 0);

    if ($record_id < 1) {
        $existing_stmt = $conn->prepare("SELECT id FROM service_records WHERE attendee_id = ? LIMIT 1");
        $existing_stmt->bind_param("i", $attendee_id);
        $existing_stmt->execute();
        if ($existing_stmt->get_result()->num_rows > 0) {
            header("Location: service_records.php?saved=duplicate");
            exit;
        }
    }

    $service_id = 1;
    $service_date = $_POST["service_date"] ?? date("Y-m-d");
    $selected_tests = $_POST["tests"] ?? [];
    $test_results = $_POST["test_results"] ?? [];
    $tests = implode(", ", $selected_tests);
    $temperature = ($_POST["temperature"] ?? "") !== "" ? (float) $_POST["temperature"] : null;
    $pulse = ($_POST["pulse"] ?? "") !== "" ? (int) $_POST["pulse"] : null;
    $spo2 = ($_POST["spo2"] ?? "") !== "" ? (int) $_POST["spo2"] : null;
    $weight = ($_POST["weight"] ?? "") !== "" ? (float) $_POST["weight"] : null;
    $height = ($_POST["height"] ?? "") !== "" ? (float) $_POST["height"] : null;
    $urine_output = trim($_POST["urine_output"] ?? "");
    $consciousness = trim($_POST["consciousness"] ?? "");
    $clinical_history = trim($_POST["clinical_history"] ?? "");
    $diagnosis = trim($_POST["diagnosis"] ?? "");
    $scd_family_history = trim($_POST["scd_family_history"] ?? "");
    $result_lines = [];
    foreach ($selected_tests as $test) {
        $test_result = trim($test_results[$test] ?? "");
        $result_lines[] = $test . ": " . ($test_result !== "" ? $test_result : "Not recorded");
    }
    $result = implode("\n", $result_lines);
    $systolic = ($_POST["systolic"] ?? "") !== "" ? (int) $_POST["systolic"] : null;
    $diastolic = ($_POST["diastolic"] ?? "") !== "" ? (int) $_POST["diastolic"] : null;
    $blood_sugar = ($_POST["blood_sugar"] ?? "") !== "" ? (float) $_POST["blood_sugar"] : null;
    $blood_sugar_unit = ($_POST["blood_sugar_unit"] ?? "mmol/L") === "mg/dL" ? "mg/dL" : "mmol/L";
    $right_eye = trim($_POST["right_eye"] ?? "");
    $left_eye = trim($_POST["left_eye"] ?? "");
    $notes = trim($_POST["notes"] ?? "");
    $doctor_recommendation = trim($_POST["doctor_recommendation"] ?? "");
    $referral_required = isset($_POST["referral_required"]) ? 1 : 0;
    $referral_notes = trim($_POST["referral_notes"] ?? "");
    $staff = $_SESSION["full_name"] ?? "Medical Staff";

    if ($record_id > 0) {
        $stmt = $conn->prepare(
            "UPDATE service_records SET service_date = ?, tests = ?, temperature = ?, pulse = ?,
             spo2 = ?, weight = ?, height = ?, clinical_history = ?, diagnosis = ?, scd_family_history = ?,
             urine_output = ?, consciousness = ?, result = ?, systolic = ?, diastolic = ?,
             blood_sugar = ?, blood_sugar_unit = ?, notes = ?, doctor_recommendation = ?, referral_required = ?,
             referral_notes = ? WHERE id = ?"
        );
        $stmt->bind_param("ssdiiddssssssiidsssisi", $service_date, $tests, $temperature, $pulse, $spo2,
            $weight, $height, $clinical_history, $diagnosis, $scd_family_history,
            $urine_output, $consciousness, $result, $systolic, $diastolic, $blood_sugar, $blood_sugar_unit, $notes,
            $doctor_recommendation, $referral_required, $referral_notes, $record_id);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO service_records
                (attendee_id, service_id, service_date, tests, temperature, pulse,
                spo2, weight, height, clinical_history, diagnosis, scd_family_history, urine_output, consciousness,
                result, systolic, diastolic,
                blood_sugar, blood_sugar_unit, notes, doctor_recommendation, referral_required,
                referral_notes, attended_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
            $stmt->bind_param("iissdiiddssssssiidsssiss", $attendee_id, $service_id, $service_date, $tests, $temperature, $pulse, $spo2, $weight, $height, $clinical_history, $diagnosis, $scd_family_history, $urine_output, $consciousness, $result, $systolic, $diastolic, $blood_sugar, $blood_sugar_unit, $notes, $doctor_recommendation, $referral_required, $referral_notes, $staff);
    }
    if ($stmt->execute()) {
        $saved_record_id = $record_id > 0 ? $record_id : $stmt->insert_id;
        if ($referral_required && $referral_notes !== "") {
            $referral_check = $conn->prepare("SELECT id FROM referrals WHERE service_record_id = ? LIMIT 1");
            $referral_check->bind_param("i", $saved_record_id);
            $referral_check->execute();
            $existing_referral = $referral_check->get_result()->fetch_assoc();
            if ($existing_referral) {
                $referral = $conn->prepare("UPDATE referrals SET referral_reason = ?, created_by = ? WHERE id = ?");
                $referral->bind_param("ssi", $referral_notes, $staff, $existing_referral["id"]);
            } else {
                $referral = $conn->prepare("INSERT INTO referrals (attendee_id, service_record_id, referral_reason, created_by) VALUES (?, ?, ?, ?)");
                $referral->bind_param("iiss", $attendee_id, $saved_record_id, $referral_notes, $staff);
            }
            $referral->execute();
        } elseif ($record_id > 0 && !$referral_required) {
            $cancel_referral = $conn->prepare("UPDATE referrals SET status = 'Cancelled' WHERE service_record_id = ? AND status = 'Pending'");
            $cancel_referral->bind_param("i", $saved_record_id);
            $cancel_referral->execute();
        }
        header("Location: service_records.php?saved=" . ($record_id > 0 ? "updated" : "1"));
        exit;
    } else {
        $message = "<div class='error'>Could not save this service record.</div>";
    }
}

$attendees = $conn->query(
    "SELECT id, registration_number, first_name, middle_name, last_name
     FROM attendees ORDER BY first_name, last_name"
);
$attendees = $attendees ? $attendees->fetch_all(MYSQLI_ASSOC) : [];
$services = $conn->query("SELECT id, service_name FROM medical_services ORDER BY service_name");
$records = $conn->query(
    "SELECT a.id AS attendee_id, a.first_name, a.middle_name, a.last_name, a.phone,
            r.id AS record_id, m.service_name, r.service_date, r.tests, r.result,
            r.temperature, r.pulse, r.systolic, r.diastolic, r.spo2, r.blood_sugar,
            r.weight, r.height, r.clinical_history, r.diagnosis, r.blood_sugar_unit, r.notes,
            r.doctor_recommendation, r.referral_required, r.referral_notes, r.attended_by
     FROM service_records r
     JOIN attendees a ON a.id = r.attendee_id
     JOIN medical_services m ON m.id = r.service_id
     ORDER BY r.created_at DESC LIMIT 15"
);
$recent_records = $records ? $records->fetch_all(MYSQLI_ASSOC) : [];
foreach ($recent_records as &$record) {
    $patient_name = trim($record["first_name"] . " " . $record["middle_name"] . " " . $record["last_name"]);
    $phone = preg_replace("/\D+/", "", $record["phone"] ?? "");
    if (str_starts_with($phone, "0")) {
        $phone = "256" . substr($phone, 1);
    }
    $record["patient_name"] = $patient_name;
    $record["whatsapp_url"] = $phone !== ""
        ? "https://wa.me/" . $phone . "?text=" . rawurlencode(MUSDAA_RETURN_INVITATION)
        : "";
}
unset($record);
$edit_value = static fn(string $field): string => htmlspecialchars((string) ($editing_record[$field] ?? ""), ENT_QUOTES, "UTF-8");
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
    <nav class="topbar-nav" id="main-menu"><a href="dashboard.php">Dashboard</a><a href="register.php">Register attendee</a><a href="checkin.php">Daily check-in</a><a class="active" href="service_records.php" aria-current="page">Medical services</a><a href="all_records.php">All Records</a><a href="reports.php">Reports</a><a class="logout-link" href="logout.php">Sign out</a></nav>
</header>
<main class="service-page">
    <div class="dashboard-heading"><div><a class="history-back" href="index.php" onclick="if (window.history.length > 1) { window.history.back(); return false; }">Back</a><p class="eyebrow">Clinical records</p><h1><?= $editing_record ? "Edit medical service" : "Record a medical service" ?></h1><p class="muted-copy">Keep each attendee's care history organized and easy to review.</p></div></div>
    <?= $message ?>
    <section class="service-layout">
        <div class="dashboard-panel">
            <form method="POST">
                <?php if ($editing_record): ?><input type="hidden" name="record_id" value="<?= (int) $editing_record["id"] ?>"><input type="hidden" name="attendee_id" value="<?= (int) $editing_record["attendee_id"] ?>"><?php endif; ?>
                <div class="form-grid">
                    <div class="form-section full"><span>Visit details</span><small>Choose the attendee and date</small></div>
                    <div class="full"><label for="attendee_id">Attendee *</label><select id="attendee_id" name="attendee_id" required <?= $editing_record ? "disabled" : "" ?>><option value="">Select attendee</option><?php foreach ($attendees as $attendee): ?><option value="<?= (int) $attendee["id"] ?>" <?= (int) $attendee["id"] === (int) ($editing_record["attendee_id"] ?? 0) ? "selected" : "" ?>><?= htmlspecialchars($attendee["registration_number"] . " - " . trim($attendee["first_name"] . " " . $attendee["middle_name"] . " " . $attendee["last_name"])) ?></option><?php endforeach; ?></select></div>
                    <div><label for="service_date">Service date *</label><input id="service_date" type="date" name="service_date" value="<?= $editing_record ? $edit_value("service_date") : date("Y-m-d") ?>" required></div>
                    <div class="form-section full"><span>Doctor&apos;s notes</span><small>Record the presenting complaint or initial clinical notes</small></div>
                    <div class="full"><label for="notes">Doctor&apos;s notes</label><textarea id="notes" name="notes" rows="4" placeholder="Enter the presenting complaint or doctor&apos;s notes"><?= $edit_value("notes") ?></textarea></div>
                    <div class="full"><label for="clinical_history">Clinical presentation and history</label><textarea id="clinical_history" name="clinical_history" rows="4" placeholder="Symptoms, duration, relevant medical history"><?= $edit_value("clinical_history") ?></textarea></div>
                    <div class="full"><label for="diagnosis">Diagnosis</label><textarea id="diagnosis" name="diagnosis" rows="3" placeholder="Enter the clinician&apos;s diagnosis"><?= $edit_value("diagnosis") ?></textarea></div>
                    <div class="full"><label for="scd_family_history">SCD family history</label><textarea id="scd_family_history" name="scd_family_history" rows="2" placeholder="Note any family history of sickle cell disease, or enter None known"><?= $edit_value("scd_family_history") ?></textarea></div>
                    <div class="form-section full"><span>Tests and results</span><small>Select each test performed and enter its result</small></div>
                    <div class="full test-options"><?php foreach ($available_tests as $test): ?><div class="test-result-row"><label><input type="checkbox" name="tests[]" value="<?= htmlspecialchars($test) ?>" <?= in_array($test, $edit_tests, true) ? "checked" : "" ?>> <?= htmlspecialchars($test) ?></label><input type="text" name="test_results[<?= htmlspecialchars($test) ?>]" placeholder="Result" value="<?= htmlspecialchars($edit_results[$test] ?? "", ENT_QUOTES, "UTF-8") ?>"></div><?php endforeach; ?></div>
                    <div class="form-section full"><span>Vitals</span><small>Record the attendee&apos;s measurements</small></div>
                    <div><label for="temperature">Temperature <small class="field-unit">(°C)</small></label><input id="temperature" type="number" name="temperature" min="0" step="0.1" value="<?= $edit_value("temperature") ?>"></div>
                    <div><label for="pulse">Pulse <small class="field-unit">(bpm)</small></label><input id="pulse" type="number" name="pulse" min="0" value="<?= $edit_value("pulse") ?>"></div>
                    <div><label for="weight">Weight <small class="field-unit">(kg)</small></label><input id="weight" type="number" name="weight" min="0" step="0.01" value="<?= $edit_value("weight") ?>"></div>
                    <div><label for="height">Height <small class="field-unit">(cm)</small></label><input id="height" type="number" name="height" min="0" step="0.1" value="<?= $edit_value("height") ?>"></div>
                    <div><label for="bmi">BMI <small class="field-unit">(kg/m²)</small></label><input id="bmi" type="text" readonly aria-live="polite" placeholder="Calculated from weight and height"></div>
                    <div><label for="systolic">Blood pressure <small class="field-unit">(systolic mmHg)</small></label><input id="systolic" type="number" name="systolic" min="0" value="<?= $edit_value("systolic") ?>"></div>
                    <div><label for="diastolic">Blood pressure <small class="field-unit">(diastolic mmHg)</small></label><input id="diastolic" type="number" name="diastolic" min="0" value="<?= $edit_value("diastolic") ?>"></div>
                    <div><label for="spo2">SpO2 <small class="field-unit">(%)</small></label><input id="spo2" type="number" name="spo2" min="0" max="100" value="<?= $edit_value("spo2") ?>"></div>
                    <div><label for="urine_output">Urine output</label><input id="urine_output" type="text" name="urine_output" value="<?= $edit_value("urine_output") ?>"></div>
                    <div><label for="blood_sugar">Random blood sugar (RBS)</label><input id="blood_sugar" type="number" name="blood_sugar" min="0" step="0.01" value="<?= $edit_value("blood_sugar") ?>"></div>
                    <div><label for="blood_sugar_unit">RBS unit</label><select id="blood_sugar_unit" name="blood_sugar_unit"><option value="mmol/L" <?= ($editing_record["blood_sugar_unit"] ?? "mmol/L") === "mmol/L" ? "selected" : "" ?>>mmol/L</option><option value="mg/dL" <?= ($editing_record["blood_sugar_unit"] ?? "mmol/L") === "mg/dL" ? "selected" : "" ?>>mg/dL</option></select></div>
                    <div class="full"><label for="consciousness">Level of consciousness</label><select id="consciousness" name="consciousness"><option value="">Select level</option><?php foreach (["Alert", "Confused", "Drowsy", "Unresponsive"] as $level): ?><option value="<?= $level ?>" <?= ($editing_record["consciousness"] ?? "") === $level ? "selected" : "" ?>><?= $level ?></option><?php endforeach; ?></select></div>
                    <div class="form-section full"><span>Doctor recommendations or prescription</span><small>Record the next steps for the attendee</small></div>
                    <div class="full"><label for="doctor_recommendation">Recommendations or prescription</label><textarea id="doctor_recommendation" name="doctor_recommendation" rows="4"><?= $edit_value("doctor_recommendation") ?></textarea></div>
                    <div class="full"><label><input id="referral_required" type="checkbox" name="referral_required" <?= !empty($editing_record["referral_required"]) ? "checked" : "" ?>> Referral required</label></div>
                    <div class="full referral-notes-field" id="referral_notes_field"><label for="referral_notes">Referral notes</label><textarea id="referral_notes" name="referral_notes" rows="3"><?= $edit_value("referral_notes") ?></textarea></div>
                    <div class="full"><button type="submit"><?= $editing_record ? "Update service record" : "Save service record" ?> <span aria-hidden="true">&#8594;</span></button><?php if ($editing_record): ?> <a class="report-download" href="service_records.php">Cancel edit</a><?php endif; ?></div>
                </div>
            </form>
        </div>
        <div class="dashboard-panel service-history-panel">
            <div class="panel-heading"><div><p class="eyebrow">History</p><h2>Recent services</h2></div><span class="panel-count"><?= count($recent_records) ?> records</span></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Attendee</th><th>Service</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_records as $record): ?>
                        <tr>
                            <td><?= htmlspecialchars($record["patient_name"]) ?><small class="table-subtext"><?= htmlspecialchars($record["phone"] ?: "No phone number") ?></small></td>
                            <td><?= htmlspecialchars($record["service_name"]) ?><small class="table-subtext"><?= htmlspecialchars($record["result"] ?: "No result entered") ?></small></td>
                            <td><?= htmlspecialchars(date("M j, Y", strtotime($record["service_date"]))) ?></td>
                            <td class="service-record-actions">
                                <a class="record-action record-action-edit" href="service_records.php?edit=<?= (int) $record["record_id"] ?>">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg><span>Edit</span>
                                </a>
                                <a class="record-action record-action-pdf" href="person_report.php?attendee_id=<?= (int) $record["attendee_id"] ?>">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M8 13h2a1.5 1.5 0 0 1 0 3H8v3m6 0v-6h1.5a3 3 0 0 1 0 6H14m5-6v6m0-3h3"/></svg><span>PDF</span>
                                </a>
                                <?php if ($record["whatsapp_url"]): ?>
                                    <a class="record-action record-action-whatsapp" href="<?= htmlspecialchars($record["whatsapp_url"], ENT_QUOTES, "UTF-8") ?>" target="_blank" rel="noopener" data-report-url="person_report.php?attendee_id=<?= (int) $record["attendee_id"] ?>" data-file-name="MUSDAA-medical-report-<?= (int) $record["attendee_id"] ?>.pdf" aria-label="Share PDF via WhatsApp for <?= htmlspecialchars($record["patient_name"], ENT_QUOTES, "UTF-8") ?>">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 11.5a8.5 8.5 0 0 1-12.6 7.4L3 20l1.2-4.7A8.5 8.5 0 1 1 20.5 11.5Z"/><path d="M8.2 7.7c.2-.5.5-.5.8-.5h.5c.2 0 .4.1.5.4l.7 1.7c.1.2.1.4-.1.6l-.5.6c-.2.2-.2.4 0 .6.6 1 1.4 1.8 2.5 2.3.2.1.4.1.6-.1l.7-.8c.2-.2.4-.2.6-.1l1.6.8c.2.1.4.3.4.5 0 .3-.2 1.2-.7 1.6-.5.5-1.2.7-1.9.6-1.1-.2-2.5-.8-3.8-2-1.2-1.1-2-2.5-2.3-3.5-.3-1.1.1-1.9.4-2.3Z"/></svg><span>Share PDF</span>
                                    </a>
                                <?php else: ?>
                                    <span class="table-subtext">No WhatsApp number</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!$recent_records): ?><div class="empty-state"><span class="empty-state-icon" aria-hidden="true">+</span><strong>No recent services recorded yet</strong><small>Saved clinical records will appear here.</small></div><?php endif; ?>
            </div>
            <p class="service-history-footer">Showing the latest 15 service records</p>
        </div>
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

document.querySelectorAll('.record-action-whatsapp').forEach((shareLink) => {
    shareLink.addEventListener('click', async (event) => {
        if (shareLink.getAttribute('aria-busy') === 'true') {
            event.preventDefault();
            return;
        }

        const label = shareLink.querySelector('span');
        const originalLabel = label.textContent;
        shareLink.setAttribute('aria-busy', 'true');
        label.textContent = 'Preparing PDF...';

        try {
            const response = await fetch(shareLink.dataset.reportUrl, { credentials: 'same-origin' });
            if (!response.ok || !response.headers.get('Content-Type')?.includes('application/pdf')) {
                throw new Error('The medical report PDF could not be created.');
            }

            const pdfBlob = await response.blob();
            const pdfUrl = URL.createObjectURL(pdfBlob);
            const downloadLink = document.createElement('a');
            downloadLink.href = pdfUrl;
            downloadLink.download = shareLink.dataset.fileName;
            document.body.appendChild(downloadLink);
            downloadLink.click();
            downloadLink.remove();
            window.setTimeout(() => URL.revokeObjectURL(pdfUrl), 60000);
            window.alert('The WhatsApp chat for this attendee is open with the message filled in. Attach the downloaded PDF before sending.');
        } catch (error) {
            if (error.name !== 'AbortError') {
                window.alert(error.message || 'Could not prepare the medical report PDF.');
            }
        } finally {
            shareLink.removeAttribute('aria-busy');
            label.textContent = originalLabel;
        }
    });
});

const weightInput = document.getElementById('weight');
const heightInput = document.getElementById('height');
const bmiInput = document.getElementById('bmi');

function updateBmi() {
    const weight = Number(weightInput.value);
    const heightMeters = Number(heightInput.value) / 100;
    bmiInput.value = weight > 0 && heightMeters > 0 ? (weight / (heightMeters * heightMeters)).toFixed(1) : '';
}

weightInput.addEventListener('input', updateBmi);
heightInput.addEventListener('input', updateBmi);
updateBmi();
</script>
<script src="menu.js"></script>
</body>
</html>
