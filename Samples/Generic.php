<?php
define('PROJECT_HOME', getcwd());
require_once PROJECT_HOME . '/Change/Application.php';
require_once __DIR__ . '/AbstractSample.php';

class Generic extends AbstractSample
{
	public function install()
	{
		$this->registerServices();

		$transactionManager = $this->getApplicationServices()->getTransactionManager();
		$transactionManager->begin();

		$website = $this->getDefaultWebsite();

		$this->importPageJSON(__DIR__ . '/Assets/generic-pages.json', $website);

		$manageTrackersPage = $this->getApplicationServices()->getDocumentCodeManager()->getDocumentsByCode('Page:Politique de Confidentialité et Protection de la Vie Privée', 'Sample');
		$manageTrackersPageId = $manageTrackersPage[0]->getId();

		$this->log('Install texts...');
		$allEvents = array();
		$json = file_get_contents(__DIR__ .'/Assets/generic-texts.json');
		$json = str_replace('replace:manageTrackersPageId', $manageTrackersPageId, $json);
		$allData = json_decode($json, true);
		foreach($allData as $data)
		{
			$doc = $this->addText($data);
			$this->log(' - ' . $doc->getLabel());
			$allEvents[$doc->getLabel()] = $doc->getId();
		}

		$this->log('Update templates...');
		$this->updateTemplates();

		$transactionManager->commit();
	}

	/**
	 * @param array $data
	 * @return \Rbs\Website\Documents\Text
	 */
	public function addText($data)
	{
		$label = $data['label'];
		$docs = $this->getApplicationServices()->getDocumentCodeManager()->getDocumentsByCode('Text:'.$label, 'Sample');
		if (count($docs))
		{
			$text = $docs[0];
		}
		else
		{
			$text = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Website_Text');
		}

		/* @var $text \Rbs\Website\Documents\Text */
		foreach ($data as $propertyName => $restValue)
		{
			$property = $text->getDocumentModel()->getProperty($propertyName);
			if ($property)
			{
				$c = new \Change\Http\Rest\V1\PropertyConverter($text, $property, $this->getDocumentManager());
				$c->setPropertyValue($restValue);
			}
		}
		$text->save();
		$this->getApplicationServices()->getDocumentCodeManager()->addDocumentCode($text, 'Text:'.$label, 'Sample');
		return $text;
	}

	/**
	 * Updates page templates to set parameters on block Rbs_Website_TrackersAskConsent.
	 */
	protected function updateTemplates()
	{
		$params = [];
		$docs = $this->getApplicationServices()->getDocumentCodeManager()->getDocumentsByCode('Text:Bannière d\'acceptation des cookies et traceurs', 'Sample');
		$params['askConsentText'] = $docs[0]->getId();
		$docs = $this->getApplicationServices()->getDocumentCodeManager()->getDocumentsByCode('Text:Confirmation d\'opt out (cookies et traceurs)', 'Sample');
		$params['optOutConfirmationText'] = $docs[0]->getId();
		$docs = $this->getApplicationServices()->getDocumentCodeManager()->getDocumentsByCode('Text:Confirmation d\'opt in (cookies et traceurs)', 'Sample');
		$params['optInConfirmationText'] = $docs[0]->getId();

		$this->log(' - Rbs_Demo_Nosidebarpage');
		$noSidebarTemplate = $this->getPageTemplate('Rbs_Demo_Nosidebarpage');
		$this->updateTemplate($noSidebarTemplate, $params);
		$this->log(' - Rbs_Demo_Sidebarpage');
		$sidebarTemplate = $this->getPageTemplate('Rbs_Demo_Sidebarpage');
		$this->updateTemplate($sidebarTemplate, $params);

		$transactionManager = $this->getApplicationServices()->getTransactionManager();
		try
		{
			$transactionManager->begin();
			$noSidebarTemplate->update();
			$sidebarTemplate->update();
			$transactionManager->commit();
		}
		catch (\Exception $e)
		{
			$transactionManager->rollBack($e);
			$this->getApplicationServices()->getLogging()->error('Error when trying to update templates: ' . $e->getMessage());
		}
	}

	/**
	 * @param \Rbs\Theme\Documents\Template $template
	 * @param array $params
	 */
	protected function updateTemplate($template, $params)
	{
		$layout = new \Change\Presentation\Layout\Layout($template->getEditableContent());
		$block = $layout->getBlockById('trackersAskConsent');

		if ($block && $block->getName() == "Rbs_Website_TrackersAskConsent")
		{
			$parameters = $block->getParameters();
			foreach ($params as $key => $value)
			{
				$parameters[$key] = $value;
			}
			$block->setParameters($parameters);
			$layout->addItem($block);
			$template->setEditableContent($layout->toArray());
		}
	}
}

$sample = new Generic();
$sample->install();