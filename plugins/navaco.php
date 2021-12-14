<?
@session_start();
/*
  Virtual Freer
  http://freer.ir/virtual

  Copyright (c) 2011 Navaco, navaco.ir

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License v3 (http://www.gnu.org/licenses/gpl-3.0.html)
  as published by the Free Software Foundation.
*/
//-- اطلاعات کلی پلاگین
$pluginData[navaco][type] 					= 'payment';
$pluginData[navaco][name] 					= 'ناواکو';
$pluginData[navaco][uniq] 					= 'navaco';
$pluginData[navaco][description] 				= 'مخصوص پرداخت با دروازه پرداخت <a href="https://navaco.ir">ناواکو</a>';
$pluginData[navaco][author][name] 			= 'Navaco';
$pluginData[navaco][author][url] 				= 'http://navaco.ir';
$pluginData[navaco][author][email] 			= 'info@navaco.ir';

//-- فیلدهای تنظیمات پلاگین
$pluginData[navaco][field][config][1][title] 	= 'مرچنت';
$pluginData[navaco][field][config][1][name] 	= 'merchant';
$pluginData[navaco][field][config][2][title] 	= 'نام کاربری';
$pluginData[navaco][field][config][2][name] 	= 'username';
$pluginData[navaco][field][config][3][title] 	= 'گذرواژه';
$pluginData[navaco][field][config][3][name] 	= 'password';
$pluginData[navaco][field][config][4][title] 	= 'عنوان خرید';
$pluginData[navaco][field][config][4][name] 	= 'title';

function callCurl($postField,$action){
    $url = "http://79.174.161.132:8181/nvcservice/Api/v2/";
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url.$action);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type' => 'application/json'));
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postField));
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Accept: application/json"));
    $curl_exec = curl_exec($curl);
    curl_close($curl);
    return json_decode($curl_exec);
}

//-- تابع انتقال به دروازه پرداخت
function gateway__navaco($data)
{
	global $config,$db,$smarty;

	$merchantID 	= trim($data[merchant]);
	$username 	= trim($data[username]);
	$password 	= trim($data[password]);

	$amount 		= round($data[amount]/10);
	$invoice_id		= $data[invoice_id];
	$callBackUrl 	= $data[callback];
    $_SESSION["invoice_id"] = $data[invoice_id];
    $postField = [
        "CARDACCEPTORCODE"=>$merchantID,
        "USERNAME"=>$username,
        "USERPASSWORD"=>$password,
        "PAYMENTID"=>$data[invoice_id],
        "AMOUNT"=>$amount,
        "CALLBACKURL"=>$callBackUrl,
    ];

    $result = callCurl($postField,"PayRequest");

	if (isset($result->ActionCode) && (int)$result->ActionCode == 0)
	{
		@header("location: {$result->RedirectUrl}");
		exit;
		
	} else {
		$errStatus 		= (isset($result->ActionCode) && $result->ActionCode != "") ? $result->ActionCode : "Error connecting to web service";
		$data[title] 	= 'خطای سیستم';
		$data[message] 	= '<font color="red">در اتصال به درگاه ناواکو مشکلی به وجود آمد٬ لطفا از درگاه سایر بانک‌ها استفاده نمایید.</font>'.$errStatus.'<br /><a href="index.php" class="button">بازگشت</a>';
		$query			= 'SELECT * FROM `config` WHERE `config_id` = "1" LIMIT 1';
		$conf			= $db->fetch($query);
		$smarty->assign('config', $conf);
		$smarty->assign('data', $data);
		$smarty->display('message.tpl');
	}
}

//-- تابع بررسی وضعیت پرداخت
function callback__navaco($data)
{
	global $db, $get;

	if (isset($_POST['Data']))
	{

		$merchantID = $data[merchant];
		$username = $data[username];
		$password = $data[password];
		$sql 		= 'SELECT * FROM `payment` WHERE `payment_rand` = "'.$_SESSION["invoice_id"].'" LIMIT 1;';
		$payment 	= $db->fetch($sql);
		$amount		= round($payment[payment_amount]/10);

        $dataRes = $_POST["Data"];
        $dataRes = json_decode($dataRes);

        $postField = [
            "CARDACCEPTORCODE"=>$merchantID,
            "USERNAME"=>$username,
            "USERPASSWORD"=>$password,
            "PAYMENTID"=>$_SESSION["invoice_id"],
            "RRN"=>$dataRes->RRN,
        ];
        $result = callCurl($postField,"Confirm");

		if ($payment[payment_status] == 1)
		{
			if (isset($result->ActionCode) && (int)$result->ActionCode == 0)
			{
				//-- آماده کردن خروجی
				$output[status]		= 1;
				$output[res_num]	= $data[invoice_id];
				$output[ref_num]	= $result->RRN;
				$output[payment_id]	= $payment[payment_id];
			} else {
				$errStatus 		= (isset($result->ActionCode) && $result->ActionCode != "") ? $result->ActionCode : "Error connecting to web service";
				//-- در تایید پرداخت مشکلی به‌وجود آمده است‌
				$output[status]	= 0;
				$output[message]= 'پرداخت توسط ناواکو تایید نشد‌ : '.$errStatus;
			}
		} else {
			//-- قبلا پرداخت شده است‌
			$output[status]	= 0;
			$output[message]= 'سفارش قبلا پرداخت شده است.';
		}
	} else {
		//-- شماره یکتا اشتباه است
		$output[status]	= 0;
		$output[message]= 'تراکنش لغو شد';
	}

	return $output;
}
