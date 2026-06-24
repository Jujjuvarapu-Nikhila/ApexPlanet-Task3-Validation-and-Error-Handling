<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

require_once 'db.php';

$errors  = [];
$success = "";
$old     = ['name' => '', 'email' => '', 'phone' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── Sanitize inputs ───────────────────────────────────────────────────────
    $old['name']    = trim(htmlspecialchars($_POST['name']    ?? '', ENT_QUOTES));
    $old['email']   = trim($_POST['email']   ?? '');
    $old['phone']   = trim(htmlspecialchars($_POST['phone']   ?? '', ENT_QUOTES));
    $old['address'] = trim(htmlspecialchars($_POST['address'] ?? '', ENT_QUOTES));
    $password       = $_POST['password']         ?? '';
    $confirm        = $_POST['confirm_password'] ?? '';

    // ── Server-side Validation (Task 3) ──────────────────────────────────────
    if (empty($old['name'])) {
        $errors['name'] = "Full name is required.";
    } elseif (strlen($old['name']) < 2 || strlen($old['name']) > 100) {
        $errors['name'] = "Name must be between 2 and 100 characters.";
    } elseif (!preg_match('/^[\p{L}\s\'-]+$/u', $old['name'])) {
        $errors['name'] = "Name may only contain letters, spaces, hyphens, and apostrophes.";
    }

    if (empty($old['email'])) {
        $errors['email'] = "Email address is required.";
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Please enter a valid email address.";
    } elseif (strlen($old['email']) > 150) {
        $errors['email'] = "Email address is too long.";
    }

    if (!empty($old['phone']) && !preg_match('/^[0-9+\-\s]{7,15}$/', $old['phone'])) {
        $errors['phone'] = "Phone must be 7–15 digits (spaces, + and - allowed).";
    }

    if (strlen($password) < 6) {
        $errors['password'] = "Password must be at least 6 characters.";
    } elseif (strlen($password) > 72) {
        $errors['password'] = "Password must not exceed 72 characters.";
    }

    if ($password !== $confirm) {
        $errors['confirm'] = "Passwords do not match.";
    }

    // ── Database operations wrapped in try-catch (Task 3) ────────────────────
    if (empty($errors)) {
        try {
            // Check email uniqueness — SQL injection safe via prepared statement
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute([':email' => $old['email']]);

            if ($stmt->fetch()) {
                $errors['email'] = "This email is already registered. <a href='login.php'>Login instead?</a>";
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $ins = $pdo->prepare(
                    "INSERT INTO users (name, email, password, phone, address)
                     VALUES (:name, :email, :password, :phone, :address)"
                );
                $ins->execute([
                    ':name'     => $old['name'],
                    ':email'    => $old['email'],
                    ':password' => $hashed,
                    ':phone'    => $old['phone']   ?: null,
                    ':address'  => $old['address'] ?: null,
                ]);
                $success = "Account created successfully! You can now <a href='login.php'>login here</a>.";
                $old     = ['name' => '', 'email' => '', 'phone' => '', 'address' => ''];
            }
        } catch (PDOException $e) {
            error_log("Register Error: " . $e->getMessage());
            $errors['db'] = "Registration failed due to a server error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — ApexPlanet</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #e8f5e9 0%, #e0f2f1 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem;
        }
        .card {
            background: #fff; border-radius: 12px;
            box-shadow: 0 8px 32px rgba(26,92,79,0.12);
            padding: 2.5rem 2rem; width: 100%; max-width: 440px;
        }
        .logo { text-align: center; margin-bottom: 1.8rem; }
        .logo h1 { font-size: 1.7rem; color: #1a5c4f; }
        .logo h1 span { color: #3aafa9; }
        .logo p { color: #999; font-size: 0.8rem; margin-top: 2px; }
        h2 { text-align: center; font-size: 1.2rem; color: #333; margin-bottom: 1.5rem; font-weight: 600; }

        .alert { padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 1.2rem; font-size: 0.9rem; }
        .alert-error   { background: #fdecea; color: #c0392b; border-left: 4px solid #e74c3c; }
        .alert-success { background: #e8f8f5; color: #1a7a5e; border-left: 4px solid #3aafa9; }
        .alert-success a, .alert-error a { color: inherit; font-weight: 600; }

        .form-group { margin-bottom: 1.1rem; }
        label { display: block; font-size: 0.85rem; font-weight: 600; color: #444; margin-bottom: 0.35rem; }
        .opt { font-weight: 400; color: #999; }
        .input-wrap { position: relative; }
        .input-wrap .icon {
            position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%);
            color: #aaa; font-size: 1rem; pointer-events: none;
        }
        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%; padding: 0.65rem 0.9rem 0.65rem 2.3rem;
            border: 1.5px solid #ddd; border-radius: 7px; font-size: 0.95rem;
            color: #333; background: #fafafa; transition: border-color 0.2s, box-shadow 0.2s;
        }
        input:focus { outline: none; border-color: #3aafa9; box-shadow: 0 0 0 3px rgba(58,175,169,0.15); background: #fff; }
        input.is-invalid { border-color: #e74c3c !important; }
        input.is-valid   { border-color: #27ae60 !important; }
        .field-error { font-size: 0.78rem; color: #c0392b; margin-top: 4px; display: none; }
        .field-error.visible { display: block; }
        .hint { font-size: 0.75rem; color: #999; margin-top: 3px; }

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
    <h2>Create an Account</h2>

    <?php if (isset($errors['db'])): ?>
        <div class="alert alert-error">⚠️ <?= $errors['db'] ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= $success ?></div>
    <?php endif; ?>

    <form method="POST" action="register.php" id="regForm" novalidate>

        <!-- Name -->
        <div class="form-group">
            <label for="name">Full Name</label>
            <div class="input-wrap">
                <span class="icon">👤</span>
                <input type="text" id="name" name="name"
                       value="<?= htmlspecialchars($old['name']) ?>"
                       placeholder="e.g. Ravi Kumar"
                       class="<?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                       minlength="2" maxlength="100" required>
            </div>
            <span class="field-error <?= isset($errors['name']) ? 'visible' : '' ?>" id="nameErr">
                <?= $errors['name'] ?? '' ?>
            </span>
        </div>

        <!-- Email -->
        <div class="form-group">
            <label for="email">Email Address</label>
            <div class="input-wrap">
                <span class="icon">✉️</span>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($old['email']) ?>"
                       placeholder="ravi@example.com"
                       class="<?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                       maxlength="150" required>
            </div>
            <span class="field-error <?= isset($errors['email']) ? 'visible' : '' ?>" id="emailErr">
                <?= $errors['email'] ?? '' ?>
            </span>
        </div>

        <!-- Phone -->
        <div class="form-group">
            <label for="phone">Phone Number <span class="opt">(optional)</span></label>
            <div class="input-wrap">
                <span class="icon">📱</span>
                <input type="text" id="phone" name="phone"
                       value="<?= htmlspecialchars($old['phone']) ?>"
                       placeholder="+91 98765 43210"
                       class="<?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                       maxlength="15">
            </div>
            <span class="field-error <?= isset($errors['phone']) ? 'visible' : '' ?>" id="phoneErr">
                <?= $errors['phone'] ?? '' ?>
            </span>
        </div>

        <!-- Address -->
        <div class="form-group">
            <label for="address">Address <span class="opt">(optional)</span></label>
            <div class="input-wrap">
                <span class="icon">📍</span>
                <input type="text" id="address" name="address"
                       value="<?= htmlspecialchars($old['address']) ?>"
                       placeholder="City, State" maxlength="255">
            </div>
        </div>

        <!-- Password -->
        <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrap">
                <span class="icon">🔒</span>
                <input type="password" id="password" name="password"
                       placeholder="Min. 6 characters"
                       class="<?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                       minlength="6" maxlength="72" required>
            </div>
            <span class="field-error <?= isset($errors['password']) ? 'visible' : '' ?>" id="passErr">
                <?= $errors['password'] ?? '' ?>
            </span>
            <p class="hint">Use at least 6 characters.</p>
        </div>

        <!-- Confirm Password -->
        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <div class="input-wrap">
                <span class="icon">🔒</span>
                <input type="password" id="confirm_password" name="confirm_password"
                       placeholder="Repeat your password"
                       class="<?= isset($errors['confirm']) ? 'is-invalid' : '' ?>"
                       required>
            </div>
            <span class="field-error <?= isset($errors['confirm']) ? 'visible' : '' ?>" id="confirmErr">
                <?= $errors['confirm'] ?? '' ?>
            </span>
            <p class="hint" id="matchHint"></p>
        </div>

        <button type="submit">Register</button>
    </form>

    <hr class="divider">
    <p class="footer-link">Already have an account? <a href="login.php">Login</a></p>
</div>

<script>
// ── Client-Side Validation (Task 3) ──────────────────────────────────────────
const form      = document.getElementById('regForm');
const nameEl    = document.getElementById('name');
const emailEl   = document.getElementById('email');
const phoneEl   = document.getElementById('phone');
const passEl    = document.getElementById('password');
const confirmEl = document.getElementById('confirm_password');
const matchHint = document.getElementById('matchHint');

function showError(el, errId, msg) {
    el.classList.add('is-invalid');
    el.classList.remove('is-valid');
    const span = document.getElementById(errId);
    span.textContent = msg;
    span.classList.add('visible');
}
function clearError(el, errId) {
    el.classList.remove('is-invalid');
    el.classList.add('is-valid');
    const span = document.getElementById(errId);
    span.textContent = '';
    span.classList.remove('visible');
}

// Real-time: password match
confirmEl.addEventListener('input', () => {
    if (!confirmEl.value) { matchHint.textContent = ''; return; }
    if (confirmEl.value === passEl.value) {
        matchHint.textContent = '✅ Passwords match';
        matchHint.style.color = '#1a7a5e';
        clearError(confirmEl, 'confirmErr');
    } else {
        matchHint.textContent = '❌ Passwords do not match';
        matchHint.style.color = '#e74c3c';
        showError(confirmEl, 'confirmErr', 'Passwords do not match.');
    }
});

// Real-time: email format
emailEl.addEventListener('blur', () => {
    const rx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailEl.value) {
        showError(emailEl, 'emailErr', 'Email address is required.');
    } else if (!rx.test(emailEl.value)) {
        showError(emailEl, 'emailErr', 'Please enter a valid email address.');
    } else {
        clearError(emailEl, 'emailErr');
    }
});

// Real-time: phone format
phoneEl.addEventListener('blur', () => {
    const rx = /^[0-9+\-\s]{7,15}$/;
    if (phoneEl.value && !rx.test(phoneEl.value)) {
        showError(phoneEl, 'phoneErr', 'Phone must be 7–15 digits (spaces, + and - allowed).');
    } else {
        clearError(phoneEl, 'phoneErr');
    }
});

// Submit validation
form.addEventListener('submit', function (e) {
    let valid = true;

    if (nameEl.value.trim().length < 2) {
        showError(nameEl, 'nameErr', 'Full name must be at least 2 characters.');
        valid = false;
    } else { clearError(nameEl, 'nameErr'); }

    const emailRx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRx.test(emailEl.value.trim())) {
        showError(emailEl, 'emailErr', 'Please enter a valid email address.');
        valid = false;
    } else { clearError(emailEl, 'emailErr'); }

    const phoneRx = /^[0-9+\-\s]{7,15}$/;
    if (phoneEl.value && !phoneRx.test(phoneEl.value)) {
        showError(phoneEl, 'phoneErr', 'Phone must be 7–15 digits.');
        valid = false;
    } else if (phoneEl.value) { clearError(phoneEl, 'phoneErr'); }

    if (passEl.value.length < 6) {
        showError(passEl, 'passErr', 'Password must be at least 6 characters.');
        valid = false;
    } else { clearError(passEl, 'passErr'); }

    if (confirmEl.value !== passEl.value) {
        showError(confirmEl, 'confirmErr', 'Passwords do not match.');
        valid = false;
    } else { clearError(confirmEl, 'confirmErr'); }

    if (!valid) { e.preventDefault(); form.querySelector('.is-invalid').focus(); }
});
</script>
</body>
</html>
