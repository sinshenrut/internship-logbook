<?php
session_start();
include('../includes/db_connection.php');
include('../includes/functions.php');

if (!is_logged_in() || get_user_role() != 'admin') {
    redirect('/login.php');
}

// ดึงรายชื่อนักศึกษา (สำหรับ dropdown)
$students_sql = "SELECT s.id, s.student_id, u.username FROM students s JOIN users u ON s.user_id = u.id ORDER BY s.student_id";
$students_result = $conn->query($students_sql);

include('../includes/header.php');
?>

<h2>Internship Report</h2>

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

    <?php // TODO: เพิ่ม Date Range Picker (ถ้ามีเวลา) ?>

    <button type="submit">Generate Report</button>
</form>

<?php
// *** ส่วนแสดง Report ***
if (isset($_GET['student_id']) && $_GET['student_id'] !== "") { // ตรวจสอบว่าเลือกนักศึกษาแล้ว (ไม่ใช่ "All Students")

    $student_id = $_GET['student_id'];

    if(!is_numeric($student_id)){ // ถ้า student_id ไม่ใช่ตัวเลข
        echo "<p>Invalid student ID.</p>";
        include('../includes/footer.php');
        $conn->close();
        exit();
    }
        
    // ดึงข้อมูล logbook entries (เฉพาะของ student ที่เลือก)
    $report_sql = "SELECT * FROM logbook_entries WHERE student_id = ? ORDER BY date";
    $report_stmt = $conn->prepare($report_sql);
    $report_stmt->bind_param("i", $student_id);
    $report_stmt->execute();
    $report_result = $report_stmt->get_result();


    // *** คำนวณสถิติ ***
    $total_days = 0;
    $total_hours = 0;
    $total_rating = 0;
    $num_ratings = 0;
    $dates = []; // Array to store unique dates

    while ($row = $report_result->fetch_assoc()) {
        // จำนวนวัน (นับวันที่ไม่ซ้ำ)
        $date = $row['date'];
        if (!in_array($date, $dates)) {
            $dates[] = $date;
            $total_days++;
        }

        // จำนวนชั่วโมง
        $start = new DateTime($row['start_time']);
        $end = new DateTime($row['end_time']);
        $interval = $start->diff($end);
        $total_hours += ($interval->h + ($interval->i / 60)); // ชั่วโมง + นาที (แปลงเป็นเศษของชั่วโมง)

        // Average rating
        if ($row['company_rating'] !== null) {
            $total_rating += $row['company_rating'];
            $num_ratings++;
        }
    }

    $average_rating = ($num_ratings > 0) ? ($total_rating / $num_ratings) : 0;

    // Reset result set pointer (สำคัญ: ต้อง rewind result set กลับไปที่จุดเริ่มต้น)
    $report_result->data_seek(0);


    // *** แสดงผล Report (Summary) ***
    echo "<h3>Report Summary</h3>";
    echo "<p>Total Days: " . $total_days . "</p>";
    echo "<p>Total Hours: " . number_format($total_hours, 2) . "</p>"; // Format to 2 decimal places
    echo "<p>Average Rating: " . number_format($average_rating, 2) . "</p>";


    // *** แสดงผล Report (ตาราง Logbook Entries) ***
    if ($report_result->num_rows > 0) {
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

}elseif(isset($_GET['student_id']) && $_GET['student_id'] == ""){
    // *** กรณี "All Students" ***
    $report_sql = "SELECT le.*, s.student_id AS student_code
                   FROM logbook_entries le
                   JOIN students s ON le.student_id = s.id
                   ORDER BY le.date"; // เพิ่ม student_id ใน SELECT
    $report_stmt = $conn->prepare($report_sql);
    $report_stmt->execute();
    $report_result = $report_stmt->get_result();

    // *** คำนวณสถิติ (สำหรับ All Students) ***
    $total_days = 0;
    $total_hours = 0;
    $total_rating = 0;
    $num_ratings = 0;
    $dates = []; // Array to store unique dates
    $students = []; // Array to store unique students

     while ($row = $report_result->fetch_assoc()) {
        // จำนวนวัน (นับวันที่ไม่ซ้ำ)
        $date = $row['date'];
        if (!in_array($date, $dates)) {
            $dates[] = $date;
            $total_days++;
        }

        // จำนวนชั่วโมง
        $start = new DateTime($row['start_time']);
        $end = new DateTime($row['end_time']);
        $interval = $start->diff($end);
        $total_hours += ($interval->h + ($interval->i / 60));

        // Average rating
        if ($row['company_rating'] !== null) {
            $total_rating += $row['company_rating'];
            $num_ratings++;
        }

        // เก็บ student_id (ไม่ซ้ำ)
        $student_code = $row['student_code'];
        if (!in_array($student_code, $students)) {
            $students[] = $student_code;
        }
    }

    $average_rating = ($num_ratings > 0) ? ($total_rating / $num_ratings) : 0;
    $num_students = count($students); // นับจำนวนนักศึกษา

     // Reset result set pointer (สำคัญ: ต้อง rewind result set กลับไปที่จุดเริ่มต้น)
    $report_result->data_seek(0);

    // *** แสดงผล Report (Summary) ***
     echo "<h3>Report Summary (All Students)</h3>";
    echo "<p>Total Students: " . $num_students . "</p>";
    echo "<p>Total Days: " . $total_days . "</p>";
    echo "<p>Total Hours: " . number_format($total_hours, 2) . "</p>";
    echo "<p>Average Rating: " . number_format($average_rating, 2) . "</p>";


    // *** แสดงผล Report (ตาราง Logbook Entries) ***
    if ($report_result->num_rows > 0) {
        echo "<table>
        <thead>
            <tr>
                <th>Student ID</th> <?php // เพิ่ม column Student ID ?>
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
                    <td>" . htmlspecialchars($row['student_code']) . "</td>" .  // แสดง student_id
                "<td>" . htmlspecialchars($row['date']) . "</td>
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