<?php
session_start();
include('../includes/db_connection.php');
include('../includes/functions.php');

if (!is_logged_in() || get_user_role() != 'admin') {
    redirect('/login.php');
}

// *** Delete User (Improved) ***
if (isset($_GET['delete_user'])) {
    $user_id_to_delete = $_GET['delete_user'];

    // Check if it's a number and the user is an admin
    if (is_numeric($user_id_to_delete) && get_user_role() == 'admin') {
        $conn->begin_transaction(); // Start transaction

        try {
            // 1. Delete logbook entries (if any)
            $delete_log_sql = "DELETE FROM logbook_entries WHERE student_id IN (SELECT id FROM students WHERE user_id = ?)";
            $delete_log_stmt = $conn->prepare($delete_log_sql);
            $delete_log_stmt->bind_param("i", $user_id_to_delete);
            $delete_log_stmt->execute();
            $delete_log_stmt->close();

            // 2. Delete student data (if any)
            $delete_student_sql = "DELETE FROM students WHERE user_id = ?";
            $delete_student_stmt = $conn->prepare($delete_student_sql);
            $delete_student_stmt->bind_param("i", $user_id_to_delete);
            $delete_student_stmt->execute();
            $delete_student_stmt->close();

            // 3. Delete advisor data (if any)
            $delete_advisor_sql = "DELETE FROM advisors WHERE user_id = ?";
            $delete_advisor_stmt = $conn->prepare($delete_advisor_sql);
            $delete_advisor_stmt->bind_param("i", $user_id_to_delete);
            $delete_advisor_stmt->execute();
            $delete_advisor_stmt->close();

            // 4. Delete company data (if any)
            $delete_company_sql = "DELETE FROM companies WHERE user_id = ?";
            $delete_company_stmt = $conn->prepare($delete_company_sql);
            $delete_company_stmt->bind_param("i", $user_id_to_delete);
            $delete_company_stmt->execute();
            $delete_company_stmt->close();

            // 5. Delete user
            $delete_user_sql = "DELETE FROM users WHERE id = ?";
            $delete_user_stmt = $conn->prepare($delete_user_sql);
            $delete_user_stmt->bind_param("i", $user_id_to_delete);
            $delete_user_stmt->execute();
            $delete_user_stmt->close();

            $conn->commit(); // Commit transaction
            $success_message = "User and related data deleted successfully.";

        } catch (Exception $e) {
            $conn->rollback(); // Rollback transaction if error
            $error_message = "Error deleting user: " . $e->getMessage();
        }
    }
}

// Get all users
$sql = "SELECT id, username, email, role, status FROM users";
$result = $conn->query($sql);

$conn->close();
include('../includes/header.php');
?>

    <h2>Admin Dashboard</h2>

    <?php if (isset($success_message)): ?>
        <p style="color: green;"><?php echo $success_message; ?></p>
    <?php endif; ?>
    <?php if (isset($error_message)): ?>
        <p style="color: red;"><?php echo $error_message; ?></p>
    <?php endif; ?>

    <h3>User Management</h3>
    <a href="/internship_logbook/admin/create_user.php">Add New User</a>

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
                        <a href="/internship_logbook/admin/edit_user.php?id=<?php echo $row['id']; ?>">Edit</a> |
                        <a href="?delete_user=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure? This will also delete all related data (logbook entries, etc.).')">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <hr>

    <h3>Other Management Tasks (Links to be added later)</h3>
    <ul>
        <li><a href="/internship_logbook/admin/manage_companies.php">Manage Companies</a></li>
        <li><a href="/internship_logbook/admin/manage_advisors.php">Manage Advisors</a></li>
        <li><a href="/internship_logbook/admin/report.php">View Reports</a></li>
    </ul>

<?php include('../includes/footer.php'); ?>