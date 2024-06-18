<?php

namespace LWMIS\Est;

class Clarification
{
    function getEstClarifications($filter)
    {
//      d 2 d
        $retObj = ['rows' => [], 'tot_rows' => 0, 'message' => null];
        $limit = isset($filter->limit) ? $filter->limit : null;
        $offset = $limit * (isset($filter->offset) ? $filter->offset : 0);

        $where_clause = "";
        $params = [];
        $params[] = $limit;
        $params[] = $offset;

        if (isset($filter->firm_id) && $filter->firm_id > 0) {
            $firm_id = $filter->firm_id;
            $params[] = $firm_id;
            $where_clause .= ' AND a.firm_id = $' . count($params);
        }

        if (isset($filter->claim_id) && $filter->claim_id > 0) {
            $claim_id = $filter->claim_id;
            $params[] = $claim_id;
            $where_clause .= ' AND a.claim_id = $' . count($params);
        }

        if (isset($filter->search_text) && strlen($filter->search_text) > 0) {

            $search_text = '%' . $filter->search_text . '%';
            $params[] = $search_text;
            $param_cnt = '$' . count($params);
            $where_clause .= ' AND (
                                  UPPER(a.name) like UPPER(' . $param_cnt . ')
                                 )';
        }

        if (isset($filter->action_code) && strlen($filter->action_code) > 0) {
            $action_code = $filter->action_code;
            $params[] = $action_code;
            $where_clause .= ' AND a.action_code = $' . count($params);
        }

