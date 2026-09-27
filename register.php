<?php
require_once "database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $age = (int) ($_POST["age"] ?? 0);
    $gender = $_POST["gender"] ?? "";
    $address = trim($_POST["address"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $days_attend = isset($_POST["days_attend"]) ? implode(", ", $_POST["days_attend"]) : "";
    $musdaa_status = $_POST["musdaa_status"] ?? "";

    $first_name = $name;
    $middle_name = "";
    $last_name = "";
    $consent_given = 0;

    $registration_number = "MUS-" . date("Y") . "-" . strtoupper(substr(uniqid(), -6));

    $sql = "INSERT INTO attendees
        (registration_number, first_name, middle_name, last_name, age, gender,
         phone, address, days_attend, musdaa_status, consent_given)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "ssssisssssi",
        $registration_number,
        $first_name,
        $middle_name,
        $last_name,
        $age,
        $gender,
        $phone,
        $address,
        $days_attend,
        $musdaa_status,
        $consent_given
    );

    if ($stmt->execute()) {
        $message = "<div class='success'>
            Registration successful!<br>
            Registration Number: <strong>" . htmlspecialchars($registration_number) . "</strong>
        </div>";
    } else {
        $message = "<div class='error'>Registration failed. Please try again.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MUSDAA Medical Camp Registration</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="registration-page">
<div class="container registration-card">
<a class="history-back" href="../index.php" onclick="if (window.history.length > 1) { window.history.back(); return false; }">Back</a>
<h1>MUSDAA Medical Camp</h1>
<p class="center">Youth Summit Medical Camp Registration</p>

<?= $message ?>

<form class="registration-form" method="POST">
<div class="form-grid registration-form-grid">

<div class="form-section full"><span>Registration details</span><small>Basic information for the attendee record</small></div>
<div><label for="name">Name *</label><input id="name" type="text" name="name" required></div>
<div><label for="age">Age *</label><input id="age" type="number" name="age" min="1" max="120" required></div>

<div>
<label for="gender">Gender *</label>
<select id="gender" name="gender" required>
<option value="">Select gender</option>
<option value="Male">Male</option>
<option value="Female">Female</option>
<option value="Other">Other</option>
</select>
</div>

<div class="full"><label for="address">Address *</label><input id="address" type="text" name="address" required></div>
<div><label for="phone">Contact *</label><input id="phone" type="text" name="phone" required></div>

<div class="full"><label>Days attend *</label><div class="choice-grid">
<?php for ($day = 1; $day <= 7; $day++): ?>
<label><input type="checkbox" name="days_attend[]" value="Day <?= $day ?>"> Day <?= $day ?></label>
<?php endfor; ?>
</div></div>

<div class="full"><label>Membership *</label><div class="choice-grid">
<label><input type="radio" name="musdaa_status" value="MUSDAA" required> MUSDAA</label>
<label><input type="radio" name="musdaa_status" value="Nonmusdaa"> Nonmusdaa</label>
</div></div>

<div class="full registration-footer">
<div class="full"><button type="submit">Register for Medical Camp</button></div>
</div>
</div>
</form>
</div>
</body>
</html>
