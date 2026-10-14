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
        } elseif ($action === 'save') {
            $oldId = (string)($_POST['old_id'] ?? '');
            withWriteLock($realCsv, function(array $cols, array $rows) use ($oldId) {
                $r = [];
                foreach ($cols as $col) $r[$col] = trim((string)($_POST['field'][$col] ?? ''));
                if ($oldId === '') {
                    if ($r['segment_id'] === '') $r['segment_id'] = selfId();
                    if (in_array('evidence_id', $cols, true) && $r['evidence_id'] === '') $r['evidence_id'] = 'document_segment:'.$r['segment_id'];
                    $rows[] = $r;
                } else {
                    $found = false;
                    foreach ($rows as &$existing) if ($existing['segment_id'] === $oldId) { $r['segment_id'] = $oldId; $existing = $r; $found = true; break; }
                    unset($existing);
                    if (!$found) throw new RuntimeException('ไม่พบ Segment ที่ต้องการแก้ไข');
                }
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
    } catch (Throwable $exc) { $error = $exc->getMessage(); }
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
$formOpen = isset($_GET['new']) || $editing !== null;
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
.ds-shell{max-width:1320px;margin:auto}.ds-card{background:white;border:1px solid #e3ebf3;border-radius:18px;padding:22px;margin-bottom:18px}.ds-hero{background:linear-gradient(120deg,#07396c,#0c78b6);color:white;padding:28px;border-radius:20px;margin-bottom:20px}.ds-hero h1{font-weight:700;font-size:clamp(22px,3vw,32px)}.ds-table{font-size:13px}.ds-preview{max-width:520px;white-space:pre-wrap;word-break:break-word}.ds-content{min-height:180px}.ds-meta{font-size:12px;color:#6b7d8c}.ds-main{padding:24px;min-width:0;width:100%}@media(max-width:700px){.ds-main{padding:12px}.ds-card{padding:14px}}
</style></head><body><div class="admin-layout"><?php include '../includes/sidebar.php'; ?>
<div class="main-shell"><header class="topbar"><button class="mobile-menu-btn" id="mobileMenuBtn" type="button"><i class="bi bi-list"></i></button><div class="topbar-title"><span class="topbar-kicker">MBS UNIWISE ADMIN</span><strong>จัดการ Data Segment</strong></div><div class="topbar-status"><span class="status-dot"></span>Document RAG</div></header>
<main class="content-area ds-main"><div class="ds-shell"><section class="ds-hero"><h1><i class="bi bi-file-earmark-text"></i> จัดการ Document Data Segments</h1><p class="mb-0">เพิ่ม แก้ไข ลบ ค้นหา และนำเข้า/ส่งออก CSV โดยไม่ใช้ MySQL</p></section>
<?php if ($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
<?php if ($flash): ?><div class="alert alert-success"><?=e($flash)?></div><?php endif; ?>
<div class="ds-card"><div class="d-flex flex-wrap justify-content-between gap-2 align-items-center"><div><h5 class="mb-1">ข้อมูลทั้งหมด <?=count($rows)?> Segments</h5><div class="ds-meta">ไฟล์: <?=e(basename($realCsv))?> | แก้ไขไฟล์ CSV ต้นฉบับโดยตรง (มี Backup ก่อนเขียน)</div></div><div class="d-flex flex-wrap gap-2"><a href="?new=1" class="btn btn-primary"><i class="bi bi-plus-lg"></i> เพิ่ม Segment</a><form method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['segment_csrf'])?>"><button class="btn btn-outline-primary" name="action" value="export"><i class="bi bi-download"></i> ส่งออก CSV</button></form></div></div>
<hr><form method="get" class="row g-2"><div class="col-md-9"><input class="form-control" name="q" placeholder="ค้นหา Segment ID, ชื่อเอกสาร หรือข้อความ" value="<?=e($q)?>"></div><div class="col-md-3"><button class="btn btn-outline-secondary w-100">ค้นหา</button></div></form></div>
<?php if ($formOpen): ?><div class="ds-card"><h5><?= $editing ? 'แก้ไข Segment' : 'เพิ่ม Segment ใหม่' ?></h5><form method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['segment_csrf'])?>"><input type="hidden" name="action" value="save"><input type="hidden" name="old_id" value="<?=e($editing['segment_id']??'')?>"><div class="row g-3"><?php foreach ($columns as $col): ?><div class="col-12 <?= $col === 'content' ? '' : 'col-lg-6' ?>"><label class="form-label fw-semibold"><?=e($col)?></label><?php if ($col==='content'): ?><textarea class="form-control ds-content" name="field[<?=e($col)?>]" required><?=e($editing[$col]??'')?></textarea><?php else: ?><input class="form-control" name="field[<?=e($col)?>]" value="<?=e($editing[$col]??'')?>" <?=($col==='segment_id' && $editing)?'readonly':''?> <?=($col==='segment_id')?'placeholder="ว่างไว้เพื่อสร้าง UUID อัตโนมัติ"':''?>></input><?php endif; ?></div><?php endforeach; ?></div><div class="mt-3 d-flex gap-2"><button type="submit" class="btn btn-primary">บันทึก CSV</button><a class="btn btn-outline-secondary" href="index.php">ยกเลิก</a></div></form></div><?php endif; ?>
<div class="ds-card"><h5>นำเข้า CSV (แทนที่ข้อมูลทั้งหมด)</h5><p class="ds-meta">ต้องมีชื่อและลำดับคอลัมน์ตรงกับ CSV เดิม ระบบตรวจ ID ซ้ำและสำรองข้อมูลก่อนแทนที่ ใช้ไฟล์ .csv UTF-8 ไม่ใช่ .xlsx</p><form method="post" enctype="multipart/form-data" onsubmit="return confirm('ยืนยันแทนที่ข้อมูลทั้งหมดด้วยไฟล์ที่นำเข้า?')"><input type="hidden" name="csrf" value="<?=e($_SESSION['segment_csrf'])?>"><div class="d-flex flex-wrap gap-2"><input type="file" name="csv_file" accept=".csv,text/csv" class="form-control" style="max-width:420px" required><button class="btn btn-outline-danger" name="action" value="import">นำเข้าและแทนที่</button></div></form></div>
<div class="ds-card"><h5>รายการ Segments (<?=count($filtered)?>)</h5><div class="table-responsive"><table class="table table-hover align-middle ds-table"><thead><tr><th>Segment ID</th><th>เอกสาร</th><th>เนื้อหา</th><th>จัดการ</th></tr></thead><tbody><?php foreach (array_slice($filtered,0,500) as $r): ?><tr><td style="max-width:210px;word-break:break-all"><?=e($r['segment_id'])?></td><td><?=e($r['document_name']??'-')?></td><td><div class="ds-preview"><?=e(dsPreview((string)$r['content']))?></div></td><td><div class="d-flex gap-1"><a class="btn btn-sm btn-outline-primary" href="?edit=<?=urlencode($r['segment_id'])?>">แก้ไข</a><form method="post" onsubmit="return confirm('ยืนยันลบ Segment นี้?')"><input type="hidden" name="csrf" value="<?=e($_SESSION['segment_csrf'])?>"><input type="hidden" name="id" value="<?=e($r['segment_id'])?>"><button class="btn btn-sm btn-outline-danger" name="action" value="delete">ลบ</button></form></div></td></tr><?php endforeach;?></tbody></table></div><?php if(count($filtered)>500): ?><div class="alert alert-info">แสดง 500 รายการแรก โปรดค้นหาเพื่อจำกัดผลลัพธ์</div><?php endif;?></div></div></main></div></div>
<script>
document.addEventListener('DOMContentLoaded',()=>{const s=document.getElementById('sidebar'),o=document.getElementById('sidebarOverlay');if(localStorage.getItem('mbsSidebarCollapsed')==='1'&&innerWidth>=992)document.body.classList.add('sidebar-collapsed');document.getElementById('sidebarToggle')?.addEventListener('click',()=>{document.body.classList.toggle('sidebar-collapsed');localStorage.setItem('mbsSidebarCollapsed',document.body.classList.contains('sidebar-collapsed')?'1':'0')});document.getElementById('mobileMenuBtn')?.addEventListener('click',()=>{document.body.classList.toggle('sidebar-open')});o?.addEventListener('click',()=>document.body.classList.remove('sidebar-open'))});
</script></body></html>
