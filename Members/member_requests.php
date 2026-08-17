<?php
session_start();

$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin';
$is_preview = isset($_GET['preview']);

$conn = new mysqli("localhost", "root", "", "circulapu_db");

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

if (!isset($_SESSION['user_id']) ||
    ($_SESSION['user_role'] !== 'Member' && !($is_admin && $is_preview))) {
    header("Location: ../login.php");
    exit();
}

$userID = $_SESSION['user_id'];

/* Borrow Request */
$borrowQuery = "
    SELECT a.assetName AS requestName, a.imageURL,
           tr.requestStatus, tr.requestDate, tr.requestMessage
    FROM transactionrequests tr
    INNER JOIN assets a ON tr.assetID = a.assetID
    WHERE tr.requesterID = '$userID'
    ORDER BY tr.requestDate DESC
";
$borrowResult = $conn->query($borrowQuery);

if (!$borrowResult) {
    die("Borrow Query Failed: " . $conn->error);
}

/* Asset Update */
$assetQuery = "
    SELECT a.assetID, a.assetName AS requestName, a.description,
           a.assetCondition, a.imageURL, a.updatedDate,
           vr.status AS requestStatus
    FROM verificationrecords vr
    INNER JOIN assets a ON vr.targetID = a.assetID
    WHERE vr.userID = '$userID'
    AND vr.targetType = 'Asset'
    ORDER BY a.updatedDate DESC
";
$assetResult = $conn->query($assetQuery);

