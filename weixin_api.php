<?php
/**
 * 
 * @authors Your Name (you@example.org)
 * @date    2017-05-08 10:46:30
 * @version $Id$
 */

class IndexModel {

	private $appid;
	private $appsecret;
    
    function __construct($appid='wx5104563ab1ceb261',$appsecret='136cd1efbf8b537425e2b82819881e7b'){
    	$this->appid     = $appid;
    	$this->appsecret = $appsecret;
    }
    //验证消息
    public function valid(){
		if($this->checkSignature())
		{
			echo $_GET['echostr'];
		}
		else
		{
			echo "Error";
		}
	}//end valid
	//检验微信加密签名Signature
	private function checkSignature(){

		$signature = $_GET['signature'];//微信加密签名
		$timestamp = $_GET['timestamp'];//时间戳
		$nonce 	   = $_GET['nonce'];//随机数
		//2、加密/校验
		// 1. 将token、timestamp、nonce三个参数进行字典序排序；
		$tmpArr = array(TOKEN,$timestamp,$nonce);
		 sort($tmpArr,SORT_STRING);
		// sort($tmpArr);

		// 2. 将三个参数字符串拼接成一个字符串进行sha1加密；
		$tmpStr = implode($tmpArr);
		$tmpStr = sha1($tmpStr);

		// 3. 开发者获得加密后的字符串与signature对比。
		if($tmpStr == $signature)
		{
			return true;
		}
		else
		{
			return false;
		}
	}//end checksignature
	//响应消息
	//responseMsg()
	public function responseMsg(){
		//1、获取到微信推送过来post数据（接收XML数据包,）
		$postArr = $GLOBALS['HTTP_RAW_POST_DATA'];//注意：这个需要设置成全局变量

		//2、处理XML数据包,并设置回复类型和内容
		$xmlObj = simplexml_load_string($postArr,"SimpleXMLElement",LIBXML_NOCDATA);

		$toUserName = $xmlObj->ToUserName; //获取开发者微信号
		$fromUserName = $xmlObj->FromUserName; //获取用户的OpenID
		$msgType = $xmlObj->MsgType; //消息的类型
		//根据消息类型来进行业务处理
		switch ($msgType) {
			case 'event':
				//接收事件推送
				echo $this->receiveEvent($xmlObj);
				break;
			case 'text':
				//接收文本消息
				echo $this->receiveText($xmlObj);
				break;
			case 'image':
				//接收图片消息
				echo $this->receiveImage($xmlObj);
				break;
			default:
				break;
		}
	}//end responseMsg
	//接收事件推送
	//receiveEvent($obj)
	public function receiveEvent($xmlobj){
		switch ($xmlobj->Event) {
			//接收关注事件
			case 'subscribe':
				$jsoninfo = $this ->getUserInfo($xmlobj);
				$nickname = $jsoninfo["nickname"];//昵称
				$logourl  = $jsoninfo['headimgurl'];//头像
				$openid   = $jsoninfo['openid'];
				$sex	  = ($jsoninfo["sex"]==1)?"男":(($jsoninfo["sex"]==2)?"女":"未知");
				$country  = $jsoninfo["country"];
				$province = $jsoninfo["province"];
				$city 	  = $jsoninfo["city"];
				$region   = $country.$province.$city;
				$language = ($jsoninfo["language"] == "zh_CN")?"简体中文":"非简体中文";
				$subscribe_time = date('Y-m-d',$jsoninfo["subscribe_time"]);
				$contents = "";
				$contents = "您好，".$nickname."\n"."您的logo是：".$logourl."\n"."您的openID是：".$openid."\n"."性别：".$sex."\n"."地区：".$country." ".$province." ".$city."\n"."语言：".$language."\n"."关注：".$subscribe_time;
				
				require './conn/conn.php';
				$sql = "INSERT INTO userinfo(nickname,openid,sex,region,subscribe_time,logourl,vip) VALUES('$nickname','$openid','$sex','$region','$subscribe_time','$logourl',1)";
				$mysqli->query($sql);
				$mysqli->close();
				return $this->responseTxt($xmlobj,$contents);
				break;
			//接收取消关注事件
			case 'unsubscribe':
				//账号的解绑
				$jsoninfo = $this ->getUserInfo($xmlobj);
				$openid   = $jsoninfo['openid'];
				require './conn/conn.php';
				$sql = "DELETE FROM userinfo WHERE openid=?;";
				$stmt = $mysqli->prepare($sql);  
				$stmt->bind_param('s', $openid);  
				$stmt->execute();   
				$stmt->close();  
				break;
			//点击事件
			case 'CLICK':
				switch($xmlobj->EventKey)
				{					
					case 'keji':
					//$QueryType = 'keji';
					return $this->getNewsFromApi($xmlobj,'keji');
					break;
					case 'shishang':
					//$QueryType = 'shishang';
					return $this->getNewsFromApi($xmlobj,'shishang');
					break;
					case 'yule':
					//$QueryType = 'yule';
					return $this->getNewsFromApi($xmlobj,'yule');
					break;
				}
				break;
			default:
				# code...
				break;
			}			
	}//end receiveEvent

