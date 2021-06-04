<?php

namespace Stanford\ExternalModuleDeployment;
/** @var \Stanford\ExternalModuleDeployment\ExternalModuleDeployment $module */


try {
    //test
    $module->verifyWebhookSecret();

    // test commit to development branch
    if (isset($_POST) && !empty($_POST)) {
        $payload = json_decode($_POST['payload'], true);
        $module->updateREDCapRepositoryWithLastCommit($payload);
    } else {
        throw new \Exception("something went wrong!");
    }
} catch (\Exception $e) {
    $module->emError($e->getMessage());
    http_response_code(404);
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
