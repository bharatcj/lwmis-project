<?php

namespace LWMIS\Clm;

class ClaimSuggestion
{
/*  function receiveClaimSuggestion($data): array//<= Take Action
  {
//    used only for take action
    $retVal = [];
    $id = $data->id ?? null;//<= claim_id
    $firm_id = $data->firm_id ?? null;
    $o_user_id = $data->o_user_id ?? null;
    $o_designation_code_to = $data->o_designation_code_to ?? null;

//    $o_user_id_to = $data->o_user_id_to ?? null;
//    $o_designation_code_to = $data->o_designation_code_to ?? null;
//    $est_suggestion_id = $data->est_suggestion_id ?? null;
    $clm_suggestion_id = $data->clm_suggestion_id ?? null;
    $remarks = $data->remarks ?? null;

    if (isset($data->status) && strlen($data->status) > 0) {
      $status = $data->status;
      if ($status == 'S') {
        $status_to = 'REC';
      }
    }

    $db = new \LWMIS\Common\PostgreDB();
    if (!is_null($id)) {
      try {
        $db->Begin();
        $sql = "UPDATE clm.claims
                   SET o_user_id = $1, o_designation_code_to = $2 ,status = $3
                 WHERE id = $4";
        $db->Query($sql, [$o_user_id, $o_designation_code_to, $status_to, $id]);

        $sql = 'SELECT id
                  FROM clm.clm_suggestions
                 WHERE claim_id = $1 AND designation_code_to IS NULL';
        $db->Query($sql, [$id]);

        $rows = $db->FetchAll();
        if (count($rows) > 0) {
//          var_dump('receiveClaimSuggestion===',$rows[0]);
          $clm_suggestion_id = $rows[0]['id'];
        }

        if (is_null($clm_suggestion_id)) {
          $sql = 'INSERT INTO clm.clm_suggestions (claim_id,firm_id,user_id,designation_code,remarks)
                       VALUES ($1, $2, $3, $4, $5)';
          $db->Query($sql, [$id, $firm_id, $o_user_id, $o_designation_code_to, $remarks]);
        } else {
          $sql = 'UPDATE clm.clm_suggestions
                     SET designation_code_to = $1 ,user_id = $2
                   WHERE id = $3';
          $db->Query($sql, [$o_designation_code_to, $o_user_id, $clm_suggestion_id]);
        }
        $retVal['message'] = "Claim Details Are Successfully Received.";
        $db->Commit();
      } catch (\Exception $e) {
        $db->RollBack();
        $retVal['message'] = $e->getMessage();
      }
    }
    $db->DBClose();
    return $retVal;
  }*/

//  function forwardFirmEst($data){
  function forwardClaimSuggestion($data): array
  {
    //todo:remove claim suggestion after master claim suggestion is working
    $retVal = [];
    $id = $data->id ?? null;//<= claim id
    $o_user_id = $data->o_user_id ?? null;
    $o_designation_code_to = $data->o_designation_code_to ?? null;
    $firm_id = $data->firm_id ?? null;
    $clm_suggestion_id = $data->est_suggestion_id ?? null;
    $remarks = $data->remarks ?? null;

    $status = '';
    if (isset($data->status) && strlen($data->status) > 0) {
      $status = $data->status;
    }
    $db = new \LWMIS\Common\PostgreDB();
    if (!is_null($id)) {
      try {
        $db->Begin();
        $sql = 'UPDATE clm.claims
                SET o_user_id = $1, o_designation_code_to = $2 ,status = $3
                WHERE id = $4';
        $db->Query($sql, [$o_user_id, $o_designation_code_to, $status, $id]);

        $sql = 'SELECT id
                FROM clm.clm_suggestions
                WHERE claim_id = $1 AND designation_code_to IS NULL';
        $db->Query($sql, [$id]);

        $rows = $db->FetchAll();

        if (count($rows) > 0) {
          $clm_suggestion_id = $rows[0]['id'];
        }

        if (!is_null($clm_suggestion_id)) {
          $sql = 'UPDATE clm.clm_suggestions
                     SET designation_code_to = $1
                   WHERE id = $2 AND designation_code_to IS NULL';
          $db->Query($sql, [$o_designation_code_to, $clm_suggestion_id]);
        }

        $sql = 'INSERT INTO clm.clm_suggestions (claim_id,firm_id, user_id, designation_code, remarks)
                  VALUES ( $1, $2, $3, $4 ,$5)';
        //<= creating new clm_suggestion tuple if suggestion id isn't found
        $db->Query($sql, [$id, $firm_id, $o_user_id, $o_designation_code_to, $remarks]);

//        if (is_null($clm_suggestion_id)) {
//          $sql = 'INSERT INTO clm.clm_suggestions (firm_id, user_id, designation_code, remarks)
//                    VALUES ( $1, $2, $3, $4 )';
//          $db->Query($sql, [$id, $o_user_id, $o_designation_code, $remarks]);
//        }else{
//          $sql = 'UPDATE clm.clm_suggestions
//                    SET designation_code_to = $1
//                    WHERE id = $2 AND designation_code_to IS NULL';
//          $db->Query($sql, [ $o_designation_code, $clm_suggestion_id]);
//
//          $sql = 'INSERT INTO clm.clm_suggestions (firm_id, user_id, designation_code, remarks)
//                    VALUES ( $1, $2, $3, $4 )';
//          $db->Query($sql, [$id, $o_user_id, $o_designation_code, $remarks]);
//        }

        $retVal['message'] = "Claim Details are Transferred successfully.";
        $db->Commit();
      } catch (\Exception $e) {
        $db->RollBack();
        $retVal['message'] = $e->getMessage();
      }
    }
    $db->DBClose();
    return $retVal;
  }

  function backwardClaim($data): array
  {
//  todo: o_designation_code_to & o_user_id.
    $retVal = [];
    $id = $data->id ?? null;
    $status = $data->status ?? null;

    $db = new \LWMIS\Common\PostgreDB();
    if (!is_null($id)) {
      try {
        $sql = "UPDATE clm.claims
                   SET status = $1
                 WHERE id = $2";
        $db->Query($sql, [$status, $id]);

        $retVal['message'] = "Transferred successfully.";
      } catch (\Exception $e) {
        $retVal['message'] = $e->getMessage();
      }
    }
    $db->DBClose();
    return $retVal;
  }
}
