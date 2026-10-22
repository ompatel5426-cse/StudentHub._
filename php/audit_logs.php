<?php
/**
 * StudentHub — Practical 14: System Audit Logs Interface
 * Displays security and administrative traceability logs recorded in MySQL.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

start_secure_session();
require_admin();

$search = trim($_GET['search'] ?? '');
$action_filter = trim($_GET['action_filter'] ?? '');

$where = ["1=1"];
$types = "";
$vals  = [];

if ($search !== '') {
    $where[] = "(user_email LIKE ? OR action LIKE ? OR details LIKE ? OR ip_address LIKE ?)";
    $like = "%$search%";
    $types .= "ssss";
    $vals[] = $like; $vals[] = $like; $vals[] = $like; $vals[] = $like;
}

if ($action_filter !== '') {
    $where[] = "action = ?";
    $types .= "s";
    $vals[] = $action_filter;
}

$sql = "SELECT log_id, user_id, user_email, role, action, details, ip_address, created_at 
        FROM audit_logs 
        WHERE " . implode(" AND ", $where) . " 
        ORDER BY log_id DESC 
        LIMIT 50";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$vals);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Distinct actions for filter
$actions_res = $conn->query("SELECT DISTINCT action FROM audit_logs ORDER BY action");
$distinct_actions = [];
if ($actions_res) {
    while ($r = $actions_res->fetch_assoc()) $distinct_actions[] = $r['action'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs — Practical 14</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/audit_logs.css">
</head>
<body>

    <nav class="admin-nav">
        <div>
            <span class="brand-text">StudentHub</span>
            <span class="admin-tag">Audit Trail</span>
        </div>
        <div class="admin-nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="manage_students.php">Students CRUD</a>
            <a href="manage_events.php">Events CRUD</a>
            <a href="ajax_events.php">AJAX API</a>
            <a href="audit_logs.php" style="color:white; font-weight:700;">Audit Logs 📋</a>
            <a href="logout.php" class="btn-logout">Logout ⏻</a>
        </div>
    </nav>

    <div class="page-wrap">

        <div class="header-bar">
            <div>
                <h1>Administrative Audit Logs</h1>
                <p>Practical 14: Real-time traceability, administrative monitoring & action logs recorded in MySQL.</p>
            </div>
            <div>
                <a href="admin_dashboard.php" style="color:#6C63FF; text-decoration:none; font-weight:600; font-size:14px;">
                    ← Back to Dashboard
                </a>
            </div>
        </div>

        <div class="filter-panel">
            <form method="GET" action="audit_logs.php" class="filter-form">
                <div>
                    <input type="text" name="search" class="filter-input" placeholder="🔍 Search email, action, details, IP..." value="<?= e($search) ?>">
                </div>

                <div>
                    <select name="action_filter" class="filter-select">
                        <option value="">All Actions</option>
                        <?php foreach ($distinct_actions as $a): ?>
                            <option value="<?= e($a) ?>" <?= $action_filter === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <button type="submit" class="btn-filter">Search</button>
                </div>

                <div>
                    <a href="audit_logs.php" class="btn-reset">Reset</a>
                </div>
            </form>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table class="log-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">Sr.</th>
                            <th>Timestamp</th>
                            <th>User Email</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Action Details</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:35px; color:#64748b;">
                                    No audit logs found matching your query.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $sr = 0; foreach ($logs as $l): $sr++; 
                                $act = $l['action'];
                                $badge_cls = 'act-other';
                                if (strpos($act, 'LOGIN') !== false) $badge_cls = 'act-login';
                                elseif (strpos($act, 'LOGOUT') !== false) $badge_cls = 'act-logout';
                                elseif (strpos($act, 'CREATE') !== false) $badge_cls = 'act-create';
                                elseif (strpos($act, 'UPDATE') !== false) $badge_cls = 'act-update';
                                elseif (strpos($act, 'DELETE') !== false) $badge_cls = 'act-delete';
                            ?>
                                <tr>
                                    <td style="color:#64748b; font-weight:600;"><?= $sr ?></td>
                                    <td style="white-space:nowrap; font-size:12px; color:#475569;">
                                        <?= date('d M Y, h:i:s A', strtotime($l['created_at'])) ?>
                                    </td>
                                    <td><strong><?= e($l['user_email']) ?></strong></td>
                                    <td><span style="font-size:11px; text-transform:uppercase; font-weight:600; color:#64748b;"><?= e($l['role']) ?></span></td>
                                    <td>
                                        <span class="badge-act <?= $badge_cls ?>"><?= e($act) ?></span>
                                    </td>
                                    <td style="color:#1e293b;"><?= e($l['details']) ?></td>
                                    <td style="font-family:monospace; font-size:12px; color:#64748b;"><?= e($l['ip_address']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>
</html>
