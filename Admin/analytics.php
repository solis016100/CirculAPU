<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
 
require_once '../db.php';
require_once '../print.php';

 
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

date_default_timezone_set('Asia/Kuala_Lumpur');

 
$allowed_timeframes = ['All Time', 'This Month', 'This Semester', 'This Year'];
$timeframe = $_GET['timeframe'] ?? 'All Time';
if (!in_array($timeframe, $allowed_timeframes, true)) {
    $timeframe = 'All Time';
}

$now = new DateTimeImmutable('now');
$start_date = null;
if ($timeframe === 'This Month') {
    $start_date = $now->modify('first day of this month')->setTime(0, 0)->format('Y-m-d H:i:s');
} elseif ($timeframe === 'This Semester') {
    $semester_month = (int)$now->format('n') <= 6 ? 1 : 7;
    $start_date = $now->setDate((int)$now->format('Y'), $semester_month, 1)->setTime(0, 0)->format('Y-m-d H:i:s');
} elseif ($timeframe === 'This Year') {
    $start_date = $now->setDate((int)$now->format('Y'), 1, 1)->setTime(0, 0)->format('Y-m-d H:i:s');
}

$date_sql = $start_date ? " >= '" . $conn->real_escape_string($start_date) . "'" : null;

 
$asset_status_query = "SELECT availabilityStatus, COUNT(*) AS count FROM assets"
    . ($date_sql ? " WHERE createdDate$date_sql" : '')
    . " GROUP BY availabilityStatus";
$asset_status_res = $conn->query($asset_status_query);
$asset_counts = [
    'Available' => 0,
    'Reserved' => 0,
    'Borrowed' => 0,
    'Unavailable' => 0
];
$total_assets = 0;

if ($asset_status_res && $asset_status_res->num_rows > 0) {
    while ($row = $asset_status_res->fetch_assoc()) {
        $status = $row['availabilityStatus'];
        $count = (int)$row['count'];
        if (array_key_exists($status, $asset_counts)) {
            $asset_counts[$status] = $count;
        }
        $total_assets += $count;
    }
}

 
$exchange_query = "SELECT c.categoryName, COUNT(b.borrowID) AS count
                  FROM borrowings b
                  JOIN assets a ON a.assetID = b.assetID
                  JOIN categories c ON a.categoryID = c.categoryID
                  WHERE b.borrowingStatus = 'Completed'"
                  . ($date_sql ? " AND COALESCE(b.actualReturnDate, b.borrowDate)$date_sql" : '')
                  . " GROUP BY c.categoryID, c.categoryName ORDER BY count DESC";
$exchange_res = $conn->query($exchange_query);
$exchanges = [];
$total_exchanges = 0;

if ($exchange_res && $exchange_res->num_rows > 0) {
    while ($row = $exchange_res->fetch_assoc()) {
        $cat = $row['categoryName'];
        $cnt = (int)$row['count'];
        $exchanges[$cat] = $cnt;
        $total_exchanges += $cnt;
    }
}
 
$log_join_condition = $date_sql ? " AND l.timeLogged$date_sql" : '';
$productivity_query = "SELECT u.name, u.role, COUNT(l.logID) AS actions
                      FROM user u
                      LEFT JOIN systemlog l ON u.userID = l.userID$log_join_condition
                      WHERE u.role IN ('Manager', 'Auditor') AND u.status = 'Active'
                      GROUP BY u.userID, u.name, u.role
                      ORDER BY actions DESC LIMIT 6";
$productivity_res = $conn->query($productivity_query);
$productivity_data = [];

if ($productivity_res && $productivity_res->num_rows > 0) {
    while ($row = $productivity_res->fetch_assoc()) {
        $productivity_data[] = [
            'name' => explode(' ', $row['name'])[0],
            'role' => $row['role'],
            'actions' => (int)$row['actions']
        ];
    }
}

 
$ecopoint_query = "SELECT DATE(transactionDate) AS activityDate, SUM(ABS(points)) AS points
                   FROM ecopointstransaction"
                   . ($date_sql ? " WHERE transactionDate$date_sql" : '')
                   . " GROUP BY DATE(transactionDate) ORDER BY activityDate DESC LIMIT 6";
$ecopoint_result = $conn->query($ecopoint_query);
$ecopoint_data = [];
while ($row = $ecopoint_result->fetch_assoc()) {
    $ecopoint_data[] = ['date' => $row['activityDate'], 'points' => (int)$row['points']];
}
$ecopoint_data = array_reverse($ecopoint_data);
$ecopoint_total_query = "SELECT COALESCE(SUM(ABS(points)), 0) AS total FROM ecopointstransaction"
    . ($date_sql ? " WHERE transactionDate$date_sql" : '');
$total_ecopoints = (int)$conn->query($ecopoint_total_query)->fetch_assoc()['total'];
$productivity_max = max(5, ...array_column($productivity_data ?: [['actions' => 0]], 'actions'));
$productivity_chart_max = (int)(ceil($productivity_max / 5) * 5);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Analytics - CirculAPU</title>
     
    <link rel="stylesheet" href="../style.css">
     
    <link rel="stylesheet" href="admin_style.css">
