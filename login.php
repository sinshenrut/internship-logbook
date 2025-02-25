<?php
session_start();
include('includes/db_connection.php');
include('includes/functions.php');

if (is_logged_in()) {
    if (get_user_role() == 'student') {
        redirect('/student/index.php');
    } elseif (get_user_role() == 'advisor') {
        redirect('/advisor/index.php');
    } elseif (get_user_role() == 'admin') {
        redirect('/admin/index.php');
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

    // *** เพิ่มการตรวจสอบตรงนี้ ***
    if ($result && $result->num_rows == 1) { // ตรวจสอบว่า $result ไม่เป็น false และมีข้อมูล
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = $row['role'];

            echo "Login successful!"; // Optional: แสดงข้อความ

            if ($row['role'] == 'student') {
                header("Location: /internship_logbook/student/index.php");
            } elseif ($row['role'] == 'advisor') {
                header("Location: /internship_logbook/advisor/index.php");
            } elseif ($row['role'] == 'admin') {
                header("Location: /internship_logbook/admin/index.php");
            }
            exit();
        } else {
            $error_message = "Invalid username or password.";
        }
    } else { // ถ้า $result เป็น false หรือไม่มีข้อมูล
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