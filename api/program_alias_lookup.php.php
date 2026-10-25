<?php
/**
 * MBS UniWise AI - optional program-alias lookup, STAGE 2 (testing only).
 * Does not modify existing programs.php or people.php.
 * Requirements: PHP 8+, JSON and PCRE; PHP HTTP stream access or curl; filesystem cache writable in temp dir.
 * Deployment location: /uniwise/api/program_alias_lookup.php (test URL first).
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const CACHE_TTL = 300;
// Check on YOUR ReadyIDC server. Can override with MBS_PROGRAM_CATALOG_URL environment variable.
const DEFAULT_CATALOG_URL = 'http://127.0.0.1/uniwise/api/programs.php?topic=general&active=1';

function respond(array $obj, int $http = 200): never {
    http_response_code($http);
    echo json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function canonical(string $s): string {
    $s = strtolower(trim($s)); // ASCII case folding; Thai characters are already case-free
    $s = preg_replace('/^(?:สาขาวิชา|สาขา|หลักสูตร)\s*/u', '', $s) ?? $s;
    return preg_replace('/[\s\p{Z}]+/u', '', $s) ?? $s;
}
function ulen(string $s): int { return preg_match_all('/./u', $s) ?: 0; }
function aliasTerms(mixed $a): array {
    if (is_array($a)) $a = implode(',', $a);
    $a = trim((string)$a);
    if ($a === '') return [];
    return array_values(array_filter(array_map('trim', preg_split('/[,;|]+/u', $a) ?: [])));
}
function catalogRows(): array {
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mbs_program_alias_catalog_v1.json';
    $cached = null;
    if (is_readable($file)) {
        $tmp = json_decode((string)@file_get_contents($file), true);
        if (is_array($tmp) && isset($tmp['saved_at'], $tmp['records']) && is_array($tmp['records'])) $cached = $tmp;
    }
    if ($cached !== null && (time() - (int)$cached['saved_at']) < CACHE_TTL) return $cached['records'];
    $url = getenv('MBS_PROGRAM_CATALOG_URL') ?: DEFAULT_CATALOG_URL;
    $raw = false;
    $code = 0;
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        if ($curl !== false) {
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => ['Accept: application/json']
            ]);
            $raw = curl_exec($curl);
            $code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
        }
    } elseif (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        $context = stream_context_create(['http'=>[
            'method'=>'GET', 'timeout'=>5, 'follow_location'=>0,
            'header'=>"Accept: application/json\r\n"
        ]]);
        $raw = @file_get_contents($url, false, $context);
        if (isset($http_response_header[0]) && preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m)) $code=(int)$m[1];
    }
    if (is_string($raw) && $code === 200) {
        $payload = json_decode($raw, true);
        if (is_array($payload) && ($payload['success'] ?? false) === true && is_array($payload['data'] ?? null)) {
            $rows = $payload['data'];
            // Atomic cache write; safe if it fails (the request still returns the live records).
            $temporary = $file . '.' . getmypid() . '.tmp';
            $ok = @file_put_contents($temporary, json_encode(['saved_at'=>time(), 'records'=>$rows], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX);
            if ($ok !== false) @rename($temporary, $file);
            else @unlink($temporary);
            return $rows;
        }
    }
    // Stale-cache fallback only; don't invent aliases if API is down.
    if ($cached !== null) return $cached['records'];
    respond(['success'=>false, 'status'=>'unavailable', 'message'=>'Cannot load alias catalog; existing Dify fallback should run'], 503);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') respond(['success'=>false,'status'=>'method_not_allowed'],405);
$input = trim((string)($_GET['name'] ?? ''));
if ($input === '' || ulen($input) > 100) respond(['success'=>false,'status'=>'invalid_query'],400);
$needle = canonical($input);
if ($needle === '' || ulen($needle) < 2) respond(['success'=>true,'status'=>'not_found','program'=>'','group_id'=>'','candidates'=>[]]);

// Distinct normalized majors; different curriculum versions are NOT separate matches.
$programs = [];
foreach (catalogRows() as $row) {
    if (!is_array($row)) continue;
    $major = trim((string)($row['major_name'] ?? ''));
    if ($major === '') continue;
    $key = canonical($major);
    if ($key === '') continue;
    if (!isset($programs[$key])) $programs[$key] = ['name'=>$major, 'terms'=>[]];
    $terms = array_merge([$major, (string)($row['major_name_en'] ?? '')], aliasTerms($row['aliases'] ?? ''));
    foreach ($terms as $term) {
        $v = canonical($term);
        if ($v !== '') $programs[$key]['terms'][$v] = true;
    }
}
$exact = [];
$partial = [];
foreach ($programs as $key=>$record) {
    if (isset($record['terms'][$needle])) {
        $exact[$key] = $record['name'];
        continue;
    }
    // Limited substring search: only for queries of >=3 chars; ambiguity is never guessed.
    if (ulen($needle) >= 3) {
        foreach (array_keys($record['terms']) as $term) {
            if (strpos($term, $needle) !== false) {
                $partial[$key] = $record['name'];
                break;
            }
        }
    }
}
$matches = $exact ?: $partial;
if (!$matches) respond(['success'=>true,'status'=>'not_found','program'=>'','group_id'=>'','candidates'=>[]]);
if (count($matches) > 1) respond(['success'=>true,'status'=>'ambiguous','program'=>'','group_id'=>'','candidates'=>array_values($matches)]);
$major = (string)reset($matches);
// Only six official academic groups from people.php. Other curricula MUST NOT be guessed.
$groupCodes = [
    'บัญชี'=>'1','การบัญชี'=>'1','การตลาด'=>'2','การจัดการ'=>'3',
    'คอมพิวเตอร์ธุรกิจ'=>'4','บริหารการเงิน'=>'5','การบริหารการเงิน'=>'5',
    'เศรษฐศาสตร์'=>'6','เศรษฐศาสตร์ธุรกิจ'=>'6'
];
$groupId = $groupCodes[$major] ?? '';
respond(['success'=>true,'status'=>'matched','program'=>$major,'group_id'=>$groupId,
         'match_type'=>($exact?'exact':'partial'),'candidates'=>[$major]]);
