<?php

namespace LWMIS\Est;

use LWMIS\Common\ErrorHandler;

class TnebBill
{
    function getEstTnebBills($filter)
    {
        $retObj = ['rows' => [], 'message' => null];
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

        $db = new \LWMIS\Common\PostgreDB();
        try {
            // get actual data
            $sql = 'SELECT a.*,b.file_name,b.storage_name
                      from est.tneb_bills as a
                      INNER JOIN doc.attachments AS b ON (a.attachment_id = b.id )
                     where true ' . $where_clause . '
                     ORDER BY a.id
                     LIMIT $1 OFFSET $2;';
            $db->Query($sql, $params);
            $rows = $db->FetchAll();
            foreach ($rows as &$r) {
                $r['id']            = isset($r['id']) ? intval($r['id']) : null;
                $r['firm_id']       = isset($r['firm_id']) ? intval($r['firm_id']) : null;
                $r['attachment_id'] = isset($r['attachment_id']) ? intval($r['attachment_id']) : null;
                $r['is_root']       = ($r['is_root'] == 't');
                // $r['is_del'] = ($r['is_del'] == 't');
            }
            $retObj['rows'] = $rows;
        } catch (\Exception $e) {
            $retObj['message'] = \LWMIS\Common\ErrorHandler::custom($e);
        }

        $db->DBClose();

        return $retObj;
    }

    function deleteEstTnebBill($data)
    {
        $retVal = [];
        $id = isset($data->id) ? $data->id : null;
        $attachment_id = isset($data->attachment_id) ? $data->attachment_id : null;
        $file_name = isset($data->file_name) ? $data->file_name : null;
        $storage_name = isset($data->storage_name) ? $data->storage_name : null;

        $db = new \LWMIS\Common\PostgreDB();
        if (!is_null($id) && !is_null($attachment_id)) {
            try {
                if(unlink($storage_name)){
                    $db->Begin();

                    $sql = 'DELETE FROM est.tneb_bills WHERE id = $1';
                    $db->Query($sql, [$id]);

                    $sql = 'DELETE FROM doc.attachments WHERE id = $1';
                    $db->Query($sql, [$attachment_id]);

                    $retVal['message'] = "TNEB Bill deleted successfully.";

                }else{

                    $retVal['message'] = "TNEB Bill deletion failed.";
                }
                $db->Commit();
            } catch (\Exception $e) {
                $db->RollBack();
                $retVal['message'] = $e->getMessage();
            }
            $db->DBClose();
        }
        return $retVal;
    }

    function saveTneb_bill($data)
    {
        $retVal = ['message' => 'Tneb Bill cannot be saved.'];
        $id = isset($data->id) ? $data->id : null;
        $tneb_no = isset($data->tneb_no) ? $data->tneb_no : null;
        $user_email = isset($data->user_email) ? $data->user_email : null;
        $firm_id = isset($data->firm_id) ? $data->firm_id : null;

        $db = new \LWMIS\Common\PostgreDB();
      $db->Begin();
        try {
            if (is_null($id)) {
                $attc = new \LWMIS\Doc\Attachment();
                $attachments = $attc->saveAttachment($data,$db);
              $attachment_id = $attachments['rows'][0]['id'];

              if (isset($attachment_id)) {
                $sql = 'INSERT INTO est.tneb_bills ( firm_id, tneb_no, attachment_id, ent_by, cre_ts )
                VALUES ($1, $2, $3, $4, now())
                RETURNING id';
                $db->Query($sql, [$firm_id, $tneb_no, $attachment_id, $user_email]);
              } else {
                throw new \Exception("Attachment Id is missing for insert operation");
              }
            } else {
                $params[] = $id;
                $sql = 'UPDATE est.tneb_bills
                           SET name = $1
                         WHERE id = $2 RETURNING id';
                $db->Query($sql, $params);
            }
            $rows = $db->FetchAll();
            foreach ($rows as &$r) {
                $r['id'] = intval($r['id']);
            }
            if (count($rows) > 0) {
                $retVal['id'] = $rows[0]['id'];
                $retVal['message'] = "TNEB Bill saved successfully.";
            }
            $db->Commit();
        } catch (\Exception $e) {
            $db->RollBack();
          $retVal['message'] = ErrorHandler::custom($e);
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

    function toggleStatus($data)
    {
        $retObj = ['message' => 'Invalid Bank Branch.'];
        $id = isset($data->id) ? $data->id : null;
        $is_active = (isset($data->is_active) && $data->is_active === true) ? 't' : 'f';
        $user_id = isset($data->user_id) ? $data->user_id : null;
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
        } catch (\Exception $e) {
            $db->RollBack();
            $retObj['message'] = $e->getMessage();
        }
        $db->DBClose();
        return $retObj;
    }
}
