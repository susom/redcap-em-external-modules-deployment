<?php

namespace Stanford\ExternalModuleDeployment;
/** @var \Stanford\ExternalModuleDeployment\ExternalModuleDeployment $module */


try {
    //verify github secret.
    $module->verifyWebhookSecret();


    $input = trim(file_get_contents('php://input'));
    $data = json_decode($input, true);

    // test dev commit.
    if (!empty($data)) {
        $module->emLog($data['repository']['name']);
//        $payload = json_decode($data, true);
        $result = $module->updateREDCapRepositoryWithLastCommit($data);

        if ($result) {
            echo json_encode(array('status' => 'success', 'message' => $data['repository']['name'] . " branch " . $module->getCommitBranch() . " was updated"));
        } else {
            echo json_encode(array('status' => 'error', 'message' => "Could not find repo record in PID 16000.", 'data' => $data));
        }
    } else {
        throw new \Exception("No post information found");
    }
} catch (\Exception $e) {
    $module->emError($e->getMessage());
    \REDCap::logEvent($e->getMessage());
    http_response_code(404);
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
