<?php
require __DIR__.'/bootstrap.php';
if (admin_count()===0) { header('Location: ../install/', true, 303); exit; }
header('Location: '.(is_logged_in()?'dashboard.php':'login.php'));
