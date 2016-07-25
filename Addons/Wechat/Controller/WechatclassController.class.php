<?php
namespace Addons\Wechat\Controller;
use Admin\Controller\AddonsController; 

class WechatclassController extends AddonsController{
	/**
	 * 微信推送过来的数据或响应数据
	 * @var array
	 */
	private $data = array();
	
	/**
	 * 主动发送的数据
	 * @var array
	 */
	private $send = array();
	private $conf = array();

	/**
	 * 构造方法，用于实例化微信SDK
	 * @param string $token 微信开放平台设置的TOKEN
	 */
	public function __construct(){
		$this->conf = S('WECHATADDONS_CONF');
		if(!in_array(I('_action'), array('update_cache','getgroups'))){
			(I('get.ukey') ==  $this->conf['ukey']) || exit;
		}
	}
	/**
	 * 更新插件缓存
	 */
	public function update_cache(){
		/*更新插件缓存*/
		$addon_class = get_addon_class('Wechat');
		$data  =   new $addon_class;
        if(class_exists($addon_class)){
        	$addon['saveconfig_cache_list'] = $data->saveconfig_cache_list;
			if(is_array($addon['saveconfig_cache_list'])){
				foreach ($addon['saveconfig_cache_list'] as $_v) {
					S($_v,null);
				}
			}else{
				S($addon['saveconfig_cache_list'],null);
			}
        }
        //将配置写入缓存
		S('WECHATADDONS_CONF',get_addon_config('Wechat'),0);
		$this->setMenu($this->jsencode($this->getconf('menu')));
        return true;
	}
	/**
	 * 获取用户分组
	 * @param  string $type 返回类型
	 * @return string/array  返回的结果；
	 */
	public function getgroups(){
		$type = I('type',0,'intval');
		S('WECHATADDONS_GROUPS',null);
		$sgroups = S('WECHATADDONS_GROUPS');
		if ($sgroups == false) {
			$access_token = $this->getToken();
			$url = "https://api.weixin.qq.com/cgi-bin/groups/get?access_token={$access_token}";
			$_groups = json_decode($this->http($url, $data), true);
			if (empty($_groups[errcode])) {
				$groups[0] = $_groups['groups'];
				$groups[1] = 0;
				foreach ($_groups['groups'] as $key => $value) {
					
					$groups[1] += $value['count'];
				}
				S('WECHATADDONS_GROUPS',$groups,1000); // 放进缓存
			}else{
				S('WECHATADDONS_GROUPS',null);
			}
		} else {
			$groups = $sgroups;
		}
		exit($this->jsencode($groups[$type]));
	}

	/**
	 * 获取微信推送的数据
	 * @return array 转换为数组后的数据
	 */
	public function request(){
		$this->auth() || exit;
		if(IS_GET){
			exit($_GET['echostr']);
		} else {
			$xmls = $xml = file_get_contents("php://input");
			$xml = new \SimpleXMLElement($xml);
			$xml || exit;
		
			foreach ($xml as $key => $value) {
				$this->data[$key] = strval($value);
			}
		}
		if(empty($this->data['errcode'])){
			$data = array(
				'type' => $this->data ['MsgType'],
				'content' => ($this->data ['MsgType'] == 'event')?$this->data ['Event']:$this->data ['Content'],
				'user' => $this->data ['FromUserName'],
				'time' => NOW_TIME,
				'msgid' => $this->data ['MsgId']
			);
			D('Addons://Wechat/Wechat_message')->data($data)->add();
		}
       	return $this->data;
	}
	/**
	 * * 响应微信发送的信息（自动回复）
	 * @param  string $to      接收用户名
	 * @param  string $from    发送者用户名
	 * @param  array  $content 回复信息，文本信息为string类型
	 * @param  string $type    消息类型
	 * @param  string $flag    是否新标刚接受到的信息
	 * @return string          XML字符串
	 */
	public function response($content, $type = 'text', $flag = 0){
		/* 基础数据 */
		$this->data = array(
			'ToUserName'   => $this->data['FromUserName'],
			'FromUserName' => $this->data['ToUserName'],
			'CreateTime'   => NOW_TIME,
			'MsgType'      => $type,
		);

		/* 添加类型数据 */
		$this->$type($content);

		/* 添加状态 */
		$this->data['FuncFlag'] = $flag;
		$data = array(
			'type' => $this->data['MsgType'],
			'content' => $content,
			'user' => $this->data ['ToUserName'],
			'time' => $this->data ['CreateTime']
		);
		D('Addons://Wechat/Wechat_message')->data($data)->add();
		/* 转换数据为XML */
		$xml = new \SimpleXMLElement('<xml></xml>');
		$this->data2xml($xml, $this->data);
		exit($xml->asXML());
	}
	/**
	 * * 主动发送消息
	 *
	 * @param string $content   内容
	 * @param string $openid   	发送者用户名
	 * @param string $type   	类型
	 * @return array 返回的信息
	 */
	
