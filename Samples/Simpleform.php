<?php
define('PROJECT_HOME', getcwd());
require_once PROJECT_HOME . '/Change/Application.php';
require_once __DIR__ . '/AbstractSample.php';

class Simpleform extends AbstractSample
{
	public function install()
	{
		$this->registerServices();

		$transactionManager = $this->getApplicationServices()->getTransactionManager();
		$transactionManager->begin();

		$website = $this->getDefaultWebsite();

		$this->log('Install forms...');
		$allEvents = array();
		$allData = json_decode(file_get_contents(__DIR__ .'/Assets/simpleform-forms.json'), true);
		foreach($allData as $data)
		{
			$doc = $this->addForm($data, $website);
			$this->log(' - ' . $doc->getLabel());
			$allEvents[$doc->getLabel()] = $doc->getId();
		}

		$transactionManager->commit();
	}

	/**
	 * @param Rbs\Simpleform\Documents\Form $form
	 * @param array $data
	 * @return \Rbs\Simpleform\Documents\FormField
	 */
	public function addField(\Rbs\Simpleform\Documents\Form $form, $data)
	{
		$field = $form->newFormField();
		$field->setRefLCID($form->getRefLCID());
		foreach ($data as $propertyName => $restValue)
		{
			$property = $field->getDocumentModel()->getProperty($propertyName);
			if ($property)
			{
				$c = new \Change\Http\Rest\V1\PropertyConverter($field, $property, $this->getDocumentManager());
				$c->setPropertyValue($restValue);
			}
		}
		if (!$field->getCurrentLocalization()->getTitle())
		{
			$field->getCurrentLocalization()->setTitle($field->getLabel());
		}
		return $field;
	}

	/**
	 * @param array $data
	 * @param \Rbs\Website\Documents\Section $section
	 * @return \Rbs\Simpleform\Documents\Form
	 */
	public function addForm($data, $section)
	{
		/* @var $form \Rbs\Simpleform\Documents\Form */
		$form = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Simpleform_Form');
		$form->setRefLCID($this->getDocumentManager()->getLCID());

		foreach ($data as $propertyName => $restValue)
		{
			if ($propertyName == 'fields')
			{
				$fields = $form->getFields();
				foreach ($restValue as $fieldData)
				{
					$fields->add($this->addField($form, $fieldData));
				}
				continue;
			}
			$property = $form->getDocumentModel()->getProperty($propertyName);
			if ($property)
			{
				$c = new \Change\Http\Rest\V1\PropertyConverter($form, $property, $this->getDocumentManager());
				$c->setPropertyValue($restValue);
			}
		}
		if (!$form->getCurrentLocalization()->getTitle())
		{
			$form->getCurrentLocalization()->setTitle($form->getLabel());
		}
		$form->setPublicationSections(array($section));
		$form->save();
		return $form;
	}
}

$sample = new Simpleform();
$sample->install();
