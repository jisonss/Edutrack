<?php
session_start();
require 'config.php';

/**
 * Secure file serving
 * usage:
 * download.php?type=lesson&id=123&mode=view
 * download.php?type=lesson&id=123&mode=download
 * download.php?type=submission&id=55&mode=view
 * download.php?type=submission&id=55&mode=download
 */

if (!isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['email'])) {
    http_response_code(401);
    exit('Not logged in.');
}

$user_id = (int)$_SESSION['user_id'];
$role    = $_SESSION['role'];
$email   = $_SESSION['email'];

$type = $_GET['type'] ?? 'lesson';     // lesson | submission
$id   = (int)($_GET['id'] ?? 0);
$mode = $_GET['mode'] ?? 'download';   // view | download

if ($id <= 0) {
    http_response_code(400);
    exit('Invalid request.');
}

/** Resolve path safely so someone can’t request ../../etc/passwd */
function resolve_path_safe(string $relativePath): string {
    $base = realpath(__DIR__);
    $full = realpath(__DIR__ . DIRECTORY_SEPARATOR . $relativePath);

    if ($base === false || $full === false) return '';
    if (strpos($full, $base) !== 0) return ''; // must stay inside project folder
    return $full;
}

$resolvedPath = '';
$downloadName = '';
$ext = '';

// -------------------- LESSON FILES (uploads) --------------------
if ($type === 'lesson') {
    $stmt = $conn->prepare("SELECT id, filename, filepath, section_id, teacher_id FROM uploads WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $file = $stmt->get_result()->fetch_assoc();

    if (!$file) {
        http_response_code(404);
        exit('Lesson not found.');
    }

    if ($role === 'user') {
        $student_section_id = (int)($_SESSION['section_id'] ?? 0);
        if ($student_section_id <= 0) {
            http_response_code(403);
            exit('No section assigned.');
        }

        // Strict section privacy
        if ((int)$file['section_id'] !== $student_section_id) {
            http_response_code(403);
            exit('Access denied (wrong section).');
        }

        // Optional teacher filter enforcement if student selected a teacher
        $selected_teacher_id = (int)($_SESSION['selected_teacher_id'] ?? 0);
        if ($selected_teacher_id > 0 && (int)$file['teacher_id'] !== $selected_teacher_id) {
            http_response_code(403);
            exit('Access denied (wrong teacher).');
        }

    } elseif ($role === 'admin') {
        // Teacher can only access their own lessons
        if ((int)$file['teacher_id'] !== $user_id) {
            http_response_code(403);
            exit('Access denied (not your lesson).');
        }
    } else {
        http_response_code(403);
        exit('Access denied.');
    }

    $resolvedPath = resolve_path_safe($file['filepath']);
    $downloadName = $file['filename'] ?: basename($resolvedPath);
    $ext = strtolower(pathinfo($downloadName, PATHINFO_EXTENSION));
}

// -------------------- SUBMISSIONS (submissions) --------------------
elseif ($type === 'submission') {
    $stmt = $conn->prepare("
        SELECT s.id, s.submission_file, s.filepath, s.student_email, d.teacher_id, d.section_id
        FROM submissions s
        JOIN deadlines d ON d.id = s.deadline_id
        WHERE s.id=? LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $file = $stmt->get_result()->fetch_assoc();

    if (!$file) {
        http_response_code(404);
        exit('Submission not found.');
    }

    if ($role === 'user') {
        // student can only access their own submission
        if ($file['student_email'] !== $email) {
            http_response_code(403);
            exit('Access denied (not your submission).');
        }
    } elseif ($role === 'admin') {
        // teacher can only access submissions for their deadlines
        if ((int)$file['teacher_id'] !== $user_id) {
            http_response_code(403);
            exit('Access denied (not your deadline).');
        }

        // teacher must also be assigned to that section (extra safety)
        $chk = $conn->prepare("SELECT 1 FROM teacher_sections WHERE teacher_id=? AND section_id=? LIMIT 1");
        $sec = (int)$file['section_id'];
        $chk->bind_param("ii", $user_id, $sec);
        $chk->execute();
        if ($chk->get_result()->num_rows === 0) {
            http_response_code(403);
            exit('Access denied (not assigned).');
        }
    } else {
        http_response_code(403);
        exit('Access denied.');
    }

    $resolvedPath = resolve_path_safe($file['filepath']);
    $downloadName = $file['submission_file'] ?: basename($resolvedPath);
    $ext = strtolower(pathinfo($downloadName, PATHINFO_EXTENSION));
}
else {
    http_response_code(400);
    exit('Invalid type.');
}

if ($resolvedPath === '' || !is_file($resolvedPath)) {
    http_response_code(404);
    exit('File missing on server.');
}

$mime = match ($ext) {
    'pdf'  => 'application/pdf',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    default => 'application/octet-stream'
};

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');

if ($mode === 'view') {
    header('Content-Disposition: inline; filename="' . $downloadName . '"');
} else {
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
}

header('Content-Length: ' . filesize($resolvedPath));
readfile($resolvedPath);
exit;
