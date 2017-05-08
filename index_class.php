<?php
/**
 * 
 * @authors Your Name (you@example.org)
 * @date    2017-05-08 10:45:49
 * @version $Id$
 */
	define("TOKEN","weixin");
	$echostr = $_GET['echostr'];//随机字符串

	$indexModel = new IndexModel();
	$indexModel->valid();

class IndexModel {

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
	private function checkSignature(){

		$signature = $_GET['signature'];//微信加密签名
		$timestamp = $_GET['timestamp'];//时间戳
		$nonce 	   = $_GET['nonce'];//随机数
		//2、加密/校验
		// 1. 将token、timestamp、nonce三个参数进行字典序排序；
		$tmpArr = array(TOKEN,$timestamp,$nonce);
		 sort($tmpArr,SORT_STRING);

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
}//end class
	