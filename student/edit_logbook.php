<?php
session_start();
include('../includes/db_connection.php');
include('../includes/functions.php');

if (!is_logged_in() || get_user_role() != 'student') {
    redirect('/login.php');
}

$user_id = $_SESSION['user_id'];

// ดึงข้อมูล student_id
$student_id_sql = "SELECT id FROM students WHERE user_id = ?";
$stmt_student = $conn->prepare($student_id_sql);
$stmt_student->bind_param('i', $user_id);
$stmt_student->execute();
$student_result = $stmt_student->get_result();
$student_row = $student_result->fetch_assoc();
$student_id = $student_row['id'];
$stmt_student->close();

$message = '';
$log_entry = null;

// *** ดึงข้อมูล Log Entry (เมื่อโหลดหน้า Edit) ***
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $log_id = $_GET['id'];

    $sql = "SELECT * FROM logbook_entries WHERE id = ? AND student_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $log_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $log_entry = $result->fetch_assoc();
    } else {
        $message = "Log entry not found or you don't have permission to edit it.";
        $log_entry = null; // Set to null if not found
    }
    $stmt->close();
}

// *** จัดการ UPDATE (เมื่อ submit form) ***
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $log_id = $_POST['id']; // รับจาก hidden input
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $tasks = $_POST['tasks'];
    $problems = $_POST['problems'];
    $solutions = $_POST['solutions'];
    $comments = $_POST['comments'];

    // File Upload Handling
    $file_path = $log_entry['file_path']; // ใช้ค่าเดิม ถ้าไม่มีการอัปโหลดใหม่

    if (isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['file']['tmp_name'];
        $file_name = $_FILES['file']['name'];
        $file_size = $_FILES['file']['size']; // เพิ่ม
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx');
        $max_file_size = 5 * 1024 * 1024; // 5 MB

        if (!in_array($file_ext, $allowed_extensions)) {
            $message = "Invalid file type. Allowed types: " . implode(', ', $allowed_extensions);
            goto skip_update;
        }

        if ($file_size > $max_file_size) { // เพิ่ม
            $message = "File is too large. Maximum size is " . ($max_file_size / (1024 * 1024)) . " MB.";
            goto skip_update;
        }

        $new_file_name = uniqid() . '_' . time() . '.' . $file_ext;
        $destination = $_SERVER['DOCUMENT_ROOT'] . '/internship_logbook/uploads/' . $student_id . '/' . $new_file_name;

        if (move_uploaded_file($file_tmp, $destination)) {
            $file_path = 'uploads/' . $student_id . '/' . $new_file_name;

            // ลบไฟล์เก่า (ถ้ามี)
            if ($log_entry['file_path'] && file_exists($_SERVER['DOCUMENT_ROOT'] . '/internship_logbook/' . $log_entry['file_path'])) {
                unlink($_SERVER['DOCUMENT_ROOT'] . '/internship_logbook/' . $log_entry['file_path']);
            }
        } else {
            $message = "Error uploading file.";
            goto skip_update;
        }
    }

    // Update ฐานข้อมูล
    $update_sql = "UPDATE logbook_entries SET date = ?, start_time = ?, end_time = ?, tasks = ?, problems = ?, solutions = ?, comments = ?, file_path = ? WHERE id = ? AND student_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssssssssii", $date, $start_time, $end_time, $tasks, $problems, $solutions, $comments, $file_path, $log_id, $student_id);

    if ($update_stmt->execute()) {
        $message = "Log entry updated successfully.";
        header("Location: /internship_logbook/student/logbook.php");
        exit();
    } else {
        $message = "Error updating log entry: " . $update_stmt->error;
    }
    $update_stmt->close();

    skip_update: // Label for goto
}

$conn->close();
include('../includes/header.php');
?>

<h2>Edit Logbook Entry</h2>

<?php if ($message): ?>
    <p><?php echo $message; ?></p>
<?php endif; ?>

<?php if ($log_entry): ?>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($log_entry['id']); ?>">
        <div>
            <label for="date">Date:</label>
            <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($log_entry['date']); ?>" required>
        </div>
        <div>
            <label for="start_time">Start Time:</label>
            <input type="time" name="start_time" id="start_time" value="<?php echo htmlspecialchars($log_entry['start_time']); ?>">
        </div>
        <div>
            <label for="end_time">End Time:</label>
            <input type="time" name="end_time" id="end_time" value="<?php echo htmlspecialchars($log_entry['end_time']); ?>">
        </div>
        <div>
            <label for="tasks">Tasks:</label>
            <textarea name="tasks" id="tasks" rows="4" required><?php echo htmlspecialchars($log_entry['tasks']); ?></textarea>
        </div>
        <div>
            <label for="problems">Problems:</label>
            <textarea name="problems" id="problems" rows="2"><?php echo htmlspecialchars($log_entry['problems']); ?></textarea>
        </div>
        <div>
            <label for="solutions">Solutions:</label>
            <textarea name="solutions" id="solutions" rows="2"><?php echo htmlspecialchars($log_entry['solutions']); ?></textarea>
        </div>
        <div>
            <label for="comments">Comments:</label>
            <textarea name="comments" id="comments" rows="2"><?php echo htmlspecialchars($log_entry['comments']); ?></textarea>
        </div>
        <div>
            <label for="file">Current File:</label>
            <?php if ($log_entry['file_path']): ?>
                <a href="/internship_logbook/<?php echo htmlspecialchars($log_entry['file_path']); ?>" target="_blank">View</a>
            <?php else: ?>
                No File
            <?php endif; ?>
        </div>
        <div>
            <label for="file">Change File (optional):</label>
            <input type="file" name="file" id="file">
        </div>
        <button type="submit">Update Entry</button>
    </form>
<?php else: ?>
    <p>Log entry not found.</p>
<?php endif; ?>

<?php include('../includes/footer.php'); ?>