<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}
require_once '../db.php';
$total_users  = $conn->query("SELECT COUNT(*) as count FROM User")->fetch_assoc()['count'] ?? 0;
$total_active = $conn->query("SELECT COUNT(*) as count FROM User WHERE status = 'Active'")->fetch_assoc()['count'] ?? 0;
$user_result  = $conn->query("SELECT userID, name, email, role, status, ecopoint FROM User ORDER BY userID ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="role-admin">

    <div class="dashboard-container">
        <?php include '../sidebar.php'; ?>

        <div class="main-content">
            
            <?php include '../header.php'; ?>

            <main class="page-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Accounts</h3>
                        <div class="stat-value"><?php echo $total_users; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Active System Users</h3>
                        <div class="stat-value"><?php echo $total_active; ?></div>
                    </div>
                </div>

                <div class="content-card">
                    <div class="card-header">
                        <h2>User Management Directory</h2>
                    </div>

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>EcoPoints</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $user_result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['userID']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><span class="role-badge"><?php echo htmlspecialchars($row['role']); ?></span></td>
                                    <td><?php echo htmlspecialchars($row['status']); ?></td>
                                    <td><?php echo number_format($row['ecopoint']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </main>

        </div>
    </div>

</body>
</html>
<?php $conn->close(); ?>