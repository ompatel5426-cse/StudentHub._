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
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',-apple-system,sans-serif; }
        body { background: #f4f6fa; color: #1e293b; }

        nav.admin-nav {
            background: #0f172a; color: white; padding: 16px 8%;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15); position: sticky; top: 0; z-index: 100;
        }
        .brand-text { font-size: 24px; font-weight: 700; color: #818cf8; }
        .admin-tag { background: #ef4444; color: white; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 12px; text-transform: uppercase; margin-left: 8px; }
        .admin-nav-links { display: flex; align-items: center; gap: 20px; }
        .admin-nav-links a { color: #cbd5e1; text-decoration: none; font-size: 14px; font-weight: 500; }
        .admin-nav-links a:hover { color: white; }
        .btn-logout { background: #ef4444 !important; color: white !important; padding: 7px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; }

        .page-wrap { max-width: 1350px; margin: 35px auto; padding: 0 4%; }
        .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .header-bar h1 { font-size: 26px; color: #0f172a; }
        .header-bar p { font-size: 14px; color: #64748b; }

        .filter-panel { background: white; padding: 18px 24px; border-radius: 14px; border: 1px solid #e2e8f0; margin-bottom: 25px; }
        .filter-form { display: grid; grid-template-columns: 2fr 1fr auto auto; gap: 12px; align-items: center; }
        .filter-input, .filter-select { padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; width: 100%; }
        .btn-filter { background: #2563eb; color: white; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 13px; }
        .btn-reset { background: #f1f5f9; color: #475569; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-size: 13px; border: 1px solid #cbd5e1; text-align: center; }

        .table-card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(0,0,0,0.04); overflow: hidden; }
        .table-responsive { overflow-x: auto; }
        table.log-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
        table.log-table th { background: #f8fafc; color: #475569; font-weight: 600; padding: 12px 16px; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        table.log-table td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
        table.log-table tr:hover { background: #f8fafc; }

        .badge-act { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; font-family: monospace; }
        .act-login { background: #dcfce7; color: #15803d; }
        .act-logout { background: #f1f5f9; color: #475569; }
        .act-create { background: #dbeafe; color: #1e40af; }
        .act-update { background: #fef3c7; color: #92400e; }
        .act-delete { background: #fee2e2; color: #b91c1c; }
        .act-other { background: #f3e8ff; color: #7e22ce; }
    </style>
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
