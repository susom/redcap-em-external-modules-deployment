<?php

namespace Stanford\ExternalModuleDeployment;
/** @var \Stanford\ExternalModuleDeployment\ExternalModuleDeployment $module */


try {
    //verify github secret.
    $module->verifyWebhookSecret();

    $module->emLog($_POST);
    // test commit
    if (isset($_POST) && !empty($_POST)) {
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
