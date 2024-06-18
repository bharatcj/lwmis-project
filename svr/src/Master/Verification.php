<?php

namespace LWMIS\Master;

use LWMIS\Common\ErrorHandler;

class Verification
{
  /*  function saveClarification($data): array
    {
  //    var_dump($data);
      $retVal = ['message' => 'Clarification cannot be saved.'];
      $firm_id = $data->firm_id ?? null;
      $claim_id = $data->claim_id ?? null;
      $remarks = $data->remarks ?? null;
      $user_id = $data->user_id ?? null;
  //    $o_designation_code = $data->o_designation_code ?? null;
      $from_designation_code = $data->from_designation_code ?? null;
      $to_designation_code = $data->to_designation_code ?? null;
      $status = $data->status ?? null;
      $action_code = $data->action_code ?? null;
      $email = $data->email ?? null;

      $db = new \LWMIS\Common\PostgreDB();
      try {
        $db->Begin();
        $sql = "INSERT INTO mas.verifications
                  (firm_id, claim_id, remarks, user_id, from_designation_code,to_designation_code,cre_by, action_code, status, cre_ts)
                  VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,NOW())
                  RETURNING id";

        $db->Query($sql, [$firm_id, $claim_id, $remarks, $user_id, $from_designation_code, $to_designation_code, $email, $action_code, $status]);
        $rows = $db->FetchAll();

        if ((isset($rows) && count($rows) == 1) && !is_null($claim_id)) {
  //        if ($status == 'CLR' || $status == 'V') {
  //          $status = 'REC';
  //        }

  //        if (!($status == 'V' && $from_designation_code == 'SO')) {
          $sql = "UPDATE clm.claims
                     SET status = $2
                   WHERE id = $1";
          $db->Query($sql, [$claim_id, $status]);
          $db->FetchAll();
  //        }
        }

        if (count($rows) > 0) {
          $db->Commit();
          $retVal['message'] = "clarification saved successfully.";
        }

      } catch (\Exception $e) {
        $db->RollBack();
        $retVal['message'] = $e->getMessage();
      }
      $db->DBClose();
      return $retVal;
    }*/

