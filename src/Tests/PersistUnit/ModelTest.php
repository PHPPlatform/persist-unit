<?php

/**
 * User: Raaghu
 * Date: 02-08-2015
 * Time: PM 10:19
 */

namespace PhpPlatform\Tests\PersistUnit;

use PDO;
use PHPUnit_Extensions_Database_TestCase as DBUnitTestcase;
use PhpPlatform\Mock\Config\MockSettings;
use PhpPlatform\Config\SettingsCache;
use PhpPlatform\JSONCache\Cache;
use PhpPlatform\Errors\ErrorHandler;
use PhpPlatform\Persist\Model;
use PhpPlatform\Persist\TransactionManager;

abstract class ModelTest extends DBUnitTestcase{

    private $_dataSet = null;
    private static $_databaseName = "";
    protected static $_pdo = null;
    private static $_connectionParams = null;
    private static $errorLogDir = null;
    
    protected static $enablePersistenceTrace = true;
    protected static $enableApplicationTrace = true;
    protected static $enableHttpTrace = true;
    protected static $enableSystemTrace = true;
    
    
    /**
     * @return string[] of schema sql files to run before tests
     */
    protected static function getSchemaFiles(){
    	return array();
    }
    
    /**
     * @return string[] of schema sql files to run before tests
     */
    protected static function getDataFiles(){
    	return array();
    }
    
    /**
     * @return string[] of data set xml files to create initial data 
     */
    protected static function getDataSetFiles(){
    	return array();
    }
    
    /**
     * @return Cache[] cache objects to be reset for every test
     */
    protected static function getCaches(){
    	return array(Cache::getInstance(),SettingsCache::getInstance());
    }
    
    public static function setUpBeforeClass(){
    	// close connections if any
    	$reflectionProperty = new \ReflectionProperty('PhpPlatform\Persist\Connection\ConnectionFactory', 'connection');
    	$reflectionProperty->setAccessible(true);
    	$conection = $reflectionProperty->getValue(null);
    	if($conection != null){
    		$conection->close();
    		$reflectionProperty->setValue(null,null);
    	}
    	
    	self::$_databaseName = "db".preg_replace('/[^0-9]/', '', microtime());
    	
    	// get connection parameters
    	$host     = getenv("MYSQL_HOST")    ?getenv("MYSQL_HOST")    :(defined('MYSQL_HOST')    ?MYSQL_HOST    :'localhost');
    	$port     = getenv("MYSQL_PORT")    ?getenv("MYSQL_PORT")    :(defined('MYSQL_PORT')    ?MYSQL_PORT    :'3306');
    	$username = getenv("MYSQL_USERNAME")?getenv("MYSQL_USERNAME"):(defined('MYSQL_USERNAME')?MYSQL_USERNAME:'root');
    	$password = getenv("MYSQL_PASSWORD")?getenv("MYSQL_PASSWORD"):(defined('MYSQL_PASSWORD')?MYSQL_PASSWORD:'');
    	
    	if($password == "NO_PASSWORD"){
    		$password = '';
    	}
    	
    	self::$_connectionParams = array(
    		"host"=>$host,
    		"port"=>$port,
    		"username"=>$username,
    		"password"=>$password
    	);
    	
    	// create pdo without database
    	$_pdo = new PDO('mysql:host='.$host.';port='.$port, $username, $password);
    	
    	// create database
    	$result = $_pdo->query("CREATE DATABASE ".self::$_databaseName.";");
    	if($result === false){
    		print_r($_pdo->errorInfo());
    		return ;
    	}
    	unset($result);
    	unset($_pdo);
    	
    	// create pdo for new databse
    	self::$_pdo = new PDO('mysql:host='.$host.';port='.$port.';dbname='.self::$_databaseName, $username,$password);
    	
    	// reset configurations
    	foreach (static::getCaches() as $cacheObj){
    		$cacheObj->reset();
    	}
    	
    	MockSettings::setSettings('php-platform/persist', "mysql.host", $host);
    	MockSettings::setSettings('php-platform/persist', "mysql.port", $port);
    	MockSettings::setSettings('php-platform/persist', "mysql.dbname", self::$_databaseName);
    	MockSettings::setSettings('php-platform/persist', "mysql.username", $username);
    	MockSettings::setSettings('php-platform/persist', "mysql.password", $password);
    	MockSettings::setSettings('php-platform/persist', "mysql.outputDateFormat", "%Y-%m-%d");
    	MockSettings::setSettings('php-platform/persist', "mysql.outputTimeFormat", "%H:%i:%S");
    	MockSettings::setSettings('php-platform/persist', "mysql.outputDateTimeFormat", "%Y-%m-%d %H:%i:%S");
		
    	MockSettings::setSettings("php-platform/session", "session.class", 'PhpPlatform\Tests\PersistUnit\SessionImpl');
    	 
    	$logFile = getenv('sqlLogFile');
    	if($logFile){
    		MockSettings::setSettings('php-platform/persist', "sqlLogFile", $logFile);
    	}
    	self::setTriggers(array());
    	
    	// create schema in database
    	foreach (static::getSchemaFiles() as $schemaSqlFile){
    		$sql = file_get_contents($schemaSqlFile);
    		$result = self::$_pdo->exec($sql);
    		if($result === false){
    			print_r(self::$_pdo->errorInfo());
    			return ;
    		}
    	}
    	
    	// start error handling
    	ErrorHandler::handleError();
    	
    	// create a temporary error log directory
    	$errorLogDir = sys_get_temp_dir().'/icircle/accounts/errors/'.microtime(true);
    	mkdir($errorLogDir,0777,true);
    	chmod($errorLogDir, 0777);
    	
    	self::$errorLogDir = $errorLogDir;
    }
    
