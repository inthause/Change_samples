<?php
define('PROJECT_HOME', getcwd());
require_once PROJECT_HOME . '/Change/Application.php';
require_once __DIR__ . '/AbstractSample.php';

class Catalog extends AbstractSample
{

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
			$billingArea->getCurrentLocalization()->setTitle('Euro');
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
			$webStore->getCurrentLocalization()->setTitle('Boutique de démonstration');
			$webStore->setBillingAreas(array($this->getBillingArea()));
			$webStore->setDisplayPricesWithoutTax(true);
			$webStore->setDisplayPricesWithTax(true);
			$webStore->setPricesValueWithTax(false);
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

	public function install()
	{
		$this->registerServices();

		$transactionManager = $this->getApplicationServices()->getTransactionManager();
		$transactionManager->begin();

		$website = $this->getDefaultWebsite();

		$sidebarTemplate = $this->getPageTemplate('Rbs_Demo_Sidebarpage');
		$noSidebarTemplate = $this->getPageTemplate('Rbs_Demo_Nosidebarpage');

		//initialize website
		$params = array_merge($this->getDefaultEventArguments(), [
			'websiteId' => $website->getId(), 'sidebarTemplateId' => $sidebarTemplate->getId(),
			'noSidebarTemplateId' => $noSidebarTemplate->getId(), 'LCID' => 'fr_FR'
		]);
		$event = new \Change\Commands\Events\Event('InitializeWebsiteEvent', $this->getApplication(), $params);
		$response = new \Change\Commands\Events\RestCommandResponse();
		$event->setCommandResponse($response);

		$popinTemplate = $this->getPageTemplate('Rbs_Common_Popin');
		$webStore = $this->getWebStore();
		$context = 'Rbs Generic Website Initialize ' . $website->getId();
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
		$params['userAccountTopicId'] = $userAccountTopic->getId();
		$params['storeId'] = $webStore->getId();
		$event->setParams($params);
		$initializeWebsite = new \Rbs\Commerce\Commands\InitializeWebStore();
		$initializeWebsite->execute($event);

		//initialize order process
		$params['popinTemplateId'] = $popinTemplate->getId();
		$event->setParams($params);
		$initializeWebsite = new \Rbs\Commerce\Commands\InitializeOrderProcess();
		$initializeWebsite->execute($event);

		$transactionManager->commit();
	}
}

$sample = new Catalog();
$sample->install();
