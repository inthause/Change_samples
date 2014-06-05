<?php
require_once('/vagrant' . '/Change/Application.php');

$application = new \Change\Application();
$application->start();

$controller = new \Change\Http\Rest\V1\Controller($application);
$controller->setActionResolver(new \Change\Http\Rest\V1\Resolver());
$request = new \Change\Http\Rest\Request();

$allow = $application->inDevelopmentMode();
$anonymous = function (\Change\Http\Event $event) use ($allow)
{
	$event->getPermissionsManager()->allow($allow);
};

$controller->getEventManager()->attach(\Change\Http\Event::EVENT_REQUEST, $anonymous, 1);
$response = $controller->handle($request);
$response->send();





