<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

function fmtDateLong($d) {
    if (empty($d) || $d === '0000-00-00') return '';
    return date('F j, Y', strtotime($d));
}
function fmtDateShort($d) {
    if (empty($d) || $d === '0000-00-00') return '';
    return date('d-M-y', strtotime($d));
}
function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES); }

$machine_code  = trim($_GET['machine_code']  ?? '');
$cert_number   = trim($_GET['cert_number']   ?? '');
$issue_date    = trim($_GET['issue_date']    ?? '');
$result        = trim($_GET['result']        ?? 'PASS');
$isPassed      = strtoupper($result) === 'PASS';

$engineer_name  = trim($_GET['engineer_name']  ?? '');
$engineer_title = trim($_GET['engineer_title'] ?? 'PE Calibration Engineer');
$manager_name   = trim($_GET['manager_name']   ?? '');
$manager_title  = trim($_GET['manager_title']  ?? 'PE Section Manager');
$concern_name   = trim($_GET['concern_name']   ?? '');
$concern_title  = trim($_GET['concern_title']  ?? 'Concern Section Manager');

$revisions_json = $_GET['revisions'] ?? '[]';
$revisions = json_decode($revisions_json, true) ?: [];

$report = [];
$cert   = [];

if ($machine_code) {
    $stmt = $pdo->prepare("
        SELECT machine_code, description, model, maker, serial_number, location,
               calibration_date, next_calibration, cal_frequency, calibrator,
               present_status, model_maker, section, manager, reference,
               cal_type, env_type
        FROM calibration_report
        WHERE machine_code = ?
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$machine_code]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // ── Fallback: check standard_samples if not found in calibration_report ──
    if (!$report) {
        $stmtSS = $pdo->prepare("
            SELECT
                equipment_code        AS machine_code,
                description,
                model,
                maker,
                serial_no             AS serial_number,
                location,
                calibration_date,
                next_calibration_date AS next_calibration,
                calibration_frequency AS cal_frequency,
                calibrator,
                present_status,
                model_maker,
                section,
                manager,
                reference,
                cal_type,
                env_type
            FROM standard_samples
            WHERE equipment_code = ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmtSS->execute([$machine_code]);
        $report = $stmtSS->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    $stmt2 = $pdo->prepare("
        SELECT instrument_date_received, calibration_location, certificate_number, date
        FROM certificate_registration
        WHERE machine_code = ?
        ORDER BY id DESC LIMIT 1
    ");
    $stmt2->execute([$machine_code]);
    $cert = $stmt2->fetch(PDO::FETCH_ASSOC) ?: [];
}

$calTypeRaw = trim($report['cal_type'] ?? '');
$calType    = $calTypeRaw === 'Internal' ? 'Internal Calibration'
            : ($calTypeRaw === 'External' ? 'External Calibration'
            : ($calTypeRaw ?: 'External Calibration'));

$equipCode  = $report['machine_code']   ?? '—';
$equipDesc  = $report['description']    ?? '—';
$model      = $report['model']          ?? '—';
$maker      = $report['maker']          ?? '—';
$serial     = $report['serial_number']  ?? '—';
$dateRcv    = !empty($cert['instrument_date_received']) ? fmtDateLong($cert['instrument_date_received']) : '—';
$dateCal    = !empty($report['calibration_date'])       ? fmtDateLong($report['calibration_date'])       : '—';
$calibBy    = $report['calibrator']     ?? '—';
$calLoc     = !empty($cert['calibration_location'])     ? $cert['calibration_location']                  : 'See attached Calibration Certificate';
$reference  = !empty($report['reference'])              ? $report['reference']                           : 'Based on the Calibration procedure of the external calibrator.';
$displayCertNum = $cert_number ?: ($cert['certificate_number'] ?? '—');
$displayDate    = $issue_date  ? fmtDateShort($issue_date) : fmtDateShort(date('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Calibration Certificate — <?= e($displayCertNum) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    background: #e8edf2;
    font-family: 'DM Sans', sans-serif;
    min-height: 100vh;
}

.preview-toolbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: 52px;
    background: #05304f;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    z-index: 100;
    box-shadow: 0 2px 12px rgba(0,0,0,.25);
}
.preview-toolbar-left { display: flex; align-items: center; gap: 12px; }
.preview-toolbar-title { color: #fff; font-size: 14px; font-weight: 700; letter-spacing: .3px; }
.preview-toolbar-sub   { color: #a8c8e8; font-size: 12px; }
.preview-toolbar-right { display: flex; align-items: center; gap: 10px; }
.toolbar-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600;
    font-family: 'DM Sans', sans-serif; cursor: pointer; border: none;
    transition: background .15s, transform .1s; white-space: nowrap;
}
.toolbar-btn:hover  { transform: translateY(-1px); }
.toolbar-btn:active { transform: translateY(0); }
.toolbar-btn-print  { background: #16a34a; color: #fff; }
.toolbar-btn-print:hover { background: #15803d; }
.toolbar-btn-close  { background: rgba(255,255,255,.12); color: #fff; border: 1px solid rgba(255,255,255,.18); }
.toolbar-btn-close:hover { background: rgba(255,255,255,.22); }

.page-wrapper {
    padding: 72px 24px 40px;
    display: flex;
    justify-content: center;
}

.cert-page {
    width: 210mm;
    height: 297mm;
    background: #fff;
    box-shadow: 0 4px 32px rgba(5,48,79,.18);
    padding: 8mm 10mm 2mm;
    font-family: 'DM Sans', Arial, sans-serif;
    font-size: 8.5pt;
    color: #1a1a1a;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* ── FRAME ── */
.cert-frame {
    border: 2pt solid #05304f;
    border-radius: 3pt;
    flex: 0 0 auto;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* ── HEADER — no border-bottom; inset rule below handles it ── */
.cert-header-band {
    background: #fff;
    padding: 3.5mm 7mm;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}
/* Inset rule: 7mm margin each side matches cert-body-pad padding */
.cert-header-rule {
    height: 1pt;
    background: #05304f;
    margin: 0 7mm;
}
.cert-header-left { display: flex; align-items: center; gap: 3.5mm; }
.cert-header-logo { height: 20pt; width: auto; }
.cert-header-divider { width: 1pt; height: 26pt; background: #c8d8e8; margin: 0 1mm; }
.cert-company-name { font-size: 11pt; font-weight: 700; color: #05304f; font-family: 'DM Sans', Arial, sans-serif; line-height: 1.2; }
.cert-company-sub  { font-size: 7pt; color: #6b8fa8; font-style: italic; margin-top: 1pt; font-family: 'DM Sans', Arial, sans-serif; }
.cert-header-right { text-align: right; font-family: 'DM Sans', Arial, sans-serif; }
.cert-doc-info-row { display: flex; align-items: center; justify-content: flex-end; gap: 5pt; margin-bottom: 3pt; }
.cert-doc-label    { font-size: 7pt; color: #6b8fa8; white-space: nowrap; font-weight: 500; }
.cert-doc-value    { font-size: 8.5pt; font-weight: 700; color: #05304f; border-bottom: 1.5pt solid #05304f; padding: 0 6pt 1pt; min-width: 56pt; text-align: center; display: inline-block; }

/* ── BODY ── */
.cert-body-pad { padding: 0 7mm 7mm; flex: 0 0 auto; display: flex; flex-direction: column; }

.cert-title-block  { text-align: center; margin: 2.5mm 0 2.5mm; flex-shrink: 0; position: relative; }
.cert-title-block::after { content: ''; display: block; width: 30mm; height: 1.5pt; background: #05304f; margin: 2pt auto 0; border-radius: 1pt; }
.cert-title    { font-size: 16pt; font-weight: 700; font-family: 'DM Sans', Arial, sans-serif; letter-spacing: 3pt; color: #05304f; display: block; line-height: 1.2; text-transform: uppercase; }
.cert-subtitle { font-size: 7.5pt; color: #666; margin-top: 3pt; font-style: italic; font-family: 'DM Sans', Arial, sans-serif; }

/* ── INFO TABLE ── */
.cert-info-section { margin: 2mm 0; flex-shrink: 0; border-top: 1pt solid #05304f; border-bottom: 1pt solid #05304f; padding: 1.5mm 0; }
.cert-info-cols    { display: grid; grid-template-columns: 1fr 1fr; gap: 0 6mm; }
.cert-info-row     { display: flex; align-items: baseline; gap: 3pt; margin-bottom: 1.5pt; font-size: 8pt; line-height: 1.4; font-family: 'DM Sans', Arial, sans-serif; }
.cert-info-key     { font-weight: 600; width: 108pt; flex-shrink: 0; color: #05304f; font-size: 7.8pt; }
.cert-info-colon   { flex-shrink: 0; color: #05304f; }
.cert-info-val     { flex: 1; color: #1a1a1a; word-break: break-word; border-bottom: 0.4pt solid #bbb; padding-bottom: 1pt; }

/* ── SECTION TITLES ── */
.cert-section-wrap  { margin: 2mm 0 1mm; flex-shrink: 0; }
.cert-section-title {
    font-size: 8pt;
    font-weight: 700;
    font-family: 'DM Sans', Arial, sans-serif;
    color: #05304f;
    display: block;
    margin-bottom: 2pt;
    text-transform: uppercase;
    letter-spacing: .8pt;
    border-bottom: 0.8pt solid #05304f;
    padding-bottom: 1.5pt;
}
.cert-body-text { font-size: 8pt; color: #1a1a1a; line-height: 1.6; margin: 1.5pt 0; font-family: 'DM Sans', Arial, sans-serif; }

/* ── DIVIDERS ── */
.cert-rule-thin { border: none; border-top: 0.5pt solid #ddd; margin: 1.5mm 0; flex-shrink: 0; }

/* ── RESULT CHECKBOXES ── */
.cert-result-row    { display: flex; align-items: center; gap: 8mm; margin: 1mm 0; flex-shrink: 0; }
.cert-result-label  { font-size: 8pt; font-weight: 600; color: #05304f; font-family: 'DM Sans', Arial, sans-serif; }
.cert-checkbox-group { display: flex; align-items: center; gap: 7mm; }
.cert-checkbox-item  { display: flex; align-items: center; gap: 4pt; font-size: 8.5pt; font-weight: 600; font-family: 'DM Sans', Arial, sans-serif; }
.cert-checkbox       { width: 11pt; height: 11pt; border: 1.5pt solid #05304f; border-radius: 1pt; display: inline-flex; align-items: center; justify-content: center; font-size: 9pt; font-weight: 700; vertical-align: middle; flex-shrink: 0; color: #05304f; }

/* ── CONFORMITY ── */
.cert-conformity   { border-left: 2pt solid #05304f; padding: 1.5mm 3mm; margin: 1mm 0; flex-shrink: 0; }
.cert-conformity p { font-size: 7.8pt; line-height: 1.6; margin: 0 0 2pt; color: #1a1a1a; font-family: 'DM Sans', Arial, sans-serif; text-align: justify; }
.cert-conformity p:last-child { margin-bottom: 0; }

/* ── SIGNATURES ── */
.cert-sig-section  { width: 100%; flex-shrink: 0; }
.cert-sig-divider  { border: none; border-top: 1.5pt solid #05304f; margin: 0 0 2.5pt 0; }
.cert-sig-label    { font-size: 7.5pt; font-weight: 700; color: #05304f; font-family: 'DM Sans', Arial, sans-serif; margin-bottom: 2mm; letter-spacing: .3pt; text-transform: uppercase; }
.cert-sig-table    { width: 100%; border-collapse: collapse; }
.cert-sig-table td { width: 33.33%; text-align: center; vertical-align: bottom; padding: 0 3mm; }
.cert-sig-name     { font-size: 8pt; font-weight: 700; font-family: 'DM Sans', Arial, sans-serif; border-top: 1pt solid #05304f; padding-top: 2pt; margin-top: 10mm; color: #05304f; }
.cert-sig-role     { font-size: 7.5pt; color: #555; margin-top: 1pt; font-family: 'DM Sans', Arial, sans-serif; }

/* ── REVISION TABLE ── */
.cert-footer-outside { flex-shrink: 0; padding-top: 1.5mm; }
.cert-doc-ref { font-size: 6.5pt; color: #888; text-align: right; margin-bottom: 1.5mm; font-family: 'DM Sans', Arial, sans-serif; }

.cert-rev-wrapper { display: flex; align-items: stretch; gap: 6pt; }
.cert-revision-table { border-collapse: collapse; font-size: 6pt; table-layout: fixed; flex: 1; border: 1pt solid #05304f; }
.cert-revision-table th,
.cert-revision-table td { border: 0.5pt solid #a8bfcf; padding: 4pt 3pt; text-align: left; line-height: 1.3; overflow: hidden; font-family: 'DM Sans', Arial, sans-serif; color: #1a1a1a; }
.cert-revision-table th { background: #e8edf2; color: #05304f; font-weight: 700; letter-spacing: .3pt; border-color: #a8bfcf; height: 28pt; vertical-align: middle; }
.cert-revision-table td { background: #fff; }
.cert-revision-table tr:nth-child(even) td { background: #f4f7fa; }
.cert-revision-table .col-code  { width: 22pt; }
.cert-revision-table .col-date  { width: 34pt; }
.cert-revision-table .col-by    { width: 42pt; }
.cert-revision-table .col-appr1 { width: 68pt; text-align: center; }
.cert-qa-box { width: 90pt; flex-shrink: 0; display: flex; flex-direction: column; border: 1pt solid #05304f; font-size: 6pt; }
.cert-qa-box-header { background: #e8edf2; color: #05304f; font-weight: 700; text-align: center; padding: 0 3pt; height: 28pt; display: flex; align-items: center; justify-content: center; border-bottom: 0.5pt solid #a8bfcf; line-height: 1.3; font-size: 5pt; font-family: 'DM Sans', Arial, sans-serif; letter-spacing: .3pt; }
.cert-qa-box-body   { flex: 1; display: flex; align-items: center; justify-content: center; background: #fff; }
.cert-appr-sig   { width: 60pt; height: 18pt; object-fit: contain; display: block; margin: auto; }
.cert-qa-sig-img { width: 70pt; height: 28pt; object-fit: contain; display: block; margin: auto; }

@media print {
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .preview-toolbar { display: none !important; }
    body { background: #fff; }
    .page-wrapper { padding: 0; }
    @page { size: 210mm 297mm portrait; margin: 0; }
    .cert-page {
        width: 210mm !important;
        height: 297mm !important;
        box-shadow: none;
        padding: 8mm 10mm 2mm !important;
        overflow: hidden !important;
    }
    .cert-frame { overflow: hidden !important; }
}
</style>
</head>
<body>

<div class="preview-toolbar">
    <div class="preview-toolbar-left">
        <div>
            <div class="preview-toolbar-title">🖨 Certificate Preview</div>
            <div class="preview-toolbar-sub"><?= e($equipDesc) ?> — <?= e($equipCode) ?></div>
        </div>
    </div>
    <div class="preview-toolbar-right">
        <button class="toolbar-btn toolbar-btn-close" onclick="window.close()">✕ Close</button>
        <button class="toolbar-btn toolbar-btn-print" onclick="window.print()">🖨 Print Certificate</button>
    </div>
</div>

<div class="page-wrapper">
<div class="cert-page">
    <div class="cert-frame">

        <div class="cert-header-band">
            <div class="cert-header-left">
                <img src="images/shindengen-logo.png" class="cert-header-logo" onerror="this.style.display='none'">
                <div class="cert-header-divider"></div>
                <div>
                    <div class="cert-company-name">Shindengen Philippines Corp.</div>
                    <div class="cert-company-sub">Production Engineering Section</div>
                </div>
            </div>
            <div class="cert-header-right">
                <div class="cert-doc-info-row">
                    <span class="cert-doc-label">SDP Issued Certificate No.:</span>
                    <span class="cert-doc-value"><?= e($displayCertNum) ?></span>
                </div>
                <div class="cert-doc-info-row">
                    <span class="cert-doc-label">Date of Issue:</span>
                    <span class="cert-doc-value"><?= e($displayDate) ?></span>
                </div>
            </div>
        </div>
        <div class="cert-header-rule"></div>

        <div class="cert-body-pad">

        <div class="cert-title-block">
            <span class="cert-title">Calibration Certificate</span>
            <div class="cert-subtitle">Reference: PC2-9710 Calibration Control Procedure</div>
        </div>

        <div class="cert-info-section">
            <div class="cert-info-cols">
                <div>
                    <div class="cert-info-row"><span class="cert-info-key">Type of Calibration</span><span class="cert-info-colon">:</span><span class="cert-info-val"><?= e($calType) ?></span></div>
                    <div class="cert-info-row"><span class="cert-info-key">Equipment Code</span><span class="cert-info-colon">:</span><span class="cert-info-val"><?= e($equipCode) ?></span></div>
                    <div class="cert-info-row"><span class="cert-info-key">Equipment Description</span><span class="cert-info-colon">:</span><span class="cert-info-val"><?= e($equipDesc) ?></span></div>
                    <div class="cert-info-row"><span class="cert-info-key">Model</span><span class="cert-info-colon">:</span><span class="cert-info-val"><?= e($model) ?></span></div>
                    <div class="cert-info-row"><span class="cert-info-key">Manufacturer's Name</span><span class="cert-info-colon">:</span><span class="cert-info-val"><?= e($maker) ?></span></div>
                    <div class="cert-info-row"><span class="cert-info-key">Serial No.</span><span class="cert-info-colon">:</span><span class="cert-info-val"><?= e($serial) ?></span></div>
                </div>
                <div>
                    <div class="cert-info-row"><span class="cert-info-key">Date Received (PES-Calibration)</span><span class="cert-info-colon">:</span><span class="cert-info-val"><?= e($dateRcv) ?></span></div>
                    <div class="cert-info-row"><span class="cert-info-key">Date Calibrated</span><span class="cert-info-colon">:</span><span class="cert-info-val"><?= e($dateCal) ?></span></div>
                    <div class="cert-info-row"><span class="cert-info-key">Calibrated By</span><span class="cert-info-colon">:</span><span class="cert-info-val"><?= e($calibBy) ?></span></div>
                    <div class="cert-info-row"><span class="cert-info-key">Calibration Location</span><span class="cert-info-colon">:</span><span class="cert-info-val"><?= e($calLoc) ?></span></div>
                </div>
            </div>
        </div>

        <div class="cert-section-wrap">
            <div class="cert-section-title">Calibration Method</div>
            <p class="cert-body-text"><?= e($reference) ?></p>
        </div>
        <hr class="cert-rule-thin">
        <div class="cert-section-wrap">
            <div class="cert-section-title">Reference Standard(s) Used During Calibration</div>
            <p class="cert-body-text">See attached last page of test data report for the Reference Standard(s) used during calibration.</p>
        </div>
        <hr class="cert-rule-thin">
        <div class="cert-section-wrap">
            <div class="cert-section-title">Result of Calibration</div>
            <div class="cert-result-row">
                <span class="cert-result-label">Put Check:</span>
                <div class="cert-checkbox-group">
                    <div class="cert-checkbox-item"><div class="cert-checkbox"><?= $isPassed ? '✓' : '' ?></div><span>PASS</span></div>
                    <div class="cert-checkbox-item"><div class="cert-checkbox"><?= !$isPassed ? '✓' : '' ?></div><span>FAIL</span></div>
                </div>
            </div>
        </div>
        <hr class="cert-rule-thin">
        <div class="cert-section-wrap">
            <div class="cert-section-title">Statement of Conformity or Non-Conformity</div>
            <div class="cert-conformity">
                <p>Shindengen Philippines Corp. certifies the above instruments have been calibrated using standards in compliance. The test measurement results meet the requirement that the products or process needs.</p>
                <p>The tolerances are either from the accuracy from the manufacturer or tolerances that were evaluated and assessed by the Company. Standard equipment used for the calibration of the above instruments are traceable to the Philippines Accreditation Bureau accredited calibration laboratories, or other associated foreign national standard laboratories such as National Institute of Standard Technology, JCSS, etc…</p>
                <p>In addition, external calibration are done by certified calibration engineer or technician with certification from the manufacturer or external calibration laboratories.</p>
            </div>
        </div>

        <!-- ↓ ADJUST this margin-bottom value to move signatories up or down -->
        <div style="margin-bottom: 15mm;"></div>

        <div class="cert-sig-section">
            <hr class="cert-sig-divider">
            <div class="cert-sig-label">Prepared / Noted / Approved by:</div>
            <table class="cert-sig-table">
                <tr>
                    <td><div style="height:18mm;"></div><div class="cert-sig-name"><?= e($engineer_name) ?></div><div class="cert-sig-role"><?= e($engineer_title) ?></div></td>
                    <td><div style="height:18mm;"></div><div class="cert-sig-name"><?= e($manager_name) ?></div><div class="cert-sig-role"><?= e($manager_title) ?></div></td>
                    <td><div style="height:18mm;"></div><div class="cert-sig-name"><?= e($concern_name) ?></div><div class="cert-sig-role"><?= e($concern_title) ?></div></td>
                </tr>
            </table>
        </div>

        </div><!-- /cert-body-pad -->
    </div><!-- /cert-frame -->

    <!-- Revision Table — outside the border -->
    <div class="cert-footer-outside">
        <div class="cert-doc-ref">PES.CAL.08004.R3</div>
        <div class="cert-rev-wrapper">
            <table class="cert-revision-table">
                <thead>
                    <tr>
                        <th class="col-code">Rev. Code</th>
                        <th class="col-date">Rev. Date</th>
                        <th class="col-by">Revised by:</th>
                        <th>Nature/Description of Revision</th>
                        <th class="col-appr1">Approved by:<br>(Section Manager)</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $apprSigs = ['Images/Signature-1.png', 'Images/Signature-3.png'];
                $filteredRevs = array_values(array_filter($revisions, fn($r) => !empty($r['code']) || !empty($r['desc'])));
                if (empty($filteredRevs)) {
                    echo '<tr><td></td><td></td><td></td><td></td><td></td></tr>';
                } else {
                    foreach ($filteredRevs as $ri => $rev): ?>
                    <tr>
                        <td><?= e($rev['code'] ?? '') ?></td>
                        <td><?= e($rev['date'] ?? '') ?></td>
                        <td><?= e($rev['by']   ?? '') ?></td>
                        <td><?= e($rev['desc'] ?? '') ?></td>
                        <td style="text-align:center;vertical-align:middle;padding:0;">
                            <?php if (!empty($apprSigs[$ri])): ?>
                                <img src="<?= e($apprSigs[$ri]) ?>" class="cert-appr-sig" onerror="this.style.display='none'">
                            <?php else: ?>
                                <?= e($rev['appr1'] ?? '') ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; } ?>
                </tbody>
            </table>
            <div class="cert-qa-box">
                <div class="cert-qa-box-header">Approved by:<br>QA Section Mgr. / Dept. Mgr.</div>
                <div class="cert-qa-box-body">
                    <img src="Images/Signature-4.png" class="cert-qa-sig-img" onerror="this.style.display='none'">
                </div>
            </div>
        </div>
    </div><!-- /cert-footer-outside -->

</div><!-- /cert-page -->
</div><!-- /page-wrapper -->
</body>
</html>