<?php
session_start();
include('../includes/db_connection.php');
include('../includes/functions.php');

// --- DEBUGGING: Enable error reporting ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!is_logged_in() || get_user_role() != 'student') {
    redirect('/login.php');
}

$user_id = $_SESSION['user_id'];

// --- DEBUGGING: Output user_id ---
//echo "User ID: " . $user_id . "<br>"; // ปิด Debug

// ดึงข้อมูล student_id
$sql_student = "SELECT id, student_id FROM students WHERE user_id = ?";
$stmt_student = $conn->prepare($sql_student);
$stmt_student->bind_param("i", $user_id);
$stmt_student->execute();
$result_student = $stmt_student->get_result();

// --- DEBUGGING: Check if student exists ---
if ($result_student->num_rows == 0) {
    die("Error: Could not find student information for user ID: " . $user_id);
}

$student = $result_student->fetch_assoc();
$student_id = $student['id'];
$stmt_student->close();

// --- DEBUGGING: Output student_id ---
//echo "Student ID: " . $student_id . "<br>";  // ปิด Debug

// *** สร้างโฟลเดอร์สำหรับเก็บไฟล์ (ถ้ายังไม่มี) ***
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/internship_logbook/uploads/' . $student_id . '/';
//echo "Upload Directory: " . $upload_dir . "<br>"; // Debugging output  // ปิด Debug

if (!file_exists($upload_dir)) {
    //echo "Creating directory: " . $upload_dir . "<br>"; // Debugging output // ปิด Debug
    if (!mkdir($upload_dir, 0755, true)) { // Use 0755 for permissions
        die("Error: Failed to create directory: " . $upload_dir); // More specific error
    }
    //echo "Directory created successfully.<br>"; // Debugging output // ปิด Debug
} else {
    //echo "Directory already exists.<br>"; // Debugging output // ปิด Debug
}

// *** ดึงข้อมูล Companies (สำหรับ dropdown) ***
$companies_sql = "SELECT id, name FROM companies";
$companies_result = $conn->query($companies_sql);


// *** จัดการ CREATE (เมื่อ submit form) ***
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'create') {
    // --- DEBUGGING: Output POST data ---
    // echo "POST data: <pre>";  // ปิด Debug
    // print_r($_POST);  // ปิด Debug
    // echo "</pre>";  // ปิด Debug

    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $tasks = $_POST['tasks'];
    $problems = $_POST['problems'];
    $solutions = $_POST['solutions'];
    $comments = $_POST['comments'];
    $company_id = isset($_POST['company_id']) ? $_POST['company_id'] : null;

    // File upload
    $file_path = ''; // Initialize

    // --- DEBUGGING: Output FILES data ---
    // echo "FILES data: <pre>"; // ปิด Debug
    // print_r($_FILES); // ปิด Debug
    // echo "</pre>"; // ปิด Debug

   if (isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['file']['tmp_name'];
        $file_name = $_FILES['file']['name'];
        $file_size = $_FILES['file']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx');
        $max_file_size = 5 * 1024 * 1024; // 5 MB

        // echo "File Temp Path: " . $file_tmp . "<br>"; // Debugging // ปิด Debug
        // echo "File Name: " . $file_name . "<br>"; // Debugging // ปิด Debug
        // echo "File Size: " . $file_size . "<br>"; // Debugging // ปิด Debug
        // echo "File Extension: " . $file_ext . "<br>"; // Debugging // ปิด Debug

        if (!in_array($file_ext, $allowed_extensions)) {
            $error_message = "Invalid file type.  Allowed types: " . implode(', ', $allowed_extensions);
            goto skip_upload; // Skip file upload if invalid
        }

        if ($file_size > $max_file_size) {
            $error_message = "File is too large. Maximum size is " . ($max_file_size / (1024 * 1024)) . " MB.";
            goto skip_upload; // Skip file upload if too large
        }


        // Generate unique file name
        $new_file_name = uniqid() . '_' . time() . '.' . $file_ext;
        $destination = $upload_dir . $new_file_name;
         //echo "Destination Path: " . $destination . "<br>"; // ปิด Debug

        if (move_uploaded_file($file_tmp, $destination)) {
            $file_path = 'uploads/' . $student_id . '/' . $new_file_name; // Relative path for database
             //echo "File uploaded successfully.<br>"; // ปิด Debug
        } else {
            $error_message = "Error uploading file.";
             //echo "move_uploaded_file failed.<br>"; // Debug output // ปิด Debug
            goto skip_upload; // Skip database insertion if upload failed
        }
    }
    skip_upload:


    // Insert into database
    $sql = "INSERT INTO logbook_entries (student_id, date, start_time, end_time, tasks, problems, solutions, comments, file_path, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssssssi", $student_id, $date, $start_time, $end_time, $tasks, $problems, $solutions, $comments, $file_path, $company_id);

    if ($stmt->execute()) {
        $success_message = "Log entry saved successfully.";
        header("Location: /internship_logbook/student/logbook.php"); // Redirect after successful insertion
        exit();  // *** สำคัญ: ต้อง exit() หลัง header() ***
    } else {
        $error_message = "Error saving log entry: " . $stmt->error;
    }
    $stmt->close();
}

