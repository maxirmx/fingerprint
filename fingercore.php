<?php
/**
 * fingercore. Интерфейс к базе данных
 *
 * @category fingerprint
 * @package fingercore
 * @subpackage main
 * @version 00.04.00
 * @author  Максим Самсонов <maxim@samsonov.net>
 * @copyright  2022 Максим Самсонов, его родственники и знакомые
 * @license    https://github.com/maxirmx/p.samsonov.net/blob/main/LICENSE MIT
 */

/**
 * Class C2CBase
 *
 */
 class C2CBase
 {

/**
 * @static
 * Package version
 */
  protected static $pv = '00.04.00';


/**
 * @static
 * Database handler
 */
    protected static $dbh = NULL;

/**
 * Флаг отладочного режима
 */
   protected $dbg = true;

/**
 * __construct Конструктор
 *
 * @param bool $dbg  флаг отладочного режима
 */
   function __construct($dbg)
   {
     $this->dbg = $dbg;
   }   // C2CBase::__construct

/**
 * debugOutput  Вывод отладочного сообщения
 *
 * @param string $s  Отладочное сообщение
 * @return void
 */
   protected function debugOutput($s)
   {
    if ($this->dbg)  { print $s . (PHP_SAPI==="cli" ? PHP_EOL: '<br/>'); }
   }         // C2CBase::debugOutput

/**
 * pdoError  Вывод сообщения об исключении PDO в отладочном режиме
 *
 * @param object $e  {@link PDO::PDOException}
 * @return void
 */
   protected function pdoError($e)
   {
     $this->debugOutput("PDO Exception: " . $e->getMessage());
   }         // C2CBase::pdoError

/**
 * doSearch() {@link PDO::query()}/{@link PDO::fetch()} wrapper.
 *
 * @param string $s   SQL запрос для поиска
 * @param bool $array Признак поиска одной записи, если false, или всех записей, если true
 * @param bool $assoc Признак поиска возврата нумерованного массива, если false, или ассоциативного , если true
 * @return mixed false в случае ошибки или отсуствия подходящих записей или результат поиска с учетом значения параметра $array
 */
   protected function doSearch($s, $array = false, $assoc = false)
   {
     $this->debugOutput("Executing SQL:'" . $s . "'");
     $q = self::$dbh->query($s);
     $r = $q->fetchAll($assoc ? PDO::FETCH_ASSOC : PDO::FETCH_NUM);
     $q->closeCursor();
     if ($r === false) { return false; }
     else
     {
       if ($array === false) { return count($r) == 0 ? false: $r[0][0]; }
       else                  { return $r;    }
     }
   }  // C2CBase::doSearch

/**
 * doExec() {@link PDO::exec()} wrapper.
 *
 * Необходима для тотального добавления вывода отладочных сообщений в отладочном же режиме
 * @param string $s  SQL запрос для выполнения
 * @return int  количество измененных\удаленных или вставленных записей, как описано в документации на {@link PDO::exec()}
 */
   protected function doExec($s)
   {
    $this->debugOutput("Executing SQL:'" . $s . "'");
    $r = self::$dbh->exec($s);
    return $r;
   }   // C2CBase::doExec
 }

/**
 * Class WDb  Интерфейс к базе данных FingerCore
 */
