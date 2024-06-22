<?php

namespace LWMIS\Est;

use Exception;
use LWMIS\Common\Encryption;
use LWMIS\Common\ErrorHandler;
use LWMIS\Common\PostgreDB;
use LWMIS\Common\SBIePay_Gateway;
use function count;
use function is_null;

//use LWMIS\Common\RazorPay;
if (\LWMIS\Common\MyConfig::IS_TESTING) {
  define('LOCAL_URL', 'http://localhost:4200/');
} else {
  define('LOCAL_URL', 'https://lwmis.lwb.tn.gov.in/');
}

class Payment
{
  /**
   * @throws Exception
   */


  function makePayment($data): array
  {
    $retVal = array();
    $payable = array();
    $selected_payments = $data->selected_payments ?? null;
    $selected_year = $data->selected_year ?? null;
    $firm_id = $data->id;
//    $merchantOrderNo = $this->genMerchantOrderNo((array)$selected_year, $firm_id);

    $pay_hist = new Employee_history();
    $hist = $pay_hist->getArrearsToBePaid($data)['rows'];
//    var_dump($hist);

    $totalDueAmount = 0;

    foreach ($hist as $o) {
      $totalDueAmount += $o['tt_amt'];
    }

    $db = new PostgreDB();
    $pb = new Payable();

    $merchantOrderNo = null;

    try {
      if ($selected_payments == null) {
        throw new Exception("Please select the payment Year");
      }

      $merchantOrderNo = $this->genMerchantOrderNo($db, (array)$selected_year, $firm_id);//<-generated for our reference

      $db->Begin();
      foreach ($selected_payments as $key => $item) {
        $tot_employees_per_year = $item->pe_male + $item->ce_male + $item->pe_female + $item->ce_female;
        $payable[$key] = $pb->savePayable($db, $item, $tot_employees_per_year, $firm_id);
      }

      $sql = "insert into est.payments (firm_id, trnx_no_own, trnx_amount, status)
              values ($1,$2,$3,$4)
              returning id";
      $db->Query($sql, [$firm_id, $merchantOrderNo, $totalDueAmount, 'I']);//I-Initiated.
      $payment_id = $db->FetchAll()[0]['id'];

//      $payable_id = $payable['payable_id'];
//      $tot_amt = $payable['total_amount'];

//      Check whether the payment_against_payable is required to be created before knowing payment status.
      foreach ($payable as $item) {
        $sql = "insert into est.payment_against_payables (payment_id, payable_id, tot_amt)
                values ($1,$2,$3)
                returning id";
        $db->Query($sql, [$payment_id, $item['payable_id'], $item['total_amount']]);
      }

      $db->Commit();
      $retVal['message'] = "Payment Details are saved successfully";
    } catch (Exception $e) {
      $db->RollBack();
      $retVal['message'] = "ERROR:" . ErrorHandler::custom($e);
    }

    $db->DBClose();
    $pay = new SBIePay_Gateway();
    $retVal['EncryptTrans'] = $pay->getEncryptTrans($totalDueAmount, $merchantOrderNo, json_encode($selected_year));
    $retVal['merchantId'] = $pay->getMerchantId();
    $retVal['payment_url'] = $pay->getTransactionUrl();

    return $retVal;
  }

  /**
   * @throws Exception
   */
  function genMerchantOrderNo(PostgreDB $db, array $selected_year, string $firm_id): string
  {
//    $db = new PostgreDB();
    $sql = "select lwb_reg_no
              from est.firms
             where id = $1";

    $db->Query($sql, [$firm_id]);
    $lwb_reg_no = $db->FetchAll()[0]['lwb_reg_no'];
    $firm_reg_no = explode('/', $lwb_reg_no);

    return
      $this->getPaymentTableCount() . '/' .
      $firm_reg_no[2] . '/' . $firm_reg_no[3] . '/' .
      substr((string)$selected_year[0], -2) . '-' .
      substr((string)$selected_year[count($selected_year) - 1], -2);
  }

  /**
   * @throws Exception payment count query isn't working
   */
  function getPaymentTableCount(): string
  {
    $db = new PostgreDB();
    $sql = "select count(*)
            from est.payments
            where extract(year from est.payments.trnx_date) = extract(year from now())";
    $db->Query($sql);
    return $db->FetchAll()[0]['count'];
  }

  public function doubleVerifyPayment(mixed $data): array
  {
    $retVal = [];
    $retVal['message'] = 'Verifying...';
    $merchantOrderNo = $data->trnx_no_own ?? null;
    $receipt_no = $data->receipt_no ?? null;
    $pm_status = $data->pm_status ?? null;
    $query_string = '';

    if ($pm_status == 'S') {
      $retVal['message'] = 'Double Verification is not applicable for the transaction with status SUCCESS';
      return $retVal;
    }

    if ($pm_status == 'F') {
      $retVal['message'] = 'Double Verification is not applicable for the transaction with status FAILED';
      return $retVal;
    }

    $db = new PostgreDB();
    try {
      $sql = "SELECT a.id,a.firm_id,a.trnx_to,a.trnx_date,a.trnx_no_own,
                     a.trnx_no_gw,a.trnx_amount,a.status,a.status_desc,a.payment_mode
                FROM est.payments AS a
               WHERE a.trnx_no_own = $1
            ORDER BY trnx_date DESC";

      $params = array($merchantOrderNo);
      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      if (count($rows) > 0) {
        SBIePay_Gateway::log_sbi_tran('Info', "Database Accessed Manually", "User wanted to verify the status (currently {$rows[0]['status']}) of trnx_no_own {$merchantOrderNo} with Payment Gateway.");

        $merchantOrderNo = $rows[0]['trnx_no_own'];
        $amount = $rows[0]['trnx_amount'];
        $atrn = $rows[0]['trnx_no_gw'];
        $payment_id = $rows[0]['id'];
        $firm_id = $rows[0]['firm_id'];
        $payment_mode = $rows[0]['payment_mode'];
        $trnx_date = $rows[0]['trnx_date'];

        $sbi = new SBIePay_Gateway();

        $dv_result = $sbi->doubleVerify($merchantOrderNo, $amount, $atrn);

        $pm_status = '';
        if ($dv_result[2] == 'SUCCESS') {
          $pm_status = 'SUCCESS';
        } elseif ($dv_result[2] == 'FAIL') {
          $pm_status = 'FAIL';
        } else {
          $pm_status = 'INITIATED';
        }

        $retVal['message'] = $pm_status;

        if (!($dv_result[2] == 'SUCCESS' || $dv_result[2] == 'FAIL')) {
          return $retVal;
        }

        $atrn = is_null($atrn) ? $dv_result[1] : $atrn;
        $amount = is_null($amount) ? $dv_result[7] : $amount;


        $payment_status = [
          'trnx_no_gw' => $atrn,
          'trnx_no_own' => $merchantOrderNo,
          'payment_id' => $payment_id,
          'trnx_amount' => $amount,
          'firm_id' => $firm_id,
          'trn_status' => $dv_result[2],
          'status_desc' => $dv_result[8],
          'payment_mode' => $payment_mode
        ];

        $receipt = [
          'payment_method' => 'OLP',
          'payment_id' => $payment_id,
          'trnx_dt' => $trnx_date,
          'trnx_ref_no' => $merchantOrderNo,
          'user_id' => (new \LWMIS\Est\Firm())->getUserIdByFirmId($db, $firm_id)[0]['user_id'],
          'status' => $dv_result[2],
          'receipt_amt' => $amount,
          'receipt_no' => $receipt_no,
          'firm_id' => $firm_id
        ];


//        $db->Begin();
        $query_string = $this->saveClaimPaymentDetails($db, $dv_result, $payment_status, $payment_id, $atrn, $amount, $receipt);
//        var_dump('qs', $query_string);

        \LWMIS\Common\SBIePay_Gateway::log_sbi_tran($pm_status, 'Payment Response - Decrypted', $data);

      } else {
        throw new Exception("No payment detail is available for the selected transaction no.");
      }
//      $db->Commit();

    } catch (Exception $e) {
//      $db->RollBack();
//      var_dump('error',$e);
      $retVal['message'] = \LWMIS\Common\ErrorHandler::custom($e);
    } finally {
      $db->DBClose();
//      header('Location: ' . LOCAL_URL . '../#/other/payment-receipt' . $query_string);
      return $retVal;
    }

  }