</head>
<body class="role-admin analytics-page">
    <div class="dashboard-container">
        
         
        <?php include '../sidebar.php'; ?>

        <div class="main-content">
            
             
            <?php include '../header.php'; ?>

            <main class="page-body">
                
                 
                <div class="admin-page-header">
                    <div>
                        <p class="breadcrumb">System Analytics</p>
                        <h1 class="header-title">System Analytics</h1>
                    </div>
                    
                    <div class="analytics-header-actions">
                        <form method="GET" action="analytics.php" class="analytics-timeframe-form">
                            <label for="timeframeSelect" class="timeframe-label">Timeframe:</label>
                            <div class="input-wrapper timeframe-wrapper">
                                <select name="timeframe" id="timeframeSelect" class="filter-select timeframe-select" onchange="this.form.submit()">
                                    <option value="All Time" <?php echo $timeframe === 'All Time' ? 'selected' : ''; ?>>All Time</option>
                                    <option value="This Month" <?php echo $timeframe === 'This Month' ? 'selected' : ''; ?>>This Month</option>
                                    <option value="This Semester" <?php echo $timeframe === 'This Semester' ? 'selected' : ''; ?>>This Semester</option>
                                    <option value="This Year" <?php echo $timeframe === 'This Year' ? 'selected' : ''; ?>>This Year</option>
                                </select>
                                <img src="../assets/icons/calendar.svg" alt="Calendar" class="timeframe-icon">
                            </div>
                        </form>
                        <?php printButton('Print Analytics', '../assets/icons/print.png', 'management-create-btn analytics-print-btn'); ?>
                    </div>
                </div>

                <section class="analytics-print-heading" aria-hidden="true">
                    <h1>CirculAPU System Analytics</h1>
                    <p>Timeframe: <?php echo htmlspecialchars($timeframe, ENT_QUOTES, 'UTF-8'); ?> · Printed <?php echo date('d M Y, h:i A'); ?></p>
                </section>

                 
                <div class="analytics-grid-container">
                    
                     
                    <div class="analytics-card ecosystem-kpi-card">
                        <h2 class="analytics-card-title">Ecosystem KPI</h2>

                         
                        <div class="kpi-section">
                            <h3 class="kpi-subtitle">Total Assets</h3>
                            <div class="donut-chart-container">
                                <canvas class="donut-canvas" width="150" height="150"
                                    data-values="<?php echo htmlspecialchars(json_encode(array_values($asset_counts)), ENT_QUOTES); ?>"
                                    aria-label="Asset availability chart"></canvas>
                                <div class="chart-legend-list">
                                    <div class="legend-item"><span class="legend-color chart-color-1"></span> Available (<?php echo $asset_counts['Available']; ?>)</div>
                                    <div class="legend-item"><span class="legend-color chart-color-2"></span> Reserved (<?php echo $asset_counts['Reserved']; ?>)</div>
                                    <div class="legend-item"><span class="legend-color chart-color-3"></span> Borrowed (<?php echo $asset_counts['Borrowed']; ?>)</div>
                                    <div class="legend-item"><span class="legend-color chart-color-4"></span> Unavailable (<?php echo $asset_counts['Unavailable']; ?>)</div>
                                </div>
                            </div>
                            <div class="kpi-total-summary">Total: <?php echo $total_assets; ?></div>
                        </div>

                         
                        <div class="kpi-section">
                            <h3 class="kpi-subtitle">Completed Exchange</h3>
                            <div class="donut-chart-container">
                                <canvas class="donut-canvas" width="150" height="150"
                                    data-values="<?php echo htmlspecialchars(json_encode(array_values($exchanges)), ENT_QUOTES); ?>"
                                    aria-label="Completed exchanges chart"></canvas>
                                <div class="chart-legend-list">
                                    <?php 
                                        $color_idx = 0;
                                        foreach ($exchanges as $cat_name => $count):
                                            $pct = round(($count / $total_exchanges) * 100, 2);
                                            $color_idx++;
                                    ?>
                                        <div class="legend-item">
                                            <span class="legend-color chart-color-<?php echo (($color_idx - 1) % 4) + 1; ?>"></span>
                                            <span class="legend-label"><?php echo htmlspecialchars($cat_name); ?></span>
                                            <span class="legend-value"><?php echo $count; ?> (<?php echo $pct; ?>%)</span>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (!$exchanges): ?>
                                        <div class="chart-empty-state">No completed exchanges in this timeframe.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="kpi-total-summary">Total: <?php echo $total_exchanges; ?></div>
                        </div>

                         
                        <div class="kpi-section trend-section">
                            <h3 class="kpi-subtitle">EcoPoint Valuation</h3>
                            <div class="line-chart-container">
                                <canvas id="ecopointChart" width="600" height="235"
                                    data-values="<?php echo htmlspecialchars(json_encode(array_column($ecopoint_data, 'points')), ENT_QUOTES); ?>"
                                    data-labels="<?php echo htmlspecialchars(json_encode(array_map(fn($item) => date('j M', strtotime($item['date'])), $ecopoint_data)), ENT_QUOTES); ?>"
                                    aria-label="EcoPoint activity line chart"></canvas>
                                <?php if (!$ecopoint_data): ?><div class="chart-empty-state">No EcoPoint activity in this timeframe.</div><?php endif; ?>
                            </div>
                        </div>

                    </div>

                     
                    <div class="analytics-card productivity-card">
                        <div class="productivity-card-header">
                            <h2 class="analytics-card-title">Role Productivity</h2>
                            <div class="productivity-legend">
                                <span class="legend-color action-color"></span> Actions
                            </div>
                        </div>

                        <div class="bar-chart-container">
                            <div class="bar-y-axis">
                                <?php for ($tick = 5; $tick >= 0; $tick--): ?>
                                    <span><?php echo (int)round($productivity_chart_max * ($tick / 5)); ?></span>
                                <?php endfor; ?>
                            </div>

                            <div class="bar-chart-plot">
                                 
                                <?php for ($line = 0; $line <= 5; $line++): ?>
                                    <div class="bar-grid-line" style="bottom: <?php echo $line * 20; ?>%;"></div>
                                <?php endfor; ?>

                                 
                                <div class="bars-flex-wrapper">
                                    <?php foreach ($productivity_data as $item): 
                                        $height_pct = min(100, ($item['actions'] / $productivity_chart_max) * 100);
                                    ?>
                                        <div class="bar-column">
                                            <span class="bar-val-label"><?php echo $item['actions']; ?></span>
                                            <div class="bar-shape" style="height: <?php echo $height_pct; ?>%;"></div>
                                            <div class="bar-x-label">
                                                <span class="person-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                                <span class="person-role">(<?php echo htmlspecialchars($item['role']); ?>)</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (!$productivity_data): ?><div class="chart-empty-state productivity-empty">No staff activity in this timeframe.</div><?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>

            </main>
        </div>
    </div>
    <script>
        (() => {
            const chartColors = ['#6b21a8', '#8b5cf6', '#c4b5fd', '#ede9fe'];

            document.querySelectorAll('.donut-canvas').forEach((canvas) => {
                const values = JSON.parse(canvas.dataset.values || '[]').map(Number);
                const total = values.reduce((sum, value) => sum + value, 0);
                const context = canvas.getContext('2d');
                const center = canvas.width / 2;
                const radius = 58;
                let startAngle = -Math.PI / 2;

                context.lineWidth = 28;
                context.lineCap = 'butt';
                if (total === 0) {
                    context.strokeStyle = '#ede9fe';
                    context.beginPath();
                    context.arc(center, center, radius, 0, Math.PI * 2);
                    context.stroke();
                    return;
                }

                values.forEach((value, index) => {
                    if (value <= 0) return;
                    const endAngle = startAngle + (value / total) * Math.PI * 2;
                    context.strokeStyle = chartColors[index % chartColors.length];
                    context.beginPath();
                    context.arc(center, center, radius, startAngle, endAngle);
                    context.stroke();
                    startAngle = endAngle;
                });
            });

            const lineCanvas = document.getElementById('ecopointChart');
            if (!lineCanvas) return;
            const values = JSON.parse(lineCanvas.dataset.values || '[]').map(Number);
            const labels = JSON.parse(lineCanvas.dataset.labels || '[]');
            const context = lineCanvas.getContext('2d');
            const width = lineCanvas.width;
            const height = lineCanvas.height;
            const padding = { top: 18, right: 20, bottom: 36, left: 48 };
            const plotWidth = width - padding.left - padding.right;
            const plotHeight = height - padding.top - padding.bottom;
            const maxValue = Math.max(1, ...values);

            context.font = '12px Arial';
            context.fillStyle = '#64748b';
            context.strokeStyle = '#e2e8f0';
            context.lineWidth = 1;
            for (let tick = 0; tick <= 4; tick++) {
                const y = padding.top + (plotHeight * tick / 4);
                const label = Math.round(maxValue * (1 - tick / 4));
                context.beginPath();
                context.moveTo(padding.left, y);
                context.lineTo(width - padding.right, y);
                context.stroke();
                context.fillText(String(label), 4, y + 4);
            }

            if (!values.length) return;
            context.strokeStyle = '#6b21a8';
            context.lineWidth = 3;
            context.beginPath();
            values.forEach((value, index) => {
                const x = values.length === 1 ? padding.left + plotWidth / 2 : padding.left + index * plotWidth / (values.length - 1);
                const y = padding.top + plotHeight - (value / maxValue * plotHeight);
                index === 0 ? context.moveTo(x, y) : context.lineTo(x, y);
            });
            context.stroke();

            context.fillStyle = '#475569';
            labels.forEach((label, index) => {
                const x = labels.length === 1 ? padding.left + plotWidth / 2 : padding.left + index * plotWidth / (labels.length - 1);
                context.fillText(label, Math.max(padding.left, x - 14), height - 10);
            });
        })();
    </script>
</body>
</html>
