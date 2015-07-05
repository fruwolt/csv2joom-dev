<?php
/***************************************************************************************\
**   CSV2Joom import script                                      						**
**   By: frank.ruwolt@web.de                                                      		**
**   Copyright (C) 2015  frank.ruwolt@web.de			                                **
**   Released under GNU GPL Public License                                             **
**   License: http://www.gnu.org/copyleft/gpl.html or have a look                      **
\***************************************************************************************/

defined('_JEXEC') or die('Direct Access to this location is not allowed.');

class JoomMigrateCsv2JoomImg extends JoomMigration
{
  /**
   * The name of the migration
   *
   * @var   string
   * @since 3.0
   */
  protected $migration = 'csv2joomimg';

  //prepare file names (store uploaded files + intermedia data like category ids etc)
  private $sDatafileImgName 		= null;
  private $sAlreadyStoredImgsFile 	= null;
  private $sCatIdMappingFile 		= null;
  private $sLogfileName 			= null;
  // root path of images to be imported (use paths relative to this folder in images csv data file)
  private $sPathWorkingDir			= null;
  
  private $aCatIdMapping = array(); //mapping of old/original cat ids to new ids egeranetd when categories are stored in db
  private $aAlreadyInsertedImgIds = array();   //list of already imported image ids 
  
  private $iImgCsvColumnCount = 6; //how many columns to be in images csv
  
  private $bImportImages = false; //flag indicating to execute importing images
  
  private $bCopyImages = true; //copy images from src folder to joomGallery destination folder
  
