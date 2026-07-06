<?php
session_start();
require 'config.php';

// ✅ Admin-only guard (teacher)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit();
}

$teacher_id    = (int)$_SESSION['user_id'];
$teacher_email = $_SESSION['email'] ?? '';
$teacher_name  = $_SESSION['name'] ?? 'Teacher';
$section_id    = (int)($_SESSION['section_id'] ?? 0);$teacher_id    = (int)$_SESSION['user_id'];
$teacher_email = $_SESSION['email'] ?? '';
$teacher_name  = $_SESSION['name'] ?? 'Teacher';
$section_id    = (int)($_SESSION['section_id'] ?? 0);

if ($teacher_email === '') {
    header("Location: index.php");
    exit();
}

// ====================== GET TEACHER SECTIONS ======================
$sectionsStmt = $conn->prepare("
    SELECT s.id, s.section_name
    FROM teacher_sections ts
    JOIN sections s ON s.id = ts.section_id
    WHERE ts.teacher_id=?
    ORDER BY s.section_name ASC
");
$sectionsStmt->bind_param("i", $teacher_id);
$sectionsStmt->execute();
$sectionsRes = $sectionsStmt->get_result();

$sections = [];
while ($sec = $sectionsRes->fetch_assoc()) {
    $sections[] = $sec;
}


// ====================== SECTION SWITCH ======================
if (isset($_POST['set_section'])) {

    $new_section_id = (int)($_POST['section_id'] ?? 0);

    $valid = false;

    foreach ($sections as $sec) {
        if ((int)$sec['id'] === $new_section_id) {
            $valid = true;
            break;
        }
    }

    if ($valid) {
        $_SESSION['section_id'] = $new_section_id;
        $section_id = $new_section_id;
    }

    header("Location: admin_page.php");
    exit();
}


// DEFAULT SECTION
if ($section_id <= 0 && !empty($sections)) {
    $section_id = (int)$sections[0]['id'];
    $_SESSION['section_id'] = $section_id;
}

if ($teacher_email === '' || $section_id <= 0) {
    header("Location: index.php");
    exit();
}

// ✅ Check teacher assigned to this section
$check = $conn->prepare("SELECT 1 FROM teacher_sections WHERE teacher_id=? AND section_id=? LIMIT 1");
$check->bind_param("ii", $teacher_id, $section_id);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    $_SESSION['login_error'] = "Teacher not assigned to this section.";
    header("Location: index.php");
    exit();
}

// ✅ Get section name
$secName = "Unknown Section";
$sq = $conn->prepare("SELECT section_name FROM sections WHERE id=? LIMIT 1");
$sq->bind_param("i", $section_id);
$sq->execute();
if ($r = $sq->get_result()->fetch_assoc()) $secName = $r['section_name'];


