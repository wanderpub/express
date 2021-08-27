<?php

// 1. 手动加载入口文件
include __DIR__ ."/../include.php";

try {
    $number = 'YT5744661853493';

    //cookie存放路径
    $cookiePath = __DIR__ . '/cookie';
    //错误重试次数
    $tryTimes = 3;
    //ip地址
    $ip = '101.69.230.179';
    $express = new \Express\Express($cookiePath, $ip, $tryTimes);
    //取快递公司列表
    $res = $express->getExpressList();
    print_r($res);

    //取快递物流信息
    $res = $express->express($number);
    print_r($res);
} catch (\Exception $e) {
    echo $e->getMessage();
}
