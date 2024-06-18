<?php

namespace LWMIS\GenerateDocument;

use LWMIS\Common\IndiaCurrencyFormat;
use LWMIS\Common\IndianCurrencyWord;
use LWMIS\Common\PostgreDB;

class ClaimDisbursement extends ExtendTCPDF
{
  public function __construct()
  {
    parent::__construct('A4');
  }

  public function Generate($filter): void
  {
//    var_dump("generate fillllterrrrrrrrrrrrrrrrrr\n\n\n\n",$filter);
    $data = $filter->disb_claim ?? null;
    $acc_id = $filter->acc_id ?? null;
    $ref_message = $filter->ref_message ?? null;
    $disb_id = $filter->disb_id ?? null;

    if (($data == null || $ref_message == null) && $disb_id != null) {
      $data = (new \LWMIS\Clm\Disbursements())->getDisbursedClaims($disb_id);
      foreach ($data as &$v) {
        $v = (object)$v;
      }
      $ref_message = $data[0]->ref_message;
      $acc_id = $data[0]->disb_acc_id;
    }

    $this->SetFont('times', '', 9);

    $money = new IndiaCurrencyFormat();
    $tt_claim_word = new IndianCurrencyWord();

//    $this->Line(15, 29, $this->getPageWidth() - 15, 29);
    $html = '
        <style>
            .row{
            text-align:center;
            background-color: lightgray;
            }

            td{
            border: 1px solid black;
            }

            th{
            border: 1px solid black;
            }

            .ntd{
            border: 0 solid white;
            }
        </style>';

    $html .= '<hr>
                <table>
                    <tr>
                        <td class="ntd">
                            From: <br>
                            Financial Advisor and Chef Accounts Officer, <br>
                            Tamil Nadu Labour Welfare Board,<br>
                            Chennai - 6. <br>
                            Mob: 8939782783.
                        </td>
                        <td class="ntd">
                            To: <br>
                            The Branch Manager, <br>
                            Canara Bank, <br>
                            Teynampet, <br>
                            Chennai - 18.
                        </td>
                    </tr>
                    <tr>
                        <td width="100%" class="ntd">
                        <br><br>
                         Sir, <br> <br>
                         Sub: Tamil Nadu Labour Welfare Board -Advice to credit Amount by NEFT -Reg.<br> <br>
                         Ref: ' . $ref_message . '<br><br>
                         I request you to kindly debit sum of <b> Rs.' . $money->convertToIndianFormat($this->getTotalClaim($data)) . '/-
                         (' . $tt_claim_word->get_words($this->getTotalClaim($data)) . ') </b>
                         from Board Account Number <b>
                         ' . $this->getBoardBankAc($acc_id) . '
                         </b>and credit the same to individual SB Account through NEFT. <br> <br>
                         </td>
                    </tr>
               </table>';


    $html .= '<table style="border: 1px solid black;border-collapse: collapse;width: 104%">
                    <tr style="background-color: gray;">
                        <th width="6%">Sl.No</th>
                        <th>Claim Reg No.</th>
                        <th>Employee Name</th>
                        <th>A/C Holder Name</th>
                        <th>Bank A/C No.</th>
                        <th>IFSC Code</th>
                        <th>Bank Name</th>
                        <th>Branch</th>
                        <th>Amount</th>
                    </tr>
                <tbody>
              ';

    $total_amount = 0;
    $sn = 1;


    foreach ($data as $val) {
      $total_amount = $total_amount + $val->claim_amount;
      $html .= '
                  <tr>
                    <td style="text-align: center">' . $sn++ . '.</td>
                    <td>' . $val->claim_reg_no . '</td>
                    <td>' . $val->e_name . '</td>
                    <td>' . $val->acc_bank_acc_name . '</td>
                    <td>' . $val->acc_bank_acc_no . '</td>
                    <td>' . $val->acc_ifsc . '</td>
                    <td>' . $val->bank_name . '</td>
                    <td>' . $val->branch_name . '</td>
                    <td>' . $money->convertToIndianFormat($val->claim_amount) . '</td>
                  </tr>
                  ';
    }

    $html .= <<<EOD
                <tr>
                    <th colspan="8" style="text-align: center;font-weight: bold;background-color: lightgray">
                    Total Claim Amount
                    </th>
                    <td style="font-weight: bold;">₹  {$money->convertToIndianFormat($total_amount)}</td>
                </tr>
              </tbody>
              </table>
             EOD;

//    <td style="font-weight: bold">' . $money->convertToIndianFormat($total_amount) . '</td>

//    print_r($key['id']);echo "\n";
//    print_r($key['e_name']);echo "\n";
//    print_r($key['acc_ifsc']);echo "\n";
//    print_r($key['acc_bank_acc_no']);echo "\n";
//    print_r($key['bank_name']);echo "\n";
//    print_r($key['branch_name']);echo "\n";


//    $html = '<table style="border-style: ridge;">
//                <tr><td width="100%">' . $html . '</td></tr>
//             </table>';

    // $pdf->SetFont('times', '', 8);
    $this->SetFont('freesans');
    $this->writeHTML($html, true, false, true, false, '');

    echo $this->Output('ECS.pdf', 'S');
  }

  private function getTotalClaim($data): int
  {
//    $claimTable = new \LWMIS\Clm\Claims();
//    $p = (array)$claimTable->getClaimTables($this->filter)['rows'];
//    $p = $this->filter;
//    var_dump($p);
    $getTotalClaimAmount = 0;
    foreach ($data as $kval) {
      $getTotalClaimAmount = $getTotalClaimAmount + $kval->claim_amount;
    }
    return $getTotalClaimAmount;
  }

  private function getBoardBankAc($acc_id): int|null
  {
    $db = new PostgreDB();
    $db->Begin();
    $rows = [];
    try {
      $sql = "select ac_no from mas.lwb_bank_acc where id = $acc_id";
      $db->Query($sql);
      $rows = $db->FetchAll();
    } catch (\Exception $e) {
//      print_r($e->getMessage());
      \LWMIS\Common\ErrorHandler::custom($e);
    }
//    print_r($rows[0]['ac_no']);
//    var_dump($rows);
    return $rows[0]['ac_no'];
  }

}
