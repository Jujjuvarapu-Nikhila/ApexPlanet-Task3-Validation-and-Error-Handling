<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

require_once 'db.php';

$errors  = [];
$oldEmail = '';
$loggedOut = isset($_GET['logout']) && $_GET['logout'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── Sanitize ──────────────────────────────────────────────────────────────
    $oldEmail = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // ── Server-side Validation (Task 3) ──────────────────────────────────────
    if (empty($oldEmail)) {
        $errors['email'] = "Email address is required.";
    } elseif (!filter_var($oldEmail, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Please enter a valid email address.";
    }

    if (empty($password)) {
        $errors['password'] = "Password is required.";
    }

    // ── DB query wrapped in try-catch (Task 3) ───────────────────────────────
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id, name, password FROM users WHERE email = :email");
            $stmt->execute([':email' => $oldEmail]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true); // prevent session fixation
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_email'] = $oldEmail;
                header("Location: dashboard.php?welcome=1");
                exit();
            } else {
                // Generic message — don't reveal whether email or password was wrong
                $errors['credentials'] = "Incorrect email or password. Please try again.";
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            $errors['db'] = "Login failed due to a server error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — ApexPlanet</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #e8f5e9 0%, #e0f2f1 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem;
        }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 8px 32px rgba(26,92,79,0.12); padding: 2.5rem 2rem; width: 100%; max-width: 400px; }
        .logo { text-align: center; margin-bottom: 1.8rem; }
        .logo h1 { font-size: 1.7rem; color: #1a5c4f; }
        .logo h1 span { color: #3aafa9; }
        .logo p { color: #999; font-size: 0.8rem; margin-top: 2px; }
        h2 { text-align: center; font-size: 1.2rem; color: #333; margin-bottom: 1.5rem; font-weight: 600; }

        .alert { padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 1.2rem; font-size: 0.9rem; border-left: 4px solid; }
        .alert-error   { background: #fdecea; color: #c0392b; border-color: #e74c3c; }
        .alert-success { background: #e8f8f5; color: #1a7a5e; border-color: #3aafa9; }

        .form-group { margin-bottom: 1.1rem; }
        label { display: block; font-size: 0.85rem; font-weight: 600; color: #444; margin-bottom: 0.35rem; }
        .input-wrap { position: relative; }
        .input-wrap .icon { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #aaa; pointer-events: none; }
        input[type="email"], input[type="password"] {
            width: 100%; padding: 0.65rem 0.9rem 0.65rem 2.3rem;
            border: 1.5px solid #ddd; border-radius: 7px; font-size: 0.95rem;
            color: #333; background: #fafafa; transition: border-color 0.2s, box-shadow 0.2s;
        }
        input:focus { outline: none; border-color: #3aafa9; box-shadow: 0 0 0 3px rgba(58,175,169,0.15); background: #fff; }
        input.is-invalid { border-color: #e74c3c !important; }
        .field-error { font-size: 0.78rem; color: #c0392b; margin-top: 4px; display: none; }
        .field-error.visible { display: block; }

        button[type="submit"] {
            width: 100%; padding: 0.8rem; background: #1a5c4f; color: #fff; border: none;
            border-radius: 7px; font-size: 1rem; font-weight: 600; cursor: pointer;
            margin-top: 0.4rem; transition: background 0.2s, transform 0.1s;
        }
        button[type="submit"]:hover  { background: #3aafa9; }
        button[type="submit"]:active { transform: scale(0.98); }
        .footer-link { text-align: center; margin-top: 1.2rem; font-size: 0.9rem; color: #777; }
        .footer-link a { color: #3aafa9; text-decoration: none; font-weight: 600; }
        .divider { border: none; border-top: 1px solid #eee; margin: 1.5rem 0; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <h1>Apex<span>Planet</span></h1>
        <p>Software Pvt Ltd — Internship Portal</p>
    </div>
    <h2>Login to Your Account</h2>

    <?php if ($loggedOut): ?>
        <div class="alert alert-success">✅ You have been logged out successfully.</div>
    <?php endif; ?>
    <?php if (isset($errors['credentials'])): ?>
        <div class="alert alert-error">🔐 <?= htmlspecialchars($errors['credentials']) ?></div>
    <?php endif; ?>
    <?php if (isset($errors['db'])): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($errors['db']) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php" id="loginForm" novalidate>

        <div class="form-group">
            <label for="email">Email Address</label>
            <div class="input-wrap">
                <span class="icon">✉️</span>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($oldEmail) ?>"
                       placeholder="ravi@example.com"
                       class="<?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                       required autofocus>
            </div>
            <span class="field-error <?= isset($errors['email']) ? 'visible' : '' ?>" id="emailErr">
                <?= $errors['email'] ?? '' ?>
            </span>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrap">
                <span class="icon">🔒</span>
                <input type="password" id="password" name="password"
                       placeholder="Your password"
                       class="<?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                       required>
            </div>
            <span class="field-error <?= isset($errors['password']) ? 'visible' : '' ?>" id="passErr">
                <?= $errors['password'] ?? '' ?>
            </span>
        </div>

        <button type="submit">Login</button>
    </form>

    <hr class="divider">
    <p class="footer-link">Don't have an account? <a href="register.php">Register</a></p>
</div>

<script>
// ── Client-Side Validation (Task 3) ──────────────────────────────────────────
function showError(el, errId, msg) {
    el.classList.add('is-invalid');
    const s = document.getElementById(errId);
    s.textContent = msg; s.classList.add('visible');
}
function clearError(el, errId) {
    el.classList.remove('is-invalid');
    const s = document.getElementById(errId);
    s.textContent = ''; s.classList.remove('visible');
}

const form  = document.getElementById('loginForm');
const email = document.getElementById('email');
const pass  = document.getElementById('password');

email.addEventListener('blur', () => {
    const rx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email.value)              showError(email, 'emailErr', 'Email address is required.');
    else if (!rx.test(email.value)) showError(email, 'emailErr', 'Please enter a valid email address.');
    else                            clearError(email, 'emailErr');
});

form.addEventListener('submit', function (e) {
    let valid = true;
    const rx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!rx.test(email.value.trim())) {
        showError(email, 'emailErr', 'Please enter a valid email address.');
        valid = false;
    } else { clearError(email, 'emailErr'); }

    if (!pass.value.trim()) {
        showError(pass, 'passErr', 'Password is required.');
        valid = false;
    } else { clearError(pass, 'passErr'); }

    if (!valid) { e.preventDefault(); form.querySelector('.is-invalid').focus(); }
});
</script>
</body>
</html>
