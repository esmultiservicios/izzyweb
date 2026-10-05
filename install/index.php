<?php
declare(strict_types=1);

session_start();

// Recargar el instalador siempre inicia una sesión limpia del asistente.
// El navegador redirige aquí con ?reset_installer=1 cuando detecta una recarga real.
if ((string)($_GET['reset_installer'] ?? '') === '1') {
    unset(
        $_SESSION['cms_install_state'],
        $_SESSION['cms_installer_flash'],
        $_SESSION['cms_installer_csrf']
    );
    $_SESSION['cms_installer_force_defaults']=1;
    header('Location: index.php?step=1');
    exit;
}

const CMS_INSTALLER_VERSION = '4.7.3';

$root=dirname(__DIR__);
$configDirectory=$root.'/config';
$configFile=$configDirectory.'/config.php';
$legacyConfigFile=$configDirectory.'/database.php';
$lockFile=$configDirectory.'/install.lock';
$keyFile=$configDirectory.'/app.key';
$schemaFile=is_file($root.'/schema.sql')?$root.'/schema.sql':$root.'/database.sql';

// An active installation lock plus an active database configuration means the site
// is already installed. In that state /install/ is never exposed again: visitors are
// returned to the public site. The Security Center "Preparar instalación nueva" action
// moves both the lock and active configuration out of the way before redirecting here,
// so a deliberate fresh installation can still start normally.
$installationIsLocked=is_file($lockFile) && (is_file($configFile) || is_file($legacyConfigFile));
if($installationIsLocked) {
    header('Location: ../',true,303);
    exit;
}

function installer_h(mixed $value): string
{
    return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
}

function installer_site_url(): string
{
    $forwardedProto=strtolower(trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''))[0]));
    $https=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')
        ||(string)($_SERVER['SERVER_PORT']??'')==='443'
        ||$forwardedProto==='https';
    $scheme=$https?'https':'http';

    $forwardedHost=trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_HOST']??''))[0]);
    $host=$forwardedHost!==''?$forwardedHost:trim((string)($_SERVER['HTTP_HOST']??''));
    if($host==='')$host=trim((string)($_SERVER['SERVER_NAME']??'localhost'));
    if(!preg_match('/^[A-Za-z0-9.\-\[\]:]+$/',$host))$host='localhost';

    $script=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??'/install/index.php'));
    $position=strrpos($script,'/install/');
    $basePath=$position===false?dirname(dirname($script)):substr($script,0,$position);
    $basePath='/'.trim(str_replace('\\','/',$basePath),'/');
    if($basePath==='/')$basePath='';

    return $scheme.'://'.$host.$basePath;
}

function installer_normalize_site_url(string $candidate,string $fallback): string
{
    $candidate=trim($candidate);
    if($candidate==='')return $fallback;
    $parts=parse_url($candidate);
    if(!is_array($parts))return $fallback;
    $scheme=strtolower((string)($parts['scheme']??''));
    $host=(string)($parts['host']??'');
    if(!in_array($scheme,['http','https'],true)||$host==='')return $fallback;
    $port=isset($parts['port'])?':'.(int)$parts['port']:'';
    $path=(string)($parts['path']??'');
    $path=preg_replace('#/install(?:/.*)?$#i','',$path)??'';
    $path='/'.trim($path,'/');
    if($path==='/')$path='';
    return $scheme.'://'.$host.$port.$path;
}

function installer_suggested_database_name(): string
{
    return 'izzy_web';
}

function installer_load_config(string $primary,string $legacy): array
{
    foreach([$primary,$legacy] as $file) {
        if(!is_file($file))continue;
        try {
            $configuration=require $file;
            if(is_array($configuration))return $configuration;
        } catch(Throwable $ignored) {
        }
    }
    return [];
}

function installer_csrf_token(): string
{
    if(empty($_SESSION['cms_installer_csrf']))$_SESSION['cms_installer_csrf']=bin2hex(random_bytes(32));
    return (string)$_SESSION['cms_installer_csrf'];
}

function installer_verify_csrf(): void
{
    $expected=(string)($_SESSION['cms_installer_csrf']??'');
    if($expected===''||!hash_equals($expected,(string)($_POST['csrf']??''))) {
        throw new RuntimeException('La sesión del instalador expiró. Recarga la página e inténtalo nuevamente.');
    }
}

