<?php
define('PROJECT_HOME', getcwd());
require_once PROJECT_HOME . '/Change/Application.php';
require_once __DIR__ . '/AbstractSample.php';

/**
 * @name \Rbs\Catalog\Setup\Samples\Publish
 */
class Publish extends AbstractSample
{

	/**
	 * @param $model
	 * @return \Change\Documents\Query\Query
	 */
	protected function getNewQuery($model)
	{
		return $this->getApplicationServices()->getDocumentManager()->getNewQuery($model);
	}

	/**
	 * @return \Rbs\User\Documents\User
	 */
	protected function getAdminUser()
	{
		$query = $this->getNewQuery('Rbs_User_User');
		$query->andPredicates($query->eq('login', 'admin'));
		return $query->getFirstDocument();
	}

	/**
	 * @return \Rbs\Workflow\Documents\Task[]
	 */
	protected function getTasks()
	{
		$query = $this->getNewQuery('Rbs_Workflow_Task');
		$query->andPredicates(
			$query->eq('status', 'EN'),
			$query->in('taskCode', array('requestValidation', 'contentValidation', 'publicationValidation'))
		);
		$tasks = $query->getDocuments();
		return $tasks;
	}

	public function install()
	{
		$this->registerServices();

		$user = $this->getAdminUser();
		$this->log('User: ' . $user->getId());

		while (count($tasks = $this->getTasks()))
		{
			$this->log(count($tasks) . ' Tasks to execute');
			foreach ($tasks as $task)
			{
				$this->log(' - ' . $task->getId() . ' ' . $task->getTaskCode() . ' -> ' . $task->getDocument());
				$task->execute(array(), $user->getId());
			}
		}
	}
}

$sample = new Publish();
$sample->install();
