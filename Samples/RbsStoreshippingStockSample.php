<?php
require_once(getcwd() . '/Change/Application.php');

$application = new \Change\Application();
$application->start();

class RbsStoreshippingStockSample
{
	public function import(\Change\Events\Event $event)
	{
		$applicationServices = $event->getApplicationServices();
		$documentManager = $applicationServices->getDocumentManager();

		$dbProvider = $applicationServices->getDbProvider();
		$tm = $applicationServices->getTransactionManager();

		$storeIds = $documentManager->getNewQuery('Rbs_Storelocator_Store')->getDocumentIds();
		echo 'storeIds: ', count($storeIds), PHP_EOL;

		$skuIds = $documentManager->getNewQuery('Rbs_Stock_Sku')->getDocumentIds();
		echo 'skuIds: ', count($skuIds), PHP_EOL;

		$stockQueries = new \Rbs\Storeshipping\Db\StockQueries($dbProvider);

		foreach ($storeIds as $storeId)
		{
			$tm->begin();
			echo 'populate: ', $storeId, PHP_EOL;
			foreach ($skuIds as $skuId)
			{
				$level = rand(0, 210);
				if ($level < 5) {
					continue;
				}
				if ($level <= 10) {
					$level = 0;
				} else {
					$level = $level - 10;
				}
				$stockQueries->insertStock($storeId, $skuId, intval($level));
			}
			$tm->commit();
		}
	}
}

$eventManager = $application->getNewEventManager('ImportSample');
$eventManager->attach('import', function (\Change\Events\Event $event)
{
	(new RbsStoreshippingStockSample())->import($event);
});
$eventManager->trigger('import', null, []);