<?php


namespace Stanford\ExternalModuleDeployment;

use \Firebase\JWT\JWT;

/**
 * Class Client
 * @package Stanford\ExternalModuleDeployment
 * @property \GuzzleHttp\Client $guzzleClient
 * @property string $accessToken
 * @property string $installationId
 * @property string $jwt
 * @property string $githubPrivateKey
 */
class Client extends \GuzzleHttp\Client
{
    private $guzzleClient;

    private $accessToken;

    private $installationId;

    private $jwt;

    private $githubPrivateKey;

    public function __construct($installationId, $githubPrivateKey)
    {
        $this->setInstallationId($installationId);

        $this->setGithubPrivateKey($githubPrivateKey);

        $this->setGuzzleClient(new \GuzzleHttp\Client());
    }

    /**
     * @return \GuzzleHttp\Client
     */
    public function getGuzzleClient(): \GuzzleHttp\Client
    {
        return $this->guzzleClient;
    }

    /**
     * @param \GuzzleHttp\Client $guzzleClient
     */
    public function setGuzzleClient(\GuzzleHttp\Client $guzzleClient): void
    {
        $this->guzzleClient = $guzzleClient;
    }

    /**
     * @return string
     */
    public function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        } else {
            $this->setAccessToken();
            return $this->accessToken;
        }

    }

    /**
     * @param string $accessToken
     */
    public function setAccessToken(): void
    {
        $response = $this->getGuzzleClient()->post('https://api.github.com/app/installations/' . $this->getInstallationId() . '/access_tokens', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getJwt(),
                'Accept' => 'application/vnd.github.v3+json'
            ]
        ]);
        $body = json_decode($response->getBody());
        $this->accessToken = $body->token;
    }

    /**
     * @return string
     */
    public function getInstallationId(): string
    {
        return $this->installationId;
    }

    /**
     * @param string $installationId
     */
    public function setInstallationId(string $installationId): void
    {
        $this->installationId = $installationId;
    }

    /**
     * @return string
     */
    public function getJwt(): string
    {
        if ($this->jwt) {
            return $this->jwt;
        } else {
            $this->setJwt();
            return $this->jwt;
        }

    }

    /**
     * @param string $jwt
     */
    public function setJwt(): void
    {
        $payload = array(
            "iss" => "108296",
            "iat" => time() - 60,
            "exp" => time() + 360
        );
        $privateKey = $this->getGithubPrivateKey();
        $jwt = JWT::encode($payload, $privateKey, 'RS256');
        $this->jwt = $jwt;
    }

    /**
     * @return string
     */
    public function getGithubPrivateKey(): string
    {
        return $this->githubPrivateKey;
    }

    /**
     * @param string $githubPrivateKey
     */
    public function setGithubPrivateKey(string $githubPrivateKey): void
    {
        $this->githubPrivateKey = $githubPrivateKey;
    }


}
