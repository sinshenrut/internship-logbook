<?php
session_start();
include('../includes/db_connection.php');
include('../includes/functions.php');

if (!is_logged_in() || get_user_role() != 'student') {
    redirect('/login.php');
}

$user_id = $_SESSION['user_id'];

// ดึงข้อมูล student_id
$sql_student = "SELECT id FROM students WHERE user_id = ?";
$stmt_student = $conn->prepare($sql_student);
$stmt_student->bind_param("i", $user_id);
$stmt_student->execute();
$result_student = $stmt_student->get_result();
$student = $result_student->fetch_assoc();
$student_id = $student['id'];
$stmt_student->close();


// สร้างโฟลเดอร์สำหรับเก็บไฟล์ (ถ้ายังไม่มี)
$upload_dir = $_SERVER['DOCUMENT_ROOT'] .'/internship_logbook/uploads/' . $student_id . '/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true); // สร้าง recursive directories
}


// บันทึก Log (เมื่อมีการ submit form)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $tasks = $_POST['tasks'];
    $problems = $_POST['problems'];
    $solutions = $_POST['solutions'];
    $comments = $_POST['comments'];
    $file_path = ''; // เริ่มต้นเป็นค่าว่าง

    // *** จัดการการอัปโหลดไฟล์ ***
    if (isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['file']['tmp_name'];
        $file_name = $_FILES['file']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
		$allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'); // เพิ่มนามสกุลไฟล์

        if (!in_array($file_ext, $allowed_extensions)) {
            $error_message = "Invalid file type.  Allowed types: " . implode(', ', $allowed_extensions);
            goto skip_upload;
        }


        // สร้างชื่อไฟล์ใหม่เพื่อป้องกันการเขียนทับและปัญหาชื่อไฟล์
        $new_file_name = uniqid() . '_' . time() . '.' . $file_ext;
        $destination = $upload_dir . $new_file_name;


        if (move_uploaded_file($file_tmp, $destination)) {
            $file_path = 'uploads/' . $student_id . '/' . $new_file_name; // เก็บ path ที่ถูกต้อง
        } else {
            $error_message = "Error uploading file.";
             goto skip_upload;
        }
    }
     skip_upload:

    // *** บันทึกลงฐานข้อมูล ***
    $sql = "INSERT INTO logbook_entries (student_id, date, start_time, end_time, tasks, problems, solutions, comments, file_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql); // ย้ายมาอยู่ใน block นี้
    $stmt->bind_param("issssssss", $student_id, $date, $start_time, $end_time, $tasks, $problems, $solutions, $comments, $file_path);


    if ($stmt->execute()) {
        $success_message = "Log entry saved successfully.";
    } else {
        $error_message = "Error saving log entry: " . $stmt->error;
    }
    $stmt->close(); // ย้ายมาอยู่ใน block ของ if ($_SERVER["REQUEST_METHOD"] == "POST")
}


// ดึงข้อมูล Logbook entries
$sql_log = "SELECT * FROM logbook_entries WHERE student_id = ? ORDER BY date DESC";
$stmt_log = $conn->prepare($sql_log);
$stmt_log->bind_param("i", $student_id);
$stmt_log->execute();
$result_log = $stmt_log->get_result();
$stmt_log->close();
$conn->close();

include('../includes/header.php');
?>

    <h2>Logbook</h2>

    <?php if (isset($success_message)): ?>
        <p style="color: green;"><?php echo $success_message; ?></p>
    <?php endif; ?>
    <?php if (isset($error_message)): ?>
        <p style="color: red;"><?php echo $error_message; ?></p>
    <?php endif; ?>

    <h3>Add New Entry</h3>
    <form method="post" enctype="multipart/form-data">
        <div>
            <label for="date">Date:</label>
            <input type="date" name="date" id="date" required>
        </div>
        <div>
            <label for="start_time">Start Time:</label>
            <input type="time" name="start_time" id="start_time">
        </div>
        <div>
            <label for="end_time">End Time:</label>
            <input type="time" name="end_time" id="end_time">
        </div>
        <div>
            <label for="tasks">Tasks:</label>
            <textarea name="tasks" id="tasks" rows="4" required></textarea>
        </div>
        <div>
            <label for="problems">Problems:</label>
            <textarea name="problems" id="problems" rows="2"></textarea>
        </div>
        <div>
            <label for="solutions">Solutions:</label>
            <textarea name="solutions" id="solutions" rows="2"></textarea>
        </div>
        <div>
            <label for="comments">Comments:</label>
            <textarea name="comments" id="comments" rows="2"></textarea>
        </div>
        <div>
            <label for="file">Upload File (optional):</label>
            <input type="file" name="file" id="file">
        </div>
        <button type="submit">Save Entry</button>
    </form>

    <h3>Logbook Entries</h3>
     <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Tasks</th>
                <th>Problems</th>
                <th>Solutions</th>
                <th>comments</th>
                <th>File</th>

            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result_log->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['date']; ?></td>
                    <td><?php echo $row['start_time'] . ' - ' . $row['end_time']; ?></td>
                    <td><?php echo nl2br(htmlspecialchars($row['tasks'])); ?></td>
                    <td><?php echo nl2br(htmlspecialchars($row['problems'])); ?></td>
   					<td><?php echo nl2br(htmlspecialchars($row['solutions'])); ?></td>
                     <td><?php echo nl2br(htmlspecialchars($row['comments'])); ?></td>
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