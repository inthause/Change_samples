<?php
define('PROJECT_HOME', getcwd());
require_once PROJECT_HOME . '/Change/Application.php';
require_once __DIR__ . '/AbstractSample.php';

class ElasticSearch extends AbstractSample
{
	public function install()
	{
		$this->registerServices();

		$transactionManager = $this->getApplicationServices()->getTransactionManager();
		$transactionManager->begin();

		$website = $this->getDefaultWebsite();
		$fullText = $this->getFullText($website);
		echo $fullText, ' ', $fullText->getName(), PHP_EOL;
		$this->importPageJSON(__DIR__ . '/Assets/elasticsearch-pages.json', $website, array('fullTextId' => $fullText->getId()));

		$transactionManager->commit();
	}

	/**
	 * @param \Rbs\Website\Documents\Website $website
	 * @throws RuntimeException
	 * @return \Rbs\Elasticsearch\Documents\FullText
	 */
	protected function getFullText($website)
	{
		$docs = $this->getApplicationServices()->getDocumentCodeManager()->getDocumentsByCode('FullText:default', 'Sample');
		if (count($docs))
		{
			return $docs[0];
		}

		$im = $this->getGenericServices()->getIndexManager();
		if (count($im->getClientsName()) == 0)
		{
			throw new \RuntimeException('no client name defined');
		}

		/* @var $fullText \Rbs\Elasticsearch\Documents\FullText */
		$fullText = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Elasticsearch_FullText');
		$fullText->setClientName($im->getClientsName()[0]);
		$fullText->setWebsite($website);
		$fullText->setAnalysisLCID($this->getDocumentManager()->getLCID());
		$fullText->create();

		$this->getApplicationServices()->getDocumentCodeManager()->addDocumentCode($fullText, 'FullText:default', 'Sample');
		return $fullText;
	}
}

$sample = new ElasticSearch();
$sample->install();
