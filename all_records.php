<?php
session_start();
require_once "database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$attendee_result = $conn->query(
    "SELECT a.id, a.registration_number, a.first_name, a.middle_name, a.last_name,
            a.age, a.gender, a.phone, a.email, a.address, a.district, a.tribe,
            a.days_attend, a.musdaa_status, a.occupation, a.course, a.year_of_study,
            a.religion, a.church, a.fellowship, a.blood_group,
            a.emergency_contact_name, a.emergency_contact_phone,
            a.emergency_contact_relationship, a.registration_date,
            COALESCE(c.checked_in_days, 'None recorded') AS checked_in_days
     FROM attendees a
     LEFT JOIN (
         SELECT d.attendee_id,
                GROUP_CONCAT(CONCAT('Day ', s.day_number, ' (', DATE_FORMAT(s.event_date, '%b %e, %Y'), ')')
                    ORDER BY s.day_number SEPARATOR '; ') AS checked_in_days
         FROM daily_checkins d
         JOIN summit_days s ON s.id = d.summit_day_id
         GROUP BY d.attendee_id
     ) c ON c.attendee_id = a.id
     ORDER BY a.last_name, a.first_name, a.registration_number"
);
$attendees = $attendee_result ? $attendee_result->fetch_all(MYSQLI_ASSOC) : [];

$service_result = $conn->query(
    "SELECT r.*, m.service_name
     FROM service_records r
     LEFT JOIN medical_services m ON m.id = r.service_id
     ORDER BY r.attendee_id, r.service_date DESC, r.created_at DESC"
);
$services_by_attendee = [];
if ($service_result) {
    while ($service = $service_result->fetch_assoc()) {
        $services_by_attendee[(int) $service["attendee_id"]][] = $service;
    }
}

$display_value = static function ($value): string {
    $value = trim((string) ($value ?? ""));
    return $value !== "" ? $value : "Not recorded";
};
$csv_value = static function ($value): string {
    $value = (string) ($value ?? "");
    if (preg_match('/^[\t\r\n ]*[=+@-]/', $value)) {
        $value = "'" . $value;
    }
    return $value;
};

if (($_GET["export"] ?? "") === "excel") {
    $filename = "MUSDAA-all-person-records-" . date("Y-m-d") . ".csv";
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
    $output = fopen("php://output", "w");
    fwrite($output, "\xEF\xBB\xBF");
    $headers = [
        "Registration number", "Full name", "Age", "Gender", "Phone", "Email", "Address",
        "District", "Tribe", "Membership", "Days attending", "Camp check-ins", "Occupation",
        "Course", "Year of study", "Religion", "Church", "Fellowship", "Blood group",
        "Emergency contact", "Emergency contact phone", "Emergency contact relationship", "Registered on",
        "Service date", "Service", "Clinical presentation and history", "Doctor notes", "Diagnosis",
        "SCD family history", "Tests", "Test results", "Temperature C", "Pulse bpm", "Blood pressure mmHg",
        "SpO2 percent", "Weight kg", "Height cm", "BMI", "Urine output", "Level of consciousness",
        "Random blood sugar", "Blood sugar unit", "Right eye", "Left eye", "Recommendations or prescription",
        "Referral required", "Referral notes", "Attended by"
    ];
    fputcsv($output, $headers, ",", "\"", "");

    foreach ($attendees as $attendee) {
        $full_name = trim($attendee["first_name"] . " " . $attendee["middle_name"] . " " . $attendee["last_name"]);
        $base = [
            $attendee["registration_number"], $full_name, $attendee["age"], $attendee["gender"],
            $attendee["phone"], $attendee["email"], $attendee["address"], $attendee["district"],
            $attendee["tribe"], $attendee["musdaa_status"], $attendee["days_attend"],
            $attendee["checked_in_days"], $attendee["occupation"], $attendee["course"],
            $attendee["year_of_study"], $attendee["religion"], $attendee["church"],
            $attendee["fellowship"], $attendee["blood_group"], $attendee["emergency_contact_name"],
            $attendee["emergency_contact_phone"], $attendee["emergency_contact_relationship"],
            $attendee["registration_date"]
        ];
        $visits = $services_by_attendee[(int) $attendee["id"]] ?? [[]];

        foreach ($visits as $visit) {
            $height_m = (float) ($visit["height"] ?? 0) / 100;
            $bmi = (float) ($visit["weight"] ?? 0) > 0 && $height_m > 0
                ? number_format((float) $visit["weight"] / ($height_m * $height_m), 1)
                : "";
            $blood_pressure = ($visit["systolic"] ?? "") !== "" || ($visit["diastolic"] ?? "") !== ""
                ? ($visit["systolic"] ?? "") . "/" . ($visit["diastolic"] ?? "")
                : "";
            $row = array_merge($base, [
                $visit["service_date"] ?? "", $visit["service_name"] ?? "",
                $visit["clinical_history"] ?? "", $visit["notes"] ?? "", $visit["diagnosis"] ?? "",
                $visit["scd_family_history"] ?? "", $visit["tests"] ?? "", $visit["result"] ?? "",
                $visit["temperature"] ?? "", $visit["pulse"] ?? "", $blood_pressure,
                $visit["spo2"] ?? "", $visit["weight"] ?? "", $visit["height"] ?? "", $bmi,
                $visit["urine_output"] ?? "", $visit["consciousness"] ?? "", $visit["blood_sugar"] ?? "",
                $visit["blood_sugar_unit"] ?? "", $visit["right_eye"] ?? "", $visit["left_eye"] ?? "",
                $visit["doctor_recommendation"] ?? "", $visit["referral_required"] ?? "",
                $visit["referral_notes"] ?? "", $visit["attended_by"] ?? ""
            ]);
            fputcsv($output, array_map($csv_value, $row), ",", "\"", "");
        }
    }
    fclose($output);
    exit;
}

