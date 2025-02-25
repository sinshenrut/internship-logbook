<?php
session_start();
include('../includes/db_connection.php');
include('../includes/functions.php');

if (!is_logged_in() || get_user_role() != 'advisor') {
    redirect('/login.php');
}

$user_id = $_SESSION['user_id'];

// ดึงข้อมูลอาจารย์
$advisor_sql = "SELECT id FROM advisors WHERE user_id = ?";
$advisor_stmt = $conn->prepare($advisor_sql);
$advisor_stmt->bind_param("i", $user_id);
$advisor_stmt->execute();
$advisor_result = $advisor_stmt->get_result();
$advisor = $advisor_result->fetch_assoc();
$advisor_id = $advisor['id'];
$advisor_stmt->close();


// ดึงรายชื่อนักศึกษาที่อาจารย์คนนี้ดูแล
$sql = "SELECT s.id, s.student_id, u.username, u.email
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.advisor_id = ?
        ORDER BY s.student_id";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $advisor_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
$conn->close();

include('../includes/header.php');
?>

<h2>Advisor Dashboard</h2>

<h3>Students Under Supervision</h3>

<?php if ($result->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Student ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td>
                        <a href="/internship_logbook/advisor/view_logbook.php?student_id=<?php echo $row['id']; ?>">View Logbook</a>  <?php // ตรวจสอบตรงนี้ ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No students under your supervision.</p>
<?php endif; ?>

<?php include('../includes/footer.php'); ?>