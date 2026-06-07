<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
include 'db.php';
include 'audit_helper.php';

// Ensure columns exist
try { $pdo->exec("ALTER TABLE users ADD COLUMN display_name TEXT DEFAULT NULL"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE users ADD COLUMN avatar TEXT DEFAULT NULL"); } catch(Exception $e){}

$userId = (int)$_SESSION['user_id'];

// Fetch current user
$stmt = $pdo->prepare("SELECT id, username, display_name, role, avatar FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { header("Location: logout.php"); exit(); }

$successMsg = '';
$errors     = [];
$section    = $_GET['section'] ?? 'profile';

// ── Handle profile update ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $newUsername    = trim($_POST['username']     ?? '');
    $newDisplayName = trim($_POST['display_name'] ?? '');

    if (empty($newUsername)) $errors[] = 'Username cannot be empty.';

    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
        $chk->execute([$newUsername, $userId]);
        if ((int)$chk->fetchColumn() > 0) $errors[] = 'That username is already taken.';
    }

    $newAvatarPath = $user['avatar'];
    if (!empty($_FILES['avatar']['tmp_name'])) {
        $file    = $_FILES['avatar'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $allowed)) {
            $errors[] = 'Photo must be JPG, PNG, GIF, or WEBP.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Photo must be under 2 MB.';
        } else {
            $dir = 'uploads/avatars/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $newPath = $dir . uniqid('av_', true) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $newPath)) {
                if ($user['avatar'] && file_exists($user['avatar'])) @unlink($user['avatar']);
                $newAvatarPath = $newPath;
            } else {
                $errors[] = 'Failed to save photo.';
            }
        }
    }

    if (isset($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1') {
        if ($user['avatar'] && file_exists($user['avatar'])) @unlink($user['avatar']);
        $newAvatarPath = null;
    }

    if (empty($errors)) {
        $changes = [];
        if ($newUsername !== $user['username']) $changes[] = "Username: '{$user['username']}' → '{$newUsername}'";
        if ($newDisplayName !== ($user['display_name'] ?? '')) $changes[] = "Display name updated";
        if ($newAvatarPath !== $user['avatar']) $changes[] = "Photo updated";

        $upd = $pdo->prepare("UPDATE users SET username = ?, display_name = ?, avatar = ? WHERE id = ?");
        $upd->execute([$newUsername, $newDisplayName ?: null, $newAvatarPath, $userId]);
        $_SESSION['username'] = $newUsername;

        log_audit($pdo, 'EDIT', 'users', $userId,
            "Profile updated: " . (empty($changes) ? "No changes" : implode('; ', $changes)));

        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $successMsg = 'Profile updated successfully.';
        $section    = 'profile';
    }
}

// ── Handle password change ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $section   = 'password';
    $currentPw = $_POST['current_password']  ?? '';
    $newPw     = $_POST['new_password']       ?? '';
    $confirmPw = $_POST['confirm_password']   ?? '';

    $pwStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $pwStmt->execute([$userId]);
    $stored = $pwStmt->fetchColumn();

    if (!password_verify($currentPw, $stored))       $errors[] = 'Current password is incorrect.';
    if (strlen($newPw) < 6)                          $errors[] = 'New password must be at least 6 characters.';
    if ($newPw !== $confirmPw)                       $errors[] = 'New passwords do not match.';
    if ($newPw === $currentPw && empty($errors))     $errors[] = 'New password must be different from current password.';

    if (empty($errors)) {
        $hashed = password_hash($newPw, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $userId]);
        log_audit($pdo, 'EDIT', 'users', $userId, "Password changed");
        $successMsg = 'Password changed successfully.';
        $section    = 'password';
    }
}