  public function saveClaimPaymentDetails($db, array $dv_result, array $payment_status, $payment_id, $atrn, $amount, $receipt): string
  {
    $rpt = new \LWMIS\Est\Receipt();
    $enc = new \LWMIS\Common\Encryption();
    $trnx_no_own = $payment_status['trnx_no_own'];
    $key = 'kc*&ET93gqX^N%p';
    $query_string = '';

//    note:updating payment with success status will throw a duplicate payment id error.
    if ($dv_result[2] == 'SUCCESS' && !is_null($dv_result[1]) && $dv_result[1] == $atrn && $dv_result[7] == $amount) {
      $this->updatePaymentDetails($db, $payment_status, 'S');
      $receipt_det['receipt_id'] = $rpt->insertPaymentReceipt($db, (object)$receipt)[0]['id'];
      $receipt_det['payment_id'] = $payment_id;

      $rpt->insertReceiptAgainstPayable($db, (object)$receipt_det);
      $receipt_data = json_encode((object)['receipt_id' => $receipt_det['receipt_id'], 'trnx_no_own' => $trnx_no_own, 'payment_id' => $payment_id, 'status' => 'S']);
      $enc_receipt_data = $enc->encrypt($receipt_data, $key);
      $query_string = '?payment_data=' . urlencode($enc_receipt_data);
    } else if ($dv_result[2] == 'FAIL' && !is_null($dv_result[1]) && $dv_result[1] == $atrn && $dv_result[7] == $amount) {
      $this->updatePaymentDetails($db, $payment_status, 'F');
      $payment_data = json_encode((object)['trnx_no_own' => $trnx_no_own, 'status' => 'F']);
      $enc_receipt_data = $enc->encrypt($payment_data, $key);
      $query_string = '?payment_data=' . urlencode($enc_receipt_data);
    } else {
      $this->updatePaymentDetails($db, $payment_status, 'I');//I - Initiated.
      $payment_data = json_encode((object)['trnx_no_own' => $trnx_no_own, 'status' => 'I']);
      $enc_receipt_data = $enc->encrypt($payment_data, $key);
      $query_string = '?payment_data=' . urlencode($enc_receipt_data);
    }
    return $query_string;
  }

  public function updatePaymentDetails($db, array $data, string $status): void
  {
    $trnx_no_gw = $data['trnx_no_gw'];//<- atrn_no or sbiepay_ref_no
    $trnx_no_own = $data['trnx_no_own'];//<- MerchantOrderNumber generated by us
    $payment_id = $data['payment_id'];
    $trnx_amount = $data['trnx_amount'];
    $status_desc = $data['status_desc'];
    $payment_mode = $data['payment_mode'];
//    $selected_years = $data['selected_years'];

//    $sql = "UPDATE est.payments
//            SET status = $1, trnx_no_gw = $2, status_desc = $3, payment_mode = $4
//            WHERE trnx_no_own = $5 AND id = $6 AND status = $7 AND trnx_amount = $8
//            RETURNING id";

//    razorpay doesn't return the payment_mode
    $sql = "UPDATE est.payments
            SET status = $1, trnx_no_gw = $2, status_desc = $3, payment_mode = $4, trnx_amount = $5
            WHERE id = $6 AND trnx_no_own = $7
            RETURNING id";

    $db->Query($sql, [$status, $trnx_no_gw, $status_desc, $payment_mode, $trnx_amount,
      $payment_id, $trnx_no_own]);
    $payment_id = $db->FetchAll()[0]['id'];

    if ($status == 'S') { //<-paid amount is only applicable for the successful transaction.
      $sql = "select payable_id
                from est.payment_against_payables
               where payment_id = $1";
      $db->Query($sql, [$payment_id]);
      $payable_id = $db->FetchAll();

      foreach ($payable_id as $item) {
        $sql = "select total_amt
                  from est.payables
                 where id = $1";
        $db->Query($sql, [$item['payable_id']]);
        $total_amt = $db->FetchAll()[0]['total_amt'];

        $sql = "update est.payables
                   set paid_amt = $2
                 where id = $1";
        $db->Query($sql, [$item['payable_id'], $total_amt]);
      }
    }

  }

  function getPendingPayments()
  {
    global $db;
    $sql = "SELECT * FROM est.payments WHERE status = 'INITIATED'";
    $db->query($sql);
    $rows = $db->get_all("assoc");
    foreach ($rows as &$r) {
      $r['trnx_amount'] = floatval($r['trnx_amount']);
    }
    return $rows;
  }

  function verifyCheckSum($data)
  {
    $retObj = false;

    $checkSum = isset($data['checkSum']) ? $data['checkSum'] : null;
    if (is_null($checkSum)) {
      self::log_sbi_tran('Error', 'CheckSum Detection', "Checksum not found.");
    } else {
      unset($data['checkSum']);
      $checkSum = trim($checkSum, " ");
      $str = $this->joinKeyValue($data);
      $calc_checkSum = $this->makeCheckSum($str);
      if ($checkSum !== $calc_checkSum) {
        self::log_sbi_tran('Error', 'checkSum don\'t match', "sbi_ref_no: " . (disset($data['sbi_ref_id']) ? $data['sbi_ref_id'] : $data['sbi_ref_no']));
      } else {
        self::log_sbi_tran('Info', 'CheckSum Verified', "sbi_ref_no: " . (isset($data['sbi_ref_id']) ? $data['sbi_ref_id'] : $data['sbi_ref_no']));
        $retObj = true;
      }
    }

    return $retObj;
  }

  function joinKeyValue($data)
  {
    $str = '';
    foreach ($data as $key => $val) {
      //      $str .= $key . '=' . $val . '|';
      $str .= $val . '|';
    }
    if (strlen($str) > 0) {
      $str = substr($str, 0, strlen($str) - 1);
    }
    return $str;
  }

  function makeCheckSum($str): string
  {
    return hash('sha256', $str);
  }

  // TODO::Remove after RazorPay implementation

  function cancelledPayment($data)
  {
    $retObj = [];
    $reason = isset($data->reason) ? $data->reason : null;
    $trnx_no_own = isset($data->trnx_no_own) ? $data->trnx_no_own : null;

    if (!is_null($trnx_no_own)) {
      $db = new PostgreDB();
      $sql = 'UPDATE est.payments SET status = \'F\', cancelled_dt = current_timestamp, remarks = $1 WHERE trnx_no_own = $2 AND status = \'I\'';
      $db->Query($sql, [$reason, $trnx_no_own]);
      $db->DBClose();

      $retObj['message'] = 'Payment cancelled successfully.';
    }

    return $retObj;
  }

