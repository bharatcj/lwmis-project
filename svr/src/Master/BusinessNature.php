<?php

namespace LWMIS\Master;

class BusinessNature
{
    function getBusinessNatures($filter)
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
        // var_dump($filter);
        if (isset($filter->search_text) && strlen($filter->search_text) > 0) {

            $search_text = '%' . $filter->search_text . '%';
            $params[] = $search_text;
            $param_cnt = '$' . count($params);
            $where_clause .= ' AND (
                                  UPPER(a.name) like UPPER(' . $param_cnt . ')
                                 )';
        }

        if (isset($filter->act_id) && $filter->act_id !='ALL' ) {

            $act_id = $filter->act_id;
            $params[] = $act_id;
            $where_clause .= ' AND a.act_id = $'. count($params);
        }

        if (!(isset($filter->include_inactive) && $filter->include_inactive === true)) {
          $where_clause .= ' AND a.is_active = true';
        }

        $db = new \LWMIS\Common\PostgreDB();
        try {
            // get BusinessNatureual data
            $sql = 'SELECT a.id, a.name as business_nature_name, a.name, a.is_active, a.remarks, a.act_id,
                           b.name as act_name, COALESCE(c.is_del, true) AS is_del
                      from mas.business_natures as a
                           INNER JOIN mas.acts b ON (b.id = a.act_id)
                           LEFT OUTER JOIN LATERAL (
                               SELECT false AS is_del
                                 FROM est.est_business_natures AS c
                                WHERE c.business_nature_id = a.id
                                 LIMIT 1
                           ) AS c ON (true)
                    where true ' . $where_clause . '
                    ORDER BY a.id
                    LIMIT $1 OFFSET $2;';
            $db->Query($sql, $params);
            $rows = $db->FetchAll();
            foreach ($rows as &$r) {
                $r['id'] = isset($r['id']) ? intval($r['id']) : null;
                $r['act_id'] = isset($r['act_id']) ? intval($r['act_id']) : null;
                $r['order_num'] = isset($r['order_num']) ? intval($r['order_num']) : null;
                $r['is_active'] = ($r['is_active'] == 't');
                $r['is_del'] = ($r['is_del'] == 't');

            }
            $retObj['rows'] = $rows;

            // get total rows
            if (!\is_null($limit) && count($rows) == $limit) {
                $sql = 'SELECT COUNT(*) AS cnt, $1 AS limit, $2 AS offset
                    from mas.business_natures as a
                    INNER JOIN mas.acts b ON (b.id = a.act_id)
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

    function deleteBusinessNature($data)
    {
        $retVal = [];
        $id = isset($data->id) ? $data->id : null;

        if (!is_null($id)) {
          $db = new \LWMIS\Common\PostgreDB();
            $sql = 'DELETE FROM mas.business_natures WHERE id = $1';
            $db->Query($sql, [$id]);
            $retVal['message'] = "Business Nature deleted successfully.";
            $db->DBClose();
        }
        return $retVal;
    }

    function saveBusinessNature($data)
    {
        $retVal = ['message' => 'User cannot be saved.'];
        $business_nature_name = isset($data->business_nature_name) ? $data->business_nature_name : null;
        $act_id = isset($data->act_id) ? $data->act_id : null;
        $id = isset($data->id) ? $data->id : null;

        $params = array();
        $params[] = $business_nature_name;
        $params[] = $act_id;

        $db = new \LWMIS\Common\PostgreDB();
        try {
            if (is_null($id)) {

                $sql = 'INSERT INTO mas.business_natures ( name, act_id)
                VALUES ($1, $2)
                RETURNING id';
                $db->Query($sql, $params);
                $rows = $db->FetchAll();
                foreach ($rows as &$r) {
                    $r['id'] = intval($r['id']);
                }
                if (count($rows) > 0) {
                    $retVal['id'] = $rows[0]['id'];
                    $retVal['message'] = "Business Nature saved successfully.";
                }
            } else {
                $params[] = $id;
                $sql = 'UPDATE mas.business_natures
             SET name = $1, act_id = $2
           WHERE id = $3';
                $db->Query($sql, $params);
                $retVal['message'] = "Business Nature update successfully.";
            }
        } catch (\Exception $e) {
            $retVal['message'] = $e->getMessage();
        }
        $db->DBClose();
        return $retVal;
    }

    function isMasterBusinessNatureExist($data)
  {
    $business_nature = isset($data->business_nature) ? $data->business_nature : null;
    $id = isset($data->id) ? $data->id : ((isset($payload['id']) && $payload['id'] > 0) ? $payload['id'] : null);

    $where_clause = "";
    $params = array();
    $params[] = $business_nature;

    // if (!is_null($id)) {
    //   $params[] = $id;
    //   $where_clause = 'AND email != $' . count($params);
    // }

    $db = new \LWMIS\Common\PostgreDB();
    $sql = 'SELECT name FROM mas.business_natures WHERE TRUE AND name IS NOT NULL AND name = $1 ' . $where_clause;
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

        $sql = "UPDATE mas.business_natures SET is_active = $1 WHERE id = $2";
        $db->query($sql, [$is_active, $id]);

        $actAction = new \LWMIS\LOG\ActAction();
        if ($is_active === 't') {
          $actAction->save($db, (object)[
            'act_id' => $id,
            'action_code' => 'BUSS_NAT_ACTIVATED',
            'user_id' => $user_id
          ]);
        } else {
          $actAction->save($db, (object)[
            'act_id' => $id,
            'action_code' => 'BUSS_NAT_DEACTIVATED',
            'user_id' => $user_id
          ]);
        }

        $retObj['message'] = 'Business Nature status changed successfully.';
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
?>