<?php

namespace PhpPlatform\Tests\PersistUnit;

class DataSet extends \PHPUnit_Extensions_Database_DataSet_MysqlXmlDataSet{
	
	private $datafiles = array();
	
	function __construct($datafiles,$xmlFile){
		$this->datafiles = $datafiles;
		parent::__construct($xmlFile);
	}
	
	function getDataFiles(){
		return $this->datafiles;
	}
	
}