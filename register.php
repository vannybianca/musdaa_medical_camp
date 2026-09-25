<?php
require_once "../config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first_name = trim($_POST["first_name"]);
    $middle_name = trim($_POST["middle_name"]);
    $last_name = trim($_POST["last_name"]);
    $gender = $_POST["gender"];
    $date_of_birth = $_POST["date_of_birth"] ?: null;
    $phone = trim($_POST["phone"]);
    $email = trim($_POST["email"]);
    $district = trim($_POST["district"]);
    $address = trim($_POST["address"]);
    $occupation = trim($_POST["occupation"]);
    $course = trim($_POST["course"]);
    $year_of_study = trim($_POST["year_of_study"]);
    $religion = trim($_POST["religion"]);
    $church = trim($_POST["church"]);
    $fellowship = trim($_POST["fellowship"]);
    $emergency_name = trim($_POST["emergency_contact_name"]);
    $emergency_phone = trim($_POST["emergency_contact_phone"]);
    $emergency_relationship = trim($_POST["emergency_contact_relationship"]);
    $blood_group = trim($_POST["blood_group"]);
    $consent_given = isset($_POST["consent_given"]) ? 1 : 0;

    $registration_number = "MUS-" . date("Y") . "-" . strtoupper(substr(uniqid(), -6));

    $sql = "INSERT INTO attendees
        (registration_number, first_name, middle_name, last_name, gender,
         date_of_birth, phone, address, course, year_of_study, religion,
         church, emergency_contact_relationship, blood_group, consent_given,
         email, district, occupation, fellowship, emergency_contact_name,
         emergency_contact_phone)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "sssssssssssssssiissss",
        $registration_number,
        $first_name,
        $middle_name,
        $last_name,
        $gender,
        $date_of_birth,
        $phone,
        $address,
        $course,
        $year_of_study,
        $religion,
        $church,
        $emergency_relationship,
        $blood_group,
        $consent_given,
        $email,
        $district,
        $occupation,
        $fellowship,
        $emergency_name,
        $emergency_phone
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
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="registration-page">
<div class="container registration-card">
<a class="history-back" href="../index.php" onclick="if (window.history.length > 1) { window.history.back(); return false; }">Back</a>
<h1>MUSDAA Medical Camp</h1>
<p class="center">Youth Summit Medical Camp Registration</p>

<?= $message ?>

<form class="registration-form" method="POST">
<div class="form-grid registration-form-grid">

<div class="form-section full"><span>Personal details</span><small>Basic information for the attendee record</small></div>
<div><label>First Name *</label><input type="text" name="first_name" required></div>
<div><label>Middle Name</label><input type="text" name="middle_name"></div>
<div><label>Last Name *</label><input type="text" name="last_name" required></div>

<div>
<label>Gender *</label>
<select name="gender" required>
<option value="">Select gender</option>
<option value="Male">Male</option>
<option value="Female">Female</option>
<option value="Other">Other</option>
</select>
</div>

<div><label>Date of Birth</label><input type="date" name="date_of_birth"></div>
<div><label>Phone Number *</label><input type="text" name="phone" required></div>
<div><label>Email</label><input type="email" name="email"></div>
<div><label>District</label><input type="text" name="district"></div>

<div class="form-section full"><span>Background</span><small>Education, work, and church information</small></div>
<div class="full"><label>Address</label><input type="text" name="address"></div>
<div><label>Course</label><input type="text" name="course"></div>
<div><label>Year of Study</label><input type="text" name="year_of_study"></div>
<div><label>Occupation</label><input type="text" name="occupation"></div>
<div><label>Religion</label><input type="text" name="religion"></div>
<div><label>Church</label><input type="text" name="church"></div>
<div><label>Fellowship</label><input type="text" name="fellowship"></div>

<div class="form-section full"><span>Health and emergency contact</span><small>Information that helps the camp team respond quickly</small></div>
<div>
<label>Blood Group</label>
<select name="blood_group">
<option value="">Select</option>
<option value="A+">A+</option>
<option value="A-">A-</option>
<option value="B+">B+</option>
<option value="B-">B-</option>
<option value="AB+">AB+</option>
<option value="AB-">AB-</option>
<option value="O+">O+</option>
<option value="O-">O-</option>
</select>
</div>

<div><label>Emergency contact name</label><input type="text" name="emergency_contact_name"></div>
<div><label>Emergency contact phone</label><input type="text" name="emergency_contact_phone"></div>
<div><label>Relationship</label><input type="text" name="emergency_contact_relationship"></div>

<div class="full registration-footer">
<div class="consent">
<label><input type="checkbox" name="consent_given" required>
I consent to the collection and use of my information for purposes related to this medical camp.</label>
</div>

<div class="full"><button type="submit">Register for Medical Camp</button></div>
</div>
</div>
</form>
</div>
</body>
</html>
