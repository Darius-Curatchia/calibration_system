<?php
function log_audit(PDO $pdo, string $action, ?string $targetTable, ?int $recordId, ?string $details = null): void {
    try {
        $userId   = $_SESSION['user_id']  ?? null;
        $username = $_SESSION['username'] ?? 'System';

        // phpdesktop: every client runs the embedded browser locally so
        // REMOTE_ADDR is always 127.0.0.1. Use the OS hostname instead —
        // it uniquely identifies which PC ran the action.
        $hostname = gethostname() ?: 'unknown';

        $pdo->prepare("
            INSERT INTO audit_log (user_id, username, action, target_table, record_id, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([$userId, $username, $action, $targetTable, $recordId, $details, $hostname]);
    } catch (Exception $e) {
        error_log('Audit log error: ' . $e->getMessage());
    }
}