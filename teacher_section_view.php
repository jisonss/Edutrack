<?php
session_start();
require 'config.php';

/* ===================== ADMIN GUARD ===================== */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit();
}
/* ===================== DELETE STUDENT ===================== */
if (isset($_POST['delete_student']) && !empty($_POST['delete_student_email'])) {
    $emailToDelete = $_POST['delete_student_email'];

    // Safety: prevent admin from deleting themselves
    if ($emailToDelete !== $_SESSION['email']) {
        $delStmt = $conn->prepare("DELETE FROM users WHERE email=? AND role='user' LIMIT 1");
        $delStmt->bind_param("s", $emailToDelete);
        if ($delStmt->execute()) {
            echo "<script>alert('Student account deleted successfully'); window.location='".$_SERVER['REQUEST_URI']."';</script>";
            exit();
        } else {
            echo "<script>alert('Failed to delete student');</script>";
        }
    } else {
        echo "<script>alert('You cannot delete your own admin account');</script>";
    }
}
$teacher_id = (int)$_SESSION['user_id'];
$section_id = (int)($_GET['section_id'] ?? 0);

if ($section_id <= 0) {
    header("Location: admin_page.php");
    exit();
}

/* ===================== VERIFY TEACHER SECTION ===================== */
$check = $conn->prepare("
    SELECT s.section_name
    FROM teacher_sections ts
    JOIN sections s ON s.id = ts.section_id
    WHERE ts.teacher_id=? AND ts.section_id=?
    LIMIT 1
");
$check->bind_param("ii", $teacher_id, $section_id);
$check->execute();
$sec = $check->get_result()->fetch_assoc();

if (!$sec) {
    die("Unauthorized section access.");
}

$secName = $sec['section_name'];

/* ===================== STUDENTS ===================== */
$studentsStmt = $conn->prepare("
    SELECT name, lrn, email
    FROM users
    WHERE role='user' AND section_id=?
    ORDER BY name ASC
");
$studentsStmt->bind_param("i", $section_id);
$studentsStmt->execute();
$studentsResult = $studentsStmt->get_result();

/* ===================== DEADLINES ===================== */
$deadlinesStmt = $conn->prepare("
    SELECT id, title, deadline_date
    FROM deadlines
    WHERE teacher_id=? AND section_id=?
    ORDER BY deadline_date DESC
");
$deadlinesStmt->bind_param("ii", $teacher_id, $section_id);
$deadlinesStmt->execute();
$deadlinesResult = $deadlinesStmt->get_result();

$deadline_id = (int)($_GET['deadline_id'] ?? 0);

/* ===================== NOT SUBMITTED ===================== */
$missingResult = null;
if ($deadline_id > 0) {
    $missingStmt = $conn->prepare("
        SELECT name, lrn, email
        FROM users
        WHERE role='user'
        AND section_id=?
        AND email NOT IN (
            SELECT student_email
            FROM submissions
            WHERE deadline_id=?
        )
        ORDER BY name ASC
    ");
    $missingStmt->bind_param("ii", $section_id, $deadline_id);
    $missingStmt->execute();
    $missingResult = $missingStmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduTrack - Section Overview</title>

<link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link rel="stylesheet" href="newstyled.css">
<style>
/* ===== FIX TABLE HEADER INDICATORS & SPACING ===== */
.box-info table {
    border-collapse: collapse !important;
    border-spacing: 0 !important;
    margin-top: 10px;
}

.box-info thead tr {
    background: var(--grey);
}

.box-info thead th {
    padding: 12px 14px !important;
    font-weight: 600;
    font-size: 14px;
    text-align: left;
    color: var(--dark);
    border-bottom: 1px solid #e5e7eb;
    white-space: nowrap;
}

/* Remove fake indicators / spacing */
.box-info th::before,
.box-info th::after {
    display: none !important;
    content: none !important;
}

/* Body rows */
.box-info tbody td {
    padding: 12px 14px;
    font-size: 14px;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: middle;
}

/* Remove last border */
.box-info tbody tr:last-child td {
    border-bottom: none;
}

/* Hover like dashboard */
.box-info tbody tr:hover {
    background: var(--light);
}
</style>

</head>
<body>

<!-- ===================== SIDEBAR ===================== -->
<section id="sidebar">
  <a href="#" class="brand">
    <i class='bx bxs-book-open'></i>
    <span class="text">EduTrack</span>
  </a>

  <ul class="side-menu top">
    <li>
      <a href="admin_page.php">
        <i class='bx bxs-dashboard'></i>
        <span class="text">Dashboard</span>
      </a>
    </li>
    <li class="active">
      <a href="#">
        <i class='bx bxs-group'></i>
        <span class="text">Section Overview</span>
      </a>
    </li>
  </ul>

  <ul class="side-menu">
    <li>
      <a href="logout.php" class="logout">
        <i class='bx bxs-log-out-circle'></i>
        <span class="text">Logout</span>
      </a>
    </li>
  </ul>
</section>

<!-- ===================== CONTENT ===================== -->
<section id="content">

<nav>
  <i class='bx bx-menu'></i>
  <h1 class="nav-link">Section Overview (<?= htmlspecialchars($secName) ?>)</h1>

  <input type="checkbox" id="switch-mode" hidden>
  <label for="switch-mode" class="switch-mode"></label>
</nav>

<main>

<div class="head-title">
  <div class="left">
    <h1>Students & Submissions</h1>
  </div>
</div>

<!-- ===================== STUDENTS ===================== -->
<div class="box-info-wrapper1">
<ul class="box-info">
<li>
<span class="text">
<h3><i class='bx bxs-group'></i> Registered Students</h3>

<table>
  <thead>
    <tr>
      <th>Name</th>
      <th>LRN</th>
      <th>Email</th>
      <th>Action</th> <!-- New column for Delete -->
    </tr>
  </thead>
  <tbody>
    <?php while ($s = $studentsResult->fetch_assoc()): ?>
    <tr>
      <td><?= htmlspecialchars($s['name']) ?></td>
      <td><?= htmlspecialchars($s['lrn']) ?></td>
      <td><?= htmlspecialchars($s['email']) ?></td>
      <td>
        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this student?');">
          <input type="hidden" name="delete_student_email" value="<?= htmlspecialchars($s['email']) ?>">
          <button type="submit" name="delete_student" style="
    background-color: #ff4d4f; /* Red background */
    color: #fff;               /* White text */
    border: none;
    border-radius: 5px;
    padding: 6px 12px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.3s;
" onmouseover="this.style.backgroundColor='#ff7875'" onmouseout="this.style.backgroundColor='#ff4d4f'">
    Delete
</button>
        </form>
      </td>
    </tr>
    <?php endwhile; ?>
  </tbody>
</table>

</span>
</li>
</ul>
</div>
<!-- ===================== DEADLINE FILTER ===================== -->
<div class="box-info-wrapper1">
<ul class="box-info">
<li>
<span class="text">
<h3><i class='bx bxs-calendar'></i> Check Missing Submissions</h3>

<form method="GET">
  <input type="hidden" name="section_id" value="<?= $section_id ?>">
  <select name="deadline_id" onchange="this.form.submit()">
    <option value="">Select Deadline</option>
    <?php while ($d = $deadlinesResult->fetch_assoc()): ?>
      <option value="<?= $d['id'] ?>" <?= $deadline_id == $d['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($d['title']) ?> (<?= $d['deadline_date'] ?>)
      </option>
    <?php endwhile; ?>
  </select>
</form>

</span>
</li>
</ul>
</div>

<!-- ===================== NOT SUBMITTED ===================== -->
<?php if ($deadline_id > 0): ?>
<div class="box-info-wrapper1">
<ul class="box-info">
<li>
<span class="text">
<h3><i class='bx bxs-error'></i> Students Who Did Not Submit</h3>

<?php if ($missingResult->num_rows === 0): ?>
  <p style="color:var(--green);font-weight:600;">
    ✅ All students submitted
  </p>
<?php else: ?>
<table>
<thead>
<tr>
  <th>Name</th>
  <th>LRN</th>
  <th>Email</th>
  <th>Status</th>
</tr>
</thead>
<tbody>
<?php while ($m = $missingResult->fetch_assoc()): ?>
<tr>
  <td><?= htmlspecialchars($m['name']) ?></td>
  <td><?= htmlspecialchars($m['lrn']) ?></td>
  <td><?= htmlspecialchars($m['email']) ?></td>
  <td style="color:var(--red);font-weight:600;">Not Submitted</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
<?php endif; ?>

</span>
</li>
</ul>
</div>
<?php endif; ?>

</main>
</section>

<!-- ===================== JS ===================== -->
<script>
const menuBar = document.querySelector('#content nav .bx.bx-menu');
const sidebar = document.getElementById('sidebar');
const switchMode = document.getElementById('switch-mode');

// Toggle sidebar
if (menuBar) {
    menuBar.addEventListener('click', () => sidebar.classList.toggle('hide'));
}

// Load dark mode preference on page load
window.addEventListener('DOMContentLoaded', () => {
    const darkMode = localStorage.getItem('darkMode');
    if (darkMode === '1') {
        if (switchMode) switchMode.checked = true;
        document.body.classList.add('dark');
    } else {
        if (switchMode) switchMode.checked = false;
        document.body.classList.remove('dark');
    }
});

// Toggle dark mode and save preference
if (switchMode) {
    switchMode.addEventListener('change', () => {
        document.body.classList.toggle('dark', switchMode.checked);
        localStorage.setItem('darkMode', switchMode.checked ? '1' : '0');
    });
}
</script>

</body>
</html>