  /**
   * @throws Exception
   */
  private function initiateCANARA_RazorPay($db, $data): array
  {
    $trnx_no_own = $data['trnx_no_own'];
    $trnx_amt = $data['trnx_amt'];

    $rp = new \LWMIS\Est\CANARA_Razorpay();
    $retObj = $rp->createOrder($trnx_no_own, $trnx_amt);

    if (isset($retObj['rzp_order_id'])) {
      $rzp_order_id = $retObj['rzp_order_id'];
      $rzp_order_tsp = $retObj['rzp_order_tsp'];
      $sql = 'UPDATE est.payments
                 SET rzp_order_id = $1, rzp_order_tsp = to_timestamp($2)
               WHERE trnx_no_own = $3';
      $db->Query($sql, [$rzp_order_id, $rzp_order_tsp, $trnx_no_own]);
    } else {
      $failure_reason = $retObj['failure_reason'] ?? 'RazorPay: Order cannot be created.';

      $sql = 'UPDATE est.payments
                 SET status = \'F\', status_desc = $2,
                     remarks = CASE WHEN COALESCE(remarks, \'\') = \'Awaited\' THEN NULL ELSE remarks END
               WHERE trnx_no_own = $1';
      $db->Query($sql, [$trnx_no_own, $failure_reason]);
      $retObj['message'] = 'Payment initialisation failed.';
    }

    // Log raw response from gateway
    if (isset($retObj['raw_data'])) {
      $addl_data = $retObj['raw_data'];
      $sql = 'INSERT INTO logs.payment_details (trnx_no_own, addl_data) VALUES ($1, $2)';
      $db->Query($sql, [$trnx_no_own, $addl_data]);
    }

    return $retObj;
  }

//  function saveOlp($db, $data)
//  {
//    $retObj = [];
//    // for logging purposes
//    $gf = new GeneralFunctions();
//
//    //update payment table
//    $trnx_no_gw = $data['trnx_no_gw'];
//    $trnx_dt_gw = $data['trnx_dt_gw'];
//    $trnx_no_own = $data['trnx_no_own'];
//    $is_has_pay = $data['is_has_pay'];
//    $mobile_no = $data['mobile_no'];
//    $firm_name = $data['firm_name'];
//    $firm_id = $data['firm_id'];
//    $params = [$trnx_no_gw, $trnx_dt_gw, $trnx_no_own];
//    $sql = 'UPDATE est.payments
//               SET trnx_no_gw = $1, trnx_dt_gw = to_timestamp($2), status = \'S\'
//             WHERE trnx_no_own = $3 AND status = \'I\' RETURNING *';
//    $db->Query($sql, $params);
//    $paymentData = $db->FetchAll()[0];
//
//    $gf->log($sql);
//    $gf->log(print_r($params, true));
//    $gf->log(print_r($paymentData, true));
//
//    // check remaing amount
//    $remain = new Receipt();
//    $remaining_amt = $remain->remainingBal($db, $data)[0]['remaining_amt'];
//    $payingData = [
//      'amount' => $paymentData['trnx_amt'],
//      'trnx_no_own' => $paymentData['trnx_no_own'],
//      'payment_id' => $paymentData['id'],
//      'is_has_pay' => $is_has_pay,
//      'remaining_amt' => $remaining_amt
//    ];
//
//    // paying
//    $paying = new Payable();
//    $current_payable = $paying->payablePaid($db, (object)$payingData);
//
//    if (isset($current_payable)) {
//      // $retObj['id']=$current_booking['id'];
//      $retObj['amount'] = $paymentData['trnx_amt'];
//      $retObj['message'] = 'Paid successfully.';
//    }
//
//    if ($retObj['message'] == 'Paid successfully.') {
//      $amount = $retObj['amount'];
//      $edd = $paymentData['trnx_dt_gw'];
//      // $sms = new \LWMIS\Common\SMS();
//      // $sms->sendPaymentConfirmation($mobile_no, $paymentData['trnx_no_own'], $firm_name, $edd, $amount);
//    }
//
//    return $retObj;
//  }

//  function getPaymentStatus($filter)
//  {
//    $trnx_no_own = isset($filter->trnx_no_own) ? $filter->trnx_no_own : null;
//    if (is_null($trnx_no_own)) {
//      return false;
//    }
//
//    $params = [];
//    $params[] = $trnx_no_own;
//
//    $sql = 'SELECT a.trnx_no_own, a.trnx_dt_own, a.trnx_no_gw, a.status, a.trnx_dt_gw, a.remarks, a.trnx_amt,
//                     b.name AS firm_name, b.address, b.est_phone,
//                     d.receipt_no
//                from est.payments as a
//                     inner join est.firms as b on (b.id = a.firm_id)
//                     inner join est.payment_against_payables as c on (c.payment_id = a.id)
//                     inner join est.receipts as d on (a.trnx_no_own = d.trnx_ref_no)
//               where a.trnx_no_own = $1';
//    $db = new PostgreDB();
//    $db->Query($sql, $params);
//    $rows = $db->FetchAll();
//    $db->DBClose();
//    foreach ($rows as &$r) {
//      $r['amount'] = floatval($r['trnx_amt']);
//    }
//    return (count($rows) > 0) ? $rows[0] : false;
//  }

//  function savePaymentFailure($db, $data): array
//  {
//    //update payment table (F)
//    $trnx_no_own = $data['trnx_no_own'];
//    $remarks = $data['status_description'];
//
//    $sql = 'UPDATE est.payments SET dr_dt = NOW(),
//                        status = \'F\',
//                        remarks = $1 where trnx_no_own = $2 and status = \'I\'';
//
//    $db->Query($sql, array($remarks, $trnx_no_own));
//    return ['message' => 'Payment Failed.'];
//  }

//  function checkPaymentStatus($db, $data): bool
//  {
//    $firm_id = $data->firm_id;
//
//    $params = [];
//    $params[] = $firm_id;
//
//    $sql = 'SELECT firm_id
//                FROM est.payments
//               WHERE firm_id = $1 and status IN (\'I\')';
//    $db->Query($sql, $params);
//    $rows = $db->FetchAll();
//    return (count($rows) > 0);
//  }

  function getPaymentsEst($filter)
  {

    $retObj = ["rows" => [], "tot_rows" => 0];
    $limit = isset($filter->limit) ? $filter->limit : null;
    $offset = $limit * (isset($filter->offset) ? $filter->offset : 0);

    $params = [];
    $where_clause = "";

    if (isset($filter->id) && !is_null($filter->id)) {
      $firm_id = $filter->id;
      $params[] = $firm_id;
      $where_clause .= ' AND a.firm_id =$' . count($params);
    }

    if (isset($filter->trnx_no_own) && is_string($filter->trnx_no_own)) {
      $trnx_no_own = $filter->trnx_no_own;
      $params[] = $trnx_no_own;
      $where_clause .= ' AND a.trnx_no_own = $' . count($params);
    }

    $db = new PostgreDB();
    try {
      // actual data
      $sql = 'SELECT a.trnx_no_own, a.trnx_dt_own, a.trnx_no_gw, a.status, a.trnx_dt_gw, a.remarks, a.trnx_amt,
                       b.name AS firm_name, b.address, b.est_phone,
                       d.bal_amt,d.year,d.tot_employees,d.employee_amt,d.total_cntrbtn,
                       d.employer_amt,d.total_amt,d.employee_cntrbtn, d.employer_cntrbtn
                  from est.payments as a
                       inner join est.firms as b on (b.id = a.firm_id)
                       inner join est.payment_against_payables as c on (c.payment_id = a.id)
                       left outer join est.payables as d on (d.id = c.payable_id)
                  where TRUE ' . $where_clause . '
                  ORDER by d.year asc;';
      $db->Query($sql, $params);
      $rows = $db->FetchAll();
      foreach ($rows as &$r) {
        $r['trnx_amt'] = floatval($r['trnx_amt']);
        $r['bal_amt'] = intval($r['bal_amt']);
      }
      $retObj['rows'] = $rows;
      // total rows
      if (!is_null($limit) && count($rows) == $limit) {
        $sql = 'SELECT COUNT(*) AS cnt, $1 AS limit, $2 AS offset
                    FROM est.firms AS a
                   WHERE true' . $where_clause . ';';
        $db->Query($sql, $params);
        $tot_rows = $db->FetchAll();

        foreach ($tot_rows as &$r) {
          $r['cnt'] = intval($r['cnt']);
        }

        $retObj['tot_rows'] = (count($tot_rows) > 0) ? $tot_rows[0]['cnt'] : count($rows);
      } else {
        $retObj['tot_rows'] = ((!is_null($offset)) ? $offset : 0) + count($rows);
      }
    } catch (Exception $e) {
      $retObj['message'] = ErrorHandler::custom($e);
    }
    $db->DBClose();

    return $retObj;
  }

//  function getPaymentDetails($filter)
//  {
//    $trnx_no_own = isset($filter->trnx_no_own) ? $filter->trnx_no_own : null;
//    if (is_null($trnx_no_own)) {
//      return false;
//    }
//
//    $params = [];
//    $params[] = $trnx_no_own;
//
//    $sql = 'SELECT a.trnx_no_own, a.trnx_dt_own, a.trnx_no_gw, a.status, a.trnx_dt_gw, a.remarks, a.trnx_amt,
//                     b.name AS firm_name, b.address, b.est_phone
//                from est.payments as a
//                     inner join est.firms as b on (b.id = a.firm_id)
//                     inner join est.payment_against_payables as c on (c.payment_id = a.id)
//               where a.trnx_no_own = $1';
//    $db = new PostgreDB();
//    $db->Query($sql, $params);
//    $rows = $db->FetchAll();
//    $db->DBClose();
//    foreach ($rows as &$r) {
//      $r['amount'] = floatval($r['trnx_amt']);
//    }
//    return (count($rows) > 0) ? $rows[0] : false;
//  }

  function getPaymentListByPaidOnYear(): array
  {
    $db = new PostgreDB();
    $rows = [];
    try {
//      $sql = "select extract(year from pm.trnx_date)                              as year,
//                     sum(pb.paid_amt)                                             as tt_rec_pay,
//                     coalesce(sum(pb.bal_amt), 0)                                 as tt_pend_pay,
//                     count(distinct pb.firm_id) filter ( where pb.paid_amt != 0 ) as paid_est,
//                     count(distinct pb.firm_id) filter ( where pb.paid_amt = 0 )  as unpaid_est
//              from est.payments as pm
//              left join lateral (
//                  select bal_amt,
//                         firm_id,
//                         paid_amt
//                  from est.payables
//                  where id in (select pap.payable_id
//                               from est.payment_against_payables as pap
//                               where pap.payment_id = pm.id)
//              ) as pb on true
//              group by extract(year from pm.trnx_date)
//              order by year desc";

      $sql = "select extract(year from pm.trnx_date)                             as year, --pm_made_on_year
                     count(distinct pm.firm_id) filter ( where pm.status = 'S' ) as paid_est,
                     sum(pm.trnx_amount) filter ( where pm.status = 'S' )        as tt_rec_pay,
                     count(pm.status) filter ( where pm.status = 'S' )           as suc_pmts,
                     count(pm.status) filter ( where pm.status != 'S' )          as unsuc_pmts,
                     count(pm.status)                                            as tt_rec_pay_count
              from est.payments as pm
              --          inner join LATERAL (select id,
              --                                     year,
              --                                     bal_amt,
              --                                     firm_id,
              --                                     paid_amt
              --                              from est.payables
              --                              where id in (select pap.payable_id
              --                                           from est.payment_against_payables as pap
              --                                           where pap.payment_id = pm.id)) as pb on pb.year = extract(year from pm.trnx_date)
              group by extract(year from pm.trnx_date)";

      $db->Query($sql, []);
      $rows = $db->FetchAll();

//      $rows[0]['g_paid_est'] = 0;
//      $rows[0]['g_tt_rec_pay'] = 0;
//      $rows[0]['g_unpaid_est'] = 0;
//      $rows[0]['g_tt_pend_pay'] = 0;
//
//      foreach ($rows as &$row) {
//        $rows[0]['g_paid_est'] += intval($row['paid_est'] ?? 0);
//        $rows[0]['g_tt_rec_pay'] += intval($row['tt_rec_pay'] ?? 0);
//        $rows[0]['g_unpaid_est'] += intval($row['unpaid_est'] ?? 0);
//        $rows[0]['g_tt_pend_pay'] += intval($row['tt_pend_pay'] ?? 0);
//      }

      foreach ($rows as &$r) {
        $r['year'] = intval($r['year']);
        $r['tt_rec_pay'] = intval($r['tt_rec_pay']);
        $r['paid_est'] = intval($r['paid_est']);
        $r['suc_pmts'] = intval($r['suc_pmts']);
        $r['unsuc_pmts'] = intval($r['unsuc_pmts']);
        $r['tt_rec_pay_count'] = intval($r['tt_rec_pay_count']);
//        $r['unpaid_est'] = intval($r['unpaid_est']);
//        $r['tt_pend_pay'] = intval($r['tt_pend_pay']);
//        $r['issued'] = intval($r['issued']);
//        $r['pend_clr'] = intval($r['pend_clr']);
//        $r['tt_rec_upa_pay'] = intval($r['tt_rec_upa_pay']);
      }

    } catch (Exception $e) {
      $rows = ErrorHandler::custom($e);
    } finally {
      $db->DBClose();
      return $rows;
    }

  }

  function getPaymentListByPaidForYears(): array
  {
    $rows = [];
    $db = new PostgreDB();
    try {
      $sql = "SELECT year,
                     sum(paid_amt) tt_rec_pay,
                     coalesce(sum(bal_amt),0)  tt_pend_pay,
                     count(distinct firm_id) filter ( where paid_amt != 0 ) as paid_est,
                     count(distinct firm_id) filter ( where paid_amt = 0 ) as unpaid_est
              FROM est.payables
              group by year
              order by year desc";

      $db->Query($sql, []);
      $rows = $db->FetchAll();

      foreach ($rows as &$r) {
        $r['year'] = intval($r['year']);
        $r['tt_rec_pay'] = intval($r['tt_rec_pay']);
        $r['tt_pend_pay'] = intval($r['tt_pend_pay']);
        $r['paid_est'] = intval($r['paid_est']);
        $r['unpaid_est'] = intval($r['unpaid_est']);
//        $r['issued'] = intval($r['issued']);
//        $r['pend_clr'] = intval($r['pend_clr']);
//        $r['tt_rec_upa_pay'] = intval($r['tt_rec_upa_pay']);
      }

    } catch (Exception $e) {
      $rows = ErrorHandler::custom($e);
    } finally {
      $db->DBClose();
      return $rows;
    }

  }


  function getPaymentCount(): array
  {
    $db = new PostgreDB;
    $where_clause = '';
    $params = [];
    $limit_offset_as = '';

    try {
//      $sql = "
//        SELECT EXTRACT(YEAR FROM NOW())AS year,
//               SUM(paid_amt)     tt_rec_pay,
//               SUM(bal_amt)      tt_pend_pay,
//               COUNT(clr.cre_ts) issued,
//               COUNT(clr.up_dt)  pend_clr,
//               SUM(upa.amount)   tt_rec_upa_pay
//        FROM est.payables,
//        upa.unpaid_accumulation AS upa,
//        est.clarifications AS clr
//        WHERE 'SO' = clr.o_designation_code
//        ";

      $sql = "SELECT year,
                     sum(paid_amt) tt_rec_pay,
                     sum(bal_amt)  tt_pend_pay,
                     count(distinct firm_id) filter ( where paid_amt != 0 ) as paid_est,
                     count(distinct firm_id) filter ( where paid_amt = 0 ) as unpaid_est
              FROM est.payables
              group by year
              order by year desc";

      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      foreach ($rows as &$r) {
        $r['year'] = intval($r['year']);
        $r['tt_rec_pay'] = intval($r['tt_rec_pay']);
        $r['tt_pend_pay'] = intval($r['tt_pend_pay']);
        $r['paid_est'] = intval($r['paid_est']);
        $r['unpaid_est'] = intval($r['unpaid_est']);
//        $r['issued'] = intval($r['issued']);
//        $r['pend_clr'] = intval($r['pend_clr']);
//        $r['tt_rec_upa_pay'] = intval($r['tt_rec_upa_pay']);
      }
      $retObj['rows'] = $rows;
    } catch (Exception $e) {
      $retObj['message'] = ErrorHandler::custom($e);
    }
    $db->DBClose();
    return $retObj;
  }

  function getPaymentList($data): array
  {
//    var_dump($data);
    $selected_year = $data->year ?? null;
    $limit_offset = "";
    $limit_offset_as = '';
    $limit = $data->limit ?? null;
    $offset = $limit * ($data->offset ?? 0);
    $where_clause = '';
    $params = [];

    if (isset($data->limit) && $data->limit) {
      $params[] = $limit;
      $limit_offset .= ' LIMIT $' . count($params);
      $limit_offset_as .= ' $' . count($params) . ' AS limit,';

      $params[] = $offset;
      $limit_offset .= ' OFFSET $' . count($params);
      $limit_offset_as .= ' $' . count($params) . ' AS offset';
    }

    if (!is_null($selected_year)) {
      $params[] = $selected_year;
//      $where_clause = "and pb.year = $1";
      $where_clause = "extract(YEAR from pm.trnx_date) = $" . count($params);
    }


    if (isset($data->search_text) && strlen($data->search_text) > 0) {
      $search_text = '%' . $data->search_text . '%';
      $params[] = $search_text;
      $param_cnt = '$' . count($params);
      $where_clause .= " AND (pm.trnx_no_own ilike $param_cnt OR fm.name ilike $param_cnt OR fm.lwb_reg_no ilike $param_cnt)";
      //Note: pm.trnx_no_own is necessary for sms remainder payments.
    }

    $db = new PostgreDB;
    try {
//      $sql = "select fm.id,
//                     fm.firm_reg_no,
//                     fm.name,
//                     fm.contact_mobile,
//                     fm.contact_phone,
//                     fm.locality,
//                     pb.paid_amt,
//                     pb.year,
//                     eh.tot_employees
//              from est.firms as fm
//                       inner join est.employee_history eh on fm.id = eh.firm_id
//                       inner join est.payables as pb on fm.id = pb.firm_id
//                       inner join est.payment_against_payables as pap on pb.id = pap.payable_id
//                       inner join est.payments as pm on pm.id = pap.payment_id
//              where true $where_clause
//              order by pb.year";

      $sql = "select pm.id,
                     pm.firm_id,
                     extract(YEAR from pm.trnx_date) as year,
                     pm.trnx_date,
                     pm.trnx_amount,
                     pm.trnx_no_own,
                     pm.status,
                     fm.name,
                     fm.lwb_reg_no,
                     fm.contact_mobile,
                     fm.contact_phone,
                     fm.locality,
                     eh.tot_employees
              from est.payments as pm
              left join est.firms fm on pm.firm_id = fm.id
              left join est.employee_history eh on
                  fm.id = eh.firm_id and
                  extract(year from pm.trnx_date) = eh.year
              where true and $where_clause
              order by pm.trnx_date desc
              $limit_offset";

      $db->Query($sql, $params);
      $rows = $db->FetchAll();
      $retObj['rows'] = $rows;

      if (!is_null($limit) && count($rows) == $limit) {
        $sql = "select count(*) as cnt,$limit_offset_as
                from est.payments as pm
                left join est.firms fm on pm.firm_id = fm.id
                left join est.employee_history eh on
                    fm.id = eh.firm_id and
                    extract(year from pm.trnx_date) = eh.year
                where true and $where_clause";

        $db->Query($sql, $params);
        $tot_rows = $db->FetchAll();

        foreach ($tot_rows as &$tot_row) {
          $tot_row['cnt'] = intval($tot_row['cnt']);
        }

        $retObj['tot_rows'] = (count($tot_rows) > 0) ? $tot_rows[0]['cnt'] : count($rows);
      } else {
        $retObj['tot_rows'] = ((!is_null($offset)) ? $offset : 0) + count($rows);
      }

    } catch (Exception $e) {
      $retObj['message'] = \LWMIS\Common\ErrorHandler::custom($e);
    }
    return $retObj;
  }

  /**
   * @throws Exception
   */
  function saveAlreadyPaid($data): array
  {
//    var_dump($data);
    $retVal = array();
    $selected_payments = $data->selected_payments ?? null;
    $firm_id = $data->id ?? null;
    $receipt_no = $data->receipt_no ?? null;
    $receipt_dt = $data->receipt_dt ?? null;
    $payment_mode = $data->paymentType ?? null;
    $receiptAmount = $data->receiptAmount ?? null;

    $pay_hist = new Employee_history();
    $hist = $pay_hist->getArrearsToBePaid($data)['rows'];
    $totalDueAmount = 0;

    foreach ($hist as $o) {
      $totalDueAmount += $o['tt_amt'];
    }

    $db = new PostgreDB();
    $pb = new Payable();//    var_dump('spmt',$selected_payments);

    try {
      if ($selected_payments == null) {
        throw new Exception("Please select the payment Year");
      }

      $selected_year = $selected_payments->year ?? null;
      $merchantOrderNo = $this->genMerchantOrderNo($db, (array)$selected_year, $firm_id);//<-generated for our reference

      if ($receiptAmount != $totalDueAmount) {
        $retVal['message'] = 'Receipt amount must match with our database';
        return $retVal;
      }

      $sql = "select paid_amt
            from est.payables
            where firm_id = $1 AND year = $2";

      $db->Query($sql, [$firm_id, $selected_year]);
      $paid_amt = (int)$db->FetchAll()[0]['paid_amt'];

      if ($paid_amt) {
        $retVal['message'] = "Duplicate payment detail is not allowed";
        return $retVal;
      }

      $sql = "select id
            from est.receipts
            where receipt_no = $1";

      $db->Query($sql, [$receipt_no]);

      if (!empty($db->FetchAll()[0]['id'])) {
        $retVal['message'] = "Duplicate Receipt Number";
        return $retVal;
      }

      $db->Begin();
      $tot_employees_per_year = $selected_payments->pe_male + $selected_payments->ce_male + $selected_payments->pe_female + $selected_payments->ce_female;
      $payable = $pb->savePayable($db, $selected_payments, $tot_employees_per_year, $firm_id);

      $payable_id = $payable['payable_id'];
      $tot_amt = $payable['total_amount'];

      $sql = "insert into est.payments (firm_id, trnx_no_own, trnx_date, trnx_amount, payment_mode, status, is_already_paid)
              values ($1,$2,$3,$4,$5,$6)
              returning id";

      $db->Query($sql, [$firm_id, $merchantOrderNo, $receipt_dt, $totalDueAmount, $payment_mode, 'SUCCESS', true]);
      $rows = $db->FetchAll();
      $payment_id = $rows[0]['id'] ?: null;

      if (!is_null($payment_id) && !is_null($tot_amt) && !is_null($payable_id)) {
        $sql = "insert into est.payment_against_payables(payment_id, payable_id, tot_amt)
                values ($1,$2,$3)
                returning id";
        $db->Query($sql, [$payment_id, $payable_id, $tot_amt]);

        $receipt = [
          'payment_method' => 'OLP',
          'payment_id' => $payment_id,
//          'trnx_dt' => $trnx_date,
//          'trnx_ref_no' => $trnx_no_own,
          'status' => 'C',// C : Cancelled Receipt
          'cancel_reason' => 'AP',
          'receipt_no' => $receipt_no,
          'receipt_amt' => $receiptAmount,
          'receipt_dt' => $receipt_dt,
          'user_id' => (new \LWMIS\Est\Firm())->getUserIdByFirmId($db, $firm_id)[0]['user_id'],
          'firm_id' => $firm_id
        ];

        $rpt = new Receipt();
        $receipt_id = $rpt->insertPaymentReceipt($db, (object)$receipt)[0]['id'];

        $rap = [
          ...$receipt,
          'receipt_id' => $receipt_id
        ];

        $rpt->insertReceiptAgainstPayable($db, (object)$rap);

      } else {
        throw new Exception('some fields are missing!');
      }

      $db->Commit();
      $retVal['message'] = "Payment Details are saved successfully";
    } catch (Exception $e) {
      $db->RollBack();
      $retVal['message'] = \LWMIS\Common\ErrorHandler::custom($e);
//      $retVal['message'] = "ERROR:" . $e->getMessage();
    }
    $db->DBClose();
    return $retVal;
  }

  function is_pending_payment(object $data): array
  {
    $firm_id = $data->firm_id ?? null;
    $db = new PostgreDB();
    try {
      $sql = "select count(paid_amt) filter ( where paid_amt = 0 ) as pend_pay_count
              from est.payables
              where firm_id = $1;";

      $db->Query($sql, [$firm_id]);
      $rows = $db->FetchAll();
    } catch (Exception $e) {
      $rows[0] = [\LWMIS\Common\ErrorHandler::custom($e)];
    }
    $db->DBClose();
    $rows[0] = [0];//<- todo remove this bef production launch
    return $rows[0];
  }

  private
  function getPaymentByFirm_id(mixed $data): array
  {
    $firm_id = $data->firm_id ?? null;
    $params = array($firm_id);
    $db = new PostgreDB();
    try {
      $sql = "SELECT *
                FROM est.payments P
               WHERE P.firm_id = $1";
      $db->Query($sql, $params);
      $rows = $db->FetchAll();
    } catch (\Exception $e) {
      \LWMIS\Common\ErrorHandler::defineErrorLevel();
      \LWMIS\Common\ErrorHandler::custom($e);
    }

    foreach ($rows as $row) {
      $row['trnx_amount'] = floatval($row['trnx_amount']);
    }
    return $rows;
  }

//  function rzpOrderAPI($data)
//  {
//    /**
//     * It is compulsory to create an ORDER API for every PAYMENTS API.
//     * PAYMENTS API is the Server to Server call & it is secured by AUTH using API keys.
//     * Pass the order_id received in ORDER API response for PAYMENTS API check out.
//     *
//     * Tying order with payment secures the payment request from being tampered.
//     *
//     */
//
//    $key_id = 'rzp_test_IT1L6GcJ0413py';
//    $secret = 'DyAspUq8E6fRs2smHE2uKSBa';
//
//    $receipt = '123';
//    $amount = 100;
//    $currency = 'INR';
////    $partial_pmt = false;
//
//    $api = new Api($key_id, $secret);
//
//    $val = $api->order->create(array('receipt' => $receipt, 'amount' => $amount, 'currency' => $currency, "partial_payment" => false, 'notes' => array('key1' => 'value3', 'key2' => 'value2')));
//    return $val['id'];
//  }

  function init_CF_Payment($data): array
  {
    $retVal = array();
    $payable = array();
    $selected_payments = $data->selected_payments ?? null;
    $selected_year = $data->selected_year ?? null;
    $firm_id = $data->id ?? null;

    if ($firm_id == null) {
      $retVal['message'] = "Firm id is required.";
      return $retVal;
    }

    $pay_hist = new Employee_history();
    $hist = $pay_hist->getArrearsToBePaid($data)['rows'];

    $totalDueAmount = 0;

    foreach ($hist as $k) {
      if (array_key_exists('tt_amt', $k) && is_numeric($k['tt_amt'])) {
        $totalDueAmount += $k['tt_amt'];
      } else {
        $retVal['message'] = "Amount Can't be null or non numeric value";
        return $retVal;
      }
    }

    $db = new \LWMIS\Common\PostgreDB();
    $pb = new Payable();//<- check payable is needed before making payment.

    try {
      if ($selected_year == null) {
        throw new Exception("Please select the payment Year");
      }

      $merchantOrderNo = $this->genMerchantOrderNo($db, (array)$selected_year, $firm_id);//<-generated for our reference
      $db->Begin();

      foreach ($selected_payments as $key => $item) {//savePayable will only update value if there is a duplicate payable data.
        $tot_employees_per_year = $item->pe_male + $item->pe_female + $item->pe_trans + $item->ce_male + $item->ce_female + $item->ce_trans;
        $payable[$key] = $pb->savePayable($db, $item, $tot_employees_per_year, $firm_id);
      }

      $sql = "insert into est.payments (firm_id, trnx_no_own, trnx_amount, status)
              values ($1,$2,$3,$4)
              returning id";
      $db->Query($sql, [$firm_id, $merchantOrderNo, $totalDueAmount, 'I']);//I-Initiated.
      $payment_id = $db->FetchAll()[0]['id'];

      foreach ($payable as $item) {
        $sql = "insert into est.payment_against_payables (payment_id, payable_id, tot_amt)
                values ($1,$2,$3)
                returning id";
        $db->Query($sql, [$payment_id, $item['payable_id'], $item['total_amount']]);
      }

      if ($merchantOrderNo != null && $totalDueAmount != null) {
        //uses the orderAPI
        $retVal = $this->initiateCANARA_RazorPay($db, ['trnx_no_own' => $merchantOrderNo, 'trnx_amt' => $totalDueAmount]);
      } else {
        $failure_reason = 'trnx_no_own or trnx_amount can not be null';
        $this->failure($db, (object)['trnx_no_own' => $merchantOrderNo, 'failure_reason' => $failure_reason]);
        throw new Exception($failure_reason);
      }

      $db->Commit();
      $retVal['message'] = "Payment Details are saved successfully.";
    } catch (\Throwable $th) {
      $db->RollBack();
      $retVal['message'] = $th->getMessage();
    }
    $db->DBClose();
    return $retVal;
  }

  function initiateOtherPayment($data): array//todo: enc this api end point
  {
//    $enc_data =
    $type = $data->pmt_category ?? null;
    $other_name = $data->other_category_name ?? null;
    $firm_id = $data->firm_id ?? null;
    $emp_count = $data->emp_count ?? null;
    $year = $data->year ?? null;
    $amount = $data->amount ?? null;
    $attachment_id = $data->attachment_id ?? null;

    if ($firm_id == null) {
      $retVal['message'] = "Firm id is required.";
      return $retVal;
    }

    $db = new \LWMIS\Common\PostgreDB();

    try {
      $db->Begin();

      $sql = "insert into est.payments (firm_id, trnx_amount, status,trnx_no_own)
              values ($1,$2,$3,est.gen_other_pay_trans_no($firm_id))
              returning id,trnx_no_own";
//
      $db->Query($sql, [$firm_id, $amount, 'I']);//I-Initiated.
      $pmt_row = $db->FetchAll();

      $payment_id = $pmt_row[0]['id'];
      $merchantOrderNo = $pmt_row[0]['trnx_no_own'];

      if ($type != null) {//<- use type to find the payment type
        $sql = "insert into est.other_payments (payment_id,firm_id,type,other_name,emp_count,year,amount)
                     values ($1,$2,$3,$4,$5,$6,$7)
                  returning id";
        $db->Query($sql, [$payment_id, $firm_id, $type, $other_name, $emp_count, $year, $amount]);
        $other_payment_id = $db->FetchAll()[0]['id'];

        if ($attachment_id != null && $other_payment_id != null) {
          $sql = "insert into doc.document_against_other_payments (attachment_id, other_payment_id)
                       values ($1,$2);";
          $db->Query($sql, [$attachment_id, $other_payment_id]);
        }

//        else {//some other payments doesn't require documents.
//          throw new Exception("Other Payment required more details !");
//        }

      } else {
        throw new Exception('Please select a payment category');
      }

      if ($merchantOrderNo != null && $amount != null) {
        //uses the orderAPI
        $retVal = $this->initiateCANARA_RazorPay($db, ['trnx_no_own' => $merchantOrderNo, 'trnx_amt' => $amount]);
      } else {
        $failure_reason = 'trnx_no_own or trnx_amount can not be null';
        $this->failure($db, (object)['trnx_no_own' => $merchantOrderNo, 'failure_reason' => $failure_reason]);
        throw new Exception($failure_reason);
      }

      $db->Commit();
      $retVal['message'] = "Payment Details are saved successfully.";
    } catch (\Throwable $th) {
      $db->RollBack();
      $retVal['message'] = $th->getMessage();
    }
    $db->DBClose();
    return $retVal;
  }


  function capture($datas): array
  {
    $retObj = ['message' => 'Invalid Payment.'];
    $crypto = new Encryption();

    $data = json_decode($crypto->decrypt($datas->enc_data, ENC_KEY));

    $trnx_no_own = $data->trnx_no_own ?? null;
    $razorpay_payment_id = $data->razorpay_payment_id ?? null;
    $razorpay_order_id = $data->razorpay_order_id ?? null;
    $razorpay_signature = $data->razorpay_signature ?? null;

    $db = new \LWMIS\Common\PostgreDB();
    $db->Begin();

    try {
      if (is_null($trnx_no_own) or is_null($razorpay_payment_id) or is_null($razorpay_signature)) {
        throw new Exception('Invalid input data.');
      }

      $sql = "UPDATE est.payments
                 SET trnx_no_gw = $1,
                     rzp_signature = $2
               WHERE trnx_no_own = $3 AND status = 'I'
           RETURNING *";
      $db->Query($sql, [$razorpay_payment_id, $razorpay_signature, $trnx_no_own]);
      $rows = $db->FetchAll();

      foreach ($rows as &$r) {
        $r['id'] = intval($r['id']);
        $r['trnx_amount'] = intval($r['trnx_amount']);
      }

      $trnx_amt = null;
      $c_id = null;

      if (count($rows) > 0) {
        $p = $rows[0];
        $c_id = $p['id'];
        $trnx_amt = $p['trnx_amount'];
      }

      if (\is_null($trnx_amt) or \is_null($c_id)) {
        throw new Exception('Invalid trnx_amount and payment id.');
      }

//      $rp = new \CMW\Common\ICICI_RazorPay();
      $rp = new \LWMIS\Est\CANARA_Razorpay();
      $cap_status = $rp->capturePayment($razorpay_payment_id, $trnx_amt, $razorpay_order_id, $razorpay_signature);

      // Log raw response from gateway
      if (isset($cap_status['raw_data'])) {
        $addl_data = $cap_status['raw_data'];
        $sql = 'INSERT INTO logs.payment_details (trnx_no_own, addl_data) VALUES ($1, $2)';
        $db->Query($sql, [$trnx_no_own, $addl_data]);
      }

      if ($cap_status['status'] === 'captured') {
        $retObj = $this->updateSuccessStatus($db, $cap_status, $trnx_no_own, $razorpay_payment_id);
      } else {
        $retObj = $this->failure($db, (object)['trnx_no_own' => $trnx_no_own, 'failure_reason' => $cap_status['error_description']]);
      }

      $db->Commit();
    } catch (Exception $e) {
      $db->RollBack();
      $retObj['message'] = \LWMIS\Common\ErrorHandler::custom($e);
    }
    $db->DBClose();
    $encVal['enc_data'] = $crypto->encrypt(json_encode($retObj), ENC_KEY);
    return $encVal;
  }

  function updateSuccessStatus(\LWMIS\Common\PostgreDB $db, array $cap_status, $trnx_no_own, $razorpay_payment_id, $other_pmt_id = null, string $remarks = null): array // Update success status
  {
//        For success status we are updating the paid_amt(est.payable), payment_against_payable, receipt, receipt_against_payable .
    $retObj['message'] = 'Can not update the payment success status';

    $sql = "UPDATE est.payments
               SET status = 'S',
                   trnx_no_gw = $1,
                   trnx_date = to_timestamp($2),
                   payment_mode = $3,
                   status_desc = null,
                   is_manually_cancelled = null,
                   cancelled_dt = null,
                   remarks = $4
             WHERE trnx_no_own = $5 AND status IN ('I', 'F')
             RETURNING id,firm_id,trnx_date,trnx_amount";

    $db->Query($sql, [$razorpay_payment_id, $cap_status['created_at'], $cap_status['method'], $remarks, $trnx_no_own]);
    $pmt_rows = $db->FetchAll();
    $pmt_id = intval($pmt_rows[0]['id']);
    $firm_id = $pmt_rows[0]['firm_id'];
    $trnx_date = $pmt_rows[0]['trnx_date'];
    $trnx_amount = $pmt_rows[0]['trnx_amount'];

    if ($other_pmt_id == null) {
      $sql = "select payable_id
                from est.payment_against_payables
               where payment_id = $1";
      $db->Query($sql, [$pmt_id]);
      $payable_id = $db->FetchAll();

      foreach ($payable_id as $item) {
        $sql = "select total_amt
                from est.payables
               where id = $1";
        $db->Query($sql, [$item['payable_id']]);
        $total_amt = $db->FetchAll()[0]['total_amt'];

        $sql = "update est.payables
                 set paid_amt = $2, bal_amt = $3
               where id = $1";
        $db->Query($sql, [$item['payable_id'], $total_amt, 0]);//bal_amt become 0 for paid est
      }
    }

    // Create Receipt

    $receipt = [
      'payment_method' => 'OLP',
      'payment_id' => $pmt_id,
      'trnx_dt' => $trnx_date,
      'trnx_ref_no' => $trnx_no_own,
      'user_id' => (new \LWMIS\Est\Firm())->getUserIdByFirmId($db, $firm_id)[0]['user_id'],
      'status' => 'SUCCESS',
      'receipt_amt' => $trnx_amount,
      'firm_id' => $firm_id
    ];

    $rpt = new Receipt();

    $rpt_row = $rpt->insertPaymentReceipt($db, (object)$receipt);
    $receipt_id = $rpt_row['id'];

    $receipt['receipt_id'] = $receipt_id;
    $receipt['payment_id'] = $pmt_id;

    if ($other_pmt_id == null) {//only applicable for CFP
      $rpt->insertReceiptAgainstPayable($db, (object)$receipt);
    }
//        generating receipt sms
    $rpt_data = base_convert($receipt['receipt_id'], 10, 32);
    $rpt_link_url = LOCAL_URL . '#/pr/' . $rpt_data;

//    var_dump('rpt pmt link',$rpt_link_url);
    try {
//      (new \LWMIS\Common\SMS_Message())->sendEstPaymentReceipt($db, $rpt_row['est_mobile'], $rpt_link_url);
      (new \LWMIS\Common\SMS_Message())->sendPaymentAcknowledgement($db, $rpt_row['est_mobile'], $rpt_row['employer_name']);
    } catch (\Throwable $th) {
      // Send SMS error should not stop creation of receipt. so just log it and proceed.
      error_log("Send SMS error: " . $th->getMessage());
    }

    $retObj['receipt_id'] = $receipt_id;
    $retObj['message'] = 'Payment captured & Receipt generated successfully.';

    return $retObj;
  }


  function verifyPayment($encData): array
  {
    $retObj = [];

    $cry = new Encryption();
    $data = json_decode($cry->decrypt($encData->enc_data, ENC_KEY));

    $trnx_no_own = $data->trnx_no_own ?? null;

    $db = new \LWMIS\Common\PostgreDB();
    $db->Begin();
    try {

      if (is_null($trnx_no_own)) {
        throw new Exception('Invalid transaction no.');
      }

      $sql = "SELECT p.*,
                     op.id as other_pmt_id
                FROM est.payments as p
           left join est.other_payments op on p.id = op.payment_id
               WHERE p.trnx_no_own = $1 AND p.status != 'S'";

//      SELECT p.status as ps,
//       op.id as other_payment_id,
//       (case when op.id::varchar IS NULL then 'CFP'
//             else 'OP'
//       end) as type
//FROM est.payments as p
//         left join est.other_payments op on p.id = op.payment_id
//WHERE p.trnx_no_own = '8/2022/3/03-03'
//      AND p.status != 'S';

      $db->Query($sql, [$trnx_no_own]);
      $rows = $db->FetchAll();

      foreach ($rows as &$r) {
        $r['id'] = intval($r['id']);
        $r['trnx_amount'] = floatval($r['trnx_amount']);
      }

      if ((count($rows) <= 0)) {
        throw new Exception('Invalid transaction no.');
      }
      $p_row = $rows[0];//todo change the c_id to pmt id

      if ($p_row['firm_id'] != null) {
        if ($p_row['other_pmt_id'] == null)//<- We don't need to check duplicate payment for other payment
          $this->duplicatePaymentCheck($db, (object)['id' => $p_row['firm_id']]);
      } else {
        throw new Exception("Firm detail is missing to find the duplicate payment check !");
      }

      /*** my code***/
      if (count($p_row) != 0) {
        $data->rzp_order_id = $p_row['rzp_order_id'];
        $data->trnx_amt = $p_row['trnx_amount'];
        $data->rzp_signature = $p_row['rzp_signature'];
        $data->trnx_no_own = $trnx_no_own;
        $data->other_pmt_id = $p_row['other_pmt_id'];
//        $data->c_id = $p_row['id'];
        $retObj = $this->verifyCanaraRazorpay($db, $data);
      } else {
        $failure_reason = 'Can not get the payment details!';
        $this->failure($db, (object)['trnx_no_own' => $trnx_no_own, 'failure_reason' => $failure_reason]);
        throw new Exception($failure_reason);
      }

      /*      switch ($gw_code) {
              case 'icici':
                $data->rzp_order_id = $p_row['rzp_order_id'];
                $data->trnx_amt = $p_row['trnx_amt'];
                $data->c_id = $p_row['c_id'];
                $retObj = $this->verifyICICI_RazorPay($db, $data);
                break;
              case 'hdfc':
                $data->trnx_no_gw = $p_row['trnx_no_gw'];
                $data->trnx_amt = $p_row['trnx_amt'];
                $data->c_id = $p_row['c_id'];
                $data->from_date = $p_row['from_date'];
                $data->hdfc_reference_no = $p_row['hdfc_reference_no'];
                $data->is_after_20_mins = $p_row['is_after_20_mins'];
                $data->is_after_45_mins = $p_row['is_after_45_mins'];
                // if ($data->is_after_20_mins === false && $data->is_manually_cancelled === true) {
                //   $data->is_manually_cancelled = false;
                // }
                $retObj = $this->verifyHDFC_CCAvenue($db, $data);
                // $retObj['from_date'] = $p_row['from_date'];
                break;
              default:
                $failure_reason = 'Payment Gateway yet to be integrated.';
                $this->failure($db, (object)['trnx_no_own' => $trnx_no_own, 'failure_reason' => $failure_reason]);
                throw new \Exception($failure_reason);
            }*/

      $db->Commit();
    } catch (\Throwable $th) {
      $db->RollBack();
      $retObj['message'] = $th->getMessage();
    }
    $retObj['rzp_id_key'] = PG_RP_KEY_ID;
    $encVal['enc_data'] = $cry->encrypt(json_encode($retObj), ENC_KEY);
    $db->DBClose();

    return $encVal;
  }

  /**
   * @throws Exception
   */
  private function verifyCanaraRazorpay($db, $data): array
  {
    $retObj = [];
    $rzp_order_id = $data->rzp_order_id;
    $trnx_no_own = $data->trnx_no_own;
    $rzp_signature = $data->rzp_signature;
    $trnx_amount = $data->trnx_amt;
    $other_pmt_id = $data->other_pmt_id;

//    $trnx_amt = $data->trnx_amt;
//    $c_id = $data->id;//
    // $rzp_order_id = $p_row['rzp_order_id'];
    // ['rzp_order_id' => $rzp_order_id, 'trnx_no_own' => $trnx_no_own]

    if (\is_null($rzp_order_id)) {
      throw new Exception('RazorPay order details not found.');
    }

//    if (\is_null($trnx_amt) or \is_null($c_id)) {
//    if (\is_null($trnx_amt)) {
//      throw new \Exception('Invalid input data.');
//    }

    $rp = new \LWMIS\Est\CANARA_Razorpay();
    $rzpPaymentDetails = $rp->getPaymentsByOrderId($rzp_order_id);//<- has raw_data
    $rzpPayments = $rzpPaymentDetails['payments'];

//    var_dump($rzpPaymentDetails);

    // Log raw response from gateway
    if (isset($rzpPaymentDetails['raw_data'])) {
      $addl_data = $rzpPaymentDetails['raw_data'];
      $sql = 'INSERT INTO logs.payment_details (trnx_no_own, addl_data) VALUES ($1, $2)';
      $db->Query($sql, [$trnx_no_own, $addl_data]);
    }

    $is_captured = false;
    $authorized_cnt = 0;
    $authorized_last_pmt = null;
    $fail_cnt = 0;
    $failed_last_pmt = null;
    $awaited_cnt = 0;
    $awaited_last_p = null;
    foreach ($rzpPayments as &$p) {
      switch ($p['status']) {
        case 'captured'://note: capturing payment on 2nd time will give an error.

//          We use fetch API to check the auto-captured payments to update the payment details on own server.
//          put failure_reason in status_desc

//          $sql = "UPDATE est.payments
//                     SET status = 'S',
//                         payment_mode = $4,
//                         trnx_date = to_timestamp($2),
//                         status_desc = null,
//                         trnx_no_gw = $1,
//                         is_manually_cancelled = null,
//                         cancelled_dt = null,
//                         remarks = 'Post verified.'
//                   WHERE trnx_no_own = $3 AND status IN ('I', 'F')
//               RETURNING * ";
//          $db->Query($sql, [$p['trnx_no_gw'], $p['trnx_date'], $trnx_no_own, $p['method']]);

          $razorpay_payment_id = $p['id'];
          $retObj = $this->updateSuccessStatus($db, $rzpPaymentDetails, $trnx_no_own, $razorpay_payment_id, $other_pmt_id);
          $is_captured = true;
//          $retObj['message'] = 'Payment verified successfully.';
          # should break foreach loop too
          break 2;
        case 'authorized':
          $authorized_cnt += 1;
          $authorized_last_pmt = $p;
          break;
        case 'created':
          $awaited_cnt += 1;
          $awaited_last_p = $p;
          break;
        default:
          $fail_cnt += 1;
          $failed_last_pmt = $p;
          break;
      }
    }

    if ($is_captured === false) {
      $retObj['payments'] = $rzpPayments;
      // to handle all fail (with last fail message)
      if (count($rzpPayments) === $fail_cnt) {
        if (isset($data->is_manually_cancelled) && $data->is_manually_cancelled === true) {

          // Update rzp payment id and created_at
          if (isset($failed_last_pmt['trnx_no_gw']) && isset($failed_last_pmt['trnx_dt_gw'])) {
            $sql = 'UPDATE est.payments
                       SET trnx_no_gw = $1,
                           trnx_date = to_timestamp($2),
                           remarks = \'' . $fail_cnt . ' failed transactions details received from CANARA bank.\'
                     WHERE trnx_no_own = $3 AND status = \'I\'
                 RETURNING *';
            $db->Query($sql, [$failed_last_pmt['trnx_no_gw'], $failed_last_pmt['trnx_dt_gw'], $trnx_no_own]);
          }

          // to use common failure update function
          $failure_reason = isset($failed_last_pmt['error_description']) ? ($failed_last_pmt['error_description']) : (isset($data->failure_reason) ? $data->failure_reason : null);
          $this->failure($db, (object)['failure_reason' => $failure_reason, 'trnx_no_own' => $trnx_no_own, 'is_manually_cancelled' => true]);

          $retObj['message'] = 'Transaction marked as failure after verification with Payment Gateway.';
        } else {
          $retObj['message'] = 'Retry?';
        }
      } else if ($authorized_cnt > 0) {
//       authorized status mean payment is collected from the customer but not deposited to lwb 's bank a/c.
//       all authorized payment must be captured manually / automatically to deposit it to the board's bank account.

        // to handle if at least one authorized (with last authorized transaction)
        // capture last authorized

        $cap_status = $rp->capturePayment($authorized_last_pmt['trnx_no_gw'], $trnx_amount, $rzp_order_id, $rzp_signature);

        // Log raw response from gateway
        if (isset($cap_status['raw_data'])) {
          $addl_data = $cap_status['raw_data'];
          $sql = 'INSERT INTO logs.payment_details (trnx_no_own, addl_data) VALUES ($1, $2)';
          $db->Query($sql, [$trnx_no_own, $addl_data]);
        }

        // Update payment status
        if ($cap_status['status'] === 'captured') { // Update success status

          $razorpay_payment_id = $cap_status['id'];//<- check this
          $retObj = $this->updateSuccessStatus($db, $cap_status, $trnx_no_own, $razorpay_payment_id, 'Post verified (authorized -> captured).');

//          $sql = 'UPDATE est.payments
//                     SET status = \'S\', trnx_no_gw = $1, trnx_date = to_timestamp($2),
//                         status_desc = null, is_manually_cancelled = null, cancelled_dt = null,
//                         remarks = \'Post verified (authorized -> captured).\'
//                   WHERE trnx_no_own = $3 AND status IN (\'I\', \'F\')
//               RETURNING *';
//          $db->Query($sql, [$authorized_last_pmt['trnx_no_gw'], $cap_status['created_at'], $trnx_no_own]);

          // Create Receipt
//          $retObj = $this->create_receipt($db, $c_id, $trnx_amt, $trnx_no_own);
//          $retObj['message'] = 'Payment verified successfully.';
        }
      } else if ($awaited_cnt > 0) {
        $retObj['message'] = 'Bank not yet confirmed your Payment. Please wait or try again.';
      }
    }
    return $retObj;
  }

//  private function create_receipt($db, $c_id, $trnx_amt, $trnx_no_own) {
//    $sql = 'SELECT id, receipt_no FROM bnc.receipts WHERE c_id = $1 AND trnx_no_own = $2';
//    $db->Query($sql, [$c_id, $trnx_no_own]);
//    $rows = $db->FetchAll();
//    foreach ($rows as &$r) {
//      $r['id'] = intval($r['id']);
//      // $r['tot_amt'] = floatval($r['tot_amt']);
//      // $r = (object)$r;
//    }
//
//    if (count($rows) > 0 && isset($rows[0]['id']) && $rows[0]['id'] > 0) {
//      return [ 'id' => $rows[0]['id'], 'receipt_no' => $rows[0]['receipt_no'] ];
//      // throw new \Exception('Receipt already created for this transaction. Please contact CMWSSB IT for assistance.');
//    }
//
//    $r_data = [
//      'c_id' => $c_id,
//      'collection_type' => 'R',
//      'receipt_type' => 'P',
//      'tot_receipt_amt' => $trnx_amt,
//      'payment_method_code' => 'OLP',
//      'trnx_no_own' => $trnx_no_own,
//    ];
//
//    $sql = 'SELECT id, demand_type_code, demand_type_amt AS tot_amt
//                FROM bnc.payment_dts
//                WHERE trnx_no_own = $1
//                ORDER BY id';
//    $db->Query($sql, [$trnx_no_own]);
//    $r_dts = $db->FetchAll();
//    foreach ($r_dts as &$r) {
//      $r['id'] = intval($r['id']);
//      $r['tot_amt'] = floatval($r['tot_amt']);
//      $r = (object)$r;
//    }
//
//    $rcpt = new \CMW\BNC\Receipt();
//    return $rcpt->insertReceipt($db, (object)$r_data, $r_dts);
//  }

  private function failure($db, $data): array
  {
    $retObj = [];
    $set_clause = '';
    $failure_reason = $data->failure_reason ?? null;
    $trnx_no_own = $data->trnx_no_own ?? null;

    if (isset($data->is_manually_cancelled) && $data->is_manually_cancelled === true) {
      $set_clause .= ", is_manually_cancelled = 't'";
    }

    if (!is_null($trnx_no_own)) {
      $sql = "UPDATE est.payments
                 SET status = 'F',
                     cancelled_dt = CURRENT_TIMESTAMP,
                     status_desc = $1,
                     remarks = CASE WHEN COALESCE(remarks, '') = 'Awaited' THEN NULL ELSE remarks END
                     $set_clause
               WHERE trnx_no_own = $2 AND status = 'I'";

      $db->Query($sql, [$failure_reason, $trnx_no_own]);

      $retObj['message'] = 'Payment cancelled successfully.';
    }

    return $retObj;
  }

  /**
   * @throws Exception
   */
  function duplicatePaymentCheck(\LWMIS\Common\PostgreDB $db, $data): void
  {
    $params = [];
    $where_clause = null;

    $selected_years = $data->selected_payments ?? null;
    $firm_id = $data->id ?? null;
    $trnx_no_own = $data->trnx_no_own ?? null;

    if ($trnx_no_own != null) {
//      To check the payments made for years
      $sql = "select pb.year,pb.firm_id,pmt.status
                from lwb.est.payables pb
          inner join est.payment_against_payables pap on pb.id = pap.payable_id
          inner join est.payments pmt on pap.payment_id = pmt.id
               where pmt.trnx_no_own = $1";

      $db->Query($sql, [$trnx_no_own]);
      $paid_dt = $db->FetchAll();

      $firm_id = $paid_dt[0]['firm_id'];

      foreach ($paid_dt as $year) {
        $selected_years[] = $year['year'];
      }

      $params[] = $trnx_no_own;
      $where_clause .= ' and pmt.trnx_no_own != $' . count($params);
    }

    if ($firm_id != null) {
      $params[] = $firm_id;
      $where_clause .= " and pb.firm_id = $" . count($params);
    }

    if ($selected_years != null && count($selected_years) > 0) {
      $year = '';
      foreach ($selected_years as $selected_year) {
        $year .= "$selected_year,";
      }
      $params[] = rtrim($year, ",");
      $where_clause .= " and pb.year in ($" . count($params) . ")";
    }

    $sql = "select pmt.status,pb.*
              from lwb.est.payables pb
        inner join est.payment_against_payables pap on pb.id = pap.payable_id
        inner join est.payments pmt on pap.payment_id = pmt.id
             where pmt.status = 'S' and true $where_clause ";

    $db->Query($sql, $params);
    $rows = $db->FetchAll();

    if (count($rows) > 0) {
      throw new Exception("Duplicate payment is not allowed.");
    }

  }
  #endregion RazorPay Payment Process

}
