<?php
include('includes/db_connection.php'); // ต้อง include ไฟล์เชื่อมต่อฐานข้อมูล

// ฟังก์ชันสำหรับสร้างผู้ใช้ใหม่ (รวมถึง admin)
function createUser($username, $password, $email, $role, $conn) {
    // 1. ตรวจสอบว่า username ซ้ำหรือไม่
    $check_sql = "SELECT id FROM users WHERE username = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        return "Error: Username already exists.";
    }
    $check_stmt->close();

    // 2. Hash รหัสผ่าน
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 3. เตรียมคำสั่ง SQL
    $sql = "INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $username, $hashed_password, $email, $role);

    // 4. Execute คำสั่ง SQL
    if ($stmt->execute()) {
        $new_user_id = $stmt->insert_id; // ID ของ user ที่เพิ่งถูกสร้าง
        $stmt->close();

        //5. (optional) ถ้าเป็น student, advisor, company ให้เพิ่มข้อมูลในตารางที่เกี่ยวข้อง
        if ($role == 'student') {
             $insert_student = "INSERT INTO students(user_id, student_id) VALUES (?, ?)"; // เพิ่ม student_id
            $stmt_student = $conn->prepare($insert_student);
            $stmt_student->bind_param("is",$new_user_id, $username); //ให้  student_id  = username ชั่วคราว
            $stmt_student->execute();
            $stmt_student->close();


        } elseif ($role == 'advisor') {
           // ทำคล้ายๆ กับ student, เพิ่มในตาราง advisors
           $insert_advisor = "INSERT INTO advisors(user_id) VALUES (?)";
           $stmt_advisor =  $conn->prepare($insert_advisor);
            $stmt_advisor ->bind_param("i", $new_user_id);
            $stmt_advisor ->execute();
            $stmt_advisor ->close();


        } elseif($role == 'company'){
           // ทำคล้ายๆ กับ student, เพิ่มในตาราง company
           $insert_company = "INSERT INTO companies(user_id, name) VALUES(?, ?)";//เพิ่ม name
           $stmt_company =  $conn->prepare($insert_company);
            $stmt_company ->bind_param("is", $new_user_id, $username);//ให้ name = username ชั่วคราว
            $stmt_company ->execute();
            $stmt_company ->close();
        }

        return "User created successfully. User ID: " . $new_user_id;
    } else {
        $stmt->close();
        return "Error: " . $conn->error;
    }

}

// ตรวจสอบว่ามีการส่ง form มาหรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $email = $_POST['email'];
    $role = $_POST['role']; // ต้องมี select box ให้เลือก role ใน form

    $result = createUser($username, $password, $email, $role, $conn);
    echo $result; // แสดงผลลัพธ์ (สำเร็จ/Error)
    header("refresh:3;url=/internship_logbook/admin/index.php");//กลับไปหน้า admin
    exit;
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create User</title>
    <base href="/internship_logbook/"> <?php // เพิ่ม base href ?>
</head>
<body>
    <h2>Create New User</h2>
     <form method="post"  action="">
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
                <option value="student">Student</option>
                <option value="advisor">Advisor</option>
                <option value="company">Company</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <button type="submit">Create User</button>
    </form>
</body>
</html>