$displayName = !empty($user['display_name']) ? $user['display_name'] : '';
$hasPhoto    = !empty($user['avatar']) && file_exists($user['avatar']);
$initial     = strtoupper(substr($displayName ?: $user['username'], 0, 1));
$roleLabel   = ucwords(str_replace('_', ' ', $user['role'] ?? 'user'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile — Calibration Management</title>
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

/* ── Page layout ── */
.profile-layout{display:grid;grid-template-columns:240px 1fr;gap:20px;max-width:940px;}
@media(max-width:860px){.profile-layout{grid-template-columns:1fr;}}

/* ── Card base ── */
.card{background:var(--bg-card);border-radius:var(--r-xl);box-shadow:var(--shadow-sm);border:1px solid var(--border);overflow:hidden;}
.card-header{padding:18px 22px;border-bottom:1px solid var(--border);background:linear-gradient(180deg,#fcfeff 0%,#ffffff 100%);}
.card-header h2{font-size:14.5px;font-weight:700;color:var(--navy);margin:0 0 3px;letter-spacing:-0.1px;}
.card-header p{font-size:12px;color:var(--text-3);margin:0;font-family:var(--mono);}
.card-body{padding:22px;}

/* ── Buttons ── */
.btn{display:inline-flex;align-items:center;gap:6px;padding:0 16px;height:34px;border-radius:var(--r-sm);font-size:12.5px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;border:none;white-space:nowrap;text-decoration:none;box-sizing:border-box;}
.btn svg{flex-shrink:0;}
.btn-primary{background:var(--navy);color:#fff;box-shadow:0 2px 8px rgba(5,48,79,0.20);}
.btn-primary:hover{background:var(--navy-mid);}
.btn-muted{background:var(--bg-raised);color:var(--text-2);border:1px solid var(--border);}
.btn-muted:hover{background:var(--bg-page);color:var(--text);}

/* ── Left: summary card ── */
.profile-summary-card{display:flex;flex-direction:column;align-items:center;text-align:center;padding:24px 18px 20px;}
.ps-avatar{width:80px;height:80px;border-radius:var(--r-lg);background:linear-gradient(135deg,var(--navy),var(--accent));color:#fff;font-size:30px;font-weight:700;display:flex;align-items:center;justify-content:center;overflow:hidden;box-shadow:0 4px 16px rgba(5,48,79,0.20);margin-bottom:12px;}
.ps-avatar img{width:100%;height:100%;object-fit:cover;border-radius:var(--r-lg);display:block;}
.ps-name{font-size:15px;font-weight:700;color:var(--navy);margin-bottom:2px;}
.ps-username{font-size:11.5px;color:var(--text-3);font-family:var(--mono);margin-bottom:8px;}
.ps-role-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.ps-role-badge.super_admin{background:rgba(124,58,237,0.10);color:#7c3aed;}
.ps-role-badge.admin{background:rgba(5,150,105,0.10);color:#059669;}
.ps-role-badge.user{background:rgba(26,144,217,0.10);color:var(--accent);}
.ps-role-badge.guest{background:rgba(143,163,177,0.12);color:var(--text-3);}
.ps-divider{width:100%;height:1px;background:var(--border);margin:16px 0;}

/* Side nav */
.profile-sidenav{width:100%;display:flex;flex-direction:column;gap:3px;}
.psnav-item{display:flex;align-items:center;gap:9px;padding:9px 12px;border-radius:var(--r-sm);font-size:12.5px;font-weight:600;color:var(--text-2);text-decoration:none;}
.psnav-item:hover{background:var(--bg-page);color:var(--navy);}
.psnav-item.active{background:var(--accent-soft);color:var(--accent);}
.psnav-item svg{width:14px;height:14px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}

/* ── Toasts & banners ── */
.toast{display:flex;align-items:center;gap:9px;padding:10px 14px;border-radius:var(--r-sm);margin-bottom:16px;font-size:12.5px;font-weight:600;}
.toast svg{width:14px;height:14px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;}
.toast.success{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;}
.toast.error{background:#fff1f2;color:#be123c;border:1px solid #fda4af;}
.error-banner{background:#fff1f2;border:1px solid #fda4af;border-radius:var(--r-sm);padding:11px 14px;font-size:12.5px;color:#be123c;font-weight:600;margin-bottom:16px;}
.error-banner ul{margin:4px 0 0;padding-left:18px;font-weight:500;}

/* ── Form grid ── */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px 18px;}
@media(max-width:600px){.form-grid{grid-template-columns:1fr;}}
.form-group{display:flex;flex-direction:column;gap:6px;}
.form-group.span-full{grid-column:1/-1;}
.form-group label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--text-3);display:flex;align-items:center;gap:4px;}
.required-star{color:#e53e3e;}
.optional-tag{font-size:9.5px;font-weight:600;text-transform:none;letter-spacing:0;color:var(--text-3);background:#f0f4f8;border:1px solid var(--border-mid);border-radius:4px;padding:1px 5px;}
.form-group input{height:34px;padding:0 12px;border-radius:var(--r-sm);border:1px solid var(--border-mid);font-size:12.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:var(--bg-raised);width:100%;}
.form-group input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);background:var(--bg-card);}
.form-group input::placeholder{color:var(--text-3);font-weight:400;}
.field-hint{font-size:11px;color:var(--text-3);margin-top:1px;}

/* ── Password wrap ── */
.input-wrap{position:relative;}
.input-wrap input{padding-right:40px;}
.pw-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;color:var(--text-3);display:flex;align-items:center;}
.pw-toggle:hover{color:var(--accent);}
.pw-toggle svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}

/* ── Avatar uploader ── */
.avatar-uploader{grid-column:1/-1;display:flex;align-items:center;gap:18px;padding:14px 18px;background:var(--bg-raised);border:1.5px dashed var(--border-mid);border-radius:var(--r-md);}
.avatar-uploader:hover{border-color:var(--accent);}
.av-preview{width:60px;height:60px;border-radius:var(--r-md);background:linear-gradient(135deg,var(--navy),var(--accent));color:#fff;font-size:22px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;box-shadow:0 2px 10px rgba(5,48,79,0.16);}
.av-preview img{width:100%;height:100%;object-fit:cover;border-radius:var(--r-md);}
.av-info strong{display:block;font-size:13px;font-weight:700;color:var(--navy);margin-bottom:2px;}
.av-info span{font-size:11.5px;color:var(--text-3);line-height:1.5;}
.av-btns{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:9px;}
.av-upload-btn{display:inline-flex;align-items:center;gap:5px;height:28px;padding:0 12px;border-radius:var(--r-sm);background:var(--accent-soft);color:var(--accent);border:1px solid rgba(26,144,217,0.20);font-size:12px;font-weight:600;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;}
.av-upload-btn:hover{background:rgba(26,144,217,0.14);}
.av-upload-btn svg{width:12px;height:12px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.av-remove-btn{display:none;align-items:center;gap:5px;height:28px;padding:0 12px;border-radius:var(--r-sm);background:rgba(220,53,53,0.08);color:#a81c1c;border:1px solid rgba(220,53,53,0.20);font-size:12px;font-weight:600;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;}
.av-remove-btn svg{width:11px;height:11px;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;}
.av-remove-btn.visible{display:inline-flex;}

/* ── Form actions ── */
.form-actions{grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;padding-top:14px;margin-top:4px;border-top:1px solid var(--border);}

/* ── Password strength ── */
.pw-strength-wrap{height:4px;border-radius:2px;background:#e8edf3;margin-top:6px;overflow:hidden;}
.pw-strength-bar{height:100%;width:0;border-radius:2px;}
.pw-strength-label{font-size:11px;color:var(--text-3);margin-top:3px;}
</style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
    <?php include 'includes/header.php'; ?>

    <div class="profile-layout">

        <!-- ── Left: summary card ── -->
        <div>
            <div class="card">
                <div class="profile-summary-card">
                    <div class="ps-avatar">
                        <?php if ($hasPhoto): ?>
                            <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="">
                        <?php else: ?>
                            <?= htmlspecialchars($initial) ?>
                        <?php endif; ?>
                    </div>
                    <div class="ps-name"><?= htmlspecialchars($displayName ?: $user['username']) ?></div>
                    <div class="ps-username">@<?= htmlspecialchars($user['username']) ?></div>
                    <span class="ps-role-badge <?= htmlspecialchars($user['role']) ?>"><?= htmlspecialchars($roleLabel) ?></span>

                    <div class="ps-divider"></div>

                    <nav class="profile-sidenav">
                        <a href="profile.php?section=profile" class="psnav-item <?= $section === 'profile' ? 'active' : '' ?>">
                            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            Edit Profile
                        </a>
                        <a href="profile.php?section=password" class="psnav-item <?= $section === 'password' ? 'active' : '' ?>">
                            <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                            Change Password
                        </a>
                        <?php if ($_SESSION['role'] === 'super_admin'): ?>
                        <a href="account_monitoring.php" class="psnav-item">
                            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                            Account Monitoring
                        </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </div>

        <!-- ── Right: forms ── -->
        <div>
            <?php if ($successMsg): ?>
            <div class="toast success">
                <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                <?= htmlspecialchars($successMsg) ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="error-banner">
                <strong>Please fix the following:</strong>
                <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
            </div>
            <?php endif; ?>

            <!-- ── Profile section ── -->
            <?php if ($section === 'profile'): ?>
            <div class="card">
                <div class="card-header">
                    <h2>Edit Profile</h2>
                    <p>Update your display name, username, and profile photo.</p>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="update_profile" value="1">
                        <input type="hidden" name="remove_avatar" id="removeAvatarFlag" value="0">
                        <div class="form-grid">

                            <div class="avatar-uploader">
                                <div class="av-preview" id="avPreview">
                                    <?php if ($hasPhoto): ?>
                                        <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="" id="avImg">
                                    <?php else: ?>
                                        <span id="avInitial"><?= htmlspecialchars($initial) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="av-info">
                                    <strong>Profile Photo</strong>
                                    <span>JPG, PNG, WEBP &middot; max 2 MB<br>Leave blank to keep current or show initials.</span>
                                    <div class="av-btns">
                                        <label class="av-upload-btn" for="avInput">
                                            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                            Change Photo
                                        </label>
                                        <button type="button" class="av-remove-btn <?= $hasPhoto ? 'visible' : '' ?>" id="avRemoveBtn" onclick="removeAvatar()">
                                            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            Remove
                                        </button>
                                    </div>
                                    <input type="file" id="avInput" name="avatar" accept="image/*" style="display:none" onchange="previewAv(this)">
                                </div>
                            </div>

                            <div class="form-group span-full">
                                <label>Display Name <span class="optional-tag">optional</span></label>
                                <input type="text" name="display_name" id="dispNameInput"
                                       value="<?= htmlspecialchars($displayName) ?>"
                                       placeholder="Your full name, e.g. John Doe" autocomplete="off">
                                <span class="field-hint">Shown in the header and profile. Falls back to your username if blank.</span>
                            </div>

                            <div class="form-group span-full">
                                <label>Username <span class="required-star">*</span></label>
                                <input type="text" name="username"
                                       value="<?= htmlspecialchars($user['username']) ?>"
                                       placeholder="Login username" required autocomplete="off">
                                <span class="field-hint">This is what you use to log in.</span>
                            </div>

                            <div class="form-actions">
                                <a href="dashboard.php" class="btn btn-muted">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                                    Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    Save Changes
                                </button>
                            </div>

                        </div>
                    </form>
                </div>
            </div>

            <!-- ── Password section ── -->
            <?php else: ?>
            <div class="card" id="change-password">
                <div class="card-header">
                    <h2>Change Password</h2>
                    <p>Choose a strong password at least 6 characters long.</p>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="change_password" value="1">
                        <div class="form-grid">

                            <div class="form-group span-full">
                                <label>Current Password <span class="required-star">*</span></label>
                                <div class="input-wrap">
                                    <input type="password" name="current_password" id="curPw" placeholder="Enter your current password" required autocomplete="current-password">
                                    <button type="button" class="pw-toggle" onclick="togglePw('curPw','curEye')">
                                        <svg id="curEye" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>New Password <span class="required-star">*</span></label>
                                <div class="input-wrap">
                                    <input type="password" name="new_password" id="newPw" placeholder="Min. 6 characters" required autocomplete="new-password" oninput="checkPwStrength(this.value)">
                                    <button type="button" class="pw-toggle" onclick="togglePw('newPw','newEye')">
                                        <svg id="newEye" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                </div>
                                <div class="pw-strength-wrap"><div class="pw-strength-bar" id="pwBar"></div></div>
                                <div class="pw-strength-label" id="pwLabel"></div>
                            </div>

                            <div class="form-group">
                                <label>Confirm New Password <span class="required-star">*</span></label>
                                <div class="input-wrap">
                                    <input type="password" name="confirm_password" id="confPw" placeholder="Repeat new password" required autocomplete="new-password">
                                    <button type="button" class="pw-toggle" onclick="togglePw('confPw','confEye')">
                                        <svg id="confEye" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="form-actions">
                                <a href="dashboard.php" class="btn btn-muted">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                                    Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    Update Password
                                </button>
                            </div>

                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /.right col -->
    </div><!-- /.profile-layout -->
</div><!-- /.main-content -->

<script>
/* ── Avatar preview ── */
function previewAv(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const p = document.getElementById('avPreview');
        p.innerHTML = `<img src="${e.target.result}" alt="">`;
        const rb = document.getElementById('avRemoveBtn');
        if (rb) rb.classList.add('visible');
        document.getElementById('removeAvatarFlag').value = '0';
    };
    reader.readAsDataURL(input.files[0]);
}

function removeAvatar() {
    document.getElementById('avInput').value = '';
    const dispName = (document.querySelector('input[name="display_name"]')?.value ||
                      document.querySelector('input[name="username"]')?.value || '?').trim();
    const initial = (dispName[0] || '?').toUpperCase();
    const p = document.getElementById('avPreview');
    p.innerHTML = `<span>${initial}</span>`;
    const rb = document.getElementById('avRemoveBtn');
    if (rb) rb.classList.remove('visible');
    document.getElementById('removeAvatarFlag').value = '1';
}

const dispInput = document.getElementById('dispNameInput');
if (dispInput) {
    dispInput.addEventListener('input', function() {
        const initial = (this.value.trim()[0] || '?').toUpperCase();
        const span = document.querySelector('#avPreview span');
        if (span) span.textContent = initial;
    });
}

/* ── Password toggle ── */
function togglePw(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (!input || !icon) return;
    if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`;
    } else {
        input.type = 'password';
        icon.innerHTML = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
    }
}

/* ── Password strength ── */
function checkPwStrength(pw) {
    const bar   = document.getElementById('pwBar');
    const label = document.getElementById('pwLabel');
    if (!bar || !label) return;
    let score = 0;
    if (pw.length >= 6)  score++;
    if (pw.length >= 10) score++;
    if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    const levels = [
        { w:'20%',  bg:'#ef4444', t:'Very weak'   },
        { w:'40%',  bg:'#f97316', t:'Weak'         },
        { w:'60%',  bg:'#eab308', t:'Fair'         },
        { w:'80%',  bg:'#22c55e', t:'Strong'       },
        { w:'100%', bg:'#15803d', t:'Very strong'  },
    ];
    const lvl = levels[Math.min(score, levels.length) - 1] || levels[0];
    bar.style.width      = pw.length ? lvl.w  : '0';
    bar.style.background = pw.length ? lvl.bg : 'transparent';
    label.textContent    = pw.length ? lvl.t  : '';
    label.style.color    = pw.length ? lvl.bg : 'var(--text-3)';
}

/* ── Auto-dismiss toast ── */
document.querySelectorAll('.toast').forEach(t => {
    setTimeout(() => {
        t.style.transition = 'opacity .4s';
        t.style.opacity    = '0';
        setTimeout(() => t.remove(), 400);
    }, 4000);
});

if (window.location.hash === '#change-password' && '<?= $section ?>' !== 'password') {
    window.location.href = 'profile.php?section=password';
}
</script>
</body>
</html>