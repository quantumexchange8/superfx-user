<?php
echo 'success';
echo $params = json_decode(file_get_contents("php://input"), true);
var_dump($params);exit;
