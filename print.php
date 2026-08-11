<?php
    // Header
    function printHeader(string $title): void {
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?php echo htmlspecialchars($title); ?>
        - CirculAPU
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 40px;
            background: #ffffff;
            color: #222222;
            font-family: Arial, sans-serif;
        }

        .print-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .print-header {
            text-align: center;
            border-bottom: 2px solid #222;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .print-header h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .print-header p {
            margin: 0;
            font-size: 14px;
        }

        .print-title {
            margin-bottom: 20px;
        }

        .print-title h2 {
            margin: 0;
            font-size: 22px;
        }

        .print-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .print-table th,
        .print-table td {
            border: 1px solid #cccccc;
            padding: 12px;
            text-align: left;
            vertical-align: top;
        }

        .print-table th {
            width: 30%;
            font-weight: bold;
            background: #f5f5f5;
        }

        .print-information {
            margin-top: 20px;
            font-size: 13px;
            line-height: 1.7;
        }

        .print-footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #cccccc;
            font-size: 12px;
            text-align: center;
        }

        .print-actions {
            text-align: center;
            margin-top: 30px;
        }

        .print-button,
        .back-button {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            font-size: 14px;
            text-decoration: none;
        }

        .print-button {
            background: #222222;
            color: #ffffff;
        }

        .back-button {
            margin-left: 10px;
            background: #eeeeee;
            color: #222222;
        }

        .print-error {
            padding: 15px;
            border: 1px solid #cc0000;
            background: #ffecec;
            color: #990000;
        }

        @media print {
            body {
                padding: 0;
            }

            .print-actions {
                display: none;
            }

            .print-container {
                max-width: none;
            }

        }

    </style>
</head>

<body>
    <div class="print-container">
        <header class="print-header">
            <h1>CirculAPU</h1>

            <p>Sustainability Management System</p>
        </header>

        <div class="print-title">
            <h2><?php echo htmlspecialchars($title); ?></h2>
        </div>

    <?php
        }

        // Print Information
        function printInformation(string $userID, string $userRole): void {
    ?>

    <div class="print-information">
        <strong>Printed By:</strong>
        <?php echo htmlspecialchars($userID); ?>

        <br>

        <strong>Role:</strong>

        <?php echo htmlspecialchars($userRole); ?>

        <br>

        <strong>Printed Date:</strong>

        <?php echo date('d M Y, h:i A'); ?>
    </div>

    <?php
        }
        // Print Footer
        function printFooter(): void {
    ?>
        <div class="print-footer">
            CirculAPU - Sustainability Management System
        </div>

        <div class="print-actions">
            <button type="button" class="print-button" onclick="window.print();">
                Print
            </button>

            <button type="button" class="back-button" onclick="window.history.back();">
                Back
            </button>
        </div>
    </div>
</body>
</html>
<?php
}