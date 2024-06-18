<?php
  namespace LWMIS\LOG;

class CounterAction {
    function save($db, $data)
    {
      $retObj = [];

      $gn = new \LWMIS\Common\GeneralFunctions();
      $ip = $gn->getIPAddress();
      
      $counter_id = isset($data->counter_id)?$data->counter_id:null;
      $action_code = isset($data->action_code)?$data->action_code:null;
      $user_id = isset($data->user_id)?$data->user_id:null;
      $ip = isset($ip)?$ip:null;
      $remarks = isset($data->remarks)?$data->remarks:null;
      
      $params = [];
      $params[] = $counter_id;
      $params[] = $action_code;
      $params[] = $user_id;
      $params[] = $ip;
      $params[] = $remarks;
      
      $sql = 'INSERT INTO logs.counter_actions (counter_id, action_code, user_id, ip, remarks)
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
        $retObj['message'] = 'Counter Action saved successfully.';
      }
      return $retObj;
    }

    function delete($db, $data)
    {
      $retObj = [];

      $gn = new \LWMIS\Common\GeneralFunctions();
      $ip = $gn->getIPAddress();
      
      $counter_id = isset($data->counter_id)?$data->counter_id:null;
      
      $params = [];
      $params[] = $counter_id;
      
      $sql = 'DELETE FROM logs.counter_actions WHERE counter_id = $1';

      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      if (count($rows) > 0) {
        foreach($rows AS &$r) {
          $r['id'] = intval($r['id']);
          $r['user_id'] = intval($r['user_id']);
        }
        $retObj = $rows[0];
        $retObj['message'] = 'Counter Action saved successfully.';
      }
      return $retObj;
    }
  }
?>