<?php

namespace LWMIS\Est;

class Payable
{
  /*  function SavePayable($db, $data)
    {
        $retVal = ['message' => 'Payable cannot be saved.'];
        $firm_id          = isset($data->firm_id) ? $data->firm_id : null;
        $year             = isset($data->year) ? $data->year : null;
        $tot_employees    = isset($data->tot_employees) ? $data->tot_employees : null;
        $employee_cntrbtn = isset($data->employee_cntrbtn) ? $data->employee_cntrbtn : null;
        $employer_cntrbtn = isset($data->employer_cntrbtn) ? $data->employer_cntrbtn : null;
        $total_cntrbtn    = isset($data->total_cntrbtn) ? $data->total_cntrbtn : null;
        $employee_amt     = isset($data->employee_amt) ? $data->employee_amt : null;
        $employer_amt     = isset($data->employer_amt) ? $data->employer_amt : null;
        $total_amt        = isset($data->total_amt) ? $data->total_amt : null;
        $remarks          = isset($data->remarks) ? $data->remarks : null;
        $bal_amt          = isset($data->bal_amt) ? $data->bal_amt : null;
        $sql = 'SELECT * FROM est.payables WHERE firm_id = $1 AND year=$2';
        $db->Query($sql, [$firm_id, $year]);
        $rows = $db->FetchAll();

        if (!count($rows)>0) {

            $sql = 'INSERT INTO est.payables
                                ( firm_id, year, tot_employees, employee_cntrbtn, employer_cntrbtn, total_cntrbtn, employee_amt, employer_amt, total_amt, bal_amt, remarks )
                    VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $9, $10)
                    RETURNING id';
            $db->Query($sql, [ $firm_id, $year, $tot_employees, $employee_cntrbtn, $employer_cntrbtn, $total_cntrbtn, $employee_amt, $employer_amt, $total_amt, $remarks]);
            $rows = $db->FetchAll();
            foreach ($rows as &$r) {
                $r['id'] = intval($r['id']);
            }
            if (count($rows) > 0) {
                $retVal['id'] = $rows[0]['id'];
                $retVal['message'] = "Payable saved successfully.";
            }
        } else {
            $sql = 'UPDATE est.payables
                    SET tot_employees =$3, employee_cntrbtn =$4, employer_cntrbtn =$5, total_cntrbtn =$6, employee_amt =$7, employer_amt =$8, total_amt =$9, bal_amt=$9, remarks=$10
                    WHERE firm_id =$1 AND year =$2';
            $db->Query($sql, [$firm_id, $year, $tot_employees, $employee_cntrbtn, $employer_cntrbtn, $total_cntrbtn, $employee_amt, $employer_amt, $total_amt, $remarks]);
            $retVal['message'] = "Payable update successfully.";
        }

        return $retVal;
    }*/

  /**
   * @throws \Exception
   */
  function savePayable($db, $data, $tot_employees, $firm_id = null): array//<- Work Only for single savePayable
  {
//    var_dump('data=',$data);
    if ($firm_id == null) {
      $firm_id = $data->firm_id ?? null;
    }
    $year = $data->year ?? null;
//        $tot_employees = $data->tt_emp ?? null;
    $employee_cntrbtn = $data->empe_ctb ?? null;
    $employer_cntrbtn = $data->empr_ctb ?? null;
//        $total_cntrbtn = $data->tt_ctb ?? null;
    $employee_amt = $data->employee_amt ?? null;
    $employer_amt = $data->employer_amt ?? null;
//        $total_amt = $data->tt_amt ?? null;
    $remarks = $data->remarks ?? null;
    $bal_amt = $data->bal_amt ?? 0;//bal_amt
    $total_cntrbtn = 0;

    if (is_null($employee_amt)) {
      $employee_amt = $tot_employees * $employee_cntrbtn;
    }

    if (is_null($employer_amt)) {
      $employer_amt = $tot_employees * $employer_cntrbtn;
    }

    if (!is_null($employer_cntrbtn) && !is_null($employee_cntrbtn)) {
      $total_cntrbtn = $employer_cntrbtn + $employee_cntrbtn;
    }

    $total_amt = $tot_employees * $total_cntrbtn;

    if ($bal_amt == 0) $bal_amt = $total_amt;

    if (!is_null($firm_id) && !is_null($year)) {
      $sql = 'SELECT * FROM est.payables WHERE firm_id = $1 AND year=$2';
      $db->Query($sql, [$firm_id, $year]);
      $rows = $db->FetchAll();
    } else {
      throw new \Exception("firm_id & year is required to save");
    }

    if (count($rows) > 0) {
      $sql = 'UPDATE est.payables
              SET tot_employees =$3,
                  employee_cntrbtn =$4,
                  employer_cntrbtn =$5,
                  total_cntrbtn =$6,
                  employee_amt =$7,
                  employer_amt =$8,
                  total_amt =$9,
                  bal_amt=$10,
                  remarks=$11
              WHERE firm_id = $1
              AND year = $2
              RETURNING id';

      $db->Query($sql, [$firm_id, $year, $tot_employees, $employee_cntrbtn, $employer_cntrbtn, $total_cntrbtn,
        $employee_amt, $employer_amt, $total_amt, $bal_amt, $remarks]);
      $retVal['message'] = "Payable update successfully.";
    } else {
      $sql = 'INSERT INTO est.payables
                    (firm_id,
                     year,
                     tot_employees,
                     employee_cntrbtn,--per head
                     employer_cntrbtn,--per head
                     total_cntrbtn, -- empe ctb + empr ctb
                     employee_amt,
                     employer_amt,
                     total_amt,
                     bal_amt,
                     remarks)
              VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11)
              RETURNING id';

      $db->Query($sql, [$firm_id, $year, $tot_employees, $employee_cntrbtn, $employer_cntrbtn,
        $total_cntrbtn, $employee_amt, $employer_amt, $total_amt, $bal_amt, $remarks]);

      $retVal['message'] = "Payable saved successfully.";
    }

