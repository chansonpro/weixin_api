<?php
/**
 * 
 * @authors Your Name (you@example.org)
 * @date    2017-05-08 10:57:59
 * @version $Id$
 */

	define("TOKEN","weixin");
	require 'weixin_api.php';
	$echostr = $_GET['echostr'];//随机字符串

	$indexModel = new IndexModel();
	//$indexModel->valid();
	

	if(isset($_GET['echostr']))
	{
		$indexModel->valid();
	}
	else
	{
		//$indexModel->responseMsg();
	}