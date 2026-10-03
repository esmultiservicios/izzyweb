<?php
declare(strict_types=1);

class EmailTemplates
{
    private static function base(
        string $title,
        string $content,
        array $settings,
        string $eyebrow='SYSTEM NOTIFICATION',
        string $preheader=''
    ): string {
        $brandRaw=trim((string)($settings['admin_brand_name']??$settings['company_name']??'CMS Core'));
        if($brandRaw==='')$brandRaw='CMS Core';
        $brand=h($brandRaw);
        $safeTitle=h($title);
        $safeEyebrow=h($eyebrow);
        $preheader=trim($preheader)!==''?$preheader:$title;
        $safePreheader=h($preheader);
        $year=date('Y');

        $contactParts=[];
        foreach(['phone','email'] as $key) {
            $value=trim((string)($settings[$key]??''));
            if($value!=='')$contactParts[]=h($value);
        }
        $contactLine=implode(' &nbsp;•&nbsp; ',$contactParts);

        $website=trim((string)($settings['website']??''));
        $websiteHtml='';
        if(filter_var($website,FILTER_VALIDATE_URL)) {
            $websiteHtml='<a href="'.h($website).'" style="color:#155eaa;text-decoration:none;font-weight:700">'.h(preg_replace('~^https?://~i','',$website)??$website).'</a>';
        } elseif($website!=='') {
            $websiteHtml=h($website);
        }
        $footerMeta=implode('<br>',array_values(array_filter([$contactLine,$websiteHtml],static fn(string $value):bool=>$value!=='')));
        if($footerMeta!=='')$footerMeta='<div style="margin-top:7px">'.$footerMeta.'</div>';

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light only">
    <title>{$safeTitle}</title>
    <style>
        body,table,td,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
        table,td{mso-table-lspace:0pt;mso-table-rspace:0pt}
        table{border-collapse:collapse!important}
        img{border:0;height:auto;line-height:100%;outline:none;text-decoration:none}
        @media only screen and (max-width:640px){
            .email-shell{width:100%!important}
            .email-pad{padding-left:20px!important;padding-right:20px!important}
            .email-title{font-size:26px!important;line-height:1.18!important}
            .email-body{font-size:15px!important}
            .email-button{display:block!important;width:100%!important;box-sizing:border-box!important;text-align:center!important}
        }
    </style>
<link rel="stylesheet" href="../assets/action-icons.css">
</head>
<body style="margin:0;padding:0;background:#eef3f8;font-family:Arial,Helvetica,sans-serif;color:#1c2b3a">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent">{$safePreheader}</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background:#eef3f8">
        <tr>
            <td align="center" style="padding:28px 12px">
                <table role="presentation" class="email-shell" width="640" cellpadding="0" cellspacing="0" border="0" style="width:640px;max-width:640px;background:#ffffff;border:1px solid #d7e1ea;border-radius:20px;overflow:hidden;box-shadow:0 16px 42px rgba(16,42,67,.10)">
                    <tr>
                        <td class="email-pad" style="padding:25px 30px 20px;background:#102a43;border-bottom:5px solid #2c7fc3;color:#ffffff">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td valign="middle" style="font-size:15px;line-height:1.3;font-weight:800;letter-spacing:.02em;color:#ffffff">{$brand}</td>
                                    <td valign="middle" align="right" style="font-size:11px;line-height:1.3;font-weight:700;letter-spacing:.12em;color:#b9d0e2">CMS MESSAGE</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="email-pad" style="padding:31px 30px 10px;background:#ffffff">
                            <div style="font-size:11px;line-height:1.4;font-weight:800;letter-spacing:.14em;color:#155eaa;text-transform:uppercase">{$safeEyebrow}</div>
                            <h1 class="email-title" style="margin:8px 0 0;font-size:31px;line-height:1.18;color:#102a43;font-weight:800">{$safeTitle}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td class="email-pad email-body" style="padding:17px 30px 34px;background:#ffffff;font-size:16px;line-height:1.68;color:#33485b;overflow-wrap:anywhere">
                            {$content}
                        </td>
                    </tr>
                    <tr>
                        <td class="email-pad" style="padding:21px 30px;background:#f7f9fc;border-top:1px solid #dce5ed;font-size:13px;line-height:1.55;color:#607080">
                            <strong style="display:block;color:#102a43;font-size:14px">{$brand}</strong>
                            {$footerMeta}
                            <div style="margin-top:12px;color:#7b8996">© {$year} {$brand}. Message generated securely by the CMS.</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
<script src="../assets/action-icons.js"></script>
</body>
</html>
HTML;
    }

