<?php
session_start();
require 'config.php';

if (!isset($_SESSION['LRN'])) {
    exit("Unauthorized");
}

if (isset($_POST['id'], $_POST['action'])) {
    $id = intval($_POST['id']);
    $action = $_POST['action'];

    if ($action === 'archive') {
        $stmt = $conn->prepare("UPDATE inbox SET archived = 1 WHERE id = ? AND lrn = ?");
        $stmt->bind_param("is", $id, $_SESSION['LRN']);
        $stmt->execute();
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM inbox WHERE id = ? AND lrn = ?");
        $stmt->bind_param("is", $id, $_SESSION['LRN']);
        $stmt->execute();
    }
}
?>