    $retVal['payable_id'] = $db->FetchAll()[0]['id'];
    $retVal['total_amount'] = $total_amt;

    return $retVal;
  }

  function getPayable($selected_years, $firm_id, $db): mixed
  {
    $params = [];

    $where_clause = "(";
    foreach ($selected_years as $year) {
      $params[] = $year;
      if (count($selected_years) == count($params)) {
        goto end;
      }
      $where_clause .= "year = $" . count($params) . " OR ";
    }
    end:
    $where_clause .= "year = $" . count($params) . ") ";

    $params[] = $firm_id;

    $where_clause .= "AND firm_id = $" . count($params);

    $sql = "select id, tot_employees, total_amt
            from est.payables
            where $where_clause";

    $db->Query($sql, $params);
    $rows = $db->FetchAll();

    return $rows;
  }

  function payablePaid($db, $data)
  {
    $retVal = [];
    $firm_id = isset($data->firm_id) ? $data->firm_id : null;
    $amount = isset($data->amount) ? $data->amount : null;
    $payment_id = isset($data->payment_id) ? $data->payment_id : null;
    $is_has_pay = isset($data->is_has_pay) ? $data->is_has_pay : null;
    $remaining_amt = isset($data->remaining_amt) ? $data->remaining_amt : null;

    if (count($is_has_pay) > 0) {

      $amount = $amount + $remaining_amt;
      foreach ($is_has_pay as &$r) {
        if ($amount > 0) {
          $payable_id = isset($r->payable_id) ? $r->payable_id : null;
          $paid_amt = isset($r->paid_amt) ? $r->paid_amt : 0;
          $bal_amt = isset($r->bal_amt) ? $r->bal_amt : 0;

          $pay_amt = $amount >= $bal_amt ? $bal_amt : $amount;

          $paid_amt = $paid_amt + $pay_amt;
          $remaining_bal = $bal_amt - $pay_amt;

          $amount = $amount - $pay_amt;

          $sql = 'UPDATE est.payables
                  SET paid_amt=$1 , bal_amt=$2
                  WHERE id=$3 RETURNING *';
          $db->Query($sql, [$paid_amt, $remaining_bal, $payable_id]);
          $retVal = $db->FetchAll();

          $sql = 'INSERT INTO est.payment_against_payables(payment_id, payable_id, tot_amt)
                  VALUES ($1, $2, $3) RETURNING *;';
          $db->Query($sql, [$payment_id, $payable_id, $pay_amt]);
        }
      }
    }
    return $retVal;
  }

  function payableAgainstReceipt($db, $data)//<- Receipt against payable
  {
    $retVal = [];
    $amount = isset($data["amount"]) ? $data["amount"] : null;
    $is_has_pay = isset($data["is_has_pay"]) ? $data["is_has_pay"] : [];
    $receipt_id = isset($data["receipt_id"]) ? $data["receipt_id"] : null;
    $remaing_bal = isset($data["remaing_bal"]) ? $data["remaing_bal"] : null;

    // var_dump($data["is_has_pay"]);
    if (count($is_has_pay) > 0) {
      $amount = $amount + $remaing_bal;
      foreach ($is_has_pay as &$r) {
        if ($amount > 0) {
          $payable_id = $r->payable_id ?? null;
          $paid_amt = $r->paid_amt ?? 0;
          $bal_amt = $r->bal_amt ?? 0;

          $pay_amt = $amount >= $bal_amt ? $bal_amt : $amount;

          $paid_amt = $paid_amt + $pay_amt;
          $remaining_bal = $bal_amt - $pay_amt;

          $amount = $amount - $pay_amt;

          $sql = 'UPDATE est.payables
                  SET paid_amt=$1 , bal_amt=$2
                  WHERE id=$3
                  RETURNING *';

          $db->Query($sql, [$paid_amt, $remaining_bal, $payable_id]);
          $retVal = $db->FetchAll();

          $sql = 'INSERT INTO est.receipt_against_payables(payable_id, receipt_id, tot_amt)
                  VALUES ($1, $2, $3)
                  RETURNING *;';
          $db->Query($sql, [$payable_id, $receipt_id, $pay_amt]);
        }
      }
    }
    return $retVal;
  }
}
