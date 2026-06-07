<!-- standard samples alphabetically sorted -->
<!-- 
“Please apply persistent pagination to this page so the table stays on the same page when adding, editing, deleting,
 or navigating away, but resets on browser refresh. Preserve all existing search, filter (status and calibrator),
  pagination logic, UI styles, and table behavior. Do not remove or simplify any existing features—only extend them.” -->

<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

// Function to convert frequency number to readable string
function formatFrequency($freq) {
    switch($freq) {
        case '6': return '6 months';
        case '12': return '1 year';
        case '24': return '2 years';
        case '60': return '5 years';
        default: return $freq;
    }
}

// Function to map present status (optional, can keep original if you want)
function formatStatus($status) {
    switch($status) {
        case 'Active': return 'Good';
        case 'Due': return 'Not Good';
        case 'Overdue': return 'For Disposal';
        case 'Inactive': return 'Not In Use';
        default: return $status;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Standard Samples</title>

<link rel="stylesheet" href="assets/css/sidebar.css">
<link rel="stylesheet" href="assets/css/header.css">
<link rel="stylesheet" href="assets/css/main.css">

<style>
.card { padding:30px; background:#fff; border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,0.08); margin-bottom:30px; }
.table-container { overflow-x:auto; margin-top:20px; }
.calibration-table { width:100%; border-collapse:collapse; }
.calibration-table th, .calibration-table td { border:1px solid #ccc; padding:10px; }
.calibration-table th { background-color:#073c64; color:white; }
.calibration-table tr:nth-child(even) { background-color:#f2f2f2; }
.calibration-table tr:hover { background-color:rgba(255,204,0,0.2); }
.action-links a { margin-right:8px; color:#073c64; font-weight:bold; text-decoration:none; }
.action-links a:hover { color:#ffcc00; }
.top-controls { display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom:10px; }
.top-controls button { background-color:#073c64; color:white; border:none; padding:8px 15px; border-radius:5px; cursor:pointer; }
.top-controls button:hover { background-color:#0056b3; }
.top-controls input, .top-controls select { padding:6px 10px; border-radius:5px; border:1px solid #ccc; }
</style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
<?php include 'includes/header.php'; ?>

<div class="card">
<h2>Standard Samples</h2>

<div class="top-controls">
    <button onclick="window.location.href='add_standard_sample.php'">Add New</button>

    <div>
        <input type="text" id="searchInput" placeholder="Search...">

        <select id="statusFilter">
            <option value="">All Status</option>
            <option value="Good">Good</option>
            <option value="Not Good">Not Good</option>
            <option value="For Disposal">For Disposal</option>
            <option value="Not In Use">Not In Use</option>
        </select>

        <select id="calibratorFilter">
            <option value="">All Calibrators</option>
            <?php
            $res = $conn->query("
                SELECT DISTINCT calibrator 
                FROM standard_samples 
                WHERE calibrator IS NOT NULL AND calibrator != ''
                ORDER BY calibrator ASC
            ");
            while ($row = $res->fetch_assoc()) {
                echo "<option value='".htmlspecialchars($row['calibrator'])."'>"
                    .htmlspecialchars($row['calibrator'])."</option>";
            }
            ?>
        </select>
    </div>
</div>

<form method="POST" action="delete_multiple_standard_samples.php" id="multiDeleteForm">
<div class="table-container">
<table class="calibration-table" id="samplesTable">
<thead>
<tr>
    <th><input type="checkbox" id="selectAll"></th>
    <th>Item No.</th>
    <th>Description</th>
    <th>Equipment Code</th>
    <th>Model / Maker</th>
    <th>Serial No. / Sub Parts</th>
    <th>Location</th>
    <th>Calibration Date</th>
    <th>Next Calibration</th>
    <th>Cal Freq</th>
    <th>Calibrator</th>
    <th>Present Status</th>
    <th>Action</th>
</tr>
</thead>
<tbody>

<?php
// Fetch all items sorted alphabetically
$result = $conn->query("SELECT * FROM standard_samples ORDER BY description ASC");
$rows = $result->fetch_all(MYSQLI_ASSOC);

// Assign item numbers descending based on Excel style
$itemNo = 1;

if (count($rows) > 0) {
    foreach ($rows as $r) {
        echo "<tr>";
        echo "<td><input type='checkbox' name='selected_ids[]' value='{$r['id']}'></td>";
        echo "<td>{$itemNo}</td>";
        echo "<td>{$r['description']}</td>";
        echo "<td>{$r['equipment_code']}</td>";
        echo "<td>{$r['model_maker']}</td>";
        echo "<td>{$r['serial_no']}</td>";
        echo "<td>{$r['location']}</td>";
        echo "<td>{$r['calibration_date']}</td>";
        echo "<td>{$r['next_calibration_date']}</td>";
        echo "<td>" . formatFrequency($r['calibration_frequency']) . "</td>";
        echo "<td>{$r['calibrator']}</td>";
        echo "<td>" . formatStatus($r['present_status']) . "</td>";
        echo "<td class='action-links'>
                <a href='edit_standard_sample.php?id={$r['id']}'>Edit</a>
                <a href='delete_standard_sample.php?id={$r['id']}' onclick='return confirm(\"Are you sure?\")'>Delete</a>
              </td>";
        echo "</tr>";
        $itemNo++;
    }
} else {
    echo "<tr><td colspan='13'>No records found.</td></tr>";
}
?>

</tbody>
</table>
</div>

<button type="submit"
    onclick="return confirm('Are you sure you want to delete selected items?')"
    style="margin-top:10px;">
    Delete Selected
</button>
</form>

</div>
</div>

<script>
// SEARCH + FILTER
const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const calibratorFilter = document.getElementById('calibratorFilter');
const table = document.querySelector('#samplesTable tbody');

function filterTable() {
    const searchTerm = searchInput.value.toLowerCase();
    const statusTerm = statusFilter.value.toLowerCase();
    const calibratorTerm = calibratorFilter.value.toLowerCase();

    for (let row of table.rows) {
        let rowText = row.innerText.toLowerCase();
        let statusCell = row.cells[11].innerText.toLowerCase();
        let calibratorCell = row.cells[10].innerText.toLowerCase();

        if (
            rowText.includes(searchTerm) &&
            (statusTerm === "" || statusCell === statusTerm) &&
            (calibratorTerm === "" || calibratorCell.includes(calibratorTerm))
        ) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    }
}

searchInput.addEventListener('input', filterTable);
statusFilter.addEventListener('change', filterTable);
calibratorFilter.addEventListener('change', filterTable);

// SELECT ALL
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll("input[name='selected_ids[]']").forEach(cb => cb.checked = this.checked);
});
</script>

</body>
</html>
