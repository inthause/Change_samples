<?php
require_once(getcwd() . '/Change/Application.php');

$application = new \Change\Application();
$application->start();

class RbsStorelocatorSample
{

	public function import(\Change\Events\Event $event)
	{
		$application = $event->getApplication();
		$applicationServices = $event->getApplicationServices();

		$LCID = 'fr_FR';
		$applicationServices->getI18nManager()->setLCID($LCID);


		/** @var \Rbs\Generic\GenericServices $genericServices */
		$genericServices = $event->getServices('genericServices');

		$fileName = __DIR__ . '/Assets/Samples/LesBoutiques.json';
		echo 'Import ', $fileName, PHP_EOL;
		$json = json_decode(file_get_contents($fileName), true);
		$storesRawData = $json['rows'];
		echo 'Nb stores ', count($storesRawData), PHP_EOL;
		$documentManager = $event->getApplicationServices()->getDocumentManager();
		$this->documentManager = $documentManager;

		$query = $documentManager->getNewQuery('Rbs_Website_Website');
		$query->addOrder('id');
		$website = $query->getFirstDocument();
		echo 'Published in ', $website, PHP_EOL;

		$addressFields = $applicationServices->getDocumentCodeManager()->getDocumentsByCode('AddressFields', 'Rbs_Storelocator_Setup');
		$addressFields = count($addressFields) ? $addressFields[0] : null;

		echo 'Address format ', $addressFields, PHP_EOL;
		$addressFieldsId = $addressFields ? $addressFields->getId() : null;

		$tm = $event->getApplicationServices()->getTransactionManager();
		$tm->begin();

		$this->addFacet($LCID);

		$storeLocatorIndex  = $this->addStoreLocatorIndex($website, $LCID);

		$indexManager = $genericServices->getIndexManager();
		try
		{
			$indexManager->deleteIndex($storeLocatorIndex);
		}
		catch (\Elastica\Exception\ResponseException $e)
		{
			echo 'New index ', $storeLocatorIndex->getName(), PHP_EOL;
		}

		$indexManager->createIndex($storeLocatorIndex);

		foreach ($storesRawData as $row)
		{
			$storeRawData = $row['value'];
			$code = $storeRawData['reference'];
			$query = $documentManager->getNewQuery('Rbs_Storelocator_Store');
			$query->andPredicates($query->eq('code', $code));

			/** @var \Rbs\Storelocator\Documents\Store $store */
			$store = $query->getFirstDocument();
			if (!$store instanceof \Rbs\Storelocator\Documents\Store)
			{
				$store = $documentManager->getNewDocumentInstanceByModelName('Rbs_Storelocator_Store');
				$store->setCode($code);
				$store->setRefLCID($LCID);
				$store->setAllowPayment(true);
				$store->setAllowRelayMode(true);
				$store->setAllowPickUp(true);
			}
			$store->useCorrection(false);

			$storeLocalisation = $store->getCurrentLocalization();
			$label = isset($storeRawData['card']['name']) ? $storeRawData['card']['name'] : $code;
			$store->setLabel($label);
			$storeLocalisation->setTitle($label);
			if ($website)
			{
				$store->getPublicationSections()->add($website);
			}

			$store->setCoordinates(isset($storeRawData['gps']['coordinates']) ? $storeRawData['gps']['coordinates'] : null);
			if (isset($storeRawData['address']['street1']))
			{
				$street1 = $storeRawData['address']['street1'];
				$street2 = isset($storeRawData['address']['street2']) ? $storeRawData['address']['street2'] : null;

				$addressData = [
					'common' => ['addressFieldsId' => $addressFieldsId],
					'fields' => [
						'name' => $street1,
						'name_extend' => null,
						'street' => $street2,
						'zipCode' => $storeRawData['address']['zipcode'],
						'locality' => $storeRawData['address']['city'],
						'countryCode' => $this->getCountryCode($storeRawData['address']['country'])
					]
				];
				$store->setAddress($genericServices->getGeoManager()->validateAddress($addressData));
			}
			unset($storeRawData['card']['name']);
			$store->setCard($storeRawData['card']);
			if (isset($storeRawData['opening_hours']['hours']))
			{
				$openingHours = [];
				foreach ($storeRawData['opening_hours']['hours'] as $data)
				{
					$openingHours[] = $this->getDayHours($data);
				}
				$store->setOpeningHours($openingHours);
			}

			$store->setTerritorialUnit($this->getTerritorialUnit($storeRawData['address']['zipcode']));

			$store->save();

			echo $store, ' ', $store->getCode(), ' ', $store->getLabel(), PHP_EOL;
		}

		$tm->commit();
	}

