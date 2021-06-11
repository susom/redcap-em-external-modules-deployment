<?php

namespace Stanford\ExternalModuleDeployment;

// This file is generated by Composer
require_once __DIR__ . '/vendor/autoload.php';
require_once "emLoggerTrait.php";
require_once "classes/User.php";
require_once "classes/Repository.php";

use ExternalModules\ExternalModules;
use Stanford\ExternalModuleDeployment\User;
use Stanford\ExternalModuleDeployment\Repository;
use REDCap;
use Project;
use \Firebase\JWT\JWT;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Client;

/**
 * Class ExternalModuleDeployment
 * @package Stanford\ExternalModuleDeployment
 * @property string $pta
 * @property User $user
 * @property Repository $repository
 * @property array $repositories
 * @property Project $project
 * @property string $jwt
 * @property string $accessToken
 * @property \GuzzleHttp\Client $guzzleClient
 * @property array $redcapRepositories
 * @property \stdClass $redcapBuildRepoObject
 * @property string $defaultREDCapBuildRepoBranch
 * @property string $ShaForLatestDefaultBranchCommitForREDCapBuild
 * @property array $gitRepositoriesDirectories
 * @property string $commitBranch
 * @property int $branchEventId
 */
class ExternalModuleDeployment extends \ExternalModules\AbstractExternalModule
{

    use emLoggerTrait;

    private $pta;    // PTA number on file to cover expenses (if any)

    private $user;

    private $repositories;

    private $repository;

    private $project;

    private $jwt;

    private $accessToken;

    private $guzzleClient;

    private $redcapRepositories;

    private $redcapBuildRepoObject;

    private $defaultREDCapBuildRepoBranch;

    private $ShaForLatestDefaultBranchCommitForREDCapBuild;

    private $gitRepositoriesDirectories;


    private $commitBranch;

    private $branchEventId;

    public function __construct()
    {
        parent::__construct();
        // Other code to run when object is instantiated

        if (isset($_GET['pid'])) {
            $this->setProject(new Project(filter_var($_GET['pid'], FILTER_SANITIZE_STRING)));

            if (!defined('NOAUTH') || NOAUTH == false) {
                // get user right then set the user.
                $right = REDCap::getUserRights();
                $user = $right[USERID];
                if ($user != null) {
                    $this->setUser(new User($user));
                }

            }

            // set repositories
            $this->setRepositories();
            // initiate guzzle client to get access token
            $this->setGuzzleClient(new Client());

            //authenticate github client
            // no longer needed we will do all calls manually package is not fully functional
            //$this->getClient()->authenticate($this->getAccessToken(), null, \Github\Client::AUTH_ACCESS_TOKEN);

            // set EM records saved in REDCap
            $this->setRedcapRepositories();

        }
    }

    public function isPayloadBranchADefaultBranch($payload): bool
    {
        // get default branch
        $defaultBranch = $payload['repository']['default_branch'];

        $commitBranch = $this->getCommitBranch($payload['repository']['name'], $payload['after']);

        // if commit branch is same as default then save to first event id
        if ($commitBranch == $defaultBranch) {
            return true;
        }
        return false;
    }

    public function determineCommitBranch($repository, $payload)
    {
        if ($this->isPayloadBranchADefaultBranch($payload)) {
            return $this->getFirstEventId();
        }

    }

    /**
     * @param $repository
     * @return array
     * @throws \Exception
     */
    public function findCommitDeploymentEventIds($repository, $defaultBranch = false): array
    {
        $result = array();
        $options = parseEnum($this->getProject()->metadata['deploy']['element_enum']);
        $deployments = $repository[$this->getFirstEventId()]['deploy'];
        foreach ($deployments as $name => $deployment) {
            // check if EM is deployed on specific instance.
            if ($deployment) {
                // find the event id
                $eventId = $this->searchEventViaDescription($options[$name]);
                // for that event if branch was overridden. if not and default branch then deploy .
                if ($repository[$eventId]['git_branch'] == '') {
                    // if no override then check if this commit for default branch and if so add it to array to be updated.
                    // TODO do we want to save the default branch data in all enabled instances?
                    if ($defaultBranch) {
                        $result[] = $eventId;
                    }
                    // if not check the the branch in the event is same as the commit branch if so add it.
                } elseif ($repository[$eventId]['git_branch'] == $this->getCommitBranch()) {
                    $result[] = $eventId;
                }
            }
        }
        return $result;
    }

