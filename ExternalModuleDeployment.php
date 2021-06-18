<?php

namespace Stanford\ExternalModuleDeployment;

// This file is generated by Composer
require_once __DIR__ . '/vendor/autoload.php';
require_once "emLoggerTrait.php";
require_once "classes/User.php";
require_once "classes/Repository.php";
require_once "classes/Client.php";

use ExternalModules\ExternalModules;
use Stanford\ExternalModuleDeployment\User;
use Stanford\ExternalModuleDeployment\Repository;
use Stanford\ExternalModuleDeployment\Client;
use REDCap;
use Project;
use \Firebase\JWT\JWT;


/**
 * Class ExternalModuleDeployment
 * @package Stanford\ExternalModuleDeployment
 * @property string $pta
 * @property User $user
 * @property Repository $repository
 * @property Client $client
 * @property array $repositories
 * @property Project $project
 * @property string $jwt
 * @property string $accessToken
 * @property \GuzzleHttp\Client $guzzleClient
 * @property array $redcapRepositories
 * @property \stdClass $redcapBuildRepoObject
 * @property string $defaultREDCapBuildRepoBranch
 * @property string $ShaForBranchCommitForREDCapBuild
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

    private $ShaForBranchCommitForREDCapBuild;

    private $gitRepositoriesDirectories;


    private $commitBranch;

    private $branchEventId;

    private $client;

    public function __construct()
    {
        parent::__construct();
        // Other code to run when object is instantiated

        if (isset($_GET['pid']) && $this->getProjectSetting('github-installation-id') && $this->getProjectSetting('github-app-private-key')) {
            $this->setProject(new Project(filter_var($_GET['pid'], FILTER_SANITIZE_STRING)));

            if (!defined('NOAUTH') || NOAUTH == false) {
                // get user right then set the user.
                $right = REDCap::getUserRights();
                $user = $right[USERID];
                if ($user != null) {
                    $this->setUser(new User($user));
                }

            }

            // initiate guzzle client to get access token
            $this->setClient(new Client($this->getProjectSetting('github-installation-id'), $this->getProjectSetting('github-app-private-key')));

            // set repositories
            $this->setRepositories();


            // set repository as adapter for all github requests.
            $this->setRepository(new Repository($this->getClient()));

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

    /**
     * @param int $project_id
     * @param string|null $record
     * @param string $instrument
     * @param int $event_id
     * @param int|null $group_id
     * @param string|null $survey_hash
     * @param int|null $response_id
     * @param int $repeat_instance
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function redcap_save_record(int $project_id, string $record = NULL, string $instrument, int $event_id, int $group_id = NULL, string $survey_hash = NULL, int $response_id = NULL, int $repeat_instance = 1)
    {
        $param = array(
            'project_id' => $project_id,
            'return_format' => 'array',
//                'events' => $event_id,
            'records' => [$record]
        );


        $data = REDCap::getData($param);
        if ($event_id == $this->getFirstEventId()) {

            $this->setRepository(new Repository($this->getClient(), $data));
            if ($data[$record][$event_id]['git_url'] != '') {
                $key = Repository::getGithubKey($data[$record][$event_id]['git_url']);
                list($commitBranch, $commit) = $this->getRepositoryDefaultBranchLatestCommit($key);

                $events = $this->findCommitDeploymentEventIds($data[$record], true);

                if (!empty($events)) {
                    foreach ($events as $branch => $event) {

                        if (!$this->canUpdateEvent($event, $commitBranch, $data[$record], $commit)) {

                            // lets get non-default branch commit and compare it to what is saved in redcap
                            $nonDefaultBranch = $data[$record][$event]['git_branch'];
                            $nonDefaultCommit = $this->getRepository()->getRepositoryBranchCommits($key, $nonDefaultBranch);

                            // if commit are different between
                            if ($nonDefaultCommit->sha != $data[$record][$event]['git_commit']) {
                                if ($this->updateInstanceCommitInformation($event, $record, $key, $nonDefaultCommit->sha, $nonDefaultCommit->commit->author->date, $this->shouldDeployInstance($data[$record], $branch), $nonDefaultBranch)) {
                                    $this->triggerTravisCIBuild($branch);
                                    $this->emLog("Travis build webhook triggered for branch $branch by EM $key with commit hash: " . $nonDefaultCommit->sha);
                                }
                            } else {
                                $this->emLog("Travis build webhook was ignored because no change in commit hash.");
                            }

                            continue;
                        }

                        if ($this->updateInstanceCommitInformation($event, $record, $key, $commit->sha, $commit->commit->author->date, $this->shouldDeployInstance($data[$record], $branch), $commitBranch)) {
                            if ($this->isCommitChanged($data[$event]['git_commit'], $commit->sha)) {
                                $this->triggerTravisCIBuild($branch);
                                $this->emLog("Travis build webhook triggered for branch $branch by EM $key with commit hash: " . $commit->sha);
                            } else {
                                $this->emLog("Travis build webhook was ignored because no change in commit hash.");
                            }

                        } else {
                            // currently we are only logging to avoid breaking the loop.
                            $this->emError("could not update EM $key in event " . $event);
                        }
                    }
                }
            }
        } else {
            // handle saving instance
            $commitBranch = $data[$record][$event_id]['git_branch'];
            $key = Repository::getGithubKey($data[$record][$this->getFirstEventId()]['git_url']);
            if ($commitBranch == '') {
                $commitBranch = $this->getRepository()->getRepositoryDefaultBranch($key);
            }

            $commit = $this->getRepository()->getRepositoryBranchCommits($key, $commitBranch);

            $branch = $this->searchBranchNameViaEventId($event_id);
            // if commit are different between
            if ($commit->sha != $data[$record][$event_id]['git_commit']) {
                if ($this->updateInstanceCommitInformation($event_id, $record, $key, $commit->sha, $commit->commit->author->date, $this->shouldDeployInstance($data[$record], $branch), $commitBranch)) {
                    $this->triggerTravisCIBuild($branch);
                    $this->emLog("Travis build webhook triggered for branch $branch by EM $key with commit hash: " . $commit->sha);
                }
            } else {
                $this->emLog("Travis build webhook was ignored because no change in commit hash.");
            }
        }

    }

    /**
     * @param $redcapCommit
     * @param $gitCommit
     * @return bool
     */
    public function isCommitChanged($redcapCommit, $gitCommit)
    {
        return $redcapCommit == $gitCommit;
    }

    /**
     * @param int $eventId
     * @param string $branch
     * @param array $data
     * @param \stdClass $commit
     * @return bool
     */
    private function canUpdateEvent($eventId, $branch, $data, $commit): bool
    {

        // check the branch is the same for event and default
        if ($data[$eventId]['git_branch'] != '' && $data[$eventId]['git_branch'] != $branch) {
            return false;
        }

        return true;
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
            // find the event id
            $eventId = $this->searchEventViaDescription($options[$name]);
            if ($deployment) {

                // for that event if branch was overridden. if not and default branch then deploy .
                if ($repository[$eventId]['git_branch'] == '') {
                    // if no override then check if this commit for default branch and if so add it to array to be updated.
                    // TODO do we want to save the default branch data in all enabled instances?
                    if ($defaultBranch) {
                        $result[$name] = $eventId;
                    }
                    // if not check the the branch in the event is same as the commit branch if so add it.
                } elseif ($repository[$eventId]['git_branch'] == $this->getCommitBranch() || $defaultBranch) {
                    $result[$name] = $eventId;
                }
            } else {

                // this use case to cover when developer removes EM we want to build to delete EM from instance
                if ($repository[$eventId]['deploy_instance']["1"] == "1") {
                    $result[$name] = $eventId;
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
    private function searchBranchNameViaEventId($eventId)
    {
        $arm = end($this->getProject()->events);
        $description = $arm['events'][$eventId]['descrip'];
        $options = parseEnum($this->getProject()->metadata['deploy']['element_enum']);
        if ($index = array_search($description, $options)) {
            return $index;
        }
        throw new \Exception("could not find branch for event $eventId");
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
                if ($this->isPayloadBranchADefaultBranch($payload)) {
                    $eventId = $this->getFirstEventId();
                    // first update the first event instance
                    //$this->updateInstanceCommitInformation($eventId, $recordId, $payload);
                    // next find other instances for deployment.
                    $events = $this->findCommitDeploymentEventIds($repository, true);
                } else {
                    $events = $this->findCommitDeploymentEventIds($repository);
                }


                // now update each instance
                $commit = end($payload['commits']);
                $commitBranch = $this->getCommitBranch($key, $payload['after']);
                foreach ($events as $branch => $event) {


                    if (!$this->canUpdateEvent($event, $commitBranch, $repository, $commit)) {
                        continue;
                    }


                    if ($this->updateInstanceCommitInformation($event, $recordId, $payload['repository']['name'], $payload['after'], $commit['timestamp'], $this->shouldDeployInstance($repository[$recordId], $branch))) {

                        $this->triggerTravisCIBuild($branch);
                        $this->emLog("Travis build webhook triggered for branch $branch by EM $key with commit hash: " . $payload['after']);
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


    private function shouldDeployInstance($data, $name)
    {
        $deployments = $data[$this->getFirstEventId()]['deploy'];
        return $deployments[$name];
    }

    /**
     * @param $eventId
     * @param $recordId
     * @param $name
     * @param $after
     * @param $timestamp
     * @param $deploy_instance
     * @param string $branch
     * @return bool
     * @throws \Exception
     */
    public function updateInstanceCommitInformation($eventId, $recordId, $name, $after, $timestamp, $deploy_instance, $branch = ''): bool
    {
        $data[REDCap::getRecordIdField()] = $recordId;
        if ($branch == '') {
            $data['git_branch'] = $this->getCommitBranch($name, $after);;
        } else {
            $data['git_branch'] = $branch;
        }

        $data['git_commit'] = $after;
        if ($deploy_instance) {
            $data['deploy_instance___1'] = 1;
        } else {
            $data['deploy_instance___1'] = 0;
        }

        $data['date_of_latest_commit'] = $timestamp;
        $data['redcap_event_name'] = $this->getProject()->getUniqueEventNames($eventId);
        $response = \REDCap::saveData($this->getProjectId(), 'json', json_encode(array($data)));
        if (empty($response['errors'])) {
            return true;
        } else {
            $this->emError($response);
            throw new \Exception("cant update last commit for EM : " . $name);
        }
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
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

        $rawPost = trim(file_get_contents('php://input'));
        $secret = $this->getProjectSetting('github-webhook-secret');

        if (!hash_equals($hash, hash_hmac($algo, $rawPost, $secret))) {
            throw new \Exception('Hook secret is invalid.');
        }
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

                $branch = $this->getRepository()->getRepositoryDefaultBranch($key);
                $this->setCommitBranch('', '', $branch);
            }

            // get latest commit for default branch
            $commit = $this->getRepository()->getRepositoryBranchCommits($key, $branch);
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

        // Save to em settings
        $this->setProjectSetting('ems-enabled', $versionsByPrefix, $project_id);
        $this->setProjectSetting('ems-orphaned', $orphans, $project_id);
        $this->setProjectSetting('em-scan-date', date("Y-m-d H:i:s"));

        return $payload;
    }

    public function testGithub($key, $command = '')
    {
        $response = $this->getClient()->get('https://api.github.com/repos/susom/' . $key . ($command ? '/' . $command : ''), [
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

//            foreach ($this->getGitRepositoriesDirectories() as $directory => $array) {
//                if ($array['key'] == $key) {

            $events = $this->findCommitDeploymentEventIds($repository, true);

            if (!in_array($this->getBranchEventId(), $events)) {
                continue;
            }

            if ($repository[$this->getBranchEventId()]['git_commit']) {
                $commit = $repository[$this->getBranchEventId()]['git_commit'];
            }
            // only write if branch and last commit different from what is saved in redcap.
            if ($repository[$this->getFirstEventId()]['deploy_version']) {
                $version = $repository[$this->getFirstEventId()]['deploy_version'];
            } else {
                $version = "9.9.9";
            }

            if ($repository[$this->getFirstEventId()]['deploy_name']) {
                $folder = $repository[$this->getFirstEventId()]['deploy_name'];
            } else {
                $folder = $recordId;
            }


            echo $repository[$this->getFirstEventId()]['git_url'] . ',' . $folder . "_v$version," . $repository[$this->getBranchEventId()]['git_branch'] . "," . ($commit != $repository[$this->getFirstEventId()]['git_commit'] ? $commit : '') . "\n";
//                }
//            }

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
            $repositories[] = new Repository($this->getClient(), $row);
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

//    /**
//     * @return string
//     */
//    public function getJwt(): string
//    {
//        if ($this->jwt) {
//            return $this->jwt;
//        } else {
//            $this->setJwt();
//            return $this->jwt;
//        }
//
//    }
//
//    /**
//     * @param string $jwt
//     */
//    public function setJwt(): void
//    {
//        $payload = array(
//            "iss" => "108296",
//            "iat" => time() - 60,
//            "exp" => time() + 360
//        );
//        $privateKey = $this->getProjectSetting('github-app-private-key');
//        $jwt = JWT::encode($payload, $privateKey, 'RS256');
//        $this->jwt = $jwt;
//    }


    //TODO all Github related stuff moved to Client class. we need to remove all below data.
//    /**
//     * @return string
//     */
//    public function getAccessToken(): string
//    {
//        if ($this->accessToken) {
//            return $this->accessToken;
//        } else {
//            $this->setAccessToken();
//            return $this->accessToken;
//        }
//
//    }
//
//    /**
//     * @param string $accessToken
//     */
//    public function setAccessToken(): void
//    {
//        $response = $this->getGuzzleClient()->post('https://api.github.com/app/installations/' . $this->getProjectSetting('github-installation-id') . '/access_tokens', [
//            'headers' => [
//                'Authorization' => 'Bearer ' . $this->getJwt(),
//                'Accept' => 'application/vnd.github.v3+json'
//            ]
//        ]);
//        $body = json_decode($response->getBody());
//        $this->accessToken = $body->token;
//    }

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
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function setRedcapBuildRepoObject(): void
    {
        $key = Repository::getGithubKey($this->getProjectSetting('redcap-build-github-repo'));

        $this->redcapBuildRepoObject = $this->getRepository()->getRepositoryBody($key);
    }


    /**
     * this function will call travis CI api
     */
    public function triggerTravisCIBuild($branch)
    {
        $response = $this->getClient()->getGuzzleClient()->post('https://api.travis-ci.com/repo/susom%2Fredcap-build/requests', [
            'headers' => [
                'Authorization' => 'token ' . $this->getProjectSetting('travis-ci-api-token'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Travis-API-Version' => '3',
            ],
            'body' => json_encode(array('request' => array(
                //'branch' => $this->getDefaultREDCapBuildRepoBranch(),
                'branch' => $branch,
                'sha' => $this->getShaForBranchCommitForREDCapBuild($branch)
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
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getShaForBranchCommitForREDCapBuild($branch = ''): string
    {
//        if ($this->ShaForBranchCommitForREDCapBuild) {
//            return $this->ShaForBranchCommitForREDCapBuild;
//
//        } else {
        $this->setShaForBranchCommitForREDCapBuild($branch);
        return $this->ShaForBranchCommitForREDCapBuild;
//        }
    }

    /**
     * @param string $ShaForBranchCommitForREDCapBuild
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function setShaForBranchCommitForREDCapBuild($branch = ''): void
    {
        $commit = $this->getLatestCommitForBranchForREDCapBuild($branch);
        $this->ShaForBranchCommitForREDCapBuild = $commit->sha;
    }


    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getLatestCommitForBranchForREDCapBuild($branch = '')
    {
        $key = Repository::getGithubKey($this->getProjectSetting('redcap-build-github-repo'));

        return $this->getRepository()->getLatestCommitForBranchForREDCapBuild($key, ($branch != '' ? $branch : $this->getDefaultREDCapBuildRepoBranch()));

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
            $branches = $this->getRepository()->getRepositoryBranches($key);

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

    /**
     * @return \Stanford\ExternalModuleDeployment\Client
     */
    public function getClient(): \Stanford\ExternalModuleDeployment\Client
    {
        return $this->client;
    }

    /**
     * @param \Stanford\ExternalModuleDeployment\Client $client
     */
    public function setClient(\Stanford\ExternalModuleDeployment\Client $client): void
    {
        $this->client = $client;
    }


}
