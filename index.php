<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MUSDAA Medical Camp</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="home-page">
<div class="container small home-card">
    <!-- <a class="history-back" href="index.php" onclick="if (window.history.length > 1) { window.history.back(); return false; }">Back</a> -->
    <div class="home-copy">
        <img class="home-logo" src="Logo.png" alt="Makerere University Seventh-day Adventist Association">
        <p class="eyebrow association-name">Makerere University Seventh-day Adventist Association (MUSDAA)</p>
        <h1 class="camp-title">MEDICAL CAMP</h1>
        <p class="home-subtitle">Youth Summit Registration &amp; Attendance System</p>
    </div>

    <div class="home-actions">
        <a class="button-link" href="login.php">
            <span class="action-copy"><strong>Login</strong><small>Manage camp operations</small></span>
            <span aria-hidden="true">&#8594;</span>
        </a>
        <div class="secondary-actions">
            <a class="action-link" href="registration/register.php">
                <span class="action-copy"><strong>Register an attendee</strong><small>Add a new camp participant</small></span>
            </a>
            <a class="action-link" href="checkin/checkin.php">
                <span class="action-copy"><strong>Daily check-in</strong><small>Record today's attendance</small></span>
            </a>
        </div>
    </div>

    <footer class="site-footer">We're His Hands</footer>
</div>
</body>
</html>
