<?php
namespace Metz/Lib
class Curl {
    const PROTOCOL_HTTP = 1;
    const PROTOCOL_FTP = 2;

    protected $mh;
    protected $timeout = 600;
    protected $conn_timeout = 200;//20130731
    protected $handles = array();
    protected $hosts   = array();

    function __construct()
    {
        $this->mh = curl_multi_init();
    }
    function __destruct()
    {
        curl_multi_close($this->mh);
    }

    /**
     * @param string $url
     * @param string $method
     * @param null $postfields
     * @param bool $multi
     * @param array $header_array
     * @return mixed
     */
    public static function http($url, $method, $postfields = null, $multi = false, $header_array = array()) {
        $ch = curl_init();

        // Curl 设置
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        
        $method = strtoupper($method);
        switch ($method) {
            case 'GET':
                $url = is_array($postfields) ? $url . '?' . http_build_query($postfields) : $url;
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($postfields)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                if (!empty($postfields)) {
                    $url = "{$url}?{$postfields}";
                }
        }

        $header_array2 = array();
        
        if ($multi) {
            $header_array2 = array('Content-Type: multipart/form-data; boundary=' . self::$boundary, 'Expect: ');
        }
        if (is_array($header_array)) {
            foreach ($header_array as $k => $v) {
                array_push($header_array2, $k . ': ' . $v);
            }
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header_array2);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        $response = curl_exec($ch);
        curl_close ($ch);
        return $response;
    }