	//接收文本消息，电话号码
	public function receiveText($xmlobj){
		
		$mobile = trim($xmlobj->Content); //获取文本消息的内容
		if (preg_match('#^13[\d]{9}$|^14[5,7]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0,6,7,8]{1}\d{8}$|^18[\d]{9}$#', $mobile)) {
			$jsoninfo = $this ->getUserInfo($xmlobj);
			$openid   = $jsoninfo['openid'];
			require './conn/conn.php';
			$sql = "UPDATE userinfo SET phone=? WHERE openid=?;";
			$stmt = $mysqli->prepare($sql);
			$stmt->bind_param('ss',$mobile,$openid);  
			$stmt->execute();   
			$stmt->close();  
			$contents = "已经收到您的电话号码!";
			return $this->responseTxt($xmlobj,$contents);
        	
   	    }else{
   	    	$contents = "您输入的电话号码有误!";
			return $this->responseTxt($xmlobj,$contents);
   	    }
	}//end receiveText

	//回复单文本的封装函数,也可以作为回复关注事件推送的函数
	public function responseTxt($xmlObj,$contents){
		$template = "<xml>
					<ToUserName><![CDATA[%s]]></ToUserName>
					<FromUserName><![CDATA[%s]]></FromUserName>
					<CreateTime>%s</CreateTime>
					<MsgType><![CDATA[text]]></MsgType>
					<Content><![CDATA[%s]]></Content>
					</xml>";
		$fromUser = $xmlObj->ToUserName;//ToUserName开发者微信号
		$toUser   = $xmlObj->FromUserName;//FromUserName	发送方帐号（一个OpenID）
		$time     = time();
		//$msgType  =  'text';
		//$content  = "<a href='http://www.baidu.com'>百度</a>";
		$info     = sprintf($template,$toUser,$fromUser,$time,$contents);
		return $info;
	}//responseTxt end

	//接收图片消息
	public function receiveImage($xmlobj){
		$picUrl = $xmlobj->PicUrl;//获取图片的URL
		//$mediaId =$xmlobj->MediaId;//获取图片消息媒体id
		$jsoninfo = $this ->getUserInfo($xmlobj);
		$openid   = $jsoninfo['openid'];
		require './conn/conn.php';
		$sql = "UPDATE userinfo SET imageurl=? WHERE openid=?;";
		$stmt = $mysqli->prepare($sql);
		$stmt->bind_param('ss',$picUrl,$openid);  
		$stmt->execute();   
		$stmt->close();  
		//$picArr = array('picUrl'=>$picUrl,'mediaId'=>$mediaId);
		$contents = "已经收到您发送的图片!";
		return $this->responseTxt($xmlobj,$contents);
		//return $this->replyImage($xmlobj,$picArr);
		// return $this->replyText($obj,$mediaId);
	}//end receiveImage
	//回复图片消息
	public function replyImage($xmlobj,$array){
		//回复图片消息
		$replyImageMsg = "<xml>
						<ToUserName><![CDATA[%s]]></ToUserName>
						<FromUserName><![CDATA[%s]]></FromUserName>
						<CreateTime>%s</CreateTime>
						<MsgType><![CDATA[image]]></MsgType>
						<Image>
							<MediaId><![CDATA[%s]]></MediaId>
						</Image>
						</xml>";
		$fromUser = $xmlObj->ToUserName;//ToUserName开发者微信号
		$toUser   = $xmlObj->FromUserName;//FromUserName	
		return sprintf($replyImageMsg,$toUser,$fromUser,time(),$array['mediaId']);
	}//end replyImage
	//回复多图文的函数封装
	public function responseImage_Txt($xmlObj,$arr){
		$toUser   = $xmlObj->FromUserName;
		$fromUser = $xmlObj->ToUserName;
		
		//$time   = time();
		$template = "<xml>
				<ToUserName><![CDATA[%s]]></ToUserName>
				<FromUserName><![CDATA[%s]]></FromUserName>
				<CreateTime>%s</CreateTime>
				<MsgType><![CDATA[%s]]></MsgType>
				<ArticleCount>".count($arr)."</ArticleCount>
				<Articles>";
		foreach($arr as $k=>$v){
			$template .="<item>
						<Title><![CDATA[".$v['title']."]]></Title> 
						<Description><![CDATA[".$v['description']."]]></Description>
						<PicUrl><![CDATA[".$v['picUrl']."]]></PicUrl>
						<Url><![CDATA[".$v['url']."]]></Url>
						</item>";
		}
		$template .="</Articles>
						</xml> ";
		//注意模板中的中括号 不能少 也不能多
		return sprintf($template, $toUser, $fromUser, time(), 'news');
	}//responseImage_Txt end

