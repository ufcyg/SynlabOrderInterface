<?php declare(strict_types=1);
namespace SynlabOrderInterface\Core\Api\Utilities;

use Shopware\Core\Content\MailTemplate\Service\MailService;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/*

Contains the mail service and dispatches requested eMails

*/
class OrderInterfaceMailServiceHelper
{
    /** @var MailService $mailserviceInterface */
    private $mailservice;
    public function __construct(MailService $mailservice)
    {
        $this->mailservice = $mailservice;
    }

    public function sendMyMail(string $mailaddress, $recipientName ,$salesChannelID, string $subject, string $notification): void
    {
        $data = new ParameterBag();
        $data->set(
            'recipients',
            [
                $mailaddress => $recipientName
            ]
        );

        $data->set('senderName', 'OrderInterfaceAdministrationBackend');

        $data->set('contentHtml', $notification);
        $data->set('contentPlain', $notification);
        $data->set('subject', $subject);
        $data->set('salesChannelId', $salesChannelID);

        $this->mailservice->send(
            $data->all(),
            Context::createDefaultContext()
        );
    }
}