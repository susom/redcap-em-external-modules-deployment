<?php

namespace Stanford\ExternalModuleDeployment;
/** @var \Stanford\ExternalModuleDeployment\ExternalModuleDeployment $module */


try {
    //verify github secret.
    $module->verifyWebhookSecret();


    $input = trim(file_get_contents('php://input'));
    $data = json_decode($input, true);

    // test  commit.
    if (!empty($data)) {
        $module->emLog($data['repository']['name']);
        $module->emLog($data);
//        $payload = json_decode($data, true);
        $module->updateREDCapRepositoryWithLastCommit($data);

        echo json_encode(array('status' => 'success', 'message' => $data['repository']['name'] . " branch " . $module->getCommitBranch() . " was updated"));
    } else {
        throw new \Exception("No post information found");
    }
} catch (\Exception $e) {
    $module->emError($e->getMessage());
    \REDCap::logEvent($e->getMessage());
    http_response_code(404);
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
