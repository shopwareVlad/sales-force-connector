<?php

declare(strict_types=1);

namespace ItDe\SalesforceConnector\Controller\Api;

use ItDe\SalesforceConnector\Service\SalesforceConnectionTester;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('framework')]
class SalesforceConnectTestController extends AbstractController
{
    public function __construct(private readonly SalesforceConnectionTester $tester)
    {
    }

    #[Route(
        path: '/api/_action/itde-salesforce/test-connection',
        name: 'api.action.itde_salesforce.test_connection',
        methods: ['POST'],
        defaults: ['_acl' => ['system.plugin_maintain']]
    )]
    public function testConnection(): JsonResponse
    {
        return new JsonResponse($this->tester->run());
    }
}

