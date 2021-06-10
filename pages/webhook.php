<?php

namespace Stanford\ExternalModuleDeployment;
/** @var \Stanford\ExternalModuleDeployment\ExternalModuleDeployment $module */


try {
    //verify github secret.
    $module->verifyWebhookSecret();


    $input = trim(file_get_contents('php://input'));
    $data = json_decode($input, true);
    $module->emLog($data);
    // test commit
    if (!empty($data)) {
        $payload = json_decode($_POST['payload'], true);
        $module->updateREDCapRepositoryWithLastCommit($payload);

        echo json_encode(array('status' => 'success', 'message' => $payload['repository']['name'] . " branch " . $module->getCommitBranch() . " was updated"));
    } else {
        throw new \Exception("No post information found");
    }
} catch (\Exception $e) {
    $module->emError($e->getMessage());
    http_response_code(404);
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
