<?php
  /**
   * fingerxhr. AJAX интерфейс
   *
   * @category fingerprint
   * @package fingercore
   * @subpackage main
   * @version 00.03.00
   * @author  Максим Самсонов <maxim@samsonov.net>
   * @copyright  2022 Максим Самсонов, его родственники и знакомые
   * @license    https://github.com/maxirmx/p.samsonov.net/blob/main/LICENSE MIT
   */

  header("Cache-Control: no-cache, must-revalidate");
  header('Content-type: application/json');

  require(__DIR__. '/fingercore.php');

  $finger = $_GET['finger'];
  $chatid = $_GET['chatid'];

  $rwd = new WDb(false);
  $res = $rwd->Connect();
  $res = $rwd->Query($finger, $chatid);

  print json_encode($res)
?>
