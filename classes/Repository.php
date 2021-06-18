<?php


namespace Stanford\ExternalModuleDeployment;

/**
 * Class repository
 * @package Stanford\ExternalModuleDeployment
 * @property array
 * @property Client $client
 */
class Repository
{

    private $record;

    private $client;

    public function __construct($client, $record = null)
    {
        $this->setClient($client);
        if (!is_null($record)) {
            $this->setRecord($record);
        }

    }

    public function getLatestCommitForBranchForREDCapBuild($key, $branch)
    {
        $response = $this->getClient()->getGuzzleClient()->get('https://api.github.com/repos/susom/' . $key . '/commits/' . $branch, [
            'headers' => [
                'Authorization' => 'token ' . $this->getClient()->getAccessToken(),
                'Accept' => 'application/vnd.github.v3+json'
            ]
        ]);
        return json_decode($response->getBody());
    }

    /**
     * @param $key
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getRepositoryBody($key)
    {
        if (!$key) {
            throw new \Exception("data is missing $key");
        }
        $response = $this->getClient()->getGuzzleClient()->get('https://api.github.com/repos/susom/' . $key, [
            'headers' => [
                'Authorization' => 'token ' . $this->getClient()->getAccessToken(),
                'Accept' => 'application/vnd.github.v3+json'
            ]
        ]);
        return json_decode($response->getBody());
    }

    /**
     * @param $key
     * @param $branch
     * @return \stdClass
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getRepositoryBranchCommits($key, $branch)
    {
        if (!$key || !$branch) {
            throw new \Exception("data is missing repo with $key for branch: $branch");
        }
        $commits = $this->getClient()->getGuzzleClient()->get('https://api.github.com/repos/susom/' . $key . '/commits/' . $branch, [
            'headers' => [
                'Authorization' => 'token ' . $this->getClient()->getAccessToken(),
                'Accept' => 'application/vnd.github.v3+json'
            ]
        ]);
        return json_decode($commits->getBody());
    }

    /**
     * @param $key
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getRepositoryDefaultBranch($key)
    {
        if (!$key) {
            throw new \Exception("data is missing $key");
        }
        $response = $this->getClient()->getGuzzleClient()->get('https://api.github.com/repos/susom/' . $key, [
            'headers' => [
                'Authorization' => 'token ' . $this->getClient()->getAccessToken(),
                'Accept' => 'application/vnd.github.v3+json'
            ]
        ]);
        $repo = json_decode($response->getBody());
        return $repo->default_branch;
    }

    public function getRepositoryBranches($key)
    {
        if (!$key) {
            throw new \Exception("data is missing $key");
        }
        $commits = $this->getClient()->getGuzzleClient()->get('https://api.github.com/repos/susom/' . $key . '/branches', [
            'headers' => [
                'Authorization' => 'token ' . $this->getClient()->getAccessToken(),
                'Accept' => 'application/vnd.github.v3+json'
            ]
        ]);
        return json_decode($commits->getBody());
    }

    /**
     * @param string $url
     * @return string|string[]
     */
    public static function getGithubKey($url)
    {
        $parts = explode('/', $url);
        $key = end($parts);
        $key = str_replace('.git', '', $key);
        return $key;
    }

    /**
     * @return mixed
     */
    public function getRecord()
    {
        return $this->record;
    }

    /**
     * @param mixed $record
     */
    public function setRecord($record): void
    {
        $this->record = $record;
    }

    /**
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * @param Client $client
     */
    public function setClient(Client $client): void
    {
        $this->client = $client;
    }


}
