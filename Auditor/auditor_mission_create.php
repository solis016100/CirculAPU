<?php
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Auditor') {
        header("Location: login.php");
        exit;
    }

    require_once '../db.php';
    $conn->set_charset("utf8mb4");

    /* Mission ID */
    $r = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(missionID,2) AS UNSIGNED)),0)+1 n FROM missions");
    $missionID = 'M' . str_pad($r->fetch_assoc()['n'], 3, '0', STR_PAD_LEFT);
    $error = '';

    /* Create */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['missionName'] ?? '');
        $desc = trim($_POST['missionDescription'] ?? '');
        $req = trim($_POST['missionRequirement'] ?? '');
        $points = (int)($_POST['ecoPointsReward'] ?? 0);
        $status = $_POST['status'] ?? '';

        if (!$name || !$desc || !$req || $points < 1 || !in_array($status, ['Active','Inactive'])) {
            $error = 'Please complete all fields correctly.';
        }else {
            try {
                $conn->begin_transaction();

                $s = $conn->prepare("INSERT INTO missions
                    (missionID,missionName,missionDescription,missionRequirement,ecoPointsReward,status)
                    VALUES (?,?,?,?,?,?)");
                $s->bind_param("ssssis",$missionID,$name,$desc,$req,$points,$status);
                $s->execute();

                /* Log ID */
                $r = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(logID,4) AS UNSIGNED)),0)+1 n FROM systemlog");
                $logID = 'LOG' . str_pad($r->fetch_assoc()['n'],3,'0',STR_PAD_LEFT);
                $log = "Created new mission: $name";

                $s = $conn->prepare("INSERT INTO systemlog
                    (logID,userID,actionType,refType,refID,description)
                    VALUES (?,?,'Create','Mission',?,?)");
                $s->bind_param("ssss",$logID,$_SESSION['user_id'],$missionID,$log);
                $s->execute();

                $conn->commit();
                header("Location: auditor_mission_create.php?created=1");
                exit;
            }catch (Throwable $e) {
                $conn->rollback();
                $error = 'Unable to create mission. Please try again.';
            }
        }
    }
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Create Mission - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="auditor_style.css">
</head>

<body class="role-auditor">
    <div class="dashboard-container">
        <?php include '../sidebar.php'; ?>
        <div class="main-content">
            <?php include '../header.php'; ?>

            <main class="page-body">

                <section class="management-heading">
                    <div class="breadcrumb">
                        <a href="auditor_missions_management.php">Mission Management</a> &gt;
                        <span class="breadcrumb-current">Create New Mission</span>
                    </div>

                    <h1>Create New Mission</h1>
                    <p>Add a new sustainability mission to CirculAPU.</p>
                </section>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- Green bar showing Success Creation -->
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        Mission created successfully.
                    </div>
                <?php endif; ?>

                <form method="POST" id="missionForm" class="content-card mission-create-card">
                    <div class="mission-create-body">
                        <div class="auditor-management-edit-row">
                            <label>Mission ID</label>
                            <input value="<?= htmlspecialchars($missionID) ?>" readonly>
                        </div>

                        <div class="auditor-management-edit-row">
                            <label>Mission Name<span class="required">*</span></label>
                            <input name="missionName" placeholder="Enter Mission Name..." required>
                        </div>

                        <div class="auditor-management-edit-row">
                            <label>EcoPoints<span class="required">*</span></label>
                            <input type="number" name="ecoPointsReward" min="1" max="1000" placeholder="Enter EcoPoints..." required>
                        </div>

                        <div class="auditor-management-edit-row">
                            <label>Status<span class="required">*</span></label>
                            <select name="status" required>
                                <option value="">Select Status</option>
                                <option>Active</option>
                                <option>Inactive</option>
                            </select>
                        </div>

                        <div class="auditor-management-edit-row">
                            <label>Description<span class="required">*</span></label>
                            <textarea name="missionDescription" placeholder="Enter Mission Description..." required></textarea>
                        </div>

                        <div class="auditor-management-edit-row">
                            <label>Requirement<span class="required">*</span></label>
                            <textarea name="missionRequirement" placeholder="Enter Mission Requirement..." required></textarea>
                        </div>
                    </div>

                    <div class="auditor-create-actions">
                        <a href="auditor_missions_management.php" class="auditor-back-btn">
                            Back
                        </a>

                        <button type="reset" class="auditor-management-detail-action">
                            <img src="../assets/icons/reset.png" alt="">
                            Clear Form
                        </button>

                        <button type="submit" class="auditor-management-detail-action auditor-create-btn">
                            <img src="../assets/icons/create.png" alt="">
                            Create Mission
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>


    <!-- Confirmation Pop Out Message -->
    <div id="confirmPopOut" class="auditor-popout">
        <div class="auditor-popout-box">

            <h2>Mission Creation Confirmation</h2>
            <p>Are you sure you want to create this mission with these details?</p>

            <div class="auditor-summary">
                <div><strong>Mission ID</strong><span id="confirmID"></span></div>
                <div><strong>Mission Name</strong><span id="confirmName"></span></div>
                <div><strong>EcoPoints</strong><span id="confirmPoints"></span></div>
                <div><strong>Status</strong><span id="confirmStatus"></span></div>
                <div><strong>Description</strong><span id="confirmDescription"></span></div>
                <div><strong>Requirement</strong><span id="confirmRequirement"></span></div>
            </div>

            <div class="auditor-popout-actions">
                <button type="button" onclick="closeConfirm()">No</button>
                <button type="button" class="auditor-popout-confirm" onclick="confirmCreate()">
                    Yes, Create
                </button>
            </div>
        </div>
    </div>


    <!-- Success Creation of Mission -->
    <div id="successPopout" class="auditor-popout">
        <div class="auditor-popout-box success-box">
            <h2>Mission Created Successfully!</h2>
            <p>Do you want to create another mission?</p>
            <div class="auditor-popout-actions">
                <button type="button" onclick="location.href='auditor_mission_create.php?success=1'">
                    Yes
                </button>

                <button type="button" class="auditor-popout-confirm" onclick="location.href='auditor_missions_management.php?created=1'">
                    No
                </button>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('missionForm');
        const confirmPopout = document.getElementById('confirmPopOut');

        form.onsubmit = e => {
            e.preventDefault();

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const data = {
                confirmID: '<?= $missionID ?>',
                confirmName: form.missionName.value,
                confirmPoints: form.ecoPointsReward.value + ' EcoPoints',
                confirmStatus: form.status.value,
                confirmDescription: form.missionDescription.value,
                confirmRequirement: form.missionRequirement.value
            };

            for (let id in data)
                document.getElementById(id).textContent = data[id];

            confirmPopout.classList.add('show');
        };

        function closeConfirm() {
            confirmPopout.classList.remove('show');
        }

        function confirmCreate() {
            form.submit();
        }

        <?php if (($_GET['created'] ?? '') === '1'): ?>
            document.getElementById('successPopout').classList.add('show');
        <?php endif; ?>
    </script>
</body>
</html>