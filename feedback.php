
<?php
// user_page.php
session_start();
require 'config.php';

if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

// Get student details
$stmt = $conn->prepare("SELECT name, lrn FROM users WHERE email = ?");
$stmt->bind_param("s", $_SESSION['email']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$name = $user['name'];
$lrn = $user['lrn'];

// Fetch grades
$grades = $conn->prepare("SELECT * FROM grades WHERE lrn = ?");
$grades->bind_param("s", $lrn);
$grades->execute();
$gradesResult = $grades->get_result();

// Fetch uploaded lessons
$files = $conn->prepare("SELECT * FROM uploads ORDER BY uploaded_at DESC");
$files->execute();
$filesResult = $files->get_result();

// Today’s date
$today = date('Y-m-d');

// ✅ Fetch ONLY upcoming deadlines (today or later)
$deadlines = $conn->prepare("SELECT * FROM deadlines WHERE deadline_date >= ? ORDER BY deadline_date ASC");
$deadlines->bind_param("s", $today);
$deadlines->execute();
$deadlinesResult = $deadlines->get_result();

// Fetch student submissions
$stmtSubmissions = $conn->prepare("SELECT * FROM submissions WHERE student_email = ? ORDER BY submitted_at DESC");
$stmtSubmissions->bind_param("s", $_SESSION['email']);
$stmtSubmissions->execute();
$submissions = $stmtSubmissions->get_result();

if (isset($_POST['archive_id'])) {
    $notifId = intval($_POST['archive_id']);

    $stmt = $conn->prepare("UPDATE inbox SET archived = 1 WHERE id = ? AND email = ?");
    $stmt->bind_param("is", $notifId, $email);
    $stmt->execute();

    echo 'success';
    exit();
}

// Handle delete notification (AJAX)
if (isset($_POST['delete_id'])) {
    $notifId = intval($_POST['delete_id']);

    $stmt = $conn->prepare("DELETE FROM inbox WHERE id = ? AND email = ?");
    $stmt->bind_param("is", $notifId, $email);
    $stmt->execute();

    echo 'deleted';
    exit();
}

// Fetch inbox notifications (only unarchived)
$notifStmt = $conn->prepare("SELECT * FROM inbox WHERE email = ? AND archived = 0 ORDER BY created_at DESC");
$notifStmt->bind_param("s", $email);
$notifStmt->execute();
$notifResult = $notifStmt->get_result();

// Count unread notifications for badge
$unreadCount = $notifResult->num_rows;

// Handle "Mark as Noted"
if (isset($_POST['mark_noted']) && isset($_POST['submission_id'])) {
    $submissionId = $_POST['submission_id'];

    $stmt = $conn->prepare("UPDATE submissions SET feedback_noted = 1 WHERE id = ? AND student_email = ?");
    $stmt->bind_param("is", $submissionId, $_SESSION['email']);
    $stmt->execute();

    header("Location: user_page.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EduTrack</title>

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">

  <!-- Boxicons -->
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">

  <!-- Your CSS -->
  <link rel="stylesheet" href="usersas.css">
</head>
<body>
    <?php if (isset($_SESSION['message'])): ?>
<script>
    Swal.fire({
        title: 'Notice',
        text: "<?= $_SESSION['message'] ?>",
        icon: 'info',
        confirmButtonText: 'OK'
    });
</script>
<?php unset($_SESSION['message']); endif; ?>
  <!-- Sidebar -->
  <section id="sidebar">
    <a href="#" class="brand"><i class='bx bxs-book-open'></i>EduTrack</a>
    <ul class="side-menu top">
      <li><a href="user_page.php"><i class='bx bxs-alarm'></i> Upcoming Deadline</a></li>
      <li><a href="Powerpoint.php"><i class='bx bxs-notepad'></i> Lessons</a></li>
      <li class="active"><a href=""><i class='bx bxs-file'></i> Feedback</a></li>
      <li><a href="finalgrade.php"><i class='bx bxs-report'></i> Reports</a></li>
    </ul>
    <ul class="side-menu">
      <li><a href="index.php" class="logout"><i class='bx bxs-log-out-circle'></i> Logout</a></li>
    </ul>
  </section>

  <!-- Content -->
  <section id="content">
    <!-- Navbar -->
    <nav>
      <i class='bx bx-menu'></i>
      <h1 class="nav-link">Welcome, Student <?= htmlspecialchars($_SESSION['name']) ?>!</h1>
      <input type="checkbox" id="switch-mode" hidden>
      <label for="switch-mode" class="switch-mode"></label>
      <!-- 🔔 Bell + Dropdown -->
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

    <!-- Main -->
    <main>
      <div class="head-title">
        <div class="left">
           <i class='bx bxs-notepad'></i>
           <h1>Feedback</h1>
      </div>

      <!-- Info Cards -->
	   <!-- Feedback Section -->
<div class="table-data" id="feedback">
    <h3>Your Submissions & Feedback:</h3>
    
    <table border="1" class="table-data">
        <thead>
            <tr>
                <th>File</th>
                <th>Submitted At</th>
                <th>Feedback</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $submissions->fetch_assoc()): ?>
            <tr class="<?= $row['feedback'] && !$row['feedback_noted'] ? 'highlight-feedback' : '' ?>">
                <td><?= htmlspecialchars($row['filename']) ?></td>
                <td><?= date("F d, Y, h:i a", strtotime($row['submitted_at'])) ?></td>
                <td>
                    <?= htmlspecialchars($row['feedback'] ?? 'No feedback yet') ?>
                </td>
                <td>
                    <?php if ($row['feedback'] && !$row['feedback_noted']): ?>
                        <form method="POST">
                            <input type="hidden" name="submission_id" value="<?= $row['id'] ?>">
                            <button type="submit" name="mark_noted">Mark as Noted</button>
                        </form>
                    <?php else: ?>
                        <span>✔️ Noted</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>



  <!-- JavaScript -->
  <script>
    const menuBar = document.querySelector('#content nav .bx.bx-menu');
    const sidebar = document.getElementById('sidebar');
    const switchMode = document.getElementById('switch-mode');

    menuBar.addEventListener('click', () => {
      sidebar.classList.toggle('hide');
    });

    switchMode.addEventListener('change', () => {
      document.body.classList.toggle('dark', switchMode.checked);
    });
  </script>
</body>
</html>


