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

$export = new \Rbs\Generic\Json\Export($applicationServices->getDocumentManager());
$export->setDocumentCodeManager($applicationServices->getDocumentCodeManager());
$export->setContextId('Sample_Process');
$resourcePath = __DIR__ . '/Assets';

\Change\Stdlib\File::rmdir($resourcePath);
\Change\Stdlib\File::mkdir($resourcePath);

$export->setDocuments($processes);
$export->addDocuments($documentManager->getNewQuery('Rbs_Shipping_Mode')->getDocuments());
$export->addDocuments($documentManager->getNewQuery('Rbs_Payment_Connector')->getDocuments());
$export->addDocuments($documentManager->getNewQuery('Rbs_Commerce_Fee')->getDocuments());
$export->addDocuments($documentManager->getNewQuery('Rbs_Discount_Coupon')->getDocuments());
$export->addDocuments($documentManager->getNewQuery('Rbs_Discount_Discount')->getDocuments());

$allowedDocumentProperty = function($document, $parentDocument, $parentProperty, $level) use ($export, $documentManager){
	if ($document instanceof \Rbs\Media\Documents\Image) {
		return true;
	}
	if ($document instanceof \Rbs\Stock\Documents\Sku) {
		$q = $documentManager->getNewQuery('Rbs_Price_Price');
		$export->addDocuments($q->andPredicates($q->eq('sku', $document))->getDocuments()->toArray());
		return true;
	}
	return false;
};
$export->getOptions()->set('allowedDocumentProperty', $allowedDocumentProperty);

$buildDocumentCode = function($document, $contextId) {
	if ($document instanceof \Rbs\Shipping\Documents\Mode)
	{
		return 'Mode:' . $document->getCode();
	}
	elseif ($document instanceof \Rbs\Payment\Documents\Connector)
	{
		return  'Connector:' . $document->getCode();
	}
	elseif ($document instanceof \Rbs\Discount\Documents\Coupon)
	{
		return  'Coupon:' . $document->getCode();
	}
	elseif ($document instanceof \Rbs\Stock\Documents\Sku)
	{
		return  'Sku:' . $document->getCode();
	}

	/** @var $document \Change\Documents\AbstractDocument */
	return $document->getId();
};
$export->getOptions()->set('buildDocumentCode', $buildDocumentCode);

$toArray = function($document, $jsonArray) use ($resourcePath) {
	if ($document instanceof \Rbs\Media\Documents\Image) {

		$storageURI = $document->getPath();
		copy($storageURI, $resourcePath . '/' . basename($storageURI));
	}
	return $jsonArray;
};

$export->getOptions()->set('toArray', $toArray);

file_put_contents($resourcePath . '/' . $export->getContextId() . '.json', str_replace('    ', '	',json_encode($export->toArray(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)));