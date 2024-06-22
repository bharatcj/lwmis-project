<?php

/**
 * <b>Generate Payable is used to calculate each establishment's pending amount details for every year.</b>
 * <br> <br>
 *
 * <b>NOTE: This File must be run immediately after the new year is born.</b>
 * <p>E.g: if present year is <b>2023</b> then we have to run this on <b>01.01.2024 12.01.00 AM</b> or immediately after that hour to make it work as expected. </p>
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
\LWMIS\Common\ErrorHandler::defineErrorLevel();

generatePayable();

function generatePayable(): void
{
  $db = new \LWMIS\Common\PostgreDB();
  $db->Begin();

  try {
    $sql = "SELECT id
              FROM est.firms
             WHERE status = 'A'
               AND lwb_reg_no is not null
          ORDER BY id";
//                AND extract(year from lwb_req_dt) != extract(year from now())";
    $db->Query($sql, []);
    $selections = $db->FetchAll();

    foreach ($selections as $row) {
      $firm_id = $row['id'];

      $sql = "SELECT EXTRACT(YEAR from gsy.year) AS year,
                     COALESCE(eh.pe_male, fm.pe_male) +
                     COALESCE(eh.pe_female, fm.pe_female) +
                     COALESCE(eh.pe_trans, fm.pe_trans) +
                     COALESCE(eh.ce_male, fm.ce_male) +
                     COALESCE(eh.ce_female, fm.ce_female) +
                     COALESCE(eh.ce_trans, fm.ce_trans)  AS tt_emp,

                     (COALESCE(eh.pe_male, fm.pe_male) +
                      COALESCE(eh.pe_female, fm.pe_female) +
                      COALESCE(eh.pe_trans, fm.pe_trans) +
                      COALESCE(eh.ce_male, fm.ce_male) +
                      COALESCE(eh.ce_female, fm.ce_female) +
                      COALESCE(eh.ce_trans, fm.ce_trans)) *
                     gsy.total_cntrbtn                   AS tt_amt,

                     gsy.employee_cntrbtn                AS empe_ctb,
                     gsy.employer_cntrbtn                AS empr_ctb,
                     fm.id
              FROM est.firms as fm
                       inner join lateral (
                  SELECT s.year::DATE,
                         a.employee_cntrbtn,
                         a.employer_cntrbtn,
                         a.total_cntrbtn
                  FROM GENERATE_SERIES((to_char(now(),'YYYY')||'-01'||'-01')::date, CURRENT_DATE, INTERVAL '1 year') AS s(year)
              --     FROM GENERATE_SERIES((to_char(fm.lwb_req_dt,'YYYY')||'-01'||'-01')::date, CURRENT_DATE, INTERVAL '1 year') AS s(year)
                           INNER JOIN mas.contributions a
                                      ON (s.year BETWEEN a.from_year AND COALESCE(a.to_year::date, CURRENT_DATE::date))
                           LEFT JOIN est.employee_history AS eh ON
                      (fm.id = eh.firm_id AND eh.year = extract(YEAR from s.year))
                  ) as gsy on true
                       LEFT JOIN est.employee_history AS eh ON
                  (fm.id = eh.firm_id AND eh.year = extract(YEAR from gsy.year))
              -- where fm.id = 63 and gsy.year = to_char(now(),'YYYY');
              where fm.id = $1";

      $db->Query($sql, [$firm_id]);
      $row = $db->FetchAll();

//      var_dump("for firm_id $firm_id = $rows[0]");
//      echo "firm $firm_id \n $rows";
//      var_dump($row);
//      var_dump("{$rows[0]['tt_emp']}");

      if (!is_null($row[0]['id']) && !is_null($row[0]['year'])) {// <- to ignore duplicate values.
        $sql = 'SELECT * FROM est.payables WHERE firm_id = $1 AND year=$2';
        $db->Query($sql, [$row[0]['id'], $row[0]['year']]);
        $rows = $db->FetchAll();

        if (!count($rows) > 0) {
          $sql = "insert into est.payables(year,
                    firm_id,
                    tot_employees,
                    employee_cntrbtn,
                    employer_cntrbtn,
                    total_cntrbtn,
                    employee_amt,
                    employer_amt,
                    total_amt,
                    bal_amt)
                    values ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)";

          $db->Query($sql, [$row[0]['year'], $row[0]['id'], $row[0]['tt_emp'], $row[0]['empe_ctb'], $row[0]['empr_ctb'],
              ($row[0]['empe_ctb'] + $row[0]['empr_ctb']),
              ($row[0]['tt_emp'] * $row[0]['empe_ctb']),
              ($row[0]['tt_emp'] * $row[0]['empr_ctb']),
              $row[0]['tt_amt'],
              $row[0]['tt_amt']]
          );
        }

      } else {
        throw new \Exception("firm_id & year is required to save");
      }
    }

    $db->Commit();
    (new \LWMIS\Common\GeneralFunctions())->jobSchedulerLog(__METHOD__, 'Generate Payable is Executed Successfully.');
  } catch (\Exception $e) {
    $db->RollBack();
    (new \LWMIS\Common\GeneralFunctions())->jobSchedulerLog(__METHOD__, 'Generate Payable is Executed with error: ' . $e);
    echo \LWMIS\Common\ErrorHandler::custom($e);
  }
  $db->DBClose();
}

