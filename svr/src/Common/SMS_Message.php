<?php

namespace LWMIS\Common;

class SMS_Message
{
//  todo: create this on myconfig phpfile
//todo: use a flag to disable sending messages when on development stage

//  private string $otp_tl_id = '1007174088893955958';//<- (dlt_ct_id)
//  private string $resend_otp_tl_id = '1007030700773621462';
//  private string $gw_user_name = 'tnega_tnlwborg';
//  private string $header_dlt_id = "1005494061230884771";
  private string $onex_key = 'xPoiOpuw';
  private string $pay_rem_tl_id1 = '1007858017794568736';//<- For single year.
  private string $pay_rem_tl_id2 = '1007607859079210011';//<-For Multiple Pending years
//  private string $otp_template_id = "1007924161147545248";//<- (dlt_ct_id)
  private string $otp_template_id = "1007499137451044161";//<- (dlt_ct_id)
  private string $resend_otp_tl_id = '1007530958308903279';
  private string $pmt_receipt_tl_id = '';
  private string $pmt_acknowledge_tl_id = '1007240789999611407';
  private string $payment_link = 'http://localhost:4200/#/pm/pmt/';//<- for development only
  private string $SMS_GW_URL = 'https://api.onex-aura.com/api/jsms';
  private string $header = "TNLWBD";// gateway username or gateway cli name
  private string $entity_id = "1001418706824340468";

  /**
   * @throws \Exception
   */
  public function sendOTPForUserForgotPassword(\LWMIS\Common\PostgreDB $db, string $mobile_no, &$payload): array
  {
    $otp = $this->generateOTP($mobile_no, $payload);
//    $msg = "TNLWBD: " . $otp . " is the OTP to reset your password. This OTP is valid for 10 minutes only. Please do not share your OTP or Password with anyone - Tamil Nadu Labour Welfare Board.";
    $msg = $otp . " is the OTP to reset your password. This OTP is valid for 10 minutes only. Please do not share your OTP or Password with anyone - TNLWB.";
    $sms_req_data = [
      'mobile_no' => $mobile_no,
      'from' => $this->header,
      'message' => $msg,
      'template_id' => $this->otp_template_id, //<- dlt_ct_id
      'is_otp' => true,
      'msg_type' => 'PWD_RST'
    ];

    $this->processTheSMS($db, $sms_req_data);
    $retObj['message'] = "OTP Sent Successfully";

    return $retObj;
  }

  private function generateOTP(string $mobile_no, &$payload): int
  {
    $otp = rand(100000, 999999);

    if (!isset($payload['id'])) {
      $payload['mobile_no'] = $mobile_no;
    }
    $payload['otp'] = $otp;

    $this->log_otp($mobile_no, $otp);

    return $otp;
  }

  private function log_otp(string $mobile_no, string $otp): void
  {
    $date = new \DateTime('now', new \DateTimeZone('Asia/Kolkata'));
    $date_str = $date->format('Y-m-d H:i:s A');
    $file_path = __DIR__ . "/../../../logs/" . $date->format('Y') . "/" . $date->format('m') . "/";
    if (!file_exists($file_path)) {
      mkdir($file_path, 0777, true);
    }
    $log_file_name = $file_path . "otp-" . $date->format('Y-m-d') . ".log";
    $txt = "[{$date_str}] [{$mobile_no}] [{$otp}]" . PHP_EOL;
    file_put_contents($log_file_name, $txt, FILE_APPEND);
  }