    /**
     * @param $description
     * @return int|string
     * @throws \Exception
     */
    private function searchEventViaDescription($description)
    {
        $arm = end($this->getProject()->events);
        foreach ($arm['events'] as $id => $event) {
            if ($event['descrip'] == $description) {
                return $id;
            }
        }
        throw new \Exception("could not find event for $description");
    }

    /**
     * @throws \Exception
     */
    public function updateREDCapRepositoryWithLastCommit($payload)
    {

        // Test master remove
        foreach ($this->getRedcapRepositories() as $recordId => $repository) {
            $key = Repository::getGithubKey($repository[$this->getFirstEventId()]['git_url']);
             if ($key == $payload['repository']['name']) {
                 $this->emLog("====================================");
                 if ($this->isPayloadBranchADefaultBranch($payload)) {
                     $eventId = $this->getFirstEventId();
                     $this->emLog("Event Id:", $eventId);
                     // first update the first event instance
                     $this->updateInstanceCommitInformation($eventId, $recordId, $payload);
                     // next find other instances for deployment.
                     $events = $this->findCommitDeploymentEventIds($repository, true);
                     $this->emLog($events);
                 } else {
                    $events = $this->findCommitDeploymentEventIds($repository);
                }

                // now update each instance
                foreach ($events as $event) {
                    if ($this->updateInstanceCommitInformation($event, $recordId, $payload)) {
                        // TODO should we trigger Travis? because the branch now custom for each EM and will not match redcap-build instance branch.
                        // TODO if we decided to trigger Travis. solve the build commit currently its pull latest commit for DEFAULT branch.
                        // TODO $this->triggerTravisCIBuild($branch)
                        $this->emLog("webhook triggered for EM $key last commit hash: " . $payload['after']);
                    } else {
                        // currently we are only logging to avoid breaking the loop.
                        $this->emError("could not update EM $key in event " . $event);
                    }
                }
                // no need to go over other EM
                break;
            }
        }
    }


    /**
     * @param $eventId
     * @param $recordId
     * @param $payload
     * @return bool
     * @throws \Exception
     */
    public function updateInstanceCommitInformation($eventId, $recordId, $payload): bool
    {
        $data[REDCap::getRecordIdField()] = $recordId;
        $data['git_branch'] = $this->getCommitBranch($payload['repository']['name'], $payload['after']);;
        $data['git_commit'] = $payload['after'];
        $commit = end($payload['commits']);
        $data['date_of_latest_commit'] = $commit['timestamp'];
        $data['redcap_event_name'] = $this->getProject()->getUniqueEventNames($eventId);
        $response = \REDCap::saveData($this->getProjectId(), 'json', json_encode(array($data)));
        if (empty($response['errors'])) {
            return true;
        } else {
            $this->emError($response);
            throw new \Exception("cant update last commit for EM : " . $payload['repository']['name']);
        }
    }

    public function updateREDCapRepositoriesWithLastCommit()
    {
        foreach ($this->getRedcapRepositories() as $recordId => $repository) {
            if ($repository[$this->getFirstEventId()]['git_url']) {
                $key = Repository::getGithubKey($repository[$this->getFirstEventId()]['git_url']);
                $this->updateRepositoryDefaultBranchLatestCommit($key, $recordId);
            }

        }
    }

