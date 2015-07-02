<?php
/***************************************************************************************\
**   CSV2Joom import script                                      						**
**   By: frank.ruwolt@web.de                                                      		**
**   Copyright (C) 2015  frank.ruwolt@web.de			                                **
**   Released under GNU GPL Public License                                             **
**   License: http://www.gnu.org/copyleft/gpl.html or have a look                      **
\***************************************************************************************/

defined('_JEXEC') or die('Direct Access to this location is not allowed.');

class JoomMigrateCsv2JoomCat extends JoomMigration
{
  /**
   * The name of the migration
   *
   * @var   string
   * @since 3.0
   */
  protected $migration = 'csv2joomcat';

  //prepare file names (store uploaded files + intermedia data like category ids etc)
  private $sDatafileCatName 		= null;
  private $sAlreadyStoredCatsFile 	= null;
  private $sCatIdMappingFile 		= null;
  private $sLogfileName 			= null;
  
  private $aAlreadyInsertedCatIds = array(); //list of already imported category ids
  private $aCatIdMapping = array(); //mapping of old/original cat ids to new ids egeranetd when categories are stored in db
  
  private $iCatCsvColumnCount = 4; //how many columns to be in categories csv
  
  private $bImportCategories = false; //flag indicating to execute importing categories
  
  function __construct() {
	  parent::__construct();
	  
	  $this->sDatafileCatName 		= JPATH_SITE . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'csv2joom' . DIRECTORY_SEPARATOR . 'datafileCat.csv'; 
	  $this->sAlreadyStoredCatsFile = JPATH_SITE . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'csv2joom' . DIRECTORY_SEPARATOR . 'csv2joom_already_stored_cats.txt';
	  $this->sCatIdMappingFile 		= JPATH_SITE . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'csv2joom' . DIRECTORY_SEPARATOR . 'csv2joom_catmapping.csv';
	  $this->sLogfileName 			= JPATH_SITE . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'csv2joom' . DIRECTORY_SEPARATOR . 'csv2joom.log';
  }
  
  
  /**
   * Starts all default migration checks.
   *
   * @param   array   $dirs         Array of directories to search for
   * @param   array   $tables       Array of database tables to search for
   * @param   string  $xml          Path to the XML-File of the required extension
   * @param   string  $min_version  minimal required version, false if no check shall be performed
   * @param   string  $min_version  maximum possible version, false if no check shall be performed
   * @return  void
   * @since   3.0
   */
  public function check($dirs = array(), $tables = array(), $xml = false, $min_version = false, $max_version = false) {
  	$aReady = array();
  	$aReady[] = $this->checkDatafiles();
  	$this->endCheck(!in_array(false, $aReady));
  }
  
  
  protected function endCheck($ready = false) {
  	$displayData = new stdClass();
  	$displayData->ready = $ready;
  	$displayData->url = JRoute::_('index.php?option='._JOOM_OPTION.'&amp;controller=migration');
  	$displayData->migration = $this->migration;
  	
  	$layout = new JLayoutFile('joomgallery.migration.checkend', JPATH_COMPONENT.'/layouts');
  	
  	echo $layout->render($displayData);
  }
  
