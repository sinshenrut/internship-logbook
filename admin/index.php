<?php
session_start();
include('../includes/db_connection.php');
include('../includes/functions.php');

 if (!is_logged_in() || get_user_role() != 'admin') {
    redirect('/login.php');
}

// ลบผู้ใช้ (อย่างง่าย) *ต้องปรับปรุงเรื่องความปลอดภัย*
if (isset($_GET['delete_user'])) {
    $user_id_to_delete = $_GET['delete_user'];

    // *สำคัญ* ตรวจสอบให้แน่ใจว่าเป็นตัวเลข และเป็น admin เท่านั้นที่ลบได้
    if (is_numeric($user_id_to_delete) && get_user_role() == 'admin') {
        // ควรใช้ transaction และตรวจสอบ foreign key constraints ก่อนลบจริง
        $delete_sql = "DELETE FROM users WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $user_id_to_delete);
        $delete_stmt->execute();
        $delete_stmt->close();
    }
}

// ดึงข้อมูลผู้ใช้ทั้งหมด
$sql = "SELECT id, username, email, role, status FROM users";
$result = $conn->query($sql);

$conn->close();
 include('../includes/header.php');
 ?>

    <h2>Admin Dashboard</h2>

    <h3>User Management</h3>
    <a href="/internship_logbook/admin/create_user.php">Add New User</a> <?php //<-- ลิงก์ไปที่เราทำก่อนหน้า ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td><?php echo $row['role']; ?></td>
                    <td><?php echo $row['status']; ?></td>
                    <td>
                    <a href="/internship_logbook/admin/edit_user.php?id=<?php echo $row['id']; ?>">Edit</a> |  <?php // ยังไม่ได้สร้างหน้า edit ?>
                    <a href="?delete_user=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <hr>

    <h3>Other Management Tasks (Links to be added later)</h3>
    <ul>
        <li><a href="manage_companies.php">Manage Companies</a> (ยังไม่ได้สร้าง)</li>
        <li><a href="manage_advisors.php">Manage Advisors</a> (ยังไม่ได้สร้าง)</li>
        <li><a href="view_reports.php">View Reports</a> (ยังไม่ได้สร้าง)</li>
    </ul>
    <?php include('../includes/footer.php'); ?>