<?php
declare(strict_types=1);
const ROOT_DIR = __DIR__ . '/..';
const UPLOAD_DIR = ROOT_DIR . '/uploads';
require_once ROOT_DIR . '/core/EmailValidation.php';
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function cms_sanitize_rich_html(string $html): string
{
    $html = trim($html);
    if ($html === '') return '';

    // If the value is still legacy plain text, keep line breaks readable.
    if ($html === strip_tags($html)) {
        return nl2br(h($html));
    }

    $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><blockquote><h2><h3><h4>';
    $clean = strip_tags($html, $allowed);

    // Rich editors in this CMS do not need arbitrary attributes.
    $clean = preg_replace('/<(p|br|strong|b|em|i|u|ul|ol|li|blockquote|h2|h3|h4)\b[^>]*>/i', '<$1>', $clean) ?? $clean;

    return $clean;
}

function cms_prepare_rich_html(string $html): string
{
    // Reuse the same whitelist before values are persisted.
    return cms_sanitize_rich_html($html);
}
function installation_lock_file(): string {
    return __DIR__ . '/install.lock';
}
function database_config_file(): string {
    $primary=__DIR__ . '/config.php';
    if(is_file($primary))return $primary;
    return __DIR__ . '/database.php';
}
function database_config_ready(): bool {
    return is_file(database_config_file());
}
function config_ready(): bool {
    // A valid installation needs both the lock and an active database configuration.
    // This prevents the admin area from bouncing against the installer when one of
    // those runtime files was intentionally removed during a fresh-install preparation.
    return is_file(installation_lock_file()) && database_config_ready();
}
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $cfg=$GLOBALS['cms_installer_db_config']??null;
    if(!is_array($cfg)) {
        $configFile=database_config_file();
        if(!is_file($configFile))throw new RuntimeException('Database is not configured. Open /install/ to start the setup wizard.');
        $cfg=require $configFile;
    }
    if(!is_array($cfg))throw new RuntimeException('The database configuration file is invalid.');
    $host=trim((string)($cfg['host']??''));
    $dbname=trim((string)($cfg['dbname']??''));
    $username=(string)($cfg['username']??'');
    $password=(string)($cfg['password']??'');
    $port=max(1,min(65535,(int)($cfg['port']??3306)));
    if($host===''||$dbname===''||$username==='')throw new RuntimeException('The database configuration is incomplete. Open /install/ to review it.');
    $dsn=sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',$host,$port,$dbname,$cfg['charset']??'utf8mb4');
    $pdo = new PDO($dsn,$username,$password,[ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, ]);
    return $pdo;
}
function site_content(): array {
    $rows=db()->query('SELECT content_key,content_value FROM site_content')->fetchAll();
    $o=[];
    foreach($rows as $r)$o[$r['content_key']]=$r['content_value'];
    return $o;
}
function settings(): array {
    $rows=db()->query('SELECT setting_key,setting_value FROM settings')->fetchAll();
    $o=[];
    foreach($rows as $r)$o[$r['setting_key']]=$r['setting_value'];
    return $o;
}
function setting(string $key, string $default=''): string {
    static $cache=null;
    if($cache===null)$cache=settings();
    return isset($cache[$key])?(string)$cache[$key]:$default;
}
function save_setting(string $key, string $value): void {
    $st=db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $st->execute([$key,$value]);
}
function versioned_asset(string $url,string $diskRelative=''): string {
    $relative=$diskRelative!==''?$diskRelative:(string)(parse_url($url,PHP_URL_PATH)??'');
    $relative=ltrim($relative,'/');
    $full=ROOT_DIR.'/'.$relative;
    $version=is_file($full)?(string)filemtime($full):'1';
    return $url.(str_contains($url,'?')?'&':'?').'v='.rawurlencode($version);
}
function setting_enabled(array $settings,string $key,bool $default=false): bool {
    if(!array_key_exists($key,$settings))return $default;
    return (string)$settings[$key]==='1';
}
function normalized_setting_lines(string $value,int $limit=30,int $maxLength=120): array {
    $items=[];
    foreach(preg_split('/\R/u',$value)?:[] as $line) {
        $line=trim(preg_replace('/\s+/u',' ',$line)??$line);
        if($line==='')continue;
        if(function_exists('mb_substr'))$line=mb_substr($line,0,$maxLength,'UTF-8');
        elseif(preg_match_all('/./us',$line,$characters)&&count($characters[0])>$maxLength)$line=implode('',array_slice($characters[0],0,$maxLength));
        else $line=substr($line,0,$maxLength);
        $key=function_exists('mb_strtolower')?mb_strtolower($line,'UTF-8'):strtolower($line);
        $items[$key]=$line;
        if(count($items)>=$limit)break;
    }
    return array_values($items);
}
function referral_options(array $settings,string $language): array {
    $defaultsEn="Google or another search engine\nFacebook\nInstagram\nTikTok\nWhatsApp\nRecommendation from a person or company\nI already knew the company\nOther";
    $defaultsEs="Google u otro buscador\nFacebook\nInstagram\nTikTok\nWhatsApp\nRecomendación de una persona o empresa\nYa conocía la empresa\nOtro";
    $key=$language==='es'?'form_referral_options_es':'form_referral_options_en';
    return normalized_setting_lines((string)($settings[$key]??($language==='es'?$defaultsEs:$defaultsEn)));
}
function is_other_referral_option(string $value): bool {
    $normalized=function_exists('mb_strtolower')?mb_strtolower(trim($value),'UTF-8'):strtolower(trim($value));
    return in_array($normalized,['other','otro'],true);
}
function meaningful_text_metrics(string $value): array {
    $value=trim(preg_replace('/\s+/u',' ',$value)??$value);
    $characters=preg_match_all('/[\p{L}\p{N}]/u',$value,$matches);
    $words=preg_match_all('/[\p{L}\p{N}]+/u',$value,$matches);
    return [
        'characters'=>$characters===false?0:$characters,
        'words'=>$words===false?0:$words,
        'normalized'=>$value,
    ];
}
function public_form_antispam_config(array $settings): array {
    return [
        'enabled'=>setting_enabled($settings,'form_antispam_enabled',true),
        'block_sales'=>setting_enabled($settings,'form_block_sales_solicitation',true),
        'minimum_seconds'=>max(0,min(30,(int)($settings['form_minimum_seconds']??3))),
        'cooldown_seconds'=>max(0,min(3600,(int)($settings['form_cooldown_seconds']??60))),
        'maximum_per_hour'=>max(1,min(50,(int)($settings['form_maximum_per_hour']??5))),
        'turnstile_enabled'=>setting_enabled($settings,'turnstile_enabled',false),
        'turnstile_site_key'=>trim((string)($settings['turnstile_site_key']??'')),
        'turnstile_secret_stored'=>trim((string)($settings['turnstile_secret_key']??''))!=='',
    ];
}

