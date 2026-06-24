<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$deletedName = '';

if ($id > 0) {
    try {
        // Fetch the user's name first (for the success message)
        $lookup = $pdo->prepare("SELECT name FROM users WHERE id = :id");
        $lookup->execute([':id' => $id]);
        $user = $lookup->fetch();
        $deletedName = $user['name'] ?? '';

        // Delete using a prepared statement — SQL injection safe (Task 3)
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);

        // If the deleted user was the logged-in user, log them out
        if ($id === (int)$_SESSION['user_id']) {
            session_unset();
            session_destroy();
            header("Location: login.php");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Delete User Error: " . $e->getMessage());
        header("Location: manage_users.php?error=delete_failed");
        exit();
    }
}

$nameParam = $deletedName ? '&name=' . urlencode($deletedName) : '';
header("Location: manage_users.php?deleted=1" . $nameParam);
exit();
?>