// ====================== HANDLE FILE UPLOAD ======================
if (isset($_POST['upload'])) {
    $targetDir = "uploads/";
    if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);

    $filename = basename($_FILES['file']['name'] ?? '');
    if ($filename === '') {
        $_SESSION['message'] = "No file selected.";
        header("Location: admin_page.php");
        exit();
    }

    $filepath = $targetDir . time() . "_" . $filename;
    $fileType = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    $allowedTypes = ['pdf', 'ppt', 'pptx'];

    if (!in_array($fileType, $allowedTypes, true)) {
        $_SESSION['message'] = "Invalid file type. Only PDF, PPT, PPTX allowed.";
        header("Location: admin_page.php");
        exit();
    }

    if (!move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
        $_SESSION['message'] = "Failed to upload file.";
        header("Location: admin_page.php");
        exit();
    }

    // ✅ Save with teacher_id + section_id
    $stmt = $conn->prepare("
        INSERT INTO uploads (filename, filepath, uploaded_by, teacher_id, section_id, uploaded_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("sssii", $filename, $filepath, $teacher_email, $teacher_id, $section_id);
    $stmt->execute();

    $_SESSION['message'] = "File uploaded successfully for {$secName}.";

    // ✅ Notify only students in this section
    $students = $conn->prepare("SELECT email, lrn FROM users WHERE role='user' AND section_id=?");
    $students->bind_param("i", $section_id);
    $students->execute();
    $studentsRes = $students->get_result();

    while ($student = $studentsRes->fetch_assoc()) {
        $message = "New lesson uploaded for {$secName}: " . $filename;

        $lesson_id = $stmt->insert_id;

$notif = $conn->prepare("
INSERT INTO inbox
(email, lrn, message, teacher_id, section_id, type, related_id, created_at, archived)
VALUES (?, ?, ?, ?, ?, 'lesson', ?, NOW(), 0)
");
$notif->bind_param(
  "sssiii",
  $student['email'],
  $student['lrn'],
  $message,
  $teacher_id,
  $section_id,
  $lesson_id
);
$notif->execute();

    }

    header("Location: admin_page.php");
    exit();
}


// ====================== DELETE LESSON ======================
if (isset($_POST['delete_lesson'])) {
    $lesson_id = (int)($_POST['lesson_id'] ?? 0);

    $stmt = $conn->prepare("SELECT filepath FROM uploads WHERE id=? AND teacher_id=? AND section_id=? LIMIT 1");
    $stmt->bind_param("iii", $lesson_id, $teacher_id, $section_id);
    $stmt->execute();
    $lesson = $stmt->get_result()->fetch_assoc();

    if ($lesson) {
        if (!empty($lesson['filepath']) && file_exists($lesson['filepath'])) unlink($lesson['filepath']);
        $del = $conn->prepare("DELETE FROM uploads WHERE id=? AND teacher_id=? AND section_id=?");
        $del->bind_param("iii", $lesson_id, $teacher_id, $section_id);
        $del->execute();
        $_SESSION['message'] = "Lesson deleted successfully.";
    } else {
        $_SESSION['message'] = "Lesson not found.";
    }

    header("Location: admin_page.php");
    exit();
}


// ====================== SET DEADLINE ======================
if (isset($_POST['set_deadline'])) {
    $title = trim($_POST['title'] ?? '');
    $deadlineDate = $_POST['deadline_date'] ?? '';

    if ($title === '' || $deadlineDate === '') {
        $_SESSION['message'] = "Deadline title and date required.";
        header("Location: admin_page.php");
        exit();
    }

    $stmt = $conn->prepare("
        INSERT INTO deadlines (title, deadline_date, created_by, teacher_id, section_id, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("sssii", $title, $deadlineDate, $teacher_email, $teacher_id, $section_id);
    $stmt->execute();

    $_SESSION['message'] = "Deadline set for {$secName}.";

    // notify only students in this section
    $students = $conn->prepare("SELECT email, lrn FROM users WHERE role='user' AND section_id=?");
    $students->bind_param("i", $section_id);
    $students->execute();
    $studentsRes = $students->get_result();

    while ($student = $studentsRes->fetch_assoc()) {
        $message = "New deadline for {$secName}: " . $title;
        $deadline_id = $stmt->insert_id;

$notif = $conn->prepare("
INSERT INTO inbox
(email, lrn, message, teacher_id, section_id, type, related_id, created_at, archived)
VALUES (?, ?, ?, ?, ?, 'deadline', ?, NOW(), 0)
");
$notif->bind_param(
  "sssiii",
  $student['email'],
  $student['lrn'],
  $message,
  $teacher_id,
  $section_id,
  $deadline_id
);
$notif->execute();

    }

    header("Location: admin_page.php");
    exit();
}


// ====================== DELETE DEADLINE ======================
if (isset($_POST['delete_deadline'])) {
    $deadline_id = (int)($_POST['deadline_id'] ?? 0);

    $stmt = $conn->prepare("DELETE FROM deadlines WHERE id=? AND teacher_id=? AND section_id=?");
    $stmt->bind_param("iii", $deadline_id, $teacher_id, $section_id);
    $stmt->execute();

    $_SESSION['message'] = "Deadline deleted.";
    header("Location: admin_page.php");
    exit();
}


// ====================== FEEDBACK ======================
if (isset($_POST['submit_feedback'])) {
    $feedback = trim($_POST['feedback'] ?? '');
    $submission_id = (int)($_POST['submission_id'] ?? 0);

    // Ensure submission is for THIS teacher's deadline
    $stmt = $conn->prepare("
        SELECT s.id, s.student_email, s.filename, u.lrn
        FROM submissions s
        JOIN deadlines d ON d.id = s.deadline_id
        JOIN users u ON u.email = s.student_email
        WHERE s.id = ? AND d.teacher_id=? AND d.section_id=?
        LIMIT 1
    ");
    $stmt->bind_param("iii", $submission_id, $teacher_id, $section_id);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();

    if (!$submission) {
        $_SESSION['message'] = "Submission not found for your deadlines/section.";
        header("Location: admin_page.php");
        exit();
    }

    $student_email = $submission['student_email'];
    $student_lrn   = $submission['lrn'];
    $filename      = $submission['filename'];
    $message       = "Your file '$filename' has been reviewed. Feedback: $feedback";

    $inbox_stmt = $conn->prepare("
INSERT INTO inbox
(email, lrn, message, teacher_id, section_id, type, related_id, created_at, archived)
VALUES (?, ?, ?, ?, ?, 'feedback', ?, NOW(), 0)
");
$inbox_stmt->bind_param(
  "sssiii",
  $student_email,
  $student_lrn,
  $message,
  $teacher_id,
  $section_id,
  $submission_id
);
$inbox_stmt->execute();


    $update_stmt = $conn->prepare("UPDATE submissions SET feedback=? WHERE id=?");
    $update_stmt->bind_param("si", $feedback, $submission_id);
    $update_stmt->execute();

    $_SESSION['message'] = "Feedback sent.";
    header("Location: admin_page.php");
    exit();
}


// hide submission
if (isset($_POST['delete_submission'])) {
    $submission_id = (int)($_POST['submission_id'] ?? 0);

    // Only hide submissions for this teacher’s deadlines
    $stmt = $conn->prepare("
        UPDATE submissions s
        JOIN deadlines d ON d.id = s.deadline_id
        SET s.visible_to_admin=0
        WHERE s.id=? AND d.teacher_id=? AND d.section_id=?
    ");
    $stmt->bind_param("iii", $submission_id, $teacher_id, $section_id);
    $stmt->execute();

    $_SESSION['message'] = "Submission hidden from admin view.";
    header("Location: admin_page.php");
    exit();
}

// ====================== SAVE FINAL GRADE ======================
if (isset($_POST['submit_final_grade'])) {
    $student_email = $_POST['student_email'];
    $lrn           = $_POST['lrn'];
    $final_grade   = (float)$_POST['final_grade'];
    $remarks       = trim($_POST['remarks'] ?? '');

    $stmt = $conn->prepare("
        INSERT INTO final_grades
        (student_email, lrn, teacher_id, section_id, final_grade, remarks)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        final_grade=VALUES(final_grade),
        remarks=VALUES(remarks)
    ");
    $stmt->bind_param(
        "ssiids",
        $student_email,
        $lrn,
        $teacher_id,
        $section_id,
        $final_grade,
        $remarks
    );
    $stmt->execute();

    // notify student
    $msg = "Your FINAL grade has been posted: {$final_grade}";
    $notif = $conn->prepare("
        INSERT INTO inbox
        (email, lrn, message, teacher_id, section_id, type, created_at, archived)
        VALUES (?, ?, ?, ?, ?, 'final_grade', NOW(), 0)
    ");
    $notif->bind_param(
        "sssii",
        $student_email,
        $lrn,
        $msg,
        $teacher_id,
        $section_id
    );
    $notif->execute();

    $_SESSION['message'] = "Final grade saved.";
    header("Location: admin_page.php");
    exit();
}


// ====================== FETCH DATA (TEACHER+SECTION FILTERED) ======================
$submissions = $conn->prepare("
    SELECT s.*
    FROM submissions s
    JOIN deadlines d ON d.id = s.deadline_id
    WHERE s.visible_to_admin=1 AND d.section_id=? AND d.teacher_id=?
    ORDER BY s.submitted_at DESC
");
$submissions->bind_param("ii", $section_id, $teacher_id);
$submissions->execute();
$submissionsRes = $submissions->get_result();

$deadlines = $conn->prepare("SELECT * FROM deadlines WHERE teacher_id=? AND section_id=? ORDER BY deadline_date ASC");
$deadlines->bind_param("ii", $teacher_id, $section_id);
$deadlines->execute();
$deadlinesRes = $deadlines->get_result();

$uploaded_lessons = $conn->prepare("SELECT * FROM uploads WHERE teacher_id=? AND section_id=? ORDER BY uploaded_at DESC");
$uploaded_lessons->bind_param("ii", $teacher_id, $section_id);
$uploaded_lessons->execute();
$lessonsRes = $uploaded_lessons->get_result();
// ====================== STUDENTS FOR FINAL GRADES ======================
$studentsFG = $conn->prepare("
    SELECT email, name, lrn
    FROM users
    WHERE role='user' AND section_id=?
    ORDER BY name ASC
");
$studentsFG->bind_param("i", $section_id);
$studentsFG->execute();
$studentsFGRes = $studentsFG->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EduTrack - Teacher</title>
  <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="newstyled.css">
</head>
<body>

<section id="sidebar">
  <a href="#" class="brand"><i class='bx bxs-book-open'></i>EduTrack</a>
  <ul class="side-menu top">
    <li class="active"><a href="#"><i class='bx bxs-dashboard'></i> Dashboard</a></li>
  </ul>
  <ul class="side-menu">
    <li><a href="logout.php" class="logout"><i class='bx bxs-log-out-circle'></i> Logout</a></li>
  </ul>
</section>

<section id="content">
  <nav>
<i class='bx bx-menu'></i>


<h1 class="nav-link">
Welcome, Teacher <?= htmlspecialchars($teacher_name) ?>!
(<?= htmlspecialchars($secName) ?>)
</h1>

<!-- SECTION SWITCH DROPDOWN -->
<form method="POST" style="margin-left:15px;">
<input type="hidden" name="set_section" value="1">

<select name="section_id"
        onchange="this.form.submit()"
        style="padding:6px;border-radius:8px;">

<?php foreach ($sections as $sec): ?>

<option value="<?= (int)$sec['id'] ?>"
<?= ((int)$sec['id'] === $section_id) ? 'selected' : '' ?>>

<?= htmlspecialchars($sec['section_name']) ?>

</option>

<?php endforeach; ?>

</select>
</form>

<input type="checkbox" id="switch-mode" hidden>
<label for="switch-mode" class="switch-mode"></label>

</nav>

  <main>
    <?php if (isset($_SESSION['message'])): ?>
      <p style="padding:10px;background:#eef;border-radius:8px;">
        <?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
      </p>
    <?php endif; ?>

    <div class="head-title">
  <div class="left">
    <h1>Dashboard</h1>
  </div>



  <a href="teacher_section_view.php?section_id=<?= (int)$section_id ?>"
     class="btn-download"
     style="background:#1E90FF;">
     <i class='bx bxs-group'></i>
     <span class="text">View Students</span>
  </a>
</div>


    </div>

    <div class="box-info-wrapper">
      <ul class="box-info">
        <li>
          <i class='bx bxs-file-import'></i>
          <span class="text">
            <h3>Upload a File (<?= htmlspecialchars($secName) ?>)</h3>
            <p>Accepted: PDF / PowerPoint</p>
            <form method="POST" enctype="multipart/form-data" class="upload-form">
              <input type="file" name="file" accept=".pdf,.ppt,.pptx" required>
              <button type="submit" name="upload">Upload</button>
            </form>
          </span>
        </li>
      </ul>

      <ul class="box-info">
        <li>
          <i class='bx bxs-calendar'></i>
          <span class="text">
            <h3>Set Deadline (<?= htmlspecialchars($secName) ?>)</h3>
            <p>Create and schedule a task deadline</p>
            <form method="POST" class="deadline-form">
              <input type="text" name="title" placeholder="Deadline Title" required>
              <input type="date" name="deadline_date" required>
              <button type="submit" name="set_deadline">Set Deadline</button>
            </form>
          </span>
        </li>
      </ul>
    </div>

    <div class="box-info-wrapper1">
      <ul class="box-info">
        <li>
          <span class="text">
            <h3><i class='bx bxs-book-content'></i> Your Uploaded Lessons (<?= htmlspecialchars($secName) ?>)</h3>
            <div class="lessons-table">
              <table>
                <thead><tr><th>Filename</th><th>Action</th></tr></thead>
                <tbody>
                <?php while ($lesson = $lessonsRes->fetch_assoc()): ?>
                  <tr>
                    <td class="filename-cell"><?= htmlspecialchars($lesson['filename']) ?></td>
                    <td>
                      <a href="download.php?type=lesson&id=<?= (int)$lesson['id'] ?>&mode=view" target="_blank" class="view-btn">View</a>
                      <a href="download.php?type=lesson&id=<?= (int)$lesson['id'] ?>&mode=download" class="download-btn">Download</a>

                      <form method="POST" style="display:inline;">
                        <input type="hidden" name="lesson_id" value="<?= (int)$lesson['id'] ?>">
                        <button type="submit" name="delete_lesson" class="delete-btn">Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </span>
        </li>
      </ul>

      <ul class="box-info">
        <li>
          <span class="text">
            <h3><i class='bx bxs-time'></i> Active Deadlines (<?= htmlspecialchars($secName) ?>)</h3>
            <div class="deadline-table">
              <table>
                <thead><tr><th>Title</th><th>Deadline Date</th><th>Action</th></tr></thead>
                <tbody>
                <?php while ($deadline = $deadlinesRes->fetch_assoc()): ?>
                  <tr>
                    <td class="title-cell"><?= htmlspecialchars($deadline['title']) ?></td>
                    <td><?= date("M d, Y", strtotime($deadline['deadline_date'])) ?></td>
                    <td>
                      <form method="POST">
                        <input type="hidden" name="deadline_id" value="<?= (int)$deadline['id'] ?>">
                        <button type="submit" name="delete_deadline" class="delete-btn">Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </span>
        </li>
      </ul>
    </div>

    <div class="table-data">
      <h3>Student Submissions (<?= htmlspecialchars($secName) ?>)</h3>
      <table border="1">
        <tr>
          <th>Name</th>
          <th>LRN</th>
          <th>Title</th>
          <th>File</th>
          <th>Submitted At</th>
          <th>Status</th>
          <th>Send Feedback</th>
          <th>Remove</th>
        </tr>
        <?php while ($row = $submissionsRes->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($row['student_name']) ?></td>
            <td><?= htmlspecialchars($row['lrn']) ?></td>
            <td><?= htmlspecialchars($row['filename']) ?></td>
            <td>
              <a href="download.php?type=submission&id=<?= (int)$row['id'] ?>&mode=view" target="_blank">View</a> |
              <a href="download.php?type=submission&id=<?= (int)$row['id'] ?>&mode=download">Download</a>
            </td>
            <td><?= date("F d, Y, h:i a", strtotime($row['submitted_at'])) ?></td>
            <td>
              <?php if ((int)$row['late'] === 1): ?>
                <span style="color:red;font-weight:bold;">Late</span>
              <?php else: ?>
                <span style="color:green;font-weight:bold;">On Time</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (empty($row['feedback'])): ?>
                <form method="POST">
                  <input type="hidden" name="submission_id" value="<?= (int)$row['id'] ?>">
                  <input type="text" name="feedback" placeholder="Leave feedback" required>
                  <button type="submit" name="submit_feedback">Send</button>
                </form>
              <?php else: ?>
                <?= htmlspecialchars($row['feedback']) ?>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($row['feedback'])): ?>
                <form method="POST">
                  <input type="hidden" name="submission_id" value="<?= (int)$row['id'] ?>">
                  <button type="submit" name="delete_submission">Delete</button>
                </form>
              <?php else: ?>
                <span style="color:gray;">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      </table>
    </div>
    <div class="table-data">
  <h3>Final / Overall Grades (<?= htmlspecialchars($secName) ?>)</h3>

  <table border="1">
    <tr>
      <th>Student Name</th>
      <th>LRN</th>
      <th>Final Grade</th>
      <th>Remarks</th>
      <th>Action</th>
    </tr>

    <?php while ($s = $studentsFGRes->fetch_assoc()): ?>
      <?php
        $fg = $conn->prepare("
            SELECT final_grade, remarks
            FROM final_grades
            WHERE student_email=? AND teacher_id=? AND section_id=?
            LIMIT 1
        ");
        $fg->bind_param("sii", $s['email'], $teacher_id, $section_id);
        $fg->execute();
        $gradeRow = $fg->get_result()->fetch_assoc();
      ?>
      <tr>
        <td><?= htmlspecialchars($s['name']) ?></td>
        <td><?= htmlspecialchars($s['lrn']) ?></td>

        <td colspan="3">
          <form method="POST" style="display:flex;gap:6px;">
            <input type="hidden" name="student_email" value="<?= htmlspecialchars($s['email']) ?>">
            <input type="hidden" name="lrn" value="<?= htmlspecialchars($s['lrn']) ?>">

            <input type="number"
                   name="final_grade"
                   step="0.01"
                   min="0"
                   max="100"
                   required
                   value="<?= htmlspecialchars($gradeRow['final_grade'] ?? '') ?>">

            <input type="text"
                   name="remarks"
                   placeholder="Remarks"
                   value="<?= htmlspecialchars($gradeRow['remarks'] ?? '') ?>">

            <button type="submit" name="submit_final_grade">Save</button>
          </form>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>


  </main>
</section>

<script>
const menuBar = document.querySelector('#content nav .bx.bx-menu');
const sidebar = document.getElementById('sidebar');
const switchMode = document.getElementById('switch-mode');

// Toggle sidebar
menuBar.addEventListener('click', () => sidebar.classList.toggle('hide'));

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
