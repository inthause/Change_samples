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
		$transactionManager->commit();

		//initialize website
		$params = array_merge($this->getDefaultEventArguments(), [
			'websiteId' => $website->getId(), 'sidebarTemplateId' => $sidebarTemplate->getId(),
			'noSidebarTemplateId' => $noSidebarTemplate->getId(), 'LCID' => 'fr_FR'
		]);
		$event = new \Change\Commands\Events\Event('InitializeWebsiteEvent', $this->getApplication(), $params);
		$response = new \Change\Commands\Events\RestCommandResponse();
		$event->setCommandResponse($response);
		$initializeWebsite = new \Rbs\Generic\Commands\InitializeWebsite();
		$initializeWebsite->execute($event);

		$context = 'Rbs Generic Website Initialize ' . $website->getId();
		$userAccountTopics = $this->getApplicationServices()->getDocumentCodeManager()->getDocumentsByCode('rbs_generic_initialize_user_account_topic', $context);
		if (isset($userAccountTopics[0]) && $userAccountTopics[0] instanceof \Rbs\Website\Documents\Section)
		{
			/* @var $userAccountTopic \Rbs\Website\Documents\Section */
			$userAccountTopic = $userAccountTopics[0];
			if (!count($userAccountTopic->getAuthorizedGroups()))
			{
				$query = $this->getApplicationServices()->getDocumentManager()->getNewQuery('Rbs_User_Group');
				$groups = $query->andPredicates($query->eq('realm', 'web'))->getDocuments();
				if (count($groups))
				{
					$transactionManager->begin();
					$userAccountTopic->setAuthorizedGroups($groups->toArray());
					$userAccountTopic->update();
					$transactionManager->commit();
				}
			}

		}
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