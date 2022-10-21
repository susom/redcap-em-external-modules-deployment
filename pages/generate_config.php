<?php

namespace Stanford\ExternalModuleDeployment;
use GuzzleHttp\Exception\GuzzleException;

/** @var \Stanford\ExternalModuleDeployment\ExternalModuleDeployment $module */


try {
    // test
    if (!isset($_POST['key'])) {
        throw new \Exception("key does not exist");
    }
    if (md5($_POST['key']) != md5($module->getProjectSetting('travis-config-secret'))) {
        throw new \Exception("key does not match");
    }
    // test commit
    if (isset($_POST['branch']) && $_POST['branch'] != '') {
        $module->setCommitBranch('', '', htmlentities($_POST['branch'], ENT_NOQUOTES));

    } else {
        // if no branch provided then use default branch from redcap-build
        $module->setCommitBranch('', '', $module->getDefaultREDCapBuildRepoBranch());
    }


    // next is to set the event id will be used to generate the file.
    $module->setBranchEventId($module->getCommitBranch());
    #
    $module->generateREDCapBuildConfigCSV();
} catch (\Exception $e) {
    $module->emError($e->getMessage());
    http_response_code(404);
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
} catch (GuzzleException $e) {
    $module->emError($e->getMessage());
    http_response_code(404);
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
