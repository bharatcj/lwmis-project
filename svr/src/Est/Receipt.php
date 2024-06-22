<?php

namespace LWMIS\Est;

class Receipt
{
  function insertPaymentReceipt($db, $data): array
  {
//    var_dump("data====",$data);
    $rows = [];
    $payment_method = $data->payment_method ?? null;
    $firm_id = $data->firm_id ?? null;
    $payment_id = $data->payment_id ?? null;
    $receipt_dt = $data->receipt_dt ?? 'now()';
    $receipt_no = $data->receipt_no ?? null;
    $ifsc = $data->ifsc ?? null;
    $chq_no = $data->chq_no ?? null;
    $chq_dt = $data->chq_dt ?? null;
    $bank_code = $data->bank_code ?? null;
    $card_type = $data->card_type ?? null;
    $card_no = $data->card_no ?? null;
    $card_exp_dt = $data->card_exp_dt ?? null;
    $trnx_dt = $data->trnx_dt ?? null;
    $trnx_ref_no = $data->trnx_ref_no ?? null;
    $receipt_amt = $data->receipt_amt ?? null;
    $counter_id = $data->counter_id ?? null;
    $user_id = $data->user_id ?? null;
    $is_cancelled = $data->is_cancelled ?? null;
    $cancel_ts = $data->cancel_ts ?? null;
    $cancel_reason = $data->cancel_reason ?? null;
    $cancel_receipt_id = $data->cancel_receipt_id ?? null;
    $status = $data->status ?? null;

    $sql = "select lwb_reg_no, est_mobile, employer_name
              from est.firms
             where id = $1";
    $db->Query($sql, [$firm_id]);
    $firm_row = $db->FetchAll();

//    $sql = "select est.get_receipt_no($1,$2,$3)";
//    $db->Query($sql, [$payment_method, $lwb_reg_no, $counter_id]);

    if (is_null($receipt_no)) {
//      var_dump('the recpt no is ', $receipt_no);
      $receipt_no_field = "est.get_receipt_no($1, $23, $13)";
      $receipt_no = $firm_row[0]['lwb_reg_no'];
    } else {
      $receipt_no_field = '$23';
    }
    //note: cre_ts : permanent date, receipt dt can be used if we update the est.receipts
    $sql = "INSERT INTO est.receipts (payment_mode, payment_id, ifsc, chq_no, chq_dt, bank_code,
                                        card_type, card_no, card_exp_dt, trnx_dt, trnx_ref_no, receipt_amt,
                                        counter_id, user_id, is_cancelled, cancel_ts, cancel_reason, cancel_receipt_id,
                                        cre_ts, status, receipt_dt ,firm_id,receipt_no)
                           VALUES ($1, $2, $3, $4, $5, $6,
                                   $7, $8, $9, $10, $11, $12,
                                   $13, $14, $15, $16, $17, $18,
                                   $19, $20, $21, $22, $receipt_no_field)
                       RETURNING id";

    $db->Query($sql, [$payment_method, $payment_id, $ifsc, $chq_no, $chq_dt, $bank_code,//6
      $card_type, $card_no, $card_exp_dt, $trnx_dt, $trnx_ref_no, $receipt_amt,//12
      $counter_id, $user_id, $is_cancelled, $cancel_ts, $cancel_reason, $cancel_receipt_id,//18
      'now()', $status, $receipt_dt, $firm_id, $receipt_no]);//23

    $rows = $db->FetchAll()[0];

    $rows['id'] = intval($rows['id']);
    $rows['est_mobile'] = $firm_row[0]['est_mobile'];
    $rows['lwb_reg_no'] = $firm_row[0]['lwb_reg_no'];
    $rows['employer_name'] = $firm_row[0]['employer_name'];

    return $rows;
  }

  function insertReceiptAgainstPayable($db, $data): void
  {
    $receipt_id = $data->receipt_id ?? null;
    $payment_id = $data->payment_id ?? null;

    $sql = "select payable_id,tot_amt
            from est.payment_against_payables
            where payment_id = $1";

    $db->Query($sql, [$payment_id]);
    $pap = $db->FetchAll();

    foreach ($pap as $payable) {
      $sql = "insert into est.receipt_against_payables(receipt_id, payable_id, tot_amt)
              values ($1,$2,$3)";
      $db->Query($sql, [$receipt_id, $payable['payable_id'], $payable['tot_amt']]);
    }

  }


