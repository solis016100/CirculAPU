<?php
session_start();

// Access Control Guard for Community Member Role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Member') {
    header("Location: ../login.php");
    exit();
}

// Get Member's information from login
$memberID = $_SESSION['user_id'];
$memberName = $_SESSION['user_name'] ?? 'Member';
$userID = $memberID;

// Connect to Database
require_once '../db.php';

if ($conn->connect_error) {
    $databaseError = "Unable to connect to the database.";
} else {
    // UTF-8 allows the database to handle different languages
    $conn->set_charset("utf8mb4");
}

/* Borrow Request
   Use borrowingStatus after the borrowing record has been created.
   Before that, fall back to transaction requestStatus. */
$borrowQuery = "
    SELECT
        a.assetName AS requestName,
        a.imageURL,
        tr.requestStatus,
        tr.requestDate,
        tr.requestMessage,
        b.borrowingStatus,
        COALESCE(b.borrowingStatus, tr.requestStatus) AS displayStatus
    FROM transactionrequests tr
    INNER JOIN assets a
        ON tr.assetID = a.assetID
    LEFT JOIN borrowings b
        ON b.transactionID = tr.transactionID
    WHERE tr.requesterID = '$userID'
    ORDER BY tr.requestDate DESC
";
$borrowResult = $conn->query($borrowQuery);

if (!$borrowResult) {
    die("Borrow Query Failed: " . $conn->error);
}

/* Asset Update */
$assetQuery = "
SELECT
    assetID,
    assetName AS requestName,
    description,
    assetCondition,
    imageURL,
    updatedDate,
    listingStatus,
    verificationStatus
