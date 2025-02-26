<?php
session_start();
include('../includes/db_connection.php');
include('../includes/functions.php');

if (!is_logged_in() || get_user_role() != 'advisor') {
    redirect('/login.php');
}

$user_id = $_SESSION['user_id'];

// ดึงข้อมูล advisor
$advisor_sql = "SELECT id FROM advisors WHERE user_id = ?";
$advisor_stmt = $conn->prepare($advisor_sql);
$advisor_stmt->bind_param("i", $user_id);
$advisor_stmt->execute();
$advisor_result = $advisor_stmt->get_result();
$advisor = $advisor_result->fetch_assoc();
$advisor_id = $advisor['id'];
$advisor_stmt->close();

// ดึงรายชื่อนักศึกษา (สำหรับ dropdown) *ภายใต้ advisor คนนี้*
$students_sql = "SELECT s.id, s.student_id, u.username FROM students s JOIN users u ON s.user_id = u.id WHERE s.advisor_id = ? ORDER BY s.student_id";
$students_stmt = $conn->prepare($students_sql);
$students_stmt->bind_param("i", $advisor_id);
$students_stmt->execute();
$students_result = $students_stmt->get_result();
// $students_stmt->close(); // Close *after* fetching results for the dropdown  <-- ไม่ต้อง close ตรงนี้

include('../includes/header.php');
?>

<h2>Advisor Report</h2>

