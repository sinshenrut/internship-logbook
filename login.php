<?php
session_start();
include('includes/db_connection.php');
include('includes/functions.php');

if (is_logged_in()) {
    if (get_user_role() == 'student') {
        redirect('/student/index.php');
    } elseif (get_user_role() == 'advisor') {
        redirect('/advisor/index.php'); // ยังไม่ได้สร้าง
    } elseif (get_user_role() == 'admin') {
        redirect('/admin/index.php');
    }elseif (get_user_role() == 'company') { // เพิ่ม
        redirect('/company/index.php'); // เพิ่ม
    }
}

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = $row['role'];

            // echo "Login successful!"; // Optional: แสดงข้อความ

            if ($row['role'] == 'student') {
                header("Location: /internship_logbook/student/index.php");
            } elseif ($row['role'] == 'advisor') {
                 header("Location: /internship_logbook/advisor/index.php");
            } elseif ($row['role'] == 'admin') {
                header("Location: /internship_logbook/admin/index.php");
            }elseif ($row['role'] == 'company') { // เพิ่ม
                header("Location: /internship_logbook/company/index.php"); // เพิ่ม
            }
            exit();
        } else {
            $error_message = "Invalid username or password.";
        }
    } else {
        $error_message = "Invalid username or password.";
    }
    $stmt->close();
}

$conn->close();
include('includes/header.php');
?>

    <h2>Login</h2>
    <?php if ($error_message): ?>
        <p style="color: red;"><?php echo $error_message; ?></p>
    <?php endif; ?>

    <form method="post">
        <div>
            <label for="username">Username:</label>
            <input type="text" name="username" id="username" required>
        </div>
        <div>
            <label for="password">Password:</label>
            <input type="password" name="password" id="password" required>
        </div>
        <button type="submit">Login</button>
    </form>

<?php include('includes/footer.php'); ?>