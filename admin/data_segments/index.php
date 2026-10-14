<?php
declare(strict_types=1);

// CSV-only document segment admin; shared MBS UniWise PHP session (no second login).
require_once __DIR__ . '/../includes/auth.php';
mbs_admin_require_login();

$cfgFile = __DIR__ . '/config.php';
if (!is_file($cfgFile)) {
    http_response_code(503);
    exit('ยังไม่ได้ติดตั้ง Data Segment: โปรดคัดลอก config.example.php เป็น config.php และตั้งค่า');
}
$cfg = require $cfgFile;
$csv = (string)($cfg['csv_path'] ?? '');
if (!$csv || !is_file($csv)) {
    http_response_code(503);
    exit('ไม่พบ CSV ตาม csv_path ใน config.php กรุณาตรวจสอบ path บนเซิร์ฟเวอร์');
}
$realCsv = realpath($csv);
if ($realCsv === false || strtolower(pathinfo($realCsv, PATHINFO_EXTENSION)) !== 'csv') {
    http_response_code(503);
    exit('ตำแหน่งไฟล์ CSV ไม่ถูกต้อง');
}
$requiredColumns = ['segment_id', 'content'];
$defaultColumns = ['segment_id', 'evidence_id', 'document_id', 'document_name', 'position', 'word_count', 'tokens', 'content'];
function dsContains(string $haystack, string $needle): bool {
    if (function_exists('mb_stripos')) return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
    return stripos($haystack, $needle) !== false;
}
function dsPreview(string $s): string {
    if (function_exists('mb_strimwidth')) return mb_strimwidth($s, 0, 280, '…', 'UTF-8');
    $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($chars)) return substr($s, 0, 250);
    return count($chars) > 200 ? implode('', array_slice($chars, 0, 200)) . '…' : $s;
}
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function readCsv(string $path): array {
    $f = fopen($path, 'rb');
    if (!$f) throw new RuntimeException('อ่านไฟล์ CSV ไม่ได้');
    try {
        $header = fgetcsv($f, 0, ',', '"', '');
        if (!$header) throw new RuntimeException('ไฟล์ CSV ไม่มี header');
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        if (count(array_unique($header)) !== count($header)) throw new RuntimeException('ชื่อคอลัมน์ซ้ำ');
        foreach (['segment_id', 'content'] as $name) if (!in_array($name, $header, true)) throw new RuntimeException('ขาดคอลัมน์ '.$name);
        $rows = [];
        while (($values = fgetcsv($f, 0, ',', '"', '')) !== false) {
            if ($values === [null]) continue;
            if (count($values) !== count($header)) throw new RuntimeException('จำนวนคอลัมน์ใน CSV ไม่ตรงกัน');
            $rows[] = array_combine($header, $values);
        }
        checkRows($rows);
        return [$header, $rows];
    } finally { fclose($f); }
}
function checkRows(array $rows): void {
    $seen = [];
    foreach ($rows as $i => $r) {
        $id = trim((string)($r['segment_id'] ?? ''));
        if ($id === '') throw new RuntimeException('พบ segment_id ว่าง (แถว '.($i+2).')');
        if (isset($seen[$id])) throw new RuntimeException('segment_id ซ้ำ: '.$id);
        $seen[$id] = true;
        if (trim((string)($r['content'] ?? '')) === '') throw new RuntimeException('content ว่าง: '.$id);
    }
}
function saveCsv(string $path, array $columns, array $rows): void {
    checkRows($rows);
    $dir = dirname($path);
    if (!is_writable($dir) || !is_writable($path)) throw new RuntimeException('ไม่มีสิทธิ์เขียน CSV หรือโฟลเดอร์');
    $tmp = tempnam($dir, '.segments-');
    if ($tmp === false) throw new RuntimeException('สร้างไฟล์ชั่วคราวไม่ได้');
    try {
        $fp = fopen($tmp, 'wb');
        if (!$fp) throw new RuntimeException('เปิดไฟล์ชั่วคราวไม่ได้');
        try {
            fwrite($fp, "\xEF\xBB\xBF");
            fputcsv($fp, $columns, ',', '"', '');
            foreach ($rows as $r) fputcsv($fp, array_map(fn($key) => (string)($r[$key] ?? ''), $columns), ',', '"', '');
            fflush($fp);
        } finally { fclose($fp); }
        // Create backup before atomic replacement; fail closed if backup cannot be created.
        $backupDir = $dir . '/segment_backups';
        if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true)) throw new RuntimeException('สร้างโฟลเดอร์สำรองไม่ได้');
        $backup = $backupDir . '/dify_segments_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.csv';
        if (!copy($path, $backup)) throw new RuntimeException('สำรองไฟล์ไม่สำเร็จ');
        @chmod($tmp, 0640);
        if (!rename($tmp, $path)) throw new RuntimeException('แทนที่ CSV ไม่สำเร็จ');
    } finally { if (is_file($tmp)) unlink($tmp); }
}
function withWriteLock(string $path, callable $modify): void {
    $lock = fopen(dirname($path).'/.segments_edit.lock', 'c');
    if (!$lock) throw new RuntimeException('เปิดไฟล์ล็อกไม่ได้');
    try {
        if (!flock($lock, LOCK_EX)) throw new RuntimeException('ล็อกไฟล์ไม่ได้');
        [$cols,$rows] = readCsv($path);
        [$newCols,$newRows] = $modify($cols, $rows);
        saveCsv($path, $newCols, $newRows);
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['segment_csrf'])) $_SESSION['segment_csrf'] = bin2hex(random_bytes(24));
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        if (!hash_equals($_SESSION['segment_csrf'], (string)($_POST['csrf'] ?? ''))) throw new RuntimeException('คำขอไม่ผ่านการตรวจสอบ CSRF');
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'export') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="dify_segments_'.date('Ymd_His').'.csv"');
            header('X-Content-Type-Options: nosniff');
            readfile($realCsv); exit;
        }
        if ($action === 'import') {
            $upload = $_FILES['csv_file'] ?? null;
            if (!$upload || $upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 10*1024*1024) throw new RuntimeException('ไฟล์นำเข้าต้องเป็น CSV ขนาดไม่เกิน 10 MB');
            if (strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION)) !== 'csv' || !is_uploaded_file($upload['tmp_name'])) throw new RuntimeException('รองรับเฉพาะ .csv');
            [$importCols, $importRows] = readCsv($upload['tmp_name']);
            withWriteLock($realCsv, function(array $currentCols, array $currentRows) use ($importCols, $importRows) {
                if ($importCols !== $currentCols) throw new RuntimeException('คอลัมน์ใน CSV นำเข้าไม่ตรงกับไฟล์เดิม (รวมลำดับคอลัมน์)');
                return [$currentCols, $importRows];
            });
        } elseif ($action === 'preview_metadata') {
            // The browser only receives counts. API credentials stay on the PHP server.
            $content = (string)($_POST['content'] ?? '');
            [$words, $tokens, $encoding, $model] = dsCountMetadata($content, $cfg);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode(['ok' => true, 'word_count' => $words, 'tokens' => $tokens, 'tokenizer' => $encoding, 'model' => $model], JSON_UNESCAPED_UNICODE);
            exit;
        } elseif ($action === 'save') {
            $oldId = trim((string)($_POST['old_id'] ?? ''));
            $content = (string)($_POST['content'] ?? '');
            $docChoice = trim((string)($_POST['document_choice'] ?? ''));
            $newDocumentName = trim((string)($_POST['new_document_name'] ?? ''));
            if (trim($content) === '') throw new RuntimeException('โปรดกรอกเนื้อหาข้อมูลคู่มือการเรียน');
            if (strlen($content) > 200000) throw new RuntimeException('เนื้อหายาวเกิน 200 KB โปรดแบ่งเป็น Segment ย่อย');
            withWriteLock($realCsv, function(array $cols, array $rows) use ($oldId, $content, $docChoice, $newDocumentName, $cfg) {
                // Check originals inside the CSV write lock to avoid races with other editors.
                $oldRow = null;
                $oldIdx = -1;
                foreach ($rows as $idx => $row) {
                    if (($row['segment_id'] ?? '') === $oldId && $oldId !== '') {
                        $oldIdx = $idx;
                        $oldRow = $row;
                        break;
                    }
                }
                if ($oldId !== '' && $oldRow === null) throw new RuntimeException('ไม่พบ Segment ที่ต้องการแก้ไข');
                $documents = dsDocuments($rows);
                if ($docChoice === '__new__') {
                    if ($newDocumentName === '' || strlen($newDocumentName) > 240) throw new RuntimeException('ระบุชื่อเอกสารต้นฉบับใหม่ (ไม่เกิน 240 bytes)');
                    foreach ($documents as $document) {
                        if (dsSameName($newDocumentName, $document['document_name'])) throw new RuntimeException('ชื่อเอกสารมีอยู่แล้ว กรุณาเลือกเอกสารเดิมจากรายการ');
                    }
                    $documentId = selfId();
                    $documentName = $newDocumentName;
                } elseif ($docChoice !== '' && isset($documents[$docChoice])) {
                    $documentId = $docChoice;
                    $documentName = $documents[$docChoice]['document_name'];
                } else {
                    throw new RuntimeException('โปรดเลือกเอกสารต้นฉบับที่มีอยู่ หรือสร้างเอกสารต้นฉบับใหม่');
                }
                $sameContent = $oldRow !== null && (string)($oldRow['content'] ?? '') === $content;
                if ($sameContent) {
                    // Do not silently rewrite historical evaluated metadata.
                    $words = (string)($oldRow['word_count'] ?? '');
                    $tokens = (string)($oldRow['tokens'] ?? '');
                } else {
                    [$words, $tokens] = dsCountMetadata($content, $cfg);
                }
                $r = $oldRow ?? array_fill_keys($cols, '');
                if ($oldRow === null) {
                    $r['segment_id'] = selfId();
                    if (in_array('evidence_id', $cols, true)) $r['evidence_id'] = 'document_segment:' . $r['segment_id'];
                }
                $moved = $oldRow !== null && (string)($oldRow['document_id'] ?? '') !== $documentId;
                if (in_array('document_id', $cols, true)) $r['document_id'] = $documentId;
                if (in_array('document_name', $cols, true)) $r['document_name'] = $documentName;
                if (in_array('position', $cols, true) && ($oldRow === null || $moved)) $r['position'] = (string)dsNextPosition($rows, $documentId);
                if (in_array('word_count', $cols, true)) $r['word_count'] = (string)$words;
                if (in_array('tokens', $cols, true)) $r['tokens'] = (string)$tokens;
                $r['content'] = $content;
                if ($oldRow === null) $rows[] = $r;
                else $rows[$oldIdx] = $r;
                return [$cols, $rows];
            });
        } elseif ($action === 'delete') {
            $id = (string)($_POST['id'] ?? '');
            withWriteLock($realCsv, function(array $cols, array $rows) use ($id) {
                $new = array_values(array_filter($rows, fn($row) => $row['segment_id'] !== $id));
                if (count($new) === count($rows)) throw new RuntimeException('ไม่พบ Segment ที่ต้องการลบ');
                return [$cols, $new];
            });
        } else throw new RuntimeException('คำสั่งไม่ถูกต้อง');
        $_SESSION['segment_flash'] = 'บันทึกข้อมูลแล้ว (โปรดตรวจสอบ Retriever ว่าโหลดข้อมูลใหม่สำเร็จ)';
        header('Location: index.php'); exit;
    } catch (Throwable $exc) {
        $error = $exc->getMessage();
        if (($_POST['action'] ?? '') === 'preview_metadata') {
            http_response_code(422);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
// Document choices are always derived from the current CSV (never from user-submitted names).
function dsDocuments(array $rows): array {
    $docs = [];
    foreach ($rows as $r) {
        $id = trim((string)($r['document_id'] ?? ''));
        $name = trim((string)($r['document_name'] ?? ''));
        if ($id === '' || $name === '') continue;
        if (isset($docs[$id]) && $docs[$id]['document_name'] !== $name) {
            throw new RuntimeException('พบ document_id เดียวกันแต่ชื่อเอกสารไม่ตรงกัน: '. $id);
        }
        $docs[$id] = ['document_id' => $id, 'document_name' => $name];
    }
    uasort($docs, fn($a, $b) => strcmp($a['document_name'], $b['document_name']));
    return $docs;
}
function dsSameName(string $a, string $b): bool {
    if (function_exists('mb_strtolower')) return mb_strtolower($a, 'UTF-8') === mb_strtolower($b, 'UTF-8');
    return strcasecmp($a, $b) === 0;
}
function dsNextPosition(array $rows, string $documentId): int {
    $max = 0;
    foreach ($rows as $r) {
        if (trim((string)($r['document_id'] ?? '')) !== $documentId) continue;
        $p = trim((string)($r['position'] ?? ''));
        if ($p !== '' && ctype_digit($p)) $max = max($max, (int)$p);
    }
    return $max + 1;
}
// Do not guess tokens. Both Thai word_count and true model-token counts come from Retriever.
function dsCountMetadata(string $content, array $cfg): array {
    if ($content === '') throw new RuntimeException('กรุณากรอกเนื้อหาก่อนคำนวณ');
    $url = trim((string)($cfg['metadata_api_url'] ?? ''));
    $key = trim((string)($cfg['metadata_api_key'] ?? ''));
    if ($url === '' || $key === '') throw new RuntimeException('ยังไม่ได้เชื่อม Tokenizer กรุณาตั้ง metadata_api_url และ metadata_api_key ใน config.php');
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) throw new RuntimeException('metadata_api_url ไม่ใช่ HTTP(S) URL');
    $payload = json_encode(['content' => $content], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer '.$key];
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if (!$ch) throw new RuntimeException('ไม่สามารถเริ่มการติดต่อ Tokenizer');
        try {
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false]);
            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($response === false) throw new RuntimeException('ไม่สามารถเชื่อม Tokenizer ได้: '.curl_error($ch));
        } finally { curl_close($ch); }
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $payload, 'timeout' => 15, 'ignore_errors' => true, 'follow_location' => 0]]);
        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) throw new RuntimeException('ไม่สามารถเชื่อม Tokenizer ได้ โปรดตรวจ metadata_api_url / allow_url_fopen');
        $httpCode = 0;
        foreach (($http_response_header ?? []) as $h) if (preg_match('/^HTTP\/\S+\s+(\d+)/i', $h, $m)) $httpCode = (int)$m[1];
    }
    if ($httpCode !== 200) throw new RuntimeException('Tokenizer ไม่พร้อมใช้งาน (HTTP '. $httpCode .') ไม่บันทึกค่าประมาณ');
    $result = json_decode((string)$response, true);
    if (!is_array($result) || !is_int($result['word_count'] ?? null) || !is_int($result['tokens'] ?? null) || ($result['word_count'] ?? -1) < 0 || ($result['tokens'] ?? -1) < 0 || !is_string($result['tokenizer'] ?? null) || !is_string($result['model'] ?? null)) {
        throw new RuntimeException('Tokenizer ส่งข้อมูลไม่ครบหรือไม่ถูกต้อง');
    }
    return [$result['word_count'], $result['tokens'], $result['tokenizer'], $result['model']];
}
function selfId(): string {
    $hex = bin2hex(random_bytes(16));
    return sprintf('%s-%s-4%s-%s%s-%s', substr($hex,0,8), substr($hex,8,4), substr($hex,13,3), dechex((hexdec($hex[16]) & 3) | 8), substr($hex,17,3), substr($hex,20,12));
}
try { [$columns,$rows] = readCsv($realCsv); }
catch (Throwable $exc) { http_response_code(500); exit('CSV ไม่ถูกต้อง: '.e($exc->getMessage())); }
$q = trim((string)($_GET['q'] ?? ''));
$filtered = $q === '' ? $rows : array_values(array_filter($rows, fn($r) => dsContains(implode(' ', $r), $q)));
$editId = (string)($_GET['edit'] ?? '');
$editing = null;
foreach ($rows as $r) if ($r['segment_id'] === $editId) { $editing = $r; break; }
$documents = dsDocuments($rows);
$formOpen = isset($_GET['new']) || $editing !== null || (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'save' && $error !== '');
$selectedDocumentId = (string)($_POST['document_choice'] ?? ($editing['document_id'] ?? (count($documents) === 1 ? (string)array_key_first($documents) : '')));
$formContent = (string)($_POST['content'] ?? ($editing['content'] ?? ''));
$selectedNewDocName = (string)($_POST['new_document_name'] ?? '');
$nextPositions = [];
foreach ($documents as $id => $document) $nextPositions[$id] = dsNextPosition($rows, (string)$id);
$shownOldId = trim((string)($_POST['old_id'] ?? ''));
if ($shownOldId !== '' && $editing === null) {
    foreach ($rows as $r) if (($r['segment_id'] ?? '') === $shownOldId) { $editing = $r; break; }
}

