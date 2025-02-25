<?php
session_start();
include('../includes/db_connection.php');
include('../includes/functions.php');

if (!is_logged_in() || get_user_role() != 'student') {
    redirect('/login.php');
}

$user_id = $_SESSION['user_id'];

// ดึงข้อมูลนักศึกษา
$sql = "SELECT s.*, u.username, u.email FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $student = $result->fetch_assoc();
    $student_id = $student['id']; // Get student ID
} else {
    die("Student data not found.");
}
$stmt->close();

// *** ดึงจำนวน unread comments ***
$unread_sql = "SELECT COUNT(*) AS unread_count FROM logbook_entries WHERE student_id = ? AND advisor_comment_read = 0";
$unread_stmt = $conn->prepare($unread_sql);
$unread_stmt->bind_param("i", $student_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_count = $unread_result->fetch_assoc()['unread_count'];
$unread_stmt->close();

$conn->close();
include('../includes/header.php');
?>

<h2>Welcome, <?php echo htmlspecialchars($student['username']); ?>!</h2>

<p>Student ID: <?php echo htmlspecialchars($student['student_id']); ?></p>
<p>Major: <?php echo htmlspecialchars($student['major']); ?></p>

<?php // *** แสดง Notification *** ?>
<?php if ($unread_count > 0): ?>
    <p>You have <a href="/internship_logbook/student/logbook.php"><?php echo $unread_count; ?> unread comments</a>.</p>
<?php endif; ?>

<a href="/internship_logbook/student/logbook.php">Go to Logbook</a>

<?php include('../includes/footer.php'); ?>