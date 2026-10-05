<?php
declare(strict_types=1);

function social_platform_catalog(): array
{
    return [
        'instagram'=>['label'=>'Instagram','icon'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" class="social-icon-fill"/></svg>'],
        'facebook'=>['label'=>'Facebook','icon'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 8h4V3h-4c-4 0-6 2.4-6 6v3H4v5h4v4h5v-4h4l1-5h-5V9c0-.7.3-1 1-1Z"/></svg>'],
        'tiktok'=>['label'=>'TikTok','icon'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3h4c.3 2 1.5 3.2 3 3.6v4.1a9 9 0 0 1-3-1.1V16a6 6 0 1 1-6-6v4a2 2 0 1 0 2 2V3Z"/></svg>'],
        'youtube'=>['label'=>'YouTube','icon'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 8.1a3 3 0 0 0-2.1-2.2C18 5.4 12 5.4 12 5.4s-6 0-7.9.5A3 3 0 0 0 2 8.1 31 31 0 0 0 1.5 12 31 31 0 0 0 2 15.9a3 3 0 0 0 2.1 2.2c1.9.5 7.9.5 7.9.5s6 0 7.9-.5a3 3 0 0 0 2.1-2.2 31 31 0 0 0 .5-3.9 31 31 0 0 0-.5-3.9ZM10 15.5v-7l6 3.5-6 3.5Z"/></svg>'],
        'linkedin'=>['label'=>'LinkedIn','icon'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 3.5A2.5 2.5 0 1 1 4 8a2.5 2.5 0 0 1 0-4.5ZM2 9h4v12H2V9Zm6.5 0h3.8v1.7c.9-1.3 2.3-2.1 4.3-2.1 4 0 5.4 2.5 5.4 6.4v6h-4v-5.3c0-2.1-.7-3.4-2.5-3.4-2 0-3 1.4-3 4.1V21h-4V9Z"/></svg>'],
    ];
}

function social_default_links(): array
{
    $catalog=social_platform_catalog();
    return [
        ['platform'=>'facebook','label'=>$catalog['facebook']['label'],'icon'=>$catalog['facebook']['icon'],'url'=>'https://www.facebook.com/esmultiserv','sort_order'=>20,'active'=>1],
        ['platform'=>'tiktok','label'=>$catalog['tiktok']['label'],'icon'=>$catalog['tiktok']['icon'],'url'=>'https://www.tiktok.com/@evelasquez91','sort_order'=>30,'active'=>1],
    ];
}

function social_public_links(): array
{
    try {
        $catalog=social_platform_catalog();
        $rows=db()->query('SELECT id,platform,url,sort_order FROM social_links WHERE active=1 ORDER BY sort_order,id')->fetchAll();
        return array_values(array_filter(array_map(static function(array $row) use($catalog): ?array {
            $platform=strtolower(trim((string)($row['platform']??'')));
            $url=trim((string)($row['url']??''));
            if(!isset($catalog[$platform])||!filter_var($url,FILTER_VALIDATE_URL)||!preg_match('~^https?://~i',$url))return null;
            return [
                'platform'=>$platform,
                'label'=>$catalog[$platform]['label'],
                'icon'=>$catalog[$platform]['icon'],
                'url'=>$url,
                'sort_order'=>(int)($row['sort_order']??0),
            ];
        },$rows)));
    } catch(Throwable $ignored) {
        return [];
    }
}

function social_display_config(array $settings): array
{
    $size=(string)($settings['social_size']??'medium');
    $style=(string)($settings['social_style']??'icon_name');
    $location=(string)($settings['social_location']??'footer');
    return [
        'size'=>in_array($size,['small','medium','large'],true)?$size:'medium',
        'style'=>in_array($style,['icon','icon_name'],true)?$style:'icon_name',
        'location'=>in_array($location,['footer','below_hero','floating_left','floating_right','footer_floating_left','footer_floating_right'],true)?$location:'footer',
        'desktop'=>(string)($settings['social_show_desktop']??'1')==='1',
        'mobile'=>(string)($settings['social_show_mobile']??'1')==='1',
    ];
}

function social_location_has(string $location,string $target): bool
{
    if($target==='footer')return in_array($location,['footer','footer_floating_left','footer_floating_right'],true);
    if($target==='below_hero')return $location==='below_hero';
    if($target==='floating_left')return in_array($location,['floating_left','footer_floating_left'],true);
    if($target==='floating_right')return in_array($location,['floating_right','footer_floating_right'],true);
    return false;
}

function render_social_links(array $links,array $config,string $context): string
{
    if(!$links)return '';
    $visibility=(!$config['desktop']?' social-hide-desktop':'').(!$config['mobile']?' social-hide-mobile':'');
    $style='social-links social-size-'.h($config['size']).' social-style-'.h($config['style']).$visibility;
    $items='';
    foreach($links as $link) {
        $items.='<a href="'.h($link['url']).'" target="_blank" rel="noopener noreferrer" aria-label="'.h($link['label']).'">'
            .'<span class="social-svg">'.$link['icon'].'</span>'
            .($config['style']==='icon_name'?'<span class="social-name">'.h($link['label']).'</span>':'')
            .'</a>';
    }
    if($context==='below_hero')return '<section class="social-strip" aria-label="Social networks"><div class="container"><div class="'.$style.'">'.$items.'</div></div></section>';
    if($context==='footer')return '<div class="footer-social" aria-label="Social networks"><strong>Follow us</strong><div class="'.$style.'">'.$items.'</div></div>';
    if(in_array($context,['floating_left','floating_right'],true))return '<nav class="social-dock '.h($context).'" aria-label="Social networks"><div class="'.$style.'">'.$items.'</div></nav>';
    return '';
}
