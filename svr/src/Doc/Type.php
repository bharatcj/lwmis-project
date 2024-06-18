<?php

namespace LWMIS\Doc;

class Type
{
    function getTypes($filter)
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

        if (isset($filter->applicable_to) && strlen($filter->applicable_to) > 0) {
            $applicable_to = $filter->applicable_to;
            $params[] = $applicable_to;
            $where_clause .= ' AND $' . count($params) . '= any(a.applicable_to)';
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
            $sql = 'SELECT a.code,a.name,a.description ,TO_JSONB(a.applicable_to)AS applicable_to,
                           TO_JSONB(a.scheme_codes)AS scheme_codes, a.is_mandatory, a.remarks,COALESCE(b.is_del, true)AS is_del
                      FROM doc.types as a
                      LEFT OUTER JOIN LATERAL (
                               SELECT false AS is_del
                                 FROM doc.attachments AS b
                                WHERE b.doc_type = a.code
                                LIMIT 1
                           ) AS b ON (true)
                     WHERE true ' . $where_clause . '
                     ORDER BY a.name
                     LIMIT $1 OFFSET $2;';
            $db->Query($sql, $params);
            $rows = $db->FetchAll();
            foreach ($rows as &$r) {
                $r['is_mandatory'] = ($r['is_mandatory'] == 't');
                $r['is_del'] = ($r['is_del'] == 't');
                $r['applicable_to'] = isset($r['applicable_to'])? json_decode($r['applicable_to']): null;
                $r['scheme_codes'] = isset($r['scheme_codes'])? json_decode($r['scheme_codes']): null;
            }
            $retObj['rows'] = $rows;

            // get total rows
            if (!\is_null($limit) && count($rows) == $limit) {
                $sql = 'SELECT COUNT(*) AS cnt, $1 AS limit, $2 AS offset
                    from doc.types as a
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

    function deleteType($data)
    {
        $retVal = [];
        $code = isset($data->code) ? $data->code : null;

        $db = new \LWMIS\Common\PostgreDB();
        if (!is_null($code)) {
            $sql = 'DELETE FROM doc.types WHERE code = $1';
            $db->Query($sql, [$code]);
            $retVal['message'] = "Document Type deleted successfully.";
        }
        $db->DBClose();
        return $retVal;
    }

    function saveType($data)
    {
        $retVal = ['message' => 'Type cannot be saved.'];
        $name = isset($data->name) ? $data->name : null;
        $code = isset($data->code) ? $data->code : null;
        $type_code = isset($data->type_code) ? $data->type_code : null;
        $description = isset($data->description) ? $data->description : null;
        $applicable_to = isset($data->applicable_to) ? $data->applicable_to : null;
        $scheme_codes = isset($data->scheme_codes) ? $data->scheme_codes : null;

        $arr_scheme_codes = '';
        $arr_applicable_to = '';

        $params[] = is_null($code)? $type_code : $code;
        $params[] = $name;
        $params[] = $description;

        if (gettype($applicable_to) === 'array') {
            if (count($applicable_to) > 0) {
                $arr_applicable_to .= 'ARRAY[';
                foreach($applicable_to as &$cf) {
                    if (isset($cf) && gettype($cf) === 'string') {
                        $params[] = $cf;
                        $arr_applicable_to .= '$'.count($params).', ';
                    }
                }
                $arr_applicable_to = rtrim($arr_applicable_to, ', ').']';
                if ($arr_applicable_to == 'ARRAY[]') {
                    $arr_applicable_to = 'NULL';
                }
            } else {
              $arr_applicable_to = 'NULL';
            }
        }else{
            $arr_applicable_to = 'NULL';
        }

        if (gettype($scheme_codes) === 'array') {
            if (count($scheme_codes) > 0) {
                $arr_scheme_codes .= 'ARRAY[';
                foreach($scheme_codes as &$cf) {
                    if (isset($cf) && gettype($cf) === 'string') {
                        $params[] = $cf;
                        $arr_scheme_codes .= '$'.count($params).', ';
                    }
                }
                $arr_scheme_codes = rtrim($arr_scheme_codes, ', ').']';
                if ($arr_scheme_codes == 'ARRAY[]') {
                    $arr_scheme_codes = 'NULL';
                }
            } else {
              $arr_scheme_codes = 'NULL';
            }
        }else{
            $arr_scheme_codes = 'NULL';
        }

        $db = new \LWMIS\Common\PostgreDB();
        try {
            if (is_null($code)) {

                $sql = 'INSERT INTO doc.types (code, name, description, applicable_to, scheme_codes )
                VALUES ($1, $2, $3, '.$arr_applicable_to.', '.$arr_scheme_codes.')
                RETURNING code';
//                var_dump($sql);
//                var_dump(gettype($arr_scheme_codes));
                $db->Query($sql, $params);
                $rows = $db->FetchAll();

                if (count($rows) > 0) {
                    $retVal['code'] = $rows[0]['code'];
                    $retVal['message'] = "Type saved successfully.";
                }
            } else {
                $sql = 'UPDATE doc.types
                           SET name = $2, description = $3, applicable_to = '.$arr_applicable_to.', scheme_codes = '.$arr_scheme_codes.'
                         WHERE code = $1';
                $db->Query($sql, $params);
                $retVal['message'] = "Type update successfully.";
            }
        } catch (\Exception $e) {
            $retVal['message'] = $e->getMessage();
        }
        $db->DBClose();
        return $retVal;
    }

    function isMasterDocTypeExist($data)
    {

        $where_clause = "";
        $params = array();

        if(isset($data->type_name) && is_string($data->type_name)){
            $type_name = '%'.$data->type_name.'%';
            $params[] = $type_name;
            $where_clause .=' AND (code like UPPER($'.count($params).') OR name like $'.count($params).')';
        }

        $db = new \LWMIS\Common\PostgreDB();
        $sql = 'SELECT name FROM doc.types WHERE TRUE ' . $where_clause;
        $db->Query($sql, $params);
        $rows = $db->FetchAll();
        $db->DBClose();
        return (count($rows) > 0);
    }

    function toggleStatus($data)
    {
        $retObj = ['message' => 'Invalid Document Type.'];
        $code = isset($data->code) ? $data->code : null;
        $is_mandatory = (isset($data->is_mandatory) && $data->is_mandatory === true) ? 't' : 'f';
        $user_id = isset($data->user_id) ? $data->user_id : null;
        // var_dump($data);

        $db = new \LWMIS\Common\PostgreDB();
        try {
            $db->Begin();

            if (!is_null($code)) {

                $sql = "UPDATE doc.types SET is_mandatory = $1 WHERE code = $2";
                $db->query($sql, [$is_mandatory, $code]);

                $actAction = new \LWMIS\LOG\ActAction();
                // if ($is_mandatory === 't') {
                //     $actAction->save($db, (object)[
                //         'act_id' => $code,
                //         'action_code' => 'ACT_ACTIVATED',
                //         'user_id' => $user_id
                //     ]);
                // } else {
                //     $actAction->save($db, (object)[
                //         'act_id' => $code,
                //         'action_code' => 'ACT_DEACTIVATED',
                //         'user_id' => $user_id
                //     ]);
                // }

                $retObj['message'] = 'Document mandatory status changed successfully.';
            }

            $db->Commit();
        } catch (\Exception $e) {
            $db->RollBack();
            $retObj['message'] = $e->getMessage();
        }
        $db->DBClose();
        return $retObj;
    }
}
