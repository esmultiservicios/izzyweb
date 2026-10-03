<?php
declare(strict_types=1);

require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/emailTemplates.php';

class EmailService
{
    public function configByType(int $type): ?array
    {
        $st=db()->prepare('SELECT * FROM correo WHERE correo_tipo_id=? AND estado=1 ORDER BY correo_id DESC LIMIT 1');
        $st->execute([$type]);
        return $st->fetch()?:null;
    }

    public function configById(int $id): ?array
    {
        $st=db()->prepare('SELECT * FROM correo WHERE correo_id=? LIMIT 1');
        $st->execute([$id]);
        return $st->fetch()?:null;
    }

    public static function senderAddress(array $config): string
    {
        $method=strtoupper(trim((string)($config['metodo_envio']??'SMTP')));
        return trim((string)($method==='GRAPH'?($config['graph_user']??''):($config['correo']??'')));
    }

    public static function effectiveInternalDestination(array $config): string
    {
        $destination=trim((string)($config['destinatario']??''));
        return filter_var($destination,FILTER_VALIDATE_EMAIL)?$destination:self::senderAddress($config);
    }

    public static function normalizeAddressList(array|string|null $value): array
    {
        $raw=is_array($value)?implode(',',array_map('strval',$value)):(string)$value;
        if(trim($raw)==='')return [];

        $addresses=[];
        foreach(preg_split('/[;,]+/',$raw)?:[] as $candidate) {
            $candidate=trim($candidate);
            if($candidate==='')continue;
            if(!filter_var($candidate,FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Optional copy / CC contains an invalid email address: '.$candidate);
            }
            $addresses[strtolower($candidate)]=$candidate;
        }
        return array_values($addresses);
    }

    public function configurationProblems(array $config): array
    {
        $method=strtoupper(trim((string)($config['metodo_envio']??'')));
        $problems=[];

        if(!in_array($method,['SMTP','GRAPH'],true)) {
            return ['Select a supported email method.'];
        }

        if($method==='SMTP') {
            if(trim((string)($config['server']??''))==='')$problems[]='SMTP server is required.';
            if(!filter_var(trim((string)($config['correo']??'')),FILTER_VALIDATE_EMAIL))$problems[]='Enter a valid SMTP user / sender email.';
            $port=(int)($config['port']??0);
            if($port<1||$port>65535)$problems[]='Enter a valid SMTP port.';
            if(!in_array(strtolower(trim((string)($config['smtp_secure']??''))),['tls','ssl'],true))$problems[]='Select TLS or SSL security.';
            if(!function_exists('openssl_decrypt'))$problems[]='OpenSSL is required to read the encrypted SMTP password. Review Website Health.';
            elseif(secret_decrypt($config['password']??'')==='')$problems[]='SMTP password or app password is required.';
        } else {
            if(trim((string)($config['tenant_id']??''))==='')$problems[]='Tenant ID is required.';
            if(trim((string)($config['client_id']??''))==='')$problems[]='Client ID is required.';
            if(!function_exists('openssl_decrypt'))$problems[]='OpenSSL is required to read the encrypted Graph Client Secret. Review Website Health.';
            elseif(secret_decrypt($config['client_secret']??'')==='')$problems[]='Client Secret VALUE is required.';
            if(!filter_var(trim((string)($config['graph_user']??'')),FILTER_VALIDATE_EMAIL))$problems[]='Enter a valid Graph User / mailbox.';
        }

        $destination=trim((string)($config['destinatario']??''));
        if($destination!==''&&!filter_var($destination,FILTER_VALIDATE_EMAIL))$problems[]='Internal destination must be a valid email address or remain empty.';

        try {
            self::normalizeAddressList($config['copia']??'');
        } catch(RuntimeException $e) {
            $problems[]=$e->getMessage();
        }

        return $problems;
    }

    public function sendByType(
        int $type,
        string $to,
        string $subject,
        string $html,
        string $replyTo='',
        array|string $cc=[],
        array $options=[]
    ): array {
        $config=$this->configByType($type);
        if(!$config)return ['success'=>false,'message'=>'No active email configuration is available for this purpose.'];
        return $this->send($config,$to,$subject,$html,$replyTo,$cc,$options);
    }

    public function sendWithFallback(
        array $types,
        string $to,
        string $subject,
        string $html,
        string $replyTo='',
        array|string $cc=[],
        array $options=[]
    ): array {
        $last=null;
        foreach($types as $type) {
            $config=$this->configByType((int)$type);
            if(!$config)continue;
            $result=$this->send($config,$to,$subject,$html,$replyTo,$cc,$options);
            if($result['success'])return $result;
            $last=$result;
        }
        return $last??['success'=>false,'message'=>'No active email configuration is available.'];
    }

    public function test(int $id,string $to=''): array
    {
        $config=$this->configById($id);
        if(!$config)return ['success'=>false,'message'=>'Email configuration not found.'];
        $destination=trim($to)!==''?trim($to):self::effectiveInternalDestination($config);
        return $this->send(
            $config,
            $destination,
            'CMS email test',
            EmailTemplates::test((string)$config['metodo_envio'],settings())
        );
    }

    public function send(
        array $config,
        string $to,
        string $subject,
        string $html,
        string $replyTo='',
        array|string $cc=[],
        array $options=[]
    ): array {
        $problems=$this->configurationProblems($config);
        if($problems)return ['success'=>false,'message'=>'Email configuration is incomplete. '.implode(' ',$problems)];

        $destination=trim($to)!==''?trim($to):self::effectiveInternalDestination($config);
        if(!filter_var($destination,FILTER_VALIDATE_EMAIL)) {
            return ['success'=>false,'message'=>'No valid destination email could be resolved for this configuration.'];
        }

        if($replyTo!==''&&!filter_var($replyTo,FILTER_VALIDATE_EMAIL)) {
            return ['success'=>false,'message'=>'Reply-To must be a valid email address or remain empty.'];
        }

        try {
            $copies=array_merge(
                self::normalizeAddressList($config['copia']??''),
                self::normalizeAddressList($cc)
            );
            $blindCopies=self::normalizeAddressList($options['bcc']??[]);
            $attachments=$this->normalizeAttachments($options['attachments']??[]);
        } catch(RuntimeException $e) {
            return ['success'=>false,'message'=>$e->getMessage()];
        }

        $uniqueCopies=[];
        foreach($copies as $copy) {
            if(strcasecmp($copy,$destination)===0)continue;
            $uniqueCopies[strtolower($copy)]=$copy;
        }
        $copies=array_values($uniqueCopies);

        $uniqueBlindCopies=[];
        foreach($blindCopies as $copy) {
            if(strcasecmp($copy,$destination)===0||isset($uniqueCopies[strtolower($copy)]))continue;
            $uniqueBlindCopies[strtolower($copy)]=$copy;
        }
        $blindCopies=array_values($uniqueBlindCopies);

        return strtoupper((string)$config['metodo_envio'])==='GRAPH'
            ?$this->graph($config,$destination,$subject,$html,$replyTo,$copies,$blindCopies,$attachments)
            :$this->smtp($config,$destination,$subject,$html,$replyTo,$copies,$blindCopies,$attachments);
    }

    private static function uuidV4(): string
    {
        $bytes=random_bytes(16);
        $bytes[6]=chr((ord($bytes[6])&0x0f)|0x40);
        $bytes[8]=chr((ord($bytes[8])&0x3f)|0x80);
        $hex=bin2hex($bytes);
        return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20,12);
    }