function public_email_validation_config(array $settings): array {
    return EmailValidation::config($settings);
}

function public_request_client_ip(): string {
    $cloudflare=trim((string)($_SERVER['HTTP_CF_CONNECTING_IP']??''));
    if($cloudflare!==''&&filter_var($cloudflare,FILTER_VALIDATE_IP))return $cloudflare;
    $remote=trim((string)($_SERVER['REMOTE_ADDR']??''));
    return filter_var($remote,FILTER_VALIDATE_IP)?$remote:'';
}

function public_form_ip_rate_file(): string {
    $ip=public_request_client_ip();
    if($ip==='')return '';
    try {
        $hash=hash_hmac('sha256',$ip,app_key());
    } catch(Throwable $e) {
        error_log('[IZZY Form Rate] Unable to derive anonymous client key: '.$e->getMessage());
        return '';
    }
    $directory=rtrim(sys_get_temp_dir(),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'izzy-public-form-rate';
    if(!is_dir($directory)&&!@mkdir($directory,0700,true)&&!is_dir($directory))return '';
    return $directory.DIRECTORY_SEPARATOR.$hash.'.json';
}

function public_form_ip_rate_status(array $config): string {
    $file=public_form_ip_rate_file();
    if($file==='')return 'unavailable';
    $handle=@fopen($file,'c+');
    if($handle===false)return 'unavailable';
    try {
        if(!flock($handle,LOCK_EX))return 'unavailable';
        rewind($handle);
        $raw=stream_get_contents($handle);
        $sent=[];
        if(is_string($raw)&&$raw!=='') {
            $decoded=json_decode($raw,true);
            if(is_array($decoded))$sent=array_map('intval',$decoded);
        }
        $now=time();
        $sent=array_values(array_filter($sent,static fn(int $timestamp):bool=>$timestamp>$now-3600&&$timestamp<=$now));
        $last=$sent?(int)end($sent):0;
        if($last>0&&$now-$last<(int)$config['cooldown_seconds'])return 'cooldown';
        if(count($sent)>=(int)$config['maximum_per_hour'])return 'hourly';
        return 'ok';
    } finally {
        flock($handle,LOCK_UN);
        fclose($handle);
    }
}

function record_public_form_ip_submission(): void {
    $file=public_form_ip_rate_file();
    if($file==='')return;
    $handle=@fopen($file,'c+');
    if($handle===false)return;
    try {
        if(!flock($handle,LOCK_EX))return;
        rewind($handle);
        $raw=stream_get_contents($handle);
        $sent=[];
        if(is_string($raw)&&$raw!=='') {
            $decoded=json_decode($raw,true);
            if(is_array($decoded))$sent=array_map('intval',$decoded);
        }
        $now=time();
        $sent=array_values(array_filter($sent,static fn(int $timestamp):bool=>$timestamp>$now-3600&&$timestamp<=$now));
        $sent[]=$now;
        ftruncate($handle,0);
        rewind($handle);
        fwrite($handle,json_encode(array_slice($sent,-50),JSON_UNESCAPED_SLASHES));
        fflush($handle);
    } finally {
        flock($handle,LOCK_UN);
        fclose($handle);
    }
}

function public_form_is_obvious_automation(array $values): bool {
    $text=trim(implode(' ',array_map(static fn($value):string=>(string)$value,$values)));
    if($text==='')return false;
    $plain=html_entity_decode(strip_tags($text),ENT_QUOTES|ENT_HTML5,'UTF-8');
    $urlCount=preg_match_all('~(?:https?://|www\.)[^\s<]+~iu',$plain,$urls);
    $urlCount=$urlCount===false?0:$urlCount;
    if($urlCount>=5)return true;
    if(preg_match('/(.)\1{11,}/u',$plain))return true;
    if(preg_match('/\b(?:viagra|casino|crypto giveaway|adult traffic|buy followers|free backlinks)\b/iu',$plain))return true;
    $words=preg_split('/\s+/u',trim($plain))?:[];
    if(count($words)>=12) {
        $normalized=array_map(static fn(string $word):string=>function_exists('mb_strtolower')?mb_strtolower(trim($word,'.,;:!?()[]{}<>'),'UTF-8'):strtolower(trim($word,'.,;:!?()[]{}<>')),$words);
        $counts=array_count_values(array_filter($normalized,static fn(string $word):bool=>strlen($word)>=4));
        if($counts&&max($counts)>=8)return true;
    }
    return false;
}
function issue_public_form_token(): string {
    if(session_status()!==PHP_SESSION_ACTIVE)return '';
    $now=time();
    $tokens=is_array($_SESSION['cms_public_form_tokens']??null)?$_SESSION['cms_public_form_tokens']:[];
    foreach($tokens as $token=>$presentedAt) {
        if(!is_string($token)||(int)$presentedAt<$now-7200)unset($tokens[$token]);
    }
    if(count($tokens)>=12)$tokens=array_slice($tokens,-11,null,true);
    $token=bin2hex(random_bytes(24));
    $tokens[$token]=$now;
    $_SESSION['cms_public_form_tokens']=$tokens;
    return $token;
}
function public_form_token_is_old_enough(string $token,int $minimumSeconds): bool {
    if(session_status()!==PHP_SESSION_ACTIVE||!preg_match('/^[a-f0-9]{48}$/',$token))return false;
    $presentedAt=(int)($_SESSION['cms_public_form_tokens'][$token]??0);
    if($presentedAt<=0)return false;
    $age=time()-$presentedAt;
    return $age>=max(0,$minimumSeconds)&&$age<=7200;
}
function public_form_rate_status(array $config): string {
    if(session_status()!==PHP_SESSION_ACTIVE)return 'unavailable';
    $now=time();
    $sent=is_array($_SESSION['cms_public_form_submissions']??null)?$_SESSION['cms_public_form_submissions']:[];
    $sent=array_values(array_filter(array_map('intval',$sent),static fn(int $timestamp):bool=>$timestamp>$now-3600&&$timestamp<=$now));
    $_SESSION['cms_public_form_submissions']=$sent;
    $last=$sent?(int)end($sent):0;
    if($last>0&&$now-$last<(int)$config['cooldown_seconds'])return 'cooldown';
    if(count($sent)>=(int)$config['maximum_per_hour'])return 'hourly';
    return 'ok';
}
function record_public_form_submission(): void {
    if(session_status()!==PHP_SESSION_ACTIVE)return;
    $now=time();
    $sent=is_array($_SESSION['cms_public_form_submissions']??null)?$_SESSION['cms_public_form_submissions']:[];
    $sent=array_values(array_filter(array_map('intval',$sent),static fn(int $timestamp):bool=>$timestamp>$now-3600&&$timestamp<=$now));
    $sent[]=$now;
    $_SESSION['cms_public_form_submissions']=array_slice($sent,-50);
}
function public_form_is_obvious_sales_solicitation(array $values): bool {
    $text=trim(implode(' ',array_map(static fn($value):string=>(string)$value,$values)));
    if($text==='')return false;
    $normalized=function_exists('mb_strtolower')?mb_strtolower($text,'UTF-8'):strtolower($text);
    $urlCount=preg_match_all('~(?:https?://|www\.)[^\s<]+~iu',$normalized,$urls);
    $promotionalTerms=[
        'seo services','backlinks','link building','guest post','guest posting','digital marketing',
        'lead generation','website audit','ranking offer','rank your website','video editing',
        'promotional video','marketing agency','agency services','unsolicited partnership',
        'servicios de seo','marketing digital','generación de leads','generacion de leads',
        'construcción de enlaces','construccion de enlaces','auditoría web','auditoria web',
        'posicionamiento web','edición de video','edicion de video','videos promocionales','servicios de agencia',
    ];
    $offerPhrases=[
        'we offer','we provide','our agency','our company provides','our team offers',
        'i offer','i provide','can help you rank','boost your ranking','grow your traffic',
        'servicios de seo','ofrecemos servicios','nuestra agencia','generación de leads',
        'construcción de enlaces','mejorar su posicionamiento',
    ];
    $providerSignals=['our agency','marketing agency','agency services','we offer','we provide','ofrecemos servicios','nuestra agencia'];
    $termCount=0;
    foreach($promotionalTerms as $term)if(str_contains($normalized,$term))$termCount++;
    $offerCount=0;
    foreach($offerPhrases as $phrase)if(str_contains($normalized,$phrase))$offerCount++;
    $providerCount=0;
    foreach($providerSignals as $signal)if(str_contains($normalized,$signal))$providerCount++;
    $urlCount=$urlCount===false?0:$urlCount;
    return ($offerCount>=1&&$termCount>=2)
        ||($urlCount>=2&&$termCount>=2)
        ||($termCount>=4&&$providerCount>=1);
}
function verify_public_turnstile(string $token,array $settings): array {
    if($token===''||strlen($token)>2048)return ['ok'=>false,'reason'=>'missing_or_invalid_token'];
    if(!function_exists('curl_init'))return ['ok'=>false,'reason'=>'curl_unavailable'];
    try {
        $secret=secret_decrypt((string)($settings['turnstile_secret_key']??''));
        if($secret==='')return ['ok'=>false,'reason'=>'secret_unavailable'];
        $ch=curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        if($ch===false)return ['ok'=>false,'reason'=>'request_unavailable'];
        curl_setopt_array($ch,[
            CURLOPT_POST=>true,
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=>4,
            CURLOPT_TIMEOUT=>8,
            CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS=>http_build_query(['secret'=>$secret,'response'=>$token]),
        ]);
        $raw=curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        $curlError=curl_error($ch);
        curl_close($ch);
        if(!is_string($raw)||$status<200||$status>=300) {
            error_log('[CMS Turnstile] Siteverify request failed. HTTP '.$status.($curlError!==''?' cURL: '.$curlError:''));
            return ['ok'=>false,'reason'=>'verification_unavailable'];
        }
        $result=json_decode($raw,true);
        if(!is_array($result))return ['ok'=>false,'reason'=>'invalid_response'];
        if(($result['success']??false)!==true) {
            $codes=is_array($result['error-codes']??null)?implode(',',array_map('strval',$result['error-codes'])):'unknown';
            error_log('[CMS Turnstile] Token rejected: '.$codes);
            return ['ok'=>false,'reason'=>'token_rejected'];
        }
        if((string)($result['action']??'')!=='contact_inquiry') {
            error_log('[CMS Turnstile] Token action mismatch.');
            return ['ok'=>false,'reason'=>'action_mismatch'];
        }
        return ['ok'=>true,'reason'=>'verified'];
    } catch(Throwable $e) {
        error_log('[CMS Turnstile] '.$e::class.': '.$e->getMessage());
        return ['ok'=>false,'reason'=>'verification_error'];
    }
}
function analytics_snapshot(array $settings): array {
    $today=date('Y-m-d');
    $storedDate=(string)($settings['analytics_today_date']??'');
    $historyRaw=(string)($settings['analytics_daily_history']??'');
    $history=json_decode($historyRaw,true);
    if(!is_array($history))$history=[];
    $normalized=[];
    foreach($history as $date=>$count){
        $date=(string)$date;
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))continue;
        $normalized[$date]=max(0,(int)$count);
    }
    ksort($normalized);
    $weekly=0; $monthly=0;
    $todayTs=strtotime($today);
    foreach($normalized as $date=>$count){
        $ts=strtotime($date);
        if($ts===false)continue;
        $days=(int)floor(($todayTs-$ts)/86400);
        if($days>=0 && $days<7)$weekly += $count;
        if($days>=0 && $days<30)$monthly += $count;
    }
    return [
        'enabled'=>setting_enabled($settings,'analytics_tracking_enabled',true),
        'total'=>max(0,(int)($settings['analytics_total_visits']??0)),
        'today'=>$storedDate===$today?max(0,(int)($settings['analytics_today_visits']??0)):0,
        'today_date'=>$storedDate,
        'week'=>$weekly,
        'month'=>$monthly,
        'last_visit_at'=>trim((string)($settings['analytics_last_visit_at']??'')),
        'history'=>$normalized,
    ];
}
function public_request_is_known_bot(): bool {
    $agent=trim((string)($_SERVER['HTTP_USER_AGENT']??''));
    if($agent==='')return true;
    return (bool)preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|whatsapp|telegrambot|preview|uptime|monitor|pingdom|headless|lighthouse|pagespeed|validator|checker/i',$agent);
}
function public_request_is_https(): bool {
    if(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')return true;
    if((string)($_SERVER['SERVER_PORT']??'')==='443')return true;
    $forwardedProto=strtolower(trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''))[0]));
    return $forwardedProto==='https';
}
function track_anonymous_public_visit(array $settings,bool $isPreview=false): void {
    if($isPreview||!setting_enabled($settings,'analytics_tracking_enabled',true)||public_request_is_known_bot())return;

    $today=date('Y-m-d');
    $cookieName='cms_public_visit_day';
    if(hash_equals($today,(string)($_COOKIE[$cookieName]??'')))return;

    $pdo=null;
    try {
        $pdo=db();
        $pdo->beginTransaction();
        $seed=$pdo->prepare('INSERT IGNORE INTO settings(setting_key,setting_value) VALUES(?,?)');
        foreach([
            'analytics_tracking_enabled'=>'1',
            'analytics_total_visits'=>'0',
            'analytics_today_date'=>'',
            'analytics_today_visits'=>'0',
            'analytics_last_visit_at'=>'',
            'analytics_daily_history'=>'{}',
        ] as $key=>$value)$seed->execute([$key,$value]);

        $keys=['analytics_tracking_enabled','analytics_total_visits','analytics_today_date','analytics_today_visits','analytics_last_visit_at','analytics_daily_history'];
        $locked=$pdo->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('".implode("','",$keys)."') FOR UPDATE")->fetchAll();
        $current=[];
        foreach($locked as $row)$current[(string)$row['setting_key']]=(string)$row['setting_value'];
        if(($current['analytics_tracking_enabled']??'1')!=='1') {
            $pdo->rollBack();
            return;
        }

        $total=max(0,(int)($current['analytics_total_visits']??0))+1;
        $todayVisits=($current['analytics_today_date']??'')===$today
            ?max(0,(int)($current['analytics_today_visits']??0))+1
            :1;
        $history=json_decode((string)($current['analytics_daily_history']??'{}'),true);
        if(!is_array($history))$history=[];
        $history[$today]=$todayVisits;
        foreach(array_keys($history) as $date){
            if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$date))unset($history[$date]);
        }
        ksort($history);
        if(count($history)>400)$history=array_slice($history,-400,null,true);
        $update=$pdo->prepare('UPDATE settings SET setting_value=? WHERE setting_key=?');
        foreach([
            'analytics_total_visits'=>(string)$total,
            'analytics_today_date'=>$today,
            'analytics_today_visits'=>(string)$todayVisits,
            'analytics_last_visit_at'=>date('Y-m-d H:i:s'),
            'analytics_daily_history'=>json_encode($history,JSON_UNESCAPED_SLASHES),
        ] as $key=>$value)$update->execute([$value,$key]);
        $pdo->commit();

        setcookie($cookieName,$today,[
            'expires'=>strtotime('tomorrow')-1,
            'path'=>'/',
            'secure'=>public_request_is_https(),
            'httponly'=>true,
            'samesite'=>'Lax',
        ]);
        $_COOKIE[$cookieName]=$today;
    } catch(Throwable $e) {
        if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
        error_log('[CMS anonymous analytics] '.$e::class.': '.$e->getMessage());
    }
}
function secure_file_mime_type(string $path): string {
    if (!class_exists('finfo') || !defined('FILEINFO_MIME_TYPE')) {
        throw new RuntimeException(
            'Secure file upload is unavailable because the PHP Fileinfo extension is disabled. '
            .'In WHM, open Software > EasyApache 4 > Customize > PHP Extensions, enable fileinfo for this site PHP version, provision the change, and try again.'
        );
    }
    if ($path === '' || !is_file($path) || !is_readable($path)) {
        throw new RuntimeException('The uploaded file could not be inspected. Please choose the file again.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($path);
    if (!is_string($mime) || trim($mime) === '') {
        throw new RuntimeException('The uploaded file type could not be verified securely. Please choose a valid file and try again.');
    }
    return strtolower(trim($mime));
}
function upload_image(array $file, string $subdir, string $prefix, int $maxMb = 8): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Image upload failed.');
    if (($file['size'] ?? 0) > $maxMb * 1024 * 1024) throw new RuntimeException("Image exceeds {$maxMb} MB.");
    $mime=secure_file_mime_type((string)($file['tmp_name']??''));
    $allowed=['image/jpeg'=>'jpg',
    'image/png'=>'png',
    'image/webp'=>'webp'];
    if (!isset($allowed[$mime])) throw new RuntimeException('Only JPG, PNG and WEBP images are allowed.');
    $dir=UPLOAD_DIR.'/'.trim($subdir,'/');
    if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Could not create upload directory.');
    $name=preg_replace('/[^a-z0-9_-]+/i','-',$prefix).'-'.date('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$allowed[$mime];
    $target=$dir.'/'.$name;
    if(!move_uploaded_file($file['tmp_name'],$target))throw new RuntimeException('Could not save uploaded image.');
    return 'uploads/'.trim($subdir,'/').'/'.$name;
}
function upload_media_file(array $file, string $subdir='media', string $prefix='media', int $maxMb = 60): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Media upload failed.');
    if (($file['size'] ?? 0) > $maxMb * 1024 * 1024) throw new RuntimeException("Media file exceeds {$maxMb} MB.");
    $mime=secure_file_mime_type((string)($file['tmp_name']??''));
    $allowed=[ 'image/jpeg'=>'jpg',
    'image/png'=>'png',
    'image/webp'=>'webp',
    'video/mp4'=>'mp4',
    'video/webm'=>'webm',
    'application/pdf'=>'pdf' ];
    if(!isset($allowed[$mime])) throw new RuntimeException('Allowed media: JPG, PNG, WEBP, MP4, WEBM and PDF.');
    $dir=UPLOAD_DIR.'/'.trim($subdir,'/');
    if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Could not create upload directory.');
    $name=preg_replace('/[^a-z0-9_-]+/i','-',$prefix).'-'.date('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$allowed[$mime];
    $target=$dir.'/'.$name;
    if(!move_uploaded_file($file['tmp_name'],$target))throw new RuntimeException('Could not save uploaded media.');
    return 'uploads/'.trim($subdir,'/').'/'.$name;
}
function normalized_files(string $field): array {
    if(empty($_FILES[$field])) return [];
    $src=$_FILES[$field];
    $out=[];
    if(!is_array($src['name'])) return [$src];
    foreach($src['name'] as $i=>$name) {
        if(($src['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;
        $out[]=['name'=>$name,
        'type'=>$src['type'][$i]??'',
        'tmp_name'=>$src['tmp_name'][$i]??'',
        'error'=>$src['error'][$i]??UPLOAD_ERR_NO_FILE,
        'size'=>$src['size'][$i]??0];
    }
    return $out;
}
function app_key(): string {
    $file=__DIR__.'/app.key';
    if(!is_file($file)) {
        $key=random_bytes(32);
        if(@file_put_contents($file,base64_encode($key),LOCK_EX)===false) throw new RuntimeException('Unable to create config/app.key. Check write permissions.');
        @chmod($file,0600);
        return $key;
    }
    $raw=base64_decode(trim((string)file_get_contents($file)),true);
    if($raw===false||strlen($raw)<32) throw new RuntimeException('Invalid application encryption key.');
    return substr($raw,0,32);
}
function secret_encrypt(?string $value): string {
    $value=trim((string)$value);
    if($value==='')return '';
    $iv=random_bytes(12);
    $tag='';
    $cipher=openssl_encrypt($value,'aes-256-gcm',app_key(),OPENSSL_RAW_DATA,$iv,$tag);
    if($cipher===false)throw new RuntimeException('Unable to encrypt secret.');
    return 'v1:'.base64_encode($iv.$tag.$cipher);
}
function secret_decrypt(?string $value): string {
    $value=(string)$value;
    if($value===''||!str_starts_with($value,'v1:'))return $value;
    $raw=base64_decode(substr($value,3),true);
    if($raw===false||strlen($raw)<29)return '';
    $iv=substr($raw,0,12);
    $tag=substr($raw,12,16);
    $cipher=substr($raw,28);
    $plain=openssl_decrypt($cipher,'aes-256-gcm',app_key(),OPENSSL_RAW_DATA,$iv,$tag);
    return $plain===false?'':$plain;
}
function draft_content(): array {
    $rows=db()->query('SELECT content_key,content_value FROM content_drafts')->fetchAll();
    $o=[];
    foreach($rows as $r)$o[$r['content_key']]=$r['content_value'];
    return $o;
}
function site_sections(): array {
    try {
        $rows=db()->query('SELECT * FROM site_sections ORDER BY sort_order,section_key')->fetchAll();
    } catch(Throwable $e) {
        return [];
    }
    $o=[];
    $defaultNavigationKeys=['home','about','services','videos','gallery','areas','estimate','contact'];
    foreach($rows as $r) {
        $key=(string)$r['section_key'];
        $r['anchor_id']=$r['anchor_id']??$key;
        $r['navigation_label']=$r['navigation_label']??$r['label'];
        $r['show_in_navigation']=$r['show_in_navigation']??(in_array($key,$defaultNavigationKeys,true)?1:0);
        $r['navigation_style']=$r['navigation_style']??($key==='estimate'?'cta':'link');
        $o[$key]=$r;
    }
    return $o;
}
function section_enabled(string $key): bool {
    static $s=null;
    if($s===null)$s=site_sections();
    return !isset($s[$key])||(int)$s[$key]['active']===1;
}
function section_order(string $key,int $default=999): int {
    static $s=null;
    if($s===null)$s=site_sections();
    return isset($s[$key])?(int)$s[$key]['sort_order']:$default;
}
function section_anchor(string $key,string $default=''): string {
    static $s=null;
    if($s===null)$s=site_sections();
    $anchor=(string)($s[$key]['anchor_id']??($default!==''?$default:$key));
    $anchor=preg_replace('/[^a-zA-Z0-9_-]/','',$anchor);
    return $anchor!==''?$anchor:($default!==''?$default:$key);
}
function public_navigation_sections(array $availability=[]): array {
    $items=[];
    foreach(site_sections() as $section) {
        $key=(string)$section['section_key'];
        if((int)$section['active']!==1||(int)$section['show_in_navigation']!==1)continue;
        if(array_key_exists($key,$availability)&&$availability[$key]===false)continue;
        $anchor=preg_replace('/[^a-zA-Z0-9_-]/','',(string)$section['anchor_id']);
        if($anchor==='')continue;
        $items[]=[
            'section_key'=>$key,
            'label'=>(string)($section['navigation_label']?:$section['label']),
            'anchor_id'=>$anchor,
            'navigation_style'=>(string)$section['navigation_style']==='cta'?'cta':'link',
            'sort_order'=>(int)$section['sort_order'],
        ];
    }
    return $items;
}
function log_activity(string $type,string $description,array $metadata=[]): void {
    try {
        $adminId=!empty($_SESSION['escms_admin_id'])?(int)$_SESSION['escms_admin_id']:null;
        $st=db()->prepare('INSERT INTO activity_log(admin_id,action_type,description,metadata_json) VALUES(?,?,?,?)');
        $st->execute([$adminId,$type,$description,$metadata?json_encode($metadata,JSON_UNESCAPED_UNICODE):null]);
    } catch(Throwable $e) {
    }
}
function admin_notify(string $type,string $title,string $message,string $url=''): void {
    try {
        $st=db()->prepare('INSERT INTO admin_notifications(notification_type,title,message,action_url) VALUES(?,?,?,?)');
        $st->execute([$type,$title,$message,$url?:null]);
    } catch(Throwable $e) {
    }
}
function media_add(string $path,string $title=''): void {
    try {
        $full=ROOT_DIR.'/'.$path;
        $mime=is_file($full)?secure_file_mime_type($full):'';
        $size=is_file($full)?filesize($full):0;
        $adminId=!empty($_SESSION['escms_admin_id'])?(int)$_SESSION['escms_admin_id']:null;
        $st=db()->prepare('INSERT INTO media_library(title,file_path,mime_type,file_size,uploaded_by) VALUES(?,?,?,?,?)');
        $st->execute([$title,$path,$mime,(int)$size,$adminId]);
    } catch(Throwable $e) {
    }
}