<form method="get" action="">
    <div>
        <label for="student_id">Select Student:</label>
        <select name="student_id" id="student_id" required>
            <option value="">-- All Students --</option>
            <?php while ($student = $students_result->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($student['id']); ?>">
                    <?php echo htmlspecialchars($student['student_id'] . " - " . $student['username']); ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <button type="submit">Generate Report</button>
</form>

<?php
if (isset($_GET['student_id']) && $_GET['student_id'] !== "") {  // ดู report ของนักศึกษา *รายคน*
    $student_id = $_GET['student_id'];

    // Validate student_id
    if (!is_numeric($student_id)) {
        echo "<p>Invalid student ID.</p>";
        include('../includes/footer.php');
        $conn->close();
        exit();
    }

    // ดึงข้อมูล logbook entries (เฉพาะของ student ที่เลือก และ advisor คนนี้)
    $report_sql = "SELECT * FROM logbook_entries WHERE student_id = ? ORDER BY date";
    $report_stmt = $conn->prepare($report_sql);
    $report_stmt->bind_param("i", $student_id);
    $report_stmt->execute();
    $report_result = $report_stmt->get_result();

    // *** คำนวณสถิติ ***  (เหมือนกับ admin/report.php)
    $total_days = 0;
    $total_hours = 0;
    $total_rating = 0;
    $num_ratings = 0;
    $dates = [];

    while ($row = $report_result->fetch_assoc()) {
        $date = $row['date'];
        if (!in_array($date, $dates)) {
            $dates[] = $date;
            $total_days++;
        }

        $start = new DateTime($row['start_time']);
        $end = new DateTime($row['end_time']);
        $interval = $start->diff($end);
        $total_hours += ($interval->h + ($interval->i / 60));

        if ($row['company_rating'] !== null) {
            $total_rating += $row['company_rating'];
            $num_ratings++;
        }
    }

    $average_rating = ($num_ratings > 0) ? ($total_rating / $num_ratings) : 0;

    $report_result->data_seek(0); // Reset result pointer


    // *** แสดงผล Report (Summary) ***
    echo "<h3>Report Summary</h3>";
    echo "<p>Total Days: " . $total_days . "</p>";
    echo "<p>Total Hours: " . number_format($total_hours, 2) . "</p>";
    echo "<p>Average Rating: " . number_format($average_rating, 2) . "</p>";

    // *** แสดงผล Report (ตาราง Logbook Entries) ***
    if ($report_result->num_rows > 0) {
        // ... (ตาราง เหมือนกับ admin/report.php) ...
        echo "<table>
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
            <tbody>";

            while ($row = $report_result->fetch_assoc()) {
                echo "<tr>
                        <td>" . htmlspecialchars($row['date']) . "</td>
                        <td>" . htmlspecialchars($row['start_time']) . " - " . htmlspecialchars($row['end_time']) . "</td>
                        <td>" . nl2br(htmlspecialchars($row['tasks'])) . "</td>
                        <td>" . nl2br(htmlspecialchars($row['problems'])) . "</td>
                        <td>" . nl2br(htmlspecialchars($row['solutions'])) . "</td>
                        <td>" . nl2br(htmlspecialchars($row['comments'])) . "</td>
                        <td>" . nl2br(htmlspecialchars($row['advisor_comment'])) . "</td>
                        <td>" . htmlspecialchars($row['company_rating']) . "</td>
                        <td>";
                if ($row['file_path']) {
                    echo "<a href='/internship_logbook/" . htmlspecialchars($row['file_path']) . "' target='_blank'>Download</a>";
                } else {
                    echo "No File";
                }
                echo "</td></tr>";
            }
            echo "</tbody></table>";

    } else {
        echo "<p>No log entries for this student.</p>";
    }
    $report_stmt->close();


} elseif (isset($_GET['student_id']) && $_GET['student_id'] == "") { // ดู report ของนักศึกษา *ทั้งหมด* (ภายใต้ advisor คนนี้)
    // *** กรณี "All Students" (ที่อยู่ภายใต้ advisor คนนี้) ***
    $report_sql = "SELECT le.*, s.student_id AS student_code
                   FROM logbook_entries le
                   JOIN students s ON le.student_id = s.id
                   WHERE s.advisor_id = ?
                   ORDER BY le.date"; // เพิ่ม WHERE clause
    $report_stmt = $conn->prepare($report_sql);
    $report_stmt->bind_param("i", $advisor_id); // Bind advisor_id
    $report_stmt->execute();
    $report_result = $report_stmt->get_result();

    // *** คำนวณสถิติ (สำหรับ All Students) ***
    $total_days = 0;
    $total_hours = 0;
    $total_rating = 0;
    $num_ratings = 0;
    $dates = [];
    $students = [];

    while ($row = $report_result->fetch_assoc()) {
        $date = $row['date'];
        if (!in_array($date, $dates)) {
            $dates[] = $date;
            $total_days++;
        }

        $start = new DateTime($row['start_time']);
        $end = new DateTime($row['end_time']);
        $interval = $start->diff($end);
        $total_hours += ($interval->h + ($interval->i / 60));

        if ($row['company_rating'] !== null) {
            $total_rating += $row['company_rating'];
            $num_ratings++;
        }

        $student_code = $row['student_code'];
        if (!in_array($student_code, $students)) {
            $students[] = $student_code;
        }
    }

    $average_rating = ($num_ratings > 0) ? ($total_rating / $num_ratings) : 0;
    $num_students = count($students);

    $report_result->data_seek(0); // Reset result pointer

    // *** แสดงผล Report (Summary) ***
    echo "<h3>Report Summary (All Students)</h3>";
    echo "<p>Total Students: " . $num_students . "</p>";
    echo "<p>Total Days: " . $total_days . "</p>";
    echo "<p>Total Hours: " . number_format($total_hours, 2) . "</p>";
    echo "<p>Average Rating: " . number_format($average_rating, 2) . "</p>";

    // *** แสดงผล Report (ตาราง Logbook Entries) ***

    if ($report_result->num_rows > 0) {
        // ... (ตาราง เหมือนกับ admin/report.php) ...
        echo "<table>
        <thead>
            <tr>
                <th>Student ID</th>
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
        <tbody>";

        while ($row = $report_result->fetch_assoc()) {
            echo "<tr>
                    <td>" . htmlspecialchars($row['student_code']) . "</td>
                    <td>" . htmlspecialchars($row['date']) . "</td>
                    <td>" . htmlspecialchars($row['start_time']) . " - " . htmlspecialchars($row['end_time']) . "</td>
                    <td>" . nl2br(htmlspecialchars($row['tasks'])) . "</td>
                    <td>" . nl2br(htmlspecialchars($row['problems'])) . "</td>
                    <td>" . nl2br(htmlspecialchars($row['solutions'])) . "</td>
                    <td>" . nl2br(htmlspecialchars($row['comments'])) . "</td>
                    <td>" . nl2br(htmlspecialchars($row['advisor_comment'])) . "</td>
                    <td>" . htmlspecialchars($row['company_rating']) . "</td>
                    <td>";
            if ($row['file_path']) {
                echo "<a href='/internship_logbook/" . htmlspecialchars($row['file_path']) . "' target='_blank'>Download</a>";
            } else {
                echo "No File";
            }
            echo "</td></tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p>No log entries found.</p>";
    }
    $report_stmt->close();
}
?>

<?php
include('../includes/footer.php');
$conn->close();
?>