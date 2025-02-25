<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internship Logbook</title>
    <link rel="stylesheet" href="/internship_logbook/assets/css/style.css">
</head>
<body>
    <header>
        <h1>Internship Logbook</h1>
        <nav>
            <ul>
                <?php if (is_logged_in()): ?>
                    <?php if (get_user_role() == 'student'): ?>
                        <li><a href="/internship_logbook/student/index.php">Dashboard</a></li>
                        <li><a href="/internship_logbook/student/logbook.php">Logbook</a></li>
					 <?php elseif (get_user_role() == 'admin'): ?>
                         <li><a href="/internship_logbook/admin/index.php">Admin Dashboard</a></li>
                           <li><a href="/internship_logbook/admin/create_user.php">Create User</a></li>
                    <?php endif; ?>
                    <li><a href="/internship_logbook/logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="/internship_logbook/login.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    <main>