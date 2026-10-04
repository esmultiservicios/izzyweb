<?php
require __DIR__.'/bootstrap.php';

try {
    if(is_logged_in()) {
        log_activity('logout','Administrator signed out');
    }
} catch(Throwable $e) {
}

clear_admin_authentication_state(true,true);
header('Location: login.php?logged_out=1',true,303);
exit;
