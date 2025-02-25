<?php
session_start();
include('../includes/db_connection.php');
include('../includes/functions.php');

if (!is_logged_in() || get_user_role() != 'admin') {
    redirect('/login.php');
}

function createUser($username, $password, $email, $role, $conn) {
    $check_sql = "SELECT id FROM users WHERE username = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $check_stmt->close();
        return "Error: Username already exists.";
    }
    $check_stmt->close();

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $username, $hashed_password, $email, $role);

    if ($stmt->execute()) {
        $new_user_id = $conn->insert_id;
        $stmt->close();

        if ($role == 'student') {
            $advisor_id = isset($_POST['advisor_id']) ? $_POST['advisor_id'] : null;

            // *** ตรวจสอบ advisor_id ***
            if ($advisor_id !== null) {
                $check_advisor_sql = "SELECT id FROM users WHERE id = ? AND role = 'advisor'";
                $check_advisor_stmt = $conn->prepare($check_advisor_sql);
                $check_advisor_stmt->bind_param("i", $advisor_id);
                $check_advisor_stmt->execute();
                $check_advisor_result = $check_advisor_stmt->get_result();
                if ($check_advisor_result->num_rows == 0) {
                    $check_advisor_stmt->close();
                    return "Error: Invalid advisor ID.";
                }
                $check_advisor_stmt->close();
            } else {
                // บังคับเลือก advisor
                return "Error: Advisor ID is required for students.";
            }

            $insert_student = "INSERT INTO students(user_id, student_id, advisor_id) VALUES (?, ?, ?)";
            $stmt_student = $conn->prepare($insert_student);
            $stmt_student->bind_param("isi", $new_user_id, $username, $advisor_id);
            if (!$stmt_student->execute()) {
                return "Error inserting into students table: " . $stmt_student->error;
            }
            $stmt_student->close();

        } elseif ($role == 'advisor') {
            $insert_advisor = "INSERT INTO advisors(user_id) VALUES (?)";
            $stmt_advisor = $conn->prepare($insert_advisor);
            $stmt_advisor->bind_param("i", $new_user_id);
            if (!$stmt_advisor->execute()) {
                return "Error inserting into advisors table: " . $stmt_advisor->error;
            }
            $stmt_advisor->close();

        } elseif ($role == 'company') {
            $insert_company = "INSERT INTO companies(user_id, name) VALUES(?, ?)";
            $stmt_company = $conn->prepare($insert_company);
            $stmt_company->bind_param("is", $new_user_id, $username);
            if (!$stmt_company->execute()) {
                return "Error inserting into companies table: " . $stmt_company->error;
            }
            $stmt_company->close();
        }

        return "User created successfully. User ID: " . $new_user_id;
    } else {
        $stmt->close();
        return "Error: " . $conn->error;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $email = $_POST['email'];
    $role = $_POST['role'];

    $result = createUser($username, $password, $email, $role, $conn);
    echo $result;
    header("refresh:3;url=/internship_logbook/admin/index.php");
    exit;
}

// ดึงข้อมูลอาจารย์ (สำหรับ dropdown list) *จากตาราง advisors*
$advisor_sql = "SELECT a.id, u.username FROM advisors a JOIN users u ON a.user_id = u.id ORDER BY u.username";
$advisor_result = $conn->query($advisor_sql);


include('../includes/header.php'); // ย้าย include header มาตรงนี้
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create User</title>
    <base href="/internship_logbook/">
    <style>
        #advisor_select {
            display: none; /* Initially hide */
        }
    </style>
</head>
<body>
    <h2>Create New User</h2>
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
            <label for="email">Email (optional):</label>
            <input type="email" name="email" id="email">
        </div>
        <div>
            <label for="role">Role:</label>
            <select name="role" id="role" required>
                <option value="">-- Select Role --</option>
                <option value="student">Student</option>
                <option value="advisor">Advisor</option>
                <option value="company">Company</option>
                <option value="admin">Admin</option>
            </select>
        </div>

        <div id="advisor_select">
            <label for="advisor_id">Advisor:</label>
            <select name="advisor_id" id="advisor_id" required>
                <?php while ($advisor = $advisor_result->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($advisor['id']); ?>">
                        <?php echo htmlspecialchars($advisor['username']); // แสดง username ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <button type="submit">Create User</button>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function() { // รอให้ DOM โหลดเสร็จ
        var roleSelect = document.getElementById('role');
        var advisorSelect = document.getElementById('advisor_select');

        function toggleAdvisorSelect() {
            if (roleSelect.value == 'student') {
                advisorSelect.style.display = 'block';
                advisorSelect.required = true;
            } else {
                advisorSelect.style.display = 'none';
                advisorSelect.required = false;
            }
        }

        // Trigger on change
        roleSelect.addEventListener('change', toggleAdvisorSelect);

        // Initial state
        toggleAdvisorSelect();
    });
    </script>
</body>
</html>

<?php
$conn->close(); // *** ย้าย $conn->close() มาไว้ท้ายสุด ***
include('../includes/footer.php'); // หลังจาก conn close
?>