//  function getReceipt($filter): false|array
//  {
//    $where_clause = '';
//    $params = [];
//
//    if (isset($filter->trnx_no_own) && is_string($filter->trnx_no_own)) {
//      $trnx_no_own = $filter->trnx_no_own;
//      $params[] = $trnx_no_own;
//      $where_clause .= ' AND a.trnx_ref_no = $' . count($params);
//    }
//
//    if (isset($filter->receipt_no) && is_string($filter->receipt_no)) {
//      $receipt_no = $filter->receipt_no;
//      $params[] = $receipt_no;
//      $where_clause .= ' AND a.receipt_no = $' . count($params);
//    }
//
//    if (isset($filter->receipt_id)) {
//      $receipt_id = $filter->receipt_id;
//      $params[] = $receipt_id;
//      $where_clause .= ' AND a.id = $' . count($params);
//    }
//
//    if (isset($filter->search_dd) && is_string($filter->search_dd)) {
//      $search_dd = $filter->search_dd;
//      $params[] = $search_dd;
//      $where_clause .= ' AND UPPER(a.chq_no) like UPPER($' . count($params) . ')';
//    }
//
//    $sql = 'SELECT a.id, a.receipt_no, a.receipt_dt,a.payment_mode ,a.chq_no ,a.chq_dt ,a.trnx_dt ,a.trnx_ref_no ,a.receipt_amt ,
//                       a.is_cancelled, a.cancel_ts,a.cancel_reason , a.cancel_receipt_id ,a.verify_status ,a.status,
//                       (CASE WHEN a.status = \'C\' THEN \'Cancelled Receipt\'
//                             WHEN a.verify_status = \'TBV\' THEN \'To be Verified\'
//	                         WHEN a.verify_status = \'V\' THEN \'Verified\'
//	                         WHEN a.verify_status = \'CLR\' THEN \'Clarification Requested\'
//	                         WHEN a.verify_status = \'CLD\' THEN \'Clarified\'
//	                         ELSE NULL END)verify_status_d,
//                             sum(COALESCE(e.paid_amt,0))AS paid_amt,(a.receipt_amt -sum(COALESCE(e.paid_amt,0)))AS remaining_amt,
//                       f.name AS firm_name, f.address AS firm_address, f.lwb_reg_no,f.locality, f.postal_code, f.landmark, f.id AS firm_id,
//                       g.name AS district_name,
//                       h.name AS panchayat_name,
//                       i.name AS taluk_name
//                  FROM est.receipts AS a
//                       LEFT OUTER JOIN est.payments AS b ON (a.trnx_ref_no=b.trnx_no_own)
//                       LEFT OUTER JOIN est.receipt_against_payables AS c ON (c.receipt_id = a.id)
//                       LEFT OUTER JOIN est.payment_against_payables AS d ON (d.payment_id = b.id)
//                       LEFT OUTER JOIN est.payables AS e ON (e.id = c.payable_id OR d.payable_id=e.id)
//                       LEFT OUTER JOIN est.firms AS f ON (e.firm_id = f.id)
//                       LEFT OUTER JOIN mas.districts AS g ON (f.district_id = g.id)
//                       LEFT OUTER JOIN mas.panchayats AS h ON (f.panchayat_id = h.id)
//                       LEFT OUTER JOIN mas.taluks AS i ON (f.taluk_id = i.id)
//                 WHERE TRUE ' . $where_clause . '
//              GROUP BY a.id, a.receipt_no, a.receipt_dt, a.payment_mode, a.chq_no, a.chq_dt, a.trnx_dt, a.trnx_ref_no,a.receipt_amt,
//                       a.is_cancelled,a.cancel_ts,a.cancel_reason, a.cancel_receipt_id,a.verify_status,a.status,a.cre_ts,
//                       f.name,f.address, f.lwb_reg_no, f.locality, f.postal_code, f.landmark, f.id,
//                       g.name,h.name,i.name
//              ORDER BY a.cre_ts DESC;';
//    $db = new \LWMIS\Common\PostgreDB();
//    $db->Query($sql, $params);
//    $rows = $db->FetchAll();
//    $db->DBClose();
//    foreach ($rows as &$r) {
//      $r['amount'] = floatval($r['receipt_amt']);
//      $r['is_cancelled'] = isset($r['is_cancelled']) ? ($r['is_cancelled'] == 't') : null;
//      $r['cancel_receipt_id'] = isset($r['cancel_receipt_id']) ? intval($r['cancel_receipt_id']) : null;
//      $r['firm_id'] = isset($r['firm_id']) ? intval($r['firm_id']) : null;
//    }
//    return (count($rows) > 0) ? $rows : false;
//  }

  function getReceiptDetail($filter): array
  {
    $retObj = ['rows' => []];
    $where_clause = '';
    $receipt_id = $receipt_id ?? null;
    $params = [];


    if (isset($filter->id) && ($filter->id) > 0) {
      $firm_id = $filter->id;
      $params[] = $firm_id;
      $where_clause .= ' AND e.firm_id = $' . count($params);
    }

    if (isset($filter->trnx_no_own) && is_string($filter->trnx_no_own)) {
      $trnx_no_own = $filter->trnx_no_own;
      $params[] = $trnx_no_own;
      $where_clause .= ' AND a.trnx_ref_no = $' . count($params);
    }

//    if (isset($filter->receipt_id) && (is_string($filter->receipt_id) || is_int($filter->receipt_id))) {
    if (is_null($receipt_id)) {
      $params[] = $filter->receipt_id;
      $where_clause .= ' AND a.id = $' . count($params);
    }

    if (isset($filter->receipt_no) && is_string($filter->receipt_no)) {
      $receipt_no = $filter->receipt_no;
      $params[] = $receipt_no;
      $where_clause .= ' AND a.receipt_no = $' . count($params);
    }

    $sql = "SELECT a.id      as        receipt_id,
                   a.payment_id,
                   e.id      as        payable_id,
                   a.receipt_no,
                   a.receipt_dt,
                   a.payment_mode,
                   a.ifsc,
                   a.chq_no,
                   a.chq_dt,
                   a.bank_code,
                   a.card_type,
                   a.card_no,
                   a.card_exp_dt,
                   a.trnx_dt,
                   a.trnx_ref_no,
                   c.trnx_no_gw,
                   a.receipt_amt,
                   a.counter_id,
                   a.user_id,
                   a.cancel_ts,
                   a.cancel_reason,
                   a.cancel_receipt_id,
                   a.verify_status,
                   a.receipt_amt as amount,
                   a.status,
                   a.remarks,
                   f.lwb_reg_no,
                   f.name    AS        firm_name,
                   f.address as        firm_address,
                   f.est_phone,
                   f.landmark,
                   f.taluk_id,
                   f.postal_code,
                   e.bal_amt,
                   e.year,
                   e.tot_employees,
                   e.employee_amt,
                   e.total_cntrbtn,
                   e.employer_amt,
                   e.total_amt,
                   e.employee_cntrbtn,
                   e.employer_cntrbtn,
                   dt.name   as        district_name,
                   tk.name   as        taluk_name,
                   pt.name   as        panchayat_name,
                   coalesce(opmt.pmt_name,'Contribution Fund') as paid_for,
                   coalesce(opmt.type,'CF') as paid_for_code
              FROM est.receipts AS a
         LEFT JOIN est.receipt_against_payables AS b ON (a.id = b.receipt_id)
         LEFT JOIN est.payments AS c ON (a.payment_id = c.id)
         --LEFT OUTER JOIN est.payment_against_payables AS d ON (d.payment_id = c.id)
         --LEFT OUTER JOIN est.payables AS e ON (e.id = b.payable_id OR e.id = d.payable_id)
         LEFT JOIN est.payables AS e ON (e.id = b.payable_id)
         LEFT JOIN est.firms AS f ON (a.firm_id = f.id)
         LEFT JOIN mas.districts dt ON f.district_id = dt.id
         LEFT JOIN mas.taluks tk on tk.id = f.taluk_id
         LEFT JOIN mas.panchayats pt on pt.id = f.panchayat_id
         LEFT JOIN (select op.type,
                           op.payment_id,
                           coalesce(op.other_name,opc.name) as pmt_name
                      from est.other_payments op
                 LEFT JOIN (select code,name from mas.other_payment_category) as opc on opc.code = op.type
                   )as opmt on opmt.payment_id = a.payment_id
             WHERE TRUE $where_clause;";

//    var_dump($sql);

//    $sql = 'SELECT a.id, a.receipt_no ,a.receipt_dt ,a.payment_mode ,a.ifsc ,a.chq_no ,a.chq_dt ,
//                       a.bank_code ,a.card_type ,a.card_no ,a.card_exp_dt ,a.trnx_dt ,a.trnx_ref_no ,
//                       a.receipt_amt ,a.counter_id ,a.user_id ,a.cancel_ts ,a.cancel_reason ,
//                       a.cancel_receipt_id ,a.verify_status ,a.status ,a.remarks,
//                       (CASE WHEN a.status = \'C\' THEN \'Cancelled Receipt\'
//                             WHEN a.verify_status = \'TBV\' THEN \'To be Verified\'
//	                         WHEN a.verify_status = \'V\' THEN \'Verified\'
//	                         WHEN a.verify_status = \'CLR\' THEN \'Clarification Requested\'
//	                         WHEN a.verify_status = \'CLD\' THEN \'Clarified\'
//	                         ELSE NULL END)verify_status_d,
//                       f.name AS firm_name, f.address, f.est_phone,
//                       e.bal_amt,e.year,e.tot_employees,e.employee_amt,e.total_cntrbtn,
//                       e.employer_amt,e.total_amt,e.employee_cntrbtn, e.employer_cntrbtn
//                  FROM est.receipts AS a
//                       LEFT OUTER JOIN est.receipt_against_payables AS b ON (a.id = b.receipt_id)
//                       LEFT OUTER JOIN est.payments AS c ON (a.trnx_ref_no = c.trnx_no_own)
//                       LEFT OUTER JOIN est.payment_against_payables AS d ON (d.payment_id = c.id)
//                       LEFT OUTER JOIN est.payables AS e ON (e.id = b.payable_id OR e.id = d.payable_id)
//                       LEFT OUTER JOIN est.firms AS f ON (e.firm_id = f.id)
//                 WHERE TRUE ' . $where_clause . ';';

    $db = new \LWMIS\Common\PostgreDB();
    $db->Query($sql, $params);
    $rows = $db->FetchAll();
    $db->DBClose();
    foreach ($rows as &$r) {
//      $r['id'] = intval($r['id']);
      $r['amount'] = floatval($r['receipt_amt']);
//      $r['is_cancelled'] = isset($r['is_cancelled']) ? ($r['is_cancelled'] == 't') : null;
//      $r['cancel_receipt_id'] = isset($r['cancel_receipt_id']) ? intval($r['cancel_receipt_id']) : null;
    }
    $retObj['rows'] = $rows;
    // var_dump($retObj);
    return $retObj;
  }

  function UpdatePayment($data)
  {
    $retObj['message'] = 'Failed';

    $id = isset($data->id) ? $data->id : null;
    $verify_status_d = isset($data->verify_status_d) ? $data->verify_status_d : null;
    $status = isset($data->status) ? $data->status : null;
    $verify_status = isset($data->verify_status) ? $data->verify_status : null;
    $cancel_reason = isset($data->cancel_reason) ? $data->cancel_reason : null;

    $db = new \LWMIS\Common\PostgreDB();
    if (!is_null($id)) {
      try {
        if ($verify_status_d === 1) {
          $verify_status = 'CLR';
        }

        if ($verify_status_d === 2) {
          $verify_status = 'CLD';
        }

        if ($verify_status_d === 3) {
          $verify_status = 'V';
        }

        if ($verify_status_d === 4) {
          $status = 'C';
          $sql = 'SELECT a.*
                              FROM est.payment_against_payables AS a
                                   INNER JOIN est.payments AS b ON (a.payment_id = b.id)
                                   INNER JOIN est.receipts AS c ON (c.trnx_ref_no = b.trnx_no_own)
                             WHERE c.id=$1;';
          $db->Query($sql, [$id]);
          $row = $db->FetchAll();
          if (count($row) > 0) {
            foreach ($row as &$r) {
              $tot_amt = isset($r['tot_amt']) ? intval($r['tot_amt']) : 0;
              $payable_id = isset($r['payable_id']) ? intval($r['payable_id']) : 0;
              $payment_id = isset($r['payment_id']) ? intval($r['payment_id']) : 0;

              $sql = 'UPDATE est.payables
                                       SET paid_amt = paid_amt - $1, bal_amt = bal_amt + $1
                                     WHERE id = $2';
              $db->Query($sql, [$tot_amt, $payable_id]);

              $sql = 'UPDATE est.receipts
                                       SET verify_status = $1, status = $2, cancel_reason = $3, is_cancelled =\'t\', cancel_ts=now()
                                     WHERE id = $4 RETURNING id';
              $db->Query($sql, [$verify_status, $status, $cancel_reason, $id]);
            }
          }
        }
        if ($verify_status_d != 4) {
          $sql = 'UPDATE est.receipts
                           SET verify_status = $1, status = $2
                         WHERE id = $3 RETURNING id';
          $db->Query($sql, [$verify_status, $status, $id]);
        }
        $rows = $db->FetchAll();
        if (count($rows) > 0) {
          $retObj['message'] = 'Status Changed Successfully.';
        }
        $db->Commit();
      } catch (\Exception $e) {
        $db->RollBack();
        $retObj['message'] = $e->getMessage();
      }
    }
    $db->DBClose();

    return $retObj;

  }

  function saveDdChq($data)
  {
//    todo: remove this function if already paid is not used.
    $retObj = [];
    $rows = [];
    $chq_no = isset($data->chq_no) ? $data->chq_no : null;
    $chq_dt = isset($data->chq_dt) ? $data->chq_dt : null;
    $payment_mode = isset($data->payment_mode) ? $data->payment_mode : null;
    $receipt_amt = isset($data->receipt_amt) ? $data->receipt_amt : null;
    $counter_id = isset($data->counter_id) ? $data->counter_id : 1;
    $user_id = isset($data->user_id) ? $data->user_id : null;
    $lwb_reg_no = isset($data->firm->lwb_reg_no) ? $data->firm->lwb_reg_no : null;
    $firm = isset($data->firm) ? $data->firm : null;
    $lwb_reg_no_e = explode('/', $lwb_reg_no);
    $lwmis_reg_id = $lwb_reg_no_e[count($lwb_reg_no_e) - 1];
    $is_has_pay = isset($data->is_has_pay) ? $data->is_has_pay : null;
    $firm_id = isset($data->firm->id) ? $data->firm->id : null;
    $id = isset($data->id) ? $data->id : null;
    // die();
    $db = new \LWMIS\Common\PostgreDB();
    try {
      if (is_null($id)) {
        $sql = 'INSERT INTO est.receipts (receipt_dt, payment_mode, chq_no, chq_dt, receipt_amt, counter_id, user_id, cre_ts, receipt_no)
                VALUES (now(), $1, $2, $3, $4, $5, $6, now(), est.generate_receipt_no($5, $7, $1))
                RETURNING id';
        $db->Query($sql, [$payment_mode, $chq_no, $chq_dt, $receipt_amt, $counter_id, $user_id, $lwmis_reg_id]);
        $rows = $db->FetchAll();
      }
      if (count($rows) > 0) {
        $payable = new \LWMIS\Est\Payable();
        $remaing_bal = $this->remainingBal($db, $firm)[0]['remaining_amt'];
        $pay = [
          'receipt_id' => intval($rows[0]['id']),
          'amount' => $receipt_amt,
          'is_has_pay' => $is_has_pay,
          'firm_id' => $firm_id,
          'remaing_bal' => $remaing_bal
        ];
        $payable->payableAgainstReceipt($db, $pay);
        $retObj['id'] = intval($rows[0]['id']);
        $retObj['message'] = 'Saved successfully.';
      }
      $db->Commit();
    } catch (\Exception $e) {
      $db->RollBack();
      $retObj['message'] = $e->getMessage();
    }

    $db->DBClose();

    return $retObj;
  }

  function remainingBal($db, $data)
  {
    $rows = [];
    $firm_id = isset($data['firm_id']) ? $data['firm_id'] : null;

    $sql = 'SELECT sum(a.remaining_amt)AS remaining_amt
                       FROM (SELECT a.receipt_no ,a.receipt_amt,
                                    (CASE WHEN sum(COALESCE(c.tot_amt ,0)) =0
                                          THEN sum(COALESCE(d.tot_amt ,0))
                                          ELSE sum(COALESCE(c.tot_amt ,0)) END
                                    )AS paid_amt,
                            (a.receipt_amt -(CASE WHEN sum(COALESCE(c.tot_amt ,0)) =0 THEN sum(COALESCE(d.tot_amt ,0)) ELSE sum(COALESCE(c.tot_amt ,0)) END ))AS remaining_amt
                       FROM est.receipts AS a
                            LEFT OUTER JOIN est.payments AS b ON (a.trnx_ref_no=b.trnx_no_own)
                            LEFT OUTER JOIN est.receipt_against_payables AS c ON (c.receipt_id = a.id)
                            LEFT OUTER JOIN est.payment_against_payables AS d ON (d.payment_id = b.id)
                            LEFT OUTER JOIN est.payables AS e ON (e.id = c.payable_id OR d.payable_id=e.id)
                       WHERE e.firm_id =$1
                       GROUP BY a.receipt_no ,a.receipt_amt)AS a;';
    $db->Query($sql, [$firm_id]);
    $rows = $db->FetchAll();
    if (count($rows) > 0) {
      foreach ($rows as &$r) {
        $r['remaining_amt'] = intval($r['remaining_amt']);
      }
    }
    return $rows;

  }
}