    /**
     * this function will check HMAC header verify the request is valid. test
     * @throws \Exception test
     */
    public function verifyWebhookSecret()
    {
        list($algo, $hash) = explode('=', $_SERVER['HTTP_X_HUB_SIGNATURE'], 2) + array('', '');
//        $this->emLog("************************************************************************************************************************************");
//        $this->emLog($algo);
        $rawPost = trim(file_get_contents('php://input'));
        $secret = $this->getProjectSetting('github-webhook-secret');
//        $this->emLog("secret    " . $secret);
//        $this->emLog("hash      " . $hash);
//        $this->emLog("hash_hmac " . hash_hmac($algo, $rawPost, $secret));
//        $this->emLog(hash_equals($hash, hash_hmac($algo, $rawPost, $secret)));
        // $this->emLog($rawPost);
        if (!hash_equals($hash, hash_hmac($algo, $rawPost, $secret))) {
            throw new \Exception('Hook secret does not match.');
        }
    }

    /**
     * @param $key
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getRepositoryDefaultBranch($key)
    {
        $response = $this->getGuzzleClient()->get('https://api.github.com/repos/susom/' . $key, [
            'headers' => [
                'Authorization' => 'token ' . $this->getAccessToken(),
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
        $commits = $this->getGuzzleClient()->get('https://api.github.com/repos/susom/' . $key . '/branches', [
            'headers' => [
                'Authorization' => 'token ' . $this->getAccessToken(),
                'Accept' => 'application/vnd.github.v3+json'
            ]
        ]);
        return json_decode($commits->getBody());
    }

    /**
     * @param $key
     * @param $branch
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getRepositoryBranchCommits($key, $branch)
    {
        if (!$key || !$branch) {
            throw new \Exception("data is missing repo with $key for branch: $branch");
        }
        $commits = $this->getGuzzleClient()->get('https://api.github.com/repos/susom/' . $key . '/commits/' . $branch, [
            'headers' => [
                'Authorization' => 'token ' . $this->getAccessToken(),
                'Accept' => 'application/vnd.github.v3+json'
            ]
        ]);
        return json_decode($commits->getBody());
    }

    /**
     * @param string $key
     * @param string $branch
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getRepositoryDefaultBranchLatestCommit($key, $branch = ''): array
    {
        try {

            // get default branch
            if ($branch == '') {
                $branch = $this->getRepositoryDefaultBranch($key);
            }


            // get latest commit for default branch
            $commit = $this->getRepositoryBranchCommits($key, $branch);
            //return first commit in the array which is the last one.
            return array($branch, $commit);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->emError("Exception pulling last commit for $key: " . $e->getMessage());
        }
    }

    /**
     * Only display the shortcut links based on proper user rights
     * @param $project_id
     * @param $link
     * @return false|null
     */
    public function redcap_module_link_check_display($project_id, $link)
    {
        if ($link['name'] == "Project Cost/Fees") {
            if (!$this->getUser()->hasDesignRights()) {
                return false;
            }
        }
        // $this->emDebug($link);
        return $link;
    }


