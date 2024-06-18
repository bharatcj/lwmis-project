<?php

namespace LWMIS\Common;

class ErrorHandler
{
  static function defineErrorLevel(): void
  {
    if (MyConfig::IS_TESTING) {
      // for development
      error_reporting(E_ALL);//<- report all errors
      ini_set('display_errors', 1);
    } else {
      // for production
      error_reporting(E_ERROR);
      ini_set('display_errors', 0);//<- Similar to error_reporting(0);
    }
  }

  static function custom(\Throwable $th): string
  {
    $error_message = 'Unknown error occurred. Reported it to system administrator. Please Try again later.';
    $display_errors = ini_get('display_errors');

    if ($display_errors == "1" || $display_errors == 'On' || $th->getCode() === 0) {
      $error_message = $th->getMessage();
    } else {
      error_log($th->getMessage());
    }

    return $error_message;
  }
}

?>