    public static function tearDownAfterClass(){
    	
    	// create pdo without database
    	$_pdo = new PDO('mysql:host='.self::$_connectionParams['host'].';port='.self::$_connectionParams['port'], self::$_connectionParams['username'],self::$_connectionParams['password']);
    	 
    	// drop database
    	$result = $_pdo->query("DROP DATABASE ".self::$_databaseName.";");
    	if($result === false){
    		print_r($_pdo->errorInfo());
    		return ;
    	}
    	unset($result);
    	unset($_pdo);
    	
    	// delete error log directory
    	rmdir(self::$errorLogDir);
    }
    
    public function setUp(){
        parent::setUp();
        
        $errorlogFile = self::$errorLogDir.'/'. md5($this->getName());
        // create the error log file , and make it writable by www-data and test executor
        file_put_contents($errorlogFile, '');
        chmod($errorlogFile, 0777);

        $traces = [];
        if(self::$enablePersistenceTrace){$traces['Persistence'] = $errorlogFile;}
        if(self::$enableApplicationTrace){$traces['Application'] = $errorlogFile;}
        if(self::$enableHttpTrace){$traces['Http'] = $errorlogFile;}
        if(self::$enableSystemTrace){$traces['System'] = $errorlogFile;}
        
        // create an temporary error log
        MockSettings::setSettings('php-platform/errors', 'traces', $traces);
    }
    
    public function tearDown(){
        parent::tearDown();
        // display error log if any
        $errorlogFile = self::$errorLogDir.'/'. md5($this->getName());
        if(file_exists($errorlogFile)){
            if($this->hasFailed()){
                echo PHP_EOL.file_get_contents($errorlogFile).PHP_EOL;
            }
            unlink($errorlogFile);
        }
    }
    
    function clearErrorLog(){
        $errorlogFile = self::$errorLogDir.'/'. md5($this->getName());
        if(file_exists($errorlogFile)){
            unlink($errorlogFile);
        }
    }
    
    function assertContainsAndClearLog($message){
        $errorlogFile= self::$errorLogDir.'/'. md5($this->getName());
        $log = "";
        if(file_exists($errorlogFile)){
            $log = file_get_contents($errorlogFile);
        }
        $this->assertContains($message, $log);
        unlink($errorlogFile);
    }
    
    public function getSetUpOperation()
    {
    	return new \PHPUnit_Extensions_Database_Operation_Composite(array(
    			\PHPUnit_Extensions_Database_Operation_Factory::TRUNCATE(),
    			new DBInsert()
    	));
    }

    public function getConnection(){
        return $this->createDefaultDBConnection(self::$_pdo);
    }

