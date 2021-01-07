<?php declare(strict_types=1);
namespace SynlabOrderInterface\Core\Api\Utilities;

use Shopware\Core\Content\MailTemplate\Service\MailService;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class OrderInterfaceMailServiceHelper
{
    /** @var MailService $mailserviceInterface */
    private $mailservice;
    public function __construct(MailService $mailservice)
    {
        $this->mailservice = $mailservice;
    }

    public function sendMyMail($salesChannelID): void
    {
        $data = new ParameterBag();
        $data->set(
            'recipients',
            [
                'iifsanalyzer@gmail.com' => 'Gott'
            ]
        );

        $data->set('senderName', 'OrderInterfaceAdministrationBackend');

        $data->set('contentHtml', 'Foo bar');
        $data->set('contentPlain', 'Foo bar');
        $data->set('subject', 'The subject');
        $data->set('salesChannelId', $salesChannelID);

        $this->mailservice->send(
            $data->all(),
            // $salesChannelContext->getContext(),
            Context::createDefaultContext()
        );
    }
}