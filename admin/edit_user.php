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

    $sql = "SELECT u.*, s.advisor_id, s.student_id, c.name AS company_name, 
                   c.address AS company_address, c.contact_person AS company_contact_person, 
                   c.phone AS company_phone
            FROM users u 
            LEFT JOIN students s ON u.id = s.user_id
            LEFT JOIN companies c ON u.id = c.user_id
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
    $advisor_id = isset($_POST['advisor_id']) ? $_POST['advisor_id'] : null;
    $company_name = isset($_POST['company_name']) ? $_POST['company_name'] : null;
    $company_address = isset($_POST['company_address']) ? $_POST['company_address'] : null;
    $company_contact_person = isset($_POST['company_contact_person']) ? $_POST['company_contact_person'] : null;
    $company_phone = isset($_POST['company_phone']) ? $_POST['company_phone'] : null;

    // เริ่ม transaction
    $conn->begin_transaction();

    try {
        // Update Users Table
        $update_user_sql = "UPDATE users SET username = ?, email = ?, role = ?, status = ? WHERE id = ?";
        $update_user_stmt = $conn->prepare($update_user_sql);
        $update_user_stmt->bind_param("ssssi", $username, $email, $role, $status, $user_id);
        $update_user_stmt->execute();
        $update_user_stmt->close();

        // Update/Insert Students Table (if role is student)
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
                $update_student_stmt->bind_param("ii", $advisor_id, $user_id); // advisor_id ต้องเป็น int
                $update_student_stmt->execute();
                $update_student_stmt->close();
            } else {
                // ไม่มี record ใน students -> INSERT
                $student_id = $username; // ใช้ username เป็น student_id ชั่วคราว (ควรมี field student_id จริงๆ)
                $insert_student_sql = "INSERT INTO students (user_id, student_id, advisor_id) VALUES (?, ?, ?)";
                $insert_student_stmt = $conn->prepare($insert_student_sql);
                $insert_student_stmt->bind_param("isi", $user_id, $student_id, $advisor_id);
                $insert_student_stmt->execute();
                $insert_student_stmt->close();
            }
            $check_student_stmt->close();

        } elseif ($role == 'company') {
            // Update/Insert Companies Table (if role is company)
            $check_company_sql = "SELECT id FROM companies WHERE user_id = ?";
            $check_company_stmt = $conn->prepare($check_company_sql);
            $check_company_stmt->bind_param("i", $user_id);
            $check_company_stmt->execute();
            $check_company_result = $check_company_stmt->get_result();

            if ($check_company_result->num_rows > 0) {
                // มี record ใน companies อยู่แล้ว -> UPDATE
                $update_company_sql = "UPDATE companies SET name = ?, address = ?, contact_person = ?, phone = ? WHERE user_id = ?";
                $update_company_stmt = $conn->prepare($update_company_sql);
                $update_company_stmt->bind_param("ssssi", $company_name, $company_address, $company_contact_person, $company_phone, $user_id);
                $update_company_stmt->execute();
                $update_company_stmt->close();
            } else {
                // ไม่มี record ใน companies -> INSERT
                $insert_company_sql = "INSERT INTO companies (user_id, name, address, contact_person, phone) VALUES (?, ?, ?, ?, ?)";
                $insert_company_stmt = $conn->prepare($insert_company_sql);
                $insert_company_stmt->bind_param("issss", $user_id, $company_name, $company_address, $company_contact_person, $company_phone);
                $insert_company_stmt->execute();
                $insert_company_stmt->close();
            }
            $check_company_stmt->close();
        } elseif ($role == 'advisor') {
            //ถ้ามีอยู่แล้ว
            $check_advisor_sql = "SELECT id FROM advisors WHERE user_id = ?";
            $check_advisor_stmt =  $conn->prepare($check_advisor_sql);
            $check_advisor_stmt->bind_param("i", $user_id);
            $check_advisor_stmt->execute();
            $check_advisor_result = $check_advisor_stmt->get_result();
            if ($check_advisor_result->num_rows == 0) { // ถ้าไม่มีให้ insert
                 $insert_advisor = "INSERT INTO advisors(user_id) VALUES (?)";
                $stmt_advisor = $conn->prepare($insert_advisor);
                $stmt_advisor->bind_param("i", $user_id);
                $stmt_advisor->execute();
                $stmt_advisor->close();
            }
             $check_advisor_stmt->close();
        }

        // ถ้าทุกอย่างสำเร็จ commit transaction
        $conn->commit();
        $message = "User updated successfully.";
        header("Location: /internship_logbook/admin/index.php");
        exit();

    } catch (Exception $e) {
        // ถ้ามี error เกิดขึ้น rollback transaction
        $conn->rollback();
        $message = "Error updating user: " . $e->getMessage();
    }
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

        <?php // *** Advisor Dropdown (Student) *** ?>
        <div id="advisor_fields" <?php if ($user_data['role'] != 'student') echo 'style="display: none;"'; ?>>
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

          <div id="student_fields" <?php if ($user_data['role'] != 'student') echo 'style="display: none;"'; ?>>
            <label for="student_id">Student ID:</label>
            <input type="text" name="student_id" id="student_id" value = "<?php echo htmlspecialchars($user_data['student_id'] ?? ''); ?>">
        </div>

        <?php // *** Company Fields *** ?>
         <div id="company_fields" <?php if ($user_data['role'] != 'company') echo 'style="display: none;"'; ?>>
            <label for="company_name">Company Name:</label>
            <input type="text" name="company_name" id="company_name" value="<?php echo htmlspecialchars($user_data['company_name'] ?? ''); ?>">

            <label for="company_address">Address:</label>
            <input type="text" name="company_address" id="company_address" value="<?php echo htmlspecialchars($user_data['company_address'] ?? ''); ?>">

            <label for="company_contact_person">Contact Person:</label>
            <input type="text" name="company_contact_person" id="company_contact_person" value="<?php echo htmlspecialchars($user_data['company_contact_person'] ?? ''); ?>">

            <label for="company_phone">Phone:</label>
            <input type="text" name="company_phone" id="company_phone" value="<?php echo htmlspecialchars($user_data['company_phone'] ?? ''); ?>">
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
        var advisorFields = document.getElementById('advisor_fields');
        var companyFields = document.getElementById('company_fields');
        var studentFields =  document.getElementById('student_fields');

        function toggleFields() {
            if (roleSelect.value == 'student') {
                advisorFields.style.display = 'block';
                advisorFields.querySelector('#advisor_id').required = true;
                companyFields.style.display = 'none';
                studentFields.style.display = 'block';
            } else if (roleSelect.value == 'company') {
                advisorFields.style.display = 'none';
                companyFields.style.display = 'block';
                advisorFields.querySelector('#advisor_id').required = false;
                studentFields.style.display = 'none';
            } else {
                advisorFields.style.display = 'none';
                companyFields.style.display = 'none';
                studentFields.style.display = 'none';
                advisorFields.querySelector('#advisor_id').required = false;
            }
        }

        roleSelect.addEventListener('change', toggleFields);
        toggleFields(); // Initial state
    });
    </script>
<?php else: ?>
    <p>User not found.</p>
<?php endif; ?>

<?php include('../includes/footer.php'); ?>