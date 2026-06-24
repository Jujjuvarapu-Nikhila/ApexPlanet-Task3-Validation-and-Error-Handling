<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'db.php';

$errors = [];
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if ($id <= 0) {
    header("Location: manage_users.php");
    exit();
}

// ── Handle form submission ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim(htmlspecialchars($_POST['name']    ?? '', ENT_QUOTES));
    $email   = trim($_POST['email']   ?? '');
    $phone   = trim(htmlspecialchars($_POST['phone']   ?? '', ENT_QUOTES));
    $address = trim(htmlspecialchars($_POST['address'] ?? '', ENT_QUOTES));

    // ── Server-side Validation (Task 3) ──────────────────────────────────────
    if (empty($name) || strlen($name) < 2 || strlen($name) > 100) {
        $errors['name'] = "Name must be between 2 and 100 characters.";
    } elseif (!preg_match('/^[\p{L}\s\'-]+$/u', $name)) {
        $errors['name'] = "Name may only contain letters, spaces, hyphens, and apostrophes.";
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Please enter a valid email address.";
    } elseif (strlen($email) > 150) {
        $errors['email'] = "Email address is too long.";
    }

    if (!empty($phone) && !preg_match('/^[0-9+\-\s]{7,15}$/', $phone)) {
        $errors['phone'] = "Phone must be 7–15 digits (spaces, + and - allowed).";
    }

    // ── DB update wrapped in try-catch (Task 3) ───────────────────────────────
    if (empty($errors)) {
        try {
            // Check email not taken by another user — SQL injection safe via prepared statement
            $check = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
            $check->execute([':email' => $email, ':id' => $id]);

            if ($check->fetch()) {
                $errors['email'] = "This email is already used by another account.";
            } else {
                $upd = $pdo->prepare(
                    "UPDATE users SET name = :name, email = :email, phone = :phone, address = :address
                     WHERE id = :id"
                );
                $upd->execute([
                    ':name'    => $name,
                    ':email'   => $email,
                    ':phone'   => $phone   ?: null,
                    ':address' => $address ?: null,
                    ':id'      => $id,
                ]);
                header("Location: manage_users.php?updated=1");
                exit();
            }
        } catch (PDOException $e) {
            error_log("Edit User Error: " . $e->getMessage());
            $errors['db'] = "Update failed due to a server error. Please try again.";
        }
    }
}

// ── Fetch current user data ───────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("SELECT id, name, email, phone, address FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Fetch User Error: " . $e->getMessage());
    $user = null;
}

if (!$user) {
    header("Location: manage_users.php");
    exit();
}

