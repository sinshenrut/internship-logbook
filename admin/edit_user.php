<?php
session_start();
include('../includes/db_connection.php');
include('../includes/functions.php');

if (!is_logged_in() || get_user_role() != 'admin') {
    redirect('/login.php');
}

$message = '';
$user_data = null;

// *** 1. ดึงข้อมูล User (เมื่อโหลดหน้า) ***
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = $_GET['id'];

    $sql = "SELECT u.*, s.advisor_id, s.student_id FROM users u 
            LEFT JOIN students s ON u.id = s.user_id
            WHERE u.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user_data = $result->fetch_assoc();
    } else {
        $message = "User not found.";
    }
    $stmt->close();
}

// *** 2. ดึงข้อมูล Advisors (สำหรับ dropdown) ***
$advisors_sql = "SELECT a.id, u.username FROM advisors a JOIN users u ON a.user_id = u.id";
$advisors_result = $conn->query($advisors_sql);


// *** 3. จัดการ UPDATE (เมื่อ submit form) ***
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_POST['id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $status = $_POST['status'];
    $advisor_id = isset($_POST['advisor_id']) ? $_POST['advisor_id'] : null; // รับ advisor_id

    // Update Users Table
    $update_user_sql = "UPDATE users SET username = ?, email = ?, role = ?, status = ? WHERE id = ?";
    $update_user_stmt = $conn->prepare($update_user_sql);
    $update_user_stmt->bind_param("ssssi", $username, $email, $role, $status, $user_id);

    if ($update_user_stmt->execute()) {
        // Update Students Table (if role is student)
        if ($role == 'student') {
            $check_student_sql = "SELECT id FROM students WHERE user_id = ?";
            $check_student_stmt = $conn->prepare($check_student_sql);
            $check_student_stmt->bind_param("i", $user_id);
            $check_student_stmt->execute();
            $check_student_result = $check_student_stmt->get_result();

            if ($check_student_result->num_rows > 0) {
                // มี record ใน students อยู่แล้ว -> UPDATE
                $update_student_sql = "UPDATE students SET advisor_id = ? WHERE user_id = ?";
                $update_student_stmt = $conn->prepare($update_student_sql);

                // *** แก้ไข: ตรวจสอบ advisor_id ก่อน bind ***
                if ($advisor_id !== null) {
                    $update_student_stmt->bind_param("ii", $advisor_id, $user_id);
                } else {
                    $update_student_stmt->bind_param("is", $advisor_id, $user_id); // s เพราะ NULL
                }
                //***

                $update_student_stmt->execute();
                $update_student_stmt->close();

            } else {
                // ไม่มี record ใน students -> INSERT
                $student_id = $username; // ใช้ username เป็น student_id ชั่วคราว
                $insert_student_sql = "INSERT INTO students (user_id, student_id, advisor_id) VALUES (?, ?, ?)";
                $insert_student_stmt = $conn->prepare($insert_student_sql);

                // *** แก้ไข: ตรวจสอบ advisor_id ก่อน bind ***
                if ($advisor_id !== null) {
                    $insert_student_stmt->bind_param("isi", $user_id, $student_id, $advisor_id);
                } else {
                    $insert_student_stmt->bind_param("iss", $user_id, $student_id, $advisor_id); // s เพราะ NULL
                }
                //***
                $insert_student_stmt->execute();
                $insert_student_stmt->close();
            }
            $check_student_stmt->close();

        }
        $message = "User updated successfully.";
        header("Location: /internship_logbook/admin/index.php"); // Redirect
        exit();

    } else {
        $message = "Error updating user: " . $update_user_stmt->error;
    }

    $update_user_stmt->close();
}

$conn->close();
include('../includes/header.php');
?>

<h2>Edit User</h2>

<?php if ($message): ?>
    <p><?php echo $message; ?></p>
<?php endif; ?>

<?php if ($user_data): ?>
    <form method="post" action="">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($user_data['id']); ?>">
        <div>
            <label for="username">Username:</label>
            <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
        </div>
        <div>
            <label for="email">Email:</label>
            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user_data['email']); ?>">
        </div>
        <div>
            <label for="role">Role:</label>
            <select name="role" id="role" required>
                <option value="student" <?php if ($user_data['role'] == 'student') echo 'selected'; ?>>Student</option>
                <option value="advisor" <?php if ($user_data['role'] == 'advisor') echo 'selected'; ?>>Advisor</option>
                <option value="company" <?php if ($user_data['role'] == 'company') echo 'selected'; ?>>Company</option>
                <option value="admin" <?php if ($user_data['role'] == 'admin') echo 'selected'; ?>>Admin</option>
            </select>
        </div>

        <div id="advisor_select" <?php if ($user_data['role'] != 'student') echo 'style="display: none;"'; ?>>
            <label for="advisor_id">Advisor:</label>
            <select name="advisor_id" id="advisor_id">
                <option value="">-- Select Advisor --</option>
                <?php while ($advisor = $advisors_result->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($advisor['id']); ?>" <?php if (isset($user_data['advisor_id']) && $user_data['advisor_id'] == $advisor['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($advisor['username']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div>
            <label for="status">Status:</label>
            <select name="status" id="status" required>
                <option value="active" <?php if ($user_data['status'] == 'active') echo 'selected'; ?>>Active</option>
                <option value="inactive" <?php if ($user_data['status'] == 'inactive') echo 'selected'; ?>>Inactive</option>
            </select>
        </div>

        <button type="submit">Update User</button>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var roleSelect = document.getElementById('role');
        var advisorSelect = document.getElementById('advisor_select');

        function toggleAdvisorSelect() {
            if (roleSelect.value == 'student') {
                advisorSelect.style.display = 'block';
                advisorSelect.required = true; // Add required attribute
            } else {
                advisorSelect.style.display = 'none';
                advisorSelect.required = false; // Remove required attribute
            }
        }

        roleSelect.addEventListener('change', toggleAdvisorSelect);
        toggleAdvisorSelect(); // Call on page load
    });
    </script>
<?php else: ?>
    <p>User not found.</p>
<?php endif; ?>

<?php include('../includes/footer.php'); ?>