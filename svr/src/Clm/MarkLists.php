<?php

namespace LWMIS\Clm;

use Exception;
use LWMIS\Common\PostgreDB;

class MarkLists
{
  function saveMarkList($db, $data): ?array
  {
    $retVal['message'] = "Mark List is Not Saved.";

    $claim_id = $data->id ?? null;
    $mark_lists = $data->mark_lists ?? null;

    foreach ($mark_lists as $ml) {
      $mark_list_no = $ml->mark_list_no ?? null;
      $attachment_id = $ml->attachment_id ?? null;
      $m_list_id = $ml->m_list_id ?? null;

      $retVal['message'] = "Mark list details are Saved Successfully.";
      try {
        if (is_null($claim_id) && is_null($attachment_id) && is_null($mark_list_no)) {
          throw new \Exception("Claim Id,Mark List No and Attachment id required.");
        }

        if (is_null($m_list_id)) {
          $sql = "INSERT INTO clm.mark_lists(claim_id, mark_list_no, attachment_id)
                       VALUES ($1,$2,$3)";
          $db->Query($sql, [$claim_id, $mark_list_no, $attachment_id]);
        } else {
          $sql = "update clm.mark_lists
                     set claim_id = $1,
                         mark_list_no = $2,
                         attachment_id = $3
                   where id = $4";
          $db->Query($sql, [$claim_id, $mark_list_no, $attachment_id, $m_list_id]);
        }

      } catch (Exception $e) {
        $retVal['message'] = $e->getMessage();
      }

    }

    return $retVal;
  }

  function getMarkLists($data): array
  {
    $claim_id = $data->claim_id ?? null;
    if (is_null($claim_id)) {
      $claim_id = $data->id ?? null;
    }
    $retVal = [];
    $db = new PostgreDB();

    if (!is_null($claim_id)) {
      try {
        $sql = "SELECT ml.id,
                       ml.claim_id,
                       ml.mark_list_no,
                       ml.attachment_id,
                       atc.file_name,
                       atc.storage_name
                  FROM clm.mark_lists AS ml
             LEFT JOIN doc.attachments AS atc ON (ml.attachment_id = atc.id)
                 WHERE ml.claim_id = $1";
//        var_dump($sql);
        $db->Query($sql, [$claim_id]);
        $rows = $db->FetchAll();
        $retVal['rows'] = $rows;
      } catch (Exception $e) {
        $retVal['message'] = $e->getMessage();
      }
    } else {
      $retVal['message'] = "Invalid Inputs";
    }

    $db->DBClose();
    return $retVal;
  }

}