    private static function detailRows(array $rows): string
    {
        $html='<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border:1px solid #dbe4ec;border-radius:14px;overflow:hidden;background:#f8fafc">';
        $index=0;
        foreach($rows as $label=>$value) {
            $value=trim((string)$value);
            if($value==='')$value='Not provided';
            $border=$index>0?'border-top:1px solid #e2e9ef;':'';
            $html.='<tr>'
                .'<td valign="top" style="'.$border.'width:34%;padding:12px 14px;color:#607080;font-size:13px;font-weight:700">'.h((string)$label).'</td>'
                .'<td valign="top" style="'.$border.'padding:12px 14px;color:#1d3448;font-size:14px;font-weight:700;overflow-wrap:anywhere">'.h($value).'</td>'
                .'</tr>';
            $index++;
        }
        return $html.'</table>';
    }

    public static function estimateAdmin(array $request,array $settings): string
    {
        $rows=[
            'Name'=>$request['full_name']??'',
            'Phone'=>$request['phone']??'',
            'Email'=>$request['email']??'',
            'Address'=>$request['address']??'',
            'Service'=>$request['service_needed']??'',
            'Desired date'=>$request['desired_date']??'',
            'How they found us'=>trim((string)($request['lead_source']??''))?:'Not provided',
        ];
        if(trim((string)($request['lead_source_detail']??''))!=='')$rows['Referral detail']=$request['lead_source_detail'];

        $html='<p style="margin:0 0 18px">A new request was submitted from the public website and is ready for administrative review.</p>';
        $html.=self::detailRows($rows);
        $html.='<div style="margin-top:20px;padding:17px 18px;border-left:4px solid #2c7fc3;border-radius:10px;background:#f0f6fb">'
            .'<strong style="display:block;margin-bottom:7px;color:#102a43">Project details</strong>'
            .'<div style="white-space:normal;overflow-wrap:anywhere">'.self::safeRichText((string)($request['message']??'')).'</div></div>';

        return self::base('New estimate request',$html,$settings,'NEW BUSINESS REQUEST','A new estimate request is ready for review.');
    }

    public static function estimateCustomer(array $request,array $settings): string
    {
        $name=trim((string)($request['full_name']??''));
        $greeting=$name!==''?'Hello '.h($name).',':'Hello,';
        $content='<p style="margin:0 0 16px">'.$greeting.'</p>'
            .'<p style="margin:0 0 16px">Thank you for contacting us. Your request was received successfully and our team will review it as soon as possible.</p>'
            .'<div style="margin:22px 0 0;padding:16px 18px;border:1px solid #cfe0ed;border-radius:12px;background:#f4f8fb">'
            .'<strong style="display:block;margin-bottom:5px;color:#102a43">What happens next?</strong>'
            .'<span>We will contact you using the information provided. If you need to add details, use the contact channels shown on our website.</span></div>';

        return self::base('We received your request',$content,$settings,'REQUEST CONFIRMATION','Your request was received successfully.');
    }

    public static function estimateResponse(string $recipientName,string $safeHtml,array $settings): string
    {
        $name=trim($recipientName)!==''?h($recipientName):'';
        $greeting=$name!==''?'Hello '.$name.',':'Hello,';
        $content='<p style="margin:0 0 18px">'.$greeting.'</p>'
            .'<div style="color:#33485b;overflow-wrap:anywhere">'.$safeHtml.'</div>';
        return self::base('Response to your estimate request',$content,$settings,'PROFESSIONAL RESPONSE','A response to your estimate request is available.');
    }

    public static function adminReset(string $name,array $settings): string
    {
        $content='<p style="margin:0 0 16px">Hello '.h($name).',</p>'
            .'<p style="margin:0 0 16px">Administrator access for the CMS was reset for site handoff. Website content and customer requests were not deleted.</p>'
            .'<div style="padding:16px 18px;border-left:4px solid #c2414b;border-radius:10px;background:#fff3f3;color:#70343a">'
            .'<strong style="display:block;margin-bottom:5px">Security notice</strong>If you did not perform this action, review the hosting account, database credentials and administrator access immediately.</div>';

        return self::base('Administrator access reset',$content,$settings,'SECURITY NOTICE','Administrator access was reset.');
    }

