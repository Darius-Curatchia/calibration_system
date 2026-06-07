<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if ($_SESSION['role'] !== 'super_admin') { header("Location: dashboard.php"); exit(); }
include 'db.php';
include 'audit_helper.php';

try { $pdo->exec("ALTER TABLE users ADD COLUMN display_name TEXT DEFAULT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN avatar TEXT DEFAULT NULL"); } catch (Exception $e) {}
$pdo->exec("UPDATE users SET created_at = CURRENT_TIMESTAMP WHERE created_at IS NULL OR created_at = ''");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_account') {
    header('Content-Type: application/json');
    $delId     = (int)($_POST['delete_id'] ?? 0);
    $superPass = $_POST['super_password'] ?? '';
    if ($delId === (int)$_SESSION['user_id']) { echo json_encode(['success'=>false,'message'=>'You cannot delete your own account.']); exit(); }
    $selfStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $selfStmt->execute([(int)$_SESSION['user_id']]);
    $selfHash = $selfStmt->fetchColumn();
    if (!$selfHash || !password_verify($superPass, $selfHash)) { echo json_encode(['success'=>false,'message'=>'Incorrect password. Account not deleted.']); exit(); }
    $delStmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
    $delStmt->execute([$delId]);
    $delUser = $delStmt->fetch();
    if (!$delUser) { echo json_encode(['success'=>false,'message'=>'User not found.']); exit(); }
    $avatarStmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
    $avatarStmt->execute([$delId]);
    $av = $avatarStmt->fetchColumn();
    if ($av && file_exists($av)) @unlink($av);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$delId]);
    log_audit($pdo, 'DELETE', 'users', $delId, "Deleted account: {$delUser['username']} | Role: {$delUser['role']}");
    echo json_encode(['success'=>true,'message'=>"Account '{$delUser['username']}' deleted successfully."]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_role') {
    header('Content-Type: application/json');
    $targetId     = (int)($_POST['target_id'] ?? 0);
    $newRole      = $_POST['new_role'] ?? '';
    $superPass    = $_POST['super_password'] ?? '';
    $allowedRoles = ['super_admin','admin','user'];
    if (!in_array($newRole, $allowedRoles)) { echo json_encode(['success'=>false,'message'=>'Invalid role.']); exit(); }
    if ($targetId === (int)$_SESSION['user_id']) { echo json_encode(['success'=>false,'message'=>'You cannot change your own role.']); exit(); }
    $selfStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $selfStmt->execute([(int)$_SESSION['user_id']]);
    $selfHash = $selfStmt->fetchColumn();
    if (!$selfHash || !password_verify($superPass, $selfHash)) { echo json_encode(['success'=>false,'message'=>'Incorrect password. Role not changed.']); exit(); }
    $oldStmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
    $oldStmt->execute([$targetId]);
    $oldUser = $oldStmt->fetch();
    if (!$oldUser) { echo json_encode(['success'=>false,'message'=>'User not found.']); exit(); }
    $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$newRole, $targetId]);
    log_audit($pdo, 'UPDATE', 'users', $targetId, "Role changed: {$oldUser['username']} | {$oldUser['role']} → {$newRole}");
    echo json_encode(['success'=>true,'message'=>"Role updated to ".ucwords(str_replace('_',' ',$newRole)).".","new_role"=>$newRole]);
    exit();
}

