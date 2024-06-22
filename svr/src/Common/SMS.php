<?php
//
//namespace LWMIS\Common;
//
//#region Define Variables
//// http://digimate.airtel.in:15181/BULK_API/SendMessage?loginID=tnega_cwssb&password=tnega@123&senderid=TNGOVT&DLT_TM_ID=1001096933494158&DLT_PE_ID=1301157259712022912&route_id=DLT_GOVT&Unicode=2&camp_name=tnega_u
//
//define('SMS_GW_URL', 'http://digimate.airtel.in:15181/BULK_API/InstantJsonPush');
//define('SMS_GW_KEYWORD', 'DEMO');
//define('SMS_GW_OA', 'TNGOVT');
//define('SMS_GW_CHANNEL', 'SMS');
//define('SMS_GW_CAMPAIGN_NAME', 'tnega_u');
//define('SMS_GW_CIRCLE_NAME', 'DLT_GOVT');
////  define('SMS_GW_CIRCLE_NAME', 'DLT_SERVICE_IMPLICIT');
//define('SMS_GW_ACTION_RESP_URL', ''); // https://bnc.chennaimetrowater.in/svr/sms-response.php
//define('SMS_GW_DLT_TM_ID', '1001096933494158');
//define('SMS_GW_DLT_PE_ID', '1301157259712022912');
//
////-----------------------------------------------------------------------------------------------//
//// 1st Account
//// define('SMS_GW_USER_NAME', 'tnega_cmwssb');
//
//// Login details (1st Account):
//// ID: tnega_cmwssb
//// pwd: tnega@123
//// url: https://digimate.airtel.in/AIRTEL/Controller.6d
////-----------------------------------------------------------------------------------------------//
//// 2nd Account
//// define('SMS_GW_USER_NAME_BULK', 'tnega_gui');
//
//// Login details (2nd Account):
//// ID: tnega_gui
//// pwd: tnega@123
//// url: https://digimate.airtel.in/AIRTEL/Controller.6d
////-----------------------------------------------------------------------------------------------//
//#endregion Define Variables
//
//class SMS
//{
//  private $is_under_development = false;
//  // private $is_check_whitelisted_mobile_nos = true;
//
//  #region Normal SMS
//  // template need to be generated and approved
//  /**
//   * @throws \Exception
//   */
//  function sendOTPForUserForgotPassword(\LWMIS\Common\PostgreDB $db, string $mobile_no, &$payload): void
//  {
//    $otp = $this->generateOTP($mobile_no, $payload);
//    $msg = "TNLWBD: " . $otp . " is the OTP to reset your password. This OTP is valid for 10 minutes only. Please do not share your OTP/Password with anyone - TNGOVT";
//    $dlt_ct_id = '1007174088893955958';//<- template registration no.
////      $gw_user_name = 'tnega_lbwb1';
//    $gw_user_name = 'tnega_tnlwborg';
//    $this->processSMS($db, $mobile_no, $msg, $dlt_ct_id, $gw_user_name, true, 'PWD_RST');
//  }
//
//  // template need to be generated and approved
//
//  private function generateOTP(string $mobile_no, &$payload)
//  {
//    $otp = rand(100000, 999999);
//
//    if (isset($payload['id'])) {
//      $payload['otp'] = $otp;
//    } else {
//      $payload['mobile_no'] = $mobile_no;
//      $payload['otp'] = $otp;
//    }
//
//    $this->log_otp($mobile_no, $otp);
//
//    return $otp;
//  }
//
//  /*
//      #region Bulk SMS
//      function getAllBulkSMSTemplates() {
//        return [
//          [
//            'dlt_ct_id' => '1007879176339550235',
//            'name' => 'Arrear Intimation (General)',
//            'template_preview' => 'Dear <<v1>>, <<v2>>, your Water Tax and Charges of Rs.<<v3>> is in due. You can pay using this link: <<v4>>. Payment can be made at our Area/Depot Offices.\r\nKindly ignore, if already paid-CMWSSB.',
//            'fuction_name' => 'sendGeneralArrearIntimation',
//            'status' => false
//          ],
//          [
//            'dlt_ct_id' => '1007883504991442247',
//            'name' => 'Arrear Intimation (25th)',
//            'template_preview' => 'Dear <<v1>>, <<v2>>, your Water Tax and Charges of Rs. <<v3>> is in due. Kindly pay using this link: <<v4>>. Payment can also be made at our Area/Depot Offices-CMWSSB.',
//            'fuction_name' => 'send25ArrearIntimation',
//            'status' => true
//          ]
//        ];
//      }
//
//      function sendGeneralArrearIntimation(\CMW\Common\PostgreDB $db, string $mobile_no, string $cus_name, string $cmc_no, float $tot_due_amt, string $tiny_url, int $bulk_sms_id) {
//        $msg = "Dear {$cus_name}, {$cmc_no}, your Water Tax and Charges of Rs. {$tot_due_amt} is in due. You can pay using this link: {$tiny_url}. Payment can be made at our Area/Depot Offices.\r\nKindly ignore, if already paid-CMWSSB.";
//        $dlt_ct_id = '1007879176339550235';
//        $gw_user_name = 'tnega_cmwssb'; // tnega_gui
//        return $this->processSMS($db, $mobile_no, $msg, $dlt_ct_id, $gw_user_name, false, $bulk_sms_id);
//      }
//
//      function send25ArrearIntimation(\CMW\Common\PostgreDB $db, string $mobile_no, string $cus_name, string $cmc_no, float $tot_due_amt, string $tiny_url, int $bulk_sms_id) {
//        $msg = "Dear {$cus_name}, {$cmc_no}, your Water Tax and Charges of Rs. {$tot_due_amt} is in due. Kindly pay using this link: {$tiny_url}. Payment can also be made at our Area/Depot Offices-CMWSSB.";
//        // $msg = "Dear , , your Water Tax and Charges of Rs.  is in due. You can pay using this link: . Payment can be made at our Area/Depot Offices.\r\nKindly ignore, if already paid-CMWSSB.";
//        $dlt_ct_id = '1007883504991442247';
//        $gw_user_name = 'tnega_cmwssb'; // tnega_gui
//        return $this->processSMS($db, $mobile_no, $msg, $dlt_ct_id, $gw_user_name, false, $bulk_sms_id);
//      }
//  */
//  #endregion Bulk SMS
//
//  #region Private Functions
//
//  private function log_otp(string $mobile_no, string $otp)
//  {
//    $date = new \DateTime('now', new \DateTimeZone('Asia/Kolkata'));
//    $date_str = $date->format('Y-m-d H:i:s A');
//    $file_path = __DIR__ . "/../../../logs/" . $date->format('Y') . "/" . $date->format('m') . "/";
//    if (!file_exists($file_path)) {
//      mkdir($file_path, 0777, true);
//    }
//    $log_file_name = $file_path . "otp-" . $date->format('Y-m-d') . ".log";
//    $txt = "[{$date_str}] [{$mobile_no}] [{$otp}]" . PHP_EOL;
//    file_put_contents($log_file_name, $txt, FILE_APPEND);
//  }
//
//  /**
//   * @throws \Exception
//   */
//  private function processSMS(\LWMIS\Common\PostgreDB $db, string $mobile_no, string $message, string $dlt_ct_id, string $gw_user_name, bool $is_otp, string $msg_type): void
//  { // = false
//    // START - Development Server
//    if ($this->is_under_development) {
//      $gn = new \LWMIS\Common\GeneralFunctions();
//      $local_domain = $gn->getRequestOrigin();
//      $allowed_domains = $gn->getAllowedDomains();
//      if (in_array(strtolower($local_domain), $allowed_domains, true)) {
//        $log_req = 'Request data: ' . print_r(json_encode(['mobile_no' => $mobile_no, 'message' => $message]), true);
//        $log_res = 'Message not sent since you are testing locally from ' . $local_domain;
//        $this->log($log_req, $log_res);
//        return;
//      }
//    }
//
//    if ((!isset($mobile_no)) || (isset($mobile_no) && strlen($mobile_no) != 10) || (!isset($dlt_ct_id)) || (isset($dlt_ct_id) && strlen($dlt_ct_id) == 0)) {
//      return;
//    }
//    // START DB (save)
//    $str_is_otp = $is_otp ? 't' : 'f';
//    $sql = 'INSERT INTO logs.sms_messages (is_otp, mobile_no, message, dlt_ct_id, gw_user_name, msg_type)
//                 VALUES ($1, $2, $3, $4, $5, $6)
//              RETURNING id';
//    $db->Query($sql, [$str_is_otp, $mobile_no, $message, $dlt_ct_id, $gw_user_name, $msg_type]);
//    $rows = $db->FetchAll();
//
//    if ($is_otp === true && count($rows) > 0) {
//      $id = $rows[0]['id'];
//      try {
//        $sms_req_data = [];
//        $sms_req_data[] = ['unique_id' => $id,
//          'mobile_no' => $mobile_no,
//          'message' => $message,
//          'dlt_ct_id' => $dlt_ct_id,
//          'gw_user_name' => $gw_user_name];
//        $this->sendSingleSMS($sms_req_data);
//        $sql = 'UPDATE logs.sms_messages
//                   SET status = \'S\',
//                       attempted_ts = CURRENT_TIMESTAMP
//                 WHERE id = $1 AND status = \'R\'';
//        $db->Query($sql, [$id]);
//      } catch (\Throwable $th) {
//        $failure_reason = $th->getMessage();
//        $sql = 'UPDATE logs.sms_messages
//                   SET status = \'F\',
//                       attempted_ts = CURRENT_TIMESTAMP,
//                       failure_reason = $2
//                 WHERE id = $1 AND status = \'R\'';
//        $db->Query($sql, [$id, $failure_reason]);
//      }
//    }
//
//  }
//
//  private function log($log_req, $log_res): void
//  {
//    $date = new \DateTime('now', new \DateTimeZone('Asia/Kolkata'));
//    $date_str = $date->format('Y-m-d H:i:s A');
//    $file_path = __DIR__ . "/../../../logs/" . $date->format('Y') . "/" . $date->format('m') . "/";
//    if (!file_exists($file_path)) {
//      mkdir($file_path, 0777, true);
//    }
//    $log_file_name = $file_path . "sms-airtel-" . $date->format('Y-m-d') . ".log";
//    $txt = "[{$date_str}] [{$log_req}] [{$log_res}]" . PHP_EOL;
//    file_put_contents($log_file_name, $txt, FILE_APPEND);
//  }
//
//  private function sendSingleSMS($sms_req_data): void
//  {
//    $sms_json = $this->makeSMSJSON($sms_req_data);
//    $this->sendSMS($sms_json);
//  }
//
//  private function makeSMSJSON(array $data): false|string
//  {
//    // check the following logic, Seems wrong to me.
//    if (!is_array($data) && count($data) > 0) {
//      throw new \Exception('Array of SMS messages not found');
//    }
//
//    $data_set = [];
//    foreach ($data as &$m) {
//      $unique_id = $m['unique_id'] ?? null;
//      $mobile_no = $m['mobile_no'] ?? null;
//      $message = $m['message'] ?? null;
//      $dlt_ct_id = $m['dlt_ct_id'] ?? null;
//      $gw_user_name = $m['gw_user_name'] ?? null;
//      $data_set[] = [
//        "UNIQUE_ID" => $unique_id,
//        "MSISDN" => $mobile_no,
//        "MESSAGE" => $message, // utf8_encode()
//        "DLT_CT_ID" => $dlt_ct_id,
//        "USER_NAME" => $gw_user_name, // SMS_GW_USER_NAME,
//        "OA" => SMS_GW_OA,
//        "CHANNEL" => SMS_GW_CHANNEL,
//        "CAMPAIGN_NAME" => SMS_GW_CAMPAIGN_NAME,
//        "CIRCLE_NAME" => SMS_GW_CIRCLE_NAME,
//        "ACTION_RESP_URL" => SMS_GW_ACTION_RESP_URL,
//        "DLT_TM_ID" => SMS_GW_DLT_TM_ID,
//        "DLT_PE_ID" => SMS_GW_DLT_PE_ID,
//        "LANG_ID" => "0"
//      ];
//    }
//
//    $sms_json = json_encode([
////        "keyword" => SMS_GW_KEYWORD,
//      "keyword" => "DEMO",
//      "timeStamp" => (new \DateTime())->format('dmYHis'),
//      "dataSet" => $data_set
//    ]);
//
////      var_dump($sms_json);
//    return $sms_json;
//
//  }
//
//  private function sendSMS($sms_json): void
//  {
////      var_dump('sms json ',$sms_json);
//    $url = SMS_GW_URL;
//    $ch = curl_init();
//    curl_setopt($ch, CURLOPT_URL, $url);
//    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
//    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
//    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//    curl_setopt($ch, CURLOPT_HEADER, true);
//    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
//    curl_setopt($ch, CURLOPT_POST, true);
//    curl_setopt($ch, CURLOPT_POSTFIELDS, $sms_json);
//
//    $response = curl_exec($ch);
//    curl_close($ch);
//
//    // Log sms message & response
//    $log_req = 'Post data: ' . print_r($sms_json, true);
//    $log_res = 'Response received: nothing';
//
//    if (isset($response)) {
//      if (gettype($response) === 'boolean' && $response === 'true') {
//        $log_res = 'Response received: true';
//      } else {
//        $log_res = 'Response received: ' . print_r($response, true);
//      }
//    }
//    $this->log($log_req, $log_res);
//    return;
//  }
//
//  /**
//   * @throws \Exception
//   */
//  function resendOTPForUserForgotPassword(\LWMIS\Common\PostgreDB $db, string $mobile_no, $otp): void
//  {
//    $msg = "TNLWBD: Your OTP " . $otp . " to reset your password is sent again. Please do not share your OTP with anyone - TNGOVT ";
//    $dlt_ct_id = '1007030700773621462';
//    $gw_user_name = 'tnega_tnlwborg';
//    $this->processSMS($db, $mobile_no, $msg, $dlt_ct_id, $gw_user_name, true, 'PWD_RST');
//  }
//
////  function sendPaymentRemainders($data)
////  {
////    $rows = [];
////    $id = 0;
////    $db = new \LWMIS\Common\PostgreDB();
////    try {
//////        $sql = 'SELECT a.id AS unique_id, a.mobile_no, a.message, a.dlt_ct_id, a.gw_user_name
//////                    FROM logs.sms_messages AS a
//////                   WHERE a.status = \'R\' AND a.is_otp = false AND COALESCE(a.bulk_sms_id, 0) = COALESCE($1, 0)
//////                   ORDER BY a.create_ts
//////                   LIMIT 250';
////
//////      note: Below select query will only work for particular year only.
////      $sql = "select pb.paid_amt != 0 as is_paid,
////                     pb.paid_amt,
////                     pb.bal_amt,
////                     pb.firm_id,
////                     fm.est_mobile,
////                     fm.name,
////                     fm.lwb_reg_no
////                from est.payables as pb
////                    inner join est.firms as fm on pb.firm_id = fm.id
////               where pb.year = extract(year from now())
////                 and pb.paid_amt = 0";
////
////      $db->Query($sql);
////      $rows = $db->FetchAll();
////
////      if (count($rows) > 0) {
////        foreach ($rows as $row) {
////          $sql = "select count(pb.bal_amt = 0) as tt_year_count,
////                         sum(pb.bal_amt)       as tt_bal_amt
////                    from est.payables as pb
////                   where firm_id = $1";
////          $db->Query($sql, [$row['firm_id']]);
////          $tt_val_of_est = $db->FetchAll();
////          $tt_year_count = $tt_val_of_est[0]['tt_year_count'];
////          $tt_bal_amt = $tt_val_of_est[0]['tt_bal_amt'];
////
////          $est_name = $row['name'];
////          $lwb_reg_no = $row['lwb_reg_no'];
////          $bal_amt = $row['bal_amt'];
////          $pmt_link = 'pmt link';
////
////          if (count($tt_year_count) == 1) {
////            $msg = "TNLWBD:Payment due for your " . $est_name . " with LWB reg no: " . $lwb_reg_no . "is Rs:"
////              . $bal_amt . ". Pls pay using " . $pmt_link . ". Kindly ignore if already paid.-TNGOVT";
////          } else {
////            $msg = "TNLWBD:Payment due for " . $est_name .
////              " is pending for " . $tt_year_count . " years with Rs:" . $tt_bal_amt .
////              ". Pls pay using " . $pmt_link . " or ignore if already paid.-TNGOVT";
////          }
////
////          if (strlen($msg) >= 160) {
////            $v = explode(" ", $est_name);
////            $est_name = $v[0] . " " . $v[1];
////
////            if ($tt_year_count == 1) {
////              $msg = "TNLWBD:Payment due for your " . $est_name . " with LWB reg no: " . $lwb_reg_no . "is Rs:"
////                . $bal_amt . ". Pls pay using " . $pmt_link . ". Kindly ignore if already paid.-TNGOVT";
////            } else {
////              $msg = "TNLWBD:Payment due for " . $est_name .
////                " is pending for " . $tt_year_count . " years with Rs:" . $tt_bal_amt .
////                ". Pls pay using " . $pmt_link . " or ignore if already paid.-TNGOVT";
////            }
////          }
////
////          $mobile_no = $row['est_mobile'];
////          $dlt_ct_id = '';//<- Template registration no
////          $gw_user_name = 'tnega_tnlwborg';
////
////          $sql = "INSERT INTO logs.sms_messages (is_otp, mobile_no, message, dlt_ct_id, gw_user_name, status, msg_type)
////                       VALUES ($1, $2, $3, $4, $5, $6, $7)
////                    RETURNING id";
////          $db->Query($sql, [false, $mobile_no, $msg, $dlt_ct_id, $gw_user_name, 'R', 'PAY_REM']);
////          $ins_messages = $db->FetchAll();
////
////          if (count($ins_messages) > 0) {
////            $id = $ins_messages[0]['id'];
////            $sms_req_data[] = [
////              'unique_id' => $id,
////              'mobile_no' => $mobile_no,
////              'message' => $msg,
////              'dlt_ct_id' => $dlt_ct_id,
////              'gw_user_name' => $gw_user_name
////            ];
////            $sms_json = $this->makeSMSJSON($sms_req_data);
////            $this->sendSMS($sms_json);//<- check the return value os sendSMS function
////          } else {
////            throw new \Exception("Can't insert a data");
////          }
////
////        }
////      }
////    } catch (\Exception $e) {
////      $failure_reason = $e->getMessage();
////
////      $sql = "UPDATE logs.sms_messages
////                 SET status = 'F',
////                     attempted_ts = CURRENT_TIMESTAMP,
////                     failure_reason = $3
////               WHERE msg_type = $1 and id = $2";
////      $db->Query($sql, ['PAY_REM', $id, $failure_reason]);
////    }
////    $db->DBClose();
////  }
//}
