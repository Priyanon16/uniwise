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

// Compatible with the existing ?name=VALUE endpoint.
// New: accept ?query=FULL_QUESTION&name=EXTRACTED_PROGRAM.
// The current question takes precedence when it explicitly names a major.
function explicitMajor(string $q): string {
    if (!preg_match('/(?:สาขาวิชา|สาขา|หลักสูตร)\s*(.{2,180})/u', $q, $m)) return '';
    $text = trim($m[1]);
    // Stop at the user's intent; don't cut within the canonical major name.
    $parts = preg_split('/(?:มีใครบ้าง|มีอาจารย์|มีบุคลากร|กี่คน|กี่ปี|กี่หน่วยกิต|กี่บาท|ค่าเทอม|ค่าเล่าเรียน|เรียน|สอน|ทำงาน|อาชีพ|หน่วยกิต|เป็นใคร|คือใคร|คืออะไร|มีอะไรบ้าง|เท่าไหร่|เท่าไร|อย่างไร|ยังไง|หรือไม่|ได้ไหม|ได้มั้ย|ข้อมูลอะไร|\?|？|\n)/u', $text, 2);
    $text = trim($parts[0] ?? '');
    $text = preg_replace('/^(?:ของ|ใน|เกี่ยวกับ|ชื่อ)\s*/u', '', $text) ?? $text;
    // These are references/whole-faculty requests, NOT specific programs.
    if (in_array(canonical($text), ['อะไร','ไหน','นี้','นั้น','เดิม','ทั้งหมด','ทุก','แต่ละ',''], true)) return '';
    return $text;
}
function candidateFromQuery(string $q): string {
    $explicit = explicitMajor($q);
    if ($explicit !== '') return $explicit;
    $text = trim($q);
    // Context words are not a person's or a program's name.
    $text = preg_replace('/^(?:อยากทราบ|อยากรู้|ขอข้อมูล|ข้อมูล|ถามเรื่อง|เกี่ยวกับ|ของ|แล้วของ|แล้ว|อาจารย์|บุคลากร)\s*/u', '', $text) ?? $text;
    $pieces = preg_split('/(?:ค่าเทอม|ค่าเล่าเรียน|กี่ปี|กี่หน่วยกิต|เรียน|สอน|ทำงาน|อาชีพ|มีใครบ้าง|มีอาจารย์|กี่คน|หน่วยกิต|หลักสูตร|\?|？|\n)/u', $text, 2);
    $text = trim($pieces[0] ?? '');
    $text = preg_replace('/(?:ล่ะ|ละ|คะ|ค่ะ|ครับ)$/u', '', $text) ?? $text;
    return trim($text);
}
function asciiToken(string $x): bool { return (bool)preg_match('/^[a-z0-9_.\- ]+$/iu',$x); }
function directTokenInQuery(string $term, string $query): bool {
    $norm = canonical($term);
    if ($norm === '' || ulen($norm) < 2) return false;
    // Latin acronyms must be independent words: 'MM' must NOT match 'MMTEST26'.
    if (asciiToken($norm)) {
        return (bool)preg_match('/(?<![a-z0-9_])'.preg_quote($term,'/').'(?![a-z0-9_])/iu', $query);
    }
    // For Thai, a term might be immediately adjacent to 'เรียน/มีใครบ้าง'.
    return strpos(canonical($query), $norm) !== false;
}
function selectMajor(array $records, string $needle, bool $allowPartial): array {
    $n = canonical($needle);
    if (ulen($n) < 2) return ['not_found',[], ''];
    $exact=[]; $partial=[];
    foreach ($records as $key=>$record) {
        if (isset($record['terms'][$n])) { $exact[$key]=$record['name']; continue; }
        if ($allowPartial && ulen($n)>=3) {
            foreach (array_keys($record['terms']) as $term) {
                if (strpos($term,$n)!==false) { $partial[$key]=$record['name']; break; }
            }
        }
    }
    $found=$exact ?: $partial;
    if (!$found) return ['not_found',[], ''];
    if (count($found)>1) return ['ambiguous',array_values($found), ''];
    return ['matched',array_values($found),($exact?'exact':'partial')];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') respond(['success'=>false,'status'=>'method_not_allowed'],405);
$name=trim((string)($_GET['name']??''));
$query=trim((string)($_GET['query']??''));
if (($name==='' && $query==='') || ulen($name)>100 || ulen($query)>500) respond(['success'=>false,'status'=>'invalid_query'],400);

// Distinct majors are keyed by canonical name; multiple curriculum years are merged.
$programs=[];
foreach(catalogRows() as $row) {
    if (!is_array($row)) continue;
    $major=trim((string)($row['major_name']??''));
    if ($major==='') continue;
    $key=canonical($major);
    if ($key==='') continue;
    if (!isset($programs[$key])) $programs[$key]=['name'=>$major,'terms'=>[]];
    $terms=array_merge([$major,(string)($row['major_name_en']??'')],aliasTerms($row['aliases']??''));
    foreach ($terms as $term) {
        $v=canonical($term);
        if ($v!=='') $programs[$key]['terms'][$v]=true;
    }
}

$explicit=($query!=='' ? explicitMajor($query) : '');
$candidate=($explicit!=='' ? $explicit : ($name!=='' ? $name : candidateFromQuery($query)));
[$status,$matches,$matchType]=selectMajor($programs,$candidate,true);

// Full-question fallback is used ONLY when no reliable candidate was extracted.
// Prefer longest matched names/aliases; never arbitrarily select one program.
if ($status==='not_found' && $explicit==='' && $name==='' && $query!=='') {
    $bestLength=0; $best=[];
    foreach ($programs as $key=>$record) {
        foreach(array_keys($record['terms']) as $term) {
            if (!directTokenInQuery($term,$query)) continue;
            $length=ulen($term);
            if ($length>$bestLength) { $bestLength=$length; $best=[$key=>$record['name']]; }
            elseif ($length===$bestLength) { $best[$key]=$record['name']; }
        }
    }
    if ($best) {
        $matches=array_values($best);
        $status=count($matches)===1?'matched':'ambiguous';
        $matchType='query_exact';
    }
}
if ($status==='not_found') respond(['success'=>true,'status'=>'not_found','program'=>'','group_id'=>'','candidates'=>[]]);
if ($status==='ambiguous') respond(['success'=>true,'status'=>'ambiguous','program'=>'','group_id'=>'','candidates'=>$matches]);
$normalizedMajor=preg_replace('/^(?:สาขาวิชา|สาขา)\s*/u','',trim((string)$matches[0]))??trim((string)$matches[0]);

// personnel group codes are a SEPARATE relationship from degree/program IDs.
// Only groups 1-6 officially supported by people.php; other programs return ''.
// Adding a new alias in Admin does not require editing this mapping.
$groupCodes=[
    'บัญชี'=>'1','การบัญชี'=>'1','บัญชีบัณฑิต'=>'1',
    'การตลาด'=>'2','การจัดการ'=>'3','การจัดการสมัยใหม่'=>'3',
    'คอมพิวเตอร์ธุรกิจ'=>'4','บริหารการเงิน'=>'5','การบริหารการเงิน'=>'5',
    'เศรษฐศาสตร์'=>'6','เศรษฐศาสตร์ธุรกิจ'=>'6'
];
$groupId=$groupCodes[canonical($normalizedMajor)]??'';
respond(['success'=>true,'status'=>'matched','program'=>$normalizedMajor,'group_id'=>$groupId,
         'match_type'=>$matchType,'candidates'=>[$normalizedMajor]]);