  /**
   * @throws \Exception
   */
  private function processTheSMS(\LWMIS\Common\PostgreDB $db, $data): void
  {
    $message = $data['message'];
    $mobile_no = $data['mobile_no'];
    $gw_user_name = $data['from'];
    $dlt_ct_id = $data['template_id'];
    $is_otp = $data['is_otp'] ? 'true' : 'false';
    $msg_type = $data['msg_type'];

    $sql = 'INSERT INTO logs.sms_messages (is_otp, mobile_no, message, dlt_ct_id, gw_user_name, msg_type, attempted_ts)
                 VALUES ($1, $2, $3, $4, $5, $6, $7)
              RETURNING id';
    $db->Query($sql, [$is_otp, $mobile_no, $message, $dlt_ct_id, $gw_user_name, $msg_type, 'now()']);
    $rows = $db->FetchAll();

    if (count($rows) > 0) {
      $id = $rows[0]['id'];
      try {
        $sms_req_data = [//'unique_id' => $id,
          'mobile_no' => $mobile_no,
          'message' => $message,
          'template_id' => $dlt_ct_id,
          'from' => $gw_user_name];
        $sms_json = $this->makeSmsJsonForOnextel($sms_req_data);
        $sms_response = $this->sendSMS($sms_json);

        if ($sms_response->status == 100) {
          $status = 'S';
        } else {
          $status = 'F';
        }

        $sql = 'UPDATE logs.sms_messages
                   SET status = $4,
                       gw_message_id = $3,
                       description = $2,
                       attempted_ts = now()
                 WHERE id = $1 AND status = \'R\'';
        $db->Query($sql, [$id, $sms_response->description, $sms_response->messageid ?? null, $status]);
      } catch (\Throwable $th) {
        $failure_reason = $th->getMessage();
        $sql = 'UPDATE logs.sms_messages
                   SET status = \'F\',
                       attempted_ts = now(),
                       description = $2
                 WHERE id = $1 AND status = \'R\'';
        $db->Query($sql, [$id, $failure_reason]);
      }
    }
  }

  /**
   * @throws \Exception
   */
  private function makeSmsJsonForOnextel(array $data): false|string
  {
    if (!is_array($data) && count($data) > 0) {
      throw new \Exception("Can not make a sms json");
    }

    $msg = $data['message'];
    $mobile_no = $data['mobile_no'];
    $from = $data['from'];
    $template_id = $data['template_id'];

    return json_encode([
      //      "key" => "xPoiOpuw",
      "key" => $this->onex_key,
      "body" => $msg,
      "to" => $mobile_no,
      "from" => $from,
      "entityid" => $this->entity_id,
      "templateid" => $template_id
    ]);
  }

  /*    if (!is_array($data) && count($data) > 0) {
      throw new \Exception('Array of SMS messages not found');
    }

    $data_set = [];
    foreach ($data as &$m) {
      $unique_id = $m['unique_id'] ?? null;
      $mobile_no = $m['mobile_no'] ?? null;
      $message = $m['message'] ?? null;
      $dlt_ct_id = $m['dlt_ct_id'] ?? null;
      $gw_user_name = $m['gw_user_name'] ?? null;
      $data_set[] = [
        "UNIQUE_ID" => $unique_id,
        "MSISDN" => $mobile_no,
        "MESSAGE" => $message, // utf8_encode()
        "DLT_CT_ID" => $dlt_ct_id,
        "USER_NAME" => $gw_user_name, // SMS_GW_USER_NAME,
        "OA" => SMS_GW_OA,
        "CHANNEL" => SMS_GW_CHANNEL,
        "CAMPAIGN_NAME" => SMS_GW_CAMPAIGN_NAME,
        "CIRCLE_NAME" => SMS_GW_CIRCLE_NAME,
        "ACTION_RESP_URL" => SMS_GW_ACTION_RESP_URL,
        "DLT_TM_ID" => SMS_GW_DLT_TM_ID,
        "DLT_PE_ID" => SMS_GW_DLT_PE_ID,
        "LANG_ID" => "0"
      ];
    }

    return json_encode([
      "keyword" => "DEMO",
      "timeStamp" => (new \DateTime())->format('dmYHis'),
      "dataSet" => $data_set
    ]);*/

  private function sendSMS($sms_json): mixed
  {
    $url = $this->SMS_GW_URL;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);//<- to disable curl header
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $sms_json);

    $response = json_decode(curl_exec($ch));
    curl_close($ch);

    //    note: Responses / messageId avail inside the response can be null for failed messages.

    // Log sms message & response
    $log_req = 'Post data: ' . print_r($sms_json, true);
    //    $log_res = 'Response received: nothing';
    $log_res = 'Response received: ' . print_r($response, true);

    //    if (isset($response)) {
    //      if (gettype($response) === 'boolean' && $response === 'true') {
    //        $log_res = 'Response received: true';
    //      } else {
    //        $log_res = 'Response received: ' . print_r($response, true);
    //      }
    //    }
    $this->log($log_req, $log_res);
    //    return;//<- check this line / try this with echo
    return $response;
  }

  private function log($log_req, $log_res): void
  {
    $date = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata'));
    $date_str = $date->format('Y-m-d h:i:s A');
    $file_path = __DIR__ . "/../../../logs/" . $date->format('Y') . "/" . $date->format('m') . "/";
    if (!file_exists($file_path)) {
      mkdir($file_path, 0777, true);
    }
    $log_file_name = $file_path . "sms-airtel-" . $date->format('Y-m-d') . ".log";
    $txt = "[{$date_str}] [{$log_req}] [{$log_res}]" . PHP_EOL;
    file_put_contents($log_file_name, $txt, FILE_APPEND);
  }

  /**
   * @throws \Exception
   */