class WDb extends C2CBase
{
/**
 * Нет ошибки
 */
   const RWD_OK = 0;
/**
 * Код ошибки:     ref only
 */
   const RWD_E_INVALID_COMMAND  = -1;
/**
 * Код ошибки: Ошибка формата ответа сервера
 * Используется только внутри клиентских приложений. Здесь приведено для контроля целостности кодов ошибок.
 */
   const RWD_E_INVALID_RESPONSE = -125;       // Used by JavaScript only
/**
 * Код ошибки: Ошибка базы данных
 */
   const RWD_DB_ERROR  = -126;
/**
 * Код ошибки: Неспецифицированная ошибка
 */
   const RWD_ERROR = -127;

/**
 *
 */
   protected $db = 'sqlite:/var/fingercore/db';
   protected $create = false;
   protected $protect = 7200;  // 60*60*2;
   protected $reconnect = 1800; // 30*60
   protected $blacklist = NULL;

/**
 * __construct Конструктор
 *
 * @param bool $dbg  флаг отладочного режима для инициализации экземпляра класса
 */
   function __construct($dbg = false)
   {
      $this->dbg = $dbg;

      $ini = parse_ini_file("fingercore.ini", true);
      if ($ini)
      {
        if ($ini["database"]["path"] != NULL)     { $this->db = "sqlite:" . $ini["database"]["path"]; }
        if ($ini["database"]["create"] != NULL)   { $this->create = $ini["database"]["create"]; }
        if ($ini["visit"]["protect"] != NULL)     { $this->protect = intval($ini["visit"]["protect"]); }
        if ($ini["visit"]["reconnect"] != NULL)   { $this->reconnect = intval($ini["visit"]["reconnect"]); }
        if ($ini["visit"]["reconnect"] != NULL)   { $this->reconnect = intval($ini["visit"]["reconnect"]); }
        if ($ini["blacklist"]["path"] != NULL)    { $this->blacklist = $ini["blacklist"]["path"]; }
      }
      $this->debugOutput("Database: path='" . $this->db . "' create=" .  $this->create);
      $this->debugOutput("Visit: protect=" . $this->protect . " reconnect=" .  $this->reconnect);
   }   // WDb::__construct

/**
 * errorMessage Текстовое сообщение, соотвествующее коду ошибки
 *
 * @param int $c  код ошибки
 * @return string текстовое сообщение, соответствующее коду ошибки
 */
   public function errorMessage($c)
   {
     $msg = array (
                     WDb::RWD_ERROR              => 'Unspecified error',
                     WDb::RWD_DB_ERROR           => 'Database error',
                     WDb::RWD_E_INVALID_COMMAND  => 'Invalid command',
                     WDb::RWD_E_INVALID_RESPONSE => 'Invalid server response',
                     WDb::RWD_OK                 => 'No error'
                   );
     return $msg[$c];
   }         // WDb::errorMessage

/**
 * queryTable  проверка наличия таблицы в схеме БД
 *
 * Функция не обрабатывает исключения. Обработчик должен быть реализован в вызывающем коде.
 *
 * @param string $s  имя таблицы
 * @return bool true, если таблица есть в схеме БД, false в противном случае
 */
   private function queryTable($s)
   {
       $r = $this->doSearch("SELECT name FROM sqlite_master WHERE type = 'table' AND name = '$s'");
       if ($r === false) { $this->debugOutput("Query table '" . $s . "': does not exist");      }
       else              { $this->debugOutput("Query table '" . $s . "': exists");  $r = true;  }
       return $r;
   }         // WDb::queryTable

/**
 * queryAllTables проверка наличия всех таблиц в базе данных
 *
 * Используется только для отладки.
 * Функция не обрабатывает исключения. Обработчик должен быть реализован в вызывающем коде.
 *
 * @return void
 */
   private function queryAllTables()
   {
     $this->queryTable("Version");
     $this->queryTable("Visits");
   }         // WDb::queryAllTables

/**
 * Connect Создание соединения с базой данных.
 *
 * Функция создает соединение с базой данных.
 *
 * Если база отсуствует и в ini-файле указана необходимость создания базы (секция [database], параметр [create] установлен в true),
 * база создается или при необходимости происходит обновление схемы базы данных до последней версии.
 *
 * Расположение файла базы данных задается ini-файлом: секция [database], параметр [path]. В случае отсуствия ini-файла
 * или указанного параметра значение по умолчанию  - '/usr/local/fingercore/db'.
 *
 * @return int код ошибки, {@link WDb::RWD_OK} означает остуствие ошибки.
 */
   public function Connect()
   {
     try
     {
        if (self::$dbh == NULL)
        {
          $this->debugOutput("Opening database file at: " . $this->db);
          self::$dbh = new PDO($this->db);

          if ($this->create)
          {
            if ($this->queryTable("Visits") === false) {
                if ($this->doExec( <<< __SQL__
                      CREATE TABLE IF NOT EXISTS Visits  (
                           id     INTEGER PRIMARY KEY,
                           fingerprint CHAR[32] NOT NULL ON CONFLICT ABORT UNIQUE ON CONFLICT ABORT,
                           chatid CHAR[32],
                           unixtime INTEGER
                        )
__SQL__
                      ) != 0) { return WDb::RWD_ERROR; }
            }
            if ($this->queryTable("Version") === false) {
               if ($this->doExec( <<< __SQL__
                  CREATE TABLE Version  (
                       id INTEGER PRIMARY KEY,
                       version CHAR[16] NOT NULL ON CONFLICT ABORT UNIQUE ON CONFLICT ABORT
                     )
__SQL__
                  )  != 0) { return WDb::RWD_ERROR; }

                 if ($this->doExec("INSERT INTO Version (version) VALUES ('" . self::$pv . "')") !=1)  { return WDb::RWD_ERROR; }
             }
          }
        }
     }
     catch (PDOException $e)
     {
       $this->pdoError($e);
       return WDb::RWD_ERROR;
     }
     return WDb::RWD_OK;
   }    // WDb::Connect


/**
 * QueryBlacklist()
 *
 * @param string $finger
 *
 */
  private function QueryBlacklist($finger)
  {
    $pos = false;
    if ($this->blacklist != NULL) {
      if ($file = @fopen($this->blacklist, "r")) {
        while(!feof($file) && $pos === false) {
            $line = fgets($file);
            $pos = strpos($line, $finger);
            $this->debugOutput("Blacklist: line '$line', fingerprint '$finger', pos '$pos'");
          }
        fclose($file);
      }
    }
    return $pos;
  }


/**
 * QueryDatabase()
 *
 * @param string $finger
 * @param string $chatid
 *
 */
  private function QueryDatabase($finger, $chatid) {
    $scenario = 'U';
    $this->debugOutput("Query: fingerprint '$finger', chatid '$chatid'");
    try
    {
        $w = time() - $this->protect;
        $this->doExec("DELETE FROM Visits WHERE unixtime < '$w'");
        $visit = $this->doSearch("SELECT unixtime, chatid FROM Visits WHERE fingerprint ='$finger' ORDER BY unixtime ASC LIMIT 1", true);
        if (count($visit) == 0) {
          $this->debugOutput("fingerprint $finger was not found");
          $now = time();
          if (!empty($chatid) && $chatid != '0000') {
            $scenario = 'A';
            $this->debugOutput("Registering chat with chatid $chatid");
            $this->doExec("INSERT INTO Visits (fingerprint, chatid, unixtime) VALUES ('$finger', '$chatid', $now)");
          }
          else {
            $scenario = 'B';
            $this->debugOutput("No chatid has been provided");
          }
          $res = array("ret"=>WDb::RWD_OK, "allow"=>true, "exist"=>false, "wait"=>0);
        }
        else if ($chatid == $visit[0][1]) {
          $scenario = 'C';
          $this->debugOutput("fingerprint $finger was found with matching chatid $chatid");
          $res = array("ret"=>WDb::RWD_OK, "allow"=>true, "exist"=>true, "wait"=>0);
        }
        else {
          $w = $visit[0][0] + $this->protect - time();
          $r = $visit[0][0] + $this->reconnect - time();
          if ($r > 0) {
            $scenario = 'D';
            $this->debugOutput("fingerprint $finger was found without matching chatid, reconnect is allowed to chatid " . $visit[0][1]);
            $this->debugOutput("time-to-wait $w, time-to-reconnect $r");
            $res = array("ret"=>WDb::RWD_OK, "allow"=>false, "exist"=>false, "oldchatid"=>$visit[0][1], "wait"=>$w, "reconnect"=>$r);
          }
          else {
            $scenario = 'E';
            $this->debugOutput("fingerprint $finger was found without matching chatid");
            $this->debugOutput("time-to-wait $w");
            $res = array("ret"=>WDb::RWD_OK, "allow"=>false, "exist"=>false, "wait"=>$w);
          }
        }
    }
    catch (PDOException $e)
    {
      $scenario = 'F';
      $this->pdoError($e);
      $res = array("ret"=>WDb::RWD_DB_ERROR, "allow"=>true, "exist"=>false, "wait"=>0);
    }
    $res['scenario'] = $scenario;
    return $res;
  }    // WDb::Query

/**
 * Query()
 *
 * @param string $finger
 * @param string $chatid
 *
 */
  public function Query($finger, $chatid) {
    if ($this->QueryBlacklist($finger) === false)
      $res = $this->QueryDatabase($finger, $chatid);
    else
      $res = array("ret"=>WDb::RWD_OK, "allow"=>false, "blacklisted"=>true, "scenario"=>'L');
    return $res;
  }

/**
 * showDatabaseVersion() возвращает версию схемы базы данных.
 *
 * Версия берется из таблицы Version.
 *
 * @return string|int версия схемы базы данных или код ошибки
 */
   public function showDatabaseVersion()
   {
     try
     {
         $r = $this->doSearch("SELECT version FROM Version ORDER BY id DESC LIMIT 1");
         return $r;
     }
     catch (PDOException $e)
     {
       $this->pdoError($e);
       return WDb::RWD_ERROR;
     }
   }    // WDb::showDatabaseVersion

/**
 * showSQLiteVersion() возвращает версию клиентской части SQLite.
 *
 * @return string|int версия клиентской части PDO или код ошибки
 */
   public function showSQLiteVersion()
   {
     try
     {
         return self::$dbh->getAttribute(PDO::ATTR_CLIENT_VERSION);
     }
     catch (PDOException $e)
     {
       $this->pdoError($e);
       return WDb::RWD_ERROR;
     }
   }

/**
 * showScriptVersion() возвращает версию скрипта для взаимодействия с базой данных.
 *
 * @return string версия скрипта
 */
   public function showScriptVersion()
   {
     return self::$pv;
   }    // WDb::showScriptVersion
 }      // Class WDb
?>
