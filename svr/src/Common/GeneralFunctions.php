<?php
namespace LWMIS\Common;

class GeneralFunctions
{
  function getServerTime(): string
  {
    // Process and send Server Time
    // $dt = new \DateTime("now", new \DateTimeZone('Asia/Kolkata'));
    // $dt->add(new \DateInterval('PT3H')); // Change server time for testing
    // $dt->format('Y-m-d H:i:sP')

    $dt = new \DateTime("now", new \DateTimeZone('Asia/Kolkata'));
    return $dt->format('Y-m-d H:i:sP');
  }

  /**
   * add COR Headers after testing for ORIGIN (REQUEST)
   *
   * @return bool true on REQUEST METHOD is OPTIONS else false
   */
  function addCORHeaders(): bool
  {
    $origin = $this->getRequestOrigin();
    $allowed_domains = $this->getAllowedDomains();

    if (in_array(strtolower($origin), $allowed_domains, true)) {
      header('Access-Control-Allow-Origin: *', true);
      header('Access-Control-Allow-Headers: Content-Type, authorization', true);

      header('Access-Control-Allow-Methods: POST', true);
//      header("Content-Security-Policy: default-src 'self'");
      header("X-Powered-By: JAVA-21");//<- to hide/confuse the php version
      header("Server: FreeBSD");//<- to confuse the server name
    }
    return ($_SERVER['REQUEST_METHOD'] == 'OPTIONS');
  }

  function getRequestOrigin()
  {
    $origin = '';
    if (array_key_exists('HTTP_ORIGIN', $_SERVER)) {
      $origin = $_SERVER['HTTP_ORIGIN'];
    } else if (array_key_exists('HTTP_REFERER', $_SERVER)) {
      $origin = $_SERVER['HTTP_REFERER'];
    } else {
      $origin = $_SERVER['REMOTE_ADDR'];
    }
    return $origin;
  }

  function getAllowedDomains(): array
  {
    return ['http://localhost:4200',
      'http://lwmis.lwb.tn.gov.in','https://lwmis.lwb.tn.gov.in',
      'http://www.lwmis.lwb.tn.gov.in','https://www.lwmis.lwb.tn.gov.in',
      'http://lwmis.tnlwb.broadline.co.in','https://lwmis.tnlwb.broadline.co.in'];
  }

  /**
   * add General Headers
   */
  function addGeneralHeaders(): void
  {
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
//    header("Content-Security-Policy: default-src 'self'");
//    header("X-XSS-Protection: 1; mode=block");
    header('Access-Control-Allow-Methods: POST', true);
    header('Access-Control-Expose-Headers: Content-Length, Content-Type, X-TOKEN', true);
  }

  function getIPAddress()
  {
    foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key) {
      if (array_key_exists($key, $_SERVER) === true) {
        foreach (explode(',', $_SERVER[$key]) as $ip) {
          $ip = trim($ip); // just to be safe

          if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            return $ip;
          }
        }
      }
    }
  }

  function generateSecretKey($strength = 16): string
  {
    $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    if (strlen($permitted_chars) < $strength) {
      throw new \Exception('Strength should not be greater than ' . strlen($permitted_chars));
    }
    $randomString = substr(str_shuffle($permitted_chars), 0, $strength);
    return $randomString;
  }

  function checkPasswordPattern($password): bool
  {
    return (\preg_match("/^(?=.*\\W+)(?![\\n])(?=.*[A-Z])(?=.*[a-z])(?=.*\\d).*$/", $password) === 1);
  }

  function log($message): void
  {
    date_default_timezone_set("Asia/Calcutta");
    $logFileName = __DIR__ . "/../../../logs/ge-" . date('ymd') . ".log";
    $txt = '[' . date("Y-m-d h:i:sa") . '] [' . $message . '] ' . PHP_EOL;
    file_put_contents($logFileName, $txt, FILE_APPEND);
  }

  function jobSchedulerLog($method, $message): void
  {
    date_default_timezone_set("Asia/Calcutta");
    $file_path = dirname(__DIR__, 3) . '/logs/cron/' . $method . '/';

    if (!file_exists($file_path)) {
      mkdir($file_path, 0777, true);
    }

    $logFileName = $file_path . $method . date('d-m-y') . '.log';
    $txt = '[' . date("d-m-Y h:i:sa") . '] [' . $message . '] ' . PHP_EOL;
    file_put_contents($logFileName, $txt, FILE_APPEND);
  }

}

?>
