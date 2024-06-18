<?php
require_once __DIR__ . '/vendor/autoload.php';
\LWMIS\Common\ErrorHandler::defineErrorLevel();

if (\LWMIS\Common\MyConfig::IS_TESTING) {
  define('LOCAL_URL', 'http://localhost:4200/');
} else {
  define('LOCAL_URL', '../');
}

$postData = file_get_contents("php://input");//<- to get browser response
parse_str($postData, $res);
$db = new \LWMIS\Common\PostgreDB();
$query_string = '';

try {
  $rv = new \LWMIS\Common\SBIePay_Gateway();
  $data = $rv->decrypt($res['encData']);

  if (count($data) <= 0) {
    throw new Exception("Payment Gateway's encData failed !");
  }

  $selected_years = json_decode($data[6]);
  $merchantOrderNo = $data[0];
  $atrn = $data[1];//sbiPaymentRefNo
  $trn_status = $data[2];
  $amt = $data[3];
  $payment_mode = $data[5];

  $sql = "SELECT a.id,a.firm_id,a.trnx_to,a.trnx_date,a.trnx_no_own,
                 a.trnx_no_gw,a.trnx_amount,a.status,a.status_desc,a.payment_mode
            FROM est.payments AS a
           WHERE a.trnx_no_own = $1
        ORDER BY trnx_date DESC";

  $params = array($merchantOrderNo);
  $db->Query($sql, $params);
  $rows = $db->FetchAll();

  $firm_id = $rows[0]['firm_id'];
  $payment_id = $rows[0]['id'];
  $amount = $rows[0]['trnx_amount'];
  $trnx_date = $rows[0]['trnx_date'];
  $trnx_no_own = $rows[0]['trnx_no_own'];

  if (count($rows) > 0) {
    \LWMIS\Common\SBIePay_Gateway::log_sbi_tran('Info', "Database Accessed", "trnx_no_own $merchantOrderNo found with status {$rows[0]['status']} and will be updated with sbi_ref_no/ATRN: $atrn after double verified.");
    $rv = new \LWMIS\Common\SBIePay_Gateway();
    $dv_result = $rv->doubleVerify($merchantOrderNo, $amt, $atrn);

    $status_desc = $dv_result[8];

    $payment = new \LWMIS\Est\Payment();
    $rpt = new \LWMIS\Est\Receipt();

    $receipt = [
      'payment_method' => 'OLP',
      'payment_id' => $payment_id,
      'trnx_dt' => $trnx_date,
      'trnx_ref_no' => $trnx_no_own,
      'user_id' => (new \LWMIS\Est\Firm())->getUserIdByFirmId($db, $firm_id)[0]['user_id'],
      'status' => $dv_result[2],
      'receipt_amt' => $amount,
      'firm_id' => $firm_id
    ];

    $payment_status = [
      'trnx_no_gw' => $atrn,
      'trnx_no_own' => $merchantOrderNo,
      'payment_id' => $payment_id,
      'trnx_amount' => $amount,
      'firm_id' => $firm_id,
      'trn_status' => $trn_status,
      'status_desc' => $status_desc,
      'payment_mode' => $payment_mode
    ];

//    $db->Begin();
    $query_string = $payment->saveClaimPaymentDetails($db, $dv_result, $payment_status, $payment_id, $atrn, $amount, $receipt);
//    $db->Commit();
  } else {
    throw new Exception("No payment detail is available for the selected transaction no.");
  }

  $pm_status = '';
  if ($dv_result[2] == 'SUCCESS') {
    $pm_status = 'SUCCESS';
  } elseif ($dv_result[2] == 'FAIL') {
    $pm_status = 'FAIL';
  } else {
    $pm_status = 'INITIATED';
  }

  \LWMIS\Common\SBIePay_Gateway::log_sbi_tran($pm_status, 'Payment Response - Decrypted', $data);
} catch (Exception $e) {
  global $data;
  \LWMIS\Common\SBIePay_Gateway::log_sbi_tran('Error', 'Payment Response - Decrypted', $data);
  echo \LWMIS\Common\ErrorHandler::custom($e);
  echo 'Sorry, some error with bank response. You will be redirected to the website & please login to check your payment transaction.';
} finally {
  header('Location: ' . LOCAL_URL . '../#/other/payment-receipt' . $query_string);
  $db->DBClose();
}

