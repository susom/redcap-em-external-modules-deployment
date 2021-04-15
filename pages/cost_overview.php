<?php
namespace Stanford\ExternalModuleDeployment;
/** @var ExternalModuleDeployment $module */


$result = $module->scanProject($_GET['pid']);

echo "<pre>" . print_r($result, true) . "</pre>";

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-sm">
            <h1>
                REDCap Costs and Fees Overview
            </h1>
        </div>
    </div>
    <div class="row">
        <div class="col-sm">
            <!--            <h2 class="d-inline-block"><i class="fas fa-door-open"></i></h2>-->
            <p class="d-inline">
                Check here to learn about the many features and support options built around the Stanford REDCap
                service.
                You can review current monthly fees (if any) and also initiate requests for additional support, feature
                changes, and more.
            </p>
        </div>
    </div>
    <div class="row">
        <div class="col-sm pt-4">
            <ul class="nav nav-tabs" id="tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="services-tab" data-toggle="tab" href="#services" role="tab"
                       aria-controls="services" aria-selected="true">Services</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="snapshot-tab" data-toggle="tab" href="#snapshot" role="tab"
                       aria-controls="snapshot" aria-selected="false">Current Snapshot</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="history-tab" data-toggle="tab" href="#history" role="tab"
                       aria-controls="history" aria-selected="false">History</a>
                </li>
            </ul>

            <div class="tab-content pt-4" id="myTabContent">
                <div class="tab-pane fade show active" id="services" role="tabpanel" aria-labelledby="services-tab">
                    <div class="row">
                        <div class="col-sm">
                            <div class="card">
                                <!--            <img src="..." class="card-img-top" alt="...">-->
                                <div class="card-body">
                                    <h5 class="card-title">Basic Services</h5>
                                    <p class="card-text">REDCap is available for research use to all Stanford students,
                                        staff, and
                                        faculty at no cost. The free offering includes:
                                    <ul>
                                        <li><b>Project Hosting:</b> REDCap will be securely hosted by the ResearchIT
                                            team.
                                        </li>
                                        <li><b>Basic support:</b> Have a quick question about a standard feature? Click
                                            on the blue
                                            support link and we will do our best to help.
                                        </li>
                                        <li><b>Database backups:</b> REDCap is backed up daily to ensure you do not lose
                                            your critical
                                            research data
                                        </li>
                                        <li><b>PHI-Approval and HIPAA compliance:</b> REDCap is approved by the Privacy
                                            office and IRB
                                            for the collection, storage, and analysis of sensitive data containing PHI.
                                            You must follow
                                            your IRB's recommendations and ensure your project is correctly configured -
                                            always ask us
                                            if you have questions around security.
                                        </li>
                                        <li><b>Office hours:</b> We offer weekly office hour blocks where basic
                                            questions can be
                                            answered. Office hours are not intended for in-depth project assistance -
                                            check our our
                                            Support Blocks for that.
                                        </li>
                                    </ul>
                                    <a href="#" class="btn btn-primary text-white">Learn more about our basic
                                        services</a>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm">
                            <div class="card">
                                <!--            <img src="..." class="card-img-top" alt="...">-->
                                <div class="card-body">
                                    <h5 class="card-title">Supplemental Services</h5>
                                    <p class="card-text">REDCap is a powerful and complex tool. We recommend budgeting
                                        for and working
                                        with a REDCap expert on your critical projects. We provide a number of support
                                        and customization
                                        options.
                                    <ul>
                                        <li><b>Support Blocks:</b> work <u>with</u> a dedicated REDCap expert, from
                                            design and planning,
                                            building and testing, to analysis and archival. A support block is highly
                                            reocmmended for
                                            any complex project where you need a helping hand or more. Leverage our
                                            expertise to save
                                            time, learn more about REDCap, and minimize disruptions.
                                        </li>
                                        <li><b>Professional Services:</b> Let us help you build out the functionality
                                            you need. Our
                                            team has extensive experience in building custom solutions on top of the
                                            REDCap platform to
                                            offer features not available out-of-the-box.
                                        </li>
                                        <li><b>Code Maintenance:</b> Any custom code or features must be supported to
                                            ensure it continues
                                            to run going forward. This applies to external modules, custom plugins,
                                            project hooks, or other
                                            related custom solutions.
                                        </li>
                                    </ul>
                                    <a href="#" class="btn btn-primary text-white">Learn more about our supplemental
                                        services</a>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="snapshot" role="tabpanel" aria-labelledby="snapshot-tab">
                    <div class="row">
                        <div class="col-sm">
                            <div id="em_overview_container">
                                <p>TODO: Insert table of active EMs with links to documentation</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="history" role="tabpanel" aria-labelledby="history-tab">

                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    console.log("Foo");
    $.getScript('<?php echo $module->getUrl("js/cost_overview.js"); ?>', function (data, textStatus, jqxhr) {
        // console.log( data ); // Data returned
        // console.log( textStatus ); // Success
        // console.log( jqxhr.status ); // 200
        console.log("Load was performed.");
    });
</script>
