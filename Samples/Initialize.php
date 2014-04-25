<?php
define('PROJECT_HOME', getcwd());
require_once PROJECT_HOME . '/Change/Application.php';
require_once __DIR__ . '/AbstractSample.php';

class Initialize extends AbstractSample
{
	public function install()
	{
		$this->registerServices();

		$transactionManager = $this->getApplicationServices()->getTransactionManager();
		$transactionManager->begin();
		$website = $this->getDefaultWebsite();
		$sidebarTemplate = $this->getPageTemplate('Rbs_Demo_Sidebarpage');
		$noSidebarTemplate = $this->getPageTemplate('Rbs_Demo_Nosidebarpage');
		$popinTemplate = $this->getPageTemplate('Rbs_Demo_Popin');
		$webStore = $this->getWebStore();
		$transactionManager->commit();

		//initialize website
		$context = 'Rbs Generic Website Initialize ' . $website->getId();
		$event = new \Change\Http\Event();
		$event->setParams($this->getDefaultEventArguments());
		$post = new \Zend\Stdlib\Parameters([
			'websiteId' => $website->getId(), 'sidebarTemplateId' => $sidebarTemplate->getId(),
			'noSidebarTemplateId' => $noSidebarTemplate->getId(), 'LCID' => 'fr_FR'
		]);
		$event->setRequest((new \Change\Http\Request())->setPost($post));
		$initializeWebsite = new \Rbs\Generic\Setup\Initialize();
		$initializeWebsite->execute($event);
		$userAccountTopics = $this->getApplicationServices()->getDocumentCodeManager()->getDocumentsByCode('rbs_generic_initialize_user_account_topic', $context);
		if (isset($userAccountTopics[0]) && $userAccountTopics[0] != null)
		{
			$userAccountTopic = $userAccountTopics[0];
		}
		else
		{
			$this->log('ERROR: user account topic can\'t be found after the website initialization');
			return;
		}

		//initialize web store
		$post = new \Zend\Stdlib\Parameters([
			'websiteId' => $website->getId(), 'storeId' => $webStore->getId(), 'sidebarTemplateId' => $sidebarTemplate->getId(),
			'noSidebarTemplateId' => $noSidebarTemplate->getId(), 'LCID' => 'fr_FR', 'userAccountTopicId' => $userAccountTopic->getId()
		]);
		$event->setRequest((new \Change\Http\Request())->setPost($post));
		$initializeWebsite = new \Rbs\Commerce\Setup\InitializeWebStore();
		$initializeWebsite->execute($event);

		//initialize order process
		$post = new \Zend\Stdlib\Parameters([
			'websiteId' => $website->getId(), 'storeId' => $webStore->getId(), 'sidebarTemplateId' => $sidebarTemplate->getId(),
			'noSidebarTemplateId' => $noSidebarTemplate->getId(), 'popinTemplateId' => $popinTemplate->getId(),
			'LCID' => 'fr_FR', 'userAccountTopicId' => $userAccountTopic->getId()
		]);
		$event->setRequest((new \Change\Http\Request())->setPost($post));
		$initializeWebsite = new \Rbs\Commerce\Setup\InitializeOrderProcess();
		$initializeWebsite->execute($event);
	}

	/**
	 * @param string $code
	 * @return \Rbs\Price\Documents\Tax
	 */
	protected function getTaxByeCode($code)
	{
		$query = $this->getDocumentManager()->getNewQuery('Rbs_Price_Tax');
		$query->andPredicates($query->eq('code', $code));
		return $query->getFirstDocument();
	}

	/**
	 * @return \Rbs\Price\Documents\BillingArea
	 */
	protected function getBillingArea()
	{
		$query = $this->getDocumentManager()->getNewQuery('Rbs_Price_BillingArea');
		$billingArea = $query->getFirstDocument();
		if ($billingArea === null)
		{
			/* @var $billingArea \Rbs\Price\Documents\BillingArea */
			$billingArea = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Price_BillingArea');
			$billingArea->setLabel('Sample FR Billing Area');
			$billingArea->setCurrencyCode('EUR');
			$billingArea->setTaxes(array($this->getTaxByeCode('TVAFR')));
			$billingArea->save();
		}

		return $billingArea;
	}

	/**
	 * @return \Rbs\Store\Documents\WebStore
	 */
	protected function getWebStore()
	{
		$query = $this->getDocumentManager()->getNewQuery('Rbs_Store_WebStore');
		$webStore = $query->getFirstDocument();
		if ($webStore === null)
		{
			/* @var $webStore \Rbs\Store\Documents\WebStore */
			$webStore = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Store_WebStore');
			$webStore->setLabel('Sample Web Store');
			$webStore->setBillingAreas(array($this->getBillingArea()));
			$webStore->setPricesValueWithTax(true);
			$webStore->save();
		}
		return $webStore;
	}

	/**
	 * @return array
	 */
	protected function getDefaultEventArguments()
	{
		$arguments = array('application' => $this->getApplication());
		$services = new \Zend\Stdlib\Parameters();
		$services->set('applicationServices', $this->getApplicationServices());
		$services->set('genericServices', $this->genericServices);
		$services->set('commerceServices', $this->commerceServices);
		$arguments['services'] = $services;
		return $arguments;
	}
}

$sample = new Initialize();
$sample->install();