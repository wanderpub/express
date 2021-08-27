<?php

namespace Express;

/**
 * 百度快递100物流查询
 * Class Express
 */
class Express
{
    /**
     *  当前COOKIE文件
     * @var string
     */
    protected $cookies;

    /**
     * 取不到信息重试次数
     *
     * @var integer
     */
    protected $tryTimes = 3;

    /**
     * 网络请求参数
     * @var array
     */
    protected $options;

    public function __construct($cookie_path = '')
    {
        // 创建 CURL 请求模拟参数
        $clentip = '101.69.230.179';
        if (!empty($cookie_path)) {
            $this->cookies = $cookie_path;
        } else {
            $this->cookies =  './cookie';
        }
        $this->options = [
            'cookie_file' => $this->cookies,
            'headers' => [
                'Host:express.baidu.com', 
                "CLIENT-IP:{$clentip}", 
                "X-FORWARDED-FOR:{$clentip}",
            ]
        ];
        // 每 30000 秒重置 cookie 文件
        if (file_exists($this->cookies) && filectime($this->cookies) + 30000 < time()) {
            @unlink($this->cookies);
        }
    }

    /**
     * 获取快递公司列表
     * @return array
     */
    public function getExpressList()
    {
        $url = 'https://m.baidu.com/s?word=快递&t=' . time();
        $this->options['return_header'] = true;
        $content = $this->get($url, [], $this->options);
        // $pattern = '/"currentData":.*?\[(.*?)],/';
        $pattern = '/"快递查询","option":.*?\[(.*?)],/';
        if (preg_match($pattern, $content, $matches)) {
            $items = json_decode("[{$matches['1']}]", true);
            return $items;
        } else {
            return $this->getExpressList();
        }
    }

    /**
     * 通过百度快递100应用查询物流信息
     * @param string $number 快递物流编号
     * @param string $code 快递公司编码（默认用_auto,自动判断快递公司）
     * @param array $list 快递路径列表，自行添加物流轨迹前路径，比如拣货、验货等。
     * @return array
     */
    public function express(string $number, string $code = '_auto', array $list = []): array
    {
        // status : 1-新订单,2-在途中,3-签收,4-问题件
        // state : 0-在途，1-揽收，2-疑难，3-签收，4-退签，5-派件，6-退回
        $res = ['message' => '暂无轨迹信息', 'status' => 1, 'express' => $code, 'number' => $number, 'data' => $list];
        for ($i = 0; $i < $this->tryTimes; $i++) {
            $result = $this->doExpress($code, $number);
            $status = $result['status'] ?? 0;
            if ($status == '-3') {
                $res['message'] = $result['msg'] ?? '';
                return $res;
            }
            if (is_array($result)) {
                if (isset($result['data']['info']['context']) && isset($result['data']['info']['state'])) {
                    $state = intval($result['data']['info']['state']);
                    $status = in_array($state, [0, 1, 5]) ? 2 : ($state === 3 ? 3 : 4);
                    foreach ($result['data']['info']['context'] as $vo) {
                        $list[] = ['time' => date('Y-m-d H:i:s', intval($vo['time'])), 'context' => $vo['desc']];
                    }
                    $result = [
                        'message' => $result['notice'] ?? '',
                        'status' => $status,//$result['data']['info']['status'] ?? '',
                        'state' => $result['data']['info']['state'] ?? '',
                        'com' => $result['data']['info']['com'] ?? '',
                        'send_time' => $result['data']['info']['send_time'] ?? '',
                        'departure_city' => $result['data']['info']['departure_city'] ?? '',
                        'arrival_city' => $result['data']['info']['arrival_city'] ?? '',
                        'latest_progress' => $result['data']['info']['latest_progress'] ?? '',
                        'current' => $result['data']['info']['current'] ?? '',
                        'currentStatus' => $result['data']['info']['currentStatus'] ?? '',
                        'latest_time' => $result['data']['info']['latest_time'] ?? '',
                        'express' => $result['data']['info']['com'] ?? '',
                        'number' => $number,
                        'data' => $list,
                        'company' => [
                            'fullname' => $result['data']['company']['fullname'] ?? '',
                            'shortname' => $result['data']['company']['shortname'] ?? '',
                            'tel' => $result['data']['company']['tel'] ?? '',
                            'url' => $result['data']['company']['website']['url'] ?? '',
                            'logo' => $result['data']['company']['icon']['normal'] ?? ''
                        ]
                    ];
                    return $result;
                }
                return $res;
            }
        }
        
    }

    /**
     * 取快递单号查询链接地址
     *
     * @return void
     */
    private function getTokenV2()
    {
        $tokenUrl = 'http://www.baidu.com/baidu?isource=infinity&iname=baidu&itype=web&tn=02003390_42_hao_pg&ie=utf-8&wd=%E5%BF%AB%E9%80%92';
        $this->options['return_header'] = true;
        $content = $this->get($tokenUrl, [], $this->options);
        $pattern = '/apiUrl: \'(.*?)\',/i';
        if (preg_match($pattern, $content, $matches)) {
            return $matches[1];
        } else {
            //取不到？换方法再试一次
            return $this->getUrl();
        }
    }

    /**
     * 取快递单号查询链接地址,换地址
     *
     * @return void
     */
    private function getUrl()
    {
        $tokenUrl = 'https://m.baidu.com/s?word=快递&t=' . time();
        $this->options['return_header'] = true;
        $content = $this->get($tokenUrl, [], $this->options);
        $pattern = '/"expSearchApi":.*?"(.*?)",/';
        if (preg_match($pattern, $content, $matches)) {
            return $matches[1];
        } else {
            //取不到？换方法再试一次
            return $this->getTokenV2();
        }
    }

