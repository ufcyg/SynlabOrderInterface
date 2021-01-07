<?php declare(strict_types=1);

namespace SynlabOrderInterface\Core\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
//currently not used
class OrderInterfaceRestApiHandler
{
    /** @var Client */
    private $restClient;

    /** @var SystemConfigService */
    private $config;

    /** @var string */
    private $accessToken;

    /** @var string */
    private $refreshToken;

    /** @var \DateTimeInterface */
    private $expiresAt;

    public function __construct(SystemConfigService $config)
    {
        $this->restClient = new Client();
        $this->config = $config;
    }

    private function getAdminAccess(): void
    {
        $body = \json_encode([
            'client_id' => $this->config->get('SynlabOrderInterface.config.username'),
	        'client_secret' => $this->config->get('SynlabOrderInterface.config.password'),
            'grant_type' => "client_credentials",
            'scopes' => $this->config->get('SynlabOrderInterface.config.scope')
        ]);

        $request = new Request(
            'POST',
            getenv('APP_URL') . '/api/oauth/token',
            ['Content-Type' => 'application/json'],
            $body
        );

        $response = $this->restClient->send($request);

        $body = json_decode($response->getBody()->getContents(), true);

        $this->setAccessData($body);
    }

    private function setAccessData(array $body): void
    {
        $this->accessToken = $body['access_token'];
        $this->expiresAt = $this->calculateExpiryTime((int) $body['expires_in']);
    }

    private function calculateExpiryTime(int $expiresIn): \DateTimeInterface
    {
        $expiryTimestamp = (new \DateTime())->getTimestamp() + $expiresIn;

        return (new \DateTimeImmutable())->setTimestamp($expiryTimestamp);
    }

    private function createShopwareApiRequest(string $method, string $uri, ?string $body = null): RequestInterface
    {
        return new Request(
            $method,
            getenv('APP_URL') . '/api/v3/' . $uri,
            [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => '*/*'
            ],
            $body
        );
    }

    private function send(RequestInterface $request, string $uri)
    {
        if ($this->expiresAt <= (new \DateTime())) {
            $this->refreshAuthToken();

            $body = $request->getBody()->getContents();

            $request = $this->createShopwareApiRequest($request->getMethod(), $uri, $body);
        }

        return $this->restClient->send($request);
    }

    private function refreshAuthToken(): void
    {
        $body = \json_encode([
            

            'client_id' => 'administration',
            'grant_type' => 'refresh_token',
            'scopes' => $this->config->get('SynlabOrderInterface.config.scope'),
            'refresh_token' => $this->refreshToken
        ]);

        $request = new Request(
            'POST',
            getenv('APP_URL') . '/api/oauth/token',
            ['Content-Type' => 'application/json'],
            $body
        );

        $response = $this->restClient->send($request);

        $body = json_decode($response->getBody()->getContents(), true);

        $this->setAccessData($body);
    }

    public function request(string $method, string $uri, ?array $body = null): ResponseInterface
    {
        if ($this->accessToken === null || $this->refreshToken === null || $this->expiresAt === null) {
            $this->getAdminAccess();
        }

        $bodyEncoded = json_encode($body);

        $request = $this->createShopwareApiRequest($method, $uri, $bodyEncoded);

        return $this->send($request, $uri);
    }
}