    /**
     * 通过curl get数据
     * @param string $url
     * @param string $header
     * @param int $timeout
     * @param int $retry_times
     * @param array $curl_status
     * @return mixed
     */
    public static function curlGet($url, $header = '', $timeout = 5, $retry_times = 0, &$curl_status = array()) {
        $header = empty($header) ? self::defaultHeader() : $header;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array($header)); // 模拟的header头
        $retry_times < 0 && $retry_times = 0;
        for($i = 0; $i <= $retry_times; $i++){
            if($i>0){
                Lib_Log::info("the request of {$url} time out after {$timeout} sec and retrying the {$i} times");
            }
            $result = curl_exec($ch);
            if($result !== false){
                break;
            }
        }
        $curl_status = curl_getinfo($ch);
        curl_close($ch);
        return $result;
    }

    /**
     * 通过curl post数据
     * @param string $url
     * @param array $post_data
     * @param array $header
     * @param string $cookie
     * @param int $timeout
     * @param int $retry_times
     * @param array $curl_status
     * @return mixed
     */
    public static function curlPost($url, $post_data = array(), $header = array(),$cookie='', $timeout = 5, $retry_times = 0, &$curl_status = array()) {
        //print_r(array("url"=>$url, "post_data" => $post_data, "header" => $header,"cookie"=>$cookie));
        $post_string = is_array($post_data)?http_build_query($post_data):$post_data;  
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header); // 模拟的header头
        //设置连接结束后保存cookie信息的文件
        curl_setopt($ch, CURLOPT_COOKIE,$cookie);
        $retry_times < 0 && $retry_times = 0;
        for($i = 0; $i <= $retry_times; $i++) {
            if($i>0){
                Lib_Log::info("the request of {$url} time out after {$timeout} sec and retrying the {$i} times and post_data is ".Lib_Array::varExport($post_data));
            }
            $result = curl_exec($ch);
            if($result !== false){
                break;
            }
        }
        $curl_status = curl_getinfo($ch);
        curl_close($ch);
        return $result;
    }

    /**
     * @param string $url
     * @param array $post_data
     * @param array $header
     * @param string $body
     * @param int $timeout
     * @return mixed
     */
    public static function getCookie($url, $post_data = array(), $header = array(), &$body = null, $timeout = 5)
    {
        $post_string = is_array($post_data)?http_build_query($post_data):$post_data;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header); // 模拟的header头
        $content = curl_exec($ch);
        curl_close($ch);
        list($header, $body) = explode("\r\n\r\n", $content);
        preg_match("/set\-cookie:([^\r\n]*)/i", $header, $matches); //这个地方需要变通一下。cookie不是只有1个。
        return $matches[1];
    }

    /**
     * 默认模拟的header头
     * @return string
     */
    private static function defaultHeader() {
        $header  = "User-Agent:Mozilla/5.0 (Windows; U; Windows NT 5.1; zh-CN; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12\r\n";
        $header .= "Accept:text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n";
        $header .= "Accept-language: zh-cn,zh;q=0.5\r\n";
        $header .= "Accept-Charset: GB2312,utf-8;q=0.7,*;q=0.7\r\n";
        return $header;
    }

    /**
     * 加入一个curl并发请求
     * @param string $key
     * @param string $url
     * @param string $method
     * @param array $post_data
     * @param array $header
     * @param string $timeout
     * @param string $conn_timeout
     * @return bool
     */
    public function multiAdd($key, $url,$method='get', $post_data = array(), $header = array(), $timeout='', $conn_timeout='')
    {
        if(empty($url)||empty($key)) {
            return false;
        }
        $parts = parse_url($url);
        $host  = $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $this->hosts[$key] = $host;
        // get options
        $timeout = isset($timeout) ? $timeout : $this->timeout;
        $conn_timeout = isset($conn_timeout) ? $conn_timeout : $this->conn_timeout;
        if(isset($post_data)) {
            if(strncasecmp($method, 'get', 3) === 0) {
                $query = http_build_query($post_data);
                if(strpos($url, '?') === false) {
                    $url .= '?'.$query;
                } else if($url[strlen($url)-1] == '&') {
                    $url .= $query;
                } else {
                    $url .= '&'.$query;
                }
            }
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER        , 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $conn_timeout);
        if(isset($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        if(strncasecmp($method, 'post', 4) === 0) {
            curl_setopt($ch, CURLOPT_POST, 1);
            if(isset($post_data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            }
        }
        curl_multi_add_handle($this->mh, $ch);    
        $this->handles[$key] = $ch;
        return true;
    }

    /**
     * 获取并发curl结果
     * 为防止假死，设置循环最大执行时间，为0为不设最大执行时间
     * @param int $max_timeout
     * @return array
     */
    public function multiGet($max_timeout=0)
    {
        $active = null;
        $t1 = time();
        do {
            $mrc = curl_multi_exec($this->mh, $active);
        } while($mrc == CURLM_CALL_MULTI_PERFORM);

        while($active && $mrc == CURLM_OK) {
            if($max_timeout >0 && (time() - $t1) > $max_timeout){
                Lib_Log::warn("exec time out,exit loop");
                break;
            }
            //PHP_VERSION_ID < 50214 ? usleep(5000) : curl_multi_select($this->mh, 0.2);
            if(curl_multi_select($this->mh, 0.2)!=-1){
                do {
                    $mrc = curl_multi_exec($this->mh, $active);
                } while($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }
        $stat = array();
        $data = array();
        foreach($this->handles as $key => $ch) {
            $d = array();
            $d['errno'] = curl_errno($ch);
            if($d['errno'] != CURLE_OK) {
                $d['error'] = curl_error($ch);
                continue;
            } else {
                $d['total_time'] = curl_getinfo($ch, CURLINFO_TOTAL_TIME );
                $d['code']       = curl_getinfo($ch, CURLINFO_HTTP_CODE  ); 
                $d['data']       = curl_multi_getcontent($ch);
            }
            $data[$key] = $d;
            curl_multi_remove_handle($this->mh, $ch);
            curl_close($ch);
        }
        $this->handles = array();
        return array('errno' => 0, 'data' => $data);
    }

    /**
     * @param $remote
     * @param $local
     * @param null $info_callback
     * @return array
     */
    static function download($remote, $local, $info_callback = null)
    {
        if (file_exists($local)) {
            @unlink($local);
        }
        $fp = fopen($local, "a");
        if ($fp === false) {
            return ['errno' => -1, 'msg' => 'open file ['.$local.'] failed.'];
        }
        $remote = trim($remote);
        $protocol = self::PROTOCOL_HTTP;
        strpos($remote, 'ftp:') === 0 && $protocol = self::PROTOCOL_FTP;  // ftp protocol
        $write_func = function($cp, $content) use ($fp, $info_callback, $remote, $local) {
            //($content_length == 0) || $download_percent = sprintf("%.2f%%", $size_download * 100 / $content_length);
            empty($content) || fwrite($fp, $content);
            $len = strlen($content);
            if (is_callable($info_callback)) {
                $info = [
                    'http_code' => curl_getinfo($cp, CURLINFO_HTTP_CODE),
                    'total_time' => curl_getinfo($cp, CURLINFO_TOTAL_TIME),
                    'namelook_time' => curl_getinfo($cp, CURLINFO_NAMELOOKUP_TIME),
                    'connect_time' => curl_getinfo($cp, CURLINFO_CONNECT_TIME),
                    'content_length' => curl_getinfo($cp, CURLINFO_CONTENT_LENGTH_DOWNLOAD),
                    'download_speed' => curl_getinfo($cp, CURLINFO_SPEED_DOWNLOAD),
                    'download_size' => curl_getinfo($cp, CURLINFO_SIZE_DOWNLOAD),
                    'remote' => $remote,
                    'file' => $local,
                ];
                call_user_func($info_callback, $info);
            }
            return $len;
        };
        $ua = 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.125 Safari/537.36';
        $cp = curl_init($remote);
        curl_setopt($cp, CURLOPT_HEADER, 0);
        curl_setopt($cp, CURLOPT_AUTOREFERER, 1);
        curl_setopt($cp, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($cp, CURLOPT_MAXREDIRS, 10);
        curl_setopt($cp, CURLOPT_WRITEFUNCTION, $write_func);
        curl_setopt($cp, CURLOPT_USERAGENT, $ua);
        curl_setopt($cp, CURLOPT_LOW_SPEED_LIMIT, 100);
        curl_setopt($cp, CURLOPT_LOW_SPEED_TIME, 1800);
        $exec_result = curl_exec($cp);
        $code = curl_getinfo($cp, CURLINFO_HTTP_CODE);
        $content_length = curl_getinfo($cp, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $size_download = curl_getinfo($cp, CURLINFO_SIZE_DOWNLOAD);
        $speed = curl_getinfo($cp, CURLINFO_SPEED_DOWNLOAD);
        $total_time = curl_getinfo($cp, CURLINFO_TOTAL_TIME);
        $nameloop = curl_getinfo($cp, CURLINFO_NAMELOOKUP_TIME);
        $connect_time = curl_getinfo($cp, CURLINFO_CONNECT_TIME);
        $return = [
            'errno' => 0,
            'msg' => [
                'total_time' => curl_getinfo($cp, CURLINFO_TOTAL_TIME),
                'namelook_time' => curl_getinfo($cp, CURLINFO_NAMELOOKUP_TIME),
                'connect_time' => curl_getinfo($cp, CURLINFO_CONNECT_TIME),
                'content_length' => curl_getinfo($cp, CURLINFO_CONTENT_LENGTH_DOWNLOAD),
                'download_speed' => curl_getinfo($cp, CURLINFO_SPEED_DOWNLOAD),
                'download_size' => curl_getinfo($cp, CURLINFO_SIZE_DOWNLOAD),
                'remote' => $remote,
                'file' => $local,
            ],
        ];
        if ($protocol == self::PROTOCOL_HTTP && ($code < 200 || $code > 300))  {
            $return = [
                'errno' => -1,
                'msg' => '['.$remote.'] download failed!，http code:'.$code
            ];
        }
        if (!$exec_result) {
            $return['errno'] = -1;
            $return['msg'] = curl_error($cp);
        }
        curl_close($cp);
        fclose($fp);
        return $return;
    }

    /**
     * @param array $request_struct
     * @param string $header
     * @return null
     */
    static function multiRequest($request_struct, $header = null)
    {
        $handle_list = [];
        $header || $header = self::defaultHeader();
        $mh = curl_multi_init();
        foreach ($request_struct as $_s) {
            if (!isset($_s['url'])) {
                return null;
            }
            $h = curl_init();
            curl_setopt($h, CURLOPT_URL, $_s['url']);
            curl_setopt($h, CURLOPT_HEADER, $header);
            array_push($handle_list, $h);
            curl_multi_add_handle($mh, $h);
        }
        $running=null;
        do {
            curl_multi_exec($mh,$running);
        } while($running > 0);
        foreach ($handle_list as $_h) {
            $_h = array_pop();
            curl_multi_remove_handle($mh, $_h);
        }
        curl_multi_close($mh);
    }
    
}