$total_visits = array_sum(array_map("count", $services_by_attendee));
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>All Person Records | MUSDAA Medical Camp</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="all-records-page">
<header class="topbar">
    <a class="topbar-brand" href="dashboard.php">MUSDAA <span>Medical Camp</span></a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-menu">Menu</button>
    <nav class="topbar-nav" id="main-menu">
        <a href="dashboard.php">Dashboard</a>
        <a href="register.php">Register attendee</a>
        <a href="checkin.php">Daily check-in</a>
        <a href="service_records.php">Medical services</a>
        <a class="active" href="all_records.php" aria-current="page">All Records</a>
        <a href="reports.php">Reports</a>
        <a class="logout-link" href="logout.php">Sign out</a>
    </nav>
</header>
<main class="dashboard all-records-page-main">
    <div class="dashboard-heading all-records-heading">
        <div>
            <a class="history-back" href="dashboard.php">Back</a>
            <p class="eyebrow">Complete records</p>
            <h1>All Person Records</h1>
            <p class="muted-copy">Attendee profiles, camp attendance, and every recorded medical visit.</p>
        </div>
        <div class="all-records-actions">
            <a class="button-link" href="all_records.php?export=excel">Download Excel CSV</a>
            <button type="button" class="button-link" onclick="window.print()">Print / Save as PDF</button>
        </div>
    </div>

    <div class="all-records-summary"><strong><?= count($attendees) ?></strong> people <span aria-hidden="true">·</span> <strong><?= $total_visits ?></strong> medical visits</div>

    <?php if (!$attendees): ?>
        <div class="empty-state"><strong>No attendees registered</strong><small>Once people are registered, their full records will appear here.</small></div>
    <?php else: ?>
        <?php foreach ($attendees as $attendee): ?>
            <?php
            $attendee_id = (int) $attendee["id"];
            $full_name = trim($attendee["first_name"] . " " . $attendee["middle_name"] . " " . $attendee["last_name"]);
            $visits = $services_by_attendee[$attendee_id] ?? [];
            $attendee_details = [
                "Registration number" => $attendee["registration_number"],
                "Age" => $attendee["age"],
                "Gender" => $attendee["gender"],
                "Phone" => $attendee["phone"],
                "Email" => $attendee["email"],
                "Address" => $attendee["address"],
                "District" => $attendee["district"],
                "Tribe" => $attendee["tribe"],
                "Membership" => $attendee["musdaa_status"],
                "Days attending" => $attendee["days_attend"],
                "Camp check-ins" => $attendee["checked_in_days"],
                "Occupation" => $attendee["occupation"],
                "Course" => $attendee["course"],
                "Year of study" => $attendee["year_of_study"],
                "Religion" => $attendee["religion"],
                "Church" => $attendee["church"],
                "Fellowship" => $attendee["fellowship"],
                "Blood group" => $attendee["blood_group"],
                "Emergency contact" => $attendee["emergency_contact_name"],
                "Emergency contact phone" => $attendee["emergency_contact_phone"],
                "Emergency contact relationship" => $attendee["emergency_contact_relationship"],
                "Registered on" => $attendee["registration_date"]
            ];
            ?>
            <details class="all-records-person">
                <summary>
                    <span><strong><?= $escape($full_name) ?></strong><small><?= $escape($attendee["registration_number"]) ?> · <?= $escape($attendee["phone"] ?: "No phone") ?></small></span>
                    <span class="panel-count"><?= count($visits) ?> medical visit<?= count($visits) === 1 ? "" : "s" ?></span>
                </summary>
                <div class="all-records-content">
                    <dl class="all-records-profile">
                        <?php foreach ($attendee_details as $label => $value): ?>
                            <div><dt><?= $escape($label) ?></dt><dd><?= $escape($display_value($value)) ?></dd></div>
                        <?php endforeach; ?>
                    </dl>

                    <h2>Medical history</h2>
                    <?php if (!$visits): ?>
                        <p class="muted-copy">No medical service records recorded.</p>
                    <?php else: ?>
                        <?php foreach ($visits as $visit): ?>
                            <?php
                            $height_m = (float) ($visit["height"] ?? 0) / 100;
                            $bmi = (float) ($visit["weight"] ?? 0) > 0 && $height_m > 0
                                ? number_format((float) $visit["weight"] / ($height_m * $height_m), 1) . " kg/m2"
                                : "Not recorded";
                            $pressure = ($visit["systolic"] ?? "") !== "" || ($visit["diastolic"] ?? "") !== ""
                                ? ($visit["systolic"] ?? "") . " / " . ($visit["diastolic"] ?? "") . " mmHg"
                                : "Not recorded";
                            $visit_details = [
                                "Clinical presentation and history" => $visit["clinical_history"],
                                "Doctor notes" => $visit["notes"],
                                "Diagnosis" => $visit["diagnosis"],
                                "SCD family history" => $visit["scd_family_history"],
                                "Tests performed" => $visit["tests"],
                                "Test results" => $visit["result"],
                                "Temperature" => $visit["temperature"] !== null ? $visit["temperature"] . " C" : "",
                                "Pulse" => $visit["pulse"] !== null ? $visit["pulse"] . " bpm" : "",
                                "Blood pressure" => $pressure,
                                "SpO2" => $visit["spo2"] !== null ? $visit["spo2"] . "%" : "",
                                "Weight" => $visit["weight"] !== null ? $visit["weight"] . " kg" : "",
                                "Height" => $visit["height"] !== null ? $visit["height"] . " cm" : "",
                                "BMI" => $bmi,
                                "Urine output" => $visit["urine_output"],
                                "Level of consciousness" => $visit["consciousness"],
                                "Random blood sugar" => $visit["blood_sugar"] !== null ? $visit["blood_sugar"] . " " . $visit["blood_sugar_unit"] : "",
                                "Right eye" => $visit["right_eye"],
                                "Left eye" => $visit["left_eye"],
                                "Recommendations / prescription" => $visit["doctor_recommendation"],
                                "Referral required" => (int) $visit["referral_required"] ? "Yes" : "No",
                                "Referral notes" => $visit["referral_notes"],
                                "Attended by" => $visit["attended_by"]
                            ];
                            ?>
                            <section class="all-records-visit">
                                <h3><?= $escape(date("M j, Y", strtotime($visit["service_date"]))) ?> · <?= $escape($visit["service_name"] ?: "Medical service") ?></h3>
                                <dl class="all-records-profile all-records-visit-grid">
                                    <?php foreach ($visit_details as $label => $value): ?>
                                        <div><dt><?= $escape($label) ?></dt><dd><?= $escape($display_value($value)) ?></dd></div>
                                    <?php endforeach; ?>
                                </dl>
                            </section>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
<script src="menu.js"></script>
</body>
</html>