    /**
     * 唯一数字编码
     * @param integer $size 编码长度
     * @param string $prefix 编码前缀
     * @return string
     */
    private function uniqidNumber(int $size = 12, string $prefix = ''): string
    {
        $time = time() . '';
        if ($size < 10) $size = 10;
        $code = $prefix . (intval($time[0]) + intval($time[1])) . substr($time, 2) . rand(0, 9);
        while (strlen($code) < $size) $code .= rand(0, 9);
        return $code;
    }
    /**
     * 执行百度快递100应用查询请求
     * @param string $code 快递公司编号
     * @param string $number 快递单单号
     * @return mixed
     */
    private function doExpress(string $code, string $number)
    {
        $qid = $this->uniqidNumber(19, '7740');
        $http = $this->getTokenV2();//$this->_getExpressQueryApi();
        $url = "{$http}&appid=4001&nu={$number}&com={$code}&qid={$qid}&new_need_di=1&source_xcx=0&vcode=&token=&sourceId=4155&cb=callback";
        $this->options['return_header'] = false;
        $content = $this->get($url, [], $this->options);
        $content = str_replace('/**/callback(', '', trim($content, ')'));
        return json_decode($content, true);
    }
    /**
     * 以 CURL 模拟网络请求
     * @param string $method 模拟请求方式
     * @param string $location 模拟请求地址
     * @param array $options 请求参数[headers,query,data,cookie,cookie_file,timeout,returnHeader]
     * @return boolean|string
     */
    private function request(string $method, string $location, array $options = [])
    {
        // GET 参数设置
        if (!empty($options['query'])) {
            $location .= strpos($location, '?') !== false ? '&' : '?';
            if (is_array($options['query'])) {
                $location .= http_build_query($options['query']);
            } elseif (is_string($options['query'])) {
                $location .= $options['query'];
            }
        }
        $curl = curl_init();
        // Agent 代理设置
        curl_setopt($curl, CURLOPT_USERAGENT, $this->getUserAgent());
        // Cookie 信息设置
        if (!empty($options['cookie'])) {
            curl_setopt($curl, CURLOPT_COOKIE, $options['cookie']);
        }
        // Header 头信息设置
        if (!empty($options['headers'])) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $options['headers']);
        }
        if (!empty($options['cookie_file'])) {
            curl_setopt($curl, CURLOPT_COOKIEJAR, $options['cookie_file']);
            curl_setopt($curl, CURLOPT_COOKIEFILE, $options['cookie_file']);
        }
        // 设置请求方式
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        if (strtolower($method) === 'head') {
            curl_setopt($curl, CURLOPT_NOBODY, 1);
        } elseif (isset($options['data'])) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $options['data']);
        }
        // 请求超时设置
        if (isset($options['timeout']) && is_numeric($options['timeout'])) {
            curl_setopt($curl, CURLOPT_TIMEOUT, $options['timeout']);
        } else {
            curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        }
        $returnHeader = $options['return_header'] ?? true;
        // 是否返回HEADER
        if (!$returnHeader) {
            curl_setopt($curl, CURLOPT_HEADER, false);
        } else {
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        }
        curl_setopt($curl, CURLOPT_URL, $location);
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        [$content] = [curl_exec($curl), curl_close($curl)];
        if ($returnHeader) {
            // 解析HTTP数据流
            list($header, $body) = explode("\r\n\r\n", $content);
            // 解析COOKIE
            preg_match("/set\-cookie:([^\r\n]*)/i", $header, $matches);
            // print_r($matches);
            //请求的时候headers 带上cookie就可以了
            $cookie = explode(';', $matches[1])[0];
            // echo 'COOKIE:' . $cookie. PHP_EOL;
            $this->options['cookie'] = trim($cookie);
        }
        return $content;
    }
    /**
     * 以 GET 模拟网络请求
     * @param string $location HTTP请求地址
     * @param array|string $data GET请求参数
     * @param array $options CURL请求参数
     * @return boolean|string
     */
    private function get(string $location, $data = [], array $options = [])
    {
        $options['query'] = $data;
        return $this->request('get', $location, $options);
    }

    /**
     * 以 POST 模拟网络请求
     * @param string $location HTTP请求地址
     * @param array|string $data POST请求数据
     * @param array $options CURL请求参数
     * @return boolean|string
     */
    private function post(string $location, $data = [], array $options = [])
    {
        $options['data'] = $data;
        return $this->request('post', $location, $options);
    }

    /**
     * 获取浏览器代理信息
     * @return string
     */
    private function getUserAgent(): string
    {
        $agents = [
            "Mozilla/5.0 (Windows NT 6.1; rv:2.0.1) Gecko/20100101 Firefox/4.0.1",
            "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.57 Safari/536.11",
            "Mozilla/5.0 (Windows NT 10.0; WOW64; rv:38.0) Gecko/20100101 Firefox/38.0",
            "Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; .NET4.0C; .NET4.0E; .NET CLR 2.0.50727; .NET CLR 3.0.30729; .NET CLR 3.5.30729; InfoPath.3; rv:11.0) like Gecko",
            "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-us) AppleWebKit/534.50 (KHTML, like Gecko) Version/5.1 Safari/534.50",
            "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0)",
            "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0)",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.6; rv:2.0.1) Gecko/20100101 Firefox/4.0.1",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_0) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.56 Safari/535.11",
        ];
        return $agents[array_rand($agents)];
    }
}