  public function getVerifications($filter): array
  {
    $retObj = ['rows' => [], 'ver_screen_count' => 0, 'message' => null];

    $firm_id = $filter->firm_id ?? null;
    $claim_id = $filter->claim_id ?? null;
    $pmt_id = $filter->pmt_id ?? null;
    $action_code = $filter->action_code ?? null;

    $wc = '';
    $where_clause = "";
    $params = [];
    $pm = [];
//    $params[] = $limit;
//    $params[] = $offset;

    if (!is_null($firm_id)) {
      $firm_id = $filter->firm_id;
      $params[] = $firm_id;
      $where_clause .= ' AND a.firm_id = $' . count($params);

      $pm[] = $firm_id;
      $wc .= ' AND a.firm_id = $' . count($params);
    }

    if (!is_null($claim_id)) {
      $claim_id = $filter->claim_id;
      $params[] = $claim_id;
      $where_clause .= ' AND a.claim_id = $' . count($params);

      $pm[] = $claim_id;
      $wc .= ' AND a.claim_id = $' . count($params);
    }

    if (!is_null($pmt_id)) {
      $pmt_id = $filter->pmt_id;
      $params[] = $pmt_id;
      $where_clause .= ' AND a.payment_id = $' . count($params);

      $pm[] = $pmt_id;
      $wc .= ' AND a.payment_id = $' . count($params);
    }

    if (!is_null($action_code)) {
      $action_code = $filter->action_code;
      $params[] = $action_code;
//      $where_clause .= ' AND a.action_code = $' . count($params);
      $where_clause .= ' AND ss.action_code = $' . count($params);
    }

    $db = new \LWMIS\Common\PostgreDB();
    try {
      $sql = "SELECT a.id as verification_id,
                     a.firm_id,
                     a.claim_id,
                     a.payment_id,
                     a.user_id,
                     u.name as from_user_name,
                     a.type,
                     ss.action_code,
                     a.from_designation_code,
                     a.status as ver_status,
                     ss.status as scn_status,
                     a.cre_ts as ver_ts,
                     ss.cre_ts as scn_action_ts,
                     ss.remarks,
                     b.name AS designation_name
              FROM mas.verifications AS a
                  INNER JOIN mas.designations AS b ON a.from_designation_code = b.code
                  INNER JOIN mas.screen_status AS ss ON a.id = ss.verification_id
                  LEFT JOIN mas.users as u ON u.id = a.user_id
              WHERE true {$where_clause}
              ORDER BY a.id;";

//      var_dump($sql);
      // -- verification status: --
      //-- CLI: Clarification Issued,
      //-- CLR: Clarification Received,

      //-- SAP: Submitted by Applicant,
      //-- REC: Received by section Officer
      //-- VSO: Verified by Section Officers,
      //-- VSP: Verified by Superintendent,
      //-- VAO: Verified by Accounts Officers,
      //-- VFA: Verified by FA,
      //-- VSECR: Approved(By Secretary),
      //-- R: Rejected.

      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      foreach ($rows as &$r) {
        $r['verification_id'] = isset($r['verification_id']) ? intval($r['verification_id']) : null;
        $r['firm_id'] = isset($r['firm_id']) ? intval($r['firm_id']) : null;
        $r['user_id'] = isset($r['user_id']) ? intval($r['user_id']) : null;
        $r['claim_id'] = isset($r['claim_id']) ? intval($r['claim_id']) : null;
      }

      $retObj['rows'] = $rows;

      $sql = "SELECT count(a.status) filter ( where ss.status = 'V' )
                FROM mas.verifications AS a
          INNER JOIN mas.screen_status AS ss ON a.id = ss.verification_id
               WHERE true {$wc}";

      $db->Query($sql, $pm);
      $retObj['ver_screen_count'] = intval($db->FetchAll()[0]['count']);

    } catch (\Exception $e) {
      $retObj['message'] = \LWMIS\Common\ErrorHandler::custom($e);
    }

    $db->DBClose();
    return $retObj;
  }

//  function forwardClaimSuggestion($data): array
  function verify($data): array
  {
    /* Optimise the CLI / NV status.*/
    //todo: Remove claim suggestion from after master claim suggestion is working.
    $retVal = [];
    $claim_id = $data->claim_id ?? null;
    $user_id = $data->user_id ?? null;
    $payment_id = $data->pmt_id ?? null;
    $from_designation_code = $data->from_designation_code ?? null;
    $to_designation_code = $data->to_designation_code ?? null;
    $type = $data->type ?? null;
    $firm_id = $data->firm_id ?? null;
    $scn_status = $data->scn_status ?? null;
    $ver_status = $data->ver_status ?? null;

    $db = new \LWMIS\Common\PostgreDB();

    if ($type != 'P' && is_null($payment_id)) {//<- only non-payment statuses are allowed to check

      if (!is_null($claim_id)) { //<- condition to check it is not a payment.
        $claim_id_status = $this->checkClaimStatus($claim_id, $db)[0]['status'];
        if ($claim_id_status == 'R') {
          $retVal['message'] = "Rejected Claims are Not allowed.";
          return $retVal;
        } else if ($claim_id_status == 'A') {
          $retVal['message'] = "Approved Claims are Not allowed.";
          return $retVal;
        }
      } else if (!is_null($firm_id)) {
        $firm_status = $this->checkFirmStatus($firm_id, $db)[0]['status'];
        if ($firm_status == 'R') {
          $retVal['message'] = 'Rejected Firms are not allowed';
          return $retVal;
        } elseif ($firm_status == 'A') {
          $retVal['message'] = 'Approved Firms are not allowed';
          return $retVal;
        }
      }

    }


//    var_dump($firm_id, $type);

//    if ((!is_null($claim_id) && $type == 'C') || (!is_null($firm_id) && $type == 'F')) {
//      $retVal['message'] = 'Check the id';
//      return $retVal;
//    }

    try {
      $db->Begin();


      if ($type == 'P' && !is_null($payment_id)) {
        $sql = "update est.payments
                   set clr_status = 'CLI'
                 where id = $1
             returning id";

        $db->Query($sql, [$payment_id]);
        $rows = $db->FetchAll();

        if (count($rows) <= 0) {
          throw new \Exception("Payment Id is not available!");
        }
      }


      if ((!is_null($claim_id) && $type == 'C') || (!is_null($firm_id) && $type == 'F') || (!is_null($payment_id) && $type == 'P')) {

        if (!is_null($scn_status) && $from_designation_code == 'SO' && ((is_null($payment_id) && $type != 'P'))) {
          $to_designation_code = null;//<- Will be updated only when FORWARD button is pressed.
          $ver_status = 'REC';
        }

        if ($scn_status == 'NV') {
          $ver_status = 'CLI';
        }

        //note: mas.verification is different from mas.screen_status
        $sql = "INSERT INTO mas.verifications(firm_id,claim_id,user_id,payment_id,type,
                                            from_designation_code,to_designation_code,status,cre_ts)
                VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)
                RETURNING id";

        $db->Query($sql, [$firm_id, $claim_id, $user_id, $payment_id, $type, $from_designation_code, $to_designation_code, $ver_status, 'now()']);
        $rows = $db->FetchAll();

        if (!is_null($scn_status)) {
          $verification_id = $rows[0]['id'];
          $this->save_screen_status($verification_id, $scn_status, $data, $db);
        }

        // -- verification status: --
        //-- CLI: Clarification Issued,
        //-- CLR: Clarification Received,

        //-- SAP: Submitted by Applicant,
        //-- REC: Received by section Officer
        //-- VSO: Verified by Section Officers,
        //-- VSP: Verified by Superintendent,
        //-- VAO: Verified by Accounts Officers,
        //-- VFA: Verified by FA,
        //-- VSECR: Approved(By Secretary),
        //-- R: Rejected.

        $retVal['message'] = "clarification saved successfully.";
        $db->Commit();
      } else {
        throw new \Exception('Id field is missing');
      }
    } catch (\Exception $e) {
      $db->RollBack();
      $retVal['message'] = ErrorHandler::custom($e);
    }
    $db->DBClose();
    return $retVal;
  }

  private function checkClaimStatus($claim_id, $db): array|string
  {
    $sql = "SELECT clm.status
              FROM clm.claims AS clm
             WHERE clm.id = $1";
    $db->Query($sql, [$claim_id]);
    return $db->FetchAll();
  }

  private function checkFirmStatus($firm_id, $db): array|string
  {
    $sql = "SELECT fm.status
              FROM est.firms AS fm
             WHERE fm.id = $1";
    $db->Query($sql, [$firm_id]);
    return $db->FetchAll();
  }

  /**
   * @throws \Exception
   */
  function save_screen_status($verification_id, $scn_status, $data, $db): void
  {
    $remarks = $data->remarks ?? null;
    $action_code = $data->action_code ?? null;

    if ($verification_id) {
      $sql = "INSERT INTO mas.screen_status(verification_id, action_code, remarks, status, cre_ts)
                   VALUES ($1, $2, $3, $4, NOW())";
      $db->Query($sql, [$verification_id, $action_code, $remarks, $scn_status]);
//      V - Verified.
//      CLI - Clarification Issued.
//      CLR - Clarification Respond.
    } else {
      throw new \Exception('Verification id not found!!');
    }
  }

  function getToDesignation($claim_id, $db): bool
  {
    if ($claim_id) {
      $sql = "SELECT to_designation_code
              FROM mas.verifications
              WHERE claim_id = $1";
      $db->Query($sql, [$claim_id]);
      $v = $db->FetchAll();
//      var_dump($v);
//      var_dump($v[0]['to_designation_code']);
    }
    return true;
  }

}
