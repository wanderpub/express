# Express
百度快递100物流查询--PHP版
自动抓取百度快递接口数据，实时返回物流轨迹状态信息。
----

建议在 PHP7.1 上运行以获取最佳性能；
Express API SDK for PHP

功能描述
----

* 1、获取所有快递公司列表
* 2、获取快递/物流状态信息

安装使用
----
1.1 通过 Composer 来管理安装

```shell
# 首次安装 
composer require wander/express

# 更新 Express
composer update wander/express
```

1.2 如果不使用 Composer， 可以下载 Express 并解压到项目中

```php
# 在项目中加载初始化文件
include "您的目录/Express/include.php";
```

* 获取所有快递公司列表

```php
// 参考https://m.baidu.com/s?word=快递
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

```

开源协议
----

* Express 基于`MIT`协议发布，任何人可以用在任何地方，不受约束
* Express 部分代码来自互联网，若有异议，可以联系作者(13834563@qq.com)进行删除

