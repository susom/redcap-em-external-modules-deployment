<?php

namespace Stanford\ExternalModuleDeployment;
/** @var \Stanford\ExternalModuleDeployment\ExternalModuleDeployment $module */


try {
//$repos = $module->getClient()->api('current_user')->repositories();
    if (isset($_POST['repo']) && isset($_POST['command'])) {
        $module->testGithub(filter_var($_POST['repo'], FILTER_SANITIZE_STRING), filter_var($_POST['command'], FILTER_SANITIZE_STRING));
    }

    //$module->testGithub('external-module-manager', 'collaborators');


    ?>
    <div class="container-fluid">
        <div class="row">
            <form method="post">
                <div class="form-group">
                    <label for="exampleInputEmail1">Github Repository</label>
                    <input type="text" class="form-control" id="repo" name="repo" aria-describedby="emailHelp"
                           placeholder="Enter Github Repository Key">
                </div>
                <div class="form-group">
                    <label for="exampleInputPassword1">Github Command</label>
                    <input type="text" class="form-control" id="command" name="command"
                           placeholder="Enter Github Repository Command">
                    <small id="emailHelp" class="form-text text-muted">You can add any command from repo API after repo
                        name. for more details check: <a href="https://docs.github.com/en/rest/reference/repos"
                                                         target="_blank">Github Repo API doc</a> .</small>
                </div>
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
        </div>
    </div>
    <?php
//echo '<br>';
//echo '<br>';
//echo '<br>';
//
//echo 'curl -i -H "Authorization: Bearer ' . $module->getJwt() . '" -H "Accept: application/vnd.github.v3+json" https://api.github.com/app';
//
//echo '<br>';
//echo '<br>';
//echo '<br>';
//echo 'curl -i -X POST -H "Authorization: Bearer ' . $module->getJwt() . '" -H "Accept: application/vnd.github.v3+json" https://api.github.com/app/installations/15898575/access_tokens';


    echo '<br>';
    echo '<br>';
    echo '<br>';
    // echo 'curl -i -H "Authorization: token ' . $module->getAccessToken() . '" -H "Accept: application/vnd.github.v3+json" https://api.github.com/repos/susom/external-module-manager';
//
//    $text = '[dev]$ cat modules-lab/external_module_manager_v9.9.9/.gitrepo
//; DO NOT EDIT (unless you know what you are doing)
//;
//; This subdirectory is a git "subrepo", and this file is maintained by the
//; git-subrepo command. See https://github.com/git-commands/git-subrepo#readme
//;
//[subrepo]
//	remote = git@github.com:susom/external-module-manager.git
//	branch = master
//	commit = 1383e18ed8a8e4b05e5a189daa4a7a7e05d23f73
//	parent = e25576715db238a2c33e0c7420eb200e6f6bff19
//	method = merge
//	cmdver = 0.4.0';
//    $parts = explode("\n\t", $text);
//    $matches = preg_grep('/^remote?/m', $parts);
//    $matches = preg_grep('/^branch?/m', $parts);
//    $branch = explode(" ", end($matches));
//    $branch = end($branch);
//    print_r($branch);
//    $key = Repository::getGithubKey($module->getProjectSetting('redcap-build-github-repo'));
//    $module->testGithub($key, 'commits/master');

    //$module->triggerTravisCIBuild();

    //$module->getGitRepositoriesDirectories();
    # test secret
//    echo $module->getUrl('pages/generate_config.php', true, true);
    // test pushing commit to dev event.
    echo $module->getUrl('pages/webhook.php', true, true);
    if (isset($_GET['update_commits'])) {
        $module->updateREDCapRepositoriesWithLastCommit();
    }
} catch (\Exception $e) {
    echo '<div class="alert alert-danger">' . $e->getMessage() . '</div>';
}
