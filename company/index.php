<?php
session_start();
include('../includes/db_connection.php');
include('../includes/functions.php');

if (!is_logged_in() || get_user_role() != 'company') {
    redirect('/login.php');
}

$user_id = $_SESSION['user_id'];

// ดึงข้อมูล company
$company_sql = "SELECT * FROM companies WHERE user_id = ?";
$company_stmt = $conn->prepare($company_sql);
$company_stmt->bind_param("i", $user_id);
$company_stmt->execute();
$company_result = $company_stmt->get_result();

// *** เพิ่ม Error Handling (ถ้าไม่พบข้อมูล company) ***
if ($company_result->num_rows == 0) {
    die("Error: Company data not found for user ID: " . $user_id); // หรือ redirect, หรือแสดงข้อความ error
}

$company = $company_result->fetch_assoc();
$company_id = $company['id'];
$company_stmt->close();

// ดึงรายชื่อนักศึกษาที่ฝึกงานกับ company นี้ (JOIN กับ logbook_entries)
$students_sql = "SELECT DISTINCT s.id, s.student_id, u.username
                FROM students s
                JOIN users u ON s.user_id = u.id
                JOIN logbook_entries le ON s.id = le.student_id
                WHERE le.company_id = ?
                ORDER BY s.student_id";
$students_stmt = $conn->prepare($students_sql);
$students_stmt->bind_param("i", $company_id);
$students_stmt->execute();
$students_result = $students_stmt->get_result();
$students_stmt->close();
$conn->close();

include('../includes/header.php');
?>

<h2>Welcome, <?php echo htmlspecialchars($company['name']); ?>!</h2>

<p>Company ID: <?php echo $company['id']; ?></p>
<p>Address: <?php echo htmlspecialchars($company['address']); ?></p>
<p>Contact Person: <?php echo htmlspecialchars($company['contact_person']); ?></p>
<p>Phone: <?php echo htmlspecialchars($company['phone']); ?></p>

<h3>Students Interning at Your Company</h3>

<?php if ($students_result->num_rows > 0): ?>
    <ul>
        <?php while ($student = $students_result->fetch_assoc()): ?>
            <li>
                <?php echo htmlspecialchars($student['student_id'] . " - " . $student['username']); ?>
                (<a href="/internship_logbook/company/view_student.php?student_id=<?php echo $student['id']; ?>">View Logbook</a>)
            </li>
        <?php endwhile; ?>
    </ul>
<?php else: ?>
    <p>No students are currently interning at your company.</p>
<?php endif; ?>

<?php include('../includes/footer.php'); ?>