$name    = $_POST['name']    ?? $user['name'];
$email   = $_POST['email']   ?? $user['email'];
$phone   = $_POST['phone']   ?? $user['phone'];
$address = $_POST['address'] ?? $user['address'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User — ApexPlanet</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f4f3; min-height: 100vh; }
        header { background: #1a5c4f; color: #fff; padding: 0.9rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        header h1 { font-size: 1.4rem; }
        header h1 span { color: #3aafa9; }
        .btn-nav { background: #3aafa9; color: #fff; border: none; padding: 0.45rem 1rem; border-radius: 6px; cursor: pointer; font-size: 0.9rem; text-decoration: none; font-weight: 600; }
        .btn-nav:hover { background: #fff; color: #1a5c4f; }

        .container { max-width: 560px; margin: 2.5rem auto; padding: 0 1rem; }
        .back-link { display: inline-block; margin-bottom: 1rem; color: #3aafa9; text-decoration: none; font-size: 0.9rem; font-weight: 600; }
        .back-link:hover { text-decoration: underline; }
        .card { background: #fff; border-radius: 12px; padding: 2rem 2.2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.07); }
        h2 { color: #1a5c4f; font-size: 1.4rem; margin-bottom: 0.3rem; }
        .sub { color: #888; font-size: 0.88rem; margin-bottom: 1.5rem; }

        .alert { padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 1.2rem; font-size: 0.9rem; border-left: 4px solid; }
        .alert-error { background: #fdecea; color: #c0392b; border-color: #e74c3c; }

        .form-group { margin-bottom: 1.1rem; }
        label { display: block; font-size: 0.85rem; font-weight: 600; color: #444; margin-bottom: 0.35rem; }
        input[type="text"], input[type="email"] {
            width: 100%; padding: 0.65rem 0.9rem; border: 1.5px solid #ddd; border-radius: 7px;
            font-size: 0.95rem; color: #333; background: #fafafa; transition: border-color 0.2s, box-shadow 0.2s;
        }
        input:focus { outline: none; border-color: #3aafa9; box-shadow: 0 0 0 3px rgba(58,175,169,0.15); background: #fff; }
        input.is-invalid { border-color: #e74c3c !important; }
        input.is-valid   { border-color: #27ae60 !important; }
        .field-error { font-size: 0.78rem; color: #c0392b; margin-top: 4px; display: none; }
        .field-error.visible { display: block; }

        .btn-row { display: flex; gap: 0.8rem; margin-top: 1.5rem; }
        button[type="submit"] { flex: 1; padding: 0.8rem; background: #1a5c4f; color: #fff; border: none; border-radius: 7px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        button[type="submit"]:hover { background: #3aafa9; }
        .btn-cancel { flex: 1; padding: 0.8rem; background: #f1f1f1; color: #555; border: none; border-radius: 7px; font-size: 1rem; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; transition: background 0.2s; }
        .btn-cancel:hover { background: #e5e5e5; }
    </style>
</head>
<body>
<header>
    <h1>Apex<span>Planet</span></h1>
    <a href="logout.php" class="btn-nav">Logout</a>
</header>
<div class="container">
    <a href="manage_users.php" class="back-link">← Back to Manage Users</a>
    <div class="card">
        <h2>Edit User</h2>
        <p class="sub">Updating record for User ID #<?= (int)$user['id'] ?></p>

        <?php if (isset($errors['db'])): ?>
            <div class="alert alert-error">⚠️ <?= htmlspecialchars($errors['db']) ?></div>
        <?php endif; ?>

        <form method="POST" action="edit_user.php?id=<?= (int)$user['id'] ?>" id="editForm" novalidate>
            <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">

            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name"
                       value="<?= htmlspecialchars($name) ?>"
                       class="<?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                       required minlength="2" maxlength="100">
                <span class="field-error <?= isset($errors['name']) ? 'visible' : '' ?>" id="nameErr"><?= $errors['name'] ?? '' ?></span>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($email) ?>"
                       class="<?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                       required maxlength="150">
                <span class="field-error <?= isset($errors['email']) ? 'visible' : '' ?>" id="emailErr"><?= $errors['email'] ?? '' ?></span>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone"
                       value="<?= htmlspecialchars($phone ?? '') ?>"
                       class="<?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                       placeholder="+91 98765 43210" maxlength="15">
                <span class="field-error <?= isset($errors['phone']) ? 'visible' : '' ?>" id="phoneErr"><?= $errors['phone'] ?? '' ?></span>
            </div>

            <div class="form-group">
                <label for="address">Address</label>
                <input type="text" id="address" name="address"
                       value="<?= htmlspecialchars($address ?? '') ?>"
                       placeholder="City, State" maxlength="255">
            </div>

            <div class="btn-row">
                <a href="manage_users.php" class="btn-cancel">Cancel</a>
                <button type="submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Client-Side Validation (Task 3) ──────────────────────────────────────────
function showErr(el, id, msg) { el.classList.add('is-invalid'); el.classList.remove('is-valid'); const s=document.getElementById(id); s.textContent=msg; s.classList.add('visible'); }
function clearErr(el, id)     { el.classList.remove('is-invalid'); el.classList.add('is-valid'); const s=document.getElementById(id); s.textContent=''; s.classList.remove('visible'); }

const form    = document.getElementById('editForm');
const nameEl  = document.getElementById('name');
const emailEl = document.getElementById('email');
const phoneEl = document.getElementById('phone');

emailEl.addEventListener('blur', () => {
    const rx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    rx.test(emailEl.value) ? clearErr(emailEl,'emailErr') : showErr(emailEl,'emailErr','Please enter a valid email address.');
});

phoneEl.addEventListener('blur', () => {
    const rx = /^[0-9+\-\s]{7,15}$/;
    (!phoneEl.value || rx.test(phoneEl.value)) ? clearErr(phoneEl,'phoneErr') : showErr(phoneEl,'phoneErr','Phone must be 7–15 digits.');
});

form.addEventListener('submit', function(e) {
    let valid = true;
    if (nameEl.value.trim().length < 2)  { showErr(nameEl,'nameErr','Name must be at least 2 characters.'); valid=false; } else clearErr(nameEl,'nameErr');
    const rx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!rx.test(emailEl.value.trim()))  { showErr(emailEl,'emailErr','Please enter a valid email address.'); valid=false; } else clearErr(emailEl,'emailErr');
    const prx = /^[0-9+\-\s]{7,15}$/;
    if (phoneEl.value && !prx.test(phoneEl.value)) { showErr(phoneEl,'phoneErr','Phone must be 7–15 digits.'); valid=false; }
    if (!valid) { e.preventDefault(); form.querySelector('.is-invalid').focus(); }
});
</script>
</body>
</html>
