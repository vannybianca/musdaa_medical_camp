<?php
require_once "database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $day_number = (int) ($_POST["day_number"] ?? 0);
    if (($_POST["action"] ?? "") === "checkin_non_musdaa") {
        $selected_attendee_id = (int) ($_POST["non_musdaa_attendee_id"] ?? 0);
        $stmt = $conn->prepare(
            "SELECT id, first_name, middle_name, last_name
             FROM attendees WHERE id = ? AND musdaa_status = 'Nonmusdaa' LIMIT 1"
        );
        $stmt->bind_param("i", $selected_attendee_id);
    } else {
        $attendee_name = trim($_POST["attendee_name"] ?? "");
        $stmt = $conn->prepare(
            "SELECT id, first_name, middle_name, last_name
             FROM attendees
             WHERE CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name) = ?
             LIMIT 1"
        );
        $stmt->bind_param("s", $attendee_name);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $message = "<div class='error'>Attendee not found.</div>";
    } else {
        $attendee = $result->fetch_assoc();
        $attendee_id = $attendee["id"];

        $stmt2 = $conn->prepare(
            "SELECT id FROM summit_days WHERE day_number = ?"
        );
        $stmt2->bind_param("i", $day_number);
        $stmt2->execute();
        $day_result = $stmt2->get_result();

        if ($day_result->num_rows === 0) {
            $message = "<div class='error'>Invalid summit day.</div>";
        } else {
            $day = $day_result->fetch_assoc();
            $summit_day_id = $day["id"];

            $stmt3 = $conn->prepare(
                "SELECT id FROM daily_checkins
                 WHERE attendee_id = ? AND summit_day_id = ?"
            );
            $stmt3->bind_param("ii", $attendee_id, $summit_day_id);
            $stmt3->execute();
            $already = $stmt3->get_result();

            if ($already->num_rows > 0) {
                $message = "<div class='warning'>
                    " . htmlspecialchars($attendee["first_name"]) .
                    " has already checked in for Day " . $day_number . ".
                </div>";
            } else {
                $staff = "Registration Desk";

                $stmt4 = $conn->prepare(
                    "INSERT INTO daily_checkins
                     (attendee_id, summit_day_id, checked_in_by)
                     VALUES (?, ?, ?)"
                );
                $stmt4->bind_param("iis", $attendee_id, $summit_day_id, $staff);

                if ($stmt4->execute()) {
                    $checkin_id = $conn->insert_id;
                    $timestamp_stmt = $conn->prepare(
                        "SELECT checkin_time FROM daily_checkins WHERE id = ?"
                    );
                    $timestamp_stmt->bind_param("i", $checkin_id);
                    $timestamp_stmt->execute();
                    $checkin_time = $timestamp_stmt->get_result()->fetch_assoc()["checkin_time"];
                    $formatted_time = date("F j, Y \a\t g:i A", strtotime($checkin_time));

                    $full_name = trim(
                        $attendee["first_name"] . " " .
                        $attendee["middle_name"] . " " .
                        $attendee["last_name"]
                    );

                    $message = "<div class='success'>
                        <h2>Check-in Successful!</h2>
                        <p><strong>" . htmlspecialchars($full_name) . "</strong></p>
                        <p>Day " . $day_number . " attendance recorded.</p>
                        <p class='checkin-time'><span>Checked in</span><strong>" .
                        htmlspecialchars($formatted_time) . "</strong></p>
                    </div>";
                } else {
                    $message = "<div class='error'>Could not record check-in.</div>";
                }
            }
        }
    }
}