  /**
   * Main import function
   *
   * @return  void
   * @since   3.0
   */
  protected function doMigration() {
    $task = $this->getTask('categories');
  	
    $this->bImportCategories = $this->_mainframe->getUserState('joom.migration.import.categories');
    
    //import categories (read data from file, sort out already inserted categories, start storing other categories
    if ($this->bImportCategories) {
    	$this->writeLogfile('((re)start importing categories', true);
    	 
    	//read data of already inserted categories
    	if (!JFile::exists($this->sAlreadyStoredCatsFile)) {//contains ids of categories already stored in db
    		fopen($this->sAlreadyStoredCatsFile, 'w'); //create file (on first run)
    	}
    	$aDataTmp = explode(',', fgets(fopen($this->sAlreadyStoredCatsFile, 'r')));
    	foreach ($aDataTmp as $sTmpValue) {
    		$this->aAlreadyInsertedCatIds[$sTmpValue] = $sTmpValue;
    	}
    	$this->writeLogfile('alreadyInserted category id array set to ' . var_export($this->aAlreadyInsertedCatIds, true), false);
    	
    	//read category id mapping
    	$this->readCategoryIdMapping();
    	
    	$aCatData = $this->readCategoriesFromFile();
    	
    	$this->writeLogfile('Daten-Datei Kategorien gelesen', true);
    	$this->writeLogfile(count($aCatData) . ' categories left to be inserted into db', true);
    	 
    	if (count($aCatData) > 0) {
    		$this->createCategoriesInDB($aCatData);
    	}	
    	
    	//change flag to indicate that importing categories has finished
    	$this->bImportCategories = $this->_mainframe->setUserState('joom.migration.import.categories', false);
    }
        
  	
    $this->writeLogfile('csv2joom import done', true);
    //die('csv2joom import done<br>');
    
  }

