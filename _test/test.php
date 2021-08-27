<?php

// 1. 手动加载入口文件
include __DIR__ ."/../include.php";

try {
    $number = 'YT5744661853493';

    //cookie存放路径
    $cookiePath = __DIR__ . '/cookie';
    $express = new \Express\Express($cookiePath);
    //取快递公司列表
    $res = $express->getExpressList();
    print_r($res);
    
    //取快递物流信息
    $res = $express->express($number);
    print_r($res);
} catch (\Exception $e) {
    echo $e->getMessage();
}