	public function sendMsg($content, $openid = '', $type = 'text') {
		/* 基础数据 */
		$this->send ['touser'] = $openid;
		$this->send ['msgtype'] = $type;
		
		/* 添加类型数据 */
		$sendtype = 'send' . $type;
		$this->$sendtype ( $content );
			
		/* 发送 */
		$sendjson = $this->jsencode ( $this->send );
		$restr = $this->send ( $sendjson );
		return $restr;
	}
	/**
	 * 发送文本消息
	 * 
	 * @param string $content
	 *        	要发送的信息
	 */
	private function sendtext($content) {
		$this->send ['text'] = array (
				'content' => $content 
		);
	}
	
	/**
	 * 发送图片消息
	 * 
	 * @param string $content
	 *        	要发送的信息
	 */
	private function sendimage($content) {
		$this->send ['image'] = array (
				'media_id' => $content 
		);
	}

	/**
	 * 发送视频消息
	 * @param  string $content 要发送的信息
	 */
	private function sendvideo($video){
		list (
			$video ['media_id'],
			$video ['title'],
			$video ['description']
		) = $video;
		
		$this->send ['video'] = $video;
	}
	
	/**
	 * 发送语音消息
	 * 
	 * @param string $content
	 *        	要发送的信息
	 */
	private function sendvoice($content) {
		$this->send ['voice'] = array (
				'media_id' => $content 
		);
	}
	
	/**
	 * 发送音乐消息
	 * 
	 * @param string $content
	 *        	要发送的信息
	 */
	private function sendmusic($music) {
		list ( 
			$music ['title'], 
			$music ['description'], 
			$music ['musicurl'], 
			$music ['hqmusicurl'], 
			$music ['thumb_media_id']
		) = $music;
		
		$this->send ['music'] = $music;
	}
	
