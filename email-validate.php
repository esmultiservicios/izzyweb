<?php
declare(strict_types=1);

require __DIR__.'/config/bootstrap.php';

if(session_status()!==PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'path'=>'/',
        'secure'=>public_request_is_https(),
        'httponly'=>true,
        'samesite'=>'Lax',
    ]);
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    if($_SERVER['REQUEST_METHOD']!=='POST') {
        http_response_code(405);
        echo json_encode(['ok'=>false,'status'=>'method','message'=>'Invalid request.']);
        exit;
    }

    $now=time();
    $checks=is_array($_SESSION['izzy_email_validation_checks']??null)
        ?array_map('intval',$_SESSION['izzy_email_validation_checks'])
        :[];
    $checks=array_values(array_filter($checks,static fn(int $timestamp):bool=>$timestamp>$now-600&&$timestamp<=$now));
    if(count($checks)>=30) {
        http_response_code(429);
        echo json_encode([
            'ok'=>false,
            'status'=>'rate_limit',
            'message'=>'Espera un momento antes de validar otro correo.',
            'suggestion'=>null,
        ]);
        exit;
    }
    $checks[]=$now;
    $_SESSION['izzy_email_validation_checks']=$checks;

    $email=trim((string)($_POST['email']??''));
    $set=settings();
    $result=EmailValidation::validate($email,$set,true);

    echo json_encode([
        'ok'=>(bool)($result['ok']??false),
        'status'=>(string)($result['status']??'invalid'),
        'message'=>(string)($result['message']??''),
        'email'=>(string)($result['email']??$email),
        'suggestion'=>$result['suggestion']??null,
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e) {
    error_log('[IZZY Email Validation Endpoint] '.$e::class.': '.$e->getMessage());
    http_response_code(200);
    echo json_encode([
        'ok'=>true,
        'status'=>'unavailable',
        'message'=>'Correo válido',
        'suggestion'=>null,
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