    private function normalizeAttachments(mixed $value): array
    {
        if($value===null||$value==='')return [];
        if(!is_array($value))throw new RuntimeException('Email attachments must be provided as a list.');

        $attachments=[];
        foreach($value as $attachment) {
            if(!is_array($attachment))throw new RuntimeException('An email attachment is invalid.');
            $path=(string)($attachment['path']??'');
            $name=trim(preg_replace('/[\r\n\x00-\x1F\x7F]+/u',' ',(string)($attachment['name']??''))??'');
            $mime=strtolower(trim((string)($attachment['mime']??'')));
            if($path===''||!is_file($path)||!is_readable($path))throw new RuntimeException('An email attachment could not be read.');
            if($name===''||$name==='.'||$name==='..')throw new RuntimeException('An email attachment has an invalid name.');
            if(!preg_match('~^[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+-]*$~i',$mime))throw new RuntimeException('An email attachment has an invalid MIME type.');
            $attachments[]=['path'=>$path,'name'=>substr($name,0,255),'mime'=>$mime];
        }
        return $attachments;
    }

    private function graph(
        array $config,
        string $to,
        string $subject,
        string $html,
        string $replyTo,
        array $cc,
        array $bcc,
        array $attachments
    ): array {
        if(!function_exists('curl_init'))return ['success'=>false,'message'=>'cURL is not enabled on this server. Review Website Health.'];

        $tenant=trim((string)$config['tenant_id']);
        $client=trim((string)$config['client_id']);
        $secret=secret_decrypt($config['client_secret']??'');
        $from=self::senderAddress($config);

        $ch=curl_init('https://login.microsoftonline.com/'.rawurlencode($tenant).'/oauth2/v2.0/token');
        curl_setopt_array($ch,[
            CURLOPT_POST=>true,
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_TIMEOUT=>30,
            CURLOPT_POSTFIELDS=>http_build_query([
                'client_id'=>$client,
                'scope'=>'https://graph.microsoft.com/.default',
                'client_secret'=>$secret,
                'grant_type'=>'client_credentials',
            ]),
            CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $raw=curl_exec($ch);
        $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json=json_decode((string)$raw,true);
        if($code<200||$code>=300||empty($json['access_token'])) {
            return ['success'=>false,'message'=>'Microsoft Graph authentication failed. Review Tenant ID, Client ID and Client Secret VALUE.'];
        }

        $message=[
            'subject'=>$subject,
            'body'=>['contentType'=>'HTML','content'=>$html],
            'toRecipients'=>[['emailAddress'=>['address'=>$to]]],
        ];
        if($cc) {
            $message['ccRecipients']=array_map(
                static fn(string $address):array=>['emailAddress'=>['address'=>$address]],
                $cc
            );
        }
        if($bcc) {
            $message['bccRecipients']=array_map(
                static fn(string $address):array=>['emailAddress'=>['address'=>$address]],
                $bcc
            );
        }
        if($replyTo!=='')$message['replyTo']=[['emailAddress'=>['address'=>$replyTo]]];
        if($attachments) {
            $message['attachments']=[];
            foreach($attachments as $attachment) {
                $bytes=@file_get_contents($attachment['path']);
                if($bytes===false)return ['success'=>false,'message'=>'An attachment could not be read before sending.'];
                $message['attachments'][]=[
                    '@odata.type'=>'#microsoft.graph.fileAttachment',
                    'name'=>$attachment['name'],
                    'contentType'=>$attachment['mime'],
                    'contentBytes'=>base64_encode($bytes),
                ];
            }
        }

        $payload=[
            'message'=>$message,
            'saveToSentItems'=>(int)($config['save_to_sent_items']??1)===1,
        ];
        $requestId=self::uuidV4();
        $responseHeaders=[];
        $ch=curl_init('https://graph.microsoft.com/v1.0/users/'.rawurlencode($from).'/sendMail');
        curl_setopt_array($ch,[
            CURLOPT_POST=>true,
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_TIMEOUT=>45,
            CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER=>[
                'Authorization: Bearer '.$json['access_token'],
                'Content-Type: application/json',
                'client-request-id: '.$requestId,
                'return-client-request-id: true',
            ],
            CURLOPT_HEADERFUNCTION=>static function($curl,string $header) use (&$responseHeaders): int {
                $length=strlen($header);
                $parts=explode(':',$header,2);
                if(count($parts)===2)$responseHeaders[strtolower(trim($parts[0]))]=trim($parts[1]);
                return $length;
            },
        ]);
        $rawSend=curl_exec($ch);
        $curlError=curl_error($ch);
        $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);

        if($rawSend===false||$curlError!=='') {
            return [
                'success'=>false,
                'message'=>'No fue posible completar la solicitud de envío con Microsoft Graph. '.$curlError,
                'to'=>$to,
                'from'=>$from,
                'http_code'=>$code,
                'request_id'=>$responseHeaders['request-id']??$responseHeaders['client-request-id']??$requestId,
            ];
        }

        if($code!==202) {
            $errorMessage='Microsoft Graph no aceptó el mensaje. Revisa los permisos del buzón y el Graph User seleccionado.';
            $sendJson=json_decode((string)$rawSend,true);
            if(is_array($sendJson)&&isset($sendJson['error']['message'])&&is_string($sendJson['error']['message'])) {
                $detail=trim($sendJson['error']['message']);
                if($detail!=='')$errorMessage.=' Detalle: '.$detail;
            }
            return [
                'success'=>false,
                'message'=>$errorMessage,
                'to'=>$to,
                'from'=>$from,
                'http_code'=>$code,
                'request_id'=>$responseHeaders['request-id']??$responseHeaders['client-request-id']??$requestId,
            ];
        }

        $verifiedSentItem=false;
        $verificationAvailable=false;
        if((int)($config['save_to_sent_items']??1)===1) {
            $query=http_build_query([
                '$top'=>10,
                '$select'=>'id,subject,toRecipients,sentDateTime',
                '$orderby'=>'sentDateTime desc',
            ],'', '&', PHP_QUERY_RFC3986);
            $verifyUrl='https://graph.microsoft.com/v1.0/users/'.rawurlencode($from).'/mailFolders/sentitems/messages?'.$query;
            $verify=curl_init($verifyUrl);
            curl_setopt_array($verify,[
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_TIMEOUT=>20,
                CURLOPT_HTTPHEADER=>[
                    'Authorization: Bearer '.$json['access_token'],
                    'Accept: application/json',
                ],
            ]);
            $verifyRaw=curl_exec($verify);
            $verifyCode=(int)curl_getinfo($verify,CURLINFO_HTTP_CODE);
            curl_close($verify);
            if($verifyCode>=200&&$verifyCode<300&&is_string($verifyRaw)) {
                $verificationAvailable=true;
                $verifyJson=json_decode($verifyRaw,true);
                foreach((array)($verifyJson['value']??[]) as $sentMessage) {
                    if(!is_array($sentMessage)||strcasecmp((string)($sentMessage['subject']??''),$subject)!==0)continue;
                    foreach((array)($sentMessage['toRecipients']??[]) as $recipient) {
                        $address=(string)($recipient['emailAddress']['address']??'');
                        if($address!==''&&strcasecmp($address,$to)===0) {
                            $verifiedSentItem=true;
                            break 2;
                        }
                    }
                }
            }
        }

        $message=$verifiedSentItem
            ?'Microsoft Graph aceptó el correo y se verificó en Elementos enviados. Destino: '.$to.'.'
            :'Microsoft Graph aceptó el correo para envío. Destino: '.$to.'.';
        if(!$verifiedSentItem&&$verificationAvailable) {
            $message.=' El elemento enviado todavía no apareció en la consulta inmediata; Microsoft 365 puede tardar unos segundos en procesarlo.';
        }

        return [
            'success'=>true,
            'message'=>$message,
            'to'=>$to,
            'from'=>$from,
            'http_code'=>$code,
            'request_id'=>$responseHeaders['request-id']??$responseHeaders['client-request-id']??$requestId,
            'verified_sent_item'=>$verifiedSentItem,
            'verification_available'=>$verificationAvailable,
        ];
    }

    private function smtp(
        array $config,
        string $to,
        string $subject,
        string $html,
        string $replyTo,
        array $cc,
        array $bcc,
        array $attachments
    ): array {
        $host=trim((string)$config['server']);
        $port=(int)$config['port'];
        $secure=strtolower(trim((string)$config['smtp_secure']));
        $user=self::senderAddress($config);
        $password=secret_decrypt($config['password']??'');
        try { $emailSettings=settings(); } catch(Throwable $ignored) { $emailSettings=['admin_brand_name'=>'IZZY']; }
        $configuredName=trim((string)($emailSettings['admin_brand_name']??'IZZY'));
        $configuredName=preg_replace('/[\r\n]+/',' ',$configuredName)?:'Website';
        $senderName='=?UTF-8?B?'.base64_encode($configuredName).'?=';
        $helo=preg_replace('/[^a-z0-9.-]/i','',(string)($_SERVER['SERVER_NAME']??'localhost'))?:'localhost';
        $target=($secure==='ssl'?'ssl://':'tcp://').$host.':'.$port;
        $socket=@stream_socket_client($target,$errorNumber,$errorMessage,15,STREAM_CLIENT_CONNECT);
        if(!$socket)return ['success'=>false,'message'=>'Could not connect to the SMTP server. Review server, port and security settings.'];

        stream_set_timeout($socket,20);
        try {
            $this->expect($socket,[220]);
            $this->cmd($socket,'EHLO '.$helo,[250]);
            if($secure==='tls') {
                $this->cmd($socket,'STARTTLS',[220]);
                if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('SMTP TLS could not be enabled.');
                }
                $this->cmd($socket,'EHLO '.$helo,[250]);
            }
            $this->cmd($socket,'AUTH LOGIN',[334]);
            $this->cmd($socket,base64_encode($user),[334]);
            $this->cmd($socket,base64_encode($password),[235]);
            $this->cmd($socket,'MAIL FROM:<'.$user.'>',[250]);
            $this->cmd($socket,'RCPT TO:<'.$to.'>',[250,251]);
            foreach($cc as $copy)$this->cmd($socket,'RCPT TO:<'.$copy.'>',[250,251]);
            foreach($bcc as $copy)$this->cmd($socket,'RCPT TO:<'.$copy.'>',[250,251]);
            $this->cmd($socket,'DATA',[354]);

            $headers=[
                'From: '.$senderName.' <'.$user.'>',
                'To: <'.$to.'>',
                'Subject: =?UTF-8?B?'.base64_encode($subject).'?=',
                'MIME-Version: 1.0',
            ];
            if($cc)$headers[]='Cc: '.implode(', ',$cc);
            if($replyTo!=='')$headers[]='Reply-To: <'.$replyTo.'>';

            if($attachments) {
                $boundary='=_cms_'.bin2hex(random_bytes(18));
                $headers[]='Content-Type: multipart/mixed; boundary="'.$boundary.'"';
                $parts=[];
                $parts[]='--'.$boundary."\r\n"
                    .'Content-Type: text/html; charset=UTF-8'."\r\n"
                    .'Content-Transfer-Encoding: base64'."\r\n\r\n"
                    .chunk_split(base64_encode($html));
                foreach($attachments as $attachment) {
                    $bytes=@file_get_contents($attachment['path']);
                    if($bytes===false)throw new RuntimeException('SMTP attachment could not be read.');
                    $fallback=preg_replace('/[^a-z0-9._-]+/i','_',basename($attachment['name']))?:'attachment';
                    $encoded=rawurlencode($attachment['name']);
                    $parts[]='--'.$boundary."\r\n"
                        .'Content-Type: '.$attachment['mime'].'; name="'.$fallback.'"'."\r\n"
                        .'Content-Disposition: attachment; filename="'.$fallback.'"; filename*=UTF-8\'\''.$encoded."\r\n"
                        .'Content-Transfer-Encoding: base64'."\r\n\r\n"
                        .chunk_split(base64_encode($bytes));
                }
                $body=implode("\r\n",$headers)."\r\n\r\n".implode("\r\n",$parts).'--'.$boundary."--\r\n.";
            } else {
                $headers[]='Content-Type: text/html; charset=UTF-8';
                $headers[]='Content-Transfer-Encoding: base64';
                $body=implode("\r\n",$headers)."\r\n\r\n".chunk_split(base64_encode($html))."\r\n.";
            }
            fwrite($socket,$body."\r\n");
            $this->expect($socket,[250]);
            $this->cmd($socket,'QUIT',[221]);
            fclose($socket);
            return ['success'=>true,'message'=>'Email sent with SMTP.'];
        } catch(Throwable $e) {
            @fwrite($socket,"QUIT\r\n");
            @fclose($socket);
            return ['success'=>false,'message'=>'SMTP authentication or delivery failed. Review the saved credentials and server settings.'];
        }
    }

    private function cmd($socket,string $command,array $codes): void
    {
        fwrite($socket,$command."\r\n");
        $this->expect($socket,$codes);
    }

    private function expect($socket,array $codes): void
    {
        $response='';
        while(($line=fgets($socket,515))!==false) {
            $response.=$line;
            if(strlen($line)<4||$line[3]!=='-')break;
        }
        $code=(int)substr($response,0,3);
        if(!in_array($code,$codes,true))throw new RuntimeException('SMTP command failed.');
    }
}
