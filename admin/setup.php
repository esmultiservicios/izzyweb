<?php
require __DIR__.'/bootstrap.php';
// Legacy owner-setup route retired in v1.0.80.
// IZZY now has a single setup path: the protected 4-step installer.
if(admin_count()>0) {
    header('Location: login.php', true, 303);
    exit;
}
header('Location: ../install/', true, 303);
exit;
