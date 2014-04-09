<?php
define('PROJECT_HOME', getcwd());
require_once PROJECT_HOME . '/Change/Application.php';
require_once __DIR__ . '/AbstractSample.php';

class Order extends AbstractSample
{
	public function install()
	{
		$this->registerServices();

		$transactionManager = $this->getApplicationServices()->getTransactionManager();
		$transactionManager->begin();

		$website = $this->getDefaultWebsite();

		$this->importTopicJSON(__DIR__ . '/Assets/order-topics.json', $website);
		$this->importPageJSON(__DIR__ . '/Assets/order-pages.json', $website);

		$transactionManager->commit();
	}
}

$sample = new Order();
$sample->install();