	/**
	 * 发送图文消息
	 * @param  string $news 要回复的图文内容
	 */
	private function sendnews($news){
		$articles = array();
		foreach ($news as $key => $value) {
			list(
					$articles[$key]['title'],
					$articles[$key]['description'],
					$articles[$key]['url'],
					$articles[$key]['picurl']
			) = $value;
			if($key >= 9) { break; } //最多只允许10调新闻
		}
		$this->send['articles'] = $articles;
	}
	
	
	/**
	 * * 获取微信用户的基本资料
	 * 
	 * @param string $openid   	发送者用户名
	 * @return array 用户资料
	 */
	public function user($openid = '') {
		if ($openid) {
			header ( "Content-type: text/html; charset=utf-8" );
			$url = 'https://api.weixin.qq.com/cgi-bin/user/info';
			$params = array ();
			$params ['access_token'] = $this->getToken ();
			$params ['openid'] = $openid;
			$httpstr = $this->http( $url, $params );
			//$this->response($httpstr, 'text');
			$harr = json_decode ( $httpstr, true );
			return $harr;
		} else {
			return false;
		}
	}
	/**
	 * 生成菜单
	 * @param  string $data 菜单的str
	 * @return string  返回的结果；
	 */
	public function setMenu($data = NULL){
		$smenu = S('WECHATADDONS_MENU');
		if ($smenu == false) {
			$access_token = $this->getToken();
			$this->delMenu($access_token);
			$url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token={$access_token}";
			$menustr = $this->http($url, $data, 'POST', array("Content-type: text/html; charset=utf-8"), true);
			$_url = "https://api.weixin.qq.com/cgi-bin/menu/get?access_token={$access_token}";
			$_menustr = $this->http($_url, $data);
			S('WECHATADDONS_MENU',json_decode ($_menustr, true ),15000); // 放进缓存
		} else {
			$menustr = $smenu;
		}
		print_r(S('WECHATADDONS_MENU'));
		return $menustr;
	}
	/**
	 * 查询菜单
	 * @return string  返回的结果；
	 */
	public function getMenu(){
		$access_token = $this->getToken();
		$url = "https://api.weixin.qq.com/cgi-bin/menu/get?access_token={$access_token}";
		$menustr = $this->http($url, $data);
		return $menustr;
	}
	/**
	 * 删除菜单
	 * @return string  返回的结果；
	 */
	public function delMenu($token){
		$access_token = empty($token)?$this->getToken():$token;
		$url = "https://api.weixin.qq.com/cgi-bin/menu/delete?access_token={$access_token}";
		$menustr = $this->http($url, $data);
		return $menustr;
	}
	/**
	 * 回复文本信息
	 * @param  string $content 要回复的信息
	 */
	private function text($content){
		$this->data['Content'] = $content;
	}

	/**
	 * 回复音乐信息
	 * @param  string $content 要回复的音乐
	 */
	private function music($music){
		list(
			$music['Title'], 
			$music['Description'], 
			$music['MusicUrl'], 
			$music['HQMusicUrl']
		) = $music;
		$this->data['Music'] = $music;
	}

	/**
	 * 回复图文信息
	 * @param  string $news 要回复的图文内容
	 */
	private function news($news){
		$articles = array();
		foreach ($news as $key => $value) {
			list(
				$articles[$key]['Title'],
				$articles[$key]['Description'],
				$articles[$key]['PicUrl'],
				$articles[$key]['Url']
			) = $value;
			if($key >= 9) { break; } //最多只允许10调新闻
		}
		$this->data['ArticleCount'] = count($articles);
		$this->data['Articles'] = $articles;
	}
	/**
	 * 主动发送的信息
	 * @param  string $data    json数据
	 * @return string          微信返回信息
	 */
	private function send($data = NULL) {
		$access_token = $this->getToken ();
		$url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$access_token}";
		$restr = $this->http ( $url, $data, 'POST', array ( "Content-type: text/html; charset=utf-8" ), true );
		return $restr;
	}

	/**
     * 数据XML编码
     * @param  object $xml  XML对象
     * @param  mixed  $data 数据
     * @param  string $item 数字索引时的节点名称
     * @return string
     */
    private function data2xml($xml, $data, $item = 'item') {
        foreach ($data as $key => $value) {
            /* 指定默认的数字key */
            is_numeric($key) && $key = $item;

            /* 添加子元素 */
            if(is_array($value) || is_object($value)){
                $child = $xml->addChild($key);
                $this->data2xml($child, $value, $item);
            } else {
            	if(is_numeric($value)){
            		$child = $xml->addChild($key, $value);
            	} else {
            		$child = $xml->addChild($key);
	                $node  = dom_import_simplexml($child);
				    $node->appendChild($node->ownerDocument->createCDATASection($value));
            	}
            }
        }
    }

    /**
	 * 对数据进行签名认证，确保是微信发送的数据
	 * @param  string $token 微信开放平台设置的TOKEN
	 * @return boolean       true-签名正确，false-签名错误
	 */
	private function auth(){
		/* 获取数据 */
		$data = array($_GET['timestamp'], $_GET['nonce'], $this->conf['token']);
		$sign = $_GET['signature'];
		
		/* 对数据进行字典排序 */
		sort($data);

		/* 生成签名 */
		$signature = sha1(implode($data));
		if( $signature == $sign){
			return true;
		}else{
			return false;
		}	
	}
	/**
	 * 获取保存的accesstoken
	 */
	private function getToken() {
		$stoken = S('WECHATADDONS_TOKEN');
		if ($stoken == false) {
			$accesstoken = $this->getAcessToken(); // 去微信获取最新ACCESS_TOKEN
			S('WECHATADDONS_TOKEN',$accesstoken,5000); // 放进缓存
		} else {
			$accesstoken = $stoken;
		}
		return $accesstoken;
	}
	
	/**
	 * 重新从微信获取accesstoken
	 */
	private function getAcessToken() {
		$token = $this->conf['token'];
		$appid = $this->conf['appid'];
		$appsecret = $this->conf['appsecret'];
		$url = 'https://api.weixin.qq.com/cgi-bin/token';
		$params = array ();
		$params ['grant_type'] = 'client_credential';
		$params ['appid'] = $appid;
		$params ['secret'] = $appsecret;
		$httpstr = $this->http ( $url, $params );
		$harr = json_decode ( $httpstr, true );
		return $harr ['access_token'];
	}
