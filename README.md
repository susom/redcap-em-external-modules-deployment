# External Module Deplyment

This EM manages EMs deployment to the new Proposed GCP infra.

The EM Deployment is integrated with GitHub Application installed on SUSOM Organization on Github. Below workflow steps:

### How to add new EM to PID 16000?

You have two ways to create a new EM record in PID 16000.

1. Manually via REDCap forms.
2. Add your EM to the old REDCap builder. Then push a commit to your repo to trigger GitHub webhook.

#### *Note:*

You still need to pick the REDCap instances you want your EM deployed to. the EM will not pick instances.

### Which branch is deployed to GKE?

By default the EM will deploy the default Github branch to the selected instances.

### How Can I deploy other than default branch?

PID 16000 has an event for each REDCap instance. If you want to deploy non-default branch to a specific instance. Under
the instance event update `instance` form and change the value of "Override default Git Branch" with non-default branch
name.

**Current Workflow**
![Current Workflow](images/External%20Module%20Deployment%20Workflow.png)
