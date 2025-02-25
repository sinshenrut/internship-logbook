<?php
session_start();
include('../includes/db_connection.php');
include('../includes/functions.php');

if (!is_logged_in() || get_user_role() != 'student') {
    redirect('/login.php');
}

$user_id = $_SESSION['user_id'];

// *** ดึงข้อมูล student_id (ตรวจสอบให้ถูกต้อง) ***
$sql_student = "SELECT id FROM students WHERE user_id = ?";
$stmt_student = $conn->prepare($sql_student);
$stmt_student->bind_param("i", $user_id);
$stmt_student->execute();
$result_student = $stmt_student->get_result();

// *** เพิ่ม Error Handling ***
if ($result_student->num_rows == 0) {
    die("Error: Could not find student information for user ID: " . $user_id); // หรือแสดง error message ที่เหมาะสม
}

$student = $result_student->fetch_assoc();
$student_id = $student['id'];
$stmt_student->close();

// สร้างโฟลเดอร์สำหรับเก็บไฟล์ (ถ้ายังไม่มี)
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/internship_logbook/uploads/' . $student_id . '/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true); // *** เปลี่ยน permission เป็น 0777 (ชั่วคราว) ***
}

// *** จัดการ DELETE ***
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];

    // ตรวจสอบว่าเป็นตัวเลข และเป็นเจ้าของ log entry นั้นจริงๆ
    if (is_numeric($delete_id)) {
        // ดึงข้อมูลไฟล์ (ถ้ามี)
        $file_sql = "SELECT file_path FROM logbook_entries WHERE id = ? AND student_id = ?";
        $file_stmt = $conn->prepare($file_sql);
        $file_stmt->bind_param("ii", $delete_id, $student_id);
        $file_stmt->execute();
        $file_result = $file_stmt->get_result();

        if ($file_result->num_rows == 1) {
            $file_row = $file_result->fetch_assoc();
            $file_to_delete = $file_row['file_path'];

            // ลบข้อมูลจากฐานข้อมูล
            $delete_sql = "DELETE FROM logbook_entries WHERE id = ? AND student_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("ii", $delete_id, $student_id);

            if ($delete_stmt->execute()) {
                // ลบไฟล์ (ถ้ามี)
                if ($file_to_delete && file_exists($_SERVER['DOCUMENT_ROOT'] . '/internship_logbook/' . $file_to_delete)) {
                    unlink($_SERVER['DOCUMENT_ROOT'] . '/internship_logbook/' . $file_to_delete);
                }
                $success_message = "Log entry deleted successfully.";
            } else {
                $error_message = "Error deleting log entry: " . $delete_stmt->error;
            }

            $delete_stmt->close();
        }
        $file_stmt->close();
    }
}


// *** จัดการ INSERT (เมื่อมีการ submit form) ***
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $tasks = $_POST['tasks'];
    $problems = $_POST['problems'];
    $solutions = $_POST['solutions'];
    $comments = $_POST['comments'];
    $file_path = '';

    // จัดการการอัปโหลดไฟล์
    if (isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['file']['tmp_name'];
        $file_name = $_FILES['file']['name'];
        $file_size = $_FILES['file']['size']; // เพิ่มการตรวจสอบขนาดไฟล์
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx');
        $max_file_size = 5 * 1024 * 1024; // 5 MB

        if (!in_array($file_ext, $allowed_extensions)) {
            $error_message = "Invalid file type.  Allowed types: " . implode(', ', $allowed_extensions);
            goto skip_upload;
        }

        if ($file_size > $max_file_size) { // เพิ่มการตรวจสอบขนาดไฟล์
            $error_message = "File is too large. Maximum size is " . ($max_file_size / (1024 * 1024)) . " MB.";
            goto skip_upload;
        }

        // สร้างชื่อไฟล์ใหม่
        $new_file_name = uniqid() . '_' . time() . '.' . $file_ext;
        $destination = $upload_dir . $new_file_name;

        if (move_uploaded_file($file_tmp, $destination)) {
            $file_path = 'uploads/' . $student_id . '/' . $new_file_name;
        } else {
            $error_message = "Error uploading file.";
            goto skip_upload;
        }
    }
    skip_upload:

    // บันทึกลงฐานข้อมูล
    $sql = "INSERT INTO logbook_entries (student_id, date, start_time, end_time, tasks, problems, solutions, comments, file_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssssss", $student_id, $date, $start_time, $end_time, $tasks, $problems, $solutions, $comments, $file_path);

    if ($stmt->execute()) {
        $success_message = "Log entry saved successfully.";
    } else {
        $error_message = "Error saving log entry: " . $stmt->error;
    }
    $stmt->close();
}

// *** ดึงข้อมูล Logbook entries ***
$sql_log = "SELECT * FROM logbook_entries WHERE student_id = ? ORDER BY date DESC";
$stmt_log = $conn->prepare($sql_log);
$stmt_log->bind_param("i", $student_id);
$stmt_log->execute();
$result_log = $stmt_log->get_result();
// $stmt_log->close();
// $conn->close(); // *** ปิด connection เมื่อใช้งานเสร็จ ***

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
            <th>Comments</th>
            <th>Advisor Comment</th> <?php // เพิ่ม column ?>
            <th>Rating</th>
            <th>File</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
            <?php while ($row = $result_log->fetch_assoc()): ?>
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
                    <td>
                    <a href="/internship_logbook/student/edit_logbook.php?id=<?php echo $row['id']; ?>">Edit</a> |
                    <a href="?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this entry?')">Delete</a>
                </td>
                </tr>

                <?php // *** Set advisor_comment_read = 1 (เมื่อนักศึกษาดู) *** ?>
                <?php
                if ($row['advisor_comment'] != null && $row['advisor_comment_read'] == 0) {
                    $read_sql = "UPDATE logbook_entries SET advisor_comment_read = 1 WHERE id = ?";
                    $read_stmt = $conn->prepare($read_sql);
                    $read_stmt->bind_param("i", $row['id']);
                    $read_stmt->execute();
                    $read_stmt->close();
                }
                ?>

            <?php endwhile; ?>
            <?php
            // *** ปิด connection หลังจาก loop ***
            $stmt_log->close();
            $conn->close();
            ?>
        </tbody>
</table>

<?php include('../includes/footer.php'); ?>