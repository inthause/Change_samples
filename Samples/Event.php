<?php
define('PROJECT_HOME', getcwd());
require_once PROJECT_HOME . '/Change/Application.php';
require_once __DIR__ . '/AbstractSample.php';

class Event extends AbstractSample
{
	public function install()
	{
		$this->registerServices();

		$transactionManager = $this->getApplicationServices()->getTransactionManager();
		$transactionManager->begin();

		$website = $this->getDefaultWebsite();

		$allTopics = $this->importTopicJSON(__DIR__ . '/Assets/event-topics.json', $website);
		$this->importPageJSON(__DIR__ . '/Assets/event-pages.json', $website);

		$allImages = array();

		$this->log('Install categories...');
		$allCategories = array();
		$allData = json_decode(file_get_contents(__DIR__ .'/Assets/event-categories.json'), true);
		foreach($allData as $data)
		{
			$doc = $this->addCategory($data, $allTopics);
			$this->log(' - ' . $doc->getLabel());
			$allCategories[$doc->getLabel()] = $doc->getId();
		}

		$this->log('Install events...');
		$allEvents = array();
		$allData = json_decode(file_get_contents(__DIR__ .'/Assets/event-events.json'), true);
		foreach($allData as $data)
		{
			$doc = $this->addEvent($data, $allTopics, $allCategories, $allImages);
			$this->log(' - ' . $doc->getLabel());
			$allEvents[$doc->getLabel()] = $doc->getId();
		}

		$this->log('Install news...');
		$allNews = array();
		$allData = json_decode(file_get_contents(__DIR__ .'/Assets/event-news.json'), true);
		foreach($allData as $data)
		{
			$doc = $this->addNews($data, $allTopics, $allCategories, $allImages);
			$this->log(' - ' . $doc->getLabel());
			$allNews[$doc->getLabel()] = $doc->getId();
		}

		$transactionManager->commit();
	}

	/**
	 * @param array $data
	 * @param array $allSections
	 * @param array $allCategories
	 * @param array $allImages
	 * @return \Rbs\Event\Documents\Event
	 */
	public function addEvent($data, $allSections, $allCategories, $allImages)
	{
		/* @var $event \Rbs\Event\Documents\Event */
		$event = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Event_Event');
		foreach ($data as $propertyName => $restValue)
		{
			if ($propertyName == 'listVisual' || $propertyName == 'detailVisual')
			{
				$restValue = $allImages[$restValue];
			}
			elseif ($propertyName == 'categories')
			{
				foreach ($restValue as $i => $v)
				{
					$restValue[$i] = $allCategories[$v];
				}
			}
			elseif ($propertyName == 'publicationSections')
			{
				foreach ($restValue as $i => $v)
				{
					$restValue[$i] = $allSections[$v]->getId();
				}
			}
			$property = $event->getDocumentModel()->getProperty($propertyName);
			if ($property)
			{
				$c = new \Change\Http\Rest\PropertyConverter($event, $property, $this->getDocumentManager());
				$c->setPropertyValue($restValue);
			}
		}
		$event->save();
		return $event;
	}

	/**
	 * @param array $data
	 * @param array $allSections
	 * @param array $allCategories
	 * @param array $allImages
	 * @return \Rbs\Event\Documents\News
	 */
	public function addNews($data, $allSections, $allCategories, $allImages)
	{
		/* @var $news \Rbs\Event\Documents\Event */
		$news = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Event_News');
		foreach ($data as $propertyName => $restValue)
		{
			if ($propertyName == 'listVisual' || $propertyName == 'detailVisual')
			{
				$restValue = $allImages[$restValue];
			}
			elseif ($propertyName == 'categories')
			{
				foreach ($restValue as $i => $v)
				{
					$restValue[$i] = $allCategories[$v];
				}
			}
			elseif ($propertyName == 'publicationSections')
			{
				foreach ($restValue as $i => $v)
				{
					$restValue[$i] = $allSections[$v]->getId();
				}
			}
			$property = $news->getDocumentModel()->getProperty($propertyName);
			if ($property)
			{
				$c = new \Change\Http\Rest\PropertyConverter($news, $property, $this->getDocumentManager());
				$c->setPropertyValue($restValue);
			}
		}
		$news->save();
		return $news;
	}

	/**
	 * @param array $data
	 * @param array $allSections
	 * @return \Rbs\Event\Documents\Category
	 */
	public function addCategory($data, $allSections)
	{
		/* @var $category \Rbs\Event\Documents\Category */
		$category = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Event_Category');
		foreach ($data as $propertyName => $restValue)
		{
			if ($propertyName == 'publicationSections')
			{
				foreach ($restValue as $i => $v)
				{
					$restValue[$i] = $allSections[$v]->getId();
				}
			}
			$property = $category->getDocumentModel()->getProperty($propertyName);
			if ($property)
			{
				$c = new \Change\Http\Rest\PropertyConverter($category, $property, $this->getDocumentManager());
				$c->setPropertyValue($restValue);
			}
		}
		$category->save();
		return $category;
	}
}

$sample = new Event();
$sample->install();