// *** (Rest of the code for displaying log entries, DELETE, etc.) ***
// ดึงข้อมูล Logbook entries ของนักศึกษาคนนี้
$log_sql = "SELECT * FROM logbook_entries WHERE student_id = ? ORDER BY date DESC";
$log_stmt = $conn->prepare($log_sql);
$log_stmt->bind_param("i", $student_id);
$log_stmt->execute();
$log_result = $log_stmt->get_result();
$log_stmt->close();


include('../includes/header.php');
?>

<h2>Logbook</h2>

<?php if (isset($success_message)): ?>
    <p style="color: green;"><?php echo $success_message; ?></p>
<?php endif; ?>
<?php if (isset($error_message)): ?>
    <p style="color: red;"><?php echo $error_message; ?></p>
<?php endif; ?>

<form method="post" action="" enctype="multipart/form-data">
    <input type="hidden" name="action" value="create">
      <div>
        <label for="company_id">Company:</label>
        <select name="company_id" id="company_id">
            <option value="">-- Select Company --</option>
            <?php while ($company = $companies_result->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($company['id']); ?>">
                    <?php echo htmlspecialchars($company['name']); ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    <div>
        <label for="date">Date:</label>
        <input type="date" name="date" id="date" required>
    </div>
    <div>
        <label for="start_time">Start Time:</label>
        <input type="time" name="start_time" id="start_time" required>
    </div>
    <div>
        <label for="end_time">End Time:</label>
        <input type="time" name="end_time" id="end_time" required>
    </div>
    <div>
        <label for="tasks">Tasks:</label>
        <textarea name="tasks" id="tasks" rows="4" required></textarea>
    </div>
    <div>
        <label for="problems">Problems:</label>
        <textarea name="problems" id="problems" rows="4"></textarea>
    </div>
    <div>
        <label for="solutions">Solutions:</label>
        <textarea name="solutions" id="solutions" rows="4"></textarea>
    </div>
    <div>
        <label for="comments">Comments:</label>
        <textarea name="comments" id="comments" rows="4"></textarea>
    </div>
    <div>
        <label for="file">File:</label>
        <input type="file" name="file" id="file">
    </div>
    <button type="submit">Add Log Entry</button>
</form>

<table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Tasks</th>
                <th>Problems</th>
                <th>Solutions</th>
                <th>Comments</th>
                 <th>Company</th>
                <th>File</th>
                <th>Actions</th>
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
               <td>
                    <?php
                    // ดึงข้อมูล company (ถ้ามี)
                    if ($row['company_id']) {
                        $company_sql = "SELECT name FROM companies WHERE id = ?";
                        $company_stmt = $conn->prepare($company_sql);
                        $company_stmt->bind_param("i", $row['company_id']);
                        $company_stmt->execute();
                        $company_result = $company_stmt->get_result();
                        if ($company_result->num_rows > 0) {
                            $company_data = $company_result->fetch_assoc();
                            echo htmlspecialchars($company_data['name']);
                        } else {
                            echo "N/A"; // ไม่พบบริษัท
                        }
                        $company_stmt->close();
                    } else {
                        echo "N/A"; // ไม่ได้เลือกบริษัท
                    }
                    ?>
                </td>
                <td>
                    <?php if ($row['file_path']): ?>
                        <a href="/internship_logbook/<?php echo htmlspecialchars($row['file_path']); ?>" target="_blank">Download</a>
                    <?php else: ?>
                        No File
                    <?php endif; ?>
                </td>
                <td>

                    <form method="post" action="" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="log_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" onclick="return confirm('Are you sure you want to delete this log entry?');">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php
$conn->close(); // *** ย้ายมาไว้ตรงนี้ ***
include('../includes/footer.php');
?>