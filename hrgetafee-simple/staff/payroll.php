<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
check_role(2);

$staff = get_employee_info($conn, $_SESSION['employee_id']);
$message = '';

// Handle payroll processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payroll'])) {
    $employee_id = $_POST['employee_id'] ?? '';
    $month = $_POST['month'] ?? '';
    $year = $_POST['year'] ?? '';
    
    if ($employee_id && $month && $year) {
        $employee = get_employee_info($conn, $employee_id);
        $gross_salary = $employee['salary'];
        $deductions = $gross_salary * 0.12; // 12% deduction (simplified)
        $net_salary = $gross_salary - $deductions;
        
        $stmt = $conn->prepare("INSERT INTO payroll (employee_id, payroll_month, payroll_year, gross_salary, deductions, net_salary, status) VALUES (?, ?, ?, ?, ?, ?, 'processed')");
        $stmt->bind_param("iiiddd", $employee_id, $month, $year, $gross_salary, $deductions, $net_salary);
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">✓ Payroll processed successfully</div>';
        } else {
            $message = '<div class="alert alert-danger">Error processing payroll</div>';
        }
        $stmt->close();
    }
}

// Get employees
$employees = $conn->query("SELECT * FROM employees WHERE status = 'active' ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);

// Get payroll records
$payroll = $conn->query("SELECT p.*, e.first_name, e.last_name, e.employee_code FROM payroll p JOIN employees e ON p.employee_id = e.employee_id ORDER BY p.payroll_year DESC, p.payroll_month DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <h2>Payroll Management</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($staff['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Dashboard</a>

        <?php if ($message) echo $message; ?>

        <!-- Process Payroll Form -->
        <div class="card">
            <h3>💰 Process Payroll</h3>
            <form method="POST" style="max-width: 500px;">
                <div class="form-group">
                    <label for="employee">Employee</label>
                    <select name="employee_id" id="employee" required>
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['employee_id']; ?>">
                                <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label for="month">Month</label>
                        <input type="number" name="month" id="month" min="1" max="12" value="<?php echo date('m'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="year">Year</label>
                        <input type="number" name="year" id="year" value="<?php echo date('Y'); ?>" required>
                    </div>
                </div>

                <button type="submit" name="process_payroll" class="btn btn-primary" style="width: 100%;">Process Payroll</button>
            </form>
        </div>

        <!-- Payroll Records -->
        <div class="table-container">
            <h3>📊 Payroll Records</h3>
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Period</th>
                        <th>Gross Salary</th>
                        <th>Deductions</th>
                        <th>Net Salary</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payroll as $pay): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($pay['first_name'] . ' ' . $pay['last_name']); ?></td>
                        <td><?php echo $pay['payroll_month'] . '/' . $pay['payroll_year']; ?></td>
                        <td>₱<?php echo number_format($pay['gross_salary'], 2); ?></td>
                        <td>₱<?php echo number_format($pay['deductions'], 2); ?></td>
                        <td><strong>₱<?php echo number_format($pay['net_salary'], 2); ?></strong></td>
                        <td><span class="badge badge-success"><?php echo ucfirst($pay['status']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>