	public function getDataset($seedxml = null){
		if(isset($seedxml)){
			$seedContent = file_get_contents($seedxml);
		}else{
			$seedContent = "";
			foreach (static::getDataSetFiles() as $dataSetFile){
				$seedContent .= file_get_contents($dataSetFile);
			}
		}
		
		$seedContent = preg_replace('/\<database[\s]*name[\s]*=[\s]*"[a-zA-Z0-9]*"[\s]*\>/','<database name="'.self::$_databaseName.'">',$seedContent);

		$tmpFile = tempnam(sys_get_temp_dir(),self::$_databaseName);
		file_put_contents($tmpFile,$seedContent);

		return new DataSet(static::getDataFiles(), $tmpFile);
	}
	
	public function getDatasetValue($table,$row,$column = null){
		$value = $this->getDataset()->getTable($table)->getRow($row);
		if(isset($column)){
			$value = $value[$column];
		}
		return $value;
	}
    

    function assertPrimaryIds($expected,$actual,$className,$message = null){
        $i = 0;

        $classReflection = new \ReflectionClass($className);
        $fPrimaryIdReflection = $classReflection->getProperty("fPrimaryId");
        $fPrimaryIdReflection->setAccessible(true);
        foreach($actual as $findResult){
            $actualPrimaryId = $fPrimaryIdReflection->getValue($findResult);
            $this->assertEquals($expected[$i],$actualPrimaryId,$message);
            $i++;
        }
    }

    public function assertSelect($expected,$query,$message = null){
        $dataSet = new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet($expected);
        $tableNames = $dataSet->getTableNames();
        $tableName = $tableNames[0];

        $queryTable = $this->getConnection()->createQueryTable(
            $tableName, $query
        );

        $expectedTable = $dataSet->getTable($tableName);
        $this->assertTablesEqual($expectedTable, $queryTable,$message);
    }
    
    
    public static function setTriggers($triggers){
    	MockSettings::setSettings('php-platform/persist',"triggers",$triggers);
    	
    	// force reset the $_triggers property on PhpPlatform\Persist\Model 
    	$_triggers = new \ReflectionProperty('PhpPlatform\Persist\Model', '_triggers');
    	$_triggers->setAccessible(true);
    	$_triggers->setValue(null,null);
    	
    	if(!defined('TRIGGER_TEST_LOG')){
    		define('TRIGGER_TEST_LOG', 'TRIGGER_TEST_LOG');
    	}
    	
    }
    
    /**   Template Tests for Model CRUD Operations , Override and provide the data through dataProvider **/
    
