<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
check_role(1);

$admin = get_employee_info($conn, $_SESSION['employee_id']);
$message = '';
$error = '';

// Validate password strength
function validate_password($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    
    return $errors;
}

// Validate user data
function validate_user($data, $conn) {
    $errors = [];
    
    if (empty($data['username'])) {
        $errors[] = 'Username is required';
    } else if (strlen($data['username']) < 4) {
        $errors[] = 'Username must be at least 4 characters';
    } else if (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
        $errors[] = 'Username can only contain letters, numbers, and underscores';
    }
    
    if (empty($data['password'])) {
        $errors[] = 'Password is required';
    } else {
        $pwd_errors = validate_password($data['password']);
        $errors = array_merge($errors, $pwd_errors);
    }
    
    if (empty($data['role_id'])) {
        $errors[] = 'Role is required';
    }
    
    if (empty($data['employee_id'])) {
        $errors[] = 'Employee is required';
    }
    
    return $errors;
}

// Create user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $errors = validate_user($_POST, $conn);
    
    if (!empty($errors)) {
        $error = '<div class="alert alert-danger"><strong>Validation Errors:</strong><ul style="margin: 10px 0; padding-left: 20px;">';
        foreach ($errors as $err) {
            $error .= '<li>' . htmlspecialchars($err) . '</li>';
        }
        $error .= '</ul></div>';
    } else {
        $username = $conn->real_escape_string($_POST['username']);
        $password = $conn->real_escape_string($_POST['password']);
        $role_id = (int)$_POST['role_id'];
        $employee_id = (int)$_POST['employee_id'];
        
        // Check duplicate username
        $check = $conn->query("SELECT user_id FROM users WHERE username = '$username'");
        if ($check->num_rows > 0) {
            $error = '<div class="alert alert-danger"><strong>Error:</strong> Username already exists. Choose a different username.</div>';
        } else {
            $stmt = $conn->prepare("INSERT INTO users (username, password, role_id, employee_id, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->bind_param("ssii", $username, $password, $role_id, $employee_id);
            
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success"><strong>✓ Success!</strong> User created successfully.<br><small>Password: ' . htmlspecialchars($password) . ' (Share securely with user)</small></div>';
            } else {
                $error = '<div class="alert alert-danger"><strong>Error:</strong> Failed to create user. Please try again.</div>';
            }
            $stmt->close();
        }
    }
}

// Get data
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';

$where = "WHERE 1=1";
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $where .= " AND (u.username LIKE '%$search%' OR e.first_name LIKE '%$search%' OR e.last_name LIKE '%$search%')";
}
if (!empty($role_filter)) {
    $role_filter = (int)$role_filter;
    $where .= " AND u.role_id = $role_filter";
}

$users = $conn->query("SELECT u.*, e.first_name, e.last_name, e.employee_code, r.role_name FROM users u JOIN employees e ON u.employee_id = e.employee_id JOIN roles r ON u.role_id = r.role_id $where ORDER BY u.created_at DESC")->fetch_all(MYSQLI_ASSOC);

$employees = $conn->query("SELECT e.* FROM employees e WHERE e.employee_id NOT IN (SELECT employee_id FROM users) ORDER BY e.first_name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .form-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .password-strength {
            margin-top: 8px;
            padding: 8px;
            border-radius: 4px;
            font-size: 12px;
            background: #f0f0f0;
        }
        
        .password-strength.weak { background: #ffebee; color: #c62828; }
        .password-strength.medium { background: #fff3e0; color: #e65100; }
        .password-strength.strong { background: #e8f5e9; color: #2e7d32; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Manage Users</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($admin['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Dashboard</a>

        <?php if ($message) echo $message; ?>
        <?php if ($error) echo $error; ?>

        <!-- Create User Form -->
        <div class="form-section">
            <h3>➕ Create New User</h3>
            
            <form method="POST">
                <div class="form-group">
                    <label for="employee">Select Employee *</label>
                    <select name="employee_id" id="employee" required>
                        <option value="">-- Choose an employee without a user account --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['employee_id']; ?>">
                                <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?> (<?php echo $emp['employee_code']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" name="username" id="username" placeholder="e.g., john.doe" required>
                    <small style="color: #666;">4+ characters, letters/numbers/underscores only</small>
                </div>

                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" name="password" id="password" placeholder="Create strong password" required>
                    <div id="pwd-strength" class="password-strength" style="display:none;"></div>
                    <small style="color: #666;">Must: 8+ chars, 1 uppercase, 1 lowercase, 1 number</small>
                </div>

                <div class="form-group">
                    <label for="role">Role *</label>
                    <select name="role_id" id="role" required>
                        <option value="">-- Select Role --</option>
                        <option value="2">HR Staff</option>
                        <option value="3">Employee</option>
                    </select>
                </div>

                <button type="submit" name="create_user" class="btn btn-primary" style="width: 100%;">➕ Create User</button>
            </form>
        </div>

        <!-- Search & Filter -->
        <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);">
            <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <input type="text" name="search" placeholder="Search by username or name..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="role">
                    <option value="">-- All Roles --</option>
                    <option value="2" <?php echo ($role_filter == 2 ? 'selected' : ''); ?>>HR Staff</option>
                    <option value="3" <?php echo ($role_filter == 3 ? 'selected' : ''); ?>>Employee</option>
                </select>
                <button type="submit" class="btn btn-primary">🔍 Search</button>
                <?php if (!empty($search) || !empty($role_filter)): ?>
                    <a href="manage_users.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Users Table -->
        <div class="table-container">
            <h3>👤 All Users (<?php echo count($users); ?>)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                            <td>
                                <?php if ($user['status'] === 'active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $user['last_login'] ? format_datetime($user['last_login']) : '-'; ?></td>
                            <td><?php echo format_date($user['created_at']); ?></td>
                            <td>
                                <a href="user_status.php?user_id=<?php echo $user['user_id']; ?>&status=<?php echo ($user['status'] === 'active' ? 'inactive' : 'active'); ?>" class="btn <?php echo ($user['status'] === 'active' ? 'btn-danger' : 'btn-success'); ?>" style="padding: 5px 10px; font-size: 12px;">
                                    <?php echo ($user['status'] === 'active' ? '🔒 Deactivate' : '🔓 Activate'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 30px;">
                            <?php echo !empty($search) || !empty($role_filter) ? 'No users found' : 'No users yet'; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const pwd = this.value;
            const strengthEl = document.getElementById('pwd-strength');
            
            if (pwd.length === 0) {
                strengthEl.style.display = 'none';
                return;
            }
            
            let strength = 0;
            if (pwd.length >= 8) strength++;
            if (/[a-z]/.test(pwd)) strength++;
            if (/[A-Z]/.test(pwd)) strength++;
            if (/[0-9]/.test(pwd)) strength++;
            
            strengthEl.style.display = 'block';
            
            if (strength < 2) {
                strengthEl.className = 'password-strength weak';
                strengthEl.textContent = '⚠ Weak password';
            } else if (strength < 4) {
                strengthEl.className = 'password-strength medium';
                strengthEl.textContent = '⚡ Medium strength';
            } else {
                strengthEl.className = 'password-strength strong';
                strengthEl.textContent = '✓ Strong password';
            }
        });
    </script>
</body>
</html>