///////////////////////////////////////////////////////////////////////////////////////////////////
	/**
	 * 发送HTTP请求方法，目前只支持CURL发送请求
	 * @param  string $url    请求URL
	 * @param  array  $params 请求参数
	 * @param  string $method 请求方法GET/POST
	 * @return array  $data   响应数据
	 */
	private function http($url, $params, $method = 'GET', $header = array(), $multi = false){
		$opts = array(
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_HTTPHEADER     => $header
		);
	
		/* 根据请求类型设置特定参数 */
		switch(strtoupper($method)){
			case 'GET':
				$param = is_array($params)?'?'.http_build_query($params):'';
				$opts[CURLOPT_URL] = $url . $param;
				break;
			case 'POST':
				//判断是否传输文件
				//$params = $multi ? $params : http_build_query($params);
				$opts[CURLOPT_URL] = $url;
				$opts[CURLOPT_POST] = 1;
				$opts[CURLOPT_POSTFIELDS] = $params;
				break;
			default:
				throw new \Think\ThinkException('不支持的请求方式！');
		}
	
		/* 初始化并执行curl请求 */
		$ch = curl_init();
		curl_setopt_array($ch, $opts);
		$data  = curl_exec($ch);
		$error = curl_error($ch);
		curl_close($ch);
		if($error) throw new \Think\ThinkException('请求发生错误：' . $error);
		return  $data;
	}
	/**
	 * 不转义中文字符和\/的 json 编码方法
	 * @param array $arr 待编码数组
	 * @return string
	 */
	public function jsencode($arr) {
		$str = str_replace ( "\\/", "/", json_encode ( $arr ) );
		$search = "#\\\u([0-9a-f]+)#ie";
		
		if (strpos ( strtoupper(PHP_OS), 'WIN' ) === false) {
			$replace = "iconv('UCS-2BE', 'UTF-8', pack('H4', '\\1'))";//LINUX
		} else {
			$replace = "iconv('UCS-2', 'UTF-8', pack('H4', '\\1'))";//WINDOWS
		}
		
		return preg_replace ( $search, $replace, $str );
	}
	public function getconf($str) {
		$d = array();
		if(!empty($str)){
			$d = explode("/",$str);
			
			$s = $this->conf;
			
			$i = 0;
			do
			{
				$s = $s[$d[$i]];
				$i++;
			}
			while (is_array($s) && $d[$i]);
			return $s;
		}else{
			return '';
		}
		
		
	}
}