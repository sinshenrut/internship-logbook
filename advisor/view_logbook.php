<?php
session_start();
include('../includes/db_connection.php');
include('../includes/functions.php');

if (!is_logged_in() || get_user_role() != 'advisor') {
    redirect('/login.php');
}

// ตรวจสอบว่ามีการส่ง student_id มาหรือไม่
if (!isset($_GET['student_id']) || !is_numeric($_GET['student_id'])) {
    redirect('/advisor/index.php'); // หรือแสดง error message
}

$student_id = $_GET['student_id'];

// ดึงข้อมูลนักศึกษา (ตรวจสอบว่าเป็นนักศึกษาที่อาจารย์คนนี้ดูแลจริงหรือไม่)
$student_sql = "SELECT s.*, u.username, u.email
                FROM students s
                JOIN users u ON s.user_id = u.id
                WHERE s.id = ? AND s.advisor_id = (SELECT id FROM advisors WHERE user_id = ?)";
$student_stmt = $conn->prepare($student_sql);
$student_stmt->bind_param("ii", $student_id, $_SESSION['user_id']);
$student_stmt->execute();
$student_result = $student_stmt->get_result();

if ($student_result->num_rows != 1) {
    // ไม่พบนักศึกษา หรืออาจารย์ไม่มีสิทธิ์เข้าถึง
    redirect('/advisor/index.php'); // หรือแสดง error message
}

$student = $student_result->fetch_assoc();
$student_stmt->close();

// *** จัดการ Comment/Rating Submission ***
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['log_id'])) {
    $log_id = $_POST['log_id'];
    $advisor_comment = $_POST['advisor_comment'];
    $company_rating = $_POST['company_rating'];

    // Validate: ตรวจสอบว่า log_id เป็นของนักศึกษาคนนี้จริง (ป้องกันการ submit ข้อมูลมั่ว)
    $check_sql = "SELECT 1 FROM logbook_entries WHERE id = ? AND student_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $log_id, $student_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows == 1) {
        // Update ฐานข้อมูล
        $update_sql = "UPDATE logbook_entries SET advisor_comment = ?, company_rating = ?, advisor_comment_read = 0 WHERE id = ?"; // แก้ไข
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sii", $advisor_comment, $company_rating, $log_id);
        $update_stmt->execute();
        $update_stmt->close();
    
        $success_message = "Comment and rating saved.";
    } else {
        $error_message = "Invalid log entry.";
    }
    $check_stmt->close();
}


// ดึงข้อมูล Logbook entries ของนักศึกษาคนนี้
$log_sql = "SELECT * FROM logbook_entries WHERE student_id = ? ORDER BY date DESC";
$log_stmt = $conn->prepare($log_sql);
$log_stmt->bind_param("i", $student_id);
$log_stmt->execute();
$log_result = $log_stmt->get_result();
$log_stmt->close();
$conn->close();

include('../includes/header.php');
?>

<h2>Logbook for <?php echo htmlspecialchars($student['username']); ?> (<?php echo htmlspecialchars($student['student_id']); ?>)</h2>

<?php if (isset($success_message)): ?>
    <p style="color: green;"><?php echo $success_message; ?></p>
<?php endif; ?>
<?php if (isset($error_message)): ?>
    <p style="color: red;"><?php echo $error_message; ?></p>
<?php endif; ?>

<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Time</th>
            <th>Tasks</th>
            <th>Problems</th>
            <th>Solutions</th>
            <th>Comments</th>
            <th>Advisor Comment</th> <?php // เพิ่ม column ?>
            <th>Rating</th> <?php // เพิ่ม column ?>
            <th>File</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $log_result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['date']); ?></td>
                <td><?php echo htmlspecialchars($row['start_time']) . ' - ' . htmlspecialchars($row['end_time']); ?></td>
                <td><?php echo nl2br(htmlspecialchars($row['tasks'])); ?></td>
                <td><?php echo nl2br(htmlspecialchars($row['problems'])); ?></td>
                <td><?php echo nl2br(htmlspecialchars($row['solutions'])); ?></td>
                <td><?php echo nl2br(htmlspecialchars($row['comments'])); ?></td>
                <td><?php echo nl2br(htmlspecialchars($row['advisor_comment'])); ?></td> <?php // แสดง comment ?>
                <td><?php echo htmlspecialchars($row['company_rating']); ?></td> <?php // แสดง rating ?>

                <td>
                    <?php if ($row['file_path']): ?>
                        <a href="/internship_logbook/<?php echo htmlspecialchars($row['file_path']); ?>" target="_blank">Download</a>
                    <?php else: ?>
                        No File
                    <?php endif; ?>
                </td>
            </tr>

            <?php // *** Form for Comment/Rating *** ?>
            <tr>
                <td colspan="9">
                    <form method="post" action="">
                        <input type="hidden" name="log_id" value="<?php echo $row['id']; ?>">
                        <label for="advisor_comment_<?php echo $row['id']; ?>">Comment:</label>
                        <textarea name="advisor_comment" id="advisor_comment_<?php echo $row['id']; ?>" rows="2"><?php echo htmlspecialchars($row['advisor_comment']); ?></textarea>
                        <label for="company_rating_<?php echo $row['id']; ?>">Rating:</label>
                        <input type="number" name="company_rating" id="company_rating_<?php echo $row['id']; ?>" min="1" max="5" value="<?php echo htmlspecialchars($row['company_rating']); ?>">
                        <button type="submit">Save</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php include('../includes/footer.php'); ?>