if (!$assetResult) {
    die("Asset Query Failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Request - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="member_new_style.css">
</head>

<body class="role-member">
<div class="dashboard-container">
    <?php include '../sidebar.php'; ?>

    <div class="main-content">
        <?php include '../header.php'; ?>

        <main class="page-body">
            <div class="my-request-header">
                <h1>My Request</h1>
                <p>Here you can view and manage all your borrow and asset update requests.</p>
            </div>

            <div class="my-request-layout">
                <div class="request-main-panel">

                    <div class="request-tabs">
                        <button type="button" class="request-tab active" onclick="showRequest('borrow', this)">
                            Borrow Request
                            <span class="tab-count"><?php echo $borrowResult->num_rows; ?></span>
                        </button>

                        <button type="button" class="request-tab" onclick="showRequest('asset', this)">
                            Asset Update
                            <span class="tab-count"><?php echo $assetResult->num_rows; ?></span>
                        </button>
                    </div>

                    <div class="request-tools">
                        <input type="text" id="requestSearch"
                               placeholder="Search your request..."
                               onkeyup="filterRequests()">

                        <select id="statusFilter" onchange="filterRequests()">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>

                    <!-- Borrow Request -->
                    <div id="borrow" class="request-content active">
                        <?php if ($borrowResult->num_rows > 0): ?>
                            <?php while ($request = $borrowResult->fetch_assoc()): ?>
                                <?php
                                $borrowStatus = $request['requestStatus'];
                                $borrowStatusClass = strtolower($borrowStatus);
                                ?>

                                <div class="request-item"
                                     data-name="<?php echo strtolower(htmlspecialchars($request['requestName'])); ?>"
                                     data-status="<?php echo htmlspecialchars($borrowStatusClass); ?>">

                                    <div class="request-image">
                                        <?php if (!empty($request['imageURL'])): ?>
                                            <img src="../<?php echo htmlspecialchars($request['imageURL']); ?>"
                                                 alt="<?php echo htmlspecialchars($request['requestName']); ?>">
                                        <?php else: ?>
                                            <img src="../assets/icons/myrequest.png" alt="Borrow Request">
                                        <?php endif; ?>
                                    </div>

                                    <div class="request-item-info">
                                        <h3><?php echo htmlspecialchars($request['requestName']); ?></h3>
                                        <p>Borrow Request</p>
                                        <span class="request-status <?php echo $borrowStatusClass; ?>">
                                            <?php echo htmlspecialchars($borrowStatus); ?>
                                        </span>
                                    </div>

                                    <div class="request-item-date">
                                        <p>Requested on</p>
                                        <strong>
                                            <?php echo date("d M Y, h:i A", strtotime($request['requestDate'])); ?>
                                        </strong>
                                    </div>

                                    <div class="request-item-action">
                                        <button type="button"
                                                class="view-details-btn"
                                                onclick="openBorrowModal(this)"
                                                data-name="<?php echo htmlspecialchars($request['requestName']); ?>"
                                                data-status="<?php echo htmlspecialchars($borrowStatus); ?>"
                                                data-date="<?php echo date("d M Y, h:i A", strtotime($request['requestDate'])); ?>"
                                                data-message="<?php echo htmlspecialchars($request['requestMessage'] ?? ''); ?>">
                                            View Details
                                        </button>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-request">No borrow requests found.</div>
                        <?php endif; ?>
                    </div>

                    <!-- Asset Update -->
                    <div id="asset" class="request-content">
                        <?php if ($assetResult->num_rows > 0): ?>
                            <?php while ($request = $assetResult->fetch_assoc()): ?>
                                <?php
                                $assetStatus = $request['requestStatus'];

                                if (strtolower($assetStatus) === 'pending') {
                                    $assetStatus = 'Published';
                                }

                                $assetStatusClass = strtolower($assetStatus);
                                ?>

                                <div class="request-item"
                                     data-name="<?php echo strtolower(htmlspecialchars($request['requestName'])); ?>"
                                     data-status="<?php echo htmlspecialchars($assetStatusClass); ?>">

                                    <div class="request-image">
                                        <?php if (!empty($request['imageURL'])): ?>
                                            <img src="../<?php echo htmlspecialchars($request['imageURL']); ?>"
                                                 alt="<?php echo htmlspecialchars($request['requestName']); ?>">
                                        <?php else: ?>
                                            <img src="../assets/icons/myrequest.png" alt="Asset Update">
                                        <?php endif; ?>
                                    </div>

                                    <div class="request-item-info">
                                        <h3><?php echo htmlspecialchars($request['requestName']); ?></h3>
                                        <p>Asset Update</p>
                                        <span class="request-status <?php echo $assetStatusClass; ?>">
                                            <?php echo htmlspecialchars($assetStatus); ?>
                                        </span>
                                    </div>

                                    <div class="request-item-date">
                                        <p>Updated on</p>
                                        <strong>
                                            <?php echo date("d M Y, h:i A", strtotime($request['updatedDate'])); ?>
                                        </strong>
                                    </div>

                                    <div class="request-item-action">
                                        <button type="button"
                                                class="view-details-btn"
                                                onclick="openAssetModal(this)"
                                                data-name="<?php echo htmlspecialchars($request['requestName']); ?>"
                                                data-description="<?php echo htmlspecialchars($request['description'] ?? ''); ?>"
                                                data-condition="<?php echo htmlspecialchars($request['assetCondition'] ?? ''); ?>"
                                                data-status="<?php echo htmlspecialchars($assetStatus); ?>"
                                                data-date="<?php echo date("d M Y, h:i A", strtotime($request['updatedDate'])); ?>">
                                            View Details
                                        </button>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-request">No asset update requests found.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <aside class="request-guide">
                    <h3>Request Guide</h3>

                    <div class="guide-item">
                        <strong>Take Care of Items</strong>
                        <p>Keep borrowed items in good condition and return them on time.</p>
                    </div>

                    <div class="guide-item" id="guidePending">
                        <strong>Pending</strong>
                        <p>Your borrow request is waiting for approval.</p>
                    </div>

                    <div class="guide-item" id="guidePublished" style="display:none;">
                        <strong>Published</strong>
                        <p>Your asset has been published and is waiting for approval.</p>
                    </div>

                    <div class="guide-item">
                        <strong>Approved</strong>
                        <p>Your request has been approved.</p>
                    </div>

                    <div class="guide-item">
                        <strong>Rejected</strong>
                        <p>Your request was not approved.</p>
                    </div>
                </aside>
            </div>
        </main>
    </div>
</div>

<!-- Borrow Popup -->
<div id="borrowModal" class="request-modal">
    <div class="request-modal-box">
        <div class="request-modal-header">
            <div>
                <p class="modal-small-title">REQUEST INFORMATION</p>
                <h2>Borrow Request Details</h2>
            </div>

            <button type="button" class="request-modal-close" onclick="closeBorrowModal()">
                &times;
            </button>
        </div>

        <div class="request-modal-body">
            <div class="modal-detail-row">
                <span>Asset Name</span>
                <strong id="borrowAssetName"></strong>
            </div>

            <div class="modal-detail-row">
                <span>Status</span>
                <strong id="borrowStatus"></strong>
            </div>

            <div class="modal-detail-row">
                <span>Requested Date</span>
                <strong id="borrowDate"></strong>
            </div>

            <div class="modal-detail-row modal-message">
                <span>Request Message</span>
                <strong id="borrowMessage"></strong>
            </div>
        </div>
    </div>
</div>

<!-- Asset Popup -->
<div id="assetModal" class="request-modal">
    <div class="request-modal-box">
        <div class="request-modal-header">
            <div>
                <p class="modal-small-title">ASSET INFORMATION</p>
                <h2>Asset Details</h2>
            </div>

            <button type="button" class="request-modal-close" onclick="closeAssetModal()">
                &times;
            </button>
        </div>

        <div class="request-modal-body">
            <div class="modal-detail-row">
                <span>Asset Name</span>
                <strong id="assetName"></strong>
            </div>

            <div class="modal-detail-row modal-message">
                <span>Description</span>
                <strong id="assetDescription"></strong>
            </div>

            <div class="modal-detail-row">
                <span>Condition</span>
                <strong id="assetCondition"></strong>
            </div>

            <div class="modal-detail-row">
                <span>Status</span>
                <strong id="assetStatus"></strong>
            </div>

            <div class="modal-detail-row">
                <span>Updated Date</span>
                <strong id="assetDate"></strong>
            </div>
        </div>
    </div>
</div>

<script>
function showRequest(type, button) {
    const contents = document.querySelectorAll('.request-content');
    const tabs = document.querySelectorAll('.request-tab');
    const statusFilter = document.getElementById('statusFilter');

    contents.forEach(content => content.classList.remove('active'));
    tabs.forEach(tab => tab.classList.remove('active'));

    document.getElementById(type).classList.add('active');
    button.classList.add('active');

    if (type === 'borrow') {
        statusFilter.innerHTML = `
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
        `;

        document.getElementById('guidePending').style.display = 'block';
        document.getElementById('guidePublished').style.display = 'none';
    } else {
        statusFilter.innerHTML = `
            <option value="">All Status</option>
            <option value="published">Published</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
        `;

        document.getElementById('guidePending').style.display = 'none';
        document.getElementById('guidePublished').style.display = 'block';
    }

    filterRequests();
}

function filterRequests() {
    const search = document.getElementById('requestSearch').value.toLowerCase();
    const status = document.getElementById('statusFilter').value.toLowerCase();
    const activeContent = document.querySelector('.request-content.active');

    if (!activeContent) return;

    activeContent.querySelectorAll('.request-item').forEach(request => {
        const nameMatch = request.dataset.name.includes(search);
        const statusMatch = status === '' || request.dataset.status === status;

        request.style.display = nameMatch && statusMatch ? 'grid' : 'none';
    });
}

function openBorrowModal(button) {
    document.getElementById('borrowAssetName').textContent = button.dataset.name;
    document.getElementById('borrowStatus').textContent = button.dataset.status;
    document.getElementById('borrowDate').textContent = button.dataset.date;
    document.getElementById('borrowMessage').textContent =
        button.dataset.message || 'No request message';

    document.getElementById('borrowModal').classList.add('show');
}

function closeBorrowModal() {
    document.getElementById('borrowModal').classList.remove('show');
}

function openAssetModal(button) {
    document.getElementById('assetName').textContent = button.dataset.name;
    document.getElementById('assetDescription').textContent =
        button.dataset.description || '-';
    document.getElementById('assetCondition').textContent =
        button.dataset.condition || '-';
    document.getElementById('assetStatus').textContent = button.dataset.status;
    document.getElementById('assetDate').textContent = button.dataset.date;

    document.getElementById('assetModal').classList.add('show');
}

function closeAssetModal() {
    document.getElementById('assetModal').classList.remove('show');
}

window.addEventListener('click', function(event) {
    if (event.target.id === 'borrowModal') closeBorrowModal();
    if (event.target.id === 'assetModal') closeAssetModal();
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeBorrowModal();
        closeAssetModal();
    }
});
</script>

</body>
</html>