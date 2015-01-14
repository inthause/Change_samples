<?php
abstract class AbstractSample
{
	/**
	 * @var \Change\Application
	 */
	protected $application;

	/**
	 * @var \Change\Services\ApplicationServices
	 */
	protected $applicationServices;

	/**
	 * @return \Change\Application
	 */
	protected function getApplication()
	{
		if (!$this->application)
		{
			$this->application = new \Change\Application();
		}
		return $this->application;
	}

	/**
	 * @return \Change\Services\ApplicationServices
	 */
	public function getApplicationServices()
	{
		return $this->applicationServices;
	}

	/**
	 * @return \Change\Documents\DocumentManager
	 */
	public function getDocumentManager()
	{
		return $this->getApplicationServices()->getDocumentManager();
	}

	/**
	 * @var \Rbs\Generic\GenericServices
	 */
	protected $genericServices;

	/**
	 * @return \Rbs\Generic\GenericServices
	 */
	public function getGenericServices()
	{
		return $this->genericServices;
	}

	/**
	 * @var \Rbs\Commerce\CommerceServices
	 */
	protected $commerceServices;

	/**
	 * @return \Rbs\Commerce\CommerceServices
	 */
	public function getCommerceServices()
	{
		return $this->commerceServices;
	}

	protected function registerServices()
	{
		$evtManager = $this->getApplication()->getNewEventManager('Sample');
		$registerEvent = new \Change\Events\Event('register', $this);
		$evtManager->trigger($registerEvent);
		$this->applicationServices = $registerEvent->getApplicationServices();
		$this->commerceServices = $registerEvent->getServices('commerceServices');
		$this->genericServices =$registerEvent->getServices('genericServices');
	}

	public function __construct()
	{
		$this->getApplication()->start();
	}

	/**
	 * @param mixed $data
	 */
	protected function log($data)
	{
		if (is_string($data))
		{
			echo $data;
		}
		else
		{
			var_export($data);
		}
		echo PHP_EOL;
	}

	/**
	 * @return \Rbs\Website\Documents\Website
	 */
	protected function getDefaultWebsite()
	{
		$docs = $this->getApplicationServices()->getDocumentCodeManager()->getDocumentsByCode('Website:Default', 'Sample');
		if (count($docs))
		{
			return $docs[0];
		}

		$query = $this->getDocumentManager()->getNewQuery('Rbs_Website_Website');
		$website = $query->getFirstDocument();
		$this->getApplicationServices()->getDocumentCodeManager()->addDocumentCode($website, 'Website:Default', 'Sample');
		return $website;
	}

	/**
	 * @var array
	 */
	protected $pageTemplates = array();

	/**
	 * @param string $code
	 * @return \Rbs\Theme\Documents\Template
	 */
	protected function getPageTemplate($code = 'Rbs_Blank_SidebarPage')
	{
		if (!isset($this->pageTemplates[$code]))
		{
			$query = $this->getDocumentManager()->getNewQuery('Rbs_Theme_Template');
			$this->pageTemplates[$code] = $query->andPredicates($query->eq('code', $code))->getFirstDocument();
		}
		return $this->pageTemplates[$code];
	}

	/**
	 * @param \Rbs\Website\Documents\Section $section
	 * @param string $title
	 * @return \Rbs\Website\Documents\Topic
	 */
	public function getTopic($section, $title)
	{
		$docs = $this->getApplicationServices()->getDocumentCodeManager()->getDocumentsByCode('Section:'.$title, 'Sample');
		if (count($docs))
		{
			return $docs[0];
		}

		/* @var $topic \Rbs\Website\Documents\Topic */
		$topic = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Website_Topic');
		$topic->getCurrentLocalization()->setTitle($title);
		$topic->setLabel($title);
		$topic->setSection($section);
		$topic->save();

		$this->getApplicationServices()->getDocumentCodeManager()->addDocumentCode($topic, 'Section:'.$title, 'Sample');
		return $topic;
	}

	/**
	 * @param \Rbs\Website\Documents\Section $section
	 * @param \Rbs\Theme\Documents\Template $template
	 * @param string $title
	 * @param array $content
	 * @param boolean $hideLinks
	 * @param boolean $updateIfExists
	 * @return \Rbs\Website\Documents\StaticPage
	 */
	public function getStaticPage($section, $template, $title, $content, $hideLinks = false, $updateIfExists = false)
	{
		$docs = $this->getApplicationServices()->getDocumentCodeManager()->getDocumentsByCode('Page:'.$title, 'Sample');
		if (count($docs) && !$updateIfExists)
		{
			return $docs[0];
		}
		elseif (count($docs))
		{
			$page = $docs[0];
		}
		else
		{
			$page = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Website_StaticPage');
		}

		/* @var $page \Rbs\Website\Documents\StaticPage */
		$page->getCurrentLocalization()->setTitle($title);
		$page->setLabel($title);
		$page->setSection($section);
		$page->setPageTemplate($template);
		$page->setHideLinks($hideLinks);
		$page->getCurrentLocalization()->setEditableContent($content);
		$page->save();

		$this->getApplicationServices()->getDocumentCodeManager()->addDocumentCode($page, 'Page:'.$title, 'Sample');
		return $page;
	}