$attendee_options = [];
$non_musdaa_options = [];
$today_day_result = $conn->query("SELECT day_number FROM summit_days WHERE event_date = CURDATE() LIMIT 1");
$today_day = $today_day_result ? (int) ($today_day_result->fetch_assoc()["day_number"] ?? 0) : 0;
$non_musdaa_result = $conn->query(
    "SELECT id, registration_number, first_name, middle_name, last_name, phone, age, gender, address, tribe
     FROM attendees WHERE musdaa_status = 'Nonmusdaa' ORDER BY first_name, last_name"
);
if ($non_musdaa_result) {
    while ($row = $non_musdaa_result->fetch_assoc()) {
        $non_musdaa_options[] = [
            "id" => (int) $row["id"],
            "registration_number" => $row["registration_number"],
            "name" => trim($row["first_name"] . " " . $row["middle_name"] . " " . $row["last_name"]),
            "phone" => $row["phone"],
            "age" => $row["age"],
            "gender" => $row["gender"],
            "address" => $row["address"],
            "tribe" => $row["tribe"]
        ];
    }
}
$attendee_result = $conn->query(
    "SELECT first_name, middle_name, last_name
     FROM attendees
     ORDER BY first_name, last_name"
);
if ($attendee_result) {
    while ($row = $attendee_result->fetch_assoc()) {
        $attendee_options[] = trim(
            $row["first_name"] . " " .
            $row["middle_name"] . " " .
            $row["last_name"]
        );
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MUSDAA Daily Check-In</title>
<link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . "/style.css") ?>">
<style>
.checkin-shell { background: #edf5f2; display: block; height: auto; margin: 0 auto; min-height: 100vh; overflow: visible; position: relative; width: min(100%, 1000px); }
.checkin-card { min-height: 100vh; }
@media (max-width: 700px) {
    .checkin-shell { width: 100%; }
    .checkin-card { min-height: auto; }
}
</style>
</head>
<body class="checkin-page">
<main class="checkin-shell">
<div class="checkin-top-actions">
    <a class="history-back checkin-back" href="../index.php" onclick="if (window.history.length > 1) { window.history.back(); return false; }">Back</a>
</div>
<section class="checkin-card" aria-labelledby="checkin-heading">
    <button class="theme-toggle" id="theme_toggle" type="button" aria-label="Switch to dark mode" aria-pressed="false">
        <span class="theme-icon sun-icon" aria-hidden="true">&#9788;</span>
        <span class="theme-icon moon-icon" aria-hidden="true">&#9790;</span>
    </button>
    <div class="checkin-heading">
        <h2 id="checkin-heading">Daily Check-In</h2>
        <p class="checkin-instructions">Search for the attendee, choose today&apos;s camp day, and submit the form. After a successful check-in, proceed to the medical services desk.</p>
    </div>

    <?= $message ?>

    <form method="POST">
    <label for="attendee_name">Attendee name</label>
    <div class="attendee-search">
        <span class="search-icon" aria-hidden="true"></span>
        <input id="attendee_name" type="text" name="attendee_name"
            placeholder="Search registered attendees" autocomplete="off" required autofocus>
        <div class="attendee-suggestions" id="attendee_suggestions" role="listbox" aria-label="Matching attendees"></div>
    </div>

    <label for="day_number">Camp day</label>
    <select id="day_number" name="day_number" required>
    <option value="">Choose camp day</option>
    <?php for ($day = 1; $day <= 7; $day++): ?><option value="<?= $day ?>" <?= $day === $today_day ? "selected" : "" ?>>Day <?= $day ?></option><?php endfor; ?>
    </select>

    <button type="submit">Check in attendee <span aria-hidden="true">&#8594;</span></button>
    <p class="checkin-note">Attendance is recorded once per attendee for each camp day.</p>
    </form>

    <details class="quick-register-details">
        <summary>Check in a non-MUSDAA member</summary>
        <p class="checkin-note">Select a registered member to view their details and record today's attendance.</p>
        <form method="POST">
            <input type="hidden" name="action" value="checkin_non_musdaa">
            <label for="non_musdaa_attendee_id">Registered member</label>
            <select id="non_musdaa_attendee_id" name="non_musdaa_attendee_id" required <?= !$non_musdaa_options ? "disabled" : "" ?>>
                <option value="">Choose a non-MUSDAA member</option>
                <?php foreach ($non_musdaa_options as $member): ?>
                    <option value="<?= $member["id"] ?>"><?= htmlspecialchars($member["name"] . " - " . ($member["phone"] ?: "No phone"), ENT_QUOTES, "UTF-8") ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!$non_musdaa_options): ?><p class="checkin-note">No registered non-MUSDAA members found.</p><?php endif; ?>
            <dl class="non-musdaa-member-details" id="non_musdaa_member_details" hidden>
                <div><dt>Registration</dt><dd data-member-field="registration_number"></dd></div>
                <div><dt>Contact</dt><dd data-member-field="phone"></dd></div>
                <div><dt>Age and gender</dt><dd data-member-field="age_gender"></dd></div>
                <div><dt>Address</dt><dd data-member-field="address"></dd></div>
                <div><dt>Tribe</dt><dd data-member-field="tribe"></dd></div>
            </dl>
            <label for="non_musdaa_day_number">Camp day</label>
            <select id="non_musdaa_day_number" name="day_number" required>
                <option value="">Choose camp day</option>
                <?php for ($day = 1; $day <= 7; $day++): ?><option value="<?= $day ?>" <?= $day === $today_day ? "selected" : "" ?>>Day <?= $day ?></option><?php endfor; ?>
            </select>
            <button type="submit" <?= !$non_musdaa_options ? "disabled" : "" ?>>Check in selected member <span aria-hidden="true">&#8594;</span></button>
        </form>
    </details>
</section>
</main>
<script>
const attendeeNames = <?= json_encode($attendee_options, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const nonMusdaaMembers = <?= json_encode($non_musdaa_options, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const attendeeInput = document.getElementById('attendee_name');
const attendeeSuggestions = document.getElementById('attendee_suggestions');
const themeToggle = document.getElementById('theme_toggle');
const nonMusdaaSelect = document.getElementById('non_musdaa_attendee_id');
const nonMusdaaDetails = document.getElementById('non_musdaa_member_details');

function setTheme(isDark) {
    document.body.classList.toggle('dark-mode', isDark);
    themeToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
    themeToggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
}

const savedTheme = localStorage.getItem('musdaa-checkin-theme');
setTheme(savedTheme === 'dark');
themeToggle.addEventListener('click', () => {
    const isDark = !document.body.classList.contains('dark-mode');
    setTheme(isDark);
    localStorage.setItem('musdaa-checkin-theme', isDark ? 'dark' : 'light');
});

function renderAttendeeSuggestions() {
    const query = attendeeInput.value.trim().toLowerCase();
    attendeeSuggestions.innerHTML = '';
    if (!query) {
        attendeeSuggestions.classList.remove('is-visible');
        return;
    }

    const matches = attendeeNames.filter((name) => name.toLowerCase().includes(query)).slice(0, 6);
    matches.forEach((name) => {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'attendee-suggestion';
        option.setAttribute('role', 'option');
        option.textContent = name;
        option.addEventListener('click', () => {
            attendeeInput.value = name;
            attendeeSuggestions.classList.remove('is-visible');
            attendeeInput.focus();
        });
        attendeeSuggestions.appendChild(option);
    });

    if (matches.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'attendee-suggestion-empty';
        empty.textContent = 'No matching attendee found';
        attendeeSuggestions.appendChild(empty);
    }
    attendeeSuggestions.classList.add('is-visible');
}

attendeeInput.addEventListener('input', renderAttendeeSuggestions);
attendeeInput.addEventListener('focus', renderAttendeeSuggestions);
document.addEventListener('click', (event) => {
    if (!event.target.closest('.attendee-search')) {
        attendeeSuggestions.classList.remove('is-visible');
    }
});

if (nonMusdaaSelect) {
    const nonMusdaaById = new Map(nonMusdaaMembers.map((member) => [String(member.id), member]));
    nonMusdaaSelect.addEventListener('change', () => {
        const member = nonMusdaaById.get(nonMusdaaSelect.value);
        nonMusdaaDetails.hidden = !member;
        if (!member) return;
        const details = {
            registration_number: member.registration_number || 'Not recorded',
            phone: member.phone || 'Not recorded',
            age_gender: [member.age ? `Age ${member.age}` : '', member.gender || ''].filter(Boolean).join(' · ') || 'Not recorded',
            address: member.address || 'Not recorded',
            tribe: member.tribe || 'Not recorded'
        };
        Object.entries(details).forEach(([field, value]) => {
            nonMusdaaDetails.querySelector(`[data-member-field="${field}"]`).textContent = value;
        });
    });
}
</script>
</body>
</html>
