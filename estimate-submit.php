<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
require_once __DIR__.'/core/EmailService.php';
if(session_status()!==PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['path'=>'/','secure'=>public_request_is_https(),'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
try {
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('Invalid request.');
    $set=settings();
    $language=in_array(($set['site_language']??'en'),['en','es'],true)?$set['site_language']:'en';
    $labels=$language==='es'?[ 
        'email'=>'Escribe un correo electrónico válido.',
        'name'=>'Escribe un nombre válido.',
        'phone'=>'Escribe un número de teléfono válido.',
        'service'=>'Selecciona un tipo de solicitud o servicio válido.',
        'referral'=>'Selecciona una opción válida para indicar cómo nos encontraste.',
        'other'=>'Explica brevemente cómo nos encontraste.',
        'message'=>'El mensaje necesita más información útil.',
        'length'=>'Uno de los campos supera la longitud permitida.',
        'date'=>'Selecciona una fecha válida.',
        'cooldown'=>'Espera un momento antes de enviar otra solicitud.',
        'hourly'=>'Has enviado varias solicitudes. Intenta nuevamente más tarde.',
        'verification'=>'No pudimos verificar el envío. Actualiza la página e intenta nuevamente.',
        'verification_setup'=>'La verificación del formulario no está disponible temporalmente.',
        'success'=>'Gracias. Hemos recibido tu solicitud.',
        'save_error'=>'No pudimos guardar tu solicitud en este momento. Intenta nuevamente en unos minutos.',
        'process_error'=>'No pudimos procesar tu solicitud en este momento. Intenta nuevamente en unos minutos.',
    ]:[
        'email'=>'Enter a valid email address.',
        'name'=>'Enter a valid name.',
        'phone'=>'Enter a valid phone number.',
        'service'=>'Select a valid request type or service.',
        'referral'=>'Select a valid option for how you found us.',
        'other'=>'Briefly explain how you found us.',
        'message'=>'The message needs more meaningful information.',
        'length'=>'One of the fields exceeds the allowed length.',
        'date'=>'Select a valid date.',
        'cooldown'=>'Please wait a moment before sending another request.',
        'hourly'=>'Several requests have been submitted. Please try again later.',
        'verification'=>'We could not verify the submission. Refresh the page and try again.',
        'verification_setup'=>'Form verification is temporarily unavailable.',
        'success'=>'Thank you. Your request has been received.',
        'save_error'=>'We could not save your request right now. Please try again shortly.',
        'process_error'=>'We could not process your request right now. Please try again shortly.',
    ];
    $antiSpam=public_form_antispam_config($set);
    if($antiSpam['enabled']) {
        if(trim((string)($_POST['website_url']??''))!=='') {
            echo json_encode(['ok'=>true,'message'=>$labels['success']]);
            exit;
        }
        $guardToken=trim((string)($_POST['form_guard_token']??''));
        if(!public_form_token_is_old_enough($guardToken,(int)$antiSpam['minimum_seconds'])) {
            echo json_encode(['ok'=>true,'message'=>$labels['success']]);
            exit;
        }
        $rateStatus=public_form_rate_status($antiSpam);
        $ipRateStatus=public_form_ip_rate_status($antiSpam);
        if($rateStatus==='cooldown'||$ipRateStatus==='cooldown') {
            http_response_code(429);
            echo json_encode(['ok'=>false,'message'=>$labels['cooldown']]);
            exit;
        }
        if($rateStatus==='hourly'||$ipRateStatus==='hourly') {
            http_response_code(429);
            echo json_encode(['ok'=>false,'message'=>$labels['hourly']]);
            exit;
        }
        if(public_form_is_obvious_automation([
            substr((string)($_POST['name']??''),0,300),
            substr((string)($_POST['email']??''),0,300),
            substr((string)($_POST['service']??''),0,300),
            substr((string)($_POST['message']??''),0,10000),
        ])) {
            echo json_encode(['ok'=>true,'message'=>$labels['success']]);
            exit;
        }
        if($antiSpam['block_sales']&&public_form_is_obvious_sales_solicitation([
            substr((string)($_POST['name']??''),0,300),
            substr((string)($_POST['service']??''),0,300),
            substr((string)($_POST['referral']??''),0,300),
            substr((string)($_POST['referral_other']??''),0,600),
            substr((string)($_POST['message']??''),0,10000),
        ])) {
            echo json_encode(['ok'=>true,'message'=>$labels['success']]);
            exit;
        }
    }
    if($antiSpam['turnstile_enabled']) {
        if($antiSpam['turnstile_site_key']===''||!$antiSpam['turnstile_secret_stored']) {
            error_log('[CMS Turnstile] Enabled with incomplete key configuration.');
            http_response_code(503);
            echo json_encode(['ok'=>false,'message'=>$labels['verification_setup']]);
            exit;
        }
        $turnstile=verify_public_turnstile(trim((string)($_POST['cf-turnstile-response']??'')),$set);
        if(!$turnstile['ok']) {
            http_response_code(422);
            echo json_encode(['ok'=>false,'message'=>$labels['verification']]);
            exit;
        }
    }
    $name=trim((string)($_POST['name']??''));
    $phone=trim((string)($_POST['phone']??''));
    $email=trim((string)($_POST['email']??''));
    $address=trim((string)($_POST['address']??''));
    $service=trim((string)($_POST['service']??''));
    $date=trim((string)($_POST['date']??''));
    $messageRaw=trim((string)($_POST['message']??''));
    $message=preg_replace('~<(script|style|iframe|object|embed|svg|math)\b[^>]*>.*?</\\1\s*>~is','',$messageRaw)??'';
    $message=preg_replace('/<!--.*?-->/s','',$message)??'';
    $message=strip_tags($message,'<p><br><strong><b><em><i><ul><ol><li>');
    $message=trim(preg_replace_callback(
        '~<\s*(/?)\s*(p|br|strong|b|em|i|ul|ol|li)\b[^>]*>~i',
        static function(array $m):string {
            $tag=strtolower($m[2]);
            return $m[1]==='/'&&$tag==='br'?'':'<'.$m[1].$tag.'>';
        },$message
    )??'');
    $referral=trim((string)($_POST['referral']??''));
    $referralOther=trim((string)($_POST['referral_other']??''));
    $textLength=static function(string $value):int {
        $count=preg_match_all('/./us',$value,$matches);
        return $count===false?strlen($value):$count;
    };
    foreach([
        [$name,150],[$phone,80],[$email,180],[$address,255],[$service,150],
        [$date,10],[$referral,120],[$referralOther,255],[$message,5000],
    ] as [$value,$maximum]) {
        if($textLength($value)>$maximum)throw new RuntimeException($labels['length']);
    }

    $emailValidation=EmailValidation::validate($email,$set,true);
    if(!$emailValidation['ok']) {
        http_response_code(422);
        echo json_encode([
            'ok'=>false,
            'message'=>$emailValidation['message']?:$labels['email'],
            'field'=>'email',
            'code'=>$emailValidation['status'],
            'suggestion'=>$emailValidation['suggestion'],
        ]);
        exit;
    }
    $email=(string)($emailValidation['email']??$email);
    $required=[
        'name'=>setting_enabled($set,'form_required_name',true),
        'phone'=>setting_enabled($set,'form_required_phone',false),
        'service'=>setting_enabled($set,'form_required_service',false),
        'referral'=>setting_enabled($set,'form_required_referral',true),
        'message'=>setting_enabled($set,'form_required_message',true),
    ];
    $nameMetrics=meaningful_text_metrics($name);
    if(($required['name']||$name!=='')&&$nameMetrics['characters']<2)throw new RuntimeException($labels['name']);
    $phoneDigits=preg_replace('/\D+/','',$phone);
    if(($required['phone']||$phone!=='')&&strlen($phoneDigits)<7)throw new RuntimeException($labels['phone']);

    $serviceRows=db()->query('SELECT title FROM services WHERE active=1')->fetchAll(PDO::FETCH_COLUMN);
    if(($required['service']&&$service==='')||($service!==''&&!in_array($service,$serviceRows,true)))throw new RuntimeException($labels['service']);

    $allowedReferrals=referral_options($set,$language);
    if(($required['referral']&&$referral==='')||($referral!==''&&!in_array($referral,$allowedReferrals,true)))throw new RuntimeException($labels['referral']);
    if(is_other_referral_option($referral)) {
        if(meaningful_text_metrics($referralOther)['characters']<2)throw new RuntimeException($labels['other']);
    } else {
        $referralOther='';
    }

    $minimumCharacters=max(10,min(1000,(int)($set['form_message_min_characters']??30)));
    $minimumWords=max(2,min(100,(int)($set['form_message_min_words']??5)));
    $messageMetrics=meaningful_text_metrics(html_entity_decode(strip_tags($message),ENT_QUOTES|ENT_HTML5,'UTF-8'));
    if($required['message']&&$message==='')throw new RuntimeException($labels['message']);
    if($message!==''&&($messageMetrics['characters']<$minimumCharacters||$messageMetrics['words']<$minimumWords))throw new RuntimeException($labels['message']);

    if($date!=='') {
        $parsedDate=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$parsedDate||$parsedDate->format('Y-m-d')!==$date)throw new RuntimeException($labels['date']);
    }
    $files=normalized_files('photos');
    if(count($files)>8)throw new RuntimeException('Please upload no more than 8 images.');
    $pdo=db();
    $pdo->beginTransaction();
    $st=$pdo->prepare('INSERT INTO estimate_requests(full_name,phone,email,address,service_needed,desired_date,message,lead_source,lead_source_detail,photo_path) VALUES(?,?,?,?,?,?,?,?,?,NULL)');
    $st->execute([$name?:null,$phone?:null,$email,$address?:null,$service?:null,$date!==''?$date:null,$message?:null,$referral?:null,$referralOther?:null]);
    $id=(int)$pdo->lastInsertId();
    $first=null;
    foreach($files as $f) {
        $path=upload_image($f,'estimates','estimate-'.$id,8);
        if($first===null)$first=$path;
        $ins=$pdo->prepare('INSERT INTO estimate_attachments(estimate_id,file_path,original_name,mime_type,file_size) VALUES(?,?,?,?,?)');
        $ins->execute([$id,$path,$f['name']??'',secure_file_mime_type(ROOT_DIR.'/'.$path),(int)($f['size']??0)]);
    }
    if($first)$pdo->prepare('UPDATE estimate_requests SET photo_path=? WHERE id=?')->execute([$first,$id]);
    $pdo->commit();
    if($antiSpam['enabled']) {
        record_public_form_submission();
        record_public_form_ip_submission();
    }
    admin_notify('info','New estimate request',($name!==''?$name:'A website visitor').' submitted a free estimate request.','estimates.php?view='.$id);
    log_activity('estimate_received','New website estimate request',['estimate_id'=>$id]);
    $request=['full_name'=>$name,
    'phone'=>$phone,
    'email'=>$email,
    'address'=>$address,
    'service_needed'=>$service,
    'desired_date'=>$date,
    'message'=>$message,
    'lead_source'=>$referral,
    'lead_source_detail'=>$referralOther];
    $emailFailures=[];
    try {
        $mailer=new EmailService();
        $adminResult=$mailer->sendWithFallback(
            [3,1],
            '',
            'New website estimate request',
            EmailTemplates::estimateAdmin($request,$set),
            filter_var($email,FILTER_VALIDATE_EMAIL)?$email:''
        );
        if(!$adminResult['success'])$emailFailures[]='internal notification';
    } catch(Throwable $mailError) {
        $emailFailures[]='email delivery';
    }
    if($emailFailures) {
        admin_notify(
            'warning',
            'Estimate saved; email needs attention',
            'Request #'.$id.' was stored successfully, but '.implode(' and ',array_unique($emailFailures)).' could not be sent. Review Email Configuration.',
            'estimates.php?view='.$id
        );
    }
    echo json_encode(['ok'=>true,'message'=>$labels['success'],'request_id'=>$id]);
} catch(Throwable $e) {
    if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    http_response_code(422);
    $publicMessage=$e instanceof PDOException
        ?($labels['save_error']??'We could not save your request right now. Please try again shortly.')
        :($e instanceof RuntimeException
            ?$e->getMessage()
            :($labels['process_error']??'We could not process your request right now. Please try again shortly.'));
    echo json_encode(['ok'=>false,'message'=>$publicMessage]);
}
