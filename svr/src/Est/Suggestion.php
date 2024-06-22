<?php
    namespace LWMIS\Est;

    class Suggestion
    {
      /*        function receiveFirmEstSuggestion($data): array
              {
                  $retVal = [];
                  $id = isset($data->id) ? $data->id : null;
                  $o_user_id = isset($data->o_user_id) ? $data->o_user_id : null;
                  $o_designation_code = isset($data->o_designation_code) ? $data->o_designation_code : null;
                  $o_user_id_to = isset($data->o_user_id_to) ? $data->o_user_id_to : null;
                  $o_designation_code_to = isset($data->o_designation_code_to) ? $data->o_designation_code_to : null;
                  $est_suggestion_id = isset($data->est_suggestion_id) ? $data->est_suggestion_id : null;
                  $remarks = isset($data->remarks) ? $data->remarks : null;

                  if(isset($data->status) && strlen($data->status) >0 ){
                      $status = $data->status;
                      if($status == 'S'){
                          $status_to = 'REC';
                      }
                  }

                  $db = new \LWMIS\Common\PostgreDB();
                  if (!is_null($id)) {
                      try{
                          $db->Begin();
                          $sql = 'UPDATE est.firms
                                     SET o_user_id = $1, o_designation_code = $2 ,status = $3
                                   WHERE id = $4';
                          $db->Query($sql, [$o_user_id, $o_designation_code, $status_to, $id]);

                          $sql = 'SELECT id
                                    FROM est.suggestions
                                   WHERE firm_id = $1 AND designation_code_to IS NULL';
                          $db->Query($sql, [$id]);

                          $rows = $db->FetchAll();
                          if( count($rows) > 0 ) {
                              $est_suggestion_id=$rows[0]['id'];
                          }

                          if (is_null($est_suggestion_id)) {
                              $sql = 'INSERT INTO est.suggestions (firm_id, user_id, designation_code, remarks)
                                           VALUES ( $1, $2, $3, $4 )';
                              $db->Query($sql, [$id, $o_user_id, $o_designation_code, $remarks]);
                          }else{
                              $sql = 'UPDATE est.suggestions
                                         SET designation_code_to = $1 ,user_id = $2
                                       WHERE id = $3';
                              $db->Query($sql, [$o_designation_code, $o_user_id, $est_suggestion_id]);

                              // $sql = 'INSERT INTO est.suggestions (firm_id, user_id, designation_code, remarks)
                              //              VALUES ( $1, $2, $3, $4 )';
                              // $db->Query($sql, [$id, $o_user_id_to, $o_designation_code_to, $remarks]);
                          }
                          $retVal['message'] = "Received Establishment successfully.";
                          $db->Commit();
                      }catch (\Exception $e) {
                          $db->RollBack();
                          $retVal['message'] = $e->getMessage();
                      }
                  }
                  $db->DBClose();
                  return $retVal;
              }*/

        function forwardFirmEst($data): array
        {
            $retVal = [];
            $id = isset($data->id) ? $data->id : null;
            $o_user_id = isset($data->o_user_id) ? $data->o_user_id : null;
            $o_designation_code = isset($data->o_designation_code) ? $data->o_designation_code : null;
            $o_user_id_to = isset($data->o_user_id_to) ? $data->o_user_id_to : null;
            $o_designation_code_to = isset($data->o_designation_code_to) ? $data->o_designation_code_to : null;
            $est_suggestion_id = isset($data->est_suggestion_id) ? $data->est_suggestion_id : null;
            $remarks = isset($data->remarks) ? $data->remarks : null;

            if(isset($data->status_to) && strlen($data->status_to) >0 ){
                $status_to = $data->status_to;
            }
            $db = new \LWMIS\Common\PostgreDB();
            if (!is_null($id)) {
                try{
                    $db->Begin();
                    $sql = 'UPDATE est.firms
                               SET o_user_id = $1, o_designation_code = $2 ,status = $3
                             WHERE id = $4';
                    $db->Query($sql, [$o_user_id_to, $o_designation_code_to,$status_to, $id]);

                    $sql = 'SELECT id
                              FROM est.suggestions
                             WHERE firm_id = $1 AND designation_code_to IS NULL';
                    $db->Query($sql, [$id]);

                    $rows = $db->FetchAll();

                    if( count($rows) > 0 ) {
                        $est_suggestion_id=$rows[0]['id'];
                    }

                    if (is_null($est_suggestion_id)) {
                        $sql = 'INSERT INTO est.suggestions (firm_id, user_id, designation_code, remarks)
                                     VALUES ( $1, $2, $3, $4 )';
                        $db->Query($sql, [$id, $o_user_id_to, $o_designation_code_to, $remarks]);
                    }else{
                        $sql = 'UPDATE est.suggestions
                                   SET designation_code_to = $1
                                 WHERE id = $2 AND designation_code_to IS NULL';
                        $db->Query($sql, [ $o_designation_code_to, $est_suggestion_id]);

                        $sql = 'INSERT INTO est.suggestions (firm_id, user_id, designation_code, remarks)
                                     VALUES ( $1, $2, $3, $4 )';
                        $db->Query($sql, [$id, $o_user_id_to, $o_designation_code_to, $remarks]);
                    }

                    $retVal['message'] = "Firm Establishment Transferred successfully.";
                    $db->Commit();
                }catch (\Exception $e) {
                    $db->RollBack();
                    $retVal['message'] = $e->getMessage();
                }
            }
            $db->DBClose();
            return $retVal;
        }

        function backwardFirmEst($data){
            $retVal = [];
            $id = isset($data->id) ? $data->id : null;
            $o_user_id = isset($data->o_user_id) ? $data->o_user_id : null;
            $o_designation_code = isset($data->o_designation_code) ? $data->o_designation_code : null;
            $o_user_id_to = isset($data->o_user_id_to) ? $data->o_user_id_to : null;
            $o_designation_code_to = isset($data->o_designation_code_to) ? $data->o_designation_code_to : null;
            $est_suggestion_id = isset($data->est_suggestion_id) ? $data->est_suggestion_id : null;
            $remarks = isset($data->remarks) ? $data->remarks : null;

            if(isset($data->status_to) && strlen($data->status_to) >0 ){
                $status_to = $data->status_to;
            }
            $db = new \LWMIS\Common\PostgreDB();
            if (!is_null($id)) {
                try{
                    $sql = 'UPDATE est.firms
                               SET status = $1
                             WHERE id = $2';
                    $db->Query($sql, [$status_to, $id]);

                    $retVal['message'] = "Firm Establishment Transferred successfully.";
                }catch (\Exception $e) {
                    $retVal['message'] = $e->getMessage();
                }
            }
            $db->DBClose();
            return $retVal;
        }
    }
?>
