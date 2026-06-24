<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'db.php';

// ── Flash messages ───────────────────────────────────────────────────────────
$deleted      = isset($_GET['deleted'])      && $_GET['deleted']      === '1';
$deletedName  = isset($_GET['name'])         ? trim($_GET['name'])    : '';
$updated      = isset($_GET['updated'])      && $_GET['updated']      === '1';
$deleteError  = isset($_GET['error'])        && $_GET['error']        === 'delete_failed';

// ── Search ────────────────────────────────────────────────────────────────────
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ── Pagination ────────────────────────────────────────────────────────────────
$perPage     = 5;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset      = ($currentPage - 1) * $perPage;

// ── DB queries wrapped in try-catch (Task 3) ─────────────────────────────────
$users      = [];
$totalUsers = 0;
$dbError    = '';

try {
    if ($search !== '') {
        $like = "%{$search}%";

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE name LIKE :like1 OR email LIKE :like2");
        $countStmt->execute([':like1' => $like, ':like2' => $like]);
        $totalUsers = (int)$countStmt->fetchColumn();

        $totalPages  = max(1, (int)ceil($totalUsers / $perPage));
        if ($currentPage > $totalPages) { $currentPage = $totalPages; $offset = ($currentPage - 1) * $perPage; }

        $stmt = $pdo->prepare(
            "SELECT id, name, email, phone, address, created_at FROM users
             WHERE name LIKE :like1 OR email LIKE :like2
             ORDER BY id DESC LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':like1',  $like,    PDO::PARAM_STR);
        $stmt->bindValue(':like2',  $like,    PDO::PARAM_STR);
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    } else {
        $countStmt = $pdo->query("SELECT COUNT(*) FROM users");
        $totalUsers = (int)$countStmt->fetchColumn();

        $totalPages  = max(1, (int)ceil($totalUsers / $perPage));
        if ($currentPage > $totalPages) { $currentPage = $totalPages; $offset = ($currentPage - 1) * $perPage; }

        $stmt = $pdo->prepare(
            "SELECT id, name, email, phone, address, created_at FROM users
             ORDER BY id DESC LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    }

    $stmt->execute();
    $users = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Manage Users Error: " . $e->getMessage());
    $dbError = "Could not load users due to a server error. Please try again.";
}

$totalPages    = isset($totalPages) ? $totalPages : 1;
$currentUserId = (int)$_SESSION['user_id'];

// Helper to build pagination links that preserve the search query
function pageUrl($page, $search) {
    $q = ['page' => $page];
    if ($search !== '') { $q['search'] = $search; }
    return 'manage_users.php?' . http_build_query($q);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users — ApexPlanet</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f4f3; min-height: 100vh; }

        header {
            background: #1a5c4f;
            color: #fff;
            padding: 0.9rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        header h1 { font-size: 1.4rem; }
        header h1 span { color: #3aafa9; }
        .nav-right { display: flex; align-items: center; gap: 1rem; }
        .user-pill { background: rgba(255,255,255,0.12); padding: 0.35rem 0.8rem; border-radius: 20px; font-size: 0.85rem; color: #d0f0ed; }
        .btn-logout {
            background: #3aafa9; color: #fff; border: none; padding: 0.45rem 1rem;
            border-radius: 6px; cursor: pointer; font-size: 0.9rem; text-decoration: none;
            font-weight: 600; transition: background 0.2s;
        }
        .btn-logout:hover { background: #fff; color: #1a5c4f; }

        .container { max-width: 1100px; margin: 2rem auto; padding: 0 1rem 3rem; }

        .page-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem; flex-wrap: wrap; gap: 0.8rem; }
        .page-head h2 { color: #1a5c4f; font-size: 1.5rem; }
        .page-head p { color: #777; font-size: 0.9rem; margin-top: 0.2rem; }

        .alert { padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 1.2rem; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; opacity: 1; transition: opacity 0.4s ease, transform 0.4s ease; }
        .alert-success { background: #e8f8f5; color: #1a7a5e; border-left: 4px solid #3aafa9; }
        .alert.fade-out { opacity: 0; transform: translateY(-6px); }
        .alert-close { background: none; border: none; cursor: pointer; color: inherit; font-size: 1.1rem; line-height: 1; opacity: 0.6; }
        .alert-close:hover { opacity: 1; }

        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem; flex-wrap: wrap; gap: 0.8rem; }
        .search-form { display: flex; gap: 0.5rem; flex: 1; max-width: 360px; }
        .search-form input {
            flex: 1; padding: 0.55rem 0.9rem; border: 1.5px solid #ddd; border-radius: 7px;
            font-size: 0.9rem; background: #fff; transition: border-color 0.2s, box-shadow 0.2s;
        }
        .search-form input:focus { outline: none; border-color: #3aafa9; box-shadow: 0 0 0 3px rgba(58,175,169,0.15); }
        .search-form button {
            background: #1a5c4f; color: #fff; border: none; padding: 0 1rem; border-radius: 7px;
            cursor: pointer; font-size: 0.9rem; font-weight: 600;
        }
        .search-form button:hover { background: #3aafa9; }
        .search-clear { font-size: 0.85rem; color: #999; text-decoration: none; align-self: center; }
        .search-clear:hover { color: #c0392b; }

        .count-badge { background: #3aafa9; color: #fff; padding: 0.3rem 0.9rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600; white-space: nowrap; }

        .table-wrap {
            background: #fff; border-radius: 12px; overflow-x: auto;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        table { width: 100%; border-collapse: collapse; min-width: 760px; }
        thead th {
            background: #1a5c4f; color: #fff; text-align: left;
            padding: 0.85rem 1rem; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.4px;
        }
        tbody td { padding: 0.85rem 1rem; font-size: 0.92rem; color: #333; border-bottom: 1px solid #eee; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: #f7fbfa; }
        tbody tr.is-you { background: #fffbea; }
        tbody tr.is-you:hover { background: #fff6d6; }
        .id-badge {
            display: inline-block; background: #e8f8f5; color: #1a5c4f;
            padding: 2px 9px; border-radius: 12px; font-size: 0.8rem; font-weight: 600;
        }
        .you-badge {
            display: inline-block; background: #fff0b3; color: #8a6d00;
            padding: 1px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: 700;
            margin-left: 6px; vertical-align: middle; letter-spacing: 0.3px;
        }
        .muted { color: #999; font-style: italic; }
        .actions { display: flex; gap: 0.5rem; }
        .btn-edit, .btn-delete {
            border: none; padding: 0.4rem 0.85rem; border-radius: 6px;
            font-size: 0.82rem; font-weight: 600; cursor: pointer; text-decoration: none;
            display: inline-flex; align-items: center; gap: 4px; transition: opacity 0.15s;
        }
        .btn-edit { background: #eaf4ff; color: #1565c0; }
        .btn-edit:hover { opacity: 0.75; }
        .btn-delete { background: #fdecea; color: #c0392b; }
        .btn-delete:hover { opacity: 0.75; }

        .empty-state { text-align: center; padding: 3rem 1rem; color: #999; }
        .empty-state .icon { font-size: 2.2rem; margin-bottom: 0.6rem; }
        .empty-state .title { font-size: 1rem; color: #555; font-weight: 600; margin-bottom: 0.3rem; }
        .empty-state a { color: #3aafa9; text-decoration: none; font-weight: 600; }

        .pagination { display: flex; justify-content: center; align-items: center; gap: 0.4rem; margin-top: 1.3rem; flex-wrap: wrap; }
        .pagination a, .pagination span {
            min-width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;
            border-radius: 7px; font-size: 0.88rem; text-decoration: none; padding: 0 0.6rem;
        }
        .pagination a { background: #fff; color: #1a5c4f; border: 1px solid #ddd; transition: background 0.15s; }
        .pagination a:hover { background: #e8f8f5; }
        .pagination .active { background: #1a5c4f; color: #fff; font-weight: 700; }
        .pagination .disabled { color: #ccc; border: 1px solid #eee; }

        @media (max-width: 600px) {
            header { padding: 0.9rem 1rem; }
            .container { margin: 1.2rem auto; }
            .search-form { max-width: none; width: 100%; }
        }
    </style>
</head>
<body>

<header>
    <h1>Apex<span>Planet</span></h1>
    <div class="nav-right">
        <span class="user-pill">👤 <?= htmlspecialchars($_SESSION['user_name']) ?></span>
        <a href="dashboard.php" class="btn-logout" style="background:transparent;border:1px solid rgba(255,255,255,0.4);">Dashboard</a>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</header>

<div class="container">

    <?php if ($dbError): ?>
    <div class="alert" style="background:#fdecea;color:#c0392b;border-left:4px solid #e74c3c;" id="flashDbErr">
        <span>⚠️ <?= htmlspecialchars($dbError) ?></span>
        <button class="alert-close" onclick="document.getElementById('flashDbErr').remove()">✕</button>
    </div>
    <?php endif; ?>

    <?php if ($deleteError): ?>
    <div class="alert" style="background:#fdecea;color:#c0392b;border-left:4px solid #e74c3c;" id="flashDelErr">
        <span>⚠️ Could not delete user. Please try again.</span>
        <button class="alert-close" onclick="document.getElementById('flashDelErr').remove()">✕</button>
    </div>
    <?php endif; ?>

    <?php if ($deleted): ?>
    <div class="alert alert-success" id="flashDeleted">
        <span>✅ User<?= $deletedName ? ' "' . htmlspecialchars($deletedName) . '"' : '' ?> deleted successfully.</span>
        <button class="alert-close" onclick="document.getElementById('flashDeleted').remove()">✕</button>
    </div>
    <?php endif; ?>

    <?php if ($updated): ?>
    <div class="alert alert-success" id="flashUpdated">
        <span>✅ User updated successfully.</span>
        <button class="alert-close" onclick="document.getElementById('flashUpdated').remove()">✕</button>
    </div>
    <?php endif; ?>

    <div class="page-head">
        <div>
            <h2>Manage Users</h2>
            <p>View, search, edit, or delete registered users</p>
        </div>
    </div>

    <div class="toolbar">
        <form class="search-form" method="GET" action="manage_users.php">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or email…">
            <button type="submit">🔍 Search</button>
        </form>
        <div style="display:flex; align-items:center; gap:0.8rem;">
            <?php if ($search !== ''): ?>
                <a href="manage_users.php" class="search-clear">✕ Clear search</a>
            <?php endif; ?>
            <span class="count-badge"><?= $totalUsers ?> user<?= $totalUsers !== 1 ? 's' : '' ?><?= $search !== '' ? ' found' : '' ?></span>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="icon">🔍</div>
                            <?php if ($search !== ''): ?>
                                <div class="title">No records found for "<?= htmlspecialchars($search) ?>"</div>
                                <p><a href="manage_users.php">Clear search and view all users</a></p>
                            <?php else: ?>
                                <div class="title">No users found.</div>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($users as $u):
                        $isYou = ((int)$u['id'] === $currentUserId);
                    ?>
                    <tr class="<?= $isYou ? 'is-you' : '' ?>">
                        <td>
                            <span class="id-badge">#<?= htmlspecialchars($u['id']) ?></span>
                        </td>
                        <td>
                            <?= htmlspecialchars($u['name']) ?>
                            <?php if ($isYou): ?><span class="you-badge">YOU</span><?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= $u['phone']   ? htmlspecialchars($u['phone'])   : '<span class="muted">—</span>' ?></td>
                        <td><?= $u['address'] ? htmlspecialchars($u['address']) : '<span class="muted">—</span>' ?></td>
                        <td><?= htmlspecialchars(date('d M Y', strtotime($u['created_at']))) ?></td>
                        <td>
                            <div class="actions">
                                <a href="edit_user.php?id=<?= (int)$u['id'] ?>" class="btn-edit">✏️ Edit</a>
                                <a href="delete_user.php?id=<?= (int)$u['id'] ?>" class="btn-delete"
                                   onclick="return confirm('Delete user &quot;<?= htmlspecialchars(addslashes($u['name'])) ?>&quot;?<?= $isYou ? ' This is YOUR account — you will be logged out.' : '' ?> This cannot be undone.');">
                                   🗑️ Delete</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($currentPage > 1): ?>
            <a href="<?= pageUrl($currentPage - 1, $search) ?>">‹ Prev</a>
        <?php else: ?>
            <span class="disabled">‹ Prev</span>
        <?php endif; ?>

        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <?php if ($p === $currentPage): ?>
                <span class="active"><?= $p ?></span>
            <?php else: ?>
                <a href="<?= pageUrl($p, $search) ?>"><?= $p ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($currentPage < $totalPages): ?>
            <a href="<?= pageUrl($currentPage + 1, $search) ?>">Next ›</a>
        <?php else: ?>
            <span class="disabled">Next ›</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<script>
// Auto-fade flash messages after a few seconds
document.querySelectorAll('.alert').forEach(function (el) {
    setTimeout(function () {
        el.classList.add('fade-out');
        setTimeout(function () { el.remove(); }, 450);
    }, 4000);
});
</script>
</body>
</html>