//  private function saveSms_message(\LWMIS\Common\PostgreDB $db, array $m): void
//  {
//    $unique_id = $m['unique_id'] ?? null;
//    $mobile_no = $m['mobile_no'] ?? null;
//    $message = $m['message'] ?? null;
//    $dlt_ct_id = $m['dlt_ct_id'] ?? null;
//    $gw_user_name = $m['gw_user_name'] ?? null;
//    $status = $m['status'] ?? null;
//    $failure_reason = $m['failure_reason'] ?? null;
//
//    if (is_null($unique_id)) {
//      $sql = "INSERT INTO logs.sms_messages (is_otp, mobile_no, message, dlt_ct_id, gw_user_name, status, msg_type)
//                   VALUES ($1, $2, $3, $4, $5, $6, $7)
//                RETURNING id";
//
//      $db->Query($sql, [false, $mobile_no, $message, $dlt_ct_id, $this->gw_user_name, 'R', 'PAY_REM']);
//    } else {
//      $sql = "UPDATE logs.sms_messages
//                 SET status = 'F',
//                     attempted_ts = CURRENT_TIMESTAMP,
//                     failure_reason = $3
//               WHERE msg_type = $1 and id = $2
//           RETURNING id";
//      $db->Query($sql, ['PAY_REM', $unique_id, $failure_reason]);
//    }
//
//    $db->FetchAll()[0]['id'];
//  }

  /**
   * @throws \Exception
   */
  function resendOTPForUserForgotPassword(\LWMIS\Common\PostgreDB $db, string $mobile_no, $otp): void
  {
    $msg = "TNLWBD: Your OTP " . $otp . " to reset your password is sent again. Please do not share your OTP with anyone.";

    $sms_req_data = [
      'mobile_no' => $mobile_no,
      'from' => $this->header,
      'message' => $msg,
      'template_id' => $this->resend_otp_tl_id, //<- dlt_ct_id
      'is_otp' => true,
      'msg_type' => 'PWD_RST'
    ];
    $this->processTheSMS($db, $sms_req_data);
  }

