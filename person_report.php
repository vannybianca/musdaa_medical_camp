<?php
session_start();
require_once "database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$attendee_id = (int) ($_GET["attendee_id"] ?? 0);
if ($attendee_id < 1) {
    http_response_code(400);
    exit("Invalid attendee.");
}

$attendee_stmt = $conn->prepare(
    "SELECT registration_number, first_name, middle_name, last_name, gender, age,
            phone, address, musdaa_status
     FROM attendees WHERE id = ? LIMIT 1"
);
$attendee_stmt->bind_param("i", $attendee_id);
$attendee_stmt->execute();
$attendee = $attendee_stmt->get_result()->fetch_assoc();

if (!$attendee) {
    http_response_code(404);
    exit("Attendee not found.");
}

$record_stmt = $conn->prepare(
    "SELECT m.service_name, service_date, tests, temperature, pulse, systolic, diastolic, spo2,
            urine_output, blood_sugar, consciousness, notes, result,
            doctor_recommendation, referral_required, referral_notes
     FROM service_records
     JOIN medical_services m ON m.id = service_records.service_id
     WHERE attendee_id = ?
     ORDER BY service_date DESC, created_at DESC"
);
$record_stmt->bind_param("i", $attendee_id);
$record_stmt->execute();
$records = $record_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$checkin_stmt = $conn->prepare(
    "SELECT s.day_number, s.event_date
     FROM daily_checkins d
     JOIN summit_days s ON s.id = d.summit_day_id
     WHERE d.attendee_id = ? ORDER BY s.day_number"
);
$checkin_stmt->bind_param("i", $attendee_id);
$checkin_stmt->execute();
$checkins = $checkin_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function pdf_clean($value): string
{
    $value = (string) ($value ?? "");
    $value = str_replace(["\r", "\n"], " ", $value);
    return preg_replace('/[^\x20-\x7E]/', '', $value) ?: "";
}

function pdf_escape($value): string
{
    return str_replace(["\\", "(", ")"], ["\\\\", "\\(", "\\)"], pdf_clean($value));
}

function pdf_value($value, string $empty = "Not recorded"): string
{
    return pdf_clean($value) !== "" ? pdf_clean($value) : $empty;
}

$full_name = trim($attendee["first_name"] . " " . $attendee["middle_name"] . " " . $attendee["last_name"]);
$pages = [[]];
$y = 700;

$add_text = function (string $text, int $size = 10, string $font = "F1", array $color = [0.09, 0.2, 0.23]) use (&$pages, &$y): void {
    if ($y < 55) {
        $pages[] = [];
        $y = 700;
    }
    $wrapped = wordwrap(pdf_clean($text), max(1, (int) (92 - ($size - 9) * 3)), "\n", true);
    foreach (explode("\n", $wrapped) as $line) {
        if ($y < 55) {
            $pages[] = [];
            $y = 700;
        }
        $pages[count($pages) - 1][] = sprintf(
            "0 0 0 rg BT /%s %d Tf 1 0 0 1 42 %d Tm (%s) Tj ET",
            $font, $size, $y, pdf_escape($line)
        );
        $y -= $size + 6;
    }
};

$add_section = function (string $title) use (&$pages, &$y, $add_text): void {
    if ($y < 90) {
        $pages[] = [];
        $y = 700;
    }
    $pages[count($pages) - 1][] = sprintf(
        "0.91 0.96 0.94 rg 42 %d 511 24 re f",
        $y - 5
    );
    $add_text($title, 11, "F2", [0.03, 0.38, 0.36]);
    $y -= 5;
};

$add_text("MEDICAL RESULTS REPORT", 18, "F2", [0.09, 0.2, 0.23]);
$add_text("To: Medical Records File", 10);
$add_text("Date: " . date("F j, Y"), 10);
$add_text("Subject: Medical results for " . pdf_value($full_name), 10, "F2");
$y -= 8;

$add_section("ATTENDEE DETAILS");
$add_text("Name: " . pdf_value($full_name));
$add_text("Registration number: " . pdf_value($attendee["registration_number"]));
$add_text("Age: " . pdf_value($attendee["age"]));
$add_text("Gender: " . pdf_value($attendee["gender"]));
$add_text("Contact: " . pdf_value($attendee["phone"]));
$add_text("Address: " . pdf_value($attendee["address"]));
$add_text("Membership: " . pdf_value($attendee["musdaa_status"]));
$add_text("Camp days attended: " . ($checkins ? implode(", ", array_map(fn($row) => "Day " . $row["day_number"], $checkins)) : "None recorded"));
$y -= 8;