$activeMenu = 'segments'; $basePath = '../';
$flash = $_SESSION['segment_flash'] ?? ''; unset($_SESSION['segment_flash']);
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Document Data Segments | MBS UniWise</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="../assets/admin.css" rel="stylesheet">
<style>
.ds-shell{max-width:1320px;margin:auto}.ds-card{background:white;border:1px solid #e3ebf3;border-radius:18px;padding:22px;margin-bottom:18px}.ds-hero{background:linear-gradient(120deg,#07396c,#0c78b6);color:white;padding:28px;border-radius:20px;margin-bottom:20px}.ds-hero h1{font-weight:700;font-size:clamp(22px,3vw,32px)}.ds-table{font-size:13px}.ds-preview{max-width:520px;white-space:pre-wrap;word-break:break-word}.ds-content{min-height:230px}.ds-readonly{background:#f4f7fa!important}.ds-counter{font-size:12px;min-height:18px}.ds-meta{font-size:12px;color:#6b7d8c}.ds-main{padding:24px;min-width:0;width:100%}@media(max-width:700px){.ds-main{padding:12px}.ds-card{padding:14px}}
</style></head><body><div class="admin-layout"><?php include '../includes/sidebar.php'; ?>
<div class="main-shell"><header class="topbar"><button class="mobile-menu-btn" id="mobileMenuBtn" type="button"><i class="bi bi-list"></i></button><div class="topbar-title"><span class="topbar-kicker">MBS UNIWISE ADMIN</span><strong>จัดการ Data Segment</strong></div><div class="topbar-status"><span class="status-dot"></span>Document RAG</div></header>
<main class="content-area ds-main"><div class="ds-shell"><section class="ds-hero"><h1><i class="bi bi-file-earmark-text"></i> จัดการ Document Data Segments</h1><p class="mb-0">เพิ่ม แก้ไข ลบ ค้นหา และนำเข้า/ส่งออก CSV โดยไม่ใช้ MySQL</p></section>
<?php if ($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
<?php if ($flash): ?><div class="alert alert-success"><?=e($flash)?></div><?php endif; ?>
<div class="ds-card"><div class="d-flex flex-wrap justify-content-between gap-2 align-items-center"><div><h5 class="mb-1">ข้อมูลทั้งหมด <?=count($rows)?> Segments</h5><div class="ds-meta">ไฟล์: <?=e(basename($realCsv))?> | แก้ไขไฟล์ CSV ต้นฉบับโดยตรง (มี Backup ก่อนเขียน)</div></div><div class="d-flex flex-wrap gap-2"><a href="?new=1" class="btn btn-primary"><i class="bi bi-plus-lg"></i> เพิ่ม Segment</a><form method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['segment_csrf'])?>"><button class="btn btn-outline-primary" name="action" value="export"><i class="bi bi-download"></i> ส่งออก CSV</button></form></div></div>
<hr><form method="get" class="row g-2"><div class="col-md-9"><input class="form-control" name="q" placeholder="ค้นหา Segment ID, ชื่อเอกสาร หรือข้อความ" value="<?=e($q)?>"></div><div class="col-md-3"><button class="btn btn-outline-secondary w-100">ค้นหา</button></div></form></div>
<?php if ($formOpen): ?>
<div class="ds-card" id="segment-form">
    <h5 class="mb-1"><?= $editing ? 'แก้ไข Segment' : 'เพิ่ม Segment ใหม่' ?></h5>
    <p class="ds-meta">เลือกเอกสารต้นฉบับและกรอกเฉพาะเนื้อหา ระบบสร้างรหัสและข้อมูลกำกับให้อัตโนมัติ</p>
    <form method="post" id="segmentSaveForm">
        <input type="hidden" name="csrf" value="<?=e($_SESSION['segment_csrf'])?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="old_id" value="<?=e($editing['segment_id']??$shownOldId)?>">
        <div class="row g-3">
            <div class="col-lg-8">
                <label for="document-choice" class="form-label fw-semibold">เอกสารต้นฉบับ <span class="text-danger">*</span></label>
                <select id="document-choice" name="document_choice" class="form-select" required>
                    <option value="">-- เลือกเอกสารต้นฉบับ --</option>
                    <?php foreach ($documents as $doc): ?>
                        <option value="<?=e($doc['document_id'])?>" <?= $selectedDocumentId === $doc['document_id'] ? 'selected' : '' ?>><?=e($doc['document_name'])?></option>
                    <?php endforeach; ?>
                    <option value="__new__" <?= $selectedDocumentId === '__new__' ? 'selected' : '' ?>>+ เพิ่มเอกสารต้นฉบับใหม่</option>
                </select>
                <div class="form-text">ระบบจะใช้ document_id และ document_name ของเอกสารที่เลือก โดยไม่ต้องกรอกเอง</div>
            </div>
            <div class="col-lg-4" id="new-document-wrap" hidden>
                <label for="new-document-name" class="form-label fw-semibold">ชื่อเอกสารใหม่ <span class="text-danger">*</span></label>
                <input class="form-control" id="new-document-name" name="new_document_name" maxlength="180" value="<?=e($selectedNewDocName)?>" placeholder="เช่น ข้อมูลคู่มือการเรียน 2570.md">
                <div class="form-text">ระบบสร้าง Document ID ใหม่อัตโนมัติ</div>
            </div>
            <div class="col-12">
                <label for="segment-content" class="form-label fw-semibold">เนื้อหาข้อมูล (content) <span class="text-danger">*</span></label>
                <textarea id="segment-content" class="form-control ds-content" name="content" required placeholder="วางข้อความจากเอกสารต้นฉบับที่ตรวจสอบความถูกต้องแล้ว..." maxlength="100000"><?=e($formContent)?></textarea>
                <div class="form-text">กรอกข้อความที่ต้องการให้ Chatbot ค้นคืนจากเอกสารจริง (ไม่ต้องพิมพ์ตัวเลขหรือรหัสกำกับเอง)</div>
            </div>
            <div class="col-12"><hr class="my-0"><h6 class="mt-3 mb-0">ข้อมูลกำกับที่ระบบจัดการให้</h6></div>
            <div class="col-lg-6">
                <label class="form-label" for="display-segment-id">Segment ID</label>
                <input class="form-control ds-readonly" id="display-segment-id" value="<?=e($editing['segment_id']??'ไม่ต้องกรอก — ระบบสร้างให้อัตโนมัติ')?>" readonly>
                <div class="form-text text-primary">ไม่ต้องกรอก ระบบสร้าง UUID อัตโนมัติเมื่อบันทึก</div>
            </div>
            <div class="col-lg-6">
                <label class="form-label" for="display-evidence-id">Evidence ID</label>
                <input class="form-control ds-readonly" id="display-evidence-id" value="<?=e($editing['evidence_id']??'ไม่ต้องกรอก — ระบบสร้างจาก Segment ID')?>" readonly>
            </div>
            <div class="col-lg-6">
                <label class="form-label" for="display-document-id">Document ID</label>
                <input class="form-control ds-readonly" id="display-document-id" readonly>
            </div>
            <div class="col-lg-6">
                <label class="form-label" for="display-position">Position (ลำดับในเอกสาร)</label>
                <input class="form-control ds-readonly" id="display-position" readonly>
            </div>
            <div class="col-lg-6">
                <label class="form-label" for="display-words">Word Count (จำนวนคำภาษาไทย)</label>
                <input class="form-control ds-readonly" id="display-words" value="<?=e($editing['word_count']??'')?>" readonly placeholder="คำนวณอัตโนมัติ">
            </div>
            <div class="col-lg-6">
                <label class="form-label" for="display-tokens">Tokens (ตาม Embedding Tokenizer)</label>
                <input class="form-control ds-readonly" id="display-tokens" value="<?=e($editing['tokens']??'')?>" readonly placeholder="คำนวณอัตโนมัติ">
            </div>
            <div class="col-12"><div class="ds-counter" id="metadata-status" role="status" aria-live="polite">ระบบจะนับคำและ Token หลังกรอกเนื้อหา (ใช้ Retriever API ไม่ใช้ค่าประมาณ)</div></div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">บันทึก CSV</button>
            <a class="btn btn-outline-secondary" href="index.php">ยกเลิก</a>
        </div>
    </form>
</div>
<?php endif; ?>
<div class="ds-card"><h5>นำเข้า CSV (แทนที่ข้อมูลทั้งหมด)</h5><p class="ds-meta">ต้องมีชื่อและลำดับคอลัมน์ตรงกับ CSV เดิม ระบบตรวจ ID ซ้ำและสำรองข้อมูลก่อนแทนที่ ใช้ไฟล์ .csv UTF-8 ไม่ใช่ .xlsx</p><form method="post" enctype="multipart/form-data" onsubmit="return confirm('ยืนยันแทนที่ข้อมูลทั้งหมดด้วยไฟล์ที่นำเข้า?')"><input type="hidden" name="csrf" value="<?=e($_SESSION['segment_csrf'])?>"><div class="d-flex flex-wrap gap-2"><input type="file" name="csv_file" accept=".csv,text/csv" class="form-control" style="max-width:420px" required><button class="btn btn-outline-danger" name="action" value="import">นำเข้าและแทนที่</button></div></form></div>
<div class="ds-card"><h5>รายการ Segments (<?=count($filtered)?>)</h5><div class="table-responsive"><table class="table table-hover align-middle ds-table"><thead><tr><th>Segment ID</th><th>เอกสาร</th><th>เนื้อหา</th><th>จัดการ</th></tr></thead><tbody><?php foreach (array_slice($filtered,0,500) as $r): ?><tr><td style="max-width:210px;word-break:break-all"><?=e($r['segment_id'])?></td><td><?=e($r['document_name']??'-')?></td><td><div class="ds-preview"><?=e(dsPreview((string)$r['content']))?></div></td><td><div class="d-flex gap-1"><a class="btn btn-sm btn-outline-primary" href="?edit=<?=urlencode($r['segment_id'])?>">แก้ไข</a><form method="post" onsubmit="return confirm('ยืนยันลบ Segment นี้?')"><input type="hidden" name="csrf" value="<?=e($_SESSION['segment_csrf'])?>"><input type="hidden" name="id" value="<?=e($r['segment_id'])?>"><button class="btn btn-sm btn-outline-danger" name="action" value="delete">ลบ</button></form></div></td></tr><?php endforeach;?></tbody></table></div><?php if(count($filtered)>500): ?><div class="alert alert-info">แสดง 500 รายการแรก โปรดค้นหาเพื่อจำกัดผลลัพธ์</div><?php endif;?></div></div></main></div></div>
<script>
const segmentDocuments = <?=json_encode($documents, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
const segmentPositions = <?=json_encode($nextPositions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
const segmentExisting = <?=json_encode($editing === null ? null : ['document_id' => (string)($editing['document_id'] ?? ''), 'position' => (string)($editing['position'] ?? ''), 'content' => (string)($editing['content'] ?? '')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
(()=>{
 const choice=document.getElementById('document-choice'); if(!choice) return;
 const content=document.getElementById('segment-content'), status=document.getElementById('metadata-status');
 const words=document.getElementById('display-words'), tokens=document.getElementById('display-tokens');
 const id=document.getElementById('display-document-id'), pos=document.getElementById('display-position');
 const newWrap=document.getElementById('new-document-wrap'), newName=document.getElementById('new-document-name');
 const form=document.getElementById('segmentSaveForm');
 const existingText=segmentExisting?.content??null;
 let delay,sequence=0;
 const refreshDoc=()=>{
  const value=choice.value, isNew=value==='__new__';
  newWrap.hidden=!isNew;newName.required=isNew;
  if(isNew){id.value='สร้าง Document ID ใหม่เมื่อบันทึก';pos.value='1';return}
  id.value=segmentDocuments[value]?.document_id || 'เลือกเอกสารต้นฉบับก่อน';
  pos.value=segmentExisting && segmentExisting.document_id===value ? segmentExisting.position : (segmentPositions[value]??'');
 };
 const count=async()=>{
  const n=++sequence;
  const txt=content.value;
  if(!txt.trim()){words.value='';tokens.value='';status.textContent='กรอกเนื้อหาเพื่อคำนวณจำนวนคำและ Token';return}
  if(existingText!==null && txt===existingText){
   status.textContent='เนื้อหายังไม่เปลี่ยน: คงจำนวนคำและ Token เดิมไว้';
   words.value=<?=json_encode((string)($editing['word_count']??''))?>;
   tokens.value=<?=json_encode((string)($editing['tokens']??''))?>;
   return;
  }
  words.value='';tokens.value='';status.textContent='กำลังคำนวณด้วย Tokenizer จริง...';
  const body=new FormData();body.set('csrf',form.querySelector('[name=csrf]').value);body.set('action','preview_metadata');body.set('content',txt);
  try{
   const res=await fetch('index.php',{method:'POST',body,credentials:'same-origin'});
   const json=await res.json();if(n!==sequence)return;
   if(!res.ok||!json.ok)throw new Error(json.error||'ไม่สามารถคำนวณได้');
   words.value=json.word_count;tokens.value=json.tokens;
   status.textContent='นับด้วย PyThaiNLP newmm / '+json.tokenizer+' ('+json.model+')';status.classList.remove('text-danger');
  }catch(error){if(n!==sequence)return;status.textContent='ยังคำนวณไม่ได้: '+error.message;status.classList.add('text-danger');}
 };
 choice.addEventListener('change',refreshDoc);
 newName.addEventListener('input',()=>{if(choice.value==='__new__')refreshDoc()});
 content.addEventListener('input',()=>{sequence++;clearTimeout(delay);delay=setTimeout(count,650)});
 refreshDoc();if(content.value.trim() && existingText===null)count();
})();
</script>
<script>
document.addEventListener('DOMContentLoaded',()=>{const s=document.getElementById('sidebar'),o=document.getElementById('sidebarOverlay');if(localStorage.getItem('mbsSidebarCollapsed')==='1'&&innerWidth>=992)document.body.classList.add('sidebar-collapsed');document.getElementById('sidebarToggle')?.addEventListener('click',()=>{document.body.classList.toggle('sidebar-collapsed');localStorage.setItem('mbsSidebarCollapsed',document.body.classList.contains('sidebar-collapsed')?'1':'0')});document.getElementById('mobileMenuBtn')?.addEventListener('click',()=>{document.body.classList.toggle('sidebar-open')});o?.addEventListener('click',()=>document.body.classList.remove('sidebar-open'))});
</script></body></html>
