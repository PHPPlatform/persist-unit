<?php
namespace PhpPlatform\Tests\PersistUnit;

class DBInsert extends \PHPUnit_Extensions_Database_Operation_Insert{
	
	/**
	 * @param PHPUnit_Extensions_Database_DB_IDatabaseConnection $connection
	 * @param DataSet $dataSet
	 */
	public function execute(\PHPUnit_Extensions_Database_DB_IDatabaseConnection $connection,\PHPUnit_Extensions_Database_DataSet_IDataSet $dataSet)
	{
		$connection->getConnection()->query("SET foreign_key_checks = 0");
		
		// execute the data files first
		$dataFiles = $dataSet->getDataFiles();
		foreach ($dataFiles as $dataFile){
			$dataFileContent = file_get_contents($dataFile);
			$connection->getConnection()->exec($dataFileContent);
		}
		
		// execute data set
		parent::execute($connection, $dataSet);
		$connection->getConnection()->query("SET foreign_key_checks = 1");
	}
	
}