    public static function administratorWelcome(string $name,string $loginUrl,array $settings): string
    {
        $displayName=trim($name)!==''?h($name):'Administrator';
        $safeLoginUrl=h($loginUrl);
        $content='<p style="margin:0 0 16px">Hello '.$displayName.',</p>'
            .'<p style="margin:0 0 18px">Your administrator account is ready. You can now access the CMS to manage content, requests and system settings according to your assigned permissions.</p>'
            .'<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0"><tr><td bgcolor="#155eaa" style="border-radius:10px">'
            .'<a class="email-button" href="'.$safeLoginUrl.'" style="display:inline-block;padding:14px 22px;background:#155eaa;color:#ffffff;text-decoration:none;font-weight:800;border-radius:10px">Open secure administration</a>'
            .'</td></tr></table>'
            .'<div style="padding:16px 18px;border:1px solid #dbe4ec;border-radius:11px;background:#f8fafc">'
            .'<strong style="display:block;margin-bottom:6px;color:#102a43">Security recommendation</strong>'
            .'Keep your credentials private, enable two-factor authentication from your profile and sign out when using a shared device.</div>'
            .'<p style="margin:17px 0 0;font-size:12px;line-height:1.5;color:#738191;word-break:break-all">'.$safeLoginUrl.'</p>';

        return self::base('Welcome to the administration',$content,$settings,'ACCOUNT READY','Your administrator account is ready.');
    }

    public static function freshInstallPreparationAlert(string $name,string $username,string $ip,string $dateTime,array $settings,string $databaseName=''): string
    {
        $displayName=trim($name)!==''?h($name):'Owner';
        $content='<p style="margin:0 0 16px">Hola '.$displayName.',</p>'
            .'<p style="margin:0 0 18px">Se autorizó desde el <strong>Security Center</strong> una <strong>instalación desde cero de IZZY</strong>.</p>'
            .self::detailRows([
                'Usuario'=>$username,
                'Fecha y hora'=>$dateTime,
                'Dirección IP'=>$ip!==''?$ip:'No disponible',
                'Acción'=>'Eliminar IZZY e iniciar desde cero',
                'Base de datos'=>$databaseName!==''?$databaseName:'No disponible',
            ])
            .'<div style="margin-top:20px;padding:16px 18px;border-left:4px solid #c98a11;border-radius:10px;background:#fff8e8;color:#694a08">'
            .'<strong style="display:block;margin-bottom:6px;color:#523800">Aviso de seguridad</strong>'
            .'Este correo se envía <strong>antes</strong> de ejecutar la operación. Después de enviarlo correctamente se eliminará <strong>permanentemente la base de datos actual de IZZY</strong>, se retirarán la configuración activa y <strong>install.lock</strong>, se cerrarán las sesiones y se abrirá el instalador como una instalación nueva.</div>'
            .'<p style="margin:18px 0 0">Si no reconoces esta acción, protege de inmediato el acceso al hosting y a la base de datos.</p>';

        return self::base('Instalación desde cero autorizada',$content,$settings,'ALERTA CRÍTICA DE SEGURIDAD','Se autorizó una operación destructiva desde el Security Center.');
    }

    public static function passwordReset(string $name,string $url,array $settings): string
    {
        $safeUrl=h($url);
        $content='<p style="margin:0 0 16px">Hello '.h($name).',</p>'
            .'<p style="margin:0 0 18px">A password reset was requested for your CMS administrator account.</p>'
            .'<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0"><tr><td bgcolor="#155eaa" style="border-radius:10px">'
            .'<a class="email-button" href="'.$safeUrl.'" style="display:inline-block;padding:14px 22px;background:#155eaa;color:#ffffff;text-decoration:none;font-weight:800;border-radius:10px">Create new password</a>'
            .'</td></tr></table>'
            .'<div style="padding:15px 17px;border:1px solid #dbe4ec;border-radius:11px;background:#f8fafc">This secure link expires in 60 minutes and can be used only once. Your password is never sent by email.</div>'
            .'<p style="margin:17px 0 0;font-size:12px;line-height:1.5;color:#738191;word-break:break-all">'.$safeUrl.'</p>';

        return self::base('Reset administrator password',$content,$settings,'SECURE ACCOUNT ACTION','Use the secure link to reset your administrator password.');
    }

    public static function test(string $method,array $settings): string
    {
        $safeMethod=h(strtoupper(trim($method)));
        $sentAt=h(date('Y-m-d H:i:s'));
        $content='<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin-bottom:20px"><tr>'
            .'<td width="52" valign="top"><div style="width:44px;height:44px;line-height:44px;text-align:center;border-radius:13px;background:#e8f6ef;color:#167552;font-size:22px;font-weight:900">✓</div></td>'
            .'<td valign="middle" style="padding-left:12px"><strong style="display:block;color:#102a43;font-size:17px">Connection verified</strong><span style="color:#607080;font-size:14px">Microsoft Graph or SMTP accepted this test message from the installer.</span></td>'
            .'</tr></table>'
            .self::detailRows(['Delivery method'=>$safeMethod,'Validation time'=>$sentAt,'Result'=>'Message accepted for sending'])
            .'<p style="margin:20px 0 0">This confirms that the CMS can submit professional HTML email using the selected configuration. Final delivery still depends on the receiving mail system.</p>';

        return self::base('Email configuration test',$content,$settings,'DELIVERY TEST · VERIFIED','Your CMS email configuration is working correctly.');
    }
}