FROM assets
WHERE ownerID = '$userID'
ORDER BY updatedDate DESC
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
    <link rel="stylesheet" href="member_style.css">
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
                            <option value="borrowed">Borrowed</option>
                            <option value="returned">Returned</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="overdue">Overdue</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>

                    <!-- Borrow Request -->
                    <div id="borrow" class="request-content active">
                        <?php if ($borrowResult->num_rows > 0): ?>
                            <?php while ($request = $borrowResult->fetch_assoc()): ?>
                                <?php
                                $borrowStatus = $request['displayStatus'] ?? $request['requestStatus'];
                                $borrowStatusClass = strtolower(trim($borrowStatus));
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
                                $listingStatus = $request['listingStatus'];
                                $verificationStatus = $request['verificationStatus'];

                                // Listing Status Colour
                                if ($listingStatus == 'Published') {
                                    $listingClass = 'approved';

                                } elseif ($listingStatus == 'Draft' || $listingStatus == 'Removed') {
                                    $listingClass = 'rejected';

                                } else {
                                    $listingClass = 'pending';
                                }

                                // Verification Status Colour
                                $verificationClass = strtolower($verificationStatus);
                                ?>

                                <div class="request-item"
                                     data-name="<?php echo strtolower(htmlspecialchars($request['requestName'])); ?>"
                                     data-status="<?php echo htmlspecialchars(strtolower($listingStatus . ' ' . $verificationStatus)); ?>">

                                    <div class="request-image">

                                        <?php if (!empty($request['imageURL'])): ?>

                                            <img src="../<?php echo htmlspecialchars($request['imageURL']); ?>"
                                                 alt="<?php echo htmlspecialchars($request['requestName']); ?>">

                                        <?php else: ?>

                                            <img src="../assets/icons/myrequest.png"
                                                 alt="Asset Update">

                                        <?php endif; ?>

                                    </div>


                                    <div class="request-item-info">

                                        <h3>
                                            <?php echo htmlspecialchars($request['requestName']); ?>
                                        </h3>

                                        <p>Asset Update</p>

                                        <div class="asset-status-group">

                                            <span class="request-status <?php echo $listingClass; ?>">
                                                Listing: <?php echo htmlspecialchars($listingStatus); ?>
                                            </span>

                                            <span class="request-status <?php echo $verificationClass; ?>">
                                                Verification: <?php echo htmlspecialchars($verificationStatus); ?>
                                            </span>

                                        </div>

                                    </div>


                                    <div class="request-item-date">

                                        <p>Updated on</p>

                                        <strong>
                                            <?php
                                            echo date(
                                                "d M Y, h:i A",
                                                strtotime($request['updatedDate'])
                                            );
                                            ?>
                                        </strong>

                                    </div>


                                    <div class="request-item-action">

                                        <!-- Draft Asset Can Be Modified -->
                                        <?php if ($listingStatus == 'Draft'): ?>

                                            <a href="member_edit_asset.php?id=<?php echo urlencode($request['assetID']); ?>"
                                               class="view-details-btn">
                                                Modify
                                            </a>

                                        <?php else: ?>

                                            <button type="button"
                                                    class="view-details-btn"
                                                    onclick="openAssetModal(this)"
                                                    data-name="<?php echo htmlspecialchars($request['requestName']); ?>"
                                                    data-description="<?php echo htmlspecialchars($request['description'] ?? ''); ?>"
                                                    data-condition="<?php echo htmlspecialchars($request['assetCondition'] ?? ''); ?>"
                                                    data-listing="<?php echo htmlspecialchars($listingStatus); ?>"
                                                    data-verification="<?php echo htmlspecialchars($verificationStatus); ?>"
                                                    data-date="<?php echo date("d M Y, h:i A", strtotime($request['updatedDate'])); ?>">
                                                View Details
                                            </button>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <div class="empty-request">
                                No asset update requests found.
                            </div>

                        <?php endif; ?>

                    </div>
                </div>

                <aside class="request-guide">

                    <h3>Request Guide</h3>


                    <!-- Borrow Request Guide -->
                    <div id="borrowGuide">

                        <div class="guide-item">
                            <strong>Take Care of Items</strong>
                            <p>
                                Keep borrowed items in good condition and return them on time.
                            </p>
                        </div>

                        <div class="guide-item">
                            <strong>Pending</strong>
                            <p>Your borrow request is waiting for approval.</p>
                        </div>

                        <div class="guide-item">
                            <strong>Approved</strong>
                            <p>Your borrow request has been approved.</p>
                        </div>

                        <div class="guide-item">
                            <strong>Borrowed</strong>
                            <p>The asset has been collected and the borrowing is active.</p>
                        </div>

                        <div class="guide-item">
                            <strong>Returned</strong>
                            <p>The asset has been returned and is waiting for completion.</p>
                        </div>

                        <div class="guide-item">
                            <strong>Completed</strong>
                            <p>The borrowing process has been completed successfully.</p>
                        </div>

                        <div class="guide-item">
                            <strong>Cancelled</strong>
                            <p>The borrowing request or borrowing has been cancelled.</p>
                        </div>

                        <div class="guide-item">
                            <strong>Overdue</strong>
                            <p>The expected return date has passed and the asset has not been returned.</p>
                        </div>

                        <div class="guide-item">
                            <strong>Rejected</strong>
                            <p>Your borrow request was not approved.</p>
                        </div>

                    </div>


                    <!-- Asset Update Guide -->
                    <div id="assetGuide" style="display:none;">

                        <div class="guide-item">
                            <strong>Draft</strong>
                            <p>
                                Your asset information needs changes. Click Modify,
                                update the information and resubmit the asset.
                            </p>
                        </div>

                        <div class="guide-item">
                            <strong>Pending</strong>
                            <p>
                                Your asset verification is still pending.
                            </p>
                        </div>

                        <div class="guide-item">
                            <strong>Pending Review</strong>
                            <p>
                                Your modified asset has been resubmitted and is waiting
                                to be reviewed again.
                            </p>
                        </div>

                        <div class="guide-item">
                            <strong>Published</strong>
                            <p>
                                Your asset has been approved and is ready to be shared
                                with everyone in the marketplace.
                            </p>
                        </div>

                        <div class="guide-item">
                            <strong>Rejected</strong>
                            <p>
                                Your asset did not pass the verification process.
                            </p>
                        </div>

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
                <span>Listing Status</span>
                <strong id="assetListingStatus"></strong>
            </div>

            <div class="modal-detail-row">
                <span>Verification Status</span>
                <strong id="assetVerificationStatus"></strong>
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
            <option value="borrowed">Borrowed</option>
            <option value="returned">Returned</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
            <option value="overdue">Overdue</option>
            <option value="rejected">Rejected</option>
        `;

        document.getElementById('borrowGuide').style.display = 'block';
        document.getElementById('assetGuide').style.display = 'none';
    } else {
        statusFilter.innerHTML = `
            <option value="">All Status</option>
            <option value="draft">Draft</option>
            <option value="ready for verification">Ready for Verification</option>
            <option value="pending review">Pending Review</option>
            <option value="published">Published</option>
            <option value="pending">Verification Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
        `;

        document.getElementById('borrowGuide').style.display = 'none';
        document.getElementById('assetGuide').style.display = 'block';
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
        const statusMatch = status === '' || request.dataset.status.includes(status);

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

    document.getElementById('assetListingStatus').textContent =
        button.dataset.listing || '-';

    document.getElementById('assetVerificationStatus').textContent =
        button.dataset.verification || '-';

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