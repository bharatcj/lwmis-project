<?php

namespace LWMIS\Master;

class Act
{
    function getActs($filter)
    {
        $retObj = ['rows' => [], 'tot_rows' => 0, 'message' => null];
        $limit = isset($filter->limit) ? $filter->limit : null;
        $offset = $limit * (isset($filter->offset) ? $filter->offset : 0);

        $where_clause = "";
        $params = [];
        $params[] = $limit;
        $params[] = $offset;

        if (isset($filter->id) && $filter->id > 0) {
            $id = $filter->id;
            $params[] = $id;
            $where_clause .= ' AND a.id = $' . count($params);
        }

        if (isset($filter->search_text) && strlen($filter->search_text) > 0) {

            $search_text = '%' . $filter->search_text . '%';
            $params[] = $search_text;
            $param_cnt = '$' . count($params);
            $where_clause .= ' AND (
                                  UPPER(a.name) like UPPER(' . $param_cnt . ')
                                 )';
        }

        $db = new \LWMIS\Common\PostgreDB();
        try {
            // get actual data
            $sql = 'SELECT a.id, a.name as act_name ,a.is_active, a.order_num, a.remarks, 
                           COALESCE(b.is_del, true) AS is_del
                      from mas.acts as a
                           LEFT OUTER JOIN LATERAL (
                        SELECT false AS is_del
                          FROM est.est_acts AS b
                         WHERE b.act_id = a.id
                          LIMIT 1
                      ) AS b ON (true)
                     where true ' . $where_clause . '
                     ORDER BY a.order_num
                     LIMIT $1 OFFSET $2;';
            $db->Query($sql, $params);
            $rows = $db->FetchAll();
            foreach ($rows as &$r) {
                $r['id'] = isset($r['id']) ? intval($r['id']) : null;
                $r['order_num'] = isset($r['order_num']) ? intval($r['order_num']) : null;
                $r['is_active'] = ($r['is_active'] == 't');
                $r['is_del'] = ($r['is_del'] == 't');

            }
            $retObj['rows'] = $rows;

            // get total rows
            if (!\is_null($limit) && count($rows) == $limit) {
                $sql = 'SELECT COUNT(*) AS cnt, $1 AS limit, $2 AS offset
                    from mas.acts as a
                    where true ' . $where_clause;
                $db->Query($sql, $params);
                $tot_rows = $db->FetchAll();
                foreach ($tot_rows as &$r) {
                    $r['cnt'] = intval($r['cnt']);
                }

                $retObj['tot_rows'] = (count($tot_rows) > 0) ? $tot_rows[0]['cnt'] : count($rows);
            } else {
                $retObj['tot_rows'] = ((!\is_null($offset)) ? $offset : 0) + \count($rows);
            }
        } catch (\Exception $e) {
            $retObj['message'] = \LWMIS\Common\ErrorHandler::custom($e);
        }

        $db->DBClose();

        return $retObj;
    }

    function deleteAct($data)
    {
        $retVal = [];
        $id = isset($data->id) ? $data->id : null;

        if (!is_null($id)) {
          $db = new \LWMIS\Common\PostgreDB();
            $sql = 'DELETE FROM mas.Acts WHERE id = $1';
            $db->Query($sql, [$id]);
            $retVal['message'] = "Act deleted successfully.";
            $db->DBClose();
          }
        return $retVal;
    }

    function saveAct($data)
    {
        $retVal = ['message' => 'Act cannot be saved.'];
        $act_name = isset($data->act_name) ? $data->act_name : null;
        $id = isset($data->id) ? $data->id : null;
        
        $params = array();
        $params[] = $act_name;

        $db = new \LWMIS\Common\PostgreDB();
        try {
            if (is_null($id)) {

                $sql = 'INSERT INTO mas.Acts ( name )
                VALUES ($1)
                RETURNING id';
                $db->Query($sql, $params);
                $rows = $db->FetchAll();
                foreach ($rows as &$r) {
                    $r['id'] = intval($r['id']);
                }
                if (count($rows) > 0) {
                    $retVal['id'] = $rows[0]['id'];
                    $retVal['message'] = "Act saved successfully.";
                }
            } else {
                $params[] = $id;
                $sql = 'UPDATE mas.Acts
                           SET name = $1
                         WHERE id = $2';
                $db->Query($sql, $params);
                $retVal['message'] = "Act update successfully.";
            }
        } catch (\Exception $e) {
            $retVal['message'] = $e->getMessage();
        }
        $db->DBClose();
        return $retVal;
    }

  function isMasterActExist($data)
    {
    $act = isset($data->act) ? $data->act : null;
    $id = isset($data->id) ? $data->id : ((isset($payload['id']) && $payload['id'] > 0) ? $payload['id'] : null);

    $where_clause = "";
    $params = array();
    $params[] = $act;

    // if (!is_null($id)) {
    //   $params[] = $id;
    //   $where_clause = 'AND email != $' . count($params);
    // }

    $db = new \LWMIS\Common\PostgreDB();
    $sql = 'SELECT name FROM mas.acts WHERE TRUE AND name IS NOT NULL AND name = $1 ' . $where_clause;
    $db->Query($sql, $params);
    $rows = $db->FetchAll();
    $db->DBClose();
    return (count($rows) > 0);
  }

  function toggleStatus($data) {
    $retObj = ['message' => 'Invalid Bank Branch.'];
    $id = isset($data->id)?$data->id:null;
    $is_active = (isset($data->is_active) && $data->is_active === true)?'t':'f';
    $user_id = isset($data->user_id)?$data->user_id:null;
    // var_dump($data);

    $db = new \LWMIS\Common\PostgreDB();
    try {
      $db->Begin();

      if (!is_null($id)) {

        $sql = "UPDATE mas.acts SET is_active = $1 WHERE id = $2";
        $db->query($sql, [$is_active, $id]);

        $actAction = new \LWMIS\LOG\ActAction();
        if ($is_active === 't') {
          $actAction->save($db, (object)[
            'act_id' => $id,
            'action_code' => 'ACT_ACTIVATED',
            'user_id' => $user_id
          ]);
        } else {
          $actAction->save($db, (object)[
            'act_id' => $id,
            'action_code' => 'ACT_DEACTIVATED',
            'user_id' => $user_id
          ]);
        }

        $retObj['message'] = 'Act status changed successfully.';
      }

      $db->Commit();
    } catch(\Exception $e) {
      $db->RollBack();
      $retObj['message'] = $e->getMessage();
    }
    $db->DBClose();
    return $retObj;
  }
}
