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

		/** @var \Rbs\Storelocator\StorelocatorServices $storelocatorServices */
		$storelocatorServices = $event->getServices('Rbs_StorelocatorServices');

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
			}
			$store->useCorrection(false);

			$storeLocalisation = $store->getCurrentLocalization();
			$label = isset($storeRawData['card']['name']) ? $storeRawData['card']['name'] : $code;
			$store->setLabel($label);
			$storeLocalisation->setTitle($label);
			if ($website) {
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

			if ($website) {
				$this->publishStore($store, 'PUBLISHABLE');
			}

			echo $store, ' ', $store->getCode(), ' ', $store->getLabel(), PHP_EOL;
		}
		$tm->commit();
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
	 * @var string[]
	 */
	protected $publicationTaskCodes = ['requestValidation', 'contentValidation', 'publicationValidation'];

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

	/**
	 * @param \Rbs\Storelocator\Documents\Store $store
	 * @param string $publicationStatus
	 */
	public function publishStore(\Rbs\Storelocator\Documents\Store $store, $publicationStatus)
	{
		$LCID = $store->getCurrentLCID();
		$currentStatus = $store->getCurrentLocalization()->getPublicationStatus();

		if ($currentStatus  == \Change\Documents\Interfaces\Publishable::STATUS_DRAFT &&
			$publicationStatus == \Change\Documents\Interfaces\Publishable::STATUS_PUBLISHABLE)
		{
			$this->executePublicationTask($store, $LCID, $this->publicationTaskCodes);
		}
		else if ($currentStatus  == \Change\Documents\Interfaces\Publishable::STATUS_FROZEN &&
			$publicationStatus == \Change\Documents\Interfaces\Publishable::STATUS_PUBLISHABLE)
		{
			$this->executePublicationTask($store, $LCID, ['unfreeze']);
		}
		else if ($currentStatus  == \Change\Documents\Interfaces\Publishable::STATUS_PUBLISHABLE &&
			$publicationStatus == \Change\Documents\Interfaces\Publishable::STATUS_FROZEN)
		{
			$this->executePublicationTask($store, $LCID, ['freeze']);
		}
	}

	/**
	 * @param \Change\Documents\AbstractDocument $document
	 * @param string $LCID
	 * @param array $publicationTaskCodes
	 */
	protected function executePublicationTask(\Change\Documents\AbstractDocument $document, $LCID, array $publicationTaskCodes)
	{
		$query = $this->getDocumentManager()->getNewQuery('Rbs_Workflow_Task');

		$query->andPredicates(
			$query->in('taskCode', $publicationTaskCodes),
			$query->eq('document', $document),
			$query->eq('documentLCID', $LCID),
			$query->eq('status', \Change\Workflow\Interfaces\WorkItem::STATUS_ENABLED));

		$task = $query->getFirstDocument();
		if ($task instanceof \Rbs\Workflow\Documents\Task)
		{
			$userId = 0;
			$context = [];
			$workflowInstance = $task->execute($context, $userId);
			if ($workflowInstance)
			{
				$this->executePublicationTask($document, $LCID, $publicationTaskCodes);
			}
		}
	}
}

$eventManager = $application->getNewEventManager('ImportSample');
$eventManager->attach('import', function (\Change\Events\Event $event)
{
	(new RbsStorelocatorSample())->import($event);
});

$eventManager->trigger('import', null, []);