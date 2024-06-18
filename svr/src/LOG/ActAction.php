<?php
  namespace LWMIS\LOG;

class ActAction {
    function save($db, $data)
    {
      $retObj = [];

      $gn = new \LWMIS\Common\GeneralFunctions();
      $ip = $gn->getIPAddress();
      
      $act_id = isset($data->act_id)?$data->act_id:null;
      $action_code = isset($data->action_code)?$data->action_code:null;
      $user_id = isset($data->user_id)?$data->user_id:null;
      $ip = isset($ip)?$ip:null;
      $remarks = isset($data->remarks)?$data->remarks:null;
      
      $params = [];
      $params[] = $act_id;
      $params[] = $action_code;
      $params[] = $user_id;
      $params[] = $ip;
      $params[] = $remarks;
      
      $sql = 'INSERT INTO logs.act_actions (act_id, action_code, user_id, ip, remarks)
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
      
      $id = isset($data->id) ? $data->id : null;
      
      $params = [];
      $params[] = $id;
      
      $sql = 'DELETE FROM logs.act_actions WHERE id = $1';

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
  }
?>