    /**
     * @dataProvider dataProviderForCreate
     *
     * @param callable $pretest a callback function to be executed before the test
     * @param callable $createFunc a callback function to create the model, should return the created model
     * @param boolean $asSuperUser create using super user context
     * @param callable $selectQuery a callback function which takes the created model and returns a sql query to get data
     * @param array $expectedData expected column values for the newwly inserted row
     * @param null|array $expectedException array with keys "class" and "message" of the exception , if an exception is expected
     */
    protected function _testCreate($pretest,$createFunc,$asSuperUser = false,$selectQuery = null,$expectedData = array(),$expectedException = null){
    	if(is_callable($pretest)){
    		call_user_func_array($pretest, array());
    	}
    	$exception = null;
    	try{
    		if($asSuperUser){
    			$model = null;
    			TransactionManager::executeInTransaction(function() use (&$model,$createFunc){
    				$model = call_user_func_array($createFunc, array());
    			},array(),true);
    		}else {
    			$model = call_user_func_array($createFunc, array());
    		}
    	}catch (\Exception $e){
    		$exception = $e;
    	}
    	
    	if(isset($expectedException)){
    		$this->assertNotNull($exception);
    		$this->assertEquals($expectedException['class'], get_class($exception));
    		$this->assertStringStartsWith($expectedException['message'], $exception->getMessage());
    	}else{
    		$this->assertNull($exception);
    		// validate db for creation
    		$createdRow = $this->getConnection()->createQueryTable('model', call_user_func($selectQuery,$model))->getRow(0);
    		foreach ($expectedData as $key=>$value){
    			$this->assertEquals($value, $createdRow[$key]);
    		}
    	}
    	
    }
    
    
    /**
     *
     * @dataProvider dataProviderForFind
     *
     * @param callable $pretest a callback function to be executed before the test
     * @param callable $findFunc a callback to find function, should return models that found
     * @param boolean $asSuperUser create using super user context
     * @param array $expectedData expected rows
     * @param null|array $expectedException array with keys "class" and "message" of the exception , if an exception is expected
     */
    protected function _testFind($pretest,$findFunc,$asSuperUser = false,$expectedData = array(),$expectedException = null){
    	if(is_callable($pretest)){
    		call_user_func_array($pretest, array());
    	}
    	$exception = null;
    	try{
    		if($asSuperUser){
    			$models = null;
    			TransactionManager::executeInTransaction(function() use (&$models,$findFunc){
    				$models = call_user_func($findFunc);
    			},array(),true);
    		}else {
    			$models = call_user_func($findFunc);
    		}
    	}catch (\Exception $e){
    		$exception = $e;
    	}
    	
    	if(isset($expectedException)){
    		$this->assertNotNull($exception);
    		$this->assertEquals($expectedException['class'], get_class($exception));
    		$this->assertStringStartsWith($expectedException['message'], $exception->getMessage());
    	}else{
    		$this->assertNull($exception);
    		// validate results
    		$this->assertCount(count($expectedData), $models);
    		for($i = 0;$i<count($models);$i++){
    			$this->assertArraySubset($expectedData[$i], $models[$i]->getAttributes('*'));
    		}
    	}
    }
    
    
    /**
     * @dataProvider dataProviderForUpdate
     *
     * @param callable $pretest a callback function to be executed before the test and it shoud return a valid model to update
     * @param array $dataToUpdate data to be used for updation
     * @param boolean $asSuperUser create using super user context
     * @param callable $selectQuery a callback function which takes the created model and returns a sql query to get data
     * @param array $expectedData expected column values for the newly inserted row
     * @param null|array $expectedException array with keys "class" and "message" of the exception , if an exception is expected
     */
    protected function _testUpdate($pretest,$dataToUpdate,$asSuperUser = false,$selectQuery = null,$expectedData = array(),$expectedException = null){
   		/**
   		 * @var Model $model
   		 */
    	$model = call_user_func_array($pretest, array());
    	$exception = null;
    	try{
    		if($asSuperUser){
    			TransactionManager::executeInTransaction(function() use (&$model,$dataToUpdate){
    				$model->setAttributes($dataToUpdate);
    			},array(),true);
    		}else {
    			$model->setAttributes($dataToUpdate);
    		}
    	}catch (\Exception $e){
    		$exception = $e;
    	}
    	
    	if(isset($expectedException)){
    		$this->assertNotNull($exception);
    		$this->assertEquals($expectedException['class'], get_class($exception));
    		$this->assertStringStartsWith($expectedException['message'], $exception->getMessage());
    	}else{
    		$this->assertNull($exception);
    		// validate db for updation
    		$createdRow = $this->getConnection()->createQueryTable('model', call_user_func($selectQuery,$model))->getRow(0);
    		foreach ($expectedData as $key=>$value){
    			$this->assertEquals($value, $createdRow[$key]);
    		}
    	}
    }
    
    /**
     * @dataProvider dataProviderForDelete
     *
     * @param callable $pretest a callback function to be executed before the test and it shoud return a valid model to delete
     * @param boolean $asSuperUser create using super user context
     * @param callable $selectQuery a callback function which takes the created model and returns a sql query to get data
     * @param null|array $expectedException array with keys "class" and "message" of the exception , if an exception is expected
     */
    protected function _testDelete($pretest,$asSuperUser = false,$selectQuery = null,$expectedException = null){
    	/**
    	 * @var Model $model
    	 */
    	$model = call_user_func_array($pretest, array());
    	$exception = null;
    	try{
    		if($asSuperUser){
    			TransactionManager::executeInTransaction(function() use (&$model){
    				$model->delete();
    			},array(),true);
    		}else {
    			$model->delete();
    		}
    	}catch (\Exception $e){
    		$exception = $e;
    	}
    	
    	if(isset($expectedException)){
    		$this->assertNotNull($exception);
    		$this->assertEquals($expectedException['class'], get_class($exception));
    		$this->assertStringStartsWith($expectedException['message'], $exception->getMessage());
    	}else{
    		$this->assertNull($exception);
    		// validate db for deletion
    		$this->assertEquals(0, $this->getConnection()->createQueryTable('model', call_user_func($selectQuery,$model))->getRowCount());
    	}
    }
    
    

}