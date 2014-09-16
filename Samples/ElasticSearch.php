<?php
define('PROJECT_HOME', getcwd());
require_once PROJECT_HOME . '/Change/Application.php';
require_once __DIR__ . '/AbstractSample.php';

class ElasticSearch extends AbstractSample
{
	/**
	 * @var \Rbs\Elasticsearch\Documents\Facet[]
	 */
	protected $facets = [];

	public function install()
	{
		$this->registerServices();

		$transactionManager = $this->getApplicationServices()->getTransactionManager();
		$transactionManager->begin();

		$website = $this->getDefaultWebsite();
		$fullText = $this->getFullText($website);
		echo $fullText, ' ', $fullText->getName(), PHP_EOL;
		$pageParams = array(
			'fullTextId' => $fullText->getId(),
			'facetIds' => 0,
			'productPageId' => 0,
			'othersPageId' => 0
		);
		$this->importPageJSON(__DIR__ . '/Assets/elasticsearch-pages.json', $website, $pageParams);

		$storeIndex = $this->getStoreIndex($website);
		if ($storeIndex)
		{
			echo $storeIndex, ' ', $storeIndex->getName(), PHP_EOL;

			$productPage = $this->getApplicationServices()->getDocumentCodeManager()->getDocumentsByCode('Page:Résultat de recherche - produits', 'Sample');
			$othersPage = $this->getApplicationServices()->getDocumentCodeManager()->getDocumentsByCode('Page:Résultat de recherche - autres contenus', 'Sample');
			$facetIds = [];
			$facets = $this->getApplicationServices()->getDocumentCodeManager()->getDocumentsByCode('Facet:all', 'Sample');
			foreach ($facets as $facet)
			{
				$facetIds[] = $facet->getId();
			}

			$pageParams['facetIds'] = implode(',', $facetIds);
			$pageParams['productPageId'] = $productPage[0]->getId();
			$pageParams['othersPageId'] = $othersPage[0]->getId();
			$this->importPageJSON(__DIR__ . '/Assets/elasticsearch-pages.json', $website, $pageParams, true);
		}
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

	/**
	 * @param \Rbs\Website\Documents\Website $website
	 * @throws RuntimeException
	 * @return \Rbs\Elasticsearch\Documents\StoreIndex
	 */
	protected function getStoreIndex($website)
	{
		$docs = $this->getApplicationServices()->getDocumentCodeManager()->getDocumentsByCode('Store:default', 'Sample');
		if (count($docs))
		{
			return $docs[0];
		}

		$im = $this->getGenericServices()->getIndexManager();
		if (count($im->getClientsName()) == 0)
		{
			throw new \RuntimeException('no client name defined');
		}

		$attributes = $this->getDocumentManager()->getNewQuery('Rbs_Catalog_Attribute')->getDocuments();
		if ($attributes->count())
		{
			/* @var $storeIndex \Rbs\Elasticsearch\Documents\FullText */
			$storeIndex = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Elasticsearch_StoreIndex');
			$storeIndex->setClientName($im->getClientsName()[0]);
			$storeIndex->setWebsite($website);
			$storeIndex->setAnalysisLCID($this->getDocumentManager()->getLCID());
			$storeIndex->create();
			$this->getApplicationServices()->getDocumentCodeManager()->addDocumentCode($storeIndex, 'Store:default', 'Sample');

			/** @var $facet \Rbs\Elasticsearch\Documents\Facet */
			$facet = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Elasticsearch_Facet');
			$facet->setLabel('prix');
			$facet->getCurrentLocalization()->setTitle('Prix');
			$facet->setConfigurationType('Price');
			$facet->save();
			$this->getApplicationServices()->getDocumentCodeManager()->addDocumentCode($facet, 'Facet:all', 'Sample');

			/** @var $attribute \Rbs\Catalog\Documents\Attribute */
			foreach ($attributes as $attribute)
			{
				if ($attribute->getLabel() == 'marque')
				{
					/** @var $facet \Rbs\Elasticsearch\Documents\Facet */
					$facet = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Elasticsearch_Facet');
					$facet->setLabel('marque');
					$facet->getCurrentLocalization()->setTitle('Marque');
					$facet->setConfigurationType('Attribute');
					$facet->getParameters()->set('attributeId', $attribute->getId());
					$facet->save();
					$this->getApplicationServices()->getDocumentCodeManager()->addDocumentCode($facet, 'Facet:all', 'Sample');
				}
			}

			/** @var $facet \Rbs\Elasticsearch\Documents\Facet */
			$facet = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Elasticsearch_Facet');
			$facet->setLabel('Disponibilité');
			$facet->getCurrentLocalization()->setTitle('Disponibilité');
			$facet->setConfigurationType('SkuThreshold');
			$facet->save();
			$this->getApplicationServices()->getDocumentCodeManager()->addDocumentCode($facet, 'Facet:all', 'Sample');
			return $storeIndex;
		}
		return null;
	}
}

$sample = new ElasticSearch();
$sample->install();
