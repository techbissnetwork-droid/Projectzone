<?php
require __DIR__ . '/../app/bootstrap.php';
auth_logout();
redirect('admin/login.php');
