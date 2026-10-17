<?php
declare(strict_types=1);
/** MBS UniWise AI - server-side Dify Chatflow proxy. PHP 8.0+, allow_url_fopen=On.
 * Keep API credentials on the server; never embed them in index.html.
 */
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');
header('Referrer-Policy: same-origin');
function out(int $status, array $data): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function fail(int $status, string $message): void { out($status, ['error' => $message]); }
function charCount(string $s): int { return function_exists('mb_strlen') ? mb_strlen($s,'UTF-8') : preg_match_all('/./us',$s); }
function body(): array {
    $raw = file_get_contents('php://input',false,null,0,50001);
    if ($raw === false || strlen($raw) > 50000) fail(413,'ข้อความใหญ่เกินไป');
    $data=json_decode($raw,true);
    if (!is_array($data)) fail(400,'รูปแบบ JSON ไม่ถูกต้อง');
    return $data;
}
// Same-origin browser calls only. Also prevents a third-party page using your secret-bearing proxy.
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if ($origin!=='') {
    $originHost=parse_url($origin,PHP_URL_HOST);
    $originPort=parse_url($origin,PHP_URL_PORT);
    $serverHost=(string)($_SERVER['HTTP_HOST']??'');
    $hostOnly=explode(':',$serverHost)[0];
    $serverPort=str_contains($serverHost,':')?(int)substr(strrchr($serverHost,':'),1):null;
    if (!$originHost || strcasecmp($originHost,$hostOnly)!==0 || ($originPort??null)!==$serverPort) fail(403,'ไม่อนุญาตคำขอจากเว็บไซต์อื่น');
}
$method=$_SERVER['REQUEST_METHOD']??'GET';
if (!in_array($method,['GET','POST'],true)) fail(405,'ไม่รองรับวิธีเรียกนี้');
$configFile=__DIR__.'/config.php';
$config=is_file($configFile)?require $configFile:[];
if(!is_array($config)) $config=[];
$base=rtrim(trim((string)(getenv('DIFY_API_BASE')?:($config['dify_api_base']??''))),'/');
$key=trim((string)(getenv('DIFY_API_KEY')?:($config['dify_api_key']??'')));
$ready=$base!==''&&$key!==''&&$key!=='YOUR_DIFY_API_KEY';
$action=(string)($_GET['action']??'');
if ($action==='health') {
    if ($method!=='GET') fail(405,'health ใช้ GET เท่านั้น');
    out($ready?200:503,['ready'=>$ready,'message'=>$ready?'ตั้งค่าเซิร์ฟเวอร์แล้ว':'ยังไม่ได้ตั้งค่า Dify API Key ใน api/config.php']);
}
if (!$ready) fail(503,'ยังไม่ได้ตั้งค่า Dify API Key บนเซิร์ฟเวอร์ กรุณาเปิดคู่มือติดตั้ง');
if(!preg_match('~^https?://[^/]+(?:/[^?#]*)?/v1$~i',$base))fail(500,'DIFY_API_BASE ต้องลงท้ายด้วย /v1');
$user=(string)($_SERVER['HTTP_X_MBS_USER']??'');
if(!preg_match('/^mbs-[a-f0-9]{32}$/',$user)) fail(400,'รหัสอุปกรณ์ไม่ถูกต้อง');
if(!in_array($action,['list','messages','send','rename','delete'],true)) fail(404,'ไม่พบคำสั่ง');
if ((in_array($action,['list','messages'],true)&&$method!=='GET') || (in_array($action,['send','rename','delete'],true)&&$method!=='POST')) fail(405,'HTTP Method ไม่ถูกต้อง');
// A minimal shared rate limit by IP for cost control; still use Dify limits and HTTPS in production.
if ($action==='send') {
    $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');
    $rateFile=sys_get_temp_dir().'/mbs_uniwise_rate_'.hash('sha256',$ip).'.json';
    $f=@fopen($rateFile,'c+');
    if($f && flock($f,LOCK_EX)){
        $old=stream_get_contents($f);$records=json_decode($old?:'[]',true);if(!is_array($records))$records=[];
        $now=time();$records=array_values(array_filter($records,static fn($t)=>is_numeric($t)&&$t>$now-60));
        if(count($records)>=12){ flock($f,LOCK_UN);fclose($f);fail(429,'ส่งข้อความเร็วเกินไป กรุณารอประมาณหนึ่งนาที'); }
        $records[]=$now;ftruncate($f,0);rewind($f);fwrite($f,json_encode($records));fflush($f);flock($f,LOCK_UN);fclose($f);
    } elseif($f) fclose($f);
}
$data=$method==='POST'?body():[];
$id=(string)($data['conversation_id']??$_GET['conversation_id']??'');
$validId=static fn(string $s):bool=>(bool)preg_match('/^[A-Za-z0-9-]{1,100}$/',$s);
if($id!==''&&!$validId($id))fail(400,'รหัสบทสนทนาไม่ถูกต้อง');
$limit=min(100,max(1,(int)($_GET['limit']??50)));
$path='';$outMethod='GET';$payload=null;$stream=false;
switch($action){
 case 'list':
    $params=['user'=>$user,'limit'=>$limit,'sort_by'=>'-updated_at'];
    $last=(string)($_GET['last_id']??'');if($last!==''){if(!$validId($last))fail(400,'รหัสหน้าไม่ถูกต้อง');$params['last_id']=$last;}
    $path='/conversations?'.http_build_query($params);break;
 case 'messages':
    if($id==='')fail(400,'กรุณาระบุบทสนทนา');
    $params=['user'=>$user,'conversation_id'=>$id,'limit'=>$limit];
    $first=(string)($_GET['first_id']??'');if($first!==''){if(!$validId($first))fail(400,'รหัสหน้าไม่ถูกต้อง');$params['first_id']=$first;}
    $path='/messages?'.http_build_query($params);break;
 case 'send':
    $text=trim((string)($data['query']??''));
    if($text===''||charCount($text)>8000)fail(400,'กรุณาใส่คำถามไม่เกิน 8,000 ตัวอักษร');
    $outMethod='POST';$path='/chat-messages';$stream=true;
    $payload=['inputs'=>(object)($config['default_inputs']??[]),'query'=>$text,'user'=>$user,'response_mode'=>'streaming','auto_generate_name'=>true];
    if($id!=='')$payload['conversation_id']=$id;
    break;
 case 'rename':
    if($id==='')fail(400,'กรุณาระบุบทสนทนา');
    $name=trim((string)($data['name']??''));if($name===''||charCount($name)>100)fail(400,'กรุณากำหนดชื่อไม่เกิน 100 ตัวอักษร');
    $outMethod='POST';$path='/conversations/'.rawurlencode($id).'/name';
    $payload=['name'=>$name,'auto_generate'=>false,'user'=>$user];break;
 case 'delete':
    if($id==='')fail(400,'กรุณาระบุบทสนทนา');
    $outMethod='DELETE';$path='/conversations/'.rawurlencode($id);
    $payload=['user'=>$user];break;
}
// PHP stream wrapper: does not require extra extensions such as cURL.
if(!filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN)) fail(500,'PHP ต้องเปิด allow_url_fopen');
$headers="Authorization: Bearer {$key}\r\nAccept: ".($stream?'text/event-stream':'application/json')."\r\nContent-Type: application/json\r\n";
$httpOptions=['method'=>$outMethod,'header'=>$headers,'timeout'=>$stream?300:60,'ignore_errors'=>true,'follow_location'=>0,'protocol_version'=>1.1];
if($payload!==null)$httpOptions['content']=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
$context=stream_context_create(['http'=>$httpOptions,'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
$fp=@fopen($base.$path,'rb',false,$context);
if(!$fp){error_log('MBS UniWise proxy connect failure');fail(502,'เซิร์ฟเวอร์ติดต่อ Dify ไม่สำเร็จ โปรดตรวจสอบ API URL และเครือข่าย');}
$meta=stream_get_meta_data($fp);
$status=502;
foreach(($meta['wrapper_data']??[]) as $line){if(preg_match('~^HTTP/\S+\s+(\d{3})~i',$line,$m))$status=(int)$m[1];}
if($status<200||$status>=300){
    $errorBody=stream_get_contents($fp);fclose($fp);
    $err=json_decode((string)$errorBody,true);$reason=is_array($err)?(string)($err['message']??$err['code']??''):'';
    error_log('Dify API HTTP '.$status.' '.substr($reason,0,160));
    if($status===401)fail(502,'Dify API Key ไม่ถูกต้อง');
    if($status===404)fail(404,'ไม่พบบทสนทนานี้ใน Dify');
    if($status===429)fail(429,'ระบบ Dify มีคำขอมาก กรุณาลองใหม่');
    fail(in_array($status,[400,403],true)?$status:502,'Dify ส่งข้อผิดพลาด HTTP '.$status.($status===400&&$reason?': '.substr($reason,0,220):''));
}
if(!$stream){
    $raw=stream_get_contents($fp);fclose($fp);
    if($status===204||trim((string)$raw)==='') out(200,['ok'=>true]);
    $decoded=json_decode((string)$raw,true);
    if(!is_array($decoded))fail(502,'Dify ส่งข้อมูลที่ไม่ใช่ JSON');
    out(200,$decoded);
}
http_response_code(200);
header('Content-Type: text/event-stream; charset=utf-8');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');
@ini_set('zlib.output_compression','0');
@ini_set('output_buffering','off');
@set_time_limit(0);
while(ob_get_level()>0)@ob_end_flush();
echo ": connected\n\n";flush();
while(!feof($fp)){
    $chunk=fread($fp,8192);
    if($chunk===false)break;
    if($chunk!==''){echo $chunk;flush();}
    if(connection_aborted())break;
}
$timedOut=stream_get_meta_data($fp)['timed_out']??false;
fclose($fp);
if($timedOut)echo 'data: '.json_encode(['event'=>'error','message'=>'Dify ใช้เวลาตอบนานเกินไป'],JSON_UNESCAPED_UNICODE)."\n\n";
flush();
