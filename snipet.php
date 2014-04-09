<?php
require_once('/vagrant' . '/Change/Application.php');

$application = new \Change\Application();
$application->start();

$eventManager = $application->getNewEventManager('TEST');

$eventManager->attach('test', function (\Change\Events\Event $event) {

	echo $event->getName(), PHP_EOL;
});
$eventManager->trigger('test', null, []);