<?php
session_start();
require 'config.php';

/* =======================
   BASIC GUARDS
======================= */
if (!isset($_SESSION['email']) || !isset($_SESSION['section_id'])) {
    header("Location: index.php");
    exit();
}

$email = $_SESSION['email'];
$section_id = (int)$_SESSION['section_id'];
$selected_teacher_id = (int)($_SESSION['selected_teacher_id'] ?? 0);

/* =======================
   ENSURE VALID TEACHER
======================= */
$teachersStmt = $conn->prepare("
    SELECT u.id, u.name
    FROM teacher_sections ts
    JOIN users u ON u.id = ts.teacher_id
    WHERE ts.section_id = ? AND u.role = 'admin'
    ORDER BY u.name ASC
");
$teachersStmt->bind_param("i", $section_id);
$teachersStmt->execute();
$teachersRes = $teachersStmt->get_result();

$teachers = [];
while ($t = $teachersRes->fetch_assoc()) {
    $teachers[] = $t;
}

$valid_teacher_ids = array_map(fn($t) => (int)$t['id'], $teachers);

if ($selected_teacher_id <= 0 || !in_array($selected_teacher_id, $valid_teacher_ids, true)) {
    if (!empty($valid_teacher_ids)) {
        $selected_teacher_id = $valid_teacher_ids[0]; // fallback
        $_SESSION['selected_teacher_id'] = $selected_teacher_id;
    } else {
        die("No teacher assigned to this section.");
    }
}

/* =======================
   HANDLE TEACHER SWITCH (FILTER)
======================= */
if (isset($_POST['set_teacher'])) {
    $new_teacher_id = (int)($_POST['teacher_id'] ?? 0);
    if (in_array($new_teacher_id, $valid_teacher_ids, true)) {
        $_SESSION['selected_teacher_id'] = $new_teacher_id;
    } else {
        $_SESSION['selected_teacher_id'] = $selected_teacher_id;
    }
    header("Location: user_page.php");
    exit();
}

/* =======================
   AJAX archive/delete inbox
======================= */
if (isset($_POST['archive_id'])) {
    $notifId = (int)$_POST['archive_id'];
    $stmt = $conn->prepare("
        UPDATE inbox
        SET archived=1
        WHERE id=? AND email=? AND section_id=? AND teacher_id=?
    ");
    $stmt->bind_param("isii", $notifId, $email, $section_id, $selected_teacher_id);
    $stmt->execute();
    echo "success";
    exit();
}

if (isset($_POST['delete_id'])) {
    $notifId = (int)$_POST['delete_id'];
    $stmt = $conn->prepare("
        DELETE FROM inbox
        WHERE id=? AND email=? AND section_id=? AND teacher_id=?
    ");
    $stmt->bind_param("isii", $notifId, $email, $section_id, $selected_teacher_id);
    $stmt->execute();
    echo "deleted";
    exit();
}

/* =======================
   GET STUDENT DETAILS
======================= */
$stmt = $conn->prepare("SELECT name, lrn FROM users WHERE email=? AND role='user' LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: index.php");
    exit();
}

$name = $user['name'];
$lrn  = $user['lrn'];

/* =======================
   FINAL GRADE
======================= */
$finalStmt = $conn->prepare("
    SELECT final_grade, remarks
    FROM final_grades
    WHERE student_email=? AND teacher_id=? AND section_id=? LIMIT 1
");
$finalStmt->bind_param("sii", $email, $selected_teacher_id, $section_id);
$finalStmt->execute();
$finalGrade = $finalStmt->get_result()->fetch_assoc();

$today = date('Y-m-d');

/* =======================
   DEADLINES
======================= */
$deadlines = $conn->prepare("
    SELECT * FROM deadlines
    WHERE section_id=? AND teacher_id=?
    ORDER BY deadline_date ASC
");
$deadlines->bind_param("ii", $section_id, $selected_teacher_id);
$deadlines->execute();
$deadlinesResult = $deadlines->get_result();

/* =======================
   LESSONS (UPLOADS)
======================= */
$lessons = $conn->prepare("
    SELECT id, filename, filepath, uploaded_at
    FROM uploads
    WHERE section_id=? AND teacher_id=?
    ORDER BY uploaded_at DESC
    LIMIT 5
");
$lessons->bind_param("ii", $section_id, $selected_teacher_id);
$lessons->execute();
$lessonsRes = $lessons->get_result();

/* =======================
   INBOX (NOTIFICATIONS)
======================= */
$notifStmt = $conn->prepare("
    SELECT * FROM inbox
    WHERE email=? AND section_id=? AND teacher_id=? AND archived=0
    ORDER BY created_at DESC
");
$notifStmt->bind_param("sii", $email, $section_id, $selected_teacher_id);
$notifStmt->execute();
$notifResult = $notifStmt->get_result();
$unreadCount = $notifResult->num_rows;

/* =======================
   ARCHIVED NOTIFICATIONS
======================= */
$archivedStmt = $conn->prepare("
    SELECT * FROM inbox
    WHERE email=? AND section_id=? AND teacher_id=? AND archived=1
    ORDER BY created_at DESC
");
$archivedStmt->bind_param("sii", $email, $section_id, $selected_teacher_id);
$archivedStmt->execute();
$archivedResult = $archivedStmt->get_result();

/* =======================
   DEADLINE WARNINGS
======================= */
$warnings = [];
$deadlines->execute();
$deadlineRes2 = $deadlines->get_result();
while ($row = $deadlineRes2->fetch_assoc()) {
    $checkSubmitted = $conn->prepare("SELECT id FROM submissions WHERE student_email=? AND deadline_id=? LIMIT 1");
    $checkSubmitted->bind_param("si", $email, $row['id']);
    $checkSubmitted->execute();
    if ($checkSubmitted->get_result()->num_rows > 0) continue;

    if ($row['deadline_date'] === $today) $warnings[] = "You have a task deadline today: " . $row['title'];
    if ($row['deadline_date'] < $today)  $warnings[] = "You missed the deadline: " . $row['title'];
}

/* =======================
   ASSIGNMENT SUBMISSION
======================= */
if (isset($_POST['submit_specific_assignment'])) {
    $deadline_id = (int)($_POST['deadline_id'] ?? 0);

    $stmtD = $conn->prepare("
        SELECT title, deadline_date
        FROM deadlines
        WHERE id=? AND section_id=? AND teacher_id=?
        LIMIT 1
    ");
    $stmtD->bind_param("iii", $deadline_id, $section_id, $selected_teacher_id);
    $stmtD->execute();
    $rowDeadline = $stmtD->get_result()->fetch_assoc();

    if (!$rowDeadline) {
        $_SESSION['message'] = "Invalid deadline.";
        header("Location: user_page.php");
        exit();
    }

    $actual_title = trim($rowDeadline['title']);
    $deadline_due = $rowDeadline['deadline_date'];
    $isLate = (date("Y-m-d") > $deadline_due) ? 1 : 0;

    if (!isset($_FILES['deadline_file']) || $_FILES['deadline_file']['error'] !== 0) {
        $_SESSION['message'] = "No file selected or upload error.";
        header("Location: user_page.php");
        exit();
    }

    $fileTmp  = $_FILES['deadline_file']['tmp_name'];
    $fileName = basename($_FILES['deadline_file']['name']);

    $uploadedTitle = pathinfo($fileName, PATHINFO_FILENAME);
    $normalizedUploaded = strtolower(trim(str_replace(['_', '-'], ' ', $uploadedTitle)));
    $normalizedActual   = strtolower(trim(str_replace(['_', '-'], ' ', $actual_title)));

    if ($normalizedUploaded !== $normalizedActual) {
        $_SESSION['message'] = "Submission rejected: Filename must match the assignment title ('$actual_title').";
        header("Location: user_page.php");
        exit();
    }

    $uploadDir = 'uploads/submissions/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $filePath = $uploadDir . time() . "_" . $fileName;

    if (!move_uploaded_file($fileTmp, $filePath)) {
        $_SESSION['message'] = "Failed to upload the file. Try again.";
        header("Location: user_page.php");
        exit();
    }

    $stmtInsert = $conn->prepare("
        INSERT INTO submissions
        (student_email, student_name, lrn, filename, filepath, submission_file, feedback, deadline_id, late, visible_to_admin)
        VALUES (?, ?, ?, ?, ?, ?, '', ?, ?, 1)
    ");
    $stmtInsert->bind_param(
        "ssssssii",
        $email,
        $name,
        $lrn,
        $actual_title,
        $filePath,
        $fileName,
        $deadline_id,
        $isLate
    );
    $stmtInsert->execute();

    $_SESSION['message'] = $isLate ? "Submitted successfully! (Late)" : "Submitted successfully!";
    header("Location: user_page.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>EduTrack - Lessons</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link rel="stylesheet" href="usersas.css">
</head>

<body>

<!-- SIDEBAR -->
<section id="sidebar">
    <a href="#" class="brand"><i class='bx bxs-book-open'></i>EduTrack</a>
    <ul class="side-menu top">
        <li><a href="user_page.php"><i class='bx bxs-alarm'></i> Upcoming Deadline</a></li>
        <li class="active"><a href="Powerpoint.php"><i class='bx bxs-notepad'></i> Lessons</a></li>
        <li><a href="feedback.php"><i class='bx bxs-file'></i> Feedback</a></li>
     <li><a href="finalgrade.php"><i class='bx bxs-report'></i> Reports</a></li>

    </ul>
    <ul class="side-menu">
        <li><a href="logout.php" class="logout"><i class='bx bxs-log-out-circle'></i> Logout</a></li>
    </ul>
</section>

<!-- CONTENT -->
<section id="content">
<nav>
      <i class='bx bx-menu'></i>
      <h1 class="nav-link">Welcome, Student <?= htmlspecialchars($_SESSION['name']) ?>!</h1>
      <input type="checkbox" id="switch-mode" hidden>
      <label for="switch-mode" class="switch-mode"></label>
      <!-- 🔔 Bell + Dropdown -->
       <!-- ✅ Teacher Switch Dropdown -->
<form method="POST" style="margin-left:15px;">
  <input type="hidden" name="set_teacher" value="1">
  <select name="teacher_id" onchange="this.form.submit()" style="padding:6px;border-radius:8px;">
    <?php if (empty($teachers)): ?>
      <option value="0">No teachers assigned</option>
    <?php else: ?>
      <?php foreach ($teachers as $t): ?>
        <option value="<?= (int)$t['id'] ?>" <?= ((int)$t['id'] === $selected_teacher_id) ? 'selected' : '' ?>>
          <?= htmlspecialchars($t['name']) ?>
        </option>
      <?php endforeach; ?>
    <?php endif; ?>
  </select>
</form>
<a href="#" class="notification">
  <i class='bx bxs-bell'></i>
  <span class="num" style="<?= $unreadCount > 0 ? 'display:flex' : 'display:none' ?>"><?= $unreadCount ?></span>
</a>
<div id="inbox-dropdown" class="inbox-dropdown">
  <h4 class="inbox-title">Inbox Notifications</h4>
  <ul class="inbox-list" id="inbox-list">
    <?php
    $active_notifs = $conn->query("SELECT * FROM inbox WHERE email='{$_SESSION['email']}' AND archived=0 ORDER BY created_at DESC");
    if ($active_notifs->num_rows === 0) {
        echo "<li class='inbox-item'>No notifications.</li>";
    } else {
        while ($notif = $active_notifs->fetch_assoc()) {
            echo "<li class='inbox-item unread'>";
            echo htmlspecialchars($notif['message']);
            echo " <button class='archive-btn' data-id='".$notif['id']."'>Archive</button>";
            echo "</li>";
        }
    }
    ?>
  </ul>
  <hr class="inbox-hr">
  <a href="#" id="view-archived" class="history-link">View Archived</a>

  <ul class="inbox-list" id="archived-list" style="display:none;">
    <?php
    $archived_notifs = $conn->query("SELECT * FROM inbox WHERE email='{$_SESSION['email']}' AND archived=1 ORDER BY created_at DESC");
    if ($archived_notifs->num_rows === 0) {
        echo "<li class='inbox-item archived'>No archived notifications.</li>";
    } else {
        while ($notif = $archived_notifs->fetch_assoc()) {
            echo "<li class='inbox-item archived'>";
            echo htmlspecialchars($notif['message']);
            echo " <button class='delete-btn' data-id='".$notif['id']."'> Remove </button>";
            echo "</li>";
        }
    }
    ?>
</ul>
</div>

<script>
const bell = document.querySelector('.notification');
const inboxDropdown = document.getElementById('inbox-dropdown');
const numBadge = document.querySelector('.notification .num');

bell.addEventListener('click', e => {
    e.preventDefault();
    inboxDropdown.classList.toggle('show');
    if (inboxDropdown.classList.contains('show')) {
        document.querySelectorAll('.inbox-item.unread').forEach(item => item.classList.remove('unread'));
        numBadge.style.display = 'none';
    }
});

document.getElementById('view-archived').addEventListener('click', function(e){
    e.preventDefault();
    const inboxList = document.getElementById('inbox-list');
    const archivedList = document.getElementById('archived-list');
    if (archivedList.style.display === 'none') {
        archivedList.style.display = 'block';
        inboxList.style.display = 'none';
        this.innerHTML = "Back to Inbox";
    } else {
        archivedList.style.display = 'none';
        inboxList.style.display = 'block';
        this.innerHTML = "View Archived";
    }
});
// Archive button functionality
document.querySelectorAll('.archive-btn').forEach(btn => {
    btn.addEventListener('click', function(e){
        e.preventDefault();
        const notifId = this.getAttribute('data-id');

        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'archive_id=' + notifId
        })
        .then(res => res.text())
        .then(() => {
            // Move to archived list dynamically
const li = this.closest('.inbox-item');
li.remove();
const archivedList = document.getElementById('archived-list');
li.classList.remove('unread');
li.classList.add('archived');
// Change the button to "Remove"
const btn = li.querySelector('.archive-btn');
btn.textContent = 'Remove';
btn.classList.remove('archive-btn');
btn.classList.add('delete-btn');
// Reattach delete event
btn.addEventListener('click', function(e){
    e.preventDefault();
    const notifId = this.getAttribute('data-id');

    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'delete_id=' + notifId
    })
    .then(res => res.text())
    .then(response => {
        if (response === 'deleted') {
            li.remove();
        }
    });
});
archivedList.appendChild(li);
// Show Archived list automatically
document.getElementById('inbox-list').style.display = 'none';
archivedList.style.display = 'block';
document.getElementById('view-archived').innerHTML = "Back to Inbox";
            // Update badge count
            let count = parseInt(numBadge.textContent);
            count = Math.max(count - 1, 0);
            if (count === 0) {
                numBadge.style.display = 'none';
            } else {
                numBadge.textContent = count;
            }
        });
    });
});
// Delete button functionality
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function(e){
        e.preventDefault();
        const notifId = this.getAttribute('data-id');

        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'delete_id=' + notifId
        })
        .then(res => res.text())
        .then(response => {
            if (response === 'deleted') {
                const li = this.closest('.inbox-item');
                li.remove();
            }
        });
    });
});
document.addEventListener('click', e => {
    if (!bell.contains(e.target) && !inboxDropdown.contains(e.target)) {
        inboxDropdown.classList.remove('show');
    }
});
const menuBar = document.querySelector('#content nav .bx.bx-menu');
const sidebar = document.getElementById('sidebar');
const switchMode = document.getElementById('switch-mode');
menuBar.addEventListener('click', () => sidebar.classList.toggle('hide'));
// Apply saved mode on page load
if (localStorage.getItem("darkMode") === "enabled") {
    document.body.classList.add("dark");
    switchMode.checked = true;
}

// Toggle dark mode on change
switchMode.addEventListener("change", () => {
    if (switchMode.checked) {
        document.body.classList.add("dark");
        localStorage.setItem("darkMode", "enabled");
    } else {
        document.body.classList.remove("dark");
        localStorage.setItem("darkMode", "disabled");
    }
});

</script>
    </nav>

<main>
    <div class="head-title">
        <div class="left">
            <i class='bx bxs-notepad'></i>
            <h1>Lessons</h1>
        </div>
    </div>

    <div class="box-info-wrapper">
        <ul class="box-info" id="lessons">

           <?php if ($lessonsRes->num_rows === 0): ?>
    <li>No lessons uploaded for this teacher.</li>
<?php else: ?>
    <?php while ($row = $lessonsRes->fetch_assoc()): ?>
        <li>
            <div class="file-info">
                <span class="filename"><?= htmlspecialchars($row['filename']) ?></span>
                <span class="upload-date">
                    <?= date("M d, Y", strtotime($row['uploaded_at'])) ?>
                </span>
            </div>

            <div class="lesson-buttons">
                <a href="<?= htmlspecialchars($row['filepath']) ?>" target="_blank" class="btn-view">View</a>
                <a href="<?= htmlspecialchars($row['filepath']) ?>" download class="btn-download">Download</a>
            </div>
        </li>
    <?php endwhile; ?>
<?php endif; ?>

        </ul>
    </div>
</main>
</section>

<script>
const menuBar = document.querySelector('#content nav .bx.bx-menu');
const sidebar = document.getElementById('sidebar');
menuBar.addEventListener('click', () => sidebar.classList.toggle('hide'));
</script>

</body>
</html>