	/**
	 * @param $website
	 * @param $LCID
	 * @return \Rbs\Storelocator\Documents\StoreLocatorIndex
	 */
	public function addStoreLocatorIndex($website, $LCID)
	{
		$documentManager = $this->getDocumentManager();
		$query = $documentManager->getNewQuery('Rbs_Storelocator_StoreLocatorIndex');
		$query->andPredicates($query->eq('website', $website), $query->eq('analysisLCID', $LCID));

		$storeLocatorIndex = $query->getFirstDocument();
		if (!$storeLocatorIndex)
		{

			/** @var \Rbs\Storelocator\Documents\StoreLocatorIndex $storeLocatorIndex */
			$storeLocatorIndex = $documentManager->getNewDocumentInstanceByModelName('Rbs_Storelocator_StoreLocatorIndex');

			$storeLocatorIndex->setAnalysisLCID($LCID);
			$storeLocatorIndex->setWebsite($website);

			$storeLocatorIndex->setName('storelocator_sample_fr_fr');
			$storeLocatorIndex->setCategory('storeLocator');
			$storeLocatorIndex->setClientName('front');
			$storeLocatorIndex->save();
		}
		return $storeLocatorIndex;
	}

	public function addFacet($LCID)
	{
		$documentManager = $this->getDocumentManager();
		$query = $documentManager->getNewQuery('Rbs_Elasticsearch_Facet');
		$query->andPredicates($query->eq('indexCategory', 'storeLocator'), $query->eq('label', 'Départements'));

		$facet = $query->getFirstDocument();
		if (!$facet) {
			/** @var \Rbs\Elasticsearch\Documents\Facet $facet */
			$facet = $documentManager->getNewDocumentInstanceByModelName('Rbs_Elasticsearch_Facet');
			$facet->setLabel('Départements');
			$facet->setRefLCID($LCID);
			$facet->getRefLocalization()->setTitle('Départements');
			$facet->setIndexCategory('indexCategory');
			$facet->setConfigurationType('StorelocatorTerritorialUnit');
			$facet->setParameters([
				'unitType' => 'DEPARTEMENT',
				'multipleChoice' => false,
				'showEmptyItem' => false
			]);

			$facet->save();
		}

		$query = $documentManager->getNewQuery('Rbs_Elasticsearch_Facet');
		$query->andPredicates($query->eq('indexCategory', 'storeLocator'), $query->eq('label', 'Régions'));

		$facetRegion = $query->getFirstDocument();
		if (!$facetRegion) {
			/** @var \Rbs\Elasticsearch\Documents\Facet $facetRegion */
			$facetRegion = $documentManager->getNewDocumentInstanceByModelName('Rbs_Elasticsearch_Facet');
			$facetRegion->setLabel('Régions');
			$facetRegion->setRefLCID($LCID);
			$facetRegion->getRefLocalization()->setTitle('Régions');
			$facetRegion->setIndexCategory('indexCategory');
			$facetRegion->setConfigurationType('StorelocatorTerritorialUnit');
			$facetRegion->setParameters([
				'unitType' => 'REGION',
				'multipleChoice' => false,
				'showEmptyItem' => false
			]);
			$facetRegion->setFacets([$facet]);
			$facetRegion->save();
		}
	}
	/**
	 * @param string $zipCode
	 * @return \Rbs\Geo\Documents\TerritorialUnit | null;
	 */
	public function getTerritorialUnit($zipCode)
	{
		$query = $this->documentManager->getNewQuery('Rbs_Geo_TerritorialUnit');
		$query->andPredicates($query->eq('code', substr($zipCode, 0, 2)));
		return $query->getFirstDocument();
	}

	public function getCountryCode($country)
	{
		switch ($country)
		{
			case 'FRA':
				return 'FR';
		}
		throw new \RuntimeException('Invalid country ' . $country);
	}

	public function getDayHours($data)
	{
		switch($data['name']) {
			case 'Dimanche' : $num = 0; break;
			case 'Lundi' : $num = 1; break;
			case 'Mardi' : $num = 2; break;
			case 'Mercredi' : $num = 3; break;
			case 'Jeudi' : $num = 4; break;
			case 'Vendredi' : $num = 5; break;
			case 'Samedi' : $num = 6; break;
			default:
				throw new \RuntimeException('Invalid day ' . $data['name']);
		}

		$day = ['num' => $num, 'viewPos' => (($num + 6) % 7),
			'amBegin' => null, 'amEnd' => null, 'pmBegin' => null, 'pmEnd' => null];
		if ($data['am_start']) {
			$day['amBegin'] = $data['am_start'];
		}
		if ($data['am_end']) {
			$day['amEnd'] = $data['am_end'];
		}
		if ($data['pm_start']) {
			$day['pmBegin'] = $data['pm_start'];
		}
		if ($data['pm_end']) {
			$day['pmEnd'] = $data['pm_end'];
		}

		return $day;
	}

	/**
	 * @var \Change\Documents\DocumentManager
	 */
	protected $documentManager;

	/**
	 * @return \Change\Documents\DocumentManager
	 */
	protected function getDocumentManager()
	{
		return $this->documentManager;
	}
}

$eventManager = $application->getNewEventManager('ImportSample');
$eventManager->attach('import', function (\Change\Events\Event $event)
{
	(new RbsStorelocatorSample())->import($event);
});

$eventManager->trigger('import', null, []);