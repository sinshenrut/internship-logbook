<?php
session_start();
include('../includes/db_connection.php');
include('../includes/functions.php');

if (!is_logged_in() || get_user_role() != 'admin') {
    redirect('/login.php');
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = $_POST['email'];
    $role = $_POST['role'];

    $conn->begin_transaction();

    try {
        $sql_user = "INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)";
        $stmt_user = $conn->prepare($sql_user);
        $stmt_user->bind_param("ssss", $username, $password, $email, $role);
        $stmt_user->execute();
        $user_id = $conn->insert_id;
        $stmt_user->close();

        if ($role == 'student') {
            $student_id = $_POST['student_id'];
            $advisor_id = $_POST['advisor_id']; // รับ advisor_id
            $company_id = $_POST['company_id']; // เพิ่ม company_id
            $sql_student = "INSERT INTO students (user_id, student_id, advisor_id, company_id) VALUES (?, ?, ?, ?)"; // เพิ่ม company_id
            $stmt_student = $conn->prepare($sql_student);
            $stmt_student->bind_param("iiii", $user_id, $student_id, $advisor_id, $company_id); // เพิ่ม company_id
            $stmt_student->execute();
            $stmt_student->close();
        } elseif ($role == 'advisor') {
            $sql_advisor = "INSERT INTO advisors (user_id) VALUES (?)";
            $stmt_advisor = $conn->prepare($sql_advisor);
            $stmt_advisor->bind_param("i", $user_id);
            $stmt_advisor->execute();
            $stmt_advisor->close();
        } elseif ($role == 'company') {
            $company_name = $_POST['company_name'];
            $address = $_POST['address'];
            $contact_person = $_POST['contact_person']; // เพิ่ม contact_person
            $phone = $_POST['phone']; // เพิ่ม phone
            $sql_company = "INSERT INTO companies (user_id, name, address, contact_person, phone) VALUES (?, ?, ?, ?, ?)"; // เพิ่ม contact_person, phone
            $stmt_company = $conn->prepare($sql_company);
            $stmt_company->bind_param("issss", $user_id, $company_name, $address, $contact_person, $phone); // เพิ่ม contact_person, phone
            $stmt_company->execute();
            $stmt_company->close();
        }
        $conn->commit();
        $success_message = "User created successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error creating user: " . $e->getMessage();
    }
    $conn->close();
     header("Location: /internship_logbook/admin/index.php");
    exit();
}

// ดึงข้อมูล Advisor (สำหรับ dropdown)
$sql_advisors = "SELECT u.id, u.username FROM users u JOIN advisors a ON u.id = a.user_id";
$result_advisors = $conn->query($sql_advisors);

// ดึงข้อมูล Company (สำหรับ dropdown)
$sql_companies = "SELECT id, name FROM companies"; // Select company names
$result_companies = $conn->query($sql_companies);
?>
<?php include('../includes/header.php'); ?>
<h2>Create User</h2>
<?php if (isset($success_message)): ?>
    <p style="color: green;"><?php echo $success_message; ?></p>
<?php endif; ?>
<?php if (isset($error_message)): ?>
    <p style="color: red;"><?php echo $error_message; ?></p>
<?php endif; ?>
<form method="post" action="">
    <div>
        <label for="username">Username:</label>
        <input type="text" name="username" id="username" required>
    </div>
    <div>
        <label for="password">Password:</label>
        <input type="password" name="password" id="password" required>
    </div>
    <div>
        <label for="email">Email:</label>
        <input type="email" name="email" id="email" required>
    </div>
    <div>
        <label for="role">Role:</label>
        <select name="role" id="role" required>
            <option value="student">Student</option>
            <option value="advisor">Advisor</option>
            <option value="company">Company</option>
            <option value="admin">Admin</option>
        </select>
    </div>

    <div id="student_fields" style="display: none;">
        <div>
            <label for="student_id">Student ID:</label>
            <input type="text" name="student_id" id="student_id">
        </div>
        <div>
            <label for="advisor_id">Advisor:</label>
            <select name="advisor_id" id="advisor_id">
                <option value="">Select Advisor</option>
                <?php while ($row = $result_advisors->fetch_assoc()): ?>
                    <option value="<?php echo $row['id']; ?>"><?php echo $row['username']; ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label for="company_id">Company:</label>
            <select name="company_id" id="company_id">
                <option value="">Select Company</option>
                <?php while ($row = $result_companies->fetch_assoc()): ?>
                    <option value="<?php echo $row['id']; ?>"><?php echo $row['name']; ?></option>
                <?php endwhile; $conn->close(); ?>
            </select>
        </div>
    </div>

    <div id="company_fields" style="display: none;">
        <div>
            <label for="company_name">Company Name:</label>
            <input type="text" name="company_name" id="company_name">
        </div>
        <div>
            <label for="address">Address:</label>
            <input type="text" name="address" id="address">
        </div>
        <div>
            <label for="contact_person">Contact Person:</label>
            <input type="text" name="contact_person" id="contact_person">
        </div>
        <div>
            <label for="phone">Phone:</label>
            <input type="text" name="phone" id="phone">
        </div>
    </div>

    <button type="submit">Create User</button>
</form>

<script>
    document.getElementById('role').addEventListener('change', function() {
        var studentFields = document.getElementById('student_fields');
        var companyFields = document.getElementById('company_fields');
        if (this.value == 'student') {
            studentFields.style.display = 'block';
            companyFields.style.display = 'none';
        } else if (this.value == 'company') {
            studentFields.style.display = 'none';
            companyFields.style.display = 'block';
        } else {
            studentFields.style.display = 'none';
            companyFields.style.display = 'none';
        }
    });
</script>

<?php include('../includes/footer.php'); ?>