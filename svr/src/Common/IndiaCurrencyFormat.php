<?php

namespace LWMIS\Common;

class IndiaCurrencyFormat
{
  function convertToIndianFormat(int $num): string
  {
//    use number_format() / money_format() fn in php to simplify this
    $explrestunits = "";
    if (strlen($num) > 3) {
      $lastThree = substr($num, strlen($num) - 3, strlen($num));
      $restUnits = substr($num, 0, strlen($num) - 3); // extracts the last three digits
      $restUnits = (strlen($restUnits) % 2 == 1) ? "0" . $restUnits : $restUnits; // explodes the remaining digits in 2's formats, adds a zero in the beginning to maintain the 2's grouping.
      $expUnit = str_split($restUnits, 2);
      for ($i = 0; $i < sizeof($expUnit); $i++) {
        // creates each of the 2's group and adds a comma to the end
        if ($i == 0) {
          $explrestunits .= (int)$expUnit[$i] . ","; // if is first value , convert into integer
        } else {
          $explrestunits .= $expUnit[$i] . ",";
        }
      }
      $thecash = $explrestunits . $lastThree;
    } else {
      $thecash = $num;
    }
    return $thecash; // writes the final format where $currency is the currency symbol.
  }


}
