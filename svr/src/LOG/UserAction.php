<?php
  namespace LWMIS\LOG;

  class UserAction {
    function getUserActions($filter) {
      $retObj = [];
      $limit = isset($filter->limit)?$filter->limit:null;
      $offset = $limit * (isset($filter->offset)?$filter->offset:0);

      $where_clause = '';
      $params = [];
      $params[] = $limit;
      $params[] = $offset;

      if (isset($filter->user_id) && $filter->user_id !== 'ALL')
      {
        $user_id = $filter->user_id;
        $params[] = $user_id;
        $where_clause .= ' AND a.user_id = $'.count($params);
      }

      if (isset($filter->from_dt) && trim($filter->from_dt) !== '')
      {
        $from_dt = $filter->from_dt;
        $params[] = $from_dt;
        $where_clause .= ' AND date(a.action_ts) >= $'.count($params);
      }

      if (isset($filter->to_dt) && trim($filter->to_dt) !== '')
      {
        $to_dt = $filter->to_dt;
        $params[] = $to_dt;
        $where_clause .= ' AND date(a.action_ts) <= $'.count($params);
      }

      if (isset($filter->search_text) && $filter->search_text !== '')
      {
        $search_text = '%'.$filter->search_text.'%';
        $params[] = $search_text;
        $param_cnt = '$'.count($params);
        $where_clause .= ' AND (
          upper(c.name) like upper('.$param_cnt.') OR
          upper(c.designation_code) like upper('.$param_cnt.') OR
          upper(c.mobile_no) like upper('.$param_cnt.') OR
          upper(c.email) like upper('.$param_cnt.') OR
          upper(d.name) like upper('.$param_cnt.') OR
          upper(a.action_code) like upper('.$param_cnt.') OR
          upper(b.name) like upper('.$param_cnt.') OR
          upper(a.ip) like upper('.$param_cnt.')
        )';
      }

      $db = new \LWMIS\Common\PostgreDB();
      // get actual data
      $sql = 'SELECT a.id, a.action_ts,
                     a.user_id, c.name AS user_name, c.designation_code, d.name AS designation_name,
                     c.mobile_no, c.email,
                     a.action_code, b.name AS action_name, a.note, a.ip, a.remarks
                FROM logs.user_actions AS a
                     LEFT OUTER JOIN mas.actions AS b ON (b.code = a.action_code)
                     LEFT OUTER JOIN mas.users AS c ON (c.id = a.user_id)
                     LEFT OUTER JOIN mas.designations AS d ON (d.code = c.designation_code)
               WHERE c.is_system_user = false'.$where_clause.'
               ORDER BY a.action_ts DESC, a.id DESC
               LIMIT $1 OFFSET $2';
      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      foreach ($rows as &$r)
      {
        $r['id'] = intval($r['id']);
        $r['user_id'] = isset($r['user_id'])?intval($r['user_id']):null;
      }
      $retObj['rows'] = $rows;
      // get total rows
      if (!\is_null($limit) && count($rows) == $limit) {
        $sql = 'SELECT COUNT(*) AS cnt, $1 AS limit, $2 AS offset
                  FROM logs.user_actions AS a
                       LEFT OUTER JOIN mas.actions AS b ON (b.code = a.action_code)
                       LEFT OUTER JOIN mas.users AS c ON (c.id = a.user_id)
                       LEFT OUTER JOIN mas.designations AS d ON (d.code = c.designation_code)
                 WHERE true'.$where_clause.'';
        $db->Query($sql, $params);
        $tot_rows = $db->FetchAll();
        foreach($tot_rows as &$r) {
          $r['cnt'] = intval($r['cnt']);
        }
        $retObj['tot_rows'] = (count($tot_rows) > 0)?$tot_rows[0]['cnt']:count($rows);
      } else {
        $retObj['tot_rows'] = ((!\is_null($offset))?$offset:0) + \count($rows);
      }

      $db->DBClose();

      return $retObj;
    }

    function save($db, $data) {
      $retObj = [];
      $gn = new \LWMIS\Common\GeneralFunctions();

      $user_id = isset($data->user_id)?$data->user_id:null;
      $action_code = isset($data->action_code)?$data->action_code:null;
      $note = isset($data->note)?$data->note:null;
      $ip = $gn->getIPAddress();
      $remarks = isset($data->remarks)?$data->remarks:null;

      $params = [];
      $params[] = $user_id;
      $params[] = $action_code;
      $params[] = $note;
      $params[] = $ip;
      $params[] = $remarks;

      $sql = 'INSERT INTO logs.user_actions (user_id, action_code, note, ip, remarks, action_ts)
              VALUES ($1, $2, $3, $4, $5, CURRENT_TIMESTAMP)
              RETURNING id';
      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      foreach($rows AS &$r) {
        $r['id'] = intval($r['id']);
      }

      if (count($rows) > 0) {
        $retObj['id'] = $rows[0]['id'];
        $retObj['message'] = 'User Action saved successfully.';
      }

      return $retObj;
    }
  }
?>