	/**
	 * @param \Rbs\Website\Documents\Website $website
	 * @param \Rbs\Theme\Documents\Template $template
	 * @param string $title
	 * @param array $content
	 * @return \Rbs\Website\Documents\FunctionalPage
	 */
	public function getFunctionalPage($website, $template, $title, $content)
	{
		$docs = $this->getApplicationServices()->getDocumentCodeManager()->getDocumentsByCode('Page:'.$title, 'Sample');
		if (count($docs))
		{
			return $docs[0];
		}

		/* @var $page \Rbs\Website\Documents\FunctionalPage */
		$page = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Website_FunctionalPage');
		$page->getCurrentLocalization()->setTitle($title);
		$page->setLabel($title);
		$page->setWebsite($website);
		$page->setPageTemplate($template);
		$page->getCurrentLocalization()->setEditableContent($content);
		$page->save();

		$this->getApplicationServices()->getDocumentCodeManager()->addDocumentCode($page, 'Page:'.$title, 'Sample');
		return $page;
	}

	/**
	 * @param \Rbs\Website\Documents\Section $section
	 * @param \Rbs\Website\Documents\Page $page
	 * @param string $functionCode
	 * @return \Rbs\Website\Documents\SectionPageFunction
	 */
	public function setSectionPageFunction($section, $page, $functionCode)
	{
		$query = $this->getDocumentManager()->getNewQuery('Rbs_Website_SectionPageFunction');
		$sectionPageFunction = $query->andPredicates($query->eq('functionCode', $functionCode), $query->eq('section', $section))->getFirstDocument();
		if (!$sectionPageFunction)
		{
			/* @var $sectionPageFunction \Rbs\Website\Documents\SectionPageFunction */
			$sectionPageFunction = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Website_SectionPageFunction');
			$sectionPageFunction->setSection($section);
			$sectionPageFunction->setFunctionCode($functionCode);
		}
		$sectionPageFunction->setPage($page);
		$sectionPageFunction->save();
		return $sectionPageFunction;
	}

	/**
	 * @param string $filePath
	 * @param \Rbs\Website\Documents\Website $website
	 * @return \Rbs\Website\Documents\Topic[]
	 */
	public function importTopicJSON($filePath, $website)
	{
		$this->log('Install sections...');
		$topics = array();
		$sectionsData = json_decode(file_get_contents($filePath), true);
		foreach($sectionsData as $sectionData)
		{
			$parent = isset($sectionData['parent']) ? $this->getTopic($website, $sectionData['parent']) : $website;
			$topic = $this->getTopic($parent, $sectionData['title']);
			$this->log(' - ' . $topic->getLabel());
			$topics[$topic->getLabel()] = $topic;
		}
		return $topics;
	}

	/**
	 * @param string $filePath
	 * @param \Rbs\Website\Documents\Website $website
	 * @param array $replacements
	 * @param boolean $updateIfExists
	 */
	public function importPageJSON($filePath, $website, $replacements = array(), $updateIfExists = false)
	{
		$this->log('Install pages...');
		$pageJSON = str_replace('"replace:siteId"', $website->getId(), file_get_contents($filePath));
		foreach ($replacements as $key => $value)
		{
			$pageJSON = str_replace('"replace:' . $key . '"', $value, $pageJSON);
		}
		$pagesData = json_decode($pageJSON, true);
		foreach($pagesData as $pageData)
		{
			if (!isset($pageData['template']) || !isset($pageData['title']) || !isset($pageData['content']))
			{
				$this->log(' - Invalid page: ' . var_export($pageData, true));
				continue;
			}

			$pageTemplate = $this->getPageTemplate($pageData['template']);
			if (isset($pageData['type']) && $pageData['type'] == 'functional')
			{
				$page = $this->getFunctionalPage($website, $pageTemplate, $pageData['title'], $pageData['content']);
				if (isset($pageData['function']))
				{
					if (isset($pageData['section']))
					{
						if (is_array($pageData['section']))
						{
							foreach ($pageData['section'] as $sectionTitle)
							{
								$section = $this->getTopic($website, $sectionTitle);
								$this->applyFunction($section, $page, $pageData['function']);
							}
						}
						else
						{
							$section = $this->getTopic($website, $pageData['section']);
							$this->applyFunction($section, $page, $pageData['function']);
						}
					}
					$this->applyFunction($website, $page, $pageData['function']);
				}
			}
			else
			{
				$parent = isset($pageData['section']) ? $this->getTopic($website, $pageData['section']) : $website;
				$hideLinks = isset($pageData['hideLinks']) ? $pageData['hideLinks'] : false;
				$page = $this->getStaticPage($parent, $pageTemplate, $pageData['title'], $pageData['content'], $hideLinks, $updateIfExists);
				if (isset($pageData['index']) && $pageData['index'] == true)
				{
					$this->applyFunction($parent, $page, 'Rbs_Website_Section');
				}
				if (isset($pageData['function']))
				{
					$this->applyFunction($website, $page, $pageData['function']);
				}
			}
			$this->log(' - ' . $page->getLabel());
		}
	}

	/**
	 * @param \Rbs\Website\Documents\Section $section
	 * @param \Rbs\Website\Documents\Page $page
	 * @param string $functionCode
	 */
	protected function applyFunction($section, $page, $functionCode)
	{
		if (is_array($functionCode))
		{
			foreach ($functionCode as $oneFunctionCode)
			{
				if (is_string($oneFunctionCode))
				{
					$this->setSectionPageFunction($section, $page, $oneFunctionCode);
				}
				else
				{
					$this->log('Invalid function: ' . var_export($oneFunctionCode, true));
				}
			}
		}
		elseif (is_string($functionCode))
		{
			$this->setSectionPageFunction($section, $page, $functionCode);
		}
		else
		{
			$this->log('Invalid function: ' . var_export($functionCode, true));
		}
	}

	abstract public function install();
}