  function __construct() {
	  parent::__construct();
	  
	  $this->sDatafileImgName 		= JPATH_SITE . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'csv2joom' . DIRECTORY_SEPARATOR . 'datafileImg.csv';
	  $this->sAlreadyStoredImgsFile = JPATH_SITE . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'csv2joom' . DIRECTORY_SEPARATOR . 'csv2joom_already_stored_imgs.txt';
	  $this->sCatIdMappingFile 		= JPATH_SITE . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'csv2joom' . DIRECTORY_SEPARATOR . 'csv2joom_catmapping.csv';
	  $this->sLogfileName 			= JPATH_SITE . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'csv2joom' . DIRECTORY_SEPARATOR . 'csv2joom.log';
	  $this->sPathWorkingDir		= JPATH_SITE . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'csv2joom' . DIRECTORY_SEPARATOR; 
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
    $task = $this->getTask('images');
  	
    $this->bImportImages = $this->_mainframe->getUserState('joom.migration.import.images');
    
    //import images (read image data from file, replace/update category ids if necessary, generate thumbnail images etc)
    if ($this->bImportImages) {
    	$this->writeLogfile('(re)start importing images', true);
    	 
    	//read category id mapping
    	$this->readCategoryIdMapping();
    	 
    	//read data of already inserted images
    	if (!JFile::exists($this->sAlreadyStoredImgsFile)) {//contains ids of images already stored in db
    		fopen($this->sAlreadyStoredImgsFile, 'w'); //create file (on first run)
    	}
    	
    	$aImgIdLines = file($this->sAlreadyStoredImgsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    	foreach ($aImgIdLines as $sLine) {
    		$aDataTmp = explode(',', $sLine);
    		if (isset($aDataTmp[0]) && isset($aDataTmp[1])) { //0: original image id (in csv file); 1: newly assigned image id
    			$this->aAlreadyInsertedImgIds[$aDataTmp[0]] = $aDataTmp[1];
    		}
    	}
    	$this->writeLogfile('alreadyInserted imgage ids array set to ' . var_export($this->aAlreadyInsertedImgIds, true), false);
    	
    	$aImgData = $this->readImageDataFromFile();
    	
    	
    	$this->writeLogfile('Done reading images data file', true);
    	$this->writeLogfile(count($aImgData) . ' images left to be inserted into db', true);
    	
    	if (count($aImgData) > 0) {
    		$this->createImages($aImgData);
    	}
    	 
    	//change flag to indicate that importing images has finished
    	$this->bImportImages = $this->_mainframe->setUserState('joom.migration.import.images', false);
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
  
  
  protected function readImageDataFromFile() {
  	$aImgDataRTV = array();
  	 
  	if (JFile::exists($this->sDatafileImgName)) {
  		$aLines = file($this->sDatafileImgName);
  		
  		//determine next available image id (by adding 1 to current max image id)
  		$iNextImageId = -1;
  		$query = $this->_db->getQuery(true)
  			->select('MAX(id)')
  			->from($this->_db->qn(_JOOM_TABLE_IMAGES));
  		$this->_db->setQuery($query);
  		$iMaxId = intval($this->_db->loadResult());
  		$iNextImageId = intval($iMaxId + 1);
  		
  		$iCount = 0;
  		foreach ($aLines as $sLine) {
  			if ($iCount == 0) {
  				$iCount++;
  				continue; //skip first row
  			}
  			
  			$aLineParts = str_getcsv($sLine, ',', '"');
  			
  			//filter already inserted images / account only for other/"fresh" images
  			if (!isset($this->aAlreadyInsertedImgIds[$aLineParts[0]])) {
  				
  				if (!isset($this->aCatIdMapping[$aLineParts[1]])) {
  					$this->writeLogfile('No category id mapping found for image with id' . $aLineParts[0] . ' (original cat id: ' . $aLineParts[1] . ')', true);
  				}

  				$aDataTmp = array();
  				$aDataTmp['id'] 				= $iNextImageId++;
  				//$aDataTmp['originalId'] 		= $aLineParts[0]; //just to have original id without offset in dataset array for later user
  				$aDataTmp['catid'] 				= (isset($this->aCatIdMapping[$aLineParts[1]]) ? $this->aCatIdMapping[$aLineParts[1]] : 1);
  				$aDataTmp['imgfilenameFull'] 	= $aLineParts[2]; //special value only for this import, not recognized/needed by joomgallery
  				$aDataTmp['imgfilename'] 		= basename($aLineParts[2]); //just the filename
  				$aDataTmp['imgtitle'] 			= $aLineParts[3];
  				$aDataTmp['imgtext'] 			= $aLineParts[4];
  				$aDataTmp['imgdate'] 			= ($aLineParts[5] != '' ? $aLineParts[5] : date('Y-m-d H:i:s'));
  				$aDataTmp['published'] 			= 1; //maybe add to initial form wether imported categories are published by default or not
  				$aImgDataRTV[$aLineParts[0]] = $aDataTmp;
  			}
  			$iCount++;
  		}
  	}
  	else {
  		$this->writeLogfile('no datafile found with name \'' . $this->sDatafileImgName. '\'', true);
  	}
  	
  	return $aImgDataRTV;
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
  

  /**
   * handle all images (store in db, create thumbnail images etc)
   *
   * @return  void
   * @since   3.0
   */
  function createImages($aImgData) {
  	
  	foreach ($aImgData as $sImgId => $aDataset) { //remember: $aDataset['id'] contains additional offset; $sImgId is the real/original image id from csv file

  		if (JFile::exists($this->sPathWorkingDir . $aDataset['imgfilenameFull'])) {
  			$this->moveAndResizeImage((object)$aDataset, $this->sPathWorkingDir . $aDataset['imgfilenameFull'], null, null, true, $this->bCopyImages);
  			$this->aAlreadyInsertedImgIds[$sImgId] = $aDataset['id'];
  			
  			$this->writeLogfile('inserted image with id ' . $aDataset['id'] . ' (orig id: ' . $sImgId . '; data: ' . var_export($aDataset, true), true);
  		}
  		else {
  			$this->aAlreadyInsertedImgIds[$sImgId] = $aDataset['id'];
  			$this->writeLogfile('no image file found for image with id ' . $aDataset['id'] . ' (orig id: ' . $sImgId . ') at ' . $this->sPathWorkingDir . $aDataset['imgfilenameFull'], true);
  		}
  		
  		if(!$this->checkTime()) {
  			
  			//store already inserted image ids (mapping of original image ids to newly assigned ids) in file to survive refreshing..
  			
  			//store category ids mapping in file to survive refreshing..
  			$sImgIdMappingFileContent = '';
  			foreach ($this->aAlreadyInsertedImgIds as $sImgIdOriginal => $sImgIdNew) {
  				$sImgIdMappingFileContent .= $sImgIdOriginal . ',' . $sImgIdNew . "\n";
  			}
  			file_put_contents($this->sAlreadyStoredImgsFile, $sImgIdMappingFileContent, LOCK_EX);
  			$this->writeLogfile('already inserted images written to file: ' . implode(',', $this->aAlreadyInsertedImgIds), false);
  			
  			$this->refresh('images');
  		}
  	}
  	
  	//finally store last inserted image ids in file
  	$sImgIdMappingFileContent = '';
  	foreach ($this->aAlreadyInsertedImgIds as $sImgIdOriginal => $sImgIdNew) {
  		$sImgIdMappingFileContent .= $sImgIdOriginal . ',' . $sImgIdNew . "\n";
  	}
  	file_put_contents($this->sAlreadyStoredImgsFile, $sImgIdMappingFileContent, LOCK_EX);
  	$this->writeLogfile('already inserted images written to file: ' . implode(',', $this->aAlreadyInsertedImgIds), false);
  }
  
  
  private function checkDatafiles() {
  	
  	try {
  	  	$aFileInfoImg = JRequest::getVar('datafile-img-csv2joom', '', 'files');
  	  	
  	  	//simple check if files are available
  	  	$bImgFileAvailable = false;
  	  	if (isset($aFileInfoImg['name']) &&
  	  			isset($aFileInfoImg['tmp_name']) &&
  	  			isset($aFileInfoImg['error']) &&
  	  			isset($aFileInfoImg['size']) &&
  	  			mb_strlen($aFileInfoImg['name']) > 0 &&
  	  			mb_strlen($aFileInfoImg['tmp_name']) > 0 &&
  	  			$aFileInfoImg['error'] == 0 &&
  	  			$aFileInfoImg['size'] > 0) {
  	  		$bImgFileAvailable = true;
  	  	}
  	  	
  	  	if ($bImgFileAvailable === false) { // in case no file was given
  	  		$this->writeLogfile('No data file specified/uploaded. Stopped.' . "\n" . 'form data: img file: ' . var_export($aFileInfoImg, true), true);
  	  		return false;
  	  	}
  	  	
  	  	$bResultImg = true;
  		if ($bImgFileAvailable) {
  			$bResultImg = move_uploaded_file($aFileInfoImg['tmp_name'], $this->sDatafileImgName);
  		
  			if (!$bResultImg) {
  				$this->writeLogfile('Couldn\'t move uploaded images datafile to target folder. Stopped. ' . "\n" . 'source file (tmp): ' . var_export($aFileInfoImg['tmp_name'], true) . "\n" .  'target: ' . var_export($this->sDatafileImgName, true), true);
  			}
  		}
  		
  		//additionally check column count of csv file
  		if ($bImgFileAvailable && $bResultImg) { 
  			$bResultImg = $this->checkCsvColumnCount($this->sDatafileImgName, $this->iImgCsvColumnCount); 
  		}
  		
  		//check if category id mapping file exists (category ids changed when they've been stored in db, so we need this mapping to update categoy ids of images)
  		if ($bImgFileAvailable && $bResultImg) {
  			if (!JFile::exists($this->sCatIdMappingFile)) {
  				$this->writeLogfile('Missing category id mapping file ' . $this->sCatIdMappingFile, false);
  				$bResultImg = false;
  				echo '<span style="color: red;">Missing category id mapping file ' . $this->sCatIdMappingFile . '. Please import categories first or create file manually.</span>';
  			}
  		}
  		
  		$this->_mainframe->setUserState('joom.migration.import.images', ($bImgFileAvailable && $bResultImg)); //store flag what to do; img file available --> import images  			
  		
  		if ($bImgFileAvailable && $bResultImg) {
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
