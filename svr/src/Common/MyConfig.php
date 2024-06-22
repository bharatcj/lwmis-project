<?php
namespace LWMIS\Common;

class MyConfig
{
  public \DateTime $date;

  function __construct()
  {
    try {
      $this->date = new \DateTime('now', new \DateTimeZone('Asia/Kolkata'));
    } catch (\Exception $e) {
      echo $e;
    }
  }

  const IS_TESTING = true;
//   Development (bala)
//  const pgHost = '127.0.0.1';
//  const pgPort = 5432;
//  const pgDbName = 'lwb';
//  const pgUser = 'postgres';
//  const pgPassword = '123';
//  const pgPersistent = true;

  // BCS server
//  const pgHost = '127.0.0.1';//<- bcs server ip
//  const pgPort = 5432;
//  const pgDbName = 'lwb';
//  const pgUser = 'postgres';
//  const pgPassword = 'Broadline:2024!$99';
//  const pgPersistent = true;

// SDC server
  const pgHost = '10.236.216.109';//<- sdc server ip
  const pgPort = 5432;
  const pgDbName = 'lwb';
  const pgUser = 'postgres';
  const pgPassword = 'bcs@123';
  const pgPersistent = true;

}

?>
