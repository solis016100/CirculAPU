<?php
    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Auditor') {
        header("Location: login.php");
        exit();
    }

    require_once '../db.php';
    $conn->set_charset("utf8mb4");

    $result = $conn->query("
        SELECT missionID FROM missions
        ORDER BY CAST(SUBSTRING(missionID, 2) AS UNSIGNED) DESC
        LIMIT 1
    ");

    if ($row = $result->fetch_assoc()) {
        $next = (int)substr($row['missionID'], 1) + 1;
    }else {
        $next = 1;
    }

    $missionID = 'M' . str_pad($next, 3, '0', STR_PAD_LEFT);
    $created = false;
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['missionName']);
        $description = trim($_POST['missionDescription']);
        $requirement = trim($_POST['missionRequirement']);
        $points = (int)$_POST['ecoPointsReward'];
        $status = $_POST['status'];

        if ($name === '' || $description === '' || $requirement === '' || $points <= 0 || !in_array($status, ['Active', 'Inactive'])) {
            $error = 'Please complete all fields correctly.';
        }else {

            $stmt = $conn->prepare("
                INSERT INTO missions
                (missionID, missionName, missionDescription, missionRequirement, ecoPointsReward, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "ssssis",
                $missionID,
                $name,
                $description,
                $requirement,
                $points,
                $status
            );

            $stmt->execute();
            $stmt->close();

            header("Location: auditor_mission_create.php?created=1");
            exit();
        }
    }
    $created = isset($_GET['created']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Mission Create Dashboard - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="auditor_style.css">
</head>
<body class="role-auditor">
    <div class="dashboard-container">

        <?php include '../sidebar.php'; ?>

        <div class="main-content">
            
            <?php include '../header.php'; ?>

            <main class="page-body auditor-mission-create-page">
                <p class="breadcrumb">
                    <a href="auditor_missions_management.php">Mission Management</a>
                    >
                    Create New Mission
                </p>

                <h1>Create New Mission</h1>

                <?php if ($error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <dialog id="successDialog">
        <h2>Mission Created Successfully!</h2>
        <p>Do you want to create another mission?</p>
        <button onclick="location.href='auditor_mission_create.php'">Yes</button>
        <button onclick="location.href='auditor_missions_management.php'">No</button>
    </dialog>

    <?php if ($created): ?>
    <script>
        document.getElementById('successDialog').showModal();
    </script>
    <?php endif; ?>
</body>
</html>