<?php

namespace LWMIS\Master;

class Designation
{
    function getDesignationsRootWise($filter)
    {
        $ref_designation_code = isset($filter->ref_designation_code) ? $filter->ref_designation_code : null;

        $db = new \LWMIS\Common\PostgreDB();

        $sql = 'SELECT mas.get_json_designations($1) AS data';
        $db->Query($sql, [$ref_designation_code]);
        $rows = $db->FetchAll();

        foreach ($rows as &$r) {
            $r['data'] = isset($r['data']) ? json_decode($r['data'], true) : [];
        }
        $db->DBClose();

        return (count($rows) > 0) ? ($rows[0]['data']) : [];
    }

    function getDesignations($filter)
    {   
        $params = [];
        $where_clause = '';

        if( isset($filter->code) && is_string($filter->code)){
            $code = $filter->code;
            $params[] = $code;
            $where_clause .= ' AND code =$'. count($params) .'';
        }

        if( isset($filter->not_code) && is_string($filter->not_code)){
            $not_code = $filter->not_code;
            $params[] = $not_code;
            $where_clause .= ' AND code !=$'. count($params) .'';
        }

        $db = new \LWMIS\Common\PostgreDB();

        $sql = 'SELECT * FROM mas.designations WHERE TRUE '. $where_clause .';';
        $db->Query($sql, $params);
        $rows = $db->FetchAll();

        $db->DBClose();

        return (count($rows) > 0) ? $rows : [];
    }
}
?>