//  /**
//   * @template Tkey of array-key
//   * @param object $data
//   * @return array<string>
//   */

  function sendPaymentRemainders($data): array
  {
    //    var_dump('DATA',$data);
    $retObj = ['message' => 'Invalid Payment Remainder SMS was requested.'];
    $sp_year ??= $data->sp_year;
    $sp_amt ??= $data->sp_amt;
    $sp_amt_status ??= $data->sp_amt_stat;
    //    $all_pend_est ??= $data->allpe;
    $lwb_reg_nos = $data->lwb_reg_no ?? null;

    $where_clause = '';
    $params = array();

    if (!is_null($sp_year)) {
      $params[] = $sp_year;
      $where_clause = ' AND pb.year = $' . count($params);
    }

    if (!is_null($sp_amt) && !is_null($sp_amt_status)) {
      $params[] = $sp_amt;
      if ($sp_amt_status == 'gt') {
        $where_clause = ' AND pb.bal_amt > $' . count($params);
      } elseif ($sp_amt_status == 'lt') {
        $where_clause = ' AND pb.bal_amt < $' . count($params);
      }
    }

    if (!is_null($lwb_reg_nos[0]->reg_no)) {
      $where_clause = ' AND ';
      foreach ($lwb_reg_nos as $key => $lwb_reg_no) {
        $params[] = $lwb_reg_no->reg_no;
        if ($key == (count($lwb_reg_nos) - 1)) {
          $where_clause .= 'fm.lwb_reg_no ilike $' . count($params);
        } else {
          $where_clause .= 'fm.lwb_reg_no ilike $' . count($params) . ' OR ';
        }
      }
    }

    $where_clause .= ' AND pb.bal_amt != 0';
    $db = new PostgreDB();
    try {
      //      note: Below select query will only work for particular year only.
      $sql = "select distinct on (pb.firm_id) pb.firm_id,
                                  pb.paid_amt != 0 as is_paid,
                                  pb.paid_amt,
                                  pb.bal_amt,
                                  pb.firm_id,
                                  fm.est_mobile,
                                  fm.name,
                                  fm.lwb_reg_no
                            from est.payables as pb
                                 inner join est.firms as fm on pb.firm_id = fm.id
                           where true $where_clause";
      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      if (count($rows) > 0) {
        foreach ($rows as $row) {
          $db->Begin();
          $firm_id = $row['firm_id'];

          $sql = "select count(pb.bal_amt = 0) as tt_year_count,
                         sum(pb.bal_amt)       as tt_bal_amt
                    from est.payables as pb
                   where firm_id = $1";
          $db->Query($sql, [$firm_id]);
          $tt_val_of_est = $db->FetchAll();

          $tt_year_count = $tt_val_of_est[0]['tt_year_count'];
          $tt_bal_amt = $tt_val_of_est[0]['tt_bal_amt'];

          $est_name = $row['name'];
          $lwb_reg_no = $row['lwb_reg_no'];
          $bal_amt = $row['bal_amt'];
          $pmt_link = $this->payment_link . base_convert($firm_id, 16, 32);

          if ($tt_year_count == 1) {
            $msg = "TNLWBD:Payment due for your " . $est_name . " with LWB reg no: " . $lwb_reg_no . " is Rs:" . $bal_amt . ".\nPls pay using " . $pmt_link . ".\nKindly ignore this msg if already paid.";
            $dlt_ct_id = $this->pay_rem_tl_id1;
          } else {
            $msg = "TNLWBD:Payment due for " . $est_name . " is pending for last" . $tt_year_count . " years with Rs:" . $tt_bal_amt . ".\nPls pay using " . $pmt_link . ".\nKindly ignore this msg if already paid.";
            $dlt_ct_id = $this->pay_rem_tl_id2;
          }

          $mobile_no = $row['est_mobile'];

          $sms_req_data = [
            'message' => $msg,
            'mobile_no' => $mobile_no,
            'from' => $this->header,
            'template_id' => $dlt_ct_id, //<- dlt_ct_id
            'is_otp' => false,
            'msg_type' => 'PAY_REM'
          ];

          $this->processTheSMS($db, $sms_req_data);

          $db->Commit();
          $retObj['message'] = "Payment remainder sms is started successfully.";
        }
      }
    } catch (\Exception $e) {
      $db->RollBack();
      $retObj['message'] = ErrorHandler::custom($e);
    }
    $db->DBClose();
    return $retObj;
  }

  /**
   * @throws \Exception
   */
  function sendEstPaymentReceipt(\LWMIS\Common\PostgreDB $db, $est_mobile, $rpt_link_url): void
  {
    $msg = $rpt_link_url . '';

    $sms_req_data = [
      'message' => $msg,
      'mobile_no' => $est_mobile,
      'from' => $this->header,
      'template_id' => $this->pmt_receipt_tl_id, //<- dlt_ct_id
      'is_otp' => false,
      'msg_type' => ''
    ];

    $this->processTheSMS($db, $sms_req_data);
  }

  /**
   * @throws \Exception
   */
  function sendPaymentAcknowledgement(\LWMIS\Common\PostgreDB $db, $est_mobile, $employer_name): void
  {
    $msg = 'Hello,' . $employer_name . "We’ve confirmed your payment. Thank you for the contribution of Labour Welfare Fund. For more information: https://lwb.tn.gov.in/ - TNLWBD";

    $sms_req_data = [
      'message' => $msg,
      'mobile_no' => $est_mobile,
      'from' => $this->header,
      'template_id' => $this->pmt_acknowledge_tl_id, //<- dlt_ct_id
      'is_otp' => false,
      'msg_type' => 'PAY_ACK'
    ];

    $this->processTheSMS($db, $sms_req_data);
  }
  }
