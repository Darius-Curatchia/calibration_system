<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if ($_SESSION['role'] !== 'super_admin') { header("Location: dashboard.php"); exit(); }
include 'db.php';
include 'audit_helper.php';

// Ensure columns exist
try { $pdo->exec("ALTER TABLE users ADD COLUMN display_name TEXT DEFAULT NULL"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE users ADD COLUMN avatar TEXT DEFAULT NULL"); } catch(Exception $e){}

$errors = [];
$formData = ['username'=>'','display_name'=>'','role'=>'user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username     = trim($_POST['username'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    $password     = $_POST['password'] ?? '';
    $role         = $_POST['role'] ?? 'user';
    $allowedRoles = ['super_admin','admin','user'];

    if (!in_array($role, $allowedRoles)) $role = 'user';
    $formData = compact('username','display_name','role');

    if (empty($username))  $errors[] = 'Username is required.';
    if (empty($password))  $errors[] = 'Password is required.';
    if (strlen($password) < 6 && !empty($password)) $errors[] = 'Password must be at least 6 characters.';

    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $chk->execute([$username]);
        if ((int)$chk->fetchColumn() > 0) $errors[] = 'Username already exists.';
    }

    if (empty($errors)) {
        $hashed     = password_hash($password, PASSWORD_DEFAULT);
        $avatarPath = null;

        if (!empty($_FILES['avatar']['tmp_name'])) {
            $file    = $_FILES['avatar'];
            $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (!in_array($ext, $allowed)) {
                $errors[] = 'Avatar must be JPG, PNG, GIF, or WEBP.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = 'Avatar must be under 2 MB.';
            } else {
                $dir = 'uploads/avatars/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $avatarPath = $dir . uniqid('av_', true) . '.' . $ext;
                if (!move_uploaded_file($file['tmp_name'], $avatarPath)) {
                    $errors[] = 'Failed to save avatar.';
                    $avatarPath = null;
                }
            }
        }
    }

    if (empty($errors)) {
        try {
            $ins = $pdo->prepare("INSERT INTO users (username, display_name, password, role, avatar, created_at) VALUES (?,?,?,?,?,CURRENT_TIMESTAMP)");
            $ins->execute([$username, $display_name ?: null, $hashed, $role, $avatarPath]);
            $newId = (int)$pdo->lastInsertId();
            log_audit($pdo, 'ADD', 'users', $newId,
                "Created account: {$username}" . ($display_name ? " ({$display_name})" : '') . " | Role: {$role}");
            header("Location: account_monitoring.php?msg=created");
            exit();
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account — Calibration Management</title>
<link rel="icon" type="image/x-icon" href="assets/favicon.ico">
<script>
(function () {
    var collapsed = localStorage.getItem('sb-state') === '1';
    document.documentElement.dataset.sidebar = collapsed ? 'collapsed' : 'expanded';
    if (document.body) document.body.dataset.sidebar = collapsed ? 'collapsed' : 'expanded';
    document.addEventListener('DOMContentLoaded', function () {
        document.body.dataset.sidebar = document.documentElement.dataset.sidebar;
    });
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/sidebar.css">
<link rel="stylesheet" href="assets/css/header.css">
<link rel="stylesheet" href="assets/css/main.css">
<style>
:root {
    --navy:#05304f;--navy-mid:#0a4570;--accent:#1a90d9;
    --accent-glow:rgba(26,144,217,0.15);--accent-soft:rgba(26,144,217,0.08);
    --bg-page:#eef2f7;--bg-card:#ffffff;--bg-raised:#f8fafc;
    --border:rgba(5,48,79,0.10);--border-mid:rgba(5,48,79,0.16);
    --text:#0d1f2d;--text-2:#4a6070;--text-3:#8fa3b1;
    --mono:'DM Mono',monospace;
    --r-sm:8px;--r-md:12px;--r-lg:16px;--r-xl:20px;
    --shadow-sm:0 2px 8px rgba(5,48,79,0.08);
    --shadow-lg:0 8px 40px rgba(5,48,79,0.14);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:var(--bg-page);color:var(--text);-webkit-font-smoothing:antialiased;}

/* ── Card ── */
.card{background:var(--bg-card);border-radius:var(--r-xl);box-shadow:var(--shadow-sm);border:1px solid var(--border);margin-bottom:20px;overflow:hidden;}
.card-header{padding:18px 24px;border-bottom:1px solid var(--border);background:linear-gradient(180deg,#fcfeff 0%,#ffffff 100%);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;}
.card-header h2{font-size:14.5px;font-weight:700;color:var(--navy);margin:0 0 3px;letter-spacing:-0.1px;}
.card-header p{font-size:12px;color:var(--text-3);margin:0;font-family:var(--mono);}
.card-body{padding:24px;}

/* ── Buttons ── */
.btn{display:inline-flex;align-items:center;gap:6px;padding:0 16px;height:34px;border-radius:var(--r-sm);font-size:12.5px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;border:none;white-space:nowrap;text-decoration:none;box-sizing:border-box;}
.btn svg{flex-shrink:0;}
.btn-primary{background:var(--navy);color:#fff;box-shadow:0 2px 8px rgba(5,48,79,0.20);}
.btn-primary:hover{background:var(--navy-mid);}
.btn-muted{background:var(--bg-raised);color:var(--text-2);border:1px solid var(--border);}
.btn-muted:hover{background:var(--bg-page);color:var(--text);}

/* ── Error banner ── */
.error-banner{display:flex;flex-direction:column;gap:4px;background:#fff1f2;border:1px solid #fda4af;border-radius:var(--r-sm);padding:12px 16px;font-size:12.5px;color:#be123c;font-weight:600;margin-bottom:22px;}
.error-banner strong{font-size:12.5px;margin-bottom:2px;}
.error-banner ul{margin:0;padding-left:18px;font-weight:500;}

/* ── Form grid ── */
.form-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px 20px;}
@media(max-width:960px){.form-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:600px){.form-grid{grid-template-columns:1fr;}.card-body{padding:16px;}}

/* ── Section divider ── */
.section-divider{grid-column:1/-1;display:flex;align-items:center;gap:8px;padding-bottom:10px;border-bottom:1px solid var(--border);margin-top:4px;}
.section-divider svg{color:var(--text-3);flex-shrink:0;}
.section-divider-label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:var(--navy);}

/* ── Form groups ── */
.form-group{display:flex;flex-direction:column;gap:6px;position:relative;}
.form-group.span-2{grid-column:span 2;}
.form-group.span-full{grid-column:1/-1;}
.form-group label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--text-3);display:flex;align-items:center;gap:5px;}
.required-star{color:#e53e3e;}
.optional-tag{font-size:9.5px;font-weight:600;text-transform:none;letter-spacing:0;color:var(--text-3);background:#f0f4f8;border:1px solid var(--border-mid);border-radius:4px;padding:1px 5px;}

/* ── Inputs ── */
.form-group input,
.form-group select{height:34px;padding:0 12px;border-radius:var(--r-sm);border:1px solid var(--border-mid);font-size:12.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:var(--bg-raised);width:100%;}
.form-group input:focus,
.form-group select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);background:var(--bg-card);}
.form-group input::placeholder{color:var(--text-3);font-weight:400;}

/* ── Password wrap ── */
.input-wrap{position:relative;}
.input-wrap input{padding-right:40px;}
.pw-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;color:var(--text-3);display:flex;align-items:center;}
.pw-toggle:hover{color:var(--accent);}
.pw-toggle svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}

/* ── Avatar uploader ── */
.avatar-uploader{grid-column:1/-1;display:flex;align-items:center;gap:20px;padding:16px 20px;background:var(--bg-raised);border:1px dashed var(--border-mid);border-radius:var(--r-md);}
.avatar-uploader:hover{border-color:var(--accent);background:var(--bg-card);}
.avatar-preview{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--navy),var(--accent));color:#fff;font-size:24px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;box-shadow:0 2px 10px rgba(5,48,79,0.18);}
.avatar-preview img{width:100%;height:100%;object-fit:cover;}
.avatar-uploader-info{flex:1;}
.avatar-uploader-info strong{display:block;font-size:13px;font-weight:700;color:var(--navy);margin-bottom:2px;}
.avatar-uploader-info span{font-size:11.5px;color:var(--text-3);line-height:1.5;}
.avatar-btn-row{display:flex;align-items:center;gap:8px;margin-top:10px;flex-wrap:wrap;}
.avatar-upload-btn{display:inline-flex;align-items:center;gap:6px;padding:0 12px;height:30px;border-radius:var(--r-sm);background:var(--accent-soft);color:var(--accent);border:1px solid rgba(26,144,217,0.20);font-size:12px;font-weight:600;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;}
.avatar-upload-btn:hover{background:rgba(26,144,217,0.14);}
.avatar-upload-btn svg{width:12px;height:12px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.avatar-remove-btn{display:none;align-items:center;gap:5px;padding:0 12px;height:30px;border-radius:var(--r-sm);background:rgba(220,53,53,0.08);color:#a81c1c;border:1px solid rgba(220,53,53,0.20);font-size:12px;font-weight:600;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;}
.avatar-remove-btn.visible{display:inline-flex;}

/* ── Role cards ── */
.role-cards{grid-column:1/-1;display:grid;grid-template-columns:repeat(3,1fr);gap:10px;}
@media(max-width:700px){.role-cards{grid-template-columns:1fr;}}
.role-radio{display:none;}
.role-card{display:flex;flex-direction:column;gap:5px;padding:13px 15px;border-radius:var(--r-md);border:1.5px solid var(--border-mid);background:var(--bg-raised);cursor:pointer;}
.role-card:hover{background:var(--bg-card);border-color:var(--border-mid);}
.rc-icon{width:32px;height:32px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;margin-bottom:4px;flex-shrink:0;}
.rc-icon svg{width:16px;height:16px;}
.rc-name{font-size:13px;font-weight:700;color:var(--navy);}
.rc-desc{font-size:11px;color:var(--text-3);line-height:1.5;}

/* role-specific checked states */
.role-radio[value="super_admin"]:checked + .role-card{border-color:#7c3aed;background:#faf5ff;box-shadow:0 0 0 3px rgba(124,58,237,0.10);}
.role-radio[value="super_admin"]:checked + .role-card .rc-name{color:#7c3aed;}
.role-radio[value="super_admin"]:checked + .role-card .rc-icon{background:rgba(124,58,237,0.12);color:#7c3aed;}

.role-radio[value="admin"]:checked + .role-card{border-color:#059669;background:#f0fdf4;box-shadow:0 0 0 3px rgba(5,150,105,0.10);}
.role-radio[value="admin"]:checked + .role-card .rc-name{color:#059669;}
.role-radio[value="admin"]:checked + .role-card .rc-icon{background:rgba(5,150,105,0.12);color:#059669;}

.role-radio[value="user"]:checked + .role-card{border-color:var(--accent);background:#f0f9ff;box-shadow:0 0 0 3px var(--accent-glow);}
.role-radio[value="user"]:checked + .role-card .rc-name{color:var(--accent);}
.role-radio[value="user"]:checked + .role-card .rc-icon{background:var(--accent-soft);color:var(--accent);}

/* ── Form actions ── */
.form-actions{grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;padding-top:14px;margin-top:4px;border-top:1px solid var(--border);}
</style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
    <?php include 'includes/header.php'; ?>

    <div class="card">
        <div class="card-header">
            <div>
                <h2>Create New Account</h2>
                <p>Fill in the details below to register a new system user.</p>
            </div>
        </div>
        <div class="card-body">

            <?php if (!empty($errors)): ?>
            <div class="error-banner">
                <strong>Please fix the following:</strong>
                <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">

                    <!-- ── Photo ── -->
                    <div class="section-divider">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        <span class="section-divider-label">Profile Photo</span>
                    </div>

                    <div class="avatar-uploader">
                        <div class="avatar-preview" id="avatarPreview">
                            <span id="avatarInitial">?</span>
                        </div>
                        <div class="avatar-uploader-info">
                            <strong>Profile Photo</strong>
                            <span>Optional &middot; JPG, PNG, WEBP &middot; max 2 MB<br>If not uploaded, initials will be shown.</span>
                            <div class="avatar-btn-row">
                                <label class="avatar-upload-btn" for="avatarInput">
                                    <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    Upload Photo
                                </label>
                                <button type="button" class="avatar-remove-btn" id="avatarRemoveBtn" onclick="removeAvatar()">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    Remove
                                </button>
                            </div>
                            <input type="file" id="avatarInput" name="avatar" accept="image/*" style="display:none" onchange="previewAvatar(this)">
                        </div>
                    </div>

                    <!-- ── Account Details ── -->
                    <div class="section-divider">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span class="section-divider-label">Account Details</span>
                    </div>

                    <div class="form-group">
                        <label>Username <span class="required-star">*</span></label>
                        <input type="text" name="username" value="<?= htmlspecialchars($formData['username']) ?>"
                               id="usernameInput" placeholder="e.g. john_doe" required autocomplete="off">
                    </div>

                    <div class="form-group span-2">
                        <label>Display Name <span class="optional-tag">optional</span></label>
                        <input type="text" name="display_name" value="<?= htmlspecialchars($formData['display_name']) ?>"
                               placeholder="e.g. John Doe" autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label>Password <span class="required-star">*</span></label>
                        <div class="input-wrap">
                            <input type="password" name="password" id="pwInput"
                                   placeholder="Min. 6 characters" required autocomplete="new-password">
                            <button type="button" class="pw-toggle" onclick="togglePw()">
                                <svg id="pwEyeIcon" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- ── Role ── -->
                    <div class="section-divider">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span class="section-divider-label">Role</span>
                    </div>

                    <div class="role-cards">
                        <input type="radio" name="role" id="role_user" value="user" class="role-radio"
                               <?= $formData['role'] === 'user' ? 'checked' : '' ?>>
                        <label class="role-card" for="role_user">
                            <div class="rc-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <span class="rc-name">User</span>
                            <span class="rc-desc">Can view and add calibration and inspection records.</span>
                        </label>

                        <input type="radio" name="role" id="role_admin" value="admin" class="role-radio"
                               <?= $formData['role'] === 'admin' ? 'checked' : '' ?>>
                        <label class="role-card" for="role_admin">
                            <div class="rc-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            </div>
                            <span class="rc-name">Admin</span>
                            <span class="rc-desc">Can edit and delete records. No account management.</span>
                        </label>

                        <input type="radio" name="role" id="role_super_admin" value="super_admin" class="role-radio"
                               <?= $formData['role'] === 'super_admin' ? 'checked' : '' ?>>
                        <label class="role-card" for="role_super_admin">
                            <div class="rc-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            </div>
                            <span class="rc-name">Super Admin</span>
                            <span class="rc-desc">Full access: accounts, audit trail, all records.</span>
                        </label>
                    </div>

                    <!-- ── Actions ── -->
                    <div class="form-actions">
                        <a href="account_monitoring.php" class="btn btn-muted">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Create Account
                        </button>
                    </div>

                </div><!-- /.form-grid -->
            </form>
        </div><!-- /.card-body -->
    </div><!-- /.card -->
</div><!-- /.main-content -->

<script>
document.getElementById('usernameInput').addEventListener('input', function() {
    const initial = (this.value.trim()[0] || '?').toUpperCase();
    const span = document.getElementById('avatarInitial');
    if (span) span.textContent = initial;
});

function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const preview = document.getElementById('avatarPreview');
        preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
        document.getElementById('avatarRemoveBtn').classList.add('visible');
    };
    reader.readAsDataURL(input.files[0]);
}

function removeAvatar() {
    document.getElementById('avatarInput').value = '';
    const preview = document.getElementById('avatarPreview');
    const initial = (document.getElementById('usernameInput').value.trim()[0] || '?').toUpperCase();
    preview.innerHTML = `<span id="avatarInitial">${initial}</span>`;
    document.getElementById('avatarRemoveBtn').classList.remove('visible');
}

function togglePw() {
    const input = document.getElementById('pwInput');
    const icon  = document.getElementById('pwEyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`;
    } else {
        input.type = 'password';
        icon.innerHTML = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
    }
}
</script>
</body>
</html>