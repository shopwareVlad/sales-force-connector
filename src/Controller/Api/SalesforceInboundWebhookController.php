<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\Controller\Api;

use ItDe\SalesforceConnector\MessageQueue\Message\InboundSyncContactMessage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('framework')]
class SalesforceInboundWebhookController extends AbstractController
{
    private const SF_ORG_ID_HEADER = 'X-Salesforce-OrgId';

    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[Route(
        path: '/api/_action/itde-salesforce/inbound-webhook',
        name: 'api.action.itde_salesforce.inbound_webhook',
        methods: ['POST'],
        defaults: ['auth_required' => false]
    )]
    public function handle(Request $request): Response
    {
        $xml = $request->getContent();

        if (empty($xml)) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        try {
            $contactIds = $this->extractContactIds($xml);
        } catch (\Throwable) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $context = Context::createDefaultContext();
        foreach ($contactIds as $sfId) {
            $this->bus->dispatch(new InboundSyncContactMessage($sfId, $context));
        }

        return new Response(
            $this->buildAckResponse(),
            Response::HTTP_OK,
            ['Content-Type' => 'text/xml; charset=utf-8']
        );
    }

    private function extractContactIds(string $xml): array
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml, \LIBXML_NOERROR | \LIBXML_NOWARNING);

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('sf', 'urn:sobject.enterprise.soap.sforce.com');

        $ids = [];
        $nodes = $xpath->query('//sf:Id');
        foreach ($nodes as $node) {
            $id = trim($node->textContent);
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_unique($ids);
    }

    private function buildAckResponse(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    <soapenv:Body>
        <notificationsResponse xmlns="http://soap.sforce.com/2005/09/outbound">
            <Ack>true</Ack>
        </notificationsResponse>
    </soapenv:Body>
</soapenv:Envelope>
XML;
    }
}