    /**
     * Scan the specified project for external modules and update the scan results
     */
    public function scanProject($project_id)
    {
        // Get all external modules enabled for the project
        $versionsByPrefix = ExternalModules::getEnabledModules($project_id);
        $this->emDebug($versionsByPrefix);

        // Look for any EM settings that are not active (i.e. Orphaned settings)
        $q = $this->query("select
                rem.directory_prefix,
                rems.project_id,
                count(*) as count
            from redcap_external_module_settings rems
            join redcap_external_modules rem on rems.external_module_id = rem.external_module_id
            where rems.project_id = ?
            group by rems.project_id, rem.directory_prefix", [$project_id]);
        $orphans = [];
        while ($row = db_fetch_assoc($q)) {
            $prefix = $row['directory_prefix'];
            $count = $row['count'];
            if (!isset($versionsByPrefix[$prefix])) {
                $orphans[$prefix] = $count;
            }
        }
        $this->emDebug($orphans);

        // Save settings to logs for scan
        $payload = [
            'project_id' => $project_id,
            'ems-enabled' => json_encode($versionsByPrefix),
            'ems-orphaned' => json_encode($orphans)
        ];
        $this->emLog("ems-enabled", $payload);

        // Save to em settings
        $this->setProjectSetting('ems-enabled', $versionsByPrefix, $project_id);
        $this->setProjectSetting('ems-orphaned', $orphans, $project_id);
        $this->setProjectSetting('em-scan-date', date("Y-m-d H:i:s"));

        return $payload;
    }

    public function testGithub($key, $command = '')
    {
        $response = $this->getGuzzleClient()->get('https://api.github.com/repos/susom/' . $key . ($command ? '/' . $command : ''), [
            'headers' => [
                'Authorization' => 'token ' . $this->getAccessToken(),
                'Accept' => 'application/vnd.github.v3+json'
            ]
        ]);
        $body = json_decode($response->getBody());
        echo '<pre>';
        print_r($body);
        echo '</pre>';
    }


    public function getFolderPath($folder)
    {
        $arr = explode("/", __DIR__);
        $parts = array_slice($arr, -2, 2, true);
        if (is_dir(implode("/", $parts) . '/../' . $folder)) {
            return implode("/", $parts) . '/../' . $folder;
        } elseif (is_dir('../' . $folder)) {
            return '../' . $folder;
        } elseif (is_dir(__DIR__ . '/../' . $folder)) {
            return __DIR__ . '/../' . $folder;
        }
        return false;
    }

    /**
     * @param array $gitRepositoriesDirectories
     */
    public function setGitRepositoriesDirectories(): void
    {
        $folders = scandir(__DIR__ . '/../');
        $gitRepositoriesDirectories = array();
        foreach ($folders as $folder) {
            $path = $this->getFolderPath($folder);
//            $this->emLog($path);
//            $this->emLog(is_dir($path));
            if ($folder == '.' || $folder == '..' || !$path) {
                continue;
            } else {
                if (is_dir($path . '/.git')) {
                    $content = explode("\n\t", file_get_contents($path . '/.git/config'));
                    // url
                    $matches = preg_grep('/^url/m', $content);
                    $key = Repository::getGithubKey(end($matches));

                    // branch
                    $matches = preg_grep('/\[branch\s\"/m', $content);
                    $branch = end($matches);
                    $branch = explode("\n", $branch);
                    $branch = end($branch);
                    $regex = "(\[branch\s\")";
                    $branch = preg_replace($regex, "", str_replace('"]', "", $branch));
                    $gitRepositoriesDirectories[$folder] = array('key' => $key, 'branch' => $branch);
                } elseif (file_exists($path . '/.gitrepo')) {
                    $content = file_get_contents($path . '/.gitrepo');
                    $parts = explode("\n\t", $content);
                    $matches = preg_grep('/^remote?/m', $parts);
                    $key = Repository::getGithubKey(end($matches));
                    $matches = preg_grep('/^branch?/m', $parts);
                    $branch = explode(" ", end($matches));
                    $branch = end($branch);
                    $gitRepositoriesDirectories[$folder] = array('key' => $key, 'branch' => $branch);
                }
            }
        }
        $this->gitRepositoriesDirectories = $gitRepositoriesDirectories;
    }


    /**
     * @param string $key
     * @param string $recordId
     * @param string $branch
     * @return string
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function updateRepositoryDefaultBranchLatestCommit($key, $recordId, $branch = ''): string
    {
        list($branch, $commit) = $this->getRepositoryDefaultBranchLatestCommit($key, $branch);
        $data[REDCap::getRecordIdField()] = $recordId;
        $data['git_commit'] = $commit->sha;
        $data['git_branch'] = $branch;
        $data['date_of_latest_commit'] = $commit->commit->author->date;
        $data['redcap_event_name'] = $this->getProject()->getUniqueEventNames($this->getFirstEventId());
        $response = \REDCap::saveData($this->getProjectId(), 'json', json_encode(array($data)));
        if (empty($response['errors'])) {
            return $commit->sha;
        } else {
            throw new \Exception("cant update last commit for EM : " . $key);
        }
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function generateREDCapBuildConfigCSV()
    {
        echo "HTTP_URL,DEST,BRANCH,COMMIT\n";
        foreach ($this->getRedcapRepositories() as $recordId => $repository) {
            $key = Repository::getGithubKey($repository[$this->getFirstEventId()]['git_url']);

            foreach ($this->getGitRepositoriesDirectories() as $directory => $array) {
                if ($array['key'] == $key) {

                    $events = $this->findCommitDeploymentEventIds($repository, true);

                    if (!in_array($this->getBranchEventId(), $events)) {
                        continue;
                    }

                    if ($repository[$this->getBranchEventId()]['git_commit']) {
                        $commit = $repository[$this->getBranchEventId()]['git_commit'];
                    }
                    // only write if branch and last commit different from what is saved in redcap.
                    echo $repository[$this->getFirstEventId()]['git_url'] . ',' . $directory . "," . ($this->getCommitBranch('', '') != $repository[$this->getFirstEventId()]['git_branch'] ? $this->getCommitBranch('', '') : '') . "," . ($commit != $repository[$this->getFirstEventId()]['git_commit'] ? $commit : '') . "\n";
                    break;
                }
            }

        }
    }


    public function getExternalModuleUsage()
    {


        $q = $this->query("select
                rems.external_module_id,
                rem.directory_prefix,
                rems.project_id,
                rp.app_title,
                rp.status,
                rrc.record_count,
                sum(case when rems.`key` = 'enabled' and rems.value = 'true' then 1 else 0 end) as is_enabled,
                count(*) as settings
            from redcap_external_module_settings rems
            join redcap_external_modules rem on rems.external_module_id = rem.external_module_id
            join redcap_projects rp on rems.project_id = rp.project_id
            join redcap_record_counts rrc on rp.project_id = rrc.project_id
            where rems.project_id is not null
            group by rems.external_module_id, rems.project_id, rem.directory_prefix, rp.app_title, rp.status, rrc.record_count", []
        );

        $resultsByModule = [];
        $resultsByProject = [];
        while ($row = db_fetch_assoc($q)) {
            $results[] = $row;
        }

    }

    /**
     * @return string
     */
    public function getPta(): string
    {
        return $this->pta;
    }

    /**
     * @param string $pta
     */
    public function setPta(string $pta): void
    {
        $this->pta = $pta;
    }

    /**
     * @return \Stanford\ExternalModuleDeployment\User
     */
    public function getUser(): \Stanford\ExternalModuleDeployment\User
    {
        return $this->user;
    }

    /**
     * @param \Stanford\ExternalModuleDeployment\User $user
     */
    public function setUser(\Stanford\ExternalModuleDeployment\User $user): void
    {
        $this->user = $user;
    }

    /**
     * @return \Stanford\ExternalModuleDeployment\Repository
     */
    public function getRepository(): \Stanford\ExternalModuleDeployment\Repository
    {
        return $this->repository;
    }

    /**
     * @param \Stanford\ExternalModuleDeployment\Repository $repository
     */
    public function setRepository(\Stanford\ExternalModuleDeployment\Repository $repository): void
    {
        $this->repository = $repository;
    }

    /**
     * @return array
     */
    public function getRepositories(): array
    {
        if ($this->repositories) {
            return $this->repositories;
        } else {
            $this->setRepositories();
            return $this->repositories;
        }

    }

    /**
     * @param array $repositories
     */
    public function setRepositories(): void
    {
        $q = $this->query("select
                rems.external_module_id,
                rem.directory_prefix,
                rems.project_id,
                rp.app_title,
                rp.status,
                rrc.record_count,
                sum(case when rems.`key` = 'enabled' and rems.value = 'true' then 1 else 0 end) as is_enabled,
                count(*) as settings
            from redcap_external_module_settings rems
            join redcap_external_modules rem on rems.external_module_id = rem.external_module_id
            join redcap_projects rp on rems.project_id = rp.project_id
            join redcap_record_counts rrc on rp.project_id = rrc.project_id
            where rems.project_id is not null
            group by rems.external_module_id, rems.project_id, rem.directory_prefix, rp.app_title, rp.status, rrc.record_count", []
        );

        $resultsByModule = [];
        $resultsByProject = [];
        $repositories = [];
        while ($row = db_fetch_assoc($q)) {
            $repositories[] = new Repository($row);
        }
        $this->repositories = $repositories;
    }

    /**
     * @return \Project
     */
    public function getProject(): \Project
    {
        return $this->project;
    }

    /**
     * @param \Project $project
     */
    public function setProject(\Project $project): void
    {
        $this->project = $project;
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
        $privateKey = $this->getProjectSetting('github-app-private-key');
        $jwt = JWT::encode($payload, $privateKey, 'RS256');
        $this->jwt = $jwt;
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
        $response = $this->getGuzzleClient()->post('https://api.github.com/app/installations/' . $this->getProjectSetting('github-installation-id') . '/access_tokens', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getJwt(),
                'Accept' => 'application/vnd.github.v3+json'
            ]
        ]);
        $body = json_decode($response->getBody());
        $this->accessToken = $body->token;
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
     * @return array
     */
    public function getRedcapRepositories(): array
    {
        if ($this->redcapRepositories) {
            return $this->redcapRepositories;
        } else {
            $this->setRedcapRepositories();
            return $this->redcapRepositories;
        }

    }