  /**
   * reads category id mapping from file and stores data in array of this instance
   */
  protected function readCategoryIdMapping() {
  	if (!JFile::exists($this->sCatIdMappingFile)) {
  		fopen($this->sCatIdMappingFile, 'w'); //create file (on first run)
  	}
  	$aCatMappingLines = file($this->sCatIdMappingFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  	foreach ($aCatMappingLines as $sLine) {
  		$aDataTmp = explode(',', $sLine);
  		if (isset($aDataTmp[0]) && isset($aDataTmp[1])) {
  			$this->aCatIdMapping[$aDataTmp[0]] = $aDataTmp[1];
  		}
  	}
  	$this->writeLogfile('categoryMappingIds array set to ' . var_export($this->aCatIdMapping, true), false);
  }
  
  
  protected function readCategoriesFromFile() {
  	$aCatDataRTV = array();
  	
  	if (JFile::exists($this->sDatafileCatName)) {
  		$aLines = file($this->sDatafileCatName);
  		 
  		$iCount = 0;
  		foreach ($aLines as $sLine) {
  			if ($iCount == 0) {
  				$iCount++;
  				continue; //skip first row
  			}
  			$aLineParts = str_getcsv($sLine, ',', '"');
  			
  			//filter already inserted categories / account only for other/"fresh" categories
  			if (!isset($this->aAlreadyInsertedCatIds[$aLineParts[0]])) {
  				$aCatDataRTV[$aLineParts[0]] = array('parentId' => $aLineParts[1], 'name' => $aLineParts[2], 'description' => $aLineParts[3]);
  			}
  			$iCount++;
  		}	
  	}
  	else {
  		$this->writeLogfile('no datafile found with name \'' . $this->sDatafileCatName . '\'', true);
  	}
  	
  	return $aCatDataRTV;
  }

  
  /**
   * Migrates all categories
   *
   * @return  void
   * @since   3.0
   */
  protected function createCategoriesInDB($aCatData)
  {
  	$oController = JControllerLegacy::getInstance('JoomGallery');
  	
  	$sNewCid = null;
  	foreach ($aCatData as $sCatId => $aCatDataset) {

		//setting id directly if storing new category won't work --> img folders cant be created/moved
		//therefore just store category, which then gets new id 
		//--> store new id in mapping array (to be used for determining parent ids)
		//trick/hack: directly save data in POST array, because model->store() reads from POST data (to validate data)... didn't find a way to just set data in model via setter methods...
  		
  		//determine parent category id
  		//special case: parent_id = 0 means the category has to be child of joomgallery root category
  		$iParentId = 1; //default/fallback - if no mapping found, make this category a child of joomgallery root category (has id 1)
  		if ($aCatDataset['parentId'] != 0 && isset($this->aCatIdMapping[$aCatDataset['parentId']])) {
  			$iParentId = $this->aCatIdMapping[$aCatDataset['parentId']];
  		}
  		
  		$_POST['cid'] 			= ''; //$_POST['cid'] = ?; // set to empty string to prevent notice/warning because of missing cid
  		$_POST['name'] 			= $aCatDataset['name'];
  		$_POST['parent_id'] 	= $iParentId; 
  		$_POST['description'] 	= $aCatDataset['description'];
  		$_POST['published'] 	= 1; //maybe add to initial form wether imported categories are published by default or not
  		$_POST['owner'] 		= 0;
  		//any other fields?
  		
  		$oCatModel = $oController->getModel('category'); //create empty cat model
  		
  		if($sNewCid = $oCatModel->store()) { //store model (returns new cat id)
  			$this->writeLogfile('category saved: ' . $sCatId . '/' . var_export($aCatDataset) . ' --> new id: ' . $sNewCid, false);
  			echo ' saved category ' . $aCatDataset['name'] . ' (id old: ' . $sCatId . '/id new: ' . $sNewCid . ')' . '<br>';
  			$this->aCatIdMapping[$sCatId] = $sNewCid; //store mapping
  			$this->aAlreadyInsertedCatIds[$sCatId] = $sCatId;
  		}
  		else {
  			$this->writeLogfile('ERROR saving category with id: ' . $sCatId . '/' . var_export($aCatDataset) . ' --> new id: not assigned; ErrorMsg: ' . $oCatModel->getError(), true);
  			echo ' ERROR saving category ' . $aCatDataset['name'] . ' (id old: ' . $sCatId . '/id new: not assigned; ErrorMsg: ' . $oCatModel->getError() . ')' . '<br>';
  			$this->aAlreadyInsertedCatIds[$sCatId] = $sCatId; // add id to array to prevent trying to store category again
  		}
  		
  		// check if procces needs to restart due to execution time limits
  		if(!$this->checkTime()) {
  			$this->writeLogfile('refresh necessary', true);
  			
  			//store already inserted category ids in file to survive refreshing..
  			file_put_contents($this->sAlreadyStoredCatsFile, implode(',', $this->aAlreadyInsertedCatIds), LOCK_EX);
  			$this->writeLogfile('already inserted written to file: ' . implode(',', $this->aAlreadyInsertedCatIds), false);
  		
  			//store category ids mapping in file to survive refreshing..
  			//for better readability use foreach and string concatentation; else e.g. json_encode/decode would be fine..
  			$sCatMappingFileContent = '';
  			foreach ($this->aCatIdMapping as $sNewCatId => $sOrigCatId) {
  				$sCatMappingFileContent .= $sNewCatId . ',' . $sOrigCatId . "\n";
  			}
  			file_put_contents($this->sCatIdMappingFile, $sCatMappingFileContent, LOCK_EX);
  			$this->writeLogfile('category id mapping written to file: ' . $sCatMappingFileContent, false);
  			
  			$this->refresh();
  		}
  	}
  	
  	$this->writeLogfile('while loop ende', true);
  	
  	//finally store already inserted category ids in file last time
  	file_put_contents($this->sAlreadyStoredCatsFile, implode(',', $this->aAlreadyInsertedCatIds), LOCK_EX);
  	$this->writeLogfile('FINAL: already inserted written to file: ' . implode(',', $this->aAlreadyInsertedCatIds));
  
  	//store category ids mapping in file to survive refreshing..
  	$sCatMappingFileContent = '';
  	foreach ($this->aCatIdMapping as $sNewCatId => $sOrigCatId) {
  		$sCatMappingFileContent .= $sNewCatId . ',' . $sOrigCatId . "\n";
  	}
  	file_put_contents($this->sCatIdMappingFile, $sCatMappingFileContent, LOCK_EX);
  	$this->writeLogfile('FINAL: category id mapping written to file: ' . $sCatMappingFileContent, false);
  }
  
  
  /**
   * returns the maximum category id.
   *
   * @return  int   the maximum category id
   * @since   3.0
   */
  protected function getmaxcategoryid() {
  	return $this->imaxcatid;
  }
  
  
  private function checkDatafiles() {
  	
  	try {
  	  	$aFileInfoCat = JRequest::getVar('datafile-cat-csv2joom', '', 'files');
  	  	
  	  	//simple check if files are available
  	  	$bCatFileAvailable = false;
  	  	if (isset($aFileInfoCat['name']) && 
  	  			isset($aFileInfoCat['tmp_name']) && 
  	  			isset($aFileInfoCat['error']) && 
  	  			isset($aFileInfoCat['size']) && 
  	  			mb_strlen($aFileInfoCat['name']) > 0 && 
  	  			mb_strlen($aFileInfoCat['tmp_name']) > 0 && 
  	  			$aFileInfoCat['error'] == 0 && 
  	  			$aFileInfoCat['size'] > 0) {
  	  		$bCatFileAvailable = true;
  	  	}
  	  	
  	  	if ($bCatFileAvailable === false) { // in case no file was given
  	  		$this->writeLogfile('No data file specified/uploaded. Stopped.' . "\n" . 'form data: category file: ' . var_export($aFileInfoCat, true), true);
  	  		return false;
  	  	}
  	  	
  	  	$bResultCat = true;
  		if ($bCatFileAvailable) {
  			$bResultCat = move_uploaded_file($aFileInfoCat['tmp_name'], $this->sDatafileCatName);
  			if (!$bResultCat) {
  				$this->writeLogfile('Couldn\'t move uploaded categories datafile to target folder. Stopped. ' . "\n" . 'source file (tmp): ' . var_export($aFileInfoCat['tmp_name'], true) . "\n" .  'target: ' . var_export($this->sDatafileCatName, true), true);
  			}
  		}
  		
  		//additionally check column count of csv file
  		if ($bCatFileAvailable && $bResultCat) {
  			$bResultCat = $this->checkCsvColumnCount($this->sDatafileCatName, $this->iCatCsvColumnCount);
  		}
  		
  	  	$this->_mainframe->setUserState('joom.migration.import.categories', ($bCatFileAvailable && $bResultCat)); //store flag what to do; cat file available --> import categories
  		
  		if (($bCatFileAvailable && $bResultCat)) {
  			return true; // at least one data file to return true
  		}
  		
  		return false;
  	}
  	catch (Exception $oExc) {
  		//$this->writeLogfile($oExc->getMessage(), true);
  		//return false;
  		$this->_mainframe->redirect('index.php?option='._JOOM_OPTION.'&controller=migration', $oExc->getMessage(), 'error');
  	}
  }
  
  /**
   * helper function to check number of columns against expected column count of given file
   * doesn't check if file exists/is readable
   * @param unknown $sPathFileName
   * @param unknown $iExpectedColCount
   */
  private function checkCsvColumnCount($sPathFileName, $iExpectedColCount) {
  
	$aLines = file($sPathFileName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  			
  	foreach ($aLines as $sLine) {
  		$aLineParts = str_getcsv($sLine, ',', '"');
  		//check for malformed data (column number mismatch)
  		if (count($aLineParts) != $iExpectedColCount) {
  			$this->writeLogfile('Column count mismatch in csv file ' . $sPathFileName . '. Expected ' . $iExpectedColCount . ' but found ' . count($aLineParts), true);
  			return false;
  		}  				
  	} 
	 	
  	return true;
  }
  
  protected function writeLogfile($sMsg, $bEchoMsg = false) {
  	file_put_contents($this->sLogfileName, date('Ymd H:i:s') . '::' . $sMsg . "\n", FILE_APPEND | LOCK_EX);
  	if ($bEchoMsg) {
  		echo $sMsg, '<br>';
  		
  	}
  	
  }
}
