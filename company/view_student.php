<?php
session_start();
include('../includes/db_connection.php');
include('../includes/functions.php');

// Check if the user is logged in and has the company role
if (!is_logged_in() || get_user_role() != 'company') {
    redirect('/login.php');
}

// Check if student_id is provided in the URL
if (!isset($_GET['student_id']) || !is_numeric($_GET['student_id'])) {
    header("Location: /internship_logbook/company/index.php"); // Redirect to company dashboard
    exit();
}

$student_id = $_GET['student_id'];
$company_user_id = $_SESSION['user_id'];

// Fetch the company ID
$company_sql = "SELECT id FROM companies WHERE user_id = ?";
$company_stmt = $conn->prepare($company_sql);
$company_stmt->bind_param("i", $company_user_id);
$company_stmt->execute();
$company_result = $company_stmt->get_result();
if ($company_result->num_rows > 0) {
    $company = $company_result->fetch_assoc();
    $company_id = $company['id'];
} else {
    die("Company not found.");
}
$company_stmt->close();

// Verify if the student belongs to this company
$student_sql = "SELECT s.*, u.username, u.email FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ? AND s.company_id = ?";
$student_stmt = $conn->prepare($student_sql);
$student_stmt->bind_param("ii", $student_id, $company_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();

if ($student_result->num_rows != 1) {
    header("Location: /internship_logbook/company/index.php"); // Redirect if not authorized
    exit();
}

$student = $student_result->fetch_assoc();
$student_stmt->close();

// Fetch logbook entries for the student
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

<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Time</th>
            <th>Tasks</th>
            <th>Problems</th>
            <th>Solutions</th>
            <th>Comments</th>
            <th>Advisor Comment</th>
            <th>Rating</th>
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
                <td><?php echo nl2br(htmlspecialchars($row['advisor_comment'])); ?></td>
                <td><?php echo htmlspecialchars($row['company_rating']); ?></td>
                <td>
                    <?php if ($row['file_path']): ?>
                        <a href="/internship_logbook/<?php echo htmlspecialchars($row['file_path']); ?>" target="_blank">Download</a>
                    <?php else: ?>
                        No File
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php include('../includes/footer.php'); ?>