    /**
     * @param array $redcapRepositories
     */
    public function setRedcapRepositories(): void
    {
        $param = array(
            'project_id' => $this->getProjectId(),
            'return_format' => 'array',
//            'events' => $this->getBranchEventId()
        );
        $data = REDCap::getData($param);

        $this->redcapRepositories = $data;
    }

    /**
     * @return \stdClass
     */
    public function getRedcapBuildRepoObject(): \stdClass
    {
        if ($this->redcapBuildRepoObject) {
            return $this->redcapBuildRepoObject;
        } else {
            $this->setRedcapBuildRepoObject();
            return $this->redcapBuildRepoObject;
        }
    }

    /**
     * @param \stdClass $redcapBuildRepoObject
     */
    public function setRedcapBuildRepoObject(): void
    {
        $key = Repository::getGithubKey($this->getProjectSetting('redcap-build-github-repo'));

        $response = $this->getGuzzleClient()->get('https://api.github.com/repos/susom/' . $key, [
            'headers' => [
                'Authorization' => 'token ' . $this->getAccessToken(),
                'Accept' => 'application/vnd.github.v3+json'
            ]
        ]);
        $body = json_decode($response->getBody());
        $this->redcapBuildRepoObject = $body;
    }


    /**
     * this function will call travis CI api
     */
    public function triggerTravisCIBuild($branch)
    {
        $response = $this->getGuzzleClient()->post('https://api.travis-ci.com/repo/susom%2Fredcap-build/requests', [
            'headers' => [
                'Authorization' => 'token ' . $this->getProjectSetting('travis-ci-api-token'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Travis-API-Version' => '3',
            ],
            'body' => json_encode(array('request' => array(
                //'branch' => $this->getDefaultREDCapBuildRepoBranch(),
                'branch' => $branch,
                'sha' => $this->getShaForLatestDefaultBranchCommitForREDCapBuild()
            )))
        ]);
        $body = json_decode($response->getBody());
        echo '<pre>';
        print_r($body);
        echo '</pre>';

    }

    /**
     * @return string
     */
    public function getDefaultREDCapBuildRepoBranch(): string
    {
        if ($this->defaultREDCapBuildRepoBranch) {
            return $this->defaultREDCapBuildRepoBranch;
        } else {
            $this->setDefaultREDCapBuildRepoBranch();
            return $this->defaultREDCapBuildRepoBranch;
        }
    }

    /**
     * @param string $defaultREDCapBuildRepoBranch
     */
    public function setDefaultREDCapBuildRepoBranch(): void
    {
        $repo = $this->getRedcapBuildRepoObject();
        $this->defaultREDCapBuildRepoBranch = $repo->default_branch;
    }

    /**
     * @return string
     */
    public function getShaForLatestDefaultBranchCommitForREDCapBuild(): string
    {
        if ($this->ShaForLatestDefaultBranchCommitForREDCapBuild) {
            return $this->ShaForLatestDefaultBranchCommitForREDCapBuild;

        } else {
            $this->setShaForLatestDefaultBranchCommitForREDCapBuild();
            return $this->ShaForLatestDefaultBranchCommitForREDCapBuild;
        }
    }

    /**
     * @param string $ShaForLatestDefaultBranchCommitForREDCapBuild
     */
    public function setShaForLatestDefaultBranchCommitForREDCapBuild(): void
    {
        $commit = $this->getLatestCommitForDefaultBranchForREDCapBuild();
        $this->ShaForLatestDefaultBranchCommitForREDCapBuild = $commit->sha;
    }


    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getLatestCommitForDefaultBranchForREDCapBuild()
    {
        $key = Repository::getGithubKey($this->getProjectSetting('redcap-build-github-repo'));

        $response = $this->getGuzzleClient()->get('https://api.github.com/repos/susom/' . $key . '/commits/' . $this->getDefaultREDCapBuildRepoBranch(), [
            'headers' => [
                'Authorization' => 'token ' . $this->getAccessToken(),
                'Accept' => 'application/vnd.github.v3+json'
            ]
        ]);
        return json_decode($response->getBody());
    }

    /**
     * @return array
     */
    public function getGitRepositoriesDirectories(): array
    {
        if ($this->gitRepositoriesDirectories) {
            return $this->gitRepositoriesDirectories;
        } else {
            $this->setGitRepositoriesDirectories();
            return $this->gitRepositoriesDirectories;
        }

    }

    /**
     * @param string $key
     * @param string $sha
     * @return string
     * @throws \Exception
     */
    public function getCommitBranch(string $key = '', string $sha = ''): string
    {
        if (!$this->commitBranch) {
            $this->setCommitBranch($key, $sha);
        }
        return $this->commitBranch;
    }

    /**
     * there are two ways to set the commit branch first via EM and commit sha. then call github to get the branches
     * and compare latest sha for each branch. secondly you can set the branch directly this useful when generating
     * config.csv and
     * @param string $key
     * @param string $sha
     * @throws \Exception
     */
    public function setCommitBranch(string $key = '', string $sha = '', string $branch = ''): void
    {
        if ($branch == '') {
            if (!$key || !$sha) {
                throw new \Exception("data is missing branch with $key for sha: $sha");
            }
            $branches = $this->getRepositoryBranches($key);

            foreach ($branches as $branch) {
                if ($branch->commit->sha == $sha) {
                    $this->commitBranch = $branch->name;
                }
            }

            if (!$this->commitBranch) {
                throw new \Exception("could not find branch with $key for sha: $sha");

            }
        } else {
            $this->commitBranch = $branch;
        }
    }

    /**
     * @return int
     */
    public function getBranchEventId(): int
    {
        if (!$this->branchEventId) {
            $this->setBranchEventId();
        }
        return $this->branchEventId;
    }

    /**
     * @param int $branchEventId
     */
    public function setBranchEventId(string $branch = ''): void
    {
        if ($branch != '') {
            // check if the branch one of the instances events
//            foreach ($this->getBranchesEventsMap() as $id => $item) {
//                // if not our branch is it misc branch to use it in case no event found
//                if ($branch == $item['branch-name']) {
//                    $this->branchEventId = $item['branch-event'];
//                }
//            }

            $options = parseEnum($this->getProject()->metadata['deploy']['element_enum']);
            foreach ($options as $name => $deployment) {
                // check if EM is deployed on specific instance.
                if ($deployment) {
                    // find the event id
                    if ($name == $branch) {
                        $this->branchEventId = $this->searchEventViaDescription($deployment);
                        break;
                    }
                }
            }

        }

        // if no event is found this use the first event which represent default branch
        if (!$this->branchEventId) {
            $this->branchEventId = $this->getFirstEventId();
        }
    }


}
