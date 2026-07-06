<?php
if (isset($_COOKIE[session_name()]) && !preg_match('/^[a-zA-Z0-9,-]+$/', $_COOKIE[session_name()])) {
    unset($_COOKIE[session_name()]);
    session_id(bin2hex(random_bytes(16)));
}
session_start();
require 'config.php';

function redirect_with_error(string $form, string $msg) {
    $_SESSION['active_form'] = $form;
    if ($form === 'login') $_SESSION['login_error'] = $msg;
    if ($form === 'register') $_SESSION['register_error'] = $msg;
    header("Location: index.php");
    exit();
}

function password_column(mysqli $conn): string {
    // Detect which password column exists in users table
    $col = '';
    $res = $conn->query("SHOW COLUMNS FROM users");
    while ($row = $res->fetch_assoc()) {
        if ($row['Field'] === 'password_hash') return 'password_hash';
        if ($row['Field'] === 'password') $col = 'password';
    }
    // prefer password_hash if exists, else password if exists
    if ($col !== '') return $col;

    // If neither exists, stop with a clear error
    redirect_with_error('register', "Database error: users table has no password column. Add 'password_hash' or 'password'.");
    return 'password_hash';
}

$pwdCol = password_column($conn);

// ------------------------------ REGISTER ------------------------------
if (isset($_POST['register'])) {
    $role = trim($_POST['role'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $plain = $_POST['password'] ?? '';

    if ($role !== 'user' && $role !== 'admin') {
        redirect_with_error('register', "Please select a valid role.");
    }
    if ($name === '' || $email === '' || $plain === '') {
        redirect_with_error('register', "Please fill in all required fields.");
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect_with_error('register', "Invalid email format.");
    }
    if (strlen($plain) < 6) {
        redirect_with_error('register', "Password must be at least 6 characters.");
    }

    $hash = password_hash($plain, PASSWORD_DEFAULT);

    // Check email already exists
    $chk = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $chk->bind_param("s", $email);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        redirect_with_error('register', "Email already registered.");
    }

    if ($role === 'user') {
        // STUDENT registration
        $lrn = trim($_POST['lrn'] ?? '');
        $section_id = (int)($_POST['section_id'] ?? 0);

        if ($lrn === '' || !preg_match('/^\d{12}$/', $lrn)) {
            redirect_with_error('register', "LRN must be exactly 12 digits.");
        }
        if ($section_id <= 0) {
            redirect_with_error('register', "Please select a section.");
        }

        // optional: prevent duplicate LRN
        $chkL = $conn->prepare("SELECT id FROM users WHERE lrn=? LIMIT 1");
        $chkL->bind_param("s", $lrn);
        $chkL->execute();
        if ($chkL->get_result()->num_rows > 0) {
            redirect_with_error('register', "LRN already exists.");
        }

        // Insert student
        $sql = "INSERT INTO users (name, email, $pwdCol, role, lrn, section_id) VALUES (?, ?, ?, 'user', ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $name, $email, $hash, $lrn, $section_id);
        $stmt->execute();

        $_SESSION['active_form'] = 'login';
        $_SESSION['login_error'] = "Registered successfully! Please login.";
        header("Location: index.php");
        exit();

    } else {
        // TEACHER registration
        $section_ids = $_POST['section_ids'] ?? [];
        if (!is_array($section_ids) || count($section_ids) === 0) {
            redirect_with_error('register', "Teacher must select at least 1 section.");
        }

        // Insert teacher (teacher has no single section_id stored)
        $sql = "INSERT INTO users (name, email, $pwdCol, role) VALUES (?, ?, ?, 'admin')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $name, $email, $hash);
        $stmt->execute();

        $teacher_id = (int)$conn->insert_id;

        // Assign teacher to sections
        $ins = $conn->prepare("INSERT IGNORE INTO teacher_sections (teacher_id, section_id) VALUES (?, ?)");
        foreach ($section_ids as $sid) {
            $sid = (int)$sid;
            if ($sid > 0) {
                $ins->bind_param("ii", $teacher_id, $sid);
                $ins->execute();
            }
        }

        $_SESSION['active_form'] = 'login';
        $_SESSION['login_error'] = "Teacher registered! Please login.";
        header("Location: index.php");
        exit();
    }
}

// ------------------------------ LOGIN ------------------------------
if (isset($_POST['login'])) {
    $role = trim($_POST['role'] ?? '');
    $section_id = (int)($_POST['section_id'] ?? 0);
    $email_or_lrn = trim($_POST['email_or_lrn'] ?? '');
    $plain = $_POST['password'] ?? '';

    if ($role !== 'user' && $role !== 'admin') {
        redirect_with_error('login', "Please select a valid role.");
    }
    if ($section_id <= 0) {
        redirect_with_error('login', "Please select a section.");
    }
    if ($email_or_lrn === '' || $plain === '') {
        redirect_with_error('login', "Please enter login credentials.");
    }

    if ($role === 'admin') {
        // Teacher login by email
        if (!filter_var($email_or_lrn, FILTER_VALIDATE_EMAIL)) {
            redirect_with_error('login', "Teacher must login using email.");
        }

        $stmt = $conn->prepare("SELECT id, name, email, $pwdCol AS pwd FROM users WHERE role='admin' AND email=? LIMIT 1");
        $stmt->bind_param("s", $email_or_lrn);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();

        if (!$u || !password_verify($plain, $u['pwd'])) {
            redirect_with_error('login', "Invalid teacher credentials.");
        }

        // Check teacher assigned to chosen section
        $chk = $conn->prepare("SELECT 1 FROM teacher_sections WHERE teacher_id=? AND section_id=? LIMIT 1");
        $tid = (int)$u['id'];
        $chk->bind_param("ii", $tid, $section_id);
        $chk->execute();
        if ($chk->get_result()->num_rows === 0) {
            redirect_with_error('login', "Teacher not assigned to that section.");
        }

        // Set session
        $_SESSION['user_id'] = (int)$u['id'];
        $_SESSION['role'] = 'admin';
        $_SESSION['email'] = $u['email'];
        $_SESSION['name'] = $u['name'];
        $_SESSION['section_id'] = $section_id;

        header("Location: admin_page.php");
        exit();
    }

    // Student login by email OR LRN, but must match section
    $isEmail = filter_var($email_or_lrn, FILTER_VALIDATE_EMAIL);

    if ($isEmail) {
        $stmt = $conn->prepare("SELECT id, name, email, lrn, section_id, $pwdCol AS pwd FROM users WHERE role='user' AND email=? LIMIT 1");
        $stmt->bind_param("s", $email_or_lrn);
    } else {
        $stmt = $conn->prepare("SELECT id, name, email, lrn, section_id, $pwdCol AS pwd FROM users WHERE role='user' AND lrn=? LIMIT 1");
        $stmt->bind_param("s", $email_or_lrn);
    }

    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();

    if (!$u || !password_verify($plain, $u['pwd'])) {
        redirect_with_error('login', "Invalid student credentials.");
    }

    if ((int)$u['section_id'] !== $section_id) {
        redirect_with_error('login', "Wrong section selected.");
    }

    $_SESSION['user_id'] = (int)$u['id'];
    $_SESSION['role'] = 'user';
    $_SESSION['email'] = $u['email'];
    $_SESSION['name'] = $u['name'];
    $_SESSION['section_id'] = (int)$u['section_id'];

    header("Location: user_page.php");
    exit();
}

// If someone opens research_project.php directly
header("Location: index.php");
exit();