	//获取用户信息
	public function getUserInfo($postObj)
	{
		$access_token = $this->getWxAccessToken();
		//$openid = "oaGl9wJIq5Gz67X504HRNvdJQBBA";
		$openid = $postObj->FromUserName;////获取发送对象账号
		$url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$access_token&openid=$openid&lang=zh_CN";
		$res =$this->http_curl($url,'get','json');
		return $res;
	}//end getUserInfo
	/*优化curl函数，使其更加完备
	*$curl   接口URL	  string
	*$type  请求类型   post/get
	*$res   返回数据类型 string
	*$arr   post请求参数 String
	*/
	public function http_curl($url,$type ='get', $res = 'json',$arr= ''){
		//curl 获取工具
		// 1.初始化curl
		$ch = curl_init();
		// 2.设置curl的参数

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		//curl_setopt($ch,CURLOPT_HEADER,0);
		if ($type =='post') {
			//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			//curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);
			//curl_setopt($ch,CURLOPT_URL,1);
			curl_setopt($ch,CURLOPT_POST,1);//模拟POST请求
			curl_setopt($ch,CURLOPT_POSTFIELDS,$arr);
		}
		// 3.采集
		$output = curl_exec($ch);
		
		// 4.关闭
		curl_close($ch);
		return json_decode($output,true);//返回数组结果
		/*if($res == 'json'){
			// var_dump($output);
			//echo curl_errno($ch);
			if (curl_errno($ch)) {
				//请求失败，返回错误信息
				return curl_error($ch);
			} else {
				// 请求成功，返回json数组
				//echo "json";
				return json_decode($output,true);
			}
		}*/
	}//end curl
	
	//获取微信accesstoken
	public function getWxAccessToken(){
		// 由于access_token有过期时间，所以将access_token存放到session/cookie中。
		if ($_SESSION['access_token'] && $_SESSION['expire_time']>time()) {
			//如果access_token没有过期
			return $_SESSION['access_token'];
		} else {
			//如果access_token已经过期，需要重新获取access_token
			$appid 		= 'wx5104563ab1ceb261';//测试账号的Id和secret
		 	$appsecret	= '136cd1efbf8b537425e2b82819881e7b';//测试账号
			$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$appid."&secret=".$appsecret;
			$res = $this->http_curl($url,'get','json');
			$access_token = $res['access_token'];
			// 将获取之后的access_token存放到session
			$_SESSION['access_token'] = $access_token;
			$_SESSION['expire_time']  = time()+7000;

			return $access_token;
		}
	}//end getWxAccessToken

///////////////////////////////
	//自定义菜单创建
	public function menu_create(){
		$access_token = $this->getWxAccessToken();
		$url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token=".$access_token;
		$postArr = array(
			"button"=>array(
				array(
					"name"=>urlencode("杭州天气"),
					"type"=>"view",
					 "url"=>"http://m.hao123.com/a/tianqi",
				),//第1个一级菜单
				array(
					"name"=>urlencode("新闻咨询"),
					"sub_button"=>array(
						array(
							"name"=>urlencode("科技"),
							"type"=>"click",
							"key"=>"keji",
							),
						array(
							"name"=>urlencode("时尚"),
							"type"=>"click",
							"key"=>"shishang",
							),	
						array(
							"name"=>urlencode("娱乐"),
							"type"=>"click",
							"key"=>"yule",
							),	
						/*array(
							"name"=>urlencode("军事"),
							"type"=>"click",
							"key"=>"junshi",
							),
						array(
							"name"=>urlencode("国内"),
							"type"=>"click",
							"key"=>"guonei",
							),					*/
						)//第2个一级菜单的两个2级菜单
				),//第2个一级菜单
				array(
					"name"=>urlencode("捷峰电子"),
					"type"=>"view",
					"url"=>"http://www.smartbc.cn/jfdz",
				)//第3个一级菜单
			),
			);
		//echo '<hr/>';
		//var_dump($postArr);
		//echo '<hr/>';
	    $postJson = urldecode(json_encode($postArr));
		//echo '<hr/>';
		$res = $this->http_curl($url,'post','json',$postJson);
		return $res;
		//var_dump($res);
		// return $this->http_curl($url,$post);
	}//end menu_create
	//自定义菜单查询
	public function menu_select(){
		$access_token = $this->getWxAccessToken();
		$url = "https://api.weixin.qq.com/cgi-bin/menu/get?access_token={$access_token}";
		return $this->http_curl($url);
	}//end menu_select
	//自定义菜单删除
	public function menu_delete(){
		$access_token = $this->getWxAccessToken();
		$url = "https://api.weixin.qq.com/cgi-bin/menu/delete?access_token={$access_token}";
		return $this->http_curl($url);
	}//end menu_delete
//////////////////////////

