<?php
session_start();
include('../includes/db_connection.php');
include('../includes/functions.php');


if (!is_logged_in() || get_user_role() != 'student') {
    redirect('/login.php'); 
}

// ดึงข้อมูลนักศึกษา
$user_id = $_SESSION['user_id'];
$sql = "SELECT s.*, u.username, u.email FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $student = $result->fetch_assoc();
} else {
    // Handle error - student not found
    die("Student data not found.");
}

$stmt->close();
$conn->close();
include('../includes/header.php');

?>


    <h2>Welcome, <?php echo $student['username']; ?>!</h2>
    <p>Student ID: <?php echo $student['student_id']; ?></p>
    <p>Major: <?php echo $student['major']; ?></p>
    <a href="logbook.php">Go to Logbook</a>

<?php  include('../includes/footer.php'); ?>