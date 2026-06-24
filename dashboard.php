<?php
session_start();

// ── Session protection (Task 3) ───────────────────────────────────────────────
// Redirect unauthenticated users immediately
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Guard against session fixation — regenerate ID once per session lifetime
if (empty($_SESSION['__regen'])) {
    session_regenerate_id(true);
    $_SESSION['__regen'] = true;
}

$welcome = isset($_GET['welcome']) && $_GET['welcome'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — ApexPlanet</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f4f3; min-height: 100vh; }

        header {
            background: #1a5c4f; color: #fff; padding: 0.9rem 2rem;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        header h1 { font-size: 1.4rem; letter-spacing: -0.3px; }
        header h1 span { color: #3aafa9; }
        .nav-right { display: flex; align-items: center; gap: 1rem; }
        .user-pill {
            background: rgba(255,255,255,0.12); padding: 0.35rem 0.8rem;
            border-radius: 20px; font-size: 0.85rem; color: #d0f0ed;
        }
        .btn-nav {
            background: #3aafa9; color: #fff; border: none; padding: 0.45rem 1rem;
            border-radius: 6px; cursor: pointer; font-size: 0.9rem; text-decoration: none;
            font-weight: 600; transition: background 0.2s;
        }
        .btn-nav:hover { background: #fff; color: #1a5c4f; }
        .btn-nav.outline { background: transparent; border: 1px solid rgba(255,255,255,0.4); }
        .btn-nav.outline:hover { background: #fff; color: #1a5c4f; border-color: #fff; }

        .container { max-width: 860px; margin: 2.5rem auto; padding: 0 1rem; }

        /* Alert — consistent with Task 3 style across all pages */
        .alert {
            padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 1.5rem;
            font-size: 0.95rem; border-left: 4px solid;
            display: flex; justify-content: space-between; align-items: center;
            opacity: 1; transition: opacity 0.4s ease, transform 0.4s ease;
        }
        .alert.fade-out { opacity: 0; transform: translateY(-6px); }
        .alert-success { background: #e8f8f5; color: #1a7a5e; border-color: #3aafa9; }
        .alert-close { background: none; border: none; cursor: pointer; color: inherit; font-size: 1.1rem; opacity: 0.6; }
        .alert-close:hover { opacity: 1; }

        .welcome-card {
            background: linear-gradient(135deg, #1a5c4f, #3aafa9); color: #fff;
            border-radius: 12px; padding: 2rem 2.5rem; margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: 1.5rem;
        }
        .avatar {
            width: 64px; height: 64px; background: rgba(255,255,255,0.2);
            border-radius: 50%; display: flex; align-items: center;
            justify-content: center; font-size: 2rem; flex-shrink: 0;
        }
        .welcome-text h2 { font-size: 1.5rem; margin-bottom: 0.3rem; }
        .welcome-text p  { opacity: 0.85; font-size: 0.9rem; }

        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
        .info-card {
            background: #fff; border-radius: 10px; padding: 1.2rem 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }
        .info-card .label { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; color: #999; margin-bottom: 0.4rem; }
        .info-card .value { font-size: 1rem; color: #333; font-weight: 600; word-break: break-all; }
        .info-card .icon  { font-size: 1.5rem; margin-bottom: 0.5rem; }

        @media (max-width: 480px) {
            .welcome-card { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

<header>
    <h1>Apex<span>Planet</span></h1>
    <div class="nav-right">
        <span class="user-pill">👤 <?= htmlspecialchars($_SESSION['user_name']) ?></span>
        <a href="manage_users.php" class="btn-nav outline">Manage Users</a>
        <a href="logout.php" class="btn-nav">Logout</a>
    </div>
</header>

<div class="container">

    <?php if ($welcome): ?>
    <div class="alert alert-success" id="welcomeAlert">
        <span>✅ Welcome back, <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong>! You have logged in successfully.</span>
        <button class="alert-close" onclick="document.getElementById('welcomeAlert').remove()">✕</button>
    </div>
    <?php endif; ?>

    <div class="welcome-card">
        <div class="avatar">👤</div>
        <div class="welcome-text">
            <h2>Hello, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h2>
            <p>You are now logged in to the ApexPlanet Internship Portal.</p>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-card">
            <div class="icon">✉️</div>
            <div class="label">Email Address</div>
            <div class="value"><?= htmlspecialchars($_SESSION['user_email']) ?></div>
        </div>
        <div class="info-card">
            <div class="icon">🆔</div>
            <div class="label">User ID</div>
            <div class="value">#<?= htmlspecialchars($_SESSION['user_id']) ?></div>
        </div>
        <div class="info-card">
            <div class="icon">🔐</div>
            <div class="label">Session Status</div>
            <div class="value" style="color:#1a7a5e;">Active ✅</div>
        </div>
        <div class="info-card">
            <div class="icon">🎓</div>
            <div class="label">Internship</div>
            <div class="value">Web Dev — PHP & MySQL</div>
        </div>
    </div>

</div>

<script>
// Auto-fade welcome alert after 4 seconds — consistent with Task 3 alert style
document.querySelectorAll('.alert').forEach(function (el) {
    setTimeout(function () {
        el.classList.add('fade-out');
        setTimeout(function () { el.remove(); }, 450);
    }, 4000);
});
</script>
</body>
</html>