	//回复微信用户的关注事件
	public function responseSubscribe($postObj,$arr){
		$this->responseMsg($postObj,$arr);
	}//responseSubscribe end
	//关于新闻而专门写的类
	public function responseNews($postObj,$arrres){
		$toUser   = $postObj->FromUserName;
		$fromUser = $postObj->ToUserName;
		
		//$time   = time();
		$template = "<xml>
				<ToUserName><![CDATA[%s]]></ToUserName>
				<FromUserName><![CDATA[%s]]></FromUserName>
				<CreateTime>%s</CreateTime>
				<MsgType><![CDATA[%s]]></MsgType>
				<ArticleCount>5</ArticleCount>
				<Articles>";
		for($i=0;$i<8;$i++)
		{
			$template .="<item>
						<Title><![CDATA[".$arrres['result']['data'][$i]['title']."]]></Title> 
						<Description><![CDATA[".$arrres['result']['data'][$i]['date']."]]></Description>
						<PicUrl><![CDATA[".$arrres['result']['data'][$i]['thumbnail_pic_s']."]]></PicUrl>
						<Url><![CDATA[".$arrres['result']['data'][$i]['url']."]]></Url>
						</item>";
		}
		$template .="</Articles>
					</xml> ";
		//注意模板中的中括号 不能少 也不能多
		return sprintf($template, $toUser, $fromUser, time(), 'news');
	}//responseNews end*/
	//今日头条获取新闻
	public function getNewsFromApi($postObj,$QueryType = 'top'){
		$host = "http://toutiao-ali.juheapi.com";
		$path = "/toutiao/index";
		$method = "GET";
		$appcode = "5ab3c25815ee460783a50fb539c7fe49";
		$headers = array();
		array_push($headers, "Authorization:APPCODE " . $appcode);
		//$QueryType =$postObj->Content;
		$querys = "type=".$QueryType;
		//$querys = "type=caijing";
		//类型,,top(头条，默认),shehui(社会),guonei(国内),guoji(国际),yule(娱乐),tiyu(体育)junshi(军事),keji(科技),caijing(财经),shishang(时尚)
		$bodys = "";
		$url = $host . $path . "?" . $querys;

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_FAILONERROR, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, false);
		if (1 == strpos("$".$host, "https://"))
		{
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		}
		$res = curl_exec($curl);
		$arrres = json_decode($res,true);
		return $this->responseNews($postObj,$arrres);
	} //end getNewsFromApi
	//获取天气情况的函数
	public function getNewsFromTianQi($postObj){
		//接口基本信息配置
		$appkey = 'ec1df8d77368cd447c455e236a719f24'; //您申请的天气查询appkey
		$weather = new weather($appkey);
		//$cityname = urldecode($postObj->Content);
		$cityname = '杭州';
		//$contents = $cityname;
		$cityWeatherResult = $weather->getWeather($cityname);
		//if($cityWeatherResult['error_code'] == 0){    //以下可根据实际业务需求，自行改写
		//////////////////////////////////////////////////////////////////////
		$data = $cityWeatherResult['result'];
		$content_dangqian ="===[".$cityname."]天气实况==="."\n";
		 $content_data= "温度：".$data['sk']['temp']."℃\n"."风向：".$data['sk']['wind_direction']."    （".$data['sk']['wind_strength']."）"."\n"."湿度：".$data['sk']['humidity']."\n";
	 
		$content_tips =  "===未来几天天气预报==="."\n";
		 foreach($data['future'] as $wkey =>$f){
			$content_date .=  "日期:".$f['date']." ".$f['week']." ".$f['weather']." ".$f['temperature']."\n";
		 }
		$content_tianqi =  "===相关天气指数==="."\n";
		$content_tianqi2 = "穿衣指数：".$data['today']['dressing_index']." , ".$data['today']['dressing_advice']."\n"."紫外线强度：".$data['today']['uv_index']."\n"."洗车指数：".$data['today']['wash_index'];
		$contents = $content_dangqian.$content_data.$content_tips.$content_date.$content_tianqi.$content_tianqi2 ; 
		//实例化responseTxt模板	
		$indexModel = new IndexModel;
		$indexModel->responseTxt($postObj,$contents);
	}//end getNewsFromTianQi
	
}//end class