        $db = new \LWMIS\Common\PostgreDB();
        try {
            // get actual data
            $sql = 'SELECT a.*, b.name AS designation_name
                      FROM est.clarifications AS a
                        INNER JOIN mas.designations AS b ON ( a.o_designation_code = b.code )
                     WHERE true ' . $where_clause . '
                     ORDER BY a.id ASC
                     LIMIT $1 OFFSET $2;';
            $db->Query($sql, $params);
            $rows = $db->FetchAll();
//            var_dump($rows);
            foreach ($rows as &$r) {
                $r['id']            = isset($r['id'])          ? intval($r['id'])          : null;
                $r['firm_id']       = isset($r['firm_id'])     ? intval($r['firm_id'])     : null;
                $r['noti_rej']      = isset($r['noti_rej'])    ? intval($r['noti_rej'])    : null;
                $r['user_id']       = isset($r['user_id'])     ? intval($r['user_id'])     : null;
                $r['res_status']    = isset($r['res_status'])  ? intval($r['res_status'])  : null;
                $r['claim_id']      = isset($r['claim_id'])    ? intval($r['claim_id'])    : null;
            }

            $retObj['rows'] = $rows;

            // get total rows
            if (!\is_null($limit) && count($rows) == $limit) {
                $sql = 'SELECT COUNT(*) AS cnt, $1 AS limit, $2 AS offset
                    from est.clarifications as a
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

    function getEstClarification($filter)
    {
        $retObj = [];
        $limit = isset($filter->limit) ? $filter->limit : null;
        $offset = $limit * (isset($filter->offset) ? $filter->offset : 0);

        $where_clause = "";
        $params = [];
        $params[] = $limit;
        $params[] = $offset;

        if (isset($filter->firm_id) && $filter->firm_id > 0) {
            $firm_id = $filter->firm_id;
            $params[] = $firm_id;
            $where_clause .= ' AND a.firm_id = $' . count($params);
        }

        if (isset($filter->claim_id) && $filter->claim_id > 0) {
          $claim_id = $filter->claim_id;
          $params[] = $claim_id;
          $where_clause .= ' AND a.claim_id = $' . count($params);
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
            $sql = 'SELECT a.*
                      from est.clarifications as a
                     where true ' . $where_clause . '
                     ORDER BY a.id
                     LIMIT $1 OFFSET $2;';
            $db->Query($sql, $params);
            $rows = $db->FetchAll();
            foreach ($rows as &$r) {
                $r['id']         = isset($r['id'])          ? intval($r['id'])          : null;
                $r['firm_id']    = isset($r['firm_id'])     ? intval($r['firm_id'])     : null;
                $r['noti_rej']   = isset($r['noti_rej'])    ? intval($r['noti_rej'])    : null;
                $r['user_id']    = isset($r['user_id'])     ? intval($r['user_id'])     : null;
                $r['res_status'] = isset($r['res_status'])  ? intval($r['res_status'])  : null;
                $r['claim_id']   = isset($r['claim_id'])    ? intval($r['claim_id'])    : null;
            }
            if(count($rows) > 0){
                $retObj['rows'] = $rows[0];
            }

        } catch (\Exception $e) {
            $retObj['message'] = \LWMIS\Common\ErrorHandler::custom($e);
        }

        $db->DBClose();

        return $retObj;
    }

    function deleteClarification($data)
    {
        $retVal = [];
        $id = isset($data->id) ? $data->id : null;

        $db = new \LWMIS\Common\PostgreDB();
        if (!is_null($id)) {
            $sql = 'DELETE FROM est.clarifications WHERE id = $1';
            $db->Query($sql, [$id]);
            $retVal['message'] = "Clarification deleted successfully.";
        }
        $db->DBClose();
        return $retVal;
    }

    function saveClarification($data): array
    {
        $retVal = ['message' => 'Clarification cannot be saved.'];
        $act_name = isset($data->act_name) ? $data->act_name : null;
//        $id = isset($data->id) ? $data->id : null;

        $id                 = isset($data->id)                 ? $data->id                  : null;
        $firm_id            = isset($data->firm_id)            ? $data->firm_id             : null;
        $claim_id           = isset($data->claim_id)           ? $data->claim_id            : null;
        $remarks            = isset($data->remarks)            ? $data->remarks             : null;
        $user_id            = isset($data->user_id)            ? $data->user_id             : null;
        $o_designation_code = isset($data->o_designation_code) ? $data->o_designation_code  : null;
        $res_status         = isset($data->res_status)         ? $data->res_status          : null;
        $action_code        = isset($data->action_code)        ? $data->action_code         : null;
        $email              = isset($data->email)              ? $data->email               : null;
        $params = array();
        $params[] = $act_name;

        $db = new \LWMIS\Common\PostgreDB();
        try {
            if (is_null($id)) {

                $sql = 'INSERT INTO est.clarifications
                                    ( firm_id, remarks, user_id, o_designation_code, ent_by, action_code, res_status, claim_id, cre_ts )
                             VALUES ($1, $2, $3, $4, $5, $6, $7, $8, now())
                          RETURNING id';
                $db->Query($sql, [ $firm_id, $remarks, $user_id, $o_designation_code, $email, $action_code, $res_status, $claim_id ]);
                $rows = $db->FetchAll();
                foreach ($rows as &$r) {
                    $r['id'] = intval($r['id']);
                }
                if (count($rows) > 0) {
                    $retVal['id']       = $rows[0]['id'];
                    $retVal['message']  = "clarification saved successfully.";
                }
            }
/*
            else {
                $sql = 'UPDATE est.clarifications
                           SET remarks = $1,up_dt = now(), up_by = $2
                         WHERE id = $3
                     RETURNING id';
                $db->Query($sql, [ $remarks, $email, $id ]);
                $rows = $db->FetchAll();
                if (count($rows) > 0) {
                    $retVal['id']       = $rows[0]['id'];
                    $retVal['message'] = "clarification update successfully.";
                }
            }
*/
        } catch (\Exception $e) {
            $retVal['message'] = $e->getMessage();
        }
        $db->DBClose();
        return $retVal;
    }

    function verClarification($data): array
    {
        $retVal = [];
        $id = isset($data->id) ? $data->id : 0;
        $res_status = isset($data->res_status) ? $data->res_status : 0;

        $db = new \LWMIS\Common\PostgreDB();
        if ($res_status==0) {
            $sql = 'UPDATE est.clarifications
                       SET res_status=1
                     WHERE id = $1';
            $db->Query($sql, [$id]);
            $retVal['message'] = "Clarification status Changed successfully.";
        }

        if ($res_status==1) {
            $sql = 'UPDATE est.clarifications
                       SET res_status=0
                     WHERE id = $1';
            $db->Query($sql, [$id]);
            $retVal['message'] = "Clarification status Changed successfully.";
        }
        $db->DBClose();
        return $retVal;
    }
}
