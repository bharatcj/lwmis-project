<?php

namespace LWMIS\Master;

use LWMIS\Common\ErrorHandler;

class PaymentCategory
{
  function getOtherPaymentCategories($data): array
  {
    $code = $data->code ?? null;
    $where_clause = '';
    $params = [];

    if ($code != null) {
      $params[] = $code;
      $where_clause .= " AND c.code = " . count($params);
    }

    $db = new \LWMIS\Common\PostgreDB();
    try {
      $sql = "select id, code, name
                from mas.other_payment_category
               where true $where_clause
               order by order_no";

      $db->Query($sql, $params);
      $rows = $db->FetchAll();

      foreach ($rows as &$r) {
        $r['id'] = intval($r['id']);
      }

      } catch (\Exception $e) {
      $rows = ErrorHandler::custom($e);
    } finally {
      $db->DBClose();
      return $rows;
    }
  }

}
