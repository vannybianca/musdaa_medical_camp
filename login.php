<?php
session_start();
require_once "config/database.php";

$error = "";
$username = "";

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare(
        "SELECT id, full_name, username, password, role FROM users WHERE username = ? LIMIT 1"
    );
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    $valid_password = $user && (
        password_verify($password, $user["password"]) ||
        hash_equals($user["password"], $password)
    );

    if ($valid_password) {
        if (!password_get_info($user["password"])["algo"]) {
            $new_password = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param("si", $new_password, $user["id"]);
            $update->execute();
        }

        session_regenerate_id(true);
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["full_name"] = $user["full_name"];
        $_SESSION["role"] = $user["role"];
        header("Location: dashboard.php");
        exit;
    }

    $error = "The username or password is incorrect.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | MUSDAA Medical Camp</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
<main class="login-shell">
    <section class="login-intro">
        <a class="brand-mark" href="index.php" aria-label="MUSDAA Medical Camp home">
            <img src="Logo.png" alt="Makerere University Seventh-day Adventist Association">
        </a>
        <p class="eyebrow">MUSDAA 2026</p>
        <h1>Care starts with a welcome.</h1>
        <p class="intro-copy">Sign in to manage medical camp registration and daily attendance.</p>
        <div class="intro-note">
            <span class="note-dot"></span>
            <span>Staff access portal</span>
        </div>
    </section>

    <section class="login-card" aria-labelledby="login-heading">
        <div class="login-heading">
            <p class="eyebrow">Secure access</p>
            <h2 id="login-heading">Welcome back</h2>
            <p>Use your staff account to continue.</p>
        </div>

        <?php if ($error): ?>
            <div class="error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="on">
            <div class="field-group">
                <label for="username">Username</label>
                <input id="username" type="text" name="username" value="<?= htmlspecialchars($username) ?>" autocomplete="username" required autofocus>
            </div>

            <div class="field-group">
                <div class="label-row">
                    <label for="password">Password</label>
                </div>
                <input id="password" type="password" name="password" autocomplete="current-password" required>
            </div>

            <button type="submit">Sign in <span aria-hidden="true">&#8594;</span></button>
        </form>

    </section>
</main>
</body>
</html>
