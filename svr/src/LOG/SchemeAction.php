<?php
  namespace LWMIS\LOG;

class SchemeAction {
    function save($db, $data)
    {
      $retObj = [];

      $gn = new \LWMIS\Common\GeneralFunctions();
      $ip = $gn->getIPAddress();
      
      $scheme_code = isset($data->scheme_code)?$data->scheme_code:null;
      $action_code = isset($data->action_code)?$data->action_code:null;
      $user_id = isset($data->user_id)?$data->user_id:null;
      $ip = isset($ip)?$ip:null;
      $remarks = isset($data->remarks)?$data->remarks:null;
      
      $params = [];
      $params[] = $scheme_code;
      $params[] = $action_code;
      $params[] = $user_id;
      $params[] = $ip;
      $params[] = $remarks;
      
      $sql = 'INSERT INTO logs.scheme_actions (scheme_code, action_code, user_id, ip, remarks)
              VALUES ($1, $2, $3, $4, $5)
           RETURNING *';

      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      if (count($rows) > 0) {
        foreach($rows AS &$r) {
          $r['id'] = intval($r['id']);
          $r['user_id'] = intval($r['user_id']);
        }
        $retObj = $rows[0];
        $retObj['message'] = 'Act Action saved successfully.';
      }
      return $retObj;
    }

    function delete($db, $data)
    {
      $retObj = [];

      $gn = new \LWMIS\Common\GeneralFunctions();
      $ip = $gn->getIPAddress();
      
      $code = isset($data->code) ? $data->code : null;
      
      $params = [];
      $params[] = $code;
      
      $sql = 'DELETE FROM logs.scheme_actions WHERE scheme_code = $1';

      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      if (count($rows) > 0) {
        foreach($rows AS &$r) {
          $r['user_id'] = intval($r['user_id']);
        }
        $retObj = $rows[0];
        $retObj['message'] = 'Act Action saved successfully.';
      }
      return $retObj;
    }
  }
?>