$users      = $pdo->query("SELECT id, username, display_name, role, avatar, created_at FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$roleCounts = ['super_admin'=>0,'admin'=>0,'user'=>0];
foreach ($users as $u) { $r = $u['role'] ?? 'user'; if (isset($roleCounts[$r])) $roleCounts[$r]++; }
$totalUsers = count($users);
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account Monitoring — Calibration Management</title>
<link rel="icon" type="image/x-icon" href="assets/favicon.ico">
<script>
(function(){var c=localStorage.getItem('sb-state')==='1';document.documentElement.dataset.sidebar=c?'collapsed':'expanded';if(document.body)document.body.dataset.sidebar=c?'collapsed':'expanded';document.addEventListener('DOMContentLoaded',function(){document.body.dataset.sidebar=document.documentElement.dataset.sidebar;});})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/sidebar.css">
<link rel="stylesheet" href="assets/css/header.css">
<link rel="stylesheet" href="assets/css/main.css">
<style>
:root{
    --navy:#05304f;--navy-mid:#0a4570;--accent:#1a90d9;
    --accent-glow:rgba(26,144,217,0.15);--accent-soft:rgba(26,144,217,0.08);
    --bg-page:#eef2f7;--bg-card:#ffffff;--bg-raised:#f8fafc;
    --border:rgba(5,48,79,0.10);--border-mid:rgba(5,48,79,0.16);
    --text:#0d1f2d;--text-2:#4a6070;--text-3:#8fa3b1;
    --mono:'DM Mono',monospace;
    --r-sm:8px;--r-md:12px;--r-xl:20px;
    --shadow-sm:0 2px 8px rgba(5,48,79,0.08);--shadow-lg:0 8px 40px rgba(5,48,79,0.14);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:var(--bg-page);color:var(--text);-webkit-font-smoothing:antialiased;}
.card{background:var(--bg-card);border-radius:var(--r-xl);box-shadow:var(--shadow-sm);border:1px solid var(--border);margin-bottom:20px;overflow:hidden;}
.card-header{padding:18px 24px;border-bottom:1px solid var(--border);background:linear-gradient(180deg,#fcfeff 0%,#fff 100%);}
.card-header h2{font-size:14.5px;font-weight:700;color:var(--navy);margin:0 0 3px;letter-spacing:-0.1px;}
.card-header p{font-size:12px;color:var(--text-3);margin:0;font-family:var(--mono);}
.card-body{padding:20px 24px;}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px;}
.stat-card{background:var(--bg-raised);border-radius:var(--r-md);border:1px solid var(--border);padding:14px 16px;display:flex;align-items:center;gap:12px;}
.stat-icon{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.stat-icon svg{width:18px;height:18px;}
.stat-icon.total{background:rgba(26,144,217,.12);}.stat-icon.total svg{fill:#1a90d9;}
.stat-icon.sadmin{background:rgba(124,58,237,.12);}.stat-icon.sadmin svg{fill:#7c3aed;}
.stat-icon.admin{background:rgba(16,185,129,.12);}.stat-icon.admin svg{fill:#059669;}
.stat-icon.user{background:rgba(245,158,11,.12);}.stat-icon.user svg{fill:#d97706;}
.stat-value{font-size:22px;font-weight:700;color:var(--navy);line-height:1;}
.stat-label{font-size:11px;color:var(--text-3);margin-top:2px;font-weight:500;}
.toast{display:flex;align-items:center;gap:10px;padding:11px 16px;border-radius:var(--r-sm);margin-bottom:16px;font-size:12.5px;font-weight:600;}
.toast.success{background:rgba(22,163,74,0.10);color:#126934;border:1px solid rgba(22,163,74,0.30);}
.toast.error{background:rgba(220,53,53,0.10);color:#a81c1c;border:1px solid rgba(220,53,53,0.20);}
.toast svg{width:14px;height:14px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;}
.top-controls{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;}
.controls-left,.controls-right{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.filter-input{height:34px;padding:0 12px;border-radius:var(--r-sm);border:1px solid var(--border-mid);font-size:12.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:var(--bg-raised);min-width:200px;box-sizing:border-box;}
.filter-input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);background:var(--bg-card);}
.table-container{overflow-x:auto;overflow-y:auto;max-height:calc(100vh - 380px);border-radius:var(--r-md);border:1px solid var(--border);}
.table-container::-webkit-scrollbar{width:6px;height:6px;}
.table-container::-webkit-scrollbar-track{background:var(--bg-raised);}
.table-container::-webkit-scrollbar-thumb{background:var(--border-mid);border-radius:3px;}
.table-container::-webkit-scrollbar-thumb:hover{background:var(--accent);}
.accounts-table{width:100%;border-collapse:collapse;font-size:12.5px;}
.accounts-table thead{position:sticky;top:0;z-index:2;}
.accounts-table th{background:var(--navy);color:rgba(255,255,255,.80);padding:10px 14px;font-size:10.5px;font-weight:700;text-align:left;text-transform:uppercase;letter-spacing:0.6px;white-space:nowrap;border-right:1px solid rgba(255,255,255,.07);}
.accounts-table th:last-child{border-right:none;}
.accounts-table td{padding:10px 14px;border-bottom:1px solid var(--border);border-right:1px solid rgba(5,48,79,0.05);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;vertical-align:middle;}
.accounts-table td:last-child{border-right:none;}
.accounts-table tr:last-child td{border-bottom:none;}
.accounts-table tbody tr{cursor:pointer;}
.accounts-table tbody tr:nth-child(even){background:var(--bg-raised);}
.accounts-table tbody tr:hover{background:rgba(26,144,217,.06)!important;box-shadow:inset 3px 0 0 var(--accent);}
.user-cell{display:flex;align-items:center;gap:10px;}
.user-avatar{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0;text-transform:uppercase;overflow:hidden;}
.user-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.user-name{font-weight:700;font-size:13px;color:var(--navy);}
.user-displayname{font-size:11px;color:var(--text-3);margin-top:1px;}
.user-sub{font-size:11px;color:var(--text-3);margin-top:1px;font-family:var(--mono);}
.role-badge{display:inline-flex;align-items:center;gap:5px;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;}
.role-badge .dot{width:6px;height:6px;border-radius:50%;flex-shrink:0;}
.role-badge.super_admin{background:rgba(124,58,237,.10);color:#5b21b6;}.role-badge.super_admin .dot{background:#7c3aed;}
.role-badge.admin{background:rgba(16,185,129,.10);color:#065f46;}.role-badge.admin .dot{background:#059669;}
.role-badge.user{background:rgba(245,158,11,.10);color:#78350f;}.role-badge.user .dot{background:#d97706;}
.date-cell{font-family:var(--mono);font-size:11.5px;color:var(--text-3);}
.btn-delete{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:7px;font-size:12px;font-weight:600;background:rgba(220,53,53,0.10);color:#a81c1c;border:1px solid rgba(220,53,53,0.20);cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;}
.btn-delete:hover{background:rgba(220,53,53,0.18);}
.btn-delete:disabled{opacity:.38;cursor:not-allowed;}
.btn-delete svg{width:12px;height:12px;fill:#a81c1c;}
.you-badge{display:inline-block;background:var(--accent-soft);color:var(--accent);font-size:10px;font-weight:700;padding:1px 6px;border-radius:4px;margin-left:5px;letter-spacing:.3px;vertical-align:middle;}
.pagination{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:16px;padding-top:14px;border-top:1px solid var(--border);min-height:52px;}
.pagination button{padding:6px 16px;height:34px;border-radius:var(--r-sm);border:1px solid var(--border);background:var(--bg-raised);color:var(--navy);font-size:12.5px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;min-width:90px;text-align:center;}
.pagination button:hover:not(:disabled){background:var(--navy);color:#fff;border-color:var(--navy);}
.pagination button:disabled{opacity:.4;cursor:not-allowed;}
.pagination-info{font-size:11.5px;color:var(--text-3);font-family:var(--mono);min-width:120px;text-align:center;display:inline-block;}
.modal-overlay{position:fixed;inset:0;background:rgba(5,48,79,.55);display:none;justify-content:center;align-items:center;z-index:1000;padding:16px;box-sizing:border-box;}
.modal-overlay.open{display:flex;}
.modal-close-btn{width:28px;height:28px;border-radius:7px;border:1px solid var(--border);background:transparent;cursor:pointer;font-size:16px;color:var(--text-2);display:flex;align-items:center;justify-content:center;flex-shrink:0;padding:0;font-family:'Plus Jakarta Sans',sans-serif;line-height:1;}
.modal-close-btn:hover{background:rgba(220,53,53,0.10);color:#a81c1c;border-color:rgba(220,53,53,0.25);}
.confirm-modal-box{background:var(--bg-card);border-radius:var(--r-xl);width:92%;max-width:420px;box-shadow:var(--shadow-lg);border:1px solid var(--border);overflow:hidden;}
.confirm-modal-icon{font-size:36px;padding:26px 0 6px;text-align:center;}
.confirm-modal-body{padding:0 28px 20px;text-align:center;}
.confirm-modal-body h3{font-size:15px;font-weight:700;color:var(--navy);margin:0 0 8px;}
.confirm-modal-body p{font-size:13px;color:var(--text-2);margin:0 0 18px;line-height:1.55;}
.confirm-pw-row{display:flex;flex-direction:column;gap:5px;text-align:left;margin-bottom:4px;}
.confirm-pw-row label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-3);}
.confirm-pw-wrap{position:relative;}
.confirm-pw-input{width:100%;box-sizing:border-box;padding:9px 42px 9px 12px;border-radius:var(--r-sm);border:1px solid var(--border-mid);font-size:13.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:var(--bg-raised);}
.confirm-pw-input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);background:var(--bg-card);}
.confirm-pw-input.error-input{border-color:#dc2626;box-shadow:0 0 0 3px rgba(220,38,38,.10);}
.confirm-pw-eye{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;color:var(--text-3);display:flex;align-items:center;}
.confirm-pw-eye:hover{color:var(--navy);}
.confirm-pw-eye svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.confirm-modal-error{display:none;font-size:12px;color:#a81c1c;font-weight:600;background:rgba(220,53,53,0.08);border:1px solid rgba(220,53,53,0.20);border-radius:var(--r-sm);padding:8px 12px;margin-top:10px;text-align:left;}
.confirm-modal-error.show{display:block;}
.confirm-modal-footer{display:flex;gap:10px;justify-content:center;padding:14px 24px 18px;border-top:1px solid var(--border);background:var(--bg-raised);}
.account-modal-box{background:var(--bg-card);border-radius:var(--r-xl);width:92%;max-width:480px;box-shadow:var(--shadow-lg);border:1px solid var(--border);overflow:hidden;}
.acct-modal-header{background:linear-gradient(135deg,var(--navy) 0%,var(--navy-mid) 100%);padding:26px 24px 20px;position:relative;display:flex;flex-direction:column;align-items:center;text-align:center;}
.acct-modal-close{position:absolute;top:12px;right:14px;background:rgba(255,255,255,.12);border:none;color:#fff;width:30px;height:30px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;}
.acct-modal-close:hover{background:rgba(255,255,255,.22);}
.acct-modal-avatar{width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#1a90d9,#05304f);display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:700;color:#fff;overflow:hidden;box-shadow:0 4px 18px rgba(0,0,0,.22);margin-bottom:12px;flex-shrink:0;}
.acct-modal-avatar img{width:100%;height:100%;object-fit:cover;}
.acct-modal-username{font-size:17px;font-weight:700;color:#fff;margin:0 0 3px;display:flex;align-items:center;gap:8px;justify-content:center;}
.acct-modal-displayname{font-size:12.5px;color:rgba(255,255,255,.65);margin:0 0 10px;}
.acct-modal-body{padding:22px 24px 20px;}
.acct-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;}
.acct-info-item{background:var(--bg-raised);border-radius:9px;border:1px solid var(--border);padding:11px 14px;}
.acct-info-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-3);margin-bottom:4px;}
.acct-info-value{font-size:13px;font-weight:600;color:var(--navy);}
.acct-info-value.mono{font-family:var(--mono);font-size:12px;}
.role-change-section{border-top:1px solid var(--border);padding-top:18px;margin-top:4px;}
.role-change-title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-3);margin-bottom:12px;display:flex;align-items:center;gap:6px;}
.role-change-title svg{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;}
.role-option-group{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px;}
.role-opt-radio{display:none;}
.role-opt-card{display:flex;flex-direction:column;align-items:center;gap:3px;padding:10px 8px;border-radius:9px;border:1.5px solid var(--border);background:var(--bg-raised);cursor:pointer;text-align:center;}
.role-opt-card:hover{background:var(--bg-card);border-color:var(--border-mid);}
.role-opt-radio:checked+.role-opt-card{background:var(--bg-card);}
.role-opt-radio[value="user"]:checked+.role-opt-card{border-color:#d97706;box-shadow:0 0 0 2px rgba(217,119,6,.12);}
.role-opt-radio[value="admin"]:checked+.role-opt-card{border-color:#059669;box-shadow:0 0 0 2px rgba(5,150,105,.12);}
.role-opt-radio[value="super_admin"]:checked+.role-opt-card{border-color:#7c3aed;box-shadow:0 0 0 2px rgba(124,58,237,.12);}
.role-opt-icon{font-size:18px;line-height:1;}
.role-opt-name{font-size:11px;font-weight:700;color:var(--navy);}
.role-opt-desc{font-size:9.5px;color:var(--text-3);line-height:1.3;}
.pw-confirm-row{display:flex;flex-direction:column;gap:5px;}
.pw-confirm-row label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-3);}
.pw-input-wrap{position:relative;}
.pw-confirm-input{width:100%;box-sizing:border-box;padding:9px 42px 9px 12px;border-radius:var(--r-sm);border:1px solid var(--border-mid);font-size:13.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:var(--bg-raised);}
.pw-confirm-input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow);background:var(--bg-card);}
.pw-confirm-input.error-input{border-color:#dc2626;box-shadow:0 0 0 3px rgba(220,38,38,.10);}
.pw-eye-btn{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;color:var(--text-3);display:flex;align-items:center;}
.pw-eye-btn:hover{color:var(--accent);}
.pw-eye-btn svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.role-change-hint{font-size:11.5px;color:var(--text-3);margin-top:8px;display:flex;align-items:flex-start;gap:5px;line-height:1.45;}
.role-change-hint svg{width:13px;height:13px;fill:none;stroke:#d97706;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;margin-top:1px;}
.role-change-error{display:none;font-size:12px;color:#a81c1c;font-weight:600;background:rgba(220,53,53,0.08);border:1px solid rgba(220,53,53,0.20);border-radius:var(--r-sm);padding:8px 12px;margin-top:10px;}
.role-change-error.show{display:block;}
.role-change-success{display:none;font-size:12px;color:#126934;font-weight:600;background:rgba(22,163,74,0.10);border:1px solid rgba(22,163,74,0.30);border-radius:var(--r-sm);padding:8px 12px;margin-top:10px;}
.role-change-success.show{display:block;}
.self-lock-notice{background:var(--bg-raised);border:1px solid var(--border);border-radius:9px;padding:11px 14px;font-size:12.5px;color:var(--text-3);display:flex;align-items:center;gap:8px;}
.self-lock-notice svg{width:15px;height:15px;fill:none;stroke:var(--text-3);stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;}
.acct-modal-footer{display:flex;gap:10px;justify-content:flex-end;padding:14px 24px 18px;border-top:1px solid var(--border);background:var(--bg-raised);}
.btn{display:inline-flex;align-items:center;gap:6px;padding:0 16px;height:34px;border-radius:var(--r-sm);font-size:12.5px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;border:none;white-space:nowrap;text-decoration:none;box-sizing:border-box;}
.btn:disabled{opacity:.5;cursor:not-allowed;}
.btn-primary{background:var(--navy);color:#fff;box-shadow:0 2px 8px rgba(5,48,79,.20);}
.btn-primary:hover{background:var(--navy-mid);}
.btn-accent{background:var(--accent);color:#fff;box-shadow:0 2px 8px rgba(26,144,217,.25);}
.btn-accent:hover{background:#1480c5;}
.btn-danger-solid{background:#dc2626;color:#fff;box-shadow:0 2px 8px rgba(220,38,38,.25);}
.btn-danger-solid:hover{background:#b91c1c;}
.btn-muted{background:var(--bg-raised);color:var(--text-2);border:1px solid var(--border);}
.btn-muted:hover{background:var(--bg-page);color:var(--text);}
.spinner{width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;display:none;}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
    <?php include 'includes/header.php'; ?>

    <div id="ajaxToast" style="display:none;" class="toast success">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        <span id="ajaxToastMsg"></span>
    </div>

    <?php if ($msg === 'created'): ?>
    <div class="toast success">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        Account created successfully.
    </div>
    <?php endif; ?>

    <div class="stat-grid">
        <div class="stat-card"><div class="stat-icon total"><svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg></div><div><div class="stat-value" id="statTotal"><?= $totalUsers ?></div><div class="stat-label">Total Accounts</div></div></div>
        <div class="stat-card"><div class="stat-icon sadmin"><svg viewBox="0 0 24 24"><path d="M12 1l3.22 6.52L22 8.69l-5 4.86 1.18 6.88L12 17.27l-6.18 3.16L7 13.55 2 8.69l6.78-.17z"/></svg></div><div><div class="stat-value" id="statSuperAdmin"><?= $roleCounts['super_admin'] ?></div><div class="stat-label">Super Admins</div></div></div>
        <div class="stat-card"><div class="stat-icon admin"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L21 8l-9 9z"/></svg></div><div><div class="stat-value" id="statAdmin"><?= $roleCounts['admin'] ?></div><div class="stat-label">Admins</div></div></div>
        <div class="stat-card"><div class="stat-icon user"><svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg></div><div><div class="stat-value" id="statUser"><?= $roleCounts['user'] ?></div><div class="stat-label">Users</div></div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Registered Accounts</h2>
            <p>Click any row to view details &amp; manage roles</p>
        </div>
        <div class="card-body">
            <div class="top-controls">
                <div class="controls-left">
                    <a href="add_account.php" class="btn btn-primary">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Create Account
                    </a>
                    <span style="font-size:12px;color:var(--text-3);" id="totalLabel"><?= $totalUsers ?> account<?= $totalUsers !== 1 ? 's' : '' ?> total</span>
                </div>
                <div class="controls-right">
                    <input type="text" class="filter-input" id="searchInput" placeholder="Search accounts…">
                </div>
            </div>

            <div class="table-container">
                <table class="accounts-table" id="accountsTable">
                    <thead>
                        <tr><th>No.</th><th>Account</th><th>Role</th><th>Created</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php
                    $avatarColors = ['#1a90d9','#7c3aed','#059669','#d97706','#e11d48','#0891b2'];
                    foreach ($users as $i => $u):
                        $role      = $u['role'] ?? 'user';
                        $roleLabel = ucwords(str_replace('_',' ',$role));
                        $initial   = strtoupper(substr($u['username'],0,1));
                        $color     = $avatarColors[$u['id'] % count($avatarColors)];
                        $isSelf    = ($u['id'] == $_SESSION['user_id']);
                        $createdAt = !empty($u['created_at']) ? date('M d, Y',strtotime($u['created_at'])) : '—';
                        $dispName  = !empty($u['display_name']) ? $u['display_name'] : '';
                        $hasAvatar = !empty($u['avatar']) && file_exists($u['avatar']);
                        $rowData   = json_encode(['id'=>$u['id'],'username'=>$u['username'],'display_name'=>$dispName,'role'=>$role,'role_label'=>$roleLabel,'created_at'=>$createdAt,'avatar'=>$hasAvatar?$u['avatar']:'','color'=>$color,'initial'=>$initial,'is_self'=>$isSelf]);
                    ?>
                    <tr class="data-row" id="row-<?= $u['id'] ?>"
                        onclick="openAccountModal(<?= htmlspecialchars($rowData, ENT_QUOTES) ?>)"
                        title="Click to view account details">
                        <td style="font-family:'DM Mono',monospace;font-size:11px;color:var(--text-3);"><?= $i+1 ?></td>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar" style="background:<?= $color ?>;">
                                    <?php if ($hasAvatar): ?><img src="<?= htmlspecialchars($u['avatar']) ?>" alt=""><?php else: ?><?= htmlspecialchars($initial) ?><?php endif; ?>
                                </div>
                                <div>
                                    <div class="user-name"><?= htmlspecialchars($u['username']) ?><?php if($isSelf): ?><span class="you-badge">YOU</span><?php endif; ?></div>
                                    <?php if($dispName): ?><div class="user-displayname"><?= htmlspecialchars($dispName) ?></div><?php endif; ?>
                                    <div class="user-sub">ID #<?= $u['id'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="role-badge <?= htmlspecialchars($role) ?>" id="role-badge-<?= $u['id'] ?>"><span class="dot"></span><?= htmlspecialchars($roleLabel) ?></span></td>
                        <td class="date-cell"><?= htmlspecialchars($createdAt) ?></td>
                        <td onclick="event.stopPropagation()">
                            <button class="btn-delete"
                                    <?= $isSelf ? 'disabled title="Cannot delete your own account"' : '' ?>
                                    data-id="<?= $u['id'] ?>"
                                    data-name="<?= htmlspecialchars($u['username']) ?>"
                                    data-role="<?= htmlspecialchars($role) ?>"
                                    onclick="<?= $isSelf ? '' : 'openDeleteModal(this)' ?>">
                                <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="pagination" id="pagination"></div>
        </div>
    </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div id="deleteModal" class="modal-overlay">
    <div class="confirm-modal-box">
        <div class="confirm-modal-icon">🗑️</div>
        <div class="confirm-modal-body">
            <h3>Delete Account?</h3>
            <p>You're about to permanently delete <strong id="deleteModalName"></strong>. This action cannot be undone.</p>
            <div class="confirm-pw-row">
                <label for="deletePwInput">Your Password <span style="color:#a81c1c;">*</span></label>
                <div class="confirm-pw-wrap">
                    <input type="password" id="deletePwInput" class="confirm-pw-input" placeholder="Enter your password to confirm">
                    <button type="button" class="confirm-pw-eye" onclick="toggleDeletePw()">
                        <svg id="deletePwEyeIcon" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>
            <div class="confirm-modal-error" id="deleteModalError"></div>
        </div>
        <div class="confirm-modal-footer">
            <button type="button" class="btn btn-danger-solid" id="confirmDeleteBtn" onclick="confirmDelete()">
                <span class="spinner" id="deleteSpinner"></span>Yes, Delete
            </button>
            <button type="button" class="btn btn-muted" onclick="closeDeleteModal()">Cancel</button>
        </div>
    </div>
</div>

<!-- ACCOUNT DETAIL MODAL -->
<div id="accountModal" class="modal-overlay">
    <div class="account-modal-box">
        <div class="acct-modal-header">
            <button class="acct-modal-close" onclick="closeAccountModal()">✕</button>
            <div class="acct-modal-avatar" id="modalAvatar"></div>
            <div class="acct-modal-username" id="modalUsername"></div>
            <div class="acct-modal-displayname" id="modalDisplayname"></div>
            <span class="role-badge" style="background:rgba(255,255,255,.15);color:#fff;"><span class="dot" style="background:#fff;"></span><span id="modalRoleBadgeText"></span></span>
        </div>
        <div class="acct-modal-body">
            <div class="acct-info-grid">
                <div class="acct-info-item"><div class="acct-info-label">User ID</div><div class="acct-info-value mono" id="modalId"></div></div>
                <div class="acct-info-item"><div class="acct-info-label">Created</div><div class="acct-info-value mono" id="modalCreated"></div></div>
                <div class="acct-info-item" style="grid-column:1/-1;"><div class="acct-info-label">Current Role</div><div class="acct-info-value" id="modalCurrentRole"></div></div>
            </div>
            <div class="role-change-section">
                <div class="role-change-title">
                    <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Change Role
                </div>
                <div class="self-lock-notice" id="selfLockNotice" style="display:none;">
                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    You cannot change your own role.
                </div>
                <div id="roleChangeControls">
                    <div class="role-option-group">
                        <input type="radio" name="modal_role" id="modal_role_user" value="user" class="role-opt-radio">
                        <label class="role-opt-card" for="modal_role_user"><span class="role-opt-icon">👤</span><span class="role-opt-name">User</span><span class="role-opt-desc">View &amp; add records</span></label>
                        <input type="radio" name="modal_role" id="modal_role_admin" value="admin" class="role-opt-radio">
                        <label class="role-opt-card" for="modal_role_admin"><span class="role-opt-icon">🛡</span><span class="role-opt-name">Admin</span><span class="role-opt-desc">Edit &amp; delete records</span></label>
                        <input type="radio" name="modal_role" id="modal_role_super_admin" value="super_admin" class="role-opt-radio">
                        <label class="role-opt-card" for="modal_role_super_admin"><span class="role-opt-icon">⭐</span><span class="role-opt-name">Super Admin</span><span class="role-opt-desc">Full access</span></label>
                    </div>
                    <div class="pw-confirm-row">
                        <label for="modalSuperPassword">Your Password <span style="color:#a81c1c;">*</span></label>
                        <div class="pw-input-wrap">
                            <input type="password" id="modalSuperPassword" class="pw-confirm-input" placeholder="Enter your password to confirm">
                            <button type="button" class="pw-eye-btn" onclick="toggleModalPw()">
                                <svg id="modalPwEyeIcon" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <div class="role-change-hint">
                            <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            Enter your own password to authorize this role change.
                        </div>
                    </div>
                    <div class="role-change-error" id="roleChangeError"></div>
                    <div class="role-change-success" id="roleChangeSuccess"></div>
                </div>
            </div>
        </div>
        <div class="acct-modal-footer">
            <button class="btn btn-muted" onclick="closeAccountModal()">Close</button>
            <button class="btn btn-accent" id="applyRoleBtn" onclick="applyRoleChange()">
                <span class="spinner" id="roleSpinner"></span>Apply Role Change
            </button>
        </div>
    </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const tb = document.querySelector('#accountsTable tbody');
    const si = document.getElementById('searchInput');
    let cp = 1, rpp = 15, fr = tb ? Array.from(tb.rows) : [];
    function filter(reset) {
        if (!tb) return;
        const q = si.value.toLowerCase();
        fr = Array.from(tb.rows).filter(r => r.innerText.toLowerCase().includes(q));
        if (reset) cp = 1; show();
    }
    function show() {
        if (!tb) return;
        const tot = Math.ceil(fr.length / rpp) || 1;
        if (cp > tot) cp = tot;
        Array.from(tb.rows).forEach(r => r.style.display = 'none');
        fr.slice((cp - 1) * rpp, cp * rpp).forEach(r => r.style.display = '');
        const p = document.getElementById('pagination');
        if (!p) return;
        if (tot <= 1) { p.innerHTML = ''; return; }
        p.innerHTML = '';
        const pv = document.createElement('button'); pv.textContent = '← Prev'; pv.disabled = cp === 1; pv.onclick = () => { cp--; show(); };
        const info = document.createElement('span'); info.className = 'pagination-info'; info.textContent = `Page ${cp} of ${tot}`;
        const nx = document.createElement('button'); nx.textContent = 'Next →'; nx.disabled = cp === tot; nx.onclick = () => { cp++; show(); };
        p.appendChild(pv); p.appendChild(info); p.appendChild(nx);
    }
    if (si) si.addEventListener('input', () => filter(true));
    filter(false);
    document.querySelectorAll('.toast:not(#ajaxToast)').forEach(t => {
        setTimeout(() => { t.style.transition = 'opacity .4s'; t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 4000);
    });
});

let deleteTargetId = null;
function openDeleteModal(btn) {
    deleteTargetId = btn.dataset.id;
    document.getElementById('deleteModalName').textContent = btn.dataset.name;
    document.getElementById('deletePwInput').value = '';
    document.getElementById('deletePwInput').classList.remove('error-input');
    document.getElementById('deleteModalError').classList.remove('show');
    document.getElementById('deleteModal').classList.add('open');
    setTimeout(() => document.getElementById('deletePwInput').focus(), 100);
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('open'); deleteTargetId = null; }

async function confirmDelete() {
    const password = document.getElementById('deletePwInput').value.trim();
    const errorEl  = document.getElementById('deleteModalError');
    const pwInput  = document.getElementById('deletePwInput');
    const btn      = document.getElementById('confirmDeleteBtn');
    const spinner  = document.getElementById('deleteSpinner');
    errorEl.classList.remove('show'); pwInput.classList.remove('error-input');
    if (!password) { errorEl.textContent = 'Please enter your password.'; errorEl.classList.add('show'); pwInput.classList.add('error-input'); pwInput.focus(); return; }
    btn.disabled = true; spinner.style.display = 'inline-block';
    try {
        const fd = new FormData(); fd.append('action','delete_account'); fd.append('delete_id',deleteTargetId); fd.append('super_password',password);
        const res = await fetch('account_monitoring.php',{method:'POST',body:fd}); const json = await res.json();
        if (json.success) {
            const row = document.getElementById('row-'+deleteTargetId);
            if (row) row.remove();
            closeDeleteModal(); closeAccountModal();
            const totalEl = document.getElementById('statTotal');
            if (totalEl) totalEl.textContent = parseInt(totalEl.textContent) - 1;
            showAjaxToast(json.message,'success');
        } else {
            errorEl.textContent = '✗ '+json.message; errorEl.classList.add('show'); pwInput.classList.add('error-input'); pwInput.focus();
        }
    } catch(e) { errorEl.textContent = 'Network error. Please try again.'; errorEl.classList.add('show'); }
    finally { btn.disabled = false; spinner.style.display = 'none'; }
}

function showAjaxToast(msg, type='success') {
    const toast = document.getElementById('ajaxToast'); const msgEl = document.getElementById('ajaxToastMsg');
    toast.className = 'toast '+type; msgEl.textContent = msg; toast.style.display = 'flex'; toast.style.opacity = '1';
    setTimeout(() => { toast.style.transition = 'opacity .4s'; toast.style.opacity = '0'; setTimeout(() => { toast.style.display = 'none'; toast.style.transition = ''; }, 400); }, 4000);
}

function toggleDeletePw() {
    const input = document.getElementById('deletePwInput'); const icon = document.getElementById('deletePwEyeIcon');
    if (input.type === 'password') { input.type = 'text'; icon.innerHTML = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`; }
    else { input.type = 'password'; icon.innerHTML = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`; }
}
document.getElementById('deletePwInput').addEventListener('keydown', e => { if (e.key === 'Enter') confirmDelete(); });
document.getElementById('deleteModal').addEventListener('click', function(e) { if (e.target === this) closeDeleteModal(); });

let currentTargetId = null;
function openAccountModal(data) {
    currentTargetId = data.id;
    const avatarEl = document.getElementById('modalAvatar');
    if (data.avatar) { avatarEl.innerHTML = `<img src="${data.avatar}" alt="">`; avatarEl.style.background = ''; }
    else { avatarEl.innerHTML = data.initial; avatarEl.style.background = `linear-gradient(135deg,${data.color},#05304f)`; }
    document.getElementById('modalUsername').innerHTML = data.username + (data.is_self ? ' <span class="you-badge" style="background:rgba(255,255,255,.18);color:#fff;">YOU</span>' : '');
    document.getElementById('modalDisplayname').textContent = data.display_name || '';
    document.getElementById('modalId').textContent       = '#' + data.id;
    document.getElementById('modalCreated').textContent   = data.created_at;
    document.getElementById('modalCurrentRole').textContent  = data.role_label;
    document.getElementById('modalRoleBadgeText').textContent = data.role_label;
    const roleRadio = document.querySelector(`input[name="modal_role"][value="${data.role}"]`);
    if (roleRadio) roleRadio.checked = true;
    const selfLock = document.getElementById('selfLockNotice'); const roleControls = document.getElementById('roleChangeControls'); const applyBtn = document.getElementById('applyRoleBtn');
    if (data.is_self) { selfLock.style.display='flex'; roleControls.style.display='none'; applyBtn.style.display='none'; }
    else { selfLock.style.display='none'; roleControls.style.display='block'; applyBtn.style.display='inline-flex'; }
    document.getElementById('modalSuperPassword').value = '';
    document.getElementById('modalSuperPassword').classList.remove('error-input');
    document.getElementById('roleChangeError').classList.remove('show');
    document.getElementById('roleChangeSuccess').classList.remove('show');
    document.getElementById('accountModal').classList.add('open');
}
function closeAccountModal() { document.getElementById('accountModal').classList.remove('open'); currentTargetId = null; }
document.getElementById('accountModal').addEventListener('click', function(e) { if (e.target === this) closeAccountModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeDeleteModal(); closeAccountModal(); } });

async function applyRoleChange() {
    const selectedRole = document.querySelector('input[name="modal_role"]:checked')?.value;
    const password = document.getElementById('modalSuperPassword').value.trim();
    const errorEl = document.getElementById('roleChangeError'); const successEl = document.getElementById('roleChangeSuccess');
    const btn = document.getElementById('applyRoleBtn'); const spinner = document.getElementById('roleSpinner'); const pwInput = document.getElementById('modalSuperPassword');
    errorEl.classList.remove('show'); successEl.classList.remove('show'); pwInput.classList.remove('error-input');
    if (!selectedRole) { errorEl.textContent = 'Please select a role.'; errorEl.classList.add('show'); return; }
    if (!password) { errorEl.textContent = 'Please enter your password.'; errorEl.classList.add('show'); pwInput.classList.add('error-input'); pwInput.focus(); return; }
    btn.disabled = true; spinner.style.display = 'inline-block';
    try {
        const fd = new FormData(); fd.append('action','change_role'); fd.append('target_id',currentTargetId); fd.append('new_role',selectedRole); fd.append('super_password',password);
        const res = await fetch('account_monitoring.php',{method:'POST',body:fd}); const json = await res.json();
        if (json.success) {
            successEl.textContent = '✓ '+json.message; successEl.classList.add('show'); pwInput.value = '';
            const roleLabels = {user:'User',admin:'Admin',super_admin:'Super Admin'};
            document.getElementById('modalRoleBadgeText').textContent = roleLabels[json.new_role] || json.new_role;
            document.getElementById('modalCurrentRole').textContent   = roleLabels[json.new_role] || json.new_role;
            const tableBadge = document.getElementById('role-badge-'+currentTargetId);
            if (tableBadge) { tableBadge.className = 'role-badge '+json.new_role; tableBadge.innerHTML = `<span class="dot"></span>${roleLabels[json.new_role]}`; }
            setTimeout(closeAccountModal, 1800);
        } else { errorEl.textContent = '✗ '+json.message; errorEl.classList.add('show'); pwInput.classList.add('error-input'); pwInput.focus(); }
    } catch(e) { errorEl.textContent = 'Network error. Please try again.'; errorEl.classList.add('show'); }
    finally { btn.disabled = false; spinner.style.display = 'none'; }
}

function toggleModalPw() {
    const input = document.getElementById('modalSuperPassword'); const icon = document.getElementById('modalPwEyeIcon');
    if (input.type === 'password') { input.type = 'text'; icon.innerHTML = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`; }
    else { input.type = 'password'; icon.innerHTML = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`; }
}
</script>
</body>
</html>