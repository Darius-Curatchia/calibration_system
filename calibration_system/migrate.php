<?php
// run_migration.php — DELETE THIS FILE after running it once
include 'db.php';

try {
    $pdo->exec("BEGIN TRANSACTION;

    CREATE TABLE standard_samples_new (
        id                    INTEGER PRIMARY KEY AUTOINCREMENT,
        description           TEXT NOT NULL,
        equipment_code        TEXT,
        model_maker           TEXT,
        serial_no             TEXT,
        location              TEXT,
        calibration_date      DATE NOT NULL,
        next_calibration_date DATE NOT NULL,
        calibration_frequency INTEGER NOT NULL,
        calibrator            TEXT,
        present_status        TEXT NOT NULL CHECK(present_status IN (
                                  'Good','Not Good','For Disposal','Not In Use','Not Yet Calibrated'
                              )),
        created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    INSERT INTO standard_samples_new SELECT * FROM standard_samples;
    DROP TABLE standard_samples;
    ALTER TABLE standard_samples_new RENAME TO standard_samples;

    COMMIT;");

    echo "✅ Migration successful. You can now delete this file.";
} catch (Exception $e) {
    $pdo->exec("ROLLBACK;");
    echo "❌ Migration failed: " . $e->getMessage();
}