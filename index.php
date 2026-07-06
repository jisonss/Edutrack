<?php
if (isset($_COOKIE[session_name()]) && !preg_match('/^[a-zA-Z0-9,-]+$/', $_COOKIE[session_name()])) {
    unset($_COOKIE[session_name()]);
    session_id(bin2hex(random_bytes(16)));
}
session_start();
require 'config.php';

$errors = [
    'login' => $_SESSION['login_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? ''
];
$activeForm = $_SESSION['active_form'] ?? 'login';

function showError($error) {
    return !empty($error) ? "<p class='error-message'>$error</p>" : '';
}
function isActiveForm($formName, $activeForm) {
    return $formName === $activeForm ? 'active' : '';
}

// Load sections from DB
$sections = [];
$res = $conn->query("SELECT id, section_name FROM sections ORDER BY section_name");
while ($row = $res->fetch_assoc()) $sections[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduTrack</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="container">

    <!-- LOGIN FORM -->
    <div class="form-box <?= isActiveForm('login', $activeForm); ?>" id="login-form">
        <form action="research_project.php" method="post">
            <h2>Login</h2>
            <?= showError($errors['login']); ?>

            <select name="role" required>
                <option value="">--Select Role--</option>
                <option value="user">Student</option>
                <option value="admin">Teacher</option>
            </select>

            <select name="section_id" id="loginSection" required>
                <option value="">--Select Section--</option>
                <?php foreach ($sections as $s): ?>
                    <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['section_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <input type="text" name="email_or_lrn" placeholder="Email (Teacher) / Email or LRN (Student)" required>
            <input type="password" name="password" placeholder="Password" required>

            <button type="submit" name="login">Login</button>
            <p>Don't have an account? <a href="#" onclick="showForm('register-form')">Register</a></p>
        </form>
    </div>

    <!-- REGISTER FORM -->
    <div class="form-box <?= isActiveForm('register', $activeForm); ?>" id="register-form">
        <form action="research_project.php" method="post" id="registerForm">
            <h2>Register</h2>
            <?= showError($errors['register']); ?>

            <select name="role" id="roleSelect" onchange="toggleFields()" required>
                <option value="">--Select Role--</option>
                <option value="user">Student</option>
                <option value="admin">Teacher</option>
            </select>

            <!-- Student: one section -->
            <select name="section_id" id="studentSection" style="display:none;">
                <option value="">-- Select Section --</option>
                <?php foreach ($sections as $s): ?>
                    <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['section_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Teacher: multi sections -->
            <select name="section_ids[]" id="teacherSections" multiple style="display:none;">
                <?php foreach ($sections as $s): ?>
                    <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['section_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <small id="teacherHint" style="display:none;">
                Hold Ctrl (Windows) / Cmd (Mac) to select multiple sections.
            </small>

            <input type="text" name="name" id="nameInput" placeholder="Full Name" style="display:none;">
            <input type="text" name="lrn" id="lrnInput" placeholder="12-digit LRN" style="display:none;">
            <input type="email" name="email" id="emailInput" placeholder="Email" style="display:none;">
            <input type="password" name="password" id="passwordInput" placeholder="Password" style="display:none;">

            <button type="submit" name="register">Register</button>
            <p>Already have an account? <a href="#" onclick="showForm('login-form')">Login</a></p>
        </form>
    </div>

</div>

<script src="script.js"></script>
</body>
</html>

<?php
unset($_SESSION['login_error'], $_SESSION['register_error'], $_SESSION['active_form']);
