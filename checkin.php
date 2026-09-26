<?php
require_once "database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $attendee_name = trim($_POST["attendee_name"] ?? "");
    $day_number = intval($_POST["day_number"]);

    $stmt = $conn->prepare(
        "SELECT id, first_name, middle_name, last_name
         FROM attendees
         WHERE CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name) = ?
         LIMIT 1"
    );
    $stmt->bind_param("s", $attendee_name);
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
<link rel="stylesheet" href="style.css">
</head>
<body class="checkin-page">
<main class="checkin-shell">
<div class="checkin-top-actions">
    <a class="history-back checkin-back" href="../index.php" onclick="if (window.history.length > 1) { window.history.back(); return false; }">Back</a>
</div>
<section class="checkin-intro">
    <a class="checkin-brand" href="../index.php" aria-label="MUSDAA Medical Camp home">
        <img src="../Logo.png" alt="Makerere University Seventh-day Adventist Association">
    </a>
    <div class="checkin-intro-copy">
        <h1>MEDICAL CAMP 2026</h1>
        <p class="checkin-tagline">Every check-in, counted.</p>
        <p>Keep daily attendance accurate, quick, and ready for the people your camp serves.</p>
    </div>
</section>

<section class="checkin-card" aria-labelledby="checkin-heading">
    <button class="theme-toggle" id="theme_toggle" type="button" aria-label="Switch to dark mode" aria-pressed="false">
        <span class="theme-icon sun-icon" aria-hidden="true">&#9788;</span>
        <span class="theme-icon moon-icon" aria-hidden="true">&#9790;</span>
    </button>
    <div class="checkin-heading">
        <div class="page-kicker">Attendance desk</div>
        <h2 id="checkin-heading">Daily Check-In</h2>
        <p class="intro-text">Record today’s attendance by searching for an attendee by name.</p>
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
    <small class="field-help">Start typing to search registered attendees.</small>

    <label for="day_number">Camp day</label>
    <select id="day_number" name="day_number" required>
    <option value="">Choose camp day</option>
    <option value="1">Day 1</option>
    <option value="2">Day 2</option>
    <option value="3">Day 3</option>
    <option value="4">Day 4</option>
    <option value="5">Day 5</option>
    <option value="6">Day 6</option>
    <option value="7">Day 7</option>
    </select>

    <button type="submit">Check in attendee <span aria-hidden="true">&#8594;</span></button>
    </form>
</section>
</main>
<script>
const attendeeNames = <?= json_encode($attendee_options, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const attendeeInput = document.getElementById('attendee_name');
const attendeeSuggestions = document.getElementById('attendee_suggestions');
const themeToggle = document.getElementById('theme_toggle');

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
</script>
</body>
</html>
