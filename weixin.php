<?php
/**
 * 
 * @authors Your Name (you@example.org)
 * @date    2017-05-08 10:57:59
 * @version $Id$
 */
	define("TOKEN","weixin");
	require 'weixin_api.php';
	//获取接口调用凭证
	// $appid = "wx5104563ab1ceb261";
	// $appsecret = "136cd1efbf8b537425e2b82819881e7b";
	$echostr = $_GET['echostr'];//随机字符串

	$indexModel = new IndexModel();

	if(isset($_GET['echostr']))
	{
		$indexModel->valid();
	}
	else
	{
		//$indexModel->menu_delete();
		//$indexModel->menu_create();
		$indexModel->responseMsg();

	}