<?php
session_start();

// Access Control: Only 'Admin' role can view this page
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Database Connection
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "circulapu_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch Quick Stats
$total_users = $conn->query("SELECT COUNT(*) as count FROM User")->fetch_assoc()['count'] ?? 0;
$total_active = $conn->query("SELECT COUNT(*) as count FROM User WHERE status = 'Active'")->fetch_assoc()['count'] ?? 0;
$total_points = $conn->query("SELECT SUM(ecopoint) as total FROM User")->fetch_assoc()['total'] ?? 0;

// Fetch User Directory
$user_sql = "SELECT userID, name, email, role, status, ecopoint FROM User ORDER BY userID ASC";
$user_result = $conn->query($user_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CirculAPU</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            background-color: #f7f9fc;
            color: #333;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px 40px;
        }

        /* Stats Cards Layout */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #ffffff;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .stat-card h3 {
            font-size: 0.85rem;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #0f172a;
        }

        /* Content Sections */
        .content-card {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-header h2 {
            font-size: 1.2rem;
            color: #1e293b;
        }

        /* Data Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.9rem;
        }

        .data-table th, .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .data-table th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
        }

        .data-table tr:hover {
            background-color: #f1f5f9;
        }

        /* Status Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-active {
            background-color: #dcfce7;
            color: #166534;
        }

        .badge-suspended {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .badge-role {
            background-color: #e0f2fe;
            color: #075985;
        }

        /* Buttons */
        .action-btn {
            padding: 6px 12px;
            background-color: #e2e8f0;
            border: none;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: none;
            color: #334155;
            transition: background 0.2s;
        }

        .action-btn:hover {
            background-color: #cbd5e1;
        }
    </style>
</head>
<body>

    <!-- Include Header Component -->
    <?php include('header.php'); ?>

    <div class="dashboard-container">
        
        <!-- Key Metrics Section -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Accounts</h3>
                <div class="stat-value"><?php echo $total_users; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Users</h3>
                <div class="stat-value"><?php echo $total_active; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total EcoPoints Distributed</h3>
                <div class="stat-value"><?php echo number_format($total_points); ?> pts</div>
            </div>
        </div>

        <!-- User Management Table -->
        <div class="content-card">
            <div class="card-header">
                <h2>User Management Directory</h2>
                <button class="action-btn" onclick="alert('Export functionality ready.')">Export Directory</button>
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
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($user_result && $user_result->num_rows > 0): ?>
                        <?php while ($row = $user_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['userID']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><span class="badge badge-role"><?php echo htmlspecialchars($row['role']); ?></span></td>
                                <td>
                                    <span class="badge <?php echo $row['status'] === 'Active' ? 'badge-active' : 'badge-suspended'; ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($row['ecopoint']); ?></td>
                                <td>
                                    <button class="action-btn" onclick="alert('Editing User: <?php echo $row['userID']; ?>')">Manage</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #888;">No user records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</body>
</html>
<?php $conn->close(); ?>