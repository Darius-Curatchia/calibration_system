<?php
/**
 * lock.php
 * Stub file — satisfies require_once in db.php on networked PCs.
 * SQLite WAL mode handles concurrent access natively, so no
 * file-based locking is needed.
 */

if (!function_exists('acquireLock')) {
    function acquireLock(): bool {
        return true; // WAL mode handles concurrency — no lock file needed
    }
}

if (!function_exists('releaseLock')) {
    function releaseLock(): void {
        // no-op
    }
}