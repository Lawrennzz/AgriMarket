<?php
include 'config.php';
require_once 'classes/AuditLog.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > 3600) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();

// Initialize the AuditLog class
$auditLogger = new AuditLog();

// Set up pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Set up filters
$table_filter = isset($_GET['table']) ? $_GET['table'] : null;
$record_filter = isset($_GET['record_id']) ? intval($_GET['record_id']) : null;

// Get audit logs based on filters
$logs = $auditLogger->getLogs($table_filter, $record_filter, $per_page, $offset);

// Get total count for pagination
$query = "SELECT COUNT(*) as total FROM audit_logs";
$conditions = [];
$params = [];
$types = "";

if ($table_filter) {
    $conditions[] = "table_name = ?";
    $params[] = $table_filter;
    $types .= "s";
}

if ($record_filter) {
    $conditions[] = "record_id = ?";
    $params[] = $record_filter;
    $types .= "i";
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$stmt = mysqli_prepare($conn, $query);

if (!empty($params)) {
    // Create a reference array for bind_param
    $bind_params = [];
    $bind_params[] = $stmt;
    $bind_params[] = $types;
    
    // Add references to each parameter
    for ($i = 0; $i < count($params); $i++) {
        $bind_params[] = &$params[$i];
    }
    
    // Call bind_param with the references array
    call_user_func_array('mysqli_stmt_bind_param', $bind_params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
$total_logs = $row['total'];

$total_pages = ceil($total_logs / $per_page);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Audit Logs - AgriMarket</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-title {
            font-size: 1.8rem;
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
        }

        .form-subtitle {
            color: var(--medium-gray);
        }

        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: var(--light-gray);
            border-radius: var(--border-radius);
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logs-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }

        .logs-table th, .logs-table td {
            padding: 1rem;
            border: 1px solid var(--light-gray);
            text-align: left;
        }

        .logs-table th {
            background: var(--primary-color);
            color: white;
        }

        .logs-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .page-link {
            padding: 0.5rem 1rem;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            color: var(--primary-color);
            text-decoration: none;
            transition: var(--transition);
        }

        .page-link.active {
            background: var(--primary-color);
            color: white;
        }

        .page-link:hover:not(.active) {
            background: var(--light-gray);
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--light-gray);
            margin-bottom: 1rem;
        }

        .details-json {
            max-width: 300px;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="form-header">
            <h1 class="form-title">Audit Logs</h1>
            <p class="form-subtitle">View system activity and changes</p>
        </div>

        <form method="GET" action="audit_logs.php" class="filters">
            <div class="filter-group">
                <label for="table">Table:</label>
                <select name="table" id="table" class="form-control">
                    <option value="">All Tables</option>
                    <option value="products" <?php echo $table_filter === 'products' ? 'selected' : ''; ?>>Products</option>
                    <option value="vendors" <?php echo $table_filter === 'vendors' ? 'selected' : ''; ?>>Vendors</option>
                    <option value="users" <?php echo $table_filter === 'users' ? 'selected' : ''; ?>>Users</option>
                    <option value="orders" <?php echo $table_filter === 'orders' ? 'selected' : ''; ?>>Orders</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="record_id">Record ID:</label>
                <input type="number" name="record_id" id="record_id" class="form-control" value="<?php echo $record_filter ? $record_filter : ''; ?>">
            </div>
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="audit_logs.php" class="btn btn-secondary">Clear Filters</a>
        </form>

        <?php if (!empty($logs)): ?>
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>Log ID</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Table</th>
                        <th>Record ID</th>
                        <th>Details</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['log_id']); ?></td>
                            <td>
                                <?php if ($log['user_id']): ?>
                                    <?php echo htmlspecialchars($log['username'] ?? 'Unknown'); ?> 
                                    (ID: <?php echo $log['user_id']; ?>)
                                <?php else: ?>
                                    System
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                            <td><?php echo htmlspecialchars($log['table_name']); ?></td>
                            <td><?php echo $log['record_id'] ? $log['record_id'] : 'N/A'; ?></td>
                            <td class="details-json">
                                <?php if (is_array($log['details'])): ?>
                                    <pre><?php echo htmlspecialchars(json_encode($log['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($log['details']); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(date('M d, Y H:i:s', strtotime($log['created_at']))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&table=<?php echo urlencode($table_filter ?? ''); ?>&record_id=<?php echo $record_filter ?? ''; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&table=<?php echo urlencode($table_filter ?? ''); ?>&record_id=<?php echo $record_filter ?? ''; ?>" 
                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&table=<?php echo urlencode($table_filter ?? ''); ?>&record_id=<?php echo $record_filter ?? ''; ?>" class="page-link">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h3>No audit logs found</h3>
                <p>No logs match your current filter criteria.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html> 