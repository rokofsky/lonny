<?php
declare(strict_types=1);


$private = require __DIR__.'/private.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('lonny_site');
    session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>'/']);
    session_start();
}
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
function h(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function linkto(string $page, array $params=[]): string { return '?'.http_build_query(['page'=>$page]+$params); }
function redirectto(string $page, array $params=[]): never { header('Location: '.linkto($page,$params)); exit; }
function token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(24)); }
function csrf(): void { if (!hash_equals(token(), (string)($_POST['csrf']??''))) throw new RuntimeException('Security check failed. Please refresh and try again.'); }
function logged_in(): bool { return !empty($_SESSION['admin']); }
function is_admin(): bool { return logged_in(); }
function defaults(): array {return [
  'admin'=>null,
  'settings'=>[
    'brand'=>'Lonny Gold | Language, Learning & Communication',
    'bio_headline'=>'Learning, connection and the art of teaching',
    'bio_text'=>'Lonny Gold is a Canadian educator and teacher trainer who has worked with Suggestopedia since 1978. He leads language-learning, teacher-development and communication experiences internationally, bringing imagination, meaningful interaction and psychological safety into the learning environment. This introduction is provisional and will be replaced with Lonny’s new biography.',
    'hero_title'=>'Learning comes alive when people connect.',
    'hero_subtitle'=>'Language learning, teacher development and communication workshops inspired by Suggestopedia—and designed for real human connection.',
    'manifesto'=>'Learning is more than information. It grows through trust, attention, emotion, creativity and the connections we make with one another. The right environment makes room for new possibilities.',
    'experiment_title'=>'The Sound Shapes Experiment',
    'experiment_prompt'=>'Invent a word for a feeling that English has no name for. What does it sound like? What does it mean?',
    'contact_email'=>'',
    'privacy_owner'=>'',
    'privacy_contact'=>'',
  ],
  'workshops'=>[
    ['id'=>'w1','track'=>'Teachers','title'=>'The Unforgettable Activity Lab','description'=>'A hands-on exploration of experiences that learners remember: movement, story, surprise and participation.','format'=>'Seminar / workshop','duration'=>'By arrangement','active'=>true],
    ['id'=>'w2','track'=>'Teachers','title'=>'Suggestopedia in Practice','description'=>'Explore the ideas behind Suggestopedia and experiment with new classroom possibilities.','format'=>'Teacher training','duration'=>'By arrangement','active'=>true],
    ['id'=>'w3','track'=>'Organizations','title'=>'Bring Lonny to Your Team','description'=>'A tailor-made seminar for schools, academies and organizations seeking fresh approaches to communication and learning.','format'=>'Custom seminar','duration'=>'By arrangement','active'=>true],
    ['id'=>'w4','track'=>'Organizations','title'=>'Communication Without the Script','description'=>'Playful ways to listen, connect, express and inspire each other.','format'=>'Workshop','duration'=>'By arrangement','active'=>true],
    ['id'=>'w5','track'=>'Learners','title'=>'English That Sticks','description'=>'English through memorable experiences rather than an endless cycle of drills.','format'=>'English course','duration'=>'By arrangement','active'=>true],
    ['id'=>'w6','track'=>'Learners','title'=>'Young Learners Playground','description'=>'Stories, games and meaningful communication for young learners.','format'=>'English course','duration'=>'By arrangement','active'=>true],
  ],
  'sessions'=>[], 'videos'=>[], 'photos'=>[], 'questions'=>[], 'inquiries'=>[], 'responses'=>[], 'registrations'=>[], 'throttle'=>[]
];}
function db_path():string {return __DIR__.'/storage/site.json';}
function read_db():array {
  $path=db_path();
  if(!is_file($path)) return defaults();
  $fp=fopen($path,'r'); if(!$fp) throw new RuntimeException('Site storage is unavailable.');
  flock($fp,LOCK_SH); $str=stream_get_contents($fp); flock($fp,LOCK_UN); fclose($fp);
  $db=json_decode($str?:'null',true); if(!is_array($db)) throw new RuntimeException('Site storage is unreadable.');
  $db['settings'] = array_replace(defaults()['settings'], $db['settings'] ?? []);
  return $db;
}
function mutate(callable $func):mixed {
  $path=db_path(); $dir=dirname($path); if(!is_dir($dir)) mkdir($dir,0700,true);
  $fp=fopen($path,'c+'); if(!$fp) throw new RuntimeException('Unable to write site storage.');
  if(!flock($fp,LOCK_EX)) throw new RuntimeException('Unable to lock storage.');
  try {
    rewind($fp); $raw=stream_get_contents($fp); $db=$raw?json_decode($raw,true):defaults();
    if(!is_array($db)) throw new RuntimeException('Storage is unreadable.');
    $result=$func($db);
    $out=json_encode($db,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
    rewind($fp); ftruncate($fp,0); fwrite($fp,$out); fflush($fp);
    return $result;
  } finally {flock($fp,LOCK_UN);fclose($fp);}
}
function new_id():string {return bin2hex(random_bytes(9));}
function u_substr(string $s,int $start,int $length):string {return function_exists('mb_substr')?mb_substr($s,$start,$length,'UTF-8'):(iconv_substr($s,$start,$length,'UTF-8')?:'');}
function u_len(string $s):int{return function_exists('mb_strlen')?mb_strlen($s,'UTF-8'):(int)iconv_strlen($s,'UTF-8');}
function clean(string $v,int $max=600):string {return u_substr(trim($v),0,$max);}
function input(string $key,int $max=600):string{return clean((string)($_POST[$key]??''),$max);}
function flash(string $text):void {$_SESSION['flash']=$text;}
function take_flash():string {$str=(string)($_SESSION['flash']??'');unset($_SESSION['flash']);return $str;}
function required_consent():void {if(empty($_POST['consent']))throw new RuntimeException('Please confirm the privacy notice.');}
function public_submit(string $type,array $record):void {
  global $private;
  if(!empty($_POST['website']))throw new RuntimeException('Submission rejected.');
  $ip=(string)($_SERVER['REMOTE_ADDR']??'local');
  $finger=hash('sha256',$private['setup_key'].$type.$ip);
  mutate(function (&$db) use($type,$record,$finger){
    $now=time();
    $db['throttle']=array_filter($db['throttle'],fn($x)=>($x['time']??0)>$now-3600);
    $hits=0;
    foreach($db['throttle'] as $x) if($x['finger']===$finger) $hits++;
    if($hits>=5)throw new RuntimeException('Too many submissions. Please try again later.');
    $db['throttle'][]=['finger'=>$finger,'time'=>$now];
    $db[$type][]=$record+['id'=>new_id(),'created_at'=>gmdate('c')];
  });
}
function body_trim(string $value,int $length=120):string {return u_len($value)>$length?u_substr($value,0,$length-1).'…':$value;}
function safe_video(string $url):?array {
  $host=strtolower((string)parse_url($url,PHP_URL_HOST));
  $path=(string)parse_url($url,PHP_URL_PATH);
  if(in_array($host,['youtube.com','www.youtube.com','m.youtube.com'],true)) {
    parse_str((string)parse_url($url,PHP_URL_QUERY),$q);$id=$q['v']??'';
    if(preg_match('/^[A-Za-z0-9_-]{11}$/',(string)$id))return ['embed'=>'https://www.youtube-nocookie.com/embed/'.$id];
  }
  if($host==='youtu.be'){$id=ltrim($path,'/');if(preg_match('/^[A-Za-z0-9_-]{11}$/',$id))return ['embed'=>'https://www.youtube-nocookie.com/embed/'.$id];}
  if(in_array($host,['vimeo.com','www.vimeo.com'],true)) {$id=trim($path,'/');if(preg_match('/^\d+$/',$id))return ['embed'=>'https://player.vimeo.com/video/'.$id];}
  return null;
}