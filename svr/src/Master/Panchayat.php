<?php

namespace LWMIS\Master;

class Panchayat
{
    function getPanchayats($filter)
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

        if (isset($filter->district_id) && $filter->district_id != 'ALL') {
            $district_id = $filter->district_id;
            $params[] = $district_id;
            $where_clause .= ' AND b.id = $' . count($params);
        }

        if (isset($filter->type) && $filter->type != 'ALL') {
            $type = $filter->type;
            $params[] = $type;
            $where_clause .= ' AND a.type = $' . count($params);
        }

        $db = new \LWMIS\Common\PostgreDB();
        try {
            // get actual data
            $sql = 'SELECT a.id,a.name as panchayat_name,a.district_id,a.type,
                           b.name as district_name,
                           (CASE WHEN a.type = \'T\' then \'Town Panchayat\'
                                 WHEN a.type = \'V\' then \'Village Panchayat\'
                                 WHEN a.type = \'M\' THEN \'Municipality\'
                                 else null end
                           )as type_name,
                           COALESCE(c.is_del, true)AS is_del
                      from mas.panchayats as a
                           left outer join mas.districts as b
                               on (a.district_id = b.id)
                           LEFT OUTER JOIN LATERAL (
                                    SELECT false AS is_del
                                      FROM est.firms AS c
                                     WHERE c.panchayat_id = a.id
                                     LIMIT 1
                           ) AS c
                               ON (true)
                     where true ' . $where_clause . '
                     ORDER BY a.district_id,a.name
                     LIMIT $1 OFFSET $2;';
            $db->Query($sql, $params);
            $rows = $db->FetchAll();
            foreach ($rows as &$r) {
                $r['id'] = isset($r['id']) ? intval($r['id']) : null;
                $r['district_id'] = isset($r['district_id']) ? intval($r['district_id']) : null;
                $r['is_del'] = ($r['is_del'] == 't');
            }
            $retObj['rows'] = $rows;

            // get total rows
            if (!\is_null($limit) && count($rows) == $limit) {
                $sql = 'SELECT COUNT(*) AS cnt, $1 AS limit, $2 AS offset
                    from mas.panchayats as a
                    left outer join mas.districts as b on (a.district_id = b.id)
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

    function deletepanchayat($data)
    {
        $retVal = [];
        $id = isset($data->id) ? $data->id : null;

        if (!is_null($id)) {
            $db = new \LWMIS\Common\PostgreDB();
            $sql = 'DELETE FROM mas.panchayats WHERE id = $1';
            $db->Query($sql, [$id]);
            $retVal['message'] = "Panchayat deleted successfully.";
            $db->DBClose();
        }
        return $retVal;
    }

    function savepanchayat($data)
    {
        $retVal = ['message' => 'User cannot be saved.'];
        $panchayat_name = isset($data->panchayat_name) ? $data->panchayat_name : null;
        $district_id = isset($data->district_id) ? $data->district_id : null;
        $type = isset($data->type) ? $data->type : null;
        $id = isset($data->id) ? $data->id : null;

        $params = array();
        $params[] = $panchayat_name;
        $params[] = $district_id;
        $params[] = $type;

        $db = new \LWMIS\Common\PostgreDB();
        try {
            if (is_null($id)) {

                $sql = 'INSERT INTO mas.panchayats ( name, district_id, type)
                VALUES ($1, $2, $3)
                RETURNING id';
                $db->Query($sql, $params);
                $rows = $db->FetchAll();
                foreach ($rows as &$r) {
                    $r['id'] = intval($r['id']);
                }
                if (count($rows) > 0) {
                    $retVal['id'] = $rows[0]['id'];
                    $retVal['message'] = "Panchayat saved successfully.";
                }
            } else {
                $params[] = $id;
                $sql = 'UPDATE mas.panchayats
             SET name = $1, district_id = $2, type = $3
           WHERE id = $4';
                $db->Query($sql, $params);
                $retVal['message'] = "Panchayat update successfully.";
            }
        } catch (\Exception $e) {
            $retVal['message'] = $e->getMessage();
        }
        $db->DBClose();
        return $retVal;
    }
}
?>
