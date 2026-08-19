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
    $duplicateMessage = '';

    if (isset($_POST['checkMissionName'])) {
        $name = trim($_POST['missionName'] ?? '');

        $check = $conn->prepare("SELECT missionID FROM missions WHERE LOWER(TRIM(missionName)) = LOWER(TRIM(?)) LIMIT 1");

        $check->bind_param("s", $name);
        $check->execute();

        echo $check->get_result()->num_rows > 0 ? 'exists' : 'available';
        exit;
    }

    /* Create */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['missionName'] ?? '');
        $desc = trim($_POST['missionDescription'] ?? '');
        $req = trim($_POST['missionRequirement'] ?? '');
        $points = (int)($_POST['ecoPointsReward'] ?? 0);
        $status = $_POST['status'] ?? '';

        if (!$name || !$desc || !$req || $points < 1 || !in_array($status, ['Active','Inactive'])){
            $error = 'Please complete all fields correctly.';
        }else {
            try {
                $conn->begin_transaction();

                $s = $conn->prepare("INSERT INTO missions(missionID,missionName,missionDescription,missionRequirement,ecoPointsReward,status) VALUES (?,?,?,?,?,?)");
                $s->bind_param("ssssis", $missionID, $name, $desc, $req, $points, $status);
                $s->execute();

                /* System Log */
                $r = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(logID,4) AS UNSIGNED)),0) + 1 AS n FROM systemlog");

                $logID = 'LOG' . str_pad($r->fetch_assoc()['n'], 3, '0', STR_PAD_LEFT);
                $log = "Created new mission: $name";

                $s = $conn->prepare("INSERT INTO systemlog(logID,userID,actionType,refType,refID,description)VALUES (?,?,'Create','Mission',?,?)");
                $s->bind_param("ssss", $logID, $_SESSION['user_id'], $missionID, $log);
                $s->execute();

                /* Notify Members only if Active */
                if ($status === 'Active') {
                    $members = $conn->query("SELECT userID FROM user WHERE role = 'Member'");

                    while ($member = $members->fetch_assoc()) {
                        $r = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(notificationID,2) AS UNSIGNED)),0) + 1 AS nextID FROM notification");

                        $notificationID = 'N' . str_pad($r->fetch_assoc()['nextID'], 3, '0', STR_PAD_LEFT);

                        $title = 'New Mission Available!';
                        $content = "A new mission \"$name\" is available. Complete it to earn $points EcoPoints.";

                        $notify = $conn->prepare("INSERT INTO notification(notificationID,userID,title,content,status,createdDate) VALUES (?, ?, ?, ?, 'Unread', NOW())");

                        $notify->bind_param("ssss", $notificationID, $member['userID'], $title, $content);
                        $notify->execute();
                    }
                }
                $conn->commit();
                header("Location: auditor_mission_create.php?created=1");
                exit;
            } catch (Throwable $e) {
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
    <title>Auditor Create New Mission - CirculAPU</title>
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
                            <input id="missionName" name="missionName" placeholder="Enter Mission Name..." required>
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

                        <button type="button" id="createMissionButton" class="auditor-management-detail-action auditor-create-btn">
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

    <!-- Duplicate Mission Name Pop Out -->
    <div id="duplicateMissionPopout" class="auditor-popout">
        <div class="auditor-popout-box">
            <h2>Duplicate Mission Name</h2>
            <p>
                This mission name already exists.
                Please enter a different name.
            </p>

            <div class="auditor-popout-actions">
                <button type="button" class="auditor-popout-confirm" onclick="closeDuplicateMission()">
                    OK
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
        const createMissionButton = document.getElementById('createMissionButton');
        const duplicateMissionPopout = document.getElementById('duplicateMissionPopout');

        createMissionButton.addEventListener('click', function () {
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const data = new FormData();
            data.append('checkMissionName', '1');
            data.append('missionName', form.missionName.value);

            fetch('auditor_mission_create.php', {
                method: 'POST',
                body: data
            })
            .then(response => response.text())
            .then(result => {

                if (result.trim() === 'exists') {
                    duplicateMissionPopout.classList.add('show');
                    return;
                }

                document.getElementById('confirmID').textContent = '<?= $missionID ?>';
                document.getElementById('confirmName').textContent = form.missionName.value;
                document.getElementById('confirmPoints').textContent = form.ecoPointsReward.value + ' EcoPoints';
                document.getElementById('confirmStatus').textContent = form.status.value;
                document.getElementById('confirmDescription').textContent = form.missionDescription.value;
                document.getElementById('confirmRequirement').textContent = form.missionRequirement.value;
                confirmPopout.classList.add('show');
            });
        });

        function closeConfirm() {
            confirmPopout.classList.remove('show');
        }

        function confirmCreate() {
            form.submit();
        }

        function closeDuplicateMission() {
            duplicateMissionPopout.classList.remove('show');
            document.getElementById('missionName').focus();
        }

        <?php if (($_GET['created'] ?? '') === '1'): ?>
            document.getElementById('successPopout').classList.add('show');
        <?php endif; ?>
    </script>

</body>
</html>
