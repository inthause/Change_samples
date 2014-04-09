<?php
define('PROJECT_HOME', getcwd());
require_once PROJECT_HOME . '/Change/Application.php';
$application = new \Change\Application();
$application->start();

$evt = new \Change\Events\Event('register');
$eventManager =$application->getNewEventManager('test');
$eventManager->trigger($evt);

$applicationServices = $evt->getApplicationServices();

/** @var $genericServices \Rbs\Generic\GenericServices */
$genericServices = $evt->getServices('genericServices');

/** @var $commerceServices \Rbs\Commerce\CommerceServices */
$commerceServices = $evt->getServices('commerceServices');


$documentManager = $applicationServices->getDocumentManager();

$storageManager = $applicationServices->getStorageManager();

$processes = $documentManager->getNewQuery('Rbs_Commerce_Process')->getDocuments();


$import = new \Rbs\Generic\Json\Import($applicationServices->getDocumentManager());
$import->setDocumentCodeManager($applicationServices->getDocumentCodeManager());
$import->setContextId('Sample_Process');
$resourcePath = __DIR__ . '/Assets';

$preSave = function($document, $jsonArray) use ($resourcePath, $documentManager) {
	if ($document instanceof \Rbs\Media\Documents\Image) {

		$storageURI = $document->getPath();
		copy($resourcePath . '/' . basename($storageURI), $storageURI);
	}
	elseif ($document instanceof \Rbs\Price\Documents\Price)
	{
		if ($document->isNew())
		{
			$document->setWebStore($documentManager->getNewQuery('Rbs_Store_WebStore')->getFirstDocument());
			$document->setBillingArea($documentManager->getNewQuery('Rbs_Price_BillingArea')->getFirstDocument());
		}
	}
};

$import->getOptions()->set('preSave', $preSave);
$applicationServices->getTransactionManager()->begin();
$json = json_decode(file_get_contents($resourcePath . '/' . $import->getContextId() . '.json'), true);
$import->fromArray($json);
$applicationServices->getTransactionManager()->commit();