if (!$records) {
    $add_section("MEDICAL RESULTS");
    $add_text("No medical results have been recorded for this attendee.");
} else {
    foreach ($records as $index => $record) {
        $add_section("MEDICAL VISIT " . ($index + 1) . " - " . date("F j, Y", strtotime($record["service_date"])));
        $add_text("Service: " . pdf_value($record["service_name"]), 10, "F2");
        $add_text("Doctor's notes: " . pdf_value($record["notes"]));
        $add_text("Tests performed: " . pdf_value($record["tests"]));
        $add_text("Test results: " . pdf_value($record["result"]));
        $add_text("Temperature: " . pdf_value($record["temperature"]) . " C");
        $add_text("Pulse: " . pdf_value($record["pulse"]) . " bpm");
        $add_text("Blood pressure: " . pdf_value($record["systolic"]) . " / " . pdf_value($record["diastolic"]) . " mmHg");
        $add_text("SpO2: " . pdf_value($record["spo2"]) . "%");
        $add_text("Urine output: " . pdf_value($record["urine_output"]));
        $add_text("Blood glucose: " . pdf_value($record["blood_sugar"]));
        $add_text("Level of consciousness: " . pdf_value($record["consciousness"]));
        $add_text("Doctor's recommendations or prescription: " . pdf_value($record["doctor_recommendation"]));
        if ((int) $record["referral_required"]) {
            $add_text("Referral: Required. " . pdf_value($record["referral_notes"]));
        }
        $y -= 8;
    }
}

$add_text("Yours faithfully,", 10);
$y -= 18;
$add_text("MUSDAA Medical Camp Medical Team", 10, "F2");

$objects = [];
$objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
$objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
$objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";
$logo_data = file_get_contents(__DIR__ . "/pdf-logo.jpg");
$objects[5] = "<< /Type /XObject /Subtype /Image /Width 278 /Height 165 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($logo_data) . " >>\nstream\n" . $logo_data . "\nendstream";
$page_ids = [];
$next_id = 6;
$page_number = 1;
foreach ($pages as $page) {
    $header = [
        "q 90 0 0 54 42 760 cm /Im1 Do Q",
        "BT /F2 13 Tf 1 0 0 1 145 802 Tm (MUSDAA MEDICAL CAMP) Tj ET",
        "BT /F1 8 Tf 1 0 0 1 145 788 Tm (Youth Summit Medical Camp) Tj ET",
        "0.03 0.5 0.48 RG 42 744 m 553 744 l S",
    ];
    $footer = [
        "0.82 0.88 0.86 RG 42 42 m 553 42 l S",
        "BT /F1 8 Tf 1 0 0 1 42 27 Tm (Confidential medical record - MUSDAA Medical Camp) Tj ET",
        "BT /F1 8 Tf 1 0 0 1 520 27 Tm (Page " . $page_number . ") Tj ET",
    ];
    $content = implode("\n", array_merge($header, $page, $footer));
    $content_id = $next_id++;
    $page_id = $next_id++;
    $objects[$content_id] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
    $objects[$page_id] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> /XObject << /Im1 5 0 R >> >> /Contents " . $content_id . " 0 R >>";
    $page_ids[] = $page_id . " 0 R";
    $page_number++;
}
$objects[2] = "<< /Type /Pages /Kids [" . implode(" ", $page_ids) . "] /Count " . count($page_ids) . " >>";

$pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
$offsets = [0];
ksort($objects);
foreach ($objects as $id => $object) {
    $offsets[$id] = strlen($pdf);
    $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
}
$xref = strlen($pdf);
$pdf .= "xref\n0 " . (max(array_keys($objects)) + 1) . "\n0000000000 65535 f \n";
for ($id = 1; $id <= max(array_keys($objects)); $id++) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
}
$pdf .= "trailer\n<< /Size " . (max(array_keys($objects)) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

$filename = preg_replace('/[^A-Za-z0-9_-]/', '_', $full_name ?: "attendee") . "_medical_results.pdf";
header("Content-Type: application/pdf");
header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
header("Content-Length: " . strlen($pdf));
echo $pdf;