function installer_connect(array $configuration,bool $withDatabase=true): PDO
{
    $host=trim((string)($configuration['host']??''));
    $port=max(1,min(65535,(int)($configuration['port']??3306)));
    $dbname=trim((string)($configuration['dbname']??''));
    $username=(string)($configuration['username']??'');
    $password=(string)($configuration['password']??'');
    if($host===''||$username===''||($withDatabase&&$dbname==='')) {
        throw new RuntimeException('Completa los datos obligatorios de conexión.');
    }
    $dsn='mysql:host='.$host.';port='.$port.';charset=utf8mb4';
    if($withDatabase)$dsn.=';dbname='.$dbname;
    return new PDO($dsn,$username,$password,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
}

function installer_schema_sql(string $schemaFile): string
{
    $sql=file_get_contents($schemaFile);
    if(!is_string($sql)||trim($sql)==='')throw new RuntimeException('No se pudo leer schema.sql.');
    return preg_replace('/^\xEF\xBB\xBF/','',$sql)??$sql;
}

function installer_project_tables(string $sql): array
{
    preg_match_all('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?/i',$sql,$matches);
    $tables=array_values(array_unique(array_map('strval',$matches[1]??[])));
    if(!$tables)throw new RuntimeException('No se pudieron identificar las tablas propias del CMS en schema.sql.');
    return $tables;
}

function installer_sql_statements(string $sql): array
{
    $clean=[];
    foreach(preg_split('/\R/',$sql)?:[] as $line) {
        if(preg_match('/^\s*--/',$line))continue;
        $clean[]=$line;
    }
    $sql=implode("\n",$clean);
    $statements=preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[];
    return array_values(array_filter(array_map('trim',$statements),static fn(string $statement):bool=>$statement!==''));
}

function installer_reset_project_schema(PDO $pdo,string $sql): void
{
    $tables=installer_project_tables($sql);
    $foreignKeysDisabled=false;
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $foreignKeysDisabled=true;
        foreach(array_reverse($tables) as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `'.str_replace('`','',$table).'`');
        }
        foreach(installer_sql_statements($sql) as $statement)$pdo->exec($statement);
    } finally {
        if($foreignKeysDisabled) {
            try {$pdo->exec('SET FOREIGN_KEY_CHECKS=1');}catch(Throwable $ignored) {}
        }
    }
}

function installer_ensure_app_key(string $keyFile): void
{
    if(is_file($keyFile))return;
    $key=base64_encode(random_bytes(32));
    if(file_put_contents($keyFile,$key,LOCK_EX)===false) {
        throw new RuntimeException('No se pudo crear config/app.key. Revisa los permisos de la carpeta config.');
    }
    @chmod($keyFile,0600);
}

function installer_atomic_write(string $target,string $contents,int $permissions=0600): void
{
    $temporary=$target.'.tmp-'.bin2hex(random_bytes(5));
    try {
        if(file_put_contents($temporary,$contents,LOCK_EX)===false)throw new RuntimeException('No se pudo escribir '.basename($target).'.');
        @chmod($temporary,$permissions);
        if(!@rename($temporary,$target))throw new RuntimeException('No se pudo finalizar '.basename($target).'.');
    } finally {
        if(is_file($temporary))@unlink($temporary);
    }
}

function installer_config_contents(array $configuration,string $siteUrl): string
{
    $stored=[
        'host'=>(string)$configuration['host'],
        'port'=>(int)$configuration['port'],
        'dbname'=>(string)$configuration['dbname'],
        'username'=>(string)$configuration['username'],
        'password'=>(string)$configuration['password'],
        'charset'=>'utf8mb4',
        'site_url'=>$siteUrl,
    ];
    return "<?php\ndeclare(strict_types=1);\n\nreturn ".var_export($stored,true).";\n";
}

function installer_email_configuration(string $method,array $previous=[]): array
{
    if(!function_exists('openssl_encrypt')) {
        throw new RuntimeException('OpenSSL es obligatorio para proteger las credenciales de correo.');
    }
    $destinationField=$method==='SMTP'?'smtp_destinatario':'graph_destinatario';
    $destination=trim((string)($_POST[$destinationField]??''));
    if($destination!==''&&!filter_var($destination,FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('El correo de destino interno no es válido.');
    }
    if($method==='SMTP') {
        $plainSecret=(string)($_POST['smtp_password']??'');
        $storedSecret=$plainSecret!==''?secret_encrypt($plainSecret):(string)($previous['password']??'');
        return [
            'metodo_envio'=>'SMTP',
            'server'=>trim((string)($_POST['smtp_server']??'')),
            'correo'=>trim((string)($_POST['smtp_user']??'')),
            'password'=>$storedSecret,
            'port'=>(int)($_POST['smtp_port']??587),
            'smtp_secure'=>strtolower(trim((string)($_POST['smtp_secure']??'tls'))),
            'tenant_id'=>null,
            'client_id'=>null,
            'client_secret'=>null,
            'graph_user'=>null,
            'destinatario'=>$destination!==''?$destination:null,
            'copia'=>null,
            'save_to_sent_items'=>0,
            'estado'=>1,
        ];
    }

    $plainSecret=(string)($_POST['client_secret']??'');
    $storedSecret=$plainSecret!==''?secret_encrypt($plainSecret):(string)($previous['client_secret']??'');
    $graphUser=trim((string)($_POST['graph_user']??''));
    return [
        'metodo_envio'=>'GRAPH',
        'server'=>'graph.microsoft.com',
        'correo'=>$graphUser,
        'password'=>'',
        'port'=>0,
        'smtp_secure'=>'',
        'tenant_id'=>trim((string)($_POST['tenant_id']??''))?:null,
        'client_id'=>trim((string)($_POST['client_id']??''))?:null,
        'client_secret'=>$storedSecret,
        'graph_user'=>$graphUser?:null,
        'destinatario'=>$destination!==''?$destination:null,
        'copia'=>null,
        'save_to_sent_items'=>isset($_POST['save_to_sent_items'])?1:0,
        'estado'=>1,
    ];
}

function installer_save_email(PDO $pdo,array $configuration): int
{
    $purposeIds=array_map('intval',$pdo->query('SELECT correo_tipo_id FROM correo_tipo ORDER BY correo_tipo_id')->fetchAll(PDO::FETCH_COLUMN));
    if(!$purposeIds)throw new RuntimeException('No se encontraron tipos de correo en el esquema.');
    $pdo->beginTransaction();
    try {
        foreach($purposeIds as $purposeId) {
            $pdo->prepare('UPDATE correo SET estado=2 WHERE correo_tipo_id=?')->execute([$purposeId]);
            $pdo->prepare('INSERT INTO correo(correo_tipo_id,metodo_envio,server,correo,password,port,smtp_secure,tenant_id,client_id,client_secret,graph_user,destinatario,copia,save_to_sent_items,estado,fecha_registro) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW())')->execute([
                $purposeId,$configuration['metodo_envio'],$configuration['server'],$configuration['correo'],$configuration['password'],
                $configuration['port'],$configuration['smtp_secure'],$configuration['tenant_id'],$configuration['client_id'],
                $configuration['client_secret'],$configuration['graph_user'],$configuration['destinatario'],$configuration['copia'],
                $configuration['save_to_sent_items'],
            ]);
        }
        $pdo->commit();
    } catch(Throwable $error) {
        if($pdo->inTransaction())$pdo->rollBack();
        throw $error;
    }
    return count($purposeIds);
}

$savedConfig=installer_load_config($configFile,$legacyConfigFile);
$reinstallMode=(is_file($configFile)||is_file($legacyConfigFile))&&!is_file($lockFile);
$siteUrl=installer_site_url();
$forceInstallerDefaults=!empty($_SESSION['cms_installer_force_defaults']);
unset($_SESSION['cms_installer_force_defaults']);

if(!isset($_SESSION['cms_install_state'])||!is_array($_SESSION['cms_install_state'])) {
    $_SESSION['cms_install_state']=[
        'site_url'=>$siteUrl,
        'reinstall'=>$reinstallMode,
        'db'=>[
            'host'=>$forceInstallerDefaults?'localhost':(string)($savedConfig['host']??'localhost'),
            'port'=>$forceInstallerDefaults?3306:(int)($savedConfig['port']??3306),
            'dbname'=>$forceInstallerDefaults?installer_suggested_database_name():(string)($savedConfig['dbname']??installer_suggested_database_name()),
            'username'=>$forceInstallerDefaults?'':(string)($savedConfig['username']??''),
            'password'=>$forceInstallerDefaults?'':(string)($savedConfig['password']??''),
            'charset'=>'utf8mb4',
        ],
        'step1'=>false,
        'step2'=>false,
        'step3'=>false,
        'admin'=>[],
        'email'=>['method'=>'LATER','configuration'=>[],'saved_count'=>0],
        'email_secrets'=>['SMTP'=>'','GRAPH'=>''],
    ];
}

$state=&$_SESSION['cms_install_state'];
if(!isset($state['email_secrets'])||!is_array($state['email_secrets']))$state['email_secrets']=['SMTP'=>'','GRAPH'=>''];
if(empty($state['site_url']))$state['site_url']=$siteUrl;
$state['reinstall']=$reinstallMode;
$error='';
$notice='';
$flash=is_array($_SESSION['cms_installer_flash']??null)?$_SESSION['cms_installer_flash']:null;
unset($_SESSION['cms_installer_flash']);

$requestedStep=max(1,min(4,(int)($_GET['step']??1)));
$highestStep=1;
if(!empty($state['step1']))$highestStep=2;
if(!empty($state['step2']))$highestStep=3;
if(!empty($state['step3']))$highestStep=4;
$step=min($requestedStep,$highestStep);

if($_SERVER['REQUEST_METHOD']==='POST') {
    $action=(string)($_POST['action']??'');
    try {
        installer_verify_csrf();

        if($action==='database') {
            $state['site_url']=installer_normalize_site_url((string)($_POST['site_url_detected']??''),(string)$state['site_url']);
            $host=trim((string)($_POST['host']??'localhost'));
            $port=(int)($_POST['port']??3306);
            $dbname=trim((string)($_POST['dbname']??''));
            $username=trim((string)($_POST['username']??''));
            $postedPassword=(string)($_POST['password']??'');
            $password=$postedPassword!==''?$postedPassword:(string)($savedConfig['password']??$state['db']['password']??'');

            if($host===''||$dbname===''||$username==='')throw new RuntimeException('Servidor, base de datos y usuario son obligatorios.');
            if($port<1||$port>65535)throw new RuntimeException('El puerto MySQL debe estar entre 1 y 65535.');
            if(!preg_match('/^[A-Za-z0-9_\-]+$/',$dbname))throw new RuntimeException('El nombre de la base de datos solo puede contener letras, números, guion y guion bajo.');
            $configuration=compact('host','port','dbname','username','password');
            $configuration['charset']='utf8mb4';

            // Paso 1 valida únicamente el servidor y las credenciales.
            // La base, el esquema y los datos se crean al confirmar el paso final.
            $server=installer_connect($configuration,false);
            $server->query('SELECT 1')->fetchColumn();
            $server=null;

            // Actualizar el paso 1 sin borrar ningún dato ya capturado en los pasos posteriores.
            // El usuario puede navegar Atrás/Siguiente libremente y conservar todo el borrador
            // hasta que recargue la página y confirme expresamente que desea reiniciarlo.
            $state['db']=$configuration;
            $state['step1']=true;
            $_SESSION['cms_installer_flash']=['type'=>'success','message'=>'Conexión MySQL validada correctamente. La base de datos y el esquema se crearán únicamente al finalizar el asistente.'];
            header('Location: index.php?step=2');
            exit;
        }

        if(empty($state['step1']))throw new RuntimeException('Completa primero la conexión de base de datos.');

        if($action==='administrator') {
            $fullName=trim((string)($_POST['full_name']??''));
            $username=trim((string)($_POST['admin_username']??''));
            $email=trim((string)($_POST['admin_email']??''));
            $password=(string)($_POST['admin_password']??'');
            $confirmation=(string)($_POST['admin_password_confirmation']??'');

            if($fullName==='')throw new RuntimeException('Ingresa el nombre completo del administrador.');
            if(strlen($username)<4||strlen($username)>80)throw new RuntimeException('El usuario debe tener entre 4 y 80 caracteres.');
            if(!preg_match('/^[A-Za-z0-9._-]+$/',$username))throw new RuntimeException('El usuario solo puede contener letras, números, punto, guion y guion bajo.');
            if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Ingresa un correo válido para el administrador.');
            if(strlen($password)<8)throw new RuntimeException('La contraseña debe tener al menos 8 caracteres.');
            if($password!==$confirmation)throw new RuntimeException('Las contraseñas no coinciden.');

            // La cuenta se inserta únicamente al confirmar la instalación final.
            $state['admin']=[
                'full_name'=>$fullName,
                'username'=>$username,
                'email'=>$email,
                'password_hash'=>password_hash($password,PASSWORD_DEFAULT),
                'password_draft'=>$password,
                'password_confirmation_draft'=>$confirmation,
            ];
            // Revalidar Administrador no debe borrar ni invalidar lo ya capturado en Correo.
            $state['step2']=true;
            $_SESSION['cms_installer_flash']=['type'=>'success','message'=>'Datos del administrador guardados de forma temporal. Puedes regresar sin perderlos mientras continúas el asistente.'];
            header('Location: index.php?step=3');
            exit;
        }

        if(empty($state['step2']))throw new RuntimeException('Completa primero la cuenta administradora.');

        if(in_array($action,['test_email','save_email','skip_email'],true)) {
            if($action==='skip_email') {
                $state['email']=['method'=>'LATER','configuration'=>[],'saved_count'=>0];
                $state['email_secrets']=['SMTP'=>'','GRAPH'=>''];
                $state['step3']=true;
                $_SESSION['cms_installer_flash']=['type'=>'info','message'=>'La configuración de correo quedó pendiente. Podrás completarla después desde Administración → Correo.'];
                header('Location: index.php?step=4');
                exit;
            }

            // El correo puede configurarse y probarse antes de crear la base de datos.
            // Su persistencia definitiva se realiza en el paso final.
            $GLOBALS['cms_installer_db_config']=$state['db'];
            require_once $root.'/config/bootstrap.php';
            require_once $root.'/core/EmailService.php';
            $method=strtoupper(trim((string)($_POST['email_method']??'')));
            if(!in_array($method,['SMTP','GRAPH'],true))throw new RuntimeException('Selecciona SMTP o Microsoft Graph.');
            $previous=(array)($state['email']['configuration']??[]);
            if(strtoupper((string)($previous['metodo_envio']??''))!==$method)$previous=[];
            if($method==='SMTP' && array_key_exists('smtp_password',$_POST) && (string)$_POST['smtp_password']!=='') {
                $state['email_secrets']['SMTP']=(string)$_POST['smtp_password'];
            }
            if($method==='GRAPH' && array_key_exists('client_secret',$_POST) && (string)$_POST['client_secret']!=='') {
                $state['email_secrets']['GRAPH']=(string)$_POST['client_secret'];
            }
            $configuration=installer_email_configuration($method,$previous);
            $service=new EmailService();
            $problems=$service->configurationProblems($configuration);
            if($problems)throw new RuntimeException(implode(' ',$problems));

            if($action==='test_email') {
                $testField=$method==='SMTP'?'smtp_test_to':'graph_test_to';
                $testTo=trim((string)($_POST[$testField]??''));
                if($testTo==='')$testTo=EmailService::effectiveInternalDestination($configuration);
                if(!filter_var($testTo,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Ingresa un destinatario válido para la prueba.');
                $previewSettings=[
                    'admin_brand_name'=>'IZZY Web',
                    'company_name'=>'IZZY',
                    'website'=>(string)$state['site_url'],
                ];
                // La prueba usa directamente los valores actuales del formulario; no requiere guardar la configuración en la base de datos.
                $testSubject='Prueba de correo IZZY · '.date('Y-m-d H:i:s');
                $result=$service->send($configuration,$testTo,$testSubject,EmailTemplates::test($method,$previewSettings));
                if(empty($result['success']))throw new RuntimeException((string)($result['message']??'No fue posible enviar el correo de prueba.'));
                $state['email']=['method'=>$method,'configuration'=>$configuration,'saved_count'=>0];
                if(isset($_POST['ajax'])) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'success'=>true,
                        'message'=>(string)($result['message']??'Correo de prueba enviado correctamente.'),
                        'to'=>(string)($result['to']??$testTo),
                        'from'=>(string)($result['from']??EmailService::senderAddress($configuration)),
                        'verified_sent_item'=>(bool)($result['verified_sent_item']??false),
                        'verification_available'=>(bool)($result['verification_available']??false),
                        'request_id'=>(string)($result['request_id']??''),
                    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                    exit;
                }
                $notice=(string)($result['message']??'Correo de prueba enviado correctamente.');
                $step=3;
            } else {
                $state['email']=['method'=>$method,'configuration'=>$configuration,'saved_count'=>0];
                $state['step3']=true;
                $_SESSION['cms_installer_flash']=['type'=>'success','message'=>'Configuración de correo validada. Se guardará definitivamente cuando confirmes la instalación.'];
                header('Location: index.php?step=4');
                exit;
            }
        }

        if($action==='finish') {
            if(empty($state['step3']))throw new RuntimeException('Completa o pospón primero la configuración de correo.');
            if((string)($_POST['confirm_install']??'')!=='1')throw new RuntimeException('Confirma la instalación desde la revisión final para continuar.');

            $configuration=(array)$state['db'];
            $schemaSql=installer_schema_sql($schemaFile);
            $adminState=(array)$state['admin'];
            $emailState=(array)$state['email'];
            if(empty($adminState['password_hash']))throw new RuntimeException('La cuenta administradora no está preparada. Regresa al paso Administrador.');

            $oldConfigExists=is_file($configFile);
            $oldConfigContents=$oldConfigExists?file_get_contents($configFile):null;
            $newConfig=installer_config_contents($configuration,(string)$state['site_url']);

            try {
                // La base de datos se crea solamente ahora, al confirmar el asistente.
                $server=installer_connect($configuration,false);
                $databaseName=str_replace('`','',(string)$configuration['dbname']);
                $server->exec('CREATE DATABASE IF NOT EXISTS `'.$databaseName.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $server=null;

                $pdo=installer_connect($configuration,true);
                installer_reset_project_schema($pdo,$schemaSql);
                installer_ensure_app_key($keyFile);

                $roleId=(int)$pdo->query("SELECT id FROM admin_roles WHERE role_key='owner' LIMIT 1")->fetchColumn();
                if($roleId<1)throw new RuntimeException('No se encontró el rol Owner en el esquema instalado.');
                $insert=$pdo->prepare('INSERT INTO admin_users(username,full_name,email,password_hash,role_id,active) VALUES(?,?,?,?,?,1)');
                $insert->execute([
                    (string)$adminState['username'],
                    (string)$adminState['full_name'],
                    (string)$adminState['email'],
                    (string)$adminState['password_hash'],
                    $roleId,
                ]);
                $state['admin']['id']=(int)$pdo->lastInsertId();

                $emailMethod=strtoupper((string)($emailState['method']??'LATER'));
                if(in_array($emailMethod,['SMTP','GRAPH'],true)) {
                    $savedCount=installer_save_email($pdo,(array)$emailState['configuration']);
                    $state['email']['saved_count']=$savedCount;
                }

                installer_atomic_write($configFile,$newConfig);
                $adminEmail=trim((string)($adminState['email']??''));
                $_SESSION['cms_login_prefill']=$adminEmail!==''?$adminEmail:(string)($adminState['username']??'');
                $_SESSION['cms_install_completed_notice']=1;

                if(in_array($emailMethod,['SMTP','GRAPH'],true)&&$adminEmail!==''&&filter_var($adminEmail,FILTER_VALIDATE_EMAIL)) {
                    try {
                        $GLOBALS['cms_installer_db_config']=$configuration;
                        require_once $root.'/config/bootstrap.php';
                        require_once $root.'/core/EmailService.php';
                        $loginUrl=rtrim((string)$state['site_url'],'/').'/admin/login.php';
                        $welcome=(new EmailService())->send(
                            (array)$emailState['configuration'],
                            $adminEmail,
                            'Bienvenido a IZZY',
                            EmailTemplates::administratorWelcome((string)($adminState['full_name']??$adminState['username']??'Administrador'),$loginUrl,settings())
                        );
                        $_SESSION['cms_login_notice']=!empty($welcome['success'])
                            ?['type'=>'success','message'=>'IZZY quedó instalado correctamente y el correo de bienvenida fue enviado. Ingresa al panel para personalizar el sitio, los planes, el contenido y la información de contacto.']
                            :['type'=>'warning','message'=>'IZZY quedó instalado correctamente, pero no se pudo enviar el correo de bienvenida. Ingresa al panel y revisa Administración → Correo.'];
                    } catch(Throwable $welcomeError) {
                        $_SESSION['cms_login_notice']=['type'=>'warning','message'=>'IZZY quedó instalado correctamente, pero no se pudo enviar el correo de bienvenida. Ingresa al panel y revisa Administración → Correo.'];
                    }
                } else {
                    $_SESSION['cms_login_notice']=['type'=>'success','message'=>'IZZY quedó instalado correctamente. Ingresa al panel para personalizar el sitio y dar a conocer IZZY; el correo puede configurarse después desde Administración → Correo.'];
                }

                $sessionGeneration=bin2hex(random_bytes(24));
                $lockPayload=json_encode([
                    'installed_at'=>date(DATE_ATOM),
                    'site_url'=>(string)$state['site_url'],
                    'installer_version'=>CMS_INSTALLER_VERSION,
                    'session_generation'=>$sessionGeneration,
                ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                if(!is_string($lockPayload))throw new RuntimeException('No se pudo preparar el archivo de instalación.');
                installer_atomic_write($lockFile,$lockPayload."\n");
            } catch(Throwable $finishError) {
                if($oldConfigExists&&is_string($oldConfigContents)) {
                    installer_atomic_write($configFile,$oldConfigContents);
                } elseif(is_file($configFile)) {
                    @unlink($configFile);
                }
                if(is_file($lockFile))@unlink($lockFile);
                throw $finishError;
            }
            // La reinstalación siempre termina con una sesión nueva y sin autenticación previa.
            // Conservamos únicamente el mensaje/prefill de bienvenida para mostrarlos en el login.
            $loginPrefill=(string)($_SESSION['cms_login_prefill']??'');
            $loginNotice=$_SESSION['cms_login_notice']??null;
            $installCompletedNotice=!empty($_SESSION['cms_install_completed_notice']);

            $_SESSION=[];
            if(ini_get('session.use_cookies')) {
                $cookie=session_get_cookie_params();
                setcookie(session_name(),'',time()-42000,$cookie['path'],$cookie['domain'],$cookie['secure'],$cookie['httponly']);
            }
            session_destroy();

            // Eliminar también cualquier acceso persistente de administrador en este navegador.
            setcookie('escms_admin_remember','',[
                'expires'=>time()-3600,
                'path'=>'/',
                'secure'=>!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off',
                'httponly'=>true,
                'samesite'=>'Lax',
            ]);
            unset($_COOKIE['escms_admin_remember']);

            session_start();
            session_regenerate_id(true);
            if($loginPrefill!=='')$_SESSION['cms_login_prefill']=$loginPrefill;
            if(is_array($loginNotice))$_SESSION['cms_login_notice']=$loginNotice;
            if($installCompletedNotice)$_SESSION['cms_install_completed_notice']=1;

            header('Location: ../admin/login.php',true,303);
            exit;
        }
    } catch(Throwable $caught) {
        $message=$caught->getMessage();
        if(stripos($message,'access denied')!==false||stripos($message,'denied')!==false) {
            $message.=' Revisa los permisos del usuario MySQL o crea la base desde el panel del hosting.';
        }
        if(isset($_POST['ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(422);
            echo json_encode(['success'=>false,'message'=>$message],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }
        $error=$message;
        $step=max(1,min(4,(int)($_POST['current_step']??$step)));
    }
}

$db=(array)$state['db'];
$admin=(array)$state['admin'];
$email=(array)$state['email'];
$emailConfiguration=(array)($email['configuration']??[]);
$selectedMethod=strtoupper((string)($_POST['email_method']??($email['method']??'LATER')));
if(!in_array($selectedMethod,['LATER','SMTP','GRAPH'],true))$selectedMethod='LATER';
$progress=$step*25;
$stepLabels=[
    1=>['Base de datos','Conexión MySQL'],
    2=>['Administrador','Cuenta principal'],
    3=>['Correo','SMTP o Graph'],
    4=>['Confirmar','Revisión final'],
];
$stepGuides=[
    1=>['Conecta la base de datos','El asistente validará la conexión antes de permitirte continuar.'],
    2=>['Crea la cuenta administradora','Define el usuario principal que tendrá control protegido sobre el CMS.'],
    3=>['Configura el envío de correo','Puedes usar SMTP, Microsoft Graph o dejar la integración para después.'],
    4=>['Revisa antes de instalar','Confirma la configuración. IZZY creará la base si hace falta y finalizará la instalación.'],
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="color-scheme" content="light">
<title>Instalación · IZZY CMS Core</title>
<link rel="icon" type="image/png" sizes="32x32" href="../assets/izzy/logo-mark.png?v=414">
<link rel="shortcut icon" type="image/png" href="../assets/izzy/logo-mark.png?v=414">
<link rel="apple-touch-icon" href="../assets/izzy/logo-mark.png?v=414">
<link rel="stylesheet" href="../assets/vendor/sweetalert2/sweetalert2.min.css">
<link rel="stylesheet" href="../assets/vendor/show-notify/showNotify.css">
<link rel="stylesheet" href="../assets/vendor/cms-modal/cmsModal.css">
<link rel="stylesheet" href="../assets/vendor/select2/select2.local.css">
<link rel="stylesheet" href="../assets/ui-standards.css">
<style>
:root{
  --primary:#0ea5e9;--primary-2:#2563eb;--primary-dark:#071d3a;--primary-ink:#0b2b55;
  --accent:#38bdf8;--accent-2:#22c55e;--surface:#ffffff;--page:#edf5fb;--ink:#122033;
  --muted:#66778a;--line:#d8e5ef;--line-strong:#c8d8e5;--soft:#f6fafe;--soft-2:#eef7ff;
  --success:#0f9f6e;--warning:#a96208;--danger:#b42332;--radius:20px;--shadow:0 28px 80px rgba(7,29,58,.16)
}
*{box-sizing:border-box;min-width:0}html{-webkit-text-size-adjust:100%;scroll-behavior:smooth}body{margin:0;color:var(--ink);font-family:Inter,"Segoe UI",Arial,sans-serif;font-size:16px;line-height:1.5;overflow-x:hidden;background:
radial-gradient(circle at 12% 10%,rgba(56,189,248,.18),transparent 28%),radial-gradient(circle at 88% 8%,rgba(37,99,235,.14),transparent 25%),linear-gradient(135deg,#eef6fb 0%,#f7fbff 46%,#eaf3fb 100%)}
body::before{content:"";position:fixed;inset:0;pointer-events:none;opacity:.33;background-image:linear-gradient(rgba(14,165,233,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(14,165,233,.035) 1px,transparent 1px);background-size:34px 34px}
button,input,select{font:inherit}button,label,input[type=radio],input[type=checkbox]{-webkit-tap-highlight-color:transparent}.page{position:relative;min-height:100vh;display:grid;place-items:center;padding:clamp(14px,2.8vw,36px)}
.installer{position:relative;width:min(1240px,100%);height:auto;min-height:0;display:grid;grid-template-columns:310px minmax(0,1fr);overflow:hidden;background:rgba(255,255,255,.96);border:1px solid rgba(255,255,255,.8);border-radius:30px;box-shadow:var(--shadow),0 0 0 1px rgba(12,74,110,.06);backdrop-filter:blur(18px)}
.installer::after{content:"";position:absolute;inset:0;pointer-events:none;border-radius:inherit;box-shadow:inset 0 1px 0 rgba(255,255,255,.85)}
.side{position:relative;display:flex;flex-direction:column;padding:30px 24px 24px;color:#fff;overflow:hidden;background:linear-gradient(160deg,#071d3a 0%,#0a3158 54%,#0c4776 100%)}
.side::before{content:"";position:absolute;width:260px;height:260px;right:-150px;top:120px;border-radius:50%;background:radial-gradient(circle,rgba(56,189,248,.28),transparent 68%)}
.side::after{content:"";position:absolute;width:220px;height:220px;left:-145px;bottom:70px;border-radius:50%;background:radial-gradient(circle,rgba(34,197,94,.12),transparent 70%)}
.brand,.side h2,.side p,.side-list,.side-foot{position:relative;z-index:1}.brand{display:flex;align-items:center;gap:12px}.brand-mark{width:50px;height:50px;display:flex;align-items:center;justify-content:center;flex:0 0 50px;background:transparent;border:0;border-radius:0;box-shadow:none;overflow:visible}.brand-mark img{display:block;width:50px;height:50px;object-fit:contain;filter:none;box-shadow:none;border:0;border-radius:0}.brand-logo-full{width:126px;height:58px;flex:0 0 126px}.brand-logo-full img{width:126px;height:58px;object-fit:contain}.brand strong{display:block;font-size:16px;letter-spacing:-.01em}.brand small{display:block;margin-top:2px;color:#bed8eb;font-size:12px}.side h2{margin:48px 0 9px;font-size:27px;line-height:1.1;letter-spacing:-.02em}.side p{margin:0;color:#c8dcea;font-size:14px}.side-list{display:grid;gap:10px;margin:26px 0}.side-item{display:grid;grid-template-columns:36px minmax(0,1fr);gap:10px;align-items:center;padding:11px 12px;border:1px solid rgba(255,255,255,.12);border-radius:15px;color:#cfe0ec;background:rgba(255,255,255,.035);transition:.22s ease}.side-item b{width:32px;height:32px;display:grid;place-items:center;border-radius:10px;background:rgba(255,255,255,.09);font-size:13px}.side-item span{font-size:13px}.side-item.active{background:rgba(255,255,255,.98);color:var(--primary-dark);border-color:#fff;box-shadow:0 12px 26px rgba(0,0,0,.14);transform:translateX(3px)}.side-item.active b{background:linear-gradient(145deg,var(--primary),var(--primary-2));color:#fff;box-shadow:0 6px 16px rgba(14,165,233,.25)}.side-item.complete{border-color:rgba(34,197,94,.2)}.side-item.complete b{background:rgba(34,197,94,.18);color:#86efac}.side-foot{margin-top:auto;padding-top:20px;color:#a9c4d7;font-size:11px;line-height:1.5}
.main{min-height:0;display:grid;grid-template-rows:auto auto auto;background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(252,254,255,.98))}.top{padding:24px 30px 0}.top-row{display:flex;align-items:center;justify-content:space-between;gap:14px}.eyebrow{margin:0;color:var(--primary-2);font-size:11px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}.mode{padding:8px 12px;border:1px solid #d6e9f7;border-radius:999px;background:linear-gradient(180deg,#f4fbff,#eaf6ff);color:var(--primary-ink);font-size:11px;font-weight:900;white-space:nowrap;box-shadow:0 5px 14px rgba(14,165,233,.08)}.mode.reinstall{border-color:#f2d29f;background:#fff8eb;color:#875407}.progress{height:7px;margin-top:17px;overflow:hidden;border-radius:999px;background:#e7eef5;box-shadow:inset 0 1px 2px rgba(7,29,58,.06)}.progress span{display:block;height:100%;width:var(--progress);border-radius:inherit;background:linear-gradient(90deg,var(--primary),var(--primary-2));box-shadow:0 0 14px rgba(14,165,233,.45);transition:width .25s ease}
.stepper{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px;padding:16px 30px}.step-tab{display:grid;grid-template-columns:36px minmax(0,1fr);gap:9px;align-items:center;padding:10px 11px;border:1px solid var(--line);border-radius:15px;background:#fff;box-shadow:0 6px 18px rgba(7,29,58,.035);transition:.22s ease}.step-tab b{width:32px;height:32px;display:grid;place-items:center;border-radius:10px;background:#eef3f7;color:#718094;font-size:13px}.step-tab strong,.step-tab small{display:block;overflow-wrap:anywhere}.step-tab strong{font-size:13px}.step-tab small{color:var(--muted);font-size:10.5px}.step-tab.active{border-color:#9ed9f8;background:linear-gradient(180deg,#fbfeff,#f1f9ff);box-shadow:0 10px 22px rgba(14,165,233,.09)}.step-tab.active b{background:linear-gradient(145deg,var(--primary),var(--primary-2));color:#fff;box-shadow:0 6px 14px rgba(14,165,233,.24)}.step-tab.complete{background:#fbfffd;border-color:#cfeede}.step-tab.complete b{background:#e9fbf3;color:var(--success)}
.step-panel{min-height:0;display:grid;grid-template-rows:auto auto;border-top:1px solid #edf2f6}.scroll{min-height:0;overflow:visible;padding:30px 30px 22px}.step-title{position:relative;margin-bottom:24px;padding-left:18px}.step-title::before{content:"";position:absolute;left:0;top:2px;bottom:2px;width:4px;border-radius:999px;background:linear-gradient(180deg,var(--primary),var(--primary-2))}.step-title h1{margin:5px 0 5px;font-size:clamp(29px,3vw,39px);line-height:1.08;letter-spacing:-.035em;color:#0d223b}.step-title p{margin:0;color:var(--muted);font-size:14px}.alert{margin:0 0 18px;padding:14px 16px;border-radius:14px;overflow-wrap:anywhere}.alert.error{border:1px solid #f2c7cd;background:#fff4f5;color:var(--danger)}.alert.success{border:1px solid #bde8d5;background:#f0fbf6;color:var(--success)}.alert.warning{border:1px solid #f0d1a0;background:#fff9ee;color:#7f4d07}.notice{padding:14px 15px;border:1px solid #cfe6f5;border-radius:15px;background:linear-gradient(180deg,#f7fcff,#eff8fe);color:#36546f;font-size:13px}.notice strong{display:block;margin-bottom:3px;color:var(--primary-dark)}
.detected-site-url{display:grid;grid-template-columns:42px minmax(0,1fr);gap:12px;align-items:start;margin:-4px 0 22px;padding:15px 16px;border:1px solid #cde7f7;border-radius:16px;background:linear-gradient(180deg,#f9fdff,#f1f9fe);box-shadow:0 8px 22px rgba(14,165,233,.055)}.detected-site-url-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:12px;background:linear-gradient(145deg,var(--primary),var(--primary-2));color:#fff;font-size:18px;font-weight:900;box-shadow:0 8px 18px rgba(14,165,233,.18)}.detected-site-url small{display:block;color:#47708c;font-size:10px;font-weight:900;letter-spacing:.08em}.detected-site-url strong{display:block;margin-top:3px;color:var(--primary-dark);font-size:14px;overflow-wrap:anywhere}.detected-site-url p{margin:4px 0 0;color:var(--muted);font-size:11.5px;line-height:1.45}@media(max-width:520px){.detected-site-url{grid-template-columns:36px minmax(0,1fr);padding:13px}.detected-site-url-icon{width:36px;height:36px;border-radius:10px}.detected-site-url strong{font-size:13px}}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px 18px;align-items:start}.form-grid.three{grid-template-columns:minmax(0,1.7fr) minmax(145px,.8fr) minmax(190px,1fr)}.field{display:block;font-size:13px;font-weight:800;line-height:1.35}.field.full{grid-column:1/-1}.field-label{display:flex;align-items:center;gap:4px;min-height:21px;white-space:nowrap;color:#17283a}.field>input,.field>select{display:block;margin-top:8px}.field small{display:block;margin-top:7px;color:var(--muted);font-size:11.5px;font-weight:500;line-height:1.5}.required{display:inline-flex;align-items:center;color:var(--danger);margin:0;line-height:1;font-weight:900}input,select{width:100%;max-width:100%;min-height:48px;padding:11px 14px;border:1px solid var(--line-strong);border-radius:13px;background:#fff;color:var(--ink);outline:none;box-shadow:0 2px 0 rgba(7,29,58,.015);transition:border-color .18s ease,box-shadow .18s ease,background .18s ease}input:hover,select:hover{border-color:#b8cada}input:focus,select:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(14,165,233,.12),0 8px 20px rgba(14,165,233,.07);background:#fff}input::placeholder{color:#9aabba}input[type=password]::-ms-reveal,input[type=password]::-ms-clear{display:none}.password-wrap{position:relative;margin-top:8px}.password-wrap input{display:block;width:100%;padding-right:56px}.password-toggle{position:absolute;top:50%;right:7px;transform:translateY(-50%);display:grid;place-items:center;width:36px;min-width:36px;height:36px;min-height:36px;padding:0;border:0;border-radius:10px;background:#eef7fd;color:#1d4f7a;box-shadow:none;cursor:pointer;transition:.18s ease}.password-toggle:hover{transform:translateY(-50%) scale(1.04);background:#dff2ff;color:var(--primary-2)}.password-toggle:focus-visible{outline:3px solid rgba(14,165,233,.28);outline-offset:2px}.password-toggle svg{width:19px;height:19px;display:block}.password-toggle .icon-hide{display:none}.password-toggle[aria-pressed=true] .icon-show{display:none}.password-toggle[aria-pressed=true] .icon-hide{display:block}.db-password{grid-column:2/-1}.check{display:flex!important;align-items:flex-start;gap:11px;padding:14px 15px;border:1px solid var(--line);border-radius:14px;background:linear-gradient(180deg,#f9fcff,#f4f8fc);font-size:12.5px;font-weight:750;color:#294057;box-shadow:0 6px 16px rgba(7,29,58,.03)}.check input{width:19px;min-height:19px;flex:0 0 19px;margin:1px 0;accent-color:var(--primary-2)}.check span{overflow-wrap:anywhere}.create-db-check{grid-column:1/-1;width:min(100%,610px);margin-top:0}.db-password small{max-width:760px}
.method-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.method{position:relative;display:grid;grid-template-columns:18px 42px minmax(0,1fr);gap:10px;align-items:start;padding:15px;border:1px solid var(--line);border-radius:16px;background:#fff;cursor:pointer;box-shadow:0 8px 20px rgba(7,29,58,.04);transition:.2s ease}.method:hover{transform:translateY(-2px);border-color:#b9dbef;box-shadow:0 12px 28px rgba(7,29,58,.08)}.method:has(input:checked){border-color:#92d6f7;background:linear-gradient(180deg,#fbfeff,#eff9ff);box-shadow:0 0 0 2px rgba(14,165,233,.09),0 14px 28px rgba(14,165,233,.08)}.method input{width:16px;min-height:16px;margin:8px 0 0;accent-color:var(--primary-2)}.method-icon{width:40px;height:40px;display:grid;place-items:center;border-radius:11px;background:linear-gradient(145deg,#eef7fd,#e1f0fa);color:var(--primary-2);font-weight:900}.method strong{display:block;font-size:13px}.method small{display:block;margin-top:3px;color:var(--muted);font-size:10.8px;line-height:1.45}.email-fields{margin-top:18px;padding:20px;border:1px solid var(--line);border-radius:18px;background:linear-gradient(180deg,#fbfdff,#f5f9fc);box-shadow:inset 0 1px 0 #fff}[hidden]{display:none!important}.test-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:end}.test-row .field{margin:0}.test-row button{min-width:190px;min-height:48px}.email-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:10px;margin-top:16px}.test-result{margin-top:12px}
.review{display:grid;gap:12px}.review-card{display:grid;grid-template-columns:180px minmax(0,1fr);gap:18px;padding:17px 18px;border:1px solid var(--line);border-radius:16px;background:linear-gradient(180deg,#fbfdff,#f5f9fc);box-shadow:0 7px 20px rgba(7,29,58,.035)}.review-card strong{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em}.review-card span{font-weight:800;overflow-wrap:anywhere}.review-note{margin-top:16px;padding:16px;border:1px solid #bfe5d4;border-radius:16px;background:#f1fbf6;color:#245b48;font-size:13px}
.footer{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 30px 16px;border-top:1px solid #edf2f6;background:rgba(255,255,255,.96);box-shadow:0 -12px 28px rgba(7,29,58,.025)}.footer-right{display:flex;justify-content:flex-end;gap:10px}.button,button{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:46px;padding:10px 18px;border:0;border-radius:12px;background:linear-gradient(145deg,var(--primary),var(--primary-2));color:#fff;text-decoration:none;font-weight:900;font-size:13px;cursor:pointer;box-shadow:0 9px 20px rgba(37,99,235,.18);transition:.18s ease}.button:hover,button:hover{transform:translateY(-1px);box-shadow:0 12px 24px rgba(37,99,235,.24)}.button.secondary{border:1px solid var(--line);background:#fff;color:#2b4560;box-shadow:0 5px 14px rgba(7,29,58,.05)}.button.secondary:hover{border-color:#bdd4e5;background:#f8fbfd}.button:focus-visible,button:focus-visible{outline:3px solid rgba(14,165,233,.28);outline-offset:2px}.button:disabled,button:disabled{opacity:.65;cursor:not-allowed;transform:none}.spinner{display:none;width:15px;height:15px;border:2px solid rgba(255,255,255,.45);border-top-color:#fff;border-radius:50%;animation:spin .65s linear infinite}.loading .spinner{display:inline-block}@keyframes spin{to{transform:rotate(360deg)}}
@media(max-width:1100px) and (min-width:761px){.installer{grid-template-columns:260px minmax(0,1fr)}.side{padding:26px 20px}.side h2{font-size:23px}.form-grid.three{grid-template-columns:repeat(2,minmax(0,1fr))}.form-grid.three .db-host{grid-column:1/-1}.db-password{grid-column:2/-1}.create-db-check{grid-column:1/-1}.step-tab small{display:none}.review-card{grid-template-columns:140px minmax(0,1fr)}}
@media(max-width:900px){.stepper{gap:7px;padding-inline:22px}.top{padding-inline:22px}.scroll{padding-inline:22px}.footer{padding-inline:22px}.method-grid{grid-template-columns:1fr}.method{grid-template-columns:18px 42px minmax(0,1fr)}}
@media(max-width:760px){body{background:#fff;overflow-x:hidden}.page{display:block;padding:0;min-height:100dvh}.installer{width:100%;height:auto;min-height:100dvh;grid-template-columns:1fr;border:0;border-radius:0;box-shadow:none;overflow:hidden}.side{display:none}.top{padding:16px 15px 0}.top-row{align-items:flex-start}.mode{padding:7px 10px}.stepper{padding:12px 15px;gap:6px}.step-tab{grid-template-columns:1fr;justify-items:center;padding:8px 4px;border-radius:12px}.step-tab b{width:30px;height:30px}.step-tab div{display:none}.scroll{padding:22px 15px 16px}.step-title{padding-left:14px;margin-bottom:20px}.step-title h1{font-size:30px}.footer{position:relative;padding:12px 15px calc(12px + env(safe-area-inset-bottom,0px))}.form-grid,.form-grid.three,.method-grid,.test-row{grid-template-columns:1fr}.form-grid.three .db-host,.db-password{grid-column:1/-1}.field-label{white-space:normal}.create-db-check{width:100%}.method{grid-template-columns:18px 40px minmax(0,1fr)}.test-row button{width:100%;min-width:0}.review-card{grid-template-columns:1fr;gap:5px}.footer-right{flex:1}.footer-right .button,.footer-right button{flex:1}.email-actions{display:grid;grid-template-columns:1fr}.email-actions button{width:100%}.email-fields{padding:15px}.button,button{min-height:48px}}
@media(max-width:460px){.top-row{gap:8px}.eyebrow{font-size:10px}.mode{font-size:10px}.stepper{padding-top:10px}.scroll{padding-top:18px}.form-grid{gap:16px}.footer{gap:8px}.button,button{padding-inline:13px}.step-title h1{font-size:27px}}
@media(max-height:720px) and (min-width:761px){.page{place-items:start center;padding:9px;min-height:100vh}.installer{height:auto;min-height:0}.side{padding-block:22px}.side h2{margin-top:28px}.side-list{margin-block:16px;gap:8px}.top{padding-top:18px}.stepper{padding-block:11px}.scroll{padding-top:20px}.step-title{margin-bottom:17px}}
@media(max-height:620px) and (min-width:761px){.page{place-items:start center}.installer{height:auto;min-height:0}.side{padding-block:18px}.side h2{margin-top:20px}.side-list{margin-block:12px}}

/* Scroll policy: no inner scrollbars on normal desktop/tablet layouts.
   If the viewport is short, the browser/page scrolls vertically; horizontal overflow is never allowed. */
html,body,.page,.installer,.side,.main,.step-panel,.scroll{max-width:100%}
html,body{overflow-x:hidden}
.side,.main,.step-panel,.scroll{min-width:0}

@media(prefers-reduced-motion:reduce){*,*::before,*::after{transition:none!important;animation:none!important}}

/* IZZY brand: use the complete white wordmark on the dark installer sidebar. */
.brand-stacked{display:flex;flex-direction:column;align-items:flex-start;gap:7px}
.brand-stacked .brand-logo-full{width:138px;height:138px;flex:0 0 138px;display:grid;place-items:center}
.brand-stacked .brand-logo-full img{display:block;width:138px;height:138px;object-fit:contain;object-position:center;filter:none!important;box-shadow:none!important;border:0!important;border-radius:0!important}
.brand-stacked .brand-copy{display:block;padding-left:2px}
.brand-stacked .brand-copy strong{font-size:15px}
.brand-stacked .brand-copy small{font-size:11.5px}
@media(max-height:760px) and (min-width:761px){.brand-stacked .brand-logo-full{width:112px;height:112px;flex-basis:112px}.brand-stacked .brand-logo-full img{width:112px;height:112px}.side h2{margin-top:24px}}



/* v4.3.0 — clean IZZY premium installer: flat colors, generous spacing, no visual saturation */
:root{--primary:#079bd0;--primary-2:#079bd0;--primary-dark:#082a50;--primary-ink:#082a50;--accent:#079bd0;--page:#eef3f6;--ink:#17324d;--muted:#71849a;--line:#dce5ec;--line-strong:#cbd8e2;--soft:#f7fafc;--soft-2:#eef7f9;--shadow:0 18px 42px rgba(17,52,80,.08)}
body{background:#eef3f6!important;background-image:none!important;overflow-x:hidden}
body::before{display:none!important}
.page{display:block;min-height:100vh;padding:14px clamp(10px,1.8vw,22px)}
.installer{width:min(1500px,calc(100vw - 20px));margin:0 auto;display:grid;grid-template-columns:300px minmax(0,1fr);grid-template-areas:"head head" "side main";overflow:visible;background:transparent;border:0;border-radius:0;box-shadow:none;backdrop-filter:none}
.installer::after{display:none}
.installer-head{grid-area:head;min-height:104px;margin-bottom:12px;padding:16px 22px;display:flex;align-items:center;justify-content:space-between;gap:18px;background:#fff;border:1px solid var(--line);border-top:4px solid var(--primary);border-radius:22px;box-shadow:var(--shadow)}
.installer-brand{display:flex;align-items:center;gap:20px;min-width:0}.installer-brand img{display:block;width:154px;height:82px;object-fit:contain;object-position:left center;flex:0 0 auto}.installer-brand div{min-width:0}.installer-brand p{margin:0 0 4px;color:#087f9f;font-size:12px;font-weight:900;letter-spacing:.15em}.installer-brand strong{display:block;color:#082a50;font-size:31px;line-height:1.02;letter-spacing:-.03em}.installer-brand small{display:block;margin-top:6px;color:var(--muted);font-size:14px}
.installer-badge{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 18px;border-radius:13px;background:#082a50;color:#fff;font-size:12.5px;font-weight:900;white-space:nowrap}.installer-badge.reinstall{background:#80540b}
.side{grid-area:side;margin-right:12px;padding:20px 18px 16px;color:var(--ink);background:#fff!important;background-image:none!important;border:1px solid var(--line);border-radius:22px;box-shadow:var(--shadow);overflow:visible}.side::before,.side::after,.brand,.side>h2,.side>p{display:none!important}
.side-progress{display:grid;gap:3px;margin:0 2px 18px}.side-progress strong{font-size:15px;color:#082a50}.side-progress small{font-size:11.5px;color:var(--muted)}.side-progress>div{height:7px;margin-top:8px;background:#edf2f5;border-radius:999px;overflow:hidden}.side-progress>div span{display:block;height:100%;background:#079bd0;border-radius:999px}
.side-list{display:grid;gap:4px;margin:0}.side-item{display:grid;grid-template-columns:36px minmax(0,1fr);gap:11px;align-items:center;padding:10px 11px;border:1px solid transparent;border-radius:14px;color:#24415c;background:#fff;box-shadow:none;transition:transform .18s ease,background .18s ease,border-color .18s ease}.side-item:hover{transform:translateX(2px);background:#f7fafc;border-color:#e5edf2}.side-item b{width:34px;height:34px;border-radius:11px;background:#eef2f5;color:#6c8297;font-size:12.5px}.side-item span{display:block;min-width:0}.side-item span strong{display:block;font-size:13px;line-height:1.24}.side-item span small{display:block;margin-top:2px;color:#8b9aaa;font-size:10px;line-height:1.25}.side-item.active{background:#eaf7f8;color:#075d6f;border-color:#d8eff1;box-shadow:none;transform:none}.side-item.active b{background:#079bad;color:#fff;box-shadow:none}.side-item.complete{border-color:transparent;background:#f8fbfa}.side-item.complete b{background:#e4f6ef;color:#168264}.side-foot{margin-top:16px;padding:13px 3px 0;border-top:1px solid #edf2f5;color:#8a9aaa;font-size:10.5px;line-height:1.45}
.main{grid-area:main;display:block;min-width:0;background:#fff!important;background-image:none!important;border:1px solid var(--line);border-radius:22px;box-shadow:var(--shadow);overflow:hidden}
.main,.side{align-self:start}
.top,.stepper{display:none!important}
.step-panel{display:block;border:0;min-height:0}.scroll{overflow:visible;padding:22px 24px 18px}.step-title{margin-bottom:18px;padding-left:0}.step-title::before{display:none}.step-title .eyebrow{color:#087f9f;font-size:12px}.step-title h1{margin:4px 0 5px;font-size:clamp(32px,3.3vw,42px);color:#082a50}.step-title p{font-size:14px;max-width:820px}
.alert,.notice,.detected-site-url,.email-fields,.review-card,.review-note,.check{background:#f8fbfc!important;background-image:none!important;box-shadow:none!important;border-color:#dbe6ed!important}
.detected-site-url{margin:0 0 20px;padding:14px 15px}.detected-site-url-icon{background:#079bd0!important;background-image:none!important;box-shadow:none}.detected-site-url small{color:#087f9f}.detected-site-url strong{color:#082a50}
.form-grid{gap:16px 18px}.form-grid.three{grid-template-columns:minmax(0,1.5fr) minmax(130px,.78fr) minmax(220px,1fr)}.field-label{color:#193a55;font-size:13.5px}.field small{font-size:11.5px;line-height:1.45}input,select{min-height:48px;border-radius:12px;border-color:#ccdbe6;background:#fff;box-shadow:none;font-size:15px}input:hover,select:hover{border-color:#9fc2d3}input:focus,select:focus{border-color:#079bd0;box-shadow:0 0 0 3px rgba(7,155,208,.10)}
.password-toggle{background:#eef6f9;color:#087f9f;box-shadow:none}.password-toggle:hover{background:#dff1f5;color:#075d6f;transform:translateY(-50%)}
.create-db-check{width:100%;max-width:720px}.check{padding:13px 14px;border-radius:12px}
.method-grid{gap:10px}.method{padding:14px;border-radius:14px;border-color:#dce5ec;box-shadow:none}.method:hover{transform:translateY(-1px);border-color:#aad2dc;box-shadow:0 8px 20px rgba(17,52,80,.06)}.method:has(input:checked){border-color:#90ced8;background:#edf8f9;box-shadow:none}.method-icon{background:#e9f5f6;color:#087f9f}
.review{gap:10px}.review-card{border-radius:13px;padding:14px 16px}.review-note{border-radius:13px}
.footer{padding:12px 22px;border-top:1px solid #edf2f5;background:#fff;box-shadow:none}.button,button{min-height:46px;padding-inline:16px;border-radius:12px;background:#082a50!important;background-image:none!important;color:#fff;box-shadow:none;font-size:14px}.button:hover,button:hover{transform:translateY(-1px);box-shadow:0 7px 18px rgba(8,42,80,.14)}.button.secondary{background:#fff!important;color:#24415c;border:1px solid #d6e1e9;box-shadow:none}.button.secondary:hover{background:#f7fafc!important;border-color:#bfd0dc}
@media(max-width:1100px){.installer{width:min(100%,calc(100vw - 18px));grid-template-columns:270px minmax(0,1fr)}.installer-brand img{width:128px;height:70px}.installer-brand strong{font-size:27px}.scroll{padding:20px 18px 16px}.form-grid.three{grid-template-columns:repeat(2,minmax(0,1fr))}.form-grid.three .db-host{grid-column:1/-1}.db-password{grid-column:2/-1}}
@media(max-width:980px){.installer{grid-template-columns:245px minmax(0,1fr)}.side{margin-right:10px}.installer-brand img{width:116px;height:62px}.installer-brand strong{font-size:24px}.scroll{padding:18px 16px 16px}.form-grid.three{grid-template-columns:repeat(2,minmax(0,1fr))}.form-grid.three .db-host{grid-column:1/-1}.db-password{grid-column:2/-1}}
@media(max-width:760px){.page{padding:8px}.installer{display:block;width:100%}.installer-head{min-height:92px;margin-bottom:10px;padding:14px 15px;border-radius:16px}.installer-brand{gap:10px}.installer-brand img{width:92px;height:54px}.installer-brand p{font-size:9px}.installer-brand strong{font-size:20px}.installer-brand small{font-size:11px}.installer-badge{min-height:36px;padding:0 10px;font-size:10px}.side{display:block;margin:0 0 10px;padding:12px;border-radius:16px}.side-progress{margin-bottom:10px}.side-list{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:5px}.side-item{grid-template-columns:1fr;justify-items:center;text-align:center;padding:7px 4px}.side-item b{width:29px;height:29px}.side-item span strong{font-size:10px}.side-item span small{display:none}.side-foot{display:none}.main{border-radius:16px}.scroll{padding:20px 14px 16px}.form-grid,.form-grid.three,.method-grid,.test-row{grid-template-columns:1fr}.form-grid.three .db-host,.db-password{grid-column:1/-1}.footer{padding:11px 14px calc(11px + env(safe-area-inset-bottom,0px))}.footer-right{flex:1}.footer-right .button,.footer-right button{width:100%}}
@media(max-width:470px){.installer-head{align-items:flex-start}.installer-brand img{width:78px;height:48px}.installer-brand strong{font-size:18px}.installer-brand small{display:none}.installer-badge{font-size:9px}.side-list{gap:3px}.side-item b{width:27px;height:27px}.step-title h1{font-size:28px}}


/* v4.3.3 — equal-height desktop panels + natural vertical page scroll */
@media (min-width: 761px) {
    .installer{
        align-items:stretch;
        grid-auto-rows:auto 1fr;
    }
    .side,
    .main{
        align-self:stretch !important;
        height:100%;
        min-height:100%;
    }
    .side{
        display:flex;
        flex-direction:column;
    }
    .side-foot{
        margin-top:auto;
    }
    .main{
        display:flex;
        flex-direction:column;
    }
    .step-panel{
        display:flex;
        flex-direction:column;
        flex:1 1 auto;
    }
    .scroll{
        flex:1 1 auto;
    }
    .footer{
        margin-top:auto;
    }
}

/* Never create horizontal scrolling. If width becomes insufficient, reorganize downward. */
html,body{
    width:100%;
    max-width:100%;
    overflow-x:hidden !important;
}
.page,.installer,.installer-head,.side,.main,.step-panel,.scroll,
.form-grid,.field,.password-wrap,.detected-site-url,.review-card{
    min-width:0;
    max-width:100%;
}
input,select,textarea,button,.button{
    max-width:100%;
}

/* Intermediate widths: preserve readable content without forcing horizontal overflow. */
@media (max-width: 1180px) and (min-width: 761px) {
    .installer{
        grid-template-columns:260px minmax(0,1fr);
    }
    .form-grid.three{
        grid-template-columns:minmax(0,1fr) minmax(130px,.52fr);
    }
    .form-grid.three .db-host{
        grid-column:1/-1;
    }
    .form-grid.three .field:nth-child(3){
        grid-column:auto;
    }
    .db-password{
        grid-column:auto;
    }
}

/* Small screens: everything flows downward; only the browser/page scrolls vertically. */
@media (max-width: 760px) {
    html,body{
        overflow-x:hidden !important;
        overflow-y:auto !important;
    }
    .page{
        min-height:100dvh;
        height:auto;
        overflow:visible;
    }
    .installer{
        display:flex !important;
        flex-direction:column;
        width:100%;
        height:auto !important;
        min-height:0 !important;
        overflow:visible !important;
    }
    .installer-head,
    .side,
    .main{
        width:100%;
        height:auto !important;
        min-height:0 !important;
        overflow:visible !important;
    }
    .installer-head{
        order:1;
    }
    .side{
        order:2;
        display:block !important;
        margin:0 0 10px !important;
    }
    .main{
        order:3;
    }
    .side-list{
        grid-template-columns:1fr !important;
    }
    .side-item{
        grid-template-columns:34px minmax(0,1fr) !important;
        justify-items:stretch !important;
        text-align:left !important;
        padding:9px 10px !important;
    }
    .side-item span small{
        display:block !important;
    }
    .form-grid,
    .form-grid.three,
    .method-grid,
    .test-row{
        grid-template-columns:1fr !important;
    }
    .form-grid.three .db-host,
    .form-grid.three .field,
    .db-password{
        grid-column:1/-1 !important;
    }
    .create-db-check{
        width:100%;
    }
    .footer{
        position:static !important;
    }
}

/* Very short desktop/laptop displays: do not compress panels or add internal scrollbars.
   Let the browser provide a normal vertical scrollbar instead. */
@media (max-height: 760px) and (min-width: 761px) {
    html,body{
        overflow-y:auto !important;
    }
    .page{
        min-height:100vh;
        height:auto !important;
        padding-top:10px;
        padding-bottom:10px;
    }
    .installer{
        height:auto !important;
        min-height:0 !important;
    }
    .side,.main,.step-panel,.scroll{
        overflow:visible !important;
        max-height:none !important;
    }
}


/* v4.3.5 — 95% viewport wizard, fixed action footer, vertical content scroll and 12-column layout */
html,
body{
    width:100%;
    height:100%;
    max-width:100%;
    overflow:hidden !important;
}
body{
    min-height:100%;
}
.page{
    width:100%;
    height:100vh !important;
    height:100dvh !important;
    min-height:0 !important;
    padding:2.5vh clamp(8px,1.2vw,18px) !important;
    display:grid !important;
    place-items:center !important;
    overflow:hidden !important;
}
.installer{
    width:min(1500px,100%) !important;
    height:95vh !important;
    height:95dvh !important;
    max-height:95vh !important;
    max-height:95dvh !important;
    min-height:0 !important;
    margin:0 auto !important;
    display:grid !important;
    grid-template-columns:minmax(240px,300px) minmax(0,1fr) !important;
    grid-template-rows:auto minmax(0,1fr) !important;
    grid-template-areas:
        "head head"
        "side main" !important;
    align-items:stretch !important;
    overflow:hidden !important;
}
.installer-head{
    grid-area:head;
    min-height:86px;
    margin-bottom:10px !important;
    flex:0 0 auto;
}
.side{
    grid-area:side;
    align-self:stretch !important;
    height:auto !important;
    min-height:0 !important;
    max-height:none !important;
    overflow-y:auto !important;
    overflow-x:hidden !important;
    scrollbar-width:thin;
    scrollbar-color:#b8c8d5 transparent;
}
.main{
    grid-area:main;
    align-self:stretch !important;
    height:auto !important;
    min-height:0 !important;
    max-height:none !important;
    display:flex !important;
    flex-direction:column !important;
    overflow:hidden !important;
}
.step-panel{
    display:flex !important;
    flex:1 1 auto !important;
    min-height:0 !important;
    max-height:100% !important;
    flex-direction:column !important;
    overflow:hidden !important;
}
.scroll{
    flex:1 1 auto !important;
    min-height:0 !important;
    max-height:none !important;
    overflow-y:auto !important;
    overflow-x:hidden !important;
    overscroll-behavior:contain;
    scrollbar-gutter:auto;
    scrollbar-width:thin;
    scrollbar-color:#aebfcd #f4f7f9;
}
.scroll::-webkit-scrollbar,
.side::-webkit-scrollbar{
    width:9px;
}
.scroll::-webkit-scrollbar-track,
.side::-webkit-scrollbar-track{
    background:#f4f7f9;
    border-radius:999px;
}
.scroll::-webkit-scrollbar-thumb,
.side::-webkit-scrollbar-thumb{
    background:#b7c7d4;
    border:2px solid #f4f7f9;
    border-radius:999px;
}
.scroll::-webkit-scrollbar-thumb:hover,
.side::-webkit-scrollbar-thumb:hover{
    background:#8fa6b7;
}
.footer{
    position:relative !important;
    inset:auto !important;
    z-index:10;
    flex:0 0 auto !important;
    width:100%;
    margin:0 !important;
    display:grid !important;
    grid-template-columns:repeat(12,minmax(0,1fr)) !important;
    gap:10px !important;
    align-items:center !important;
    padding:12px 22px calc(12px + env(safe-area-inset-bottom,0px)) !important;
    border-top:1px solid #dfe8ee !important;
    background:#fff !important;
    box-shadow:0 -8px 22px rgba(17,52,80,.055) !important;
}
.footer > .button.secondary,
.footer > span{
    grid-column:1 / span 3;
    justify-self:start;
}
.footer-right{
    grid-column:7 / -1;
    width:100%;
    display:flex !important;
    justify-content:flex-end !important;
    gap:10px !important;
}
.footer-right .button,
.footer-right button{
    min-width:170px;
}

/* 12-column content grid: every form/control block aligns to the same guide. */
.form-grid,
.form-grid.three,
.method-grid,
.review,
.test-row{
    display:grid !important;
    grid-template-columns:repeat(12,minmax(0,1fr)) !important;
    column-gap:18px !important;
    row-gap:16px !important;
    width:100%;
}
.form-grid > .field{
    grid-column:span 6;
}
.form-grid > .field.full,
.form-grid > .check,
.form-grid > .test-row{
    grid-column:1 / -1 !important;
}
.form-grid.three > :nth-child(1){grid-column:span 6 !important}
.form-grid.three > :nth-child(2){grid-column:span 2 !important}
.form-grid.three > :nth-child(3){grid-column:span 4 !important}
.form-grid.three > :nth-child(4){grid-column:span 6 !important}
.form-grid.three > :nth-child(5){grid-column:span 6 !important}
.form-grid.three > :nth-child(n+6){grid-column:1 / -1 !important}
.db-password,
.db-host,
.create-db-check{
    width:100% !important;
    max-width:none !important;
}
.method-grid > .method{
    grid-column:span 4;
}
.email-fields{
    width:100%;
}
.test-row > .field{
    grid-column:span 8 !important;
}
.test-row > button{
    grid-column:span 4 !important;
    width:100%;
    min-width:0 !important;
}
.review > .review-card{
    grid-column:span 6;
    min-width:0;
}
.review-card{
    grid-template-columns:minmax(120px,4fr) minmax(0,8fr) !important;
}
.email-actions{
    display:none !important;
}

/* No table layout in the installer. Components use CSS Grid/Flex only. */
.scroll table,
.scroll thead,
.scroll tbody,
.scroll tr,
.scroll th,
.scroll td{
    display:block;
    width:100%;
}

@media (max-width:1180px){
    .installer{
        grid-template-columns:minmax(225px,260px) minmax(0,1fr) !important;
    }
    .form-grid.three > :nth-child(1){grid-column:span 12 !important}
    .form-grid.three > :nth-child(2){grid-column:span 4 !important}
    .form-grid.three > :nth-child(3){grid-column:span 8 !important}
    .form-grid.three > :nth-child(4),
    .form-grid.three > :nth-child(5){grid-column:span 6 !important}
}

@media (max-width:900px){
    .form-grid > .field,
    .form-grid.three > :nth-child(n),
    .method-grid > .method,
    .review > .review-card{
        grid-column:1 / -1 !important;
    }
    .test-row > .field,
    .test-row > button{
        grid-column:1 / -1 !important;
    }
}

@media (max-width:760px){
    html,
    body{
        overflow:hidden !important;
    }
    .page{
        height:100vh !important;
        height:100dvh !important;
        padding:2.5vh 6px !important;
        overflow:hidden !important;
    }
    .installer{
        width:100% !important;
        height:95vh !important;
        height:95dvh !important;
        max-height:95vh !important;
        max-height:95dvh !important;
        min-height:0 !important;
        display:grid !important;
        grid-template-columns:minmax(0,1fr) !important;
        grid-template-rows:auto auto minmax(0,1fr) !important;
        grid-template-areas:
            "head"
            "side"
            "main" !important;
        overflow:hidden !important;
    }
    .installer-head{
        width:100%;
        min-height:62px !important;
        margin-bottom:6px !important;
        padding:8px 10px !important;
        display:grid !important;
        grid-template-columns:repeat(12,minmax(0,1fr)) !important;
        align-items:center !important;
        column-gap:8px !important;
        border-radius:14px !important;
    }
    .installer-brand{
        grid-column:1 / span 9;
        gap:8px !important;
    }
    .installer-brand img{
        width:66px !important;
        height:42px !important;
    }
    .installer-brand p{
        font-size:8px !important;
        margin-bottom:2px !important;
    }
    .installer-brand strong{
        font-size:17px !important;
    }
    .installer-brand small{
        display:none !important;
    }
    .installer-badge{
        grid-column:10 / -1;
        min-height:32px !important;
        padding:0 7px !important;
        font-size:8.5px !important;
        white-space:normal !important;
        text-align:center;
    }
    .side{
        width:100%;
        height:auto !important;
        min-height:0 !important;
        margin:0 0 6px !important;
        padding:8px !important;
        overflow:visible !important;
        border-radius:14px !important;
    }
    .side-progress{
        grid-template-columns:auto 1fr;
        align-items:center;
        gap:3px 8px !important;
        margin:0 2px 7px !important;
    }
    .side-progress strong{
        font-size:10px !important;
    }
    .side-progress small{
        display:none !important;
    }
    .side-progress > div{
        margin:0 !important;
        height:5px !important;
    }
    .side-list{
        display:grid !important;
        grid-template-columns:repeat(12,minmax(0,1fr)) !important;
        gap:4px !important;
    }
    .side-item{
        grid-column:span 3;
        display:grid !important;
        grid-template-columns:1fr !important;
        justify-items:center !important;
        text-align:center !important;
        gap:3px !important;
        padding:6px 3px !important;
        border-radius:10px !important;
    }
    .side-item b{
        width:25px !important;
        height:25px !important;
        font-size:10px !important;
    }
    .side-item span strong{
        font-size:8.5px !important;
        line-height:1.1 !important;
        overflow-wrap:anywhere;
    }
    .side-item span small,
    .side-foot{
        display:none !important;
    }
    .main{
        width:100%;
        height:auto !important;
        min-height:0 !important;
        overflow:hidden !important;
        border-radius:14px !important;
    }
    .step-panel{
        min-height:0 !important;
        overflow:hidden !important;
    }
    .scroll{
        min-height:0 !important;
        overflow-y:auto !important;
        overflow-x:hidden !important;
        padding:16px 12px 14px !important;
    }
    .step-title{
        margin-bottom:15px !important;
    }
    .step-title h1{
        font-size:26px !important;
    }
    .step-title p{
        font-size:12px !important;
    }
    .form-grid,
    .form-grid.three,
    .method-grid,
    .review,
    .test-row{
        grid-template-columns:repeat(12,minmax(0,1fr)) !important;
        gap:13px !important;
    }
    .form-grid > .field,
    .form-grid > .check,
    .form-grid > .test-row,
    .form-grid.three > :nth-child(n),
    .method-grid > .method,
    .review > .review-card,
    .test-row > .field,
    .test-row > button{
        grid-column:1 / -1 !important;
    }
    .footer{
        grid-template-columns:repeat(12,minmax(0,1fr)) !important;
        gap:8px !important;
        padding:9px 10px calc(9px + env(safe-area-inset-bottom,0px)) !important;
    }
    .footer > .button.secondary,
    .footer > span{
        grid-column:1 / span 5;
        width:100%;
        justify-self:stretch;
    }
    .footer-right{
        grid-column:6 / -1;
        width:100%;
    }
    .footer-right .button,
    .footer-right button{
        width:100%;
        min-width:0 !important;
        padding-inline:9px !important;
        font-size:12px !important;
    }
    .footer > .button.secondary{
        font-size:12px !important;
    }
}

@media (max-width:430px){
    .installer-brand{
        grid-column:1 / span 8;
    }
    .installer-badge{
        grid-column:9 / -1;
    }
    .installer-brand img{
        width:54px !important;
        height:38px !important;
    }
    .installer-brand p{
        display:none !important;
    }
    .installer-brand strong{
        font-size:16px !important;
    }
    .side-item span strong{
        font-size:8px !important;
    }
    .footer > .button.secondary,
    .footer > span{
        grid-column:1 / span 5;
    }
    .footer-right{
        grid-column:6 / -1;
    }
}

@media (max-height:620px){
    .installer-head{
        min-height:56px !important;
    }
    .side{
        padding-top:6px !important;
        padding-bottom:6px !important;
    }
    .scroll{
        padding-top:13px !important;
        padding-bottom:12px !important;
    }
    .footer{
        padding-top:8px !important;
        padding-bottom:calc(8px + env(safe-area-inset-bottom,0px)) !important;
    }
}

/* v4.3.5 — interaction standards */
.button.secondary,.installer-back{background:#EAF5FC!important;color:#0B1F52!important;border:1px solid #C6DEED!important;box-shadow:none!important}
.button.secondary:hover,.installer-back:hover{background:#DDEFFA!important;color:#0B1F52!important;border-color:#AFCFE3!important;opacity:1!important}
.scroll,.side{overflow-y:auto!important}
.scroll:not(:hover),.side:not(:hover){scrollbar-color:#b7c7d4 transparent}
.db-create-notice{margin:0!important}
.footer .ui-action-icon{width:17px;height:17px;flex:0 0 17px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}


/* v4.3.6 — scrollbars only appear when content actually overflows. */
.scroll,.side{overflow-y:auto!important;overflow-x:hidden!important}
.scroll{scrollbar-gutter:auto!important}

/* v4.4.0 — Premium installer aligned with the ES MULTISERVICIOS visual language */
:root{
  --izzy-navy:#082b55;
  --izzy-blue:#0788bc;
  --izzy-cyan:#0aa7cf;
  --izzy-bg:#edf4f8;
  --izzy-line:#d8e4ec;
  --izzy-soft:#f2f8fb;
  --izzy-muted:#688096;
}
body{background:var(--izzy-bg)!important;color:#102b45!important}
.page{padding:clamp(12px,2vh,24px)!important}
.installer{width:min(1420px,100%)!important;height:min(94dvh,900px)!important;max-height:94dvh!important;grid-template-columns:250px minmax(0,1fr)!important;column-gap:14px!important;row-gap:12px!important}
.installer-head{min-height:78px!important;margin:0!important;padding:12px 18px!important;border:1px solid var(--izzy-line)!important;border-top:4px solid var(--izzy-blue)!important;border-radius:18px!important;box-shadow:0 12px 28px rgba(17,52,80,.07)!important}
.installer-brand{gap:14px!important}.installer-brand img{width:104px!important;height:54px!important}.installer-brand p{margin:0 0 2px!important;color:#087ba7!important;font-size:10px!important}.installer-brand strong{font-size:23px!important;color:var(--izzy-navy)!important}.installer-brand small{margin-top:3px!important;font-size:11.5px!important}
.installer-badge{min-height:38px!important;padding:0 14px!important;border-radius:12px!important;background:var(--izzy-navy)!important;font-size:11px!important;box-shadow:0 6px 14px rgba(8,43,85,.12)!important}
.side{margin:0!important;padding:18px 14px 14px!important;border-radius:18px!important;border:1px solid var(--izzy-line)!important;box-shadow:0 12px 28px rgba(17,52,80,.06)!important;overflow:auto!important}.side-progress{margin:0 2px 13px!important}.side-progress strong{font-size:13.5px!important}.side-progress small{font-size:10.5px!important}.side-progress>div{height:5px!important;margin-top:7px!important}.side-progress>div span{background:var(--izzy-blue)!important}
.side-list{gap:5px!important}.side-item{grid-template-columns:32px minmax(0,1fr)!important;gap:9px!important;padding:8px 9px!important;border-radius:11px!important}.side-item b{width:29px!important;height:29px!important;border-radius:9px!important;font-size:11px!important}.side-item span strong{font-size:11.5px!important}.side-item span small{font-size:9px!important}.side-item.active{background:#e9f6f8!important;border-color:#cce8ee!important;color:#084e66!important}.side-item.active b{background:var(--izzy-blue)!important}.side-foot{font-size:9.5px!important;padding-top:10px!important;margin-top:10px!important}
.main{border-radius:18px!important;border:1px solid var(--izzy-line)!important;box-shadow:0 12px 28px rgba(17,52,80,.06)!important}.scroll{padding:20px 24px 18px!important}
.step-title{padding-bottom:14px!important;margin-bottom:14px!important;border-bottom:3px solid #e7eff4!important}.step-title::after{content:"";display:block;width:220px;height:3px;margin-top:14px;margin-bottom:-17px;border-radius:999px;background:var(--izzy-blue)}.step-title .eyebrow{font-size:10px!important;letter-spacing:.12em!important}.step-title h1{font-size:clamp(29px,3vw,37px)!important;margin:3px 0 3px!important}.step-title p{font-size:12px!important}
.step-guide{display:grid;grid-template-columns:34px minmax(0,1fr);gap:10px;align-items:center;margin:0 0 14px;padding:10px 12px;border:1px solid #cfe5ee;border-radius:13px;background:#f1f8fb}.step-guide-icon{width:30px;height:30px;display:grid;place-items:center;border-radius:9px;background:var(--izzy-blue);color:#fff;font-size:12px;font-weight:900}.step-guide strong{display:block;color:var(--izzy-navy);font-size:12px}.step-guide small{display:block;margin-top:1px;color:var(--izzy-muted);font-size:10px;line-height:1.35}
.detected-site-url{grid-template-columns:36px minmax(0,1fr)!important;gap:10px!important;margin:0 0 14px!important;padding:11px 12px!important;border-radius:12px!important}.detected-site-url-icon{width:32px!important;height:32px!important;border-radius:9px!important;font-size:14px!important}.detected-site-url small{font-size:9px!important}.detected-site-url strong{font-size:12px!important}.detected-site-url p{font-size:10px!important;margin-top:2px!important}
.form-grid,.form-grid.three,.method-grid,.review,.test-row{row-gap:12px!important;column-gap:14px!important}.field-label{font-size:11.5px!important}.field small{font-size:9.5px!important}input,select{min-height:40px!important;border-radius:10px!important;padding:8px 11px!important;font-size:12.5px!important}.password-toggle{width:32px!important;height:32px!important;right:4px!important}.password-toggle svg{width:16px!important;height:16px!important}.notice,.check,.email-fields,.review-card,.review-note{border-radius:12px!important}.notice{padding:10px 12px!important;font-size:10.5px!important}.notice strong{font-size:11.5px!important}.check{padding:10px 12px!important;font-size:10.5px!important}.method{padding:11px!important;border-radius:12px!important}.method-icon{width:34px!important;height:34px!important}.method strong{font-size:11.5px!important}.method small{font-size:9.5px!important}.email-fields{margin-top:12px!important;padding:14px!important}.review-card{padding:11px 13px!important}
.footer{padding:10px 16px calc(10px + env(safe-area-inset-bottom,0px))!important;min-height:60px!important}.button,button{min-height:40px!important;padding:8px 14px!important;border-radius:10px!important;font-size:11.5px!important;gap:7px!important}.footer-right .button,.footer-right button{min-width:145px!important}.ui-action-icon{width:16px!important;height:16px!important;flex:0 0 16px!important}
.izzy-swal-action{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:8px!important}.izzy-swal-action svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}.izzy-swal-danger{background:#a52734!important}.swal2-popup{border-radius:18px!important}.swal2-actions{gap:8px!important}
@media(max-width:900px){.installer{grid-template-columns:210px minmax(0,1fr)!important}.installer-brand img{width:90px!important}.installer-brand strong{font-size:20px!important}.scroll{padding:18px 16px!important}}
@media(max-width:760px){html,body{overflow:auto!important}.page{height:auto!important;min-height:100dvh!important;overflow:visible!important;padding:7px!important}.installer{height:auto!important;max-height:none!important;display:flex!important;flex-direction:column!important;overflow:visible!important}.installer-head{order:1!important;min-height:68px!important}.side{order:2!important;display:block!important;overflow:visible!important}.main{order:3!important;height:auto!important;overflow:visible!important}.step-panel,.scroll{overflow:visible!important;height:auto!important}.side-list{grid-template-columns:repeat(4,minmax(0,1fr))!important}.side-item{grid-template-columns:1fr!important;justify-items:center!important;text-align:center!important}.side-item span small{display:none!important}.footer{position:static!important;display:flex!important;justify-content:space-between!important}.footer>.button.secondary,.footer>span,.footer-right{grid-column:auto!important}.footer-right{width:auto!important;flex:1!important}.footer-right .button,.footer-right button{width:100%!important;min-width:0!important}.installer-brand small{display:none!important}}
@media(max-width:520px){.installer-head{padding:9px 10px!important}.installer-brand img{width:70px!important;height:42px!important}.installer-brand strong{font-size:16px!important}.installer-brand p{font-size:7.5px!important}.installer-badge{min-height:32px!important;padding:0 8px!important;font-size:8.5px!important}.side{padding:9px!important}.side-progress{margin-bottom:7px!important}.side-list{gap:3px!important}.side-item{padding:5px 2px!important}.side-item b{width:25px!important;height:25px!important}.side-item span strong{font-size:8.5px!important}.scroll{padding:14px 11px!important}.step-title h1{font-size:27px!important}.form-grid,.form-grid.three,.method-grid,.test-row{grid-template-columns:1fr!important}.footer{gap:7px!important;padding:8px 10px!important}.footer .button,.footer button{font-size:10.5px!important;padding:7px 9px!important}}

/* v4.5.0 — IZZY premium installer, visual parity with the approved reference */
:root{--izzy-navy:#0a315d;--izzy-blue:#0a86b8;--izzy-blue2:#0f95bf;--izzy-bg:#edf3f7;--izzy-line:#d9e4ec;--izzy-soft:#f3f8fb;--izzy-muted:#607890;--izzy-shadow:0 12px 30px rgba(20,55,82,.075)}
html,body{min-height:100%;background:var(--izzy-bg)!important}
body{margin:0!important;color:#102b45!important;font-family:Inter,"Segoe UI",Arial,sans-serif!important}
.page{display:block!important;min-height:100dvh!important;padding:20px clamp(14px,5vw,90px) 28px!important;background:var(--izzy-bg)!important}
.installer{width:min(1420px,100%)!important;height:auto!important;max-height:none!important;margin:0 auto!important;display:grid!important;grid-template-columns:250px minmax(0,1fr)!important;grid-template-rows:auto minmax(720px,auto)!important;gap:14px 16px!important;background:transparent!important;border:0!important;border-radius:0!important;box-shadow:none!important;overflow:visible!important;backdrop-filter:none!important}
.installer::after{display:none!important}
.installer-head{grid-column:1/-1!important;position:relative!important;display:flex!important;align-items:center!important;justify-content:space-between!important;min-height:92px!important;padding:14px 22px!important;margin:0!important;background:#fff!important;border:1px solid var(--izzy-line)!important;border-top:4px solid var(--izzy-blue)!important;border-radius:20px!important;box-shadow:var(--izzy-shadow)!important;overflow:hidden!important}
.installer-brand{display:flex!important;align-items:center!important;gap:18px!important}.installer-brand img{width:112px!important;height:60px!important;object-fit:contain!important}.installer-brand div{display:block!important}.installer-brand p{margin:0 0 2px!important;color:#087eab!important;font-size:11px!important;font-weight:900!important;letter-spacing:.13em!important}.installer-brand strong{display:block!important;color:var(--izzy-navy)!important;font-size:22px!important;line-height:1.08!important}.installer-brand small{display:block!important;margin-top:4px!important;color:var(--izzy-muted)!important;font-size:12px!important}
.installer-badge{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:40px!important;padding:0 16px!important;border:0!important;border-radius:12px!important;background:var(--izzy-navy)!important;color:#fff!important;font-size:11px!important;font-weight:900!important;box-shadow:0 7px 16px rgba(10,49,93,.14)!important}
.side{grid-column:1!important;grid-row:2!important;position:relative!important;display:flex!important;flex-direction:column!important;padding:20px 16px 16px!important;margin:0!important;background:#fff!important;color:#102b45!important;border:1px solid var(--izzy-line)!important;border-radius:18px!important;box-shadow:var(--izzy-shadow)!important;overflow:hidden!important}
.side::before,.side::after{display:none!important}.side-progress{margin:0 2px 14px!important}.side-progress strong{display:block!important;color:var(--izzy-navy)!important;font-size:14px!important}.side-progress small{display:block!important;margin-top:4px!important;color:var(--izzy-muted)!important;font-size:10.5px!important}.side-progress>div{height:5px!important;margin-top:9px!important;background:#dfe8ef!important;border-radius:999px!important;overflow:hidden!important}.side-progress>div span{display:block!important;height:100%!important;background:var(--izzy-blue)!important;border-radius:inherit!important}
.side-list{display:grid!important;grid-template-columns:1fr!important;gap:6px!important;margin:0!important}.side-item{display:grid!important;grid-template-columns:34px minmax(0,1fr)!important;gap:10px!important;align-items:center!important;padding:9px 10px!important;border:1px solid transparent!important;border-radius:11px!important;background:transparent!important;color:#16334d!important;box-shadow:none!important;transform:none!important}.side-item b{width:30px!important;height:30px!important;display:grid!important;place-items:center!important;border-radius:9px!important;background:#edf2f6!important;color:#6e8295!important;font-size:11px!important}.side-item span strong{display:block!important;color:inherit!important;font-size:11.5px!important;line-height:1.2!important}.side-item span small{display:block!important;margin-top:2px!important;color:#6f8295!important;font-size:9px!important}.side-item.active{background:#eaf6f8!important;border-color:#cce7ee!important;color:#084e66!important}.side-item.active b{background:var(--izzy-blue)!important;color:#fff!important}.side-item.complete b{background:#eaf7ef!important;color:#12835d!important}.side-foot{margin-top:auto!important;padding-top:16px!important;border-top:1px solid #e1e9ef!important;color:#6f8295!important;font-size:9.5px!important;line-height:1.5!important}
.main{grid-column:2!important;grid-row:2!important;display:flex!important;flex-direction:column!important;min-width:0!important;min-height:720px!important;background:#fff!important;border:1px solid var(--izzy-line)!important;border-radius:18px!important;box-shadow:var(--izzy-shadow)!important;overflow:hidden!important}
.content-head{display:flex!important;align-items:flex-start!important;justify-content:space-between!important;gap:20px!important;padding:22px 28px 10px!important}.content-head-copy{min-width:0!important}.content-head .eyebrow{margin:0 0 4px!important;color:#087eab!important;font-size:10px!important;font-weight:900!important;letter-spacing:.13em!important}.content-head h1{margin:0!important;color:#092b55!important;font-size:clamp(29px,3vw,38px)!important;line-height:1.05!important;letter-spacing:-.025em!important}.content-head p{margin:5px 0 0!important;color:var(--izzy-muted)!important;font-size:12px!important}.content-step-pill{flex:0 0 auto!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:32px!important;padding:0 11px!important;margin-top:22px!important;border:1px solid #cbdde8!important;border-radius:999px!important;background:#f4f9fc!important;color:#092b55!important;font-size:10px!important;font-weight:900!important}.content-progress{height:4px!important;margin:0 28px 14px!important;background:#e7eef3!important;border-radius:999px!important;overflow:hidden!important}.content-progress span{display:block!important;height:100%!important;background:var(--izzy-blue)!important;border-radius:inherit!important}
.top,.stepper{display:none!important}.step-panel{display:flex!important;flex:1!important;min-height:0!important;border:0!important}.scroll{width:100%!important;min-height:0!important;padding:0 28px 18px!important;overflow:visible!important}.step-title{display:none!important}
.step-guide{display:grid!important;grid-template-columns:34px minmax(0,1fr)!important;gap:10px!important;align-items:center!important;margin:0 0 14px!important;padding:11px 13px!important;border:1px solid #cfe4ed!important;border-radius:13px!important;background:#f2f8fb!important}.step-guide-icon{width:30px!important;height:30px!important;display:grid!important;place-items:center!important;border-radius:9px!important;background:var(--izzy-blue)!important;color:#fff!important;font-size:12px!important;font-weight:900!important}.step-guide strong{display:block!important;color:#092b55!important;font-size:12px!important}.step-guide small{display:block!important;margin-top:2px!important;color:var(--izzy-muted)!important;font-size:10px!important}
.detected-site-url{display:grid!important;grid-template-columns:34px minmax(0,1fr)!important;gap:10px!important;align-items:center!important;margin:10px 0 14px!important;padding:11px 13px!important;border:1px solid #cfe4ed!important;border-radius:13px!important;background:#f2f8fb!important;box-shadow:none!important}.detected-site-url-icon{width:30px!important;height:30px!important;display:grid!important;place-items:center!important;border-radius:9px!important;background:var(--izzy-blue)!important;color:#fff!important;font-size:13px!important;box-shadow:none!important}.detected-site-url small{display:block!important;color:#087eab!important;font-size:8.5px!important;font-weight:900!important;letter-spacing:.06em!important}.detected-site-url strong{display:block!important;margin-top:2px!important;color:#092b55!important;font-size:11.5px!important}.detected-site-url p{margin:2px 0 0!important;color:var(--izzy-muted)!important;font-size:9.5px!important}
.form-grid{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:13px 14px!important}.form-grid.three{grid-template-columns:minmax(0,1.2fr) minmax(120px,.55fr) minmax(0,1.2fr)!important}.field{font-size:11.5px!important;font-weight:800!important;color:#102b45!important}.field-label{font-size:11.5px!important}.field small{margin-top:5px!important;color:var(--izzy-muted)!important;font-size:9.5px!important;font-weight:500!important;line-height:1.35!important}input,select{min-height:42px!important;padding:8px 11px!important;border:1px solid #ccd9e3!important;border-radius:10px!important;background:#fff!important;color:#102b45!important;font-size:12.5px!important;box-shadow:none!important}input:focus,select:focus{border-color:#58aaca!important;box-shadow:0 0 0 3px rgba(10,134,184,.09)!important;outline:none!important}.required{color:#c22d3b!important}
.notice{border:1px solid #cfe4ed!important;background:#f2f8fb!important;color:#4f687e!important}.db-create-notice{display:grid!important;grid-template-columns:34px minmax(0,1fr)!important;gap:10px!important;align-items:center!important;padding:10px 13px!important;border-radius:13px!important}.db-create-notice .notice-icon{width:30px!important;height:30px!important;display:grid!important;place-items:center!important;border-radius:9px!important;background:var(--izzy-blue)!important;color:#fff!important;font-size:13px!important}.db-create-notice strong{display:block!important;margin:0 0 1px!important;color:#092b55!important;font-size:11.5px!important}.db-create-notice span:not(.notice-icon){display:block!important;color:var(--izzy-muted)!important;font-size:9.5px!important;line-height:1.35!important}
.footer{margin-top:auto!important;display:flex!important;align-items:center!important;justify-content:space-between!important;gap:10px!important;min-height:68px!important;padding:11px 28px!important;border-top:1px solid #e1e9ef!important;background:#fff!important}.footer-right{margin-left:auto!important;display:flex!important;gap:8px!important}.button,.footer button,.footer a.button{min-height:42px!important;padding:8px 15px!important;border-radius:10px!important;font-size:11.5px!important;font-weight:900!important}.footer-right button,.footer-right .button{min-width:142px!important;background:var(--izzy-navy)!important;color:#fff!important;border-color:var(--izzy-navy)!important}.button.secondary,.installer-back{background:#eaf5fb!important;color:#0b315b!important;border:1px solid #c5deeb!important}.button.secondary:hover,.installer-back:hover{background:#dceef8!important;opacity:1!important}
.swal2-popup{border-radius:18px!important;padding:1.5rem!important}.swal2-icon{transform:scale(.88)!important}.swal2-title{color:#0a315d!important}.swal2-confirm,.swal2-cancel{min-height:44px!important;max-width:100%!important;padding:10px 16px!important;border-radius:10px!important;font-size:13.5px!important;line-height:1.22!important;font-weight:900!important;letter-spacing:0!important;white-space:normal!important;overflow-wrap:anywhere!important;text-align:center!important;box-shadow:none!important}.swal2-confirm{background:#0a315d!important;color:#fff!important}.swal2-cancel{background:#eaf5fb!important;color:#0a315d!important;border:1px solid #c5deeb!important}.izzy-swal-danger{background:#a52837!important;color:#fff!important;border-color:#a52837!important}@media(max-width:480px){.swal2-confirm,.swal2-cancel{width:100%!important;font-size:13px!important;padding:10px 12px!important}}
@media(max-width:900px){.page{padding:10px!important}.installer{grid-template-columns:210px minmax(0,1fr)!important}.installer-brand img{width:88px!important}.content-head,.scroll,.footer{padding-left:18px!important;padding-right:18px!important}.content-progress{margin-left:18px!important;margin-right:18px!important}}
@media(max-width:720px){html,body{overflow:auto!important}.installer{display:flex!important;flex-direction:column!important;gap:9px!important}.installer-head{order:1!important}.side{order:2!important;overflow:visible!important}.main{order:3!important;min-height:0!important;overflow:visible!important}.side-progress{margin-bottom:8px!important}.side-list{grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:4px!important}.side-item{grid-template-columns:1fr!important;justify-items:center!important;text-align:center!important;padding:6px 3px!important}.side-item span small{display:none!important}.side-foot{display:none!important}.form-grid,.form-grid.three,.method-grid,.review,.test-row{grid-template-columns:1fr!important}.content-head{padding-top:17px!important}.content-step-pill{margin-top:12px!important}.footer{position:static!important}.footer-right{flex:1!important}.footer-right button,.footer-right .button{width:100%!important;min-width:0!important}}
@media(max-width:480px){.page{padding:6px!important}.installer-head{min-height:70px!important;padding:9px 10px!important}.installer-brand{gap:9px!important}.installer-brand img{width:64px!important;height:42px!important}.installer-brand p{display:none!important}.installer-brand strong{font-size:16px!important}.installer-brand small{display:none!important}.installer-badge{min-height:32px!important;padding:0 9px!important;font-size:8.5px!important}.content-head{gap:8px!important;padding-left:12px!important;padding-right:12px!important}.content-head h1{font-size:27px!important}.content-head p{font-size:10.5px!important}.content-step-pill{font-size:8.5px!important;padding:0 8px!important}.content-progress{margin-left:12px!important;margin-right:12px!important}.scroll{padding-left:12px!important;padding-right:12px!important}.footer{padding-left:12px!important;padding-right:12px!important}.side{padding:9px!important}.side-item span strong{font-size:8.5px!important}}


/* v4.5.1 — strict 95% viewport shell, internal vertical scroll, always-visible centered footer actions */
html,body{
    width:100%!important;
    height:100%!important;
    min-height:100%!important;
    overflow:hidden!important;
}
.page{
    width:100%!important;
    height:100dvh!important;
    min-height:0!important;
    padding:2.5dvh clamp(8px,1.2vw,18px)!important;
    display:grid!important;
    place-items:center!important;
    overflow:hidden!important;
}
.installer{
    width:min(1500px,100%)!important;
    height:95dvh!important;
    max-height:95dvh!important;
    min-height:0!important;
    margin:0 auto!important;
    display:grid!important;
    grid-template-columns:minmax(220px,250px) minmax(0,1fr)!important;
    grid-template-rows:82px minmax(0,1fr)!important;
    grid-template-areas:"head head" "side main"!important;
    gap:10px 14px!important;
    overflow:hidden!important;
}
.installer-head{
    grid-area:head!important;
    min-height:0!important;
    height:82px!important;
    margin:0!important;
}
.side{
    grid-area:side!important;
    min-height:0!important;
    height:100%!important;
    overflow-y:auto!important;
    overflow-x:hidden!important;
}
.main{
    grid-area:main!important;
    min-height:0!important;
    height:100%!important;
    display:flex!important;
    flex-direction:column!important;
    overflow:hidden!important;
}
.content-head,
.content-progress,
.footer{
    flex:0 0 auto!important;
}
.step-panel{
    flex:1 1 auto!important;
    min-height:0!important;
    height:auto!important;
    display:flex!important;
    flex-direction:column!important;
    overflow:hidden!important;
}
.scroll{
    flex:1 1 auto!important;
    min-height:0!important;
    height:auto!important;
    overflow-y:auto!important;
    overflow-x:hidden!important;
    overscroll-behavior:contain!important;
    scrollbar-gutter:stable!important;
    padding-bottom:18px!important;
}
.footer{
    position:relative!important;
    inset:auto!important;
    z-index:25!important;
    width:100%!important;
    min-height:62px!important;
    margin:0!important;
    display:grid!important;
    grid-template-columns:1fr auto 1fr!important;
    align-items:center!important;
    gap:10px!important;
    padding:10px 22px calc(10px + env(safe-area-inset-bottom,0px))!important;
    border-top:1px solid #e1e9ef!important;
    background:#fff!important;
    box-shadow:0 -8px 24px rgba(17,52,80,.055)!important;
}
.footer > .button.secondary,
.footer > .installer-back{
    grid-column:1!important;
    justify-self:start!important;
}
.footer > span{
    grid-column:1!important;
}
.footer-right{
    grid-column:2!important;
    justify-self:center!important;
    width:auto!important;
    margin:0!important;
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
    gap:8px!important;
}
.footer-right .button,
.footer-right button{
    min-width:150px!important;
}
.notify-close,
.swal2-close{
    gap:0!important;
}
.notify-close .action-icon,
.swal2-close .action-icon{
    display:none!important;
}

@media(max-width:900px){
    .installer{
        grid-template-columns:minmax(180px,210px) minmax(0,1fr)!important;
    }
}
@media(max-width:720px){
    html,body{overflow:hidden!important}
    .page{
        height:100dvh!important;
        min-height:0!important;
        padding:2.5dvh 7px!important;
        overflow:hidden!important;
    }
    .installer{
        height:95dvh!important;
        max-height:95dvh!important;
        min-height:0!important;
        display:grid!important;
        grid-template-columns:1fr!important;
        grid-template-rows:68px auto minmax(0,1fr)!important;
        grid-template-areas:"head" "side" "main"!important;
        gap:7px!important;
        overflow:hidden!important;
    }
    .installer-head{
        grid-area:head!important;
        height:68px!important;
        min-height:68px!important;
    }
    .side{
        grid-area:side!important;
        height:auto!important;
        max-height:112px!important;
        overflow-y:auto!important;
    }
    .main{
        grid-area:main!important;
        min-height:0!important;
        height:100%!important;
        overflow:hidden!important;
    }
    .step-panel{
        min-height:0!important;
        overflow:hidden!important;
    }
    .scroll{
        overflow-y:auto!important;
        overflow-x:hidden!important;
    }
    .footer{
        position:relative!important;
        display:grid!important;
        grid-template-columns:1fr auto 1fr!important;
        min-height:58px!important;
    }
    .footer-right{
        grid-column:2!important;
        width:auto!important;
        flex:0 0 auto!important;
    }
    .footer-right .button,
    .footer-right button{
        width:auto!important;
        min-width:132px!important;
    }
}
@media(max-width:480px){
    .footer{
        grid-template-columns:auto 1fr!important;
        padding-left:10px!important;
        padding-right:10px!important;
    }
    .footer > .button.secondary,
    .footer > .installer-back,
    .footer > span{grid-column:1!important}
    .footer-right{
        grid-column:2!important;
        justify-self:center!important;
        width:100%!important;
    }
    .footer-right .button,
    .footer-right button{
        width:100%!important;
        min-width:0!important;
    }
}

/* v4.5.2 — adaptive desktop height: no scrollbar unless content truly needs it */
@media(min-width:721px){
    .scroll{
        overflow-y:auto!important;
        overflow-x:hidden!important;
        scrollbar-gutter:auto!important;
    }
    .side{
        overflow-y:auto!important;
        overflow-x:hidden!important;
        scrollbar-gutter:auto!important;
    }

    body.installer-step-1 .content-head,
    body.installer-step-2 .content-head,
    body.installer-step-3 .content-head{
        padding:16px 24px 7px!important;
    }
    body.installer-step-1 .content-head h1,
    body.installer-step-2 .content-head h1,
    body.installer-step-3 .content-head h1{
        font-size:clamp(27px,2.5vw,34px)!important;
    }
    body.installer-step-1 .content-head p,
    body.installer-step-2 .content-head p,
    body.installer-step-3 .content-head p{
        margin-top:3px!important;
        font-size:11px!important;
    }
    body.installer-step-1 .content-step-pill,
    body.installer-step-2 .content-step-pill,
    body.installer-step-3 .content-step-pill{
        min-height:29px!important;
        margin-top:15px!important;
    }
    body.installer-step-1 .content-progress,
    body.installer-step-2 .content-progress,
    body.installer-step-3 .content-progress{
        margin:0 24px 10px!important;
    }
    body.installer-step-1 .scroll,
    body.installer-step-2 .scroll,
    body.installer-step-3 .scroll{
        padding:0 24px 10px!important;
    }
    body.installer-step-1 .step-guide,
    body.installer-step-2 .step-guide,
    body.installer-step-3 .step-guide{
        margin-bottom:10px!important;
        padding:8px 11px!important;
    }
    body.installer-step-1 .step-guide-icon,
    body.installer-step-2 .step-guide-icon,
    body.installer-step-3 .step-guide-icon{
        width:28px!important;
        height:28px!important;
    }
    body.installer-step-1 .detected-site-url{
        margin:6px 0 10px!important;
        padding:8px 11px!important;
    }
    body.installer-step-1 .detected-site-url-icon{
        width:28px!important;
        height:28px!important;
    }
    body.installer-step-1 .form-grid,
    body.installer-step-2 .form-grid,
    body.installer-step-3 .form-grid{
        gap:8px 12px!important;
    }
    body.installer-step-1 input,
    body.installer-step-1 select,
    body.installer-step-2 input,
    body.installer-step-2 select,
    body.installer-step-3 input,
    body.installer-step-3 select{
        min-height:38px!important;
        padding-top:6px!important;
        padding-bottom:6px!important;
    }
    body.installer-step-1 .field small,
    body.installer-step-2 .field small,
    body.installer-step-3 .field small{
        margin-top:3px!important;
        line-height:1.25!important;
    }
    body.installer-step-1 .db-create-notice,
    body.installer-step-2 .notice,
    body.installer-step-3 .notice{
        padding-top:8px!important;
        padding-bottom:8px!important;
    }
    body.installer-step-1 .footer,
    body.installer-step-2 .footer,
    body.installer-step-3 .footer{
        min-height:56px!important;
        padding-top:7px!important;
        padding-bottom:7px!important;
    }

    /* Step 4 keeps the same shell and gains a scrollbar only if its review content exceeds the available area. */
    body.installer-step-4 .scroll{
        overflow-y:auto!important;
        scrollbar-gutter:auto!important;
    }
}



/* v4.5.3 — compact responsive installer: paired DB fields and overflow only when necessary */
@media(min-width:721px){
    /* Keep desktop/tablet shell at 95% viewport without forcing a scrollbar. */
    .side{overflow-y:auto!important;scrollbar-gutter:auto!important}
    .scroll{overflow-y:auto!important;scrollbar-gutter:auto!important}

    /* Step 1: Server + Port / Database + User, exactly two columns. */
    body.installer-step-1 .form-grid.three{
        grid-template-columns:minmax(0,1fr) minmax(0,1fr)!important;
        gap:7px 12px!important;
    }
    body.installer-step-1 .db-host,
    body.installer-step-1 .db-user{
        grid-column:auto!important;
    }
    body.installer-step-1 .db-password,
    body.installer-step-1 .db-create-notice{
        grid-column:1/-1!important;
    }

    /* Compress only what is needed on steps 1–3 so normal desktop heights fit cleanly. */
    body.installer-step-1 .content-head,
    body.installer-step-2 .content-head,
    body.installer-step-3 .content-head{padding:13px 22px 5px!important}
    body.installer-step-1 .content-head h1,
    body.installer-step-2 .content-head h1,
    body.installer-step-3 .content-head h1{font-size:clamp(25px,2.25vw,32px)!important}
    body.installer-step-1 .content-head p,
    body.installer-step-2 .content-head p,
    body.installer-step-3 .content-head p{font-size:10.5px!important;line-height:1.25!important}
    body.installer-step-1 .content-step-pill,
    body.installer-step-2 .content-step-pill,
    body.installer-step-3 .content-step-pill{min-height:27px!important;margin-top:11px!important;padding:0 10px!important}
    body.installer-step-1 .content-progress,
    body.installer-step-2 .content-progress,
    body.installer-step-3 .content-progress{margin:0 22px 7px!important}
    body.installer-step-1 .scroll,
    body.installer-step-2 .scroll,
    body.installer-step-3 .scroll{padding:0 22px 7px!important}
    body.installer-step-1 .step-guide,
    body.installer-step-2 .step-guide,
    body.installer-step-3 .step-guide{margin-bottom:7px!important;padding:7px 10px!important}
    body.installer-step-1 .detected-site-url{margin:4px 0 7px!important;padding:7px 10px!important}
    body.installer-step-1 .form-grid,
    body.installer-step-2 .form-grid,
    body.installer-step-3 .form-grid{gap:7px 12px!important}
    body.installer-step-1 .field-label,
    body.installer-step-2 .field-label,
    body.installer-step-3 .field-label{min-height:17px!important;font-size:11.5px!important}
    body.installer-step-1 .field>input,
    body.installer-step-1 .field>select,
    body.installer-step-2 .field>input,
    body.installer-step-2 .field>select,
    body.installer-step-3 .field>input,
    body.installer-step-3 .field>select{margin-top:4px!important}
    body.installer-step-1 input,
    body.installer-step-1 select,
    body.installer-step-2 input,
    body.installer-step-2 select,
    body.installer-step-3 input,
    body.installer-step-3 select{min-height:36px!important;padding:5px 12px!important;border-radius:11px!important}
    body.installer-step-1 .password-wrap,
    body.installer-step-2 .password-wrap,
    body.installer-step-3 .password-wrap{margin-top:4px!important}
    body.installer-step-1 .password-toggle,
    body.installer-step-2 .password-toggle,
    body.installer-step-3 .password-toggle{width:30px!important;min-width:30px!important;height:30px!important;min-height:30px!important}
    body.installer-step-1 .field small,
    body.installer-step-2 .field small,
    body.installer-step-3 .field small{margin-top:2px!important;font-size:10px!important;line-height:1.2!important}
    body.installer-step-1 .db-create-notice{padding:7px 10px!important;margin-top:0!important}
    body.installer-step-1 .footer,
    body.installer-step-2 .footer,
    body.installer-step-3 .footer{min-height:52px!important;padding-top:5px!important;padding-bottom:5px!important}
}

/* Responsive: collapse naturally only when the screen becomes narrow. */
@media(max-width:720px){
    body.installer-step-1 .form-grid.three{grid-template-columns:1fr!important}
    body.installer-step-1 .db-password,
    body.installer-step-1 .db-create-notice{grid-column:auto!important}
}


/* ==========================================================
   v4.6.0 — IZZY installer: clean premium parity with public site
   ========================================================== */
html,body{
  font-family:"Segoe UI",Inter,Arial,sans-serif!important;
  -webkit-font-smoothing:antialiased!important;
  -moz-osx-font-smoothing:grayscale!important;
  text-rendering:optimizeLegibility!important;
}
body{
  background:#eef4f8!important;
}
.page{
  padding:18px clamp(12px,3vw,42px)!important;
}
.installer{
  width:min(1380px,100%)!important;
  height:min(95dvh,920px)!important;
  max-height:95dvh!important;
  grid-template-columns:240px minmax(0,1fr)!important;
  gap:12px!important;
  background:transparent!important;
}
.installer-head,.side,.main{
  background:#fff!important;
  border:1px solid #d6e3eb!important;
  box-shadow:0 12px 30px rgba(8,42,80,.07)!important;
  backdrop-filter:none!important;
}
.installer-head{
  min-height:80px!important;
  border-top:4px solid #0a91c7!important;
}
.side{
  padding:16px 13px!important;
}
.main{
  overflow:hidden!important;
}
.content-head{
  background:#fff!important;
}
.content-head h1,.step-title h1{
  color:#082b58!important;
  letter-spacing:-.025em!important;
  text-shadow:none!important;
}
.content-head p,.step-title p,.field small{
  color:#657c90!important;
  text-shadow:none!important;
}
.step-guide,.detected-site-url,.notice,.email-fields,.review-note{
  background:#f5fafc!important;
  border-color:#d4e4ec!important;
  box-shadow:none!important;
}
.method,.review-card,.check{
  background:#fff!important;
  border-color:#d6e3eb!important;
  box-shadow:none!important;
}
.method:hover{
  transform:none!important;
  border-color:#9fcddd!important;
  box-shadow:0 8px 20px rgba(8,83,123,.07)!important;
}
.method:has(input:checked){
  background:#eef8fc!important;
  border-color:#7fc3dd!important;
  box-shadow:0 0 0 3px rgba(10,145,199,.08)!important;
}
input,select{
  min-height:44px!important;
  padding:9px 12px!important;
  border-radius:11px!important;
  border:1px solid #cbd9e3!important;
  background:#fff!important;
  color:#102e4c!important;
  font-size:12.5px!important;
}
input:focus,select:focus{
  border-color:#0a91c7!important;
  box-shadow:0 0 0 3px rgba(10,145,199,.10)!important;
}
.form-grid{
  gap:14px 16px!important;
}
.field>input,.field>select,.password-wrap{
  margin-top:6px!important;
}
.field-label{
  min-height:18px!important;
  color:#17344f!important;
}
.email-fields{
  padding:16px!important;
  margin-top:14px!important;
}
.graph-panel{
  background:#f8fbfd!important;
}
.graph-intro-card{
  display:grid;
  grid-template-columns:44px minmax(0,1fr);
  gap:12px;
  align-items:center;
  margin-bottom:14px;
  padding:12px 14px;
  border:1px solid #cfe4ed;
  border-radius:14px;
  background:#eef8fc;
}
.graph-intro-icon{
  width:44px;height:44px;display:grid;place-items:center;border-radius:12px;
  background:#0a91c7;color:#fff;
}
.graph-intro-icon svg{width:20px;height:20px}
.graph-intro-card strong{display:block;color:#0a315d;font-size:12.5px}
.graph-intro-card small{display:block;margin-top:3px;color:#617b90;font-size:10.5px;line-height:1.45}
.graph-grid{
  grid-template-columns:minmax(0,1fr) minmax(0,1fr)!important;
}
.graph-tenant,.graph-client,.graph-secret,.graph-mailbox{
  grid-column:auto!important;
}
.graph-destination{
  grid-column:1/-1!important;
}
.graph-help-card{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:10px;
  padding:12px;
  border:1px solid #d6e4eb;
  border-radius:14px;
  background:#fff;
}
.graph-help-card>div{
  padding:10px 11px;
  border-radius:11px;
  background:#f5f9fb;
}
.graph-help-card strong{
  display:block;
  margin-bottom:3px;
  color:#123b5d;
  font-size:10.5px;
}
.graph-help-card span{
  display:block;
  color:#6b8092;
  font-size:9.5px;
  line-height:1.45;
}
.button,.footer button,.footer a.button{
  background:#0a91c7!important;
  color:#fff!important;
  border-color:#0a91c7!important;
  box-shadow:none!important;
}
.button:hover,.footer button:hover,.footer a.button:hover{
  background:#087dac!important;
  color:#fff!important;
  border-color:#087dac!important;
}
.button.secondary,.installer-back{
  background:#e2f2f8!important;
  color:#0a3c60!important;
  border-color:#b9d9e6!important;
}
.button.secondary:hover,.installer-back:hover{
  background:#d3eaf4!important;
  color:#082f50!important;
  border-color:#9bc9db!important;
}
.footer{
  background:#fff!important;
  border-top:1px solid #dde7ed!important;
}
@media(min-width:721px){
  body.installer-step-3 .scroll{
    padding-top:2px!important;
  }
  body.installer-step-3 .form-grid{
    gap:11px 14px!important;
  }
}
@media(max-width:720px){
  .graph-grid,.graph-help-card{
    grid-template-columns:1fr!important;
  }
  .graph-destination{grid-column:auto!important}
}

/* ==========================================================
   v4.6.1 — Checkboxes compactos y confirmación implícita
   ========================================================== */
.check{
  display:grid!important;
  grid-template-columns:20px minmax(0,1fr)!important;
  align-items:center!important;
  column-gap:10px!important;
  row-gap:0!important;
  width:100%!important;
  min-height:0!important;
  padding:10px 12px!important;
  background:#f5fafc!important;
  background-image:none!important;
  border:1px solid #d6e3eb!important;
  border-radius:12px!important;
  box-shadow:none!important;
}
.check input[type="checkbox"]{
  width:18px!important;
  height:18px!important;
  min-width:18px!important;
  min-height:18px!important;
  margin:0!important;
  align-self:center!important;
}
.check span{
  display:block!important;
  min-width:0!important;
  margin:0!important;
  color:#294057!important;
  font-size:11.5px!important;
  font-weight:700!important;
  line-height:1.35!important;
  overflow-wrap:break-word!important;
}
@media(max-width:720px){
  .check{
    grid-template-columns:18px minmax(0,1fr)!important;
    column-gap:9px!important;
    padding:9px 10px!important;
  }
  .check input[type="checkbox"]{
    width:17px!important;
    height:17px!important;
    min-width:17px!important;
    min-height:17px!important;
  }
  .check span{font-size:11px!important}
}


/* v4.6.2 — legibilidad refinada y orden SMTP */
.side-progress strong{font-size:13.8px!important;color:#0a2c4f!important;font-weight:900!important}
.side-progress small{font-size:11px!important;color:#4d647a!important;font-weight:700!important}
.side-item span strong{font-size:12.6px!important;line-height:1.24!important}
.side-item span small{font-size:10.4px!important;color:#4f687e!important;font-weight:700!important}
.content-head p{color:#4c667f!important;font-size:13px!important;font-weight:600!important}
.content-step-pill{font-size:10.4px!important}
.step-guide strong{font-size:12.8px!important}
.step-guide small{color:#4f687e!important;font-size:10.8px!important;font-weight:600!important;line-height:1.4!important}
.detected-site-url p{color:#4f687e!important;font-size:10.2px!important;font-weight:600!important;line-height:1.45!important}
.field{font-size:12px!important}
.field-label{font-size:12px!important;color:#102b45!important}
.field small{color:#4f687e!important;font-size:10.2px!important;font-weight:600!important;line-height:1.45!important}
input,select{font-size:13px!important}
.notice{color:#4f687e!important;font-size:11px!important}
.notice strong{font-size:12px!important}
.check{font-size:11.2px!important;color:#294055!important}
.method strong{font-size:12.2px!important;color:#092b55!important}
.method small{font-size:10.1px!important;color:#4f687e!important;font-weight:600!important}
.review-card strong{font-size:12.2px!important;color:#092b55!important}
.review-card span,.review-note{font-size:10.8px!important;color:#4f687e!important}
.button,.footer button,.footer a.button{font-size:12px!important}
.smtp-grid-top + .smtp-grid-middle,
.smtp-grid-middle + .smtp-grid-bottom{margin-top:12px!important}
.smtp-grid-top .smtp-server{grid-column:1/2!important}
.smtp-grid-top .smtp-port{grid-column:2/3!important}
.smtp-grid-top .smtp-security{grid-column:3/4!important}
@media(max-width:720px){
  .smtp-grid-top + .smtp-grid-middle,
  .smtp-grid-middle + .smtp-grid-bottom{margin-top:10px!important}
}


/* v4.6.3 — orden del Administrador, SMTP y Select2 legible */
.admin-grid-v26 .admin-full{grid-column:1/-1!important}
.admin-grid-v26 .admin-username{grid-column:1/2!important}
.admin-grid-v26 .admin-email{grid-column:2/3!important}
.smtp-grid-middle{grid-template-columns:repeat(2,minmax(0,1fr))!important}
@media(max-width:720px){
  .admin-grid-v26 .admin-username,.admin-grid-v26 .admin-email{grid-column:1/-1!important}
  .smtp-grid-middle{grid-template-columns:1fr!important}
}

</style>
<link rel="stylesheet" href="../assets/action-icons.css">
<style id="izzy-v1020-overlay-stability">
/* v1.0.20 — SweetAlert2 and showNotify stay locked to the viewport on every reload. */
html.swal2-shown,
body.swal2-shown,
body.swal2-height-auto{
    height:100%!important;
    min-height:100%!important;
    overflow:hidden!important;
    padding-right:0!important;
}
.swal2-container{
    position:fixed!important;
    inset:0!important;
    width:100vw!important;
    height:100dvh!important;
    min-height:100dvh!important;
    margin:0!important;
    padding:16px!important;
    display:grid!important;
    place-items:center!important;
    overflow:hidden!important;
    transform:none!important;
    z-index:2147482000!important;
}
.swal2-container.swal2-backdrop-show,
.swal2-container.swal2-noanimation{
    align-items:center!important;
    justify-content:center!important;
}
.swal2-popup{
    position:relative!important;
    inset:auto!important;
    margin:0!important;
    transform:none!important;
    max-height:calc(100dvh - 32px)!important;
    overflow:auto!important;
}
.notify-stack{
    position:fixed!important;
    top:18px!important;
    right:18px!important;
    bottom:auto!important;
    left:auto!important;
    margin:0!important;
    transform:none!important;
}
@media(max-width:760px){
    .swal2-container{padding:10px!important}
    .swal2-popup{max-height:calc(100dvh - 20px)!important}
    .notify-stack{top:10px!important;right:10px!important}
}
</style>

<link rel="stylesheet" href="../assets/installer-v1.0.27.css?v=1.0.27">
<style id="izzy-installer-mobile-v473">
/* v4.7.3 — mobile wizard: one step at a time, compact shell, internal vertical scroll */
@media (max-width:720px){
    html,body{
        width:100%!important;
        height:100%!important;
        min-height:100%!important;
        overflow:hidden!important;
        background:#eef4f8!important;
    }
    body{
        min-width:0!important;
    }
    .page{
        width:100%!important;
        height:100dvh!important;
        min-height:0!important;
        padding:8px!important;
        display:block!important;
        overflow:hidden!important;
    }
    .installer{
        width:100%!important;
        height:calc(100dvh - 16px)!important;
        max-height:calc(100dvh - 16px)!important;
        min-height:0!important;
        margin:0!important;
        display:grid!important;
        grid-template-columns:1fr!important;
        grid-template-rows:64px minmax(0,1fr)!important;
        grid-template-areas:"head" "main"!important;
        gap:8px!important;
        overflow:hidden!important;
        background:transparent!important;
        border:0!important;
        border-radius:0!important;
        box-shadow:none!important;
    }
    .installer::after{display:none!important}
    .installer-head{
        grid-area:head!important;
        width:100%!important;
        height:64px!important;
        min-height:64px!important;
        margin:0!important;
        padding:8px 10px!important;
        display:flex!important;
        align-items:center!important;
        justify-content:space-between!important;
        gap:8px!important;
        border:1px solid #d6e3eb!important;
        border-top:3px solid #0a91c7!important;
        border-radius:18px!important;
        background:#fff!important;
        box-shadow:0 8px 22px rgba(8,42,80,.055)!important;
    }
    .installer-brand{
        min-width:0!important;
        display:flex!important;
        align-items:center!important;
        gap:9px!important;
    }
    .installer-brand img{
        width:55px!important;
        height:40px!important;
        flex:0 0 auto!important;
        object-fit:contain!important;
    }
    .installer-brand div{min-width:0!important}
    .installer-brand p,
    .installer-brand small{display:none!important}
    .installer-brand strong{
        display:block!important;
        margin:0!important;
        color:#0a315d!important;
        font-size:15px!important;
        line-height:1.1!important;
        white-space:nowrap!important;
        overflow:hidden!important;
        text-overflow:ellipsis!important;
    }
    .installer-badge{
        flex:0 0 auto!important;
        min-height:34px!important;
        padding:0 10px!important;
        border-radius:12px!important;
        font-size:9px!important;
        line-height:1!important;
        white-space:nowrap!important;
    }

    /* En teléfono mostramos solo el paso activo; el resumen lateral ya no roba espacio. */
    .side{display:none!important}

    .main{
        grid-area:main!important;
        width:100%!important;
        height:100%!important;
        min-height:0!important;
        margin:0!important;
        display:flex!important;
        flex-direction:column!important;
        overflow:hidden!important;
        border:1px solid #d6e3eb!important;
        border-radius:20px!important;
        background:#fff!important;
        box-shadow:0 10px 26px rgba(8,42,80,.055)!important;
    }
    .content-head{
        flex:0 0 auto!important;
        width:100%!important;
        min-height:0!important;
        padding:13px 14px 7px!important;
        display:grid!important;
        grid-template-columns:minmax(0,1fr) auto!important;
        gap:8px!important;
        align-items:start!important;
    }
    .content-head-copy{min-width:0!important}
    .content-head .eyebrow{
        margin:0 0 3px!important;
        font-size:9px!important;
        letter-spacing:.08em!important;
    }
    .content-head h1{
        margin:0!important;
        font-size:25px!important;
        line-height:1.02!important;
    }
    .content-head p:not(.eyebrow){
        margin:5px 0 0!important;
        max-width:none!important;
        font-size:10.5px!important;
        line-height:1.35!important;
    }
    .content-step-pill{
        min-height:30px!important;
        margin:3px 0 0!important;
        padding:0 9px!important;
        font-size:8.5px!important;
        border-radius:999px!important;
    }
    .content-progress{
        flex:0 0 auto!important;
        height:4px!important;
        margin:0 14px 8px!important;
    }
    .step-panel{
        flex:1 1 auto!important;
        min-height:0!important;
        height:auto!important;
        display:flex!important;
        flex-direction:column!important;
        overflow:hidden!important;
        border-top:0!important;
    }
    .scroll{
        flex:1 1 auto!important;
        min-height:0!important;
        height:auto!important;
        overflow-y:auto!important;
        overflow-x:hidden!important;
        overscroll-behavior:contain!important;
        -webkit-overflow-scrolling:touch!important;
        scrollbar-gutter:auto!important;
        padding:0 14px 14px!important;
    }
    .step-title{display:none!important}
    .step-guide{
        grid-template-columns:34px minmax(0,1fr)!important;
        gap:9px!important;
        margin:0 0 9px!important;
        padding:9px 10px!important;
        border-radius:12px!important;
    }
    .step-guide-icon{
        width:30px!important;
        height:30px!important;
        border-radius:9px!important;
        font-size:12px!important;
    }
    .step-guide strong{font-size:11.5px!important;line-height:1.2!important}
    .step-guide small{font-size:9.4px!important;line-height:1.3!important}
    .detected-site-url{
        grid-template-columns:32px minmax(0,1fr)!important;
        gap:9px!important;
        margin:0 0 10px!important;
        padding:9px 10px!important;
        border-radius:12px!important;
    }
    .detected-site-url-icon{
        width:30px!important;
        height:30px!important;
        border-radius:9px!important;
    }
    .detected-site-url small{font-size:8px!important;line-height:1.2!important}
    .detected-site-url strong{font-size:10.8px!important;line-height:1.25!important}
    .detected-site-url p{font-size:9px!important;line-height:1.3!important}

    .form-grid,
    .form-grid.three,
    .admin-grid-v26,
    .smtp-grid-top,
    .smtp-grid-middle,
    .smtp-grid-bottom,
    .graph-grid,
    .method-grid,
    .review,
    .test-row{
        grid-template-columns:1fr!important;
        gap:9px!important;
    }
    .admin-grid-v26 .admin-full,
    .admin-grid-v26 .admin-half,
    .admin-grid-v26 .admin-username,
    .admin-grid-v26 .admin-email,
    .smtp-grid-top .smtp-server,
    .smtp-grid-top .smtp-port,
    .smtp-grid-top .smtp-security,
    .graph-destination,
    .db-password,
    .db-create-notice{grid-column:1/-1!important}
    .field{
        min-width:0!important;
        font-size:11.5px!important;
    }
    .field-label{
        min-height:0!important;
        font-size:11.5px!important;
        line-height:1.25!important;
    }
    .field>input,
    .field>select,
    .password-wrap{margin-top:4px!important}
    input,select{
        width:100%!important;
        min-height:44px!important;
        height:44px!important;
        padding:7px 11px!important;
        border-radius:11px!important;
        font-size:12.5px!important;
    }
    .password-wrap input{
        padding-right:48px!important;
    }
    .password-toggle{
        width:34px!important;
        min-width:34px!important;
        height:34px!important;
        min-height:34px!important;
        right:5px!important;
        top:50%!important;
        transform:translateY(-50%)!important;
    }
    .field small{
        margin-top:3px!important;
        font-size:9.3px!important;
        line-height:1.3!important;
    }
    .notice,
    .db-create-notice,
    .email-fields,
    .review-note,
    .admin-safe-note,
    .admin-security-summary{
        margin-top:0!important;
        padding:9px 10px!important;
        border-radius:12px!important;
    }
    .db-create-notice{
        grid-template-columns:32px minmax(0,1fr)!important;
        gap:9px!important;
    }
    .db-create-notice .notice-icon{width:30px!important;height:30px!important}
    .db-create-notice strong{font-size:10.8px!important}
    .db-create-notice span:not(.notice-icon){font-size:9.2px!important;line-height:1.3!important}
    .method{
        min-height:0!important;
        padding:9px 10px!important;
        border-radius:12px!important;
    }
    .method strong{font-size:11.5px!important}
    .method small{font-size:9.2px!important;line-height:1.3!important}
    .email-fields{margin-top:9px!important}
    .graph-help-card{grid-template-columns:1fr!important;gap:8px!important}
    .review-card{
        grid-template-columns:100px minmax(0,1fr)!important;
        gap:8px!important;
        padding:9px 10px!important;
    }

    .footer{
        flex:0 0 auto!important;
        position:relative!important;
        inset:auto!important;
        width:100%!important;
        min-height:56px!important;
        margin:0!important;
        padding:7px 10px calc(7px + env(safe-area-inset-bottom,0px))!important;
        display:grid!important;
        grid-template-columns:auto minmax(0,1fr)!important;
        align-items:center!important;
        gap:8px!important;
        background:#fff!important;
        border-top:1px solid #dde7ed!important;
        box-shadow:0 -6px 18px rgba(17,52,80,.05)!important;
    }
    .footer > span{
        grid-column:1!important;
        font-size:8px!important;
        line-height:1.2!important;
    }
    .footer > .button.secondary,
    .footer > .installer-back{
        grid-column:1!important;
        min-width:94px!important;
        width:auto!important;
        justify-self:start!important;
    }
    .footer-right{
        grid-column:2!important;
        justify-self:stretch!important;
        width:100%!important;
        min-width:0!important;
        margin:0!important;
    }
    .footer-right .button,
    .footer-right button{
        width:100%!important;
        min-width:0!important;
        min-height:42px!important;
        padding:7px 12px!important;
        border-radius:11px!important;
        font-size:11.5px!important;
    }
}
@media (max-width:420px){
    .page{padding:5px!important}
    .installer{
        height:calc(100dvh - 10px)!important;
        max-height:calc(100dvh - 10px)!important;
        grid-template-rows:58px minmax(0,1fr)!important;
        gap:5px!important;
    }
    .installer-head{
        height:58px!important;
        min-height:58px!important;
        padding:6px 8px!important;
        border-radius:15px!important;
    }
    .installer-brand img{width:49px!important;height:36px!important}
    .installer-brand strong{font-size:13.5px!important}
    .installer-badge{min-height:30px!important;padding:0 8px!important;font-size:8px!important}
    .main{border-radius:16px!important}
    .content-head{padding:10px 11px 6px!important}
    .content-head h1{font-size:22px!important}
    .content-head p:not(.eyebrow){font-size:9.6px!important}
    .content-step-pill{min-height:27px!important;padding:0 7px!important;font-size:7.8px!important}
    .content-progress{margin:0 11px 7px!important}
    .scroll{padding:0 11px 10px!important}
    input,select{min-height:42px!important;height:42px!important}
    .footer{min-height:52px!important;padding:5px 8px calc(5px + env(safe-area-inset-bottom,0px))!important}
}
</style>
</head>
<body class="installer-step-<?=$step?>" data-installer-step="<?=$step?>" data-installer-has-state="<?=(!empty($state['step1'])||!empty($state['step2'])||!empty($state['step3']))?'1':'0'?>">
<main class="page">
<div class="installer">
<header class="installer-head">
    <div class="installer-brand">
        <img src="../assets/izzy/logo-full-dark.png?v=430" alt="IZZY">
        <div><p>ASISTENTE DE INSTALACIÓN</p><strong>IZZY CMS Core</strong><small>Configuración guiada, clara y segura.</small></div>
    </div>
    <span class="installer-badge <?=$reinstallMode?'reinstall':''?>"><?=$reinstallMode?'Reinstalación':'Instalación nueva'?></span>
</header>
<aside class="side">
    <div class="side-progress">
        <strong>Paso <?=$step?> de 4</strong>
        <small>Avance de instalación</small>
        <div><span style="width:<?=$progress?>%"></span></div>
    </div>
    <div class="side-list">
        <?php foreach($stepLabels as $number=>$label): ?>
        <div class="side-item <?=$number===$step?'active':($number<$step?'complete':'')?>">
            <b><?=$number<$step?'✓':$number?></b><span><strong><?=installer_h($label[0])?></strong><small><?=installer_h($label[1])?></small></span>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="side-foot">Instalador <?=installer_h(CMS_INSTALLER_VERSION)?> · El bloqueo se crea únicamente al terminar correctamente.</div>
</aside>

<section class="main">
    <div class="content-head">
        <div class="content-head-copy">
            <p class="eyebrow"><?php echo $step===1?'CONEXIÓN SEGURA':($step===2?'CUENTA PRINCIPAL':($step===3?'CORREO DEL SISTEMA':'REVISIÓN FINAL')); ?></p>
            <h1><?php echo $step===1?'Base de datos':($step===2?'Administrador':($step===3?'Correo':'Confirmación')); ?></h1>
            <p><?php echo $step===1?'Conecta MySQL o MariaDB. El asistente instalará el esquema completo de IZZY.':($step===2?'Crea la cuenta principal que administrará el sistema.':($step===3?'Configura SMTP, Microsoft Graph o déjalo pendiente para después.':'Revisa la configuración antes de completar la instalación.')); ?></p>
        </div>
        <span class="content-step-pill">PASO <?=$step?> DE 4</span>
    </div>
    <div class="content-progress" aria-label="Progreso de instalación"><span style="width:<?=$progress?>%"></span></div>

<section class="step-panel">
        <div class="scroll">
            <?php if($error!==''): ?><div class="alert error" data-ui-notify="error" role="alert" hidden><?=installer_h($error)?></div><?php endif; ?>
            <?php if($notice!==''): ?><div class="alert success" data-ui-notify="success" role="status" hidden><?=installer_h($notice)?></div><?php endif; ?>
            <?php if($flash): ?><div class="alert <?=installer_h((string)($flash['type']??'info'))?>" data-ui-notify="<?=installer_h((string)($flash['type']??'info'))?>" role="status" hidden><?=installer_h((string)($flash['message']??''))?></div><?php endif; ?>

            <div class="step-guide" aria-label="Objetivo del paso actual">
                <span class="step-guide-icon" aria-hidden="true"><?=$step?></span>
                <div><strong><?=installer_h($stepGuides[$step][0])?></strong><small><?=installer_h($stepGuides[$step][1])?></small></div>
            </div>

            <?php if($step===1): ?>
            <div class="step-title">
                <p class="eyebrow">CONEXIÓN SEGURA</p>
                <h1>Base de datos</h1>
                <p>Valida MySQL o MariaDB. La base y el esquema se crearán únicamente cuando confirmes el último paso.</p>
            </div>
            <div class="detected-site-url" aria-label="URL detectada automáticamente">
                <span class="detected-site-url-icon" aria-hidden="true">↗</span>
                <div>
                    <small>URL DEL SITIO DETECTADA AUTOMÁTICAMENTE</small>
                    <strong data-detected-site-url><?=installer_h($state['site_url'])?></strong>
                    <p>No necesitas escribirla. El instalador la toma directamente del servidor y la guardará en la configuración.</p>
                </div>
            </div>
            <?php if($reinstallMode): ?>
            <div class="alert warning" data-ui-notify="warning" hidden><strong>Reinstalación limpia detectada.</strong> La conexión existente fue precargada. No se eliminará la base completa ni se tocarán tablas ajenas al CMS.</div>
            <?php endif; ?>
            <form id="database-form" method="post">
                <input type="hidden" name="csrf" value="<?=installer_h(installer_csrf_token())?>">
                <input type="hidden" name="action" value="database">
                <input type="hidden" name="current_step" value="1">
                <input type="hidden" name="site_url_detected" value="<?=installer_h($state['site_url'])?>" data-site-url-input>
                <div class="form-grid three">
                    <label class="field db-host"><span class="field-label">Servidor <span class="required">*</span></span><input name="host" required value="<?=installer_h($_POST['host']??$db['host']??'localhost')?>" autocomplete="off"><small>Generalmente localhost.</small></label>
                    <label class="field"><span class="field-label">Puerto <span class="required">*</span></span><input type="number" name="port" min="1" max="65535" required value="<?=installer_h($_POST['port']??$db['port']??3306)?>"></label>
                    <label class="field"><span class="field-label">Base de datos <span class="required">*</span></span><input name="dbname" required value="<?=installer_h($_POST['dbname']??$db['dbname']??installer_suggested_database_name())?>" autocomplete="off"><small>Sugerencia: <strong><?=installer_h(installer_suggested_database_name())?></strong>. Puedes cambiarla si tu hosting exige otro nombre.</small></label>
                    <label class="field db-user"><span class="field-label">Usuario MySQL <span class="required">*</span></span><input name="username" required value="<?=installer_h($_POST['username']??$db['username']??'')?>" autocomplete="username"></label>
                    <div class="field db-password"><span class="field-label">Contraseña MySQL</span><div class="password-wrap"><input type="password" name="password" value="<?=installer_h($_POST['password']??$db['password']??'')?>" autocomplete="new-password"><button type="button" class="password-toggle" data-password-toggle tabindex="-1" aria-label="Mostrar contraseña" aria-pressed="false"><svg class="icon-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.3 12s3.5-6 9.7-6 9.7 6 9.7 6-3.5 6-9.7 6-9.7-6-9.7-6Z" fill="none" stroke="currentColor" stroke-width="1.9"/><circle cx="12" cy="12" r="2.7" fill="none" stroke="currentColor" stroke-width="1.9"/></svg><svg class="icon-hide" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A11 11 0 0 1 12 6c6.2 0 9.7 6 9.7 6a14.5 14.5 0 0 1-3 3.5M6.6 6.8C3.8 8.7 2.3 12 2.3 12s3.5 6 9.7 6c1.4 0 2.7-.3 3.8-.8M9.9 9.9a3 3 0 0 0 4.2 4.2" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg></button></div><small><?=$savedConfig?'Déjala vacía para reutilizar la contraseña guardada.':'Puede estar vacía únicamente si el servidor no exige contraseña.'?></small></div>
                    <div class="notice field full db-create-notice"><span class="notice-icon" aria-hidden="true">⚙</span><div><strong>Creación automática de la base de datos</strong><span>IZZY validará ahora el servidor MySQL. Si la base no existe, se creará automáticamente únicamente cuando confirmes el último paso.</span></div></div>
                </div>
            </form>

            <?php elseif($step===2): ?>
            <div class="step-title"><p class="eyebrow">CUENTA PRINCIPAL</p><h1>Administrador</h1><p>Crea la cuenta Owner que tendrá control protegido sobre el CMS.</p></div>
            <form id="admin-form" method="post">
                <input type="hidden" name="csrf" value="<?=installer_h(installer_csrf_token())?>">
                <input type="hidden" name="action" value="administrator">
                <input type="hidden" name="current_step" value="2">
                <div class="form-grid admin-grid-v26">
                    <label class="field admin-full"><span class="field-label">Nombre completo <span class="required">*</span></span><input name="full_name" required value="<?=installer_h($_POST['full_name']??$admin['full_name']??'')?>" autocomplete="name"></label>
                    <label class="field admin-half admin-username"><span class="field-label">Usuario <span class="required">*</span></span><input name="admin_username" required minlength="4" maxlength="80" pattern="[A-Za-z0-9._-]+" value="<?=installer_h($_POST['admin_username']??$admin['username']??'')?>" autocomplete="username"><small>Letras, números, punto, guion o guion bajo.</small></label>
                    <label class="field admin-half admin-email"><span class="field-label">Correo <span class="required">*</span></span><input type="email" name="admin_email" required value="<?=installer_h($_POST['admin_email']??$admin['email']??'')?>" autocomplete="email"></label>
                    <div class="field admin-half"><span class="field-label">Contraseña <span class="required">*</span></span><div class="password-wrap"><input type="password" name="admin_password" required minlength="8" value="<?=installer_h($_POST['admin_password']??$admin['password_draft']??'')?>" autocomplete="new-password"><button type="button" class="password-toggle" data-password-toggle tabindex="-1" aria-label="Mostrar contraseña" aria-pressed="false"><svg class="icon-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.3 12s3.5-6 9.7-6 9.7 6 9.7 6-3.5 6-9.7 6-9.7-6-9.7-6Z" fill="none" stroke="currentColor" stroke-width="1.9"/><circle cx="12" cy="12" r="2.7" fill="none" stroke="currentColor" stroke-width="1.9"/></svg><svg class="icon-hide" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A11 11 0 0 1 12 6c6.2 0 9.7 6 9.7 6a14.5 14.5 0 0 1-3 3.5M6.6 6.8C3.8 8.7 2.3 12 2.3 12s3.5 6 9.7 6c1.4 0 2.7-.3 3.8-.8M9.9 9.9a3 3 0 0 0 4.2 4.2" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg></button></div><small>Mínimo 8 caracteres; usa una clave única.</small></div>
                    <div class="field admin-half"><span class="field-label">Confirmar contraseña <span class="required">*</span></span><div class="password-wrap"><input type="password" name="admin_password_confirmation" required minlength="8" value="<?=installer_h($_POST['admin_password_confirmation']??$admin['password_confirmation_draft']??'')?>" autocomplete="new-password"><button type="button" class="password-toggle" data-password-toggle tabindex="-1" aria-label="Mostrar contraseña" aria-pressed="false"><svg class="icon-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.3 12s3.5-6 9.7-6 9.7 6 9.7 6-3.5 6-9.7 6-9.7-6-9.7-6Z" fill="none" stroke="currentColor" stroke-width="1.9"/><circle cx="12" cy="12" r="2.7" fill="none" stroke="currentColor" stroke-width="1.9"/></svg><svg class="icon-hide" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A11 11 0 0 1 12 6c6.2 0 9.7 6 9.7 6a14.5 14.5 0 0 1-3 3.5M6.6 6.8C3.8 8.7 2.3 12 2.3 12s3.5 6 9.7 6c1.4 0 2.7-.3 3.8-.8M9.9 9.9a3 3 0 0 0 4.2 4.2" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg></button></div></div>
                    <div class="admin-safe-note admin-full"><strong>La contraseña no se guarda en texto plano.</strong> El asistente conserva únicamente su hash para crear la cuenta durante la confirmación final.</div>
                    <div class="admin-security-summary admin-full" aria-label="Resumen de seguridad del administrador">
                        <div class="admin-security-item"><div class="admin-security-icon">O</div><div><strong>Rol Owner</strong><span>La cuenta inicial tendrá control completo del CMS.</span></div></div>
                        <div class="admin-security-item"><div class="admin-security-icon">@</div><div><strong>Acceso por usuario o correo</strong><span>Podrás iniciar sesión con cualquiera de los dos datos.</span></div></div>
                        <div class="admin-security-item"><div class="admin-security-icon">#</div><div><strong>Credencial protegida</strong><span>La contraseña se almacena únicamente como hash seguro.</span></div></div>
                    </div>
                </div>
            </form>

            <?php elseif($step===3): ?>
            <div class="step-title"><p class="eyebrow">CORREO DEL SISTEMA</p><h1>Configura el correo del sistema</h1><p>Elige cómo enviará correos el CMS. Puedes probar la conexión ahora o dejarla pendiente sin bloquear la instalación.</p></div>
            <form id="email-form" method="post" data-email-form>
                <input type="hidden" name="csrf" value="<?=installer_h(installer_csrf_token())?>">
                <input type="hidden" name="current_step" value="3">
                <div class="method-grid">
                    <label class="method"><input type="radio" name="email_method" value="LATER" <?=$selectedMethod==='LATER'?'checked':''?>><span class="method-icon">→</span><span><strong>Configurar después</strong><small>Finaliza la instalación y configura el correo desde Administración → Correo.</small></span></label>
                    <label class="method"><input type="radio" name="email_method" value="SMTP" <?=$selectedMethod==='SMTP'?'checked':''?>><span class="method-icon">✉</span><span><strong>SMTP</strong><small>Hosting, Gmail, Microsoft 365 SMTP u otro proveedor compatible.</small></span></label>
                    <label class="method"><input type="radio" name="email_method" value="GRAPH" <?=$selectedMethod==='GRAPH'?'checked':''?>><span class="method-icon">G</span><span><strong>Microsoft Graph</strong><small>OAuth2 mediante Tenant ID, Client ID y Client Secret.</small></span></label>
                </div>

                <div class="email-fields" data-email-panel="SMTP" <?=$selectedMethod!=='SMTP'?'hidden':''?>>
                    <div class="form-grid three smtp-grid-top">
                        <label class="field smtp-server"><span class="field-label">Servidor SMTP <span class="required">*</span></span><input name="smtp_server" value="<?=installer_h($_POST['smtp_server']??$emailConfiguration['server']??'')?>" data-email-required="SMTP" placeholder="smtp.example.com"></label>
                        <label class="field smtp-port"><span class="field-label">Puerto <span class="required">*</span></span><input type="number" name="smtp_port" min="1" max="65535" value="<?=installer_h($_POST['smtp_port']??$emailConfiguration['port']??587)?>" data-email-required="SMTP"></label>
                        <label class="field smtp-security"><span class="field-label">Seguridad</span><select name="smtp_secure" data-email-required="SMTP"><option value="tls" <?=($_POST['smtp_secure']??$emailConfiguration['smtp_secure']??'tls')==='tls'?'selected':''?>>TLS</option><option value="ssl" <?=($_POST['smtp_secure']??$emailConfiguration['smtp_secure']??'')==='ssl'?'selected':''?>>SSL</option></select></label>
                    </div>
                    <div class="form-grid smtp-grid-middle">
                        <label class="field"><span class="field-label">Usuario / correo emisor <span class="required">*</span></span><input type="email" name="smtp_user" value="<?=installer_h($_POST['smtp_user']??$emailConfiguration['correo']??'')?>" data-email-required="SMTP" autocomplete="username"></label>
                        <div class="field"><span class="field-label">Contraseña o App Password <span class="required">*</span></span><div class="password-wrap"><input type="password" name="smtp_password" value="<?=installer_h($_POST['smtp_password']??$state['email_secrets']['SMTP']??'')?>" data-email-required="SMTP" autocomplete="new-password"><button type="button" class="password-toggle" data-password-toggle tabindex="-1" aria-label="Mostrar contraseña" aria-pressed="false"><svg class="icon-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.3 12s3.5-6 9.7-6 9.7 6 9.7 6-3.5 6-9.7 6-9.7-6-9.7-6Z" fill="none" stroke="currentColor" stroke-width="1.9"/><circle cx="12" cy="12" r="2.7" fill="none" stroke="currentColor" stroke-width="1.9"/></svg><svg class="icon-hide" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A11 11 0 0 1 12 6c6.2 0 9.7 6 9.7 6a14.5 14.5 0 0 1-3 3.5M6.6 6.8C3.8 8.7 2.3 12 2.3 12s3.5 6 9.7 6c1.4 0 2.7-.3 3.8-.8M9.9 9.9a3 3 0 0 0 4.2 4.2" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg></button></div><small><?=$emailConfiguration?'Déjala vacía para conservar el secreto probado.':'La credencial se cifra antes de guardarse.'?></small></div>
                    </div>
                    <div class="form-grid smtp-grid-bottom">
                        <label class="field full">Destino interno opcional<input type="email" name="smtp_destinatario" value="<?=installer_h($_POST['smtp_destinatario']??$emailConfiguration['destinatario']??'')?>" placeholder="admin@example.com"></label>
                        <div class="test-row field full"><label class="field">Enviar prueba a<input type="email" name="smtp_test_to" value="<?=installer_h($_POST['smtp_test_to']??'')?>" placeholder="Si queda vacío, se usará el emisor o destino interno"></label><button type="button" class="button secondary" data-email-test><span class="spinner"></span><svg class="ui-action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12l5 5L20 6"/><path d="M20 12a8 8 0 1 1-4-6.9"/></svg><span>Probar configuración</span></button></div>
                    </div>
                </div>

                <div class="email-fields graph-panel" data-email-panel="GRAPH" <?=$selectedMethod!=='GRAPH'?'hidden':''?>>
                    <div class="graph-intro-card">
                        <span class="graph-intro-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M5 5h14v14H5zM9 9h6v6H9z" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                        </span>
                        <div>
                            <strong>Microsoft Graph · conexión segura</strong>
                            <small>Usa el Tenant ID, Client ID y el VALUE del Client Secret de tu aplicación registrada en Microsoft Entra ID.</small>
                        </div>
                    </div>

                    <div class="form-grid graph-grid">
                        <label class="field graph-tenant"><span class="field-label">Tenant ID <span class="required">*</span></span><input name="tenant_id" value="<?=installer_h($_POST['tenant_id']??$emailConfiguration['tenant_id']??'')?>" data-email-required="GRAPH"></label>
                        <label class="field graph-client"><span class="field-label">Client ID <span class="required">*</span></span><input name="client_id" value="<?=installer_h($_POST['client_id']??$emailConfiguration['client_id']??'')?>" data-email-required="GRAPH"></label>

                        <div class="field graph-secret"><span class="field-label">Client Secret VALUE <span class="required">*</span></span><div class="password-wrap"><input type="password" name="client_secret" value="<?=installer_h($_POST['client_secret']??$state['email_secrets']['GRAPH']??'')?>" data-email-required="GRAPH" autocomplete="new-password"><button type="button" class="password-toggle" data-password-toggle tabindex="-1" aria-label="Mostrar contraseña" aria-pressed="false"><svg class="icon-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.3 12s3.5-6 9.7-6 9.7 6 9.7 6-3.5 6-9.7 6-9.7-6-9.7-6Z" fill="none" stroke="currentColor" stroke-width="1.9"/><circle cx="12" cy="12" r="2.7" fill="none" stroke="currentColor" stroke-width="1.9"/></svg><svg class="icon-hide" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A11 11 0 0 1 12 6c6.2 0 9.7 6 9.7 6a14.5 14.5 0 0 1-3 3.5M6.6 6.8C3.8 8.7 2.3 12 2.3 12s3.5 6 9.7 6c1.4 0 2.7-.3 3.8-.8M9.9 9.9a3 3 0 0 0 4.2 4.2" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg></button></div><small><?=$emailConfiguration?'Déjalo vacío para conservar el secreto probado.':'Usa el valor del secreto, no su identificador.'?></small></div>
                        <label class="field graph-mailbox"><span class="field-label">Graph User / mailbox <span class="required">*</span></span><input type="email" name="graph_user" value="<?=installer_h($_POST['graph_user']??$emailConfiguration['graph_user']??'')?>" data-email-required="GRAPH" placeholder="facturacion@tuempresa.com"></label>

                        <label class="field full graph-destination">Destino interno opcional<input type="email" name="graph_destinatario" value="<?=installer_h($_POST['graph_destinatario']??$emailConfiguration['destinatario']??'')?>" placeholder="Correo que recibirá avisos internos"></label>

                        <label class="check field full"><input type="checkbox" name="save_to_sent_items" value="1" <?=isset($_POST['save_to_sent_items'])||(int)($emailConfiguration['save_to_sent_items']??1)===1?'checked':''?>><span>Guardar una copia en Elementos enviados del buzón.</span></label>

                        <div class="graph-help-card field full">
                            <div><strong>Antes de probar</strong><span>Verifica que la aplicación tenga permisos de envío y que el buzón indicado exista en Microsoft 365.</span></div>
                            <div><strong>Qué se guarda</strong><span>El secreto se cifra antes de almacenarse y nunca se muestra nuevamente en texto plano.</span></div>
                        </div>

                        <div class="test-row field full"><label class="field">Enviar prueba a<input type="email" name="graph_test_to" value="<?=installer_h($_POST['graph_test_to']??'')?>" placeholder="Si queda vacío, se usará el buzón o destino interno"></label><button type="button" class="button secondary" data-email-test><span class="spinner"></span><svg class="ui-action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12l5 5L20 6"/><path d="M20 12a8 8 0 1 1-4-6.9"/></svg><span>Probar configuración</span></button></div>
                    </div>
                </div>
                <div class="notice" style="margin-top:16px"><strong>Una sola configuración para todo el sistema</strong>Al guardar, el asistente copiará esta conexión a todos los tipos de correo disponibles. Después podrás personalizarlos individualmente desde Administración → Correo.</div>
                <div class="email-actions" data-email-actions hidden aria-hidden="true"></div>
                <div class="test-result" data-email-result aria-live="polite"></div>
            </form>

            <?php else: ?>
            <div class="step-title"><p class="eyebrow">REVISIÓN FINAL</p><h1>Confirma la instalación</h1><p>Revisa la información antes de crear la configuración y el bloqueo definitivo.</p></div>
            <div class="review">
                <div class="review-card"><strong>URL del sitio</strong><span><?=installer_h($state['site_url'])?></span></div>
                <div class="review-card"><strong>Base de datos</strong><span><?=installer_h(($db['host']??'').':'.($db['port']??3306).' / '.($db['dbname']??''))?></span></div>
                <div class="review-card"><strong>Administrador</strong><span><?=installer_h(($admin['full_name']??'').' · '.($admin['username']??'').' · '.($admin['email']??''))?></span></div>
                <div class="review-card"><strong>Correo</strong><span><?=installer_h(($email['method']??'LATER')==='LATER'?'Configurar después':($email['method'].' · se guardará al finalizar'))?></span></div>
            </div>
            <div class="review-note"><strong>Todo listo.</strong> Al finalizar se creará la base de datos si todavía no existe, se instalará el esquema, se guardará <code>config/config.php</code> y, como última operación, <code>config/install.lock</code>. Si ocurre un error, no se activará el bloqueo.</div>
            <form id="finish-form" method="post"><input type="hidden" name="csrf" value="<?=installer_h(installer_csrf_token())?>"><input type="hidden" name="action" value="finish"><input type="hidden" name="current_step" value="4"><input type="hidden" name="confirm_install" value="0" data-install-confirmed></form>
            <?php endif; ?>
        </div>

        <footer class="footer">
            <?php if($step>1): ?><a class="button installer-back" data-installer-back href="index.php?step=<?=$step-1?>"><svg class="ui-action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>Atrás</a><?php else: ?><span></span><?php endif; ?>
            <div class="footer-right">
                <?php if($step===1): ?><button type="submit" form="database-form"><span>Siguiente</span><svg class="ui-action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg></button><?php endif; ?>
                <?php if($step===2): ?><button type="submit" form="admin-form"><span>Siguiente</span><svg class="ui-action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg></button><?php endif; ?>
                <?php if($step===3): ?>
                <button type="submit" form="email-form" name="action" value="skip_email" data-later-button <?=$selectedMethod!=='LATER'?'hidden':''?>><span>Configurar después</span><svg class="ui-action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg></button>
                <button type="submit" form="email-form" name="action" value="save_email" data-save-email-button <?=$selectedMethod==='LATER'?'hidden':''?>><svg class="ui-action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l2 2v14H5z"/><path d="M8 4v5h8V4M8 15h8"/></svg><span>Guardar y continuar</span></button>
                <?php endif; ?>
                <?php if($step===4): ?><button type="submit" form="finish-form"><svg class="ui-action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l4 4 10-10"/></svg><span>Finalizar instalación</span></button><?php endif; ?>
            </div>
        </footer>
    </section>
</section>
</div>
</main>
<script src="../assets/vendor/jquery/jquery.min.js"></script>
<script src="../assets/vendor/select2/select2.local.js"></script>
<script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
<script src="../assets/vendor/show-notify/showNotify.js"></script>
<script src="../assets/vendor/cms-modal/cmsModal.js"></script>
<script src="../assets/ui-standards.js"></script>
<script>
(() => {
  const output=document.querySelector('[data-detected-site-url]');
  const input=document.querySelector('[data-site-url-input]');
  if(!output&&!input)return;
  try {
    const url=new URL(window.location.href);
    const visiblePath=url.pathname.endsWith('/')?url.pathname:(url.pathname + '/');
    const detected=url.origin + visiblePath;
    if(output)output.textContent=detected;
    if(input)input.value=detected;
  } catch(error) {}
})();
(() => {
  document.querySelectorAll('[data-password-toggle]').forEach(button=>{
    button.setAttribute('tabindex','-1');
    button.addEventListener('click',()=>{
      const input=button.closest('.password-wrap')?.querySelector('input');
      if(!input)return;
      const showing=input.type==='text';
      input.type=showing?'password':'text';
      button.setAttribute('aria-pressed',showing?'false':'true');
      button.setAttribute('aria-label',showing?'Mostrar contraseña':'Ocultar contraseña');
    });
  });

  const emailForm=document.querySelector('[data-email-form]');
  if(!emailForm)return;
  const methodInputs=[...emailForm.querySelectorAll('[name="email_method"]')];
  const panels=[...emailForm.querySelectorAll('[data-email-panel]')];
  const actions=emailForm.querySelector('[data-email-actions]');
  const laterButton=document.querySelector('[data-later-button]');
  const saveButton=document.querySelector('[data-save-email-button]');
  const result=emailForm.querySelector('[data-email-result]');

  const selectedMethod=()=>methodInputs.find(input=>input.checked)?.value||'LATER';
  const update=()=> {
    const method=selectedMethod();
    panels.forEach(panel=>panel.hidden=panel.dataset.emailPanel!==method);
    emailForm.querySelectorAll('[data-email-required]').forEach(field=> {
      const active=field.dataset.emailRequired===method;
      const hasStoredSecret=(field.name==='smtp_password'&&<?=json_encode(!empty($emailConfiguration['password']))?>)
        ||(field.name==='client_secret'&&<?=json_encode(!empty($emailConfiguration['client_secret']))?>);
      field.required=active&&!hasStoredSecret;
      field.disabled=!active;
    });
    emailForm.querySelectorAll('[name$="_destinatario"],[name$="_test_to"],[name="save_to_sent_items"]').forEach(field=> {
      const parent=field.closest('[data-email-panel]');
      if(parent)field.disabled=parent.dataset.emailPanel!==method;
    });
    if(actions)actions.hidden=true;
    if(laterButton)laterButton.hidden=method!=='LATER';
    if(saveButton)saveButton.hidden=method==='LATER';
    if(result)result.innerHTML='';
  };
  methodInputs.forEach(input=>input.addEventListener('change',update));
  update();

  emailForm.querySelectorAll('[data-email-test]').forEach(button=>button.addEventListener('click',async event=> {
      event.preventDefault();
      if(!emailForm.reportValidity())return;
      const data=new FormData(emailForm);
      data.set('action','test_email');
      data.set('ajax','1');
      button.disabled=true;
      button.classList.add('loading');
      if(result){result.textContent='Probando la conexión y el envío…';result.hidden=false;}
      window.showNotify?.('Probando la conexión y el envío…','info',{title:'Prueba de correo'});
      try {
        const response=await fetch('index.php?step=3',{method:'POST',body:data,headers:{'X-Requested-With':'XMLHttpRequest'}});
        const payload=await response.json();
        if(!response.ok||!payload.success)throw new Error(payload.message||'No se pudo completar la prueba.');
        const text=payload.message||'Correo de prueba enviado correctamente.';
        if(result){result.textContent='';result.hidden=true;}
        const message=result?.querySelector('.alert');
        if(message)message.textContent=text;
        if(window.showNotify)window.showNotify(text,'success',{title:'Prueba completada'});
      } catch(error) {
        const text=error instanceof Error?error.message:'No se pudo completar la prueba.';
        if(result){result.textContent='';result.hidden=true;}
        const message=result?.querySelector('.alert');
        if(message)message.textContent=text;
        if(window.showNotify)window.showNotify(text,'error',{title:'No se pudo enviar'});
      } finally {
        button.disabled=false;
        button.classList.remove('loading');
      }
    }));
})();

const izzySwalActionIcons=(popup,confirmIcon='check',cancelIcon='back')=>{
  const icons={
    check:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l4 4 10-10"/></svg>',
    trash:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg>',
    back:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 7l-5 5 5 5M4 12h11a5 5 0 0 1 5 5"/></svg>',
    save:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l2 2v14H5z"/><path d="M8 4v5h8V4M8 15h8"/></svg>'
  };
  const confirm=popup?.querySelector('.swal2-confirm');
  const cancel=popup?.querySelector('.swal2-cancel');
  if(confirm&&!confirm.querySelector('svg'))confirm.insertAdjacentHTML('afterbegin',icons[confirmIcon]||icons.check);
  if(cancel&&!cancel.querySelector('svg'))cancel.insertAdjacentHTML('afterbegin',icons[cancelIcon]||icons.back);
};

(() => {
  const form=document.getElementById('finish-form');
  if(!form)return;
  const confirmation=form.querySelector('[data-install-confirmed]');
  let approved=false;
  form.addEventListener('submit',async event=> {
    if(approved)return;
    event.preventDefault();
    const modal=window.Swal;
    if(!modal?.fire) {
      if(window.showNotify)window.showNotify('No fue posible abrir la confirmación. Recarga la página e inténtalo nuevamente.','error',{title:'Confirmación requerida'});
      return;
    }
    const result=await modal.fire({
      icon:'warning',
      title:<?=json_encode($reinstallMode?'Confirmar reinstalación':'Confirmar instalación de IZZY',JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>,
      html:<?=json_encode($reinstallMode?'<div class="izzy-final-confirm-copy">IZZY actualizará únicamente las tablas del CMS, conservará la base general y volverá a generar la configuración protegida.<br><strong>Esta acción se ejecutará al confirmar.</strong></div>':'<div class="izzy-final-confirm-copy">IZZY creará la base de datos si hace falta, instalará el esquema, guardará la cuenta administradora y aplicará la configuración seleccionada.<br><strong>Al finalizar se activará el bloqueo de instalación.</strong></div>',JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>,
      cancelButtonText:'Revisar datos',
      confirmButtonText:<?=json_encode($reinstallMode?'Sí, reinstalar':'Sí, instalar',JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>,
      showCancelButton:true,
      allowOutsideClick:false,
      allowEscapeKey:true,
      customClass:{popup:'izzy-swal-formal',confirmButton:'izzy-swal-action',cancelButton:'izzy-swal-action'},
      didOpen:(popup)=>izzySwalActionIcons(popup,'check','back')
    });
    if(!result.isConfirmed)return;
    approved=true;
    if(confirmation)confirmation.value='1';
    const submitter=document.querySelector('[form="finish-form"]');
    if(submitter) {
      submitter.disabled=true;
      submitter.classList.add('loading');
      submitter.innerHTML='<span class="spinner"></span><span>Instalando…</span>';
    }
    form.requestSubmit();
  });
})();

(() => {
  const DRAFT_KEY='izzy_installer_reload_draft_v1';
  const BYPASS_KEY='izzy_installer_reload_bypass_v1';
  const forms=[...document.querySelectorAll('.scroll form')].filter(form=>form.id!=='finish-form');
  const body=document.body;
  const currentStep=Number(body?.dataset.installerStep||1);
  const hasServerState=body?.dataset.installerHasState==='1';

  const serialize=()=>{
    const payload={step:currentStep,fields:{},savedAt:Date.now()};
    forms.forEach(form=>{
      form.querySelectorAll('input,select,textarea').forEach(el=>{
        if(!el.name||['csrf','action','current_step','confirm_install','ajax'].includes(el.name))return;
        if((el.type==='checkbox'||el.type==='radio')&&!el.checked)return;
        payload.fields[el.name]=el.value;
      });
    });
    return payload;
  };

  const hasMeaningfulDraft=(draft)=>{
    if(hasServerState||currentStep>1)return true;
    if(!draft?.fields)return false;
    const defaults={host:'localhost',port:'3306',dbname:'izzy_web',username:'',password:''};
    return Object.entries(draft.fields).some(([name,value])=>{
      if(name in defaults)return String(value??'')!==defaults[name];
      return String(value??'').trim()!=='';
    });
  };

  const saveDraft=()=>{
    try{sessionStorage.setItem(DRAFT_KEY,JSON.stringify(serialize()));}catch(error){}
  };
  const clearDraft=()=>{
    try{sessionStorage.removeItem(DRAFT_KEY);}catch(error){}
  };
  const restoreDraft=(draft)=>{
    if(!draft?.fields||Number(draft.step)!==currentStep)return;
    Object.entries(draft.fields).forEach(([name,value])=>{
      document.querySelectorAll(`[name="${CSS.escape(name)}"]`).forEach(el=>{
        if(el.type==='radio'||el.type==='checkbox')el.checked=String(el.value)===String(value);
        else el.value=value??'';
        el.dispatchEvent(new Event('change',{bubbles:true}));
      });
    });
  };

  forms.forEach(form=>form.addEventListener('input',saveDraft,{passive:true}));
  forms.forEach(form=>form.addEventListener('change',saveDraft,{passive:true}));
  saveDraft();

  const askReload=async()=>{
    let draft=null;
    try{draft=JSON.parse(sessionStorage.getItem(DRAFT_KEY)||'null');}catch(error){}
    if(!hasMeaningfulDraft(draft)){
      sessionStorage.setItem(BYPASS_KEY,'1');
      location.reload();
      return;
    }
    const result=await Swal.fire({
      icon:'warning',
      title:'¿Recargar la página?',
      html:'<div class="izzy-reload-copy">Hay información escrita o pasos ya completados.<br><strong>Elige si deseas conservarla o iniciar nuevamente.</strong></div>',
      confirmButtonText:'Recargar y perder datos',
      cancelButtonText:'Conservar datos',
      showCancelButton:true,
      reverseButtons:false,
      allowOutsideClick:false,
      allowEscapeKey:true,
      focusCancel:true,
      customClass:{popup:'izzy-swal-formal',confirmButton:'izzy-swal-action',cancelButton:'izzy-swal-action'},
      didOpen:(popup)=>izzySwalActionIcons(popup,'trash','save')
    });
    if(result.isConfirmed){
      clearDraft();
      sessionStorage.setItem(BYPASS_KEY,'1');
      location.href='index.php?reset_installer=1';
    }else{
      restoreDraft(draft);
      window.showNotify?.('Los datos se conservaron. Puedes continuar donde estabas.','info',{title:'Datos conservados'});
    }
  };

  window.addEventListener('keydown',event=>{
    const reloadKey=event.key==='F5'||((event.ctrlKey||event.metaKey)&&event.key.toLowerCase()==='r');
    if(!reloadKey)return;
    event.preventDefault();
    askReload();
  },true);

  try{
    const nav=performance.getEntriesByType?.('navigation')?.[0];
    const bypass=sessionStorage.getItem(BYPASS_KEY)==='1';
    if(bypass)sessionStorage.removeItem(BYPASS_KEY);
    if(nav?.type==='reload'&&!bypass){
      let draft=null;
      try{draft=JSON.parse(sessionStorage.getItem(DRAFT_KEY)||'null');}catch(error){}
      if(hasMeaningfulDraft(draft)){
        setTimeout(async()=>{
          const result=await Swal.fire({
            icon:'warning',
            title:'¿Conservar los datos anteriores?',
            html:'<div class="izzy-reload-copy">La página fue recargada y hay información pendiente del asistente.<br><strong>Puedes conservarla o comenzar nuevamente.</strong></div>',
            confirmButtonText:'Perder datos y reiniciar',
            cancelButtonText:'Conservar datos',
            showCancelButton:true,
            allowOutsideClick:false,
            allowEscapeKey:true,
            focusCancel:true,
            customClass:{popup:'izzy-swal-formal',confirmButton:'izzy-swal-action',cancelButton:'izzy-swal-action'},
            didOpen:(popup)=>izzySwalActionIcons(popup,'trash','save')
          });
          if(result.isConfirmed){
            clearDraft();
            sessionStorage.setItem(BYPASS_KEY,'1');
            location.href='index.php?reset_installer=1';
          }else{
            restoreDraft(draft);
            window.showNotify?.('Los datos continúan intactos.','info',{title:'Datos conservados'});
          }
        },80);
      }
    }
  }catch(error){}

  document.getElementById('finish-form')?.addEventListener('submit',()=>clearDraft(),{capture:true});
})();

(() => {
  // Las respuestas del servidor se muestran con showNotify; el bloque inline queda solo como fallback sin JavaScript.
  window.IZZYUI?.bridgeMessages?.(document);
})();

</script>
<script src="../assets/action-icons.js"></script>
</body>
</html>

