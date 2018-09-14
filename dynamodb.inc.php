<?php

include_once(dirname(__FILE__) . '/../configwizardhelper.inc.php');

dynamodb_configwizard_init();

function dynamodb_configwizard_init()
{
    $name = "dynamodb";
    $args = array(
        CONFIGWIZARD_NAME => $name,
        CONFIGWIZARD_VERSION => "1.0.0",
        CONFIGWIZARD_TYPE => CONFIGWIZARD_TYPE_MONITORING,
        CONFIGWIZARD_DESCRIPTION => _("Monitor an Amazon DynamoDB."),
        CONFIGWIZARD_DISPLAYTITLE => "Amazon DynamoDB",
        CONFIGWIZARD_FUNCTION => "dynamodb_configwizard_func",
        CONFIGWIZARD_PREVIEWIMAGE => "dynamodb.png",
        CONFIGWIZARD_FILTER_GROUPS => array('amazon'),
        CONFIGWIZARD_REQUIRES_VERSION => 500
    );
    register_configwizard($name, $args);
}

/**
 * @return int
 *          0 = good to go
 *          1 = prereqs non-existent
 *          2 = may need to upgrade boto3
 */

function dynamodb_configwizard_check_prereqs()
{
    // Plugin doesn't exist
    if (!file_exists("/usr/local/nagios/libexec/check_dynamodb.py") && !is_dev_mode()) {
        return 4; // plugin non-existent
    }

    $preferred_version = '1.0.0';

    $found_pip = false;
    $pip_output = array();

    // See if boto3 is installed via pip and get the version number
    $pip_command = 'python -c "import boto3"';
    exec($pip_command, $pip_output, $pip_return);

    // If neither yum nor pip returned anything, there is no need to continue
    if ($pip_return !== 0) {
        return 1; // prereqs non-existent
    }
}

/**
 * @param string $mode
 * @param null   $inargs
 * @param        $outargs
 * @param        $result
 *
 * @return string
 */

function dynamodb_configwizard_func($mode = "", $inargs = null, &$outargs, &$result)
{

    $wizard_name = "dynamodb";
    $local_url = get_base_url();

    // Initialize return code and output
    $result = 0;
    $output = "";

    // Initialize output args - pass back the same data we got
    $outargs[CONFIGWIZARD_PASSBACK_DATA] = $inargs;

    switch ($mode) {

        case CONFIGWIZARD_MODE_GETSTAGE1HTML:
            
            $check_prereqs = dynamodb_configwizard_check_prereqs();

            if ($check_prereqs == 1) {
                $output .= '<div class="message errorMessage" style="padding: 10px; margin-bottom: 20px;"><p><b>' . _('Error') . ':</b> ' . _('It looks like you are missing boto3 on your Nagios XI server.') . '</p><p>' . _('To use this wizard you must install boto3 on your server. If you are using CentOS or RHEL you can run:') . '</p><pre>pip install boto3</pre></div>';
            }             

            $accesskeyid = grab_array_var($inargs, "accesskeyid", "");
            $accesskey = grab_array_var($inargs, "accesskey", "");            
            $region = grab_array_var($inargs, "region", "");
            $dynamodb_name = grab_array_var($inargs, "dynamodb_name", "");

            if (isset($_SESSION['checkboxlist'])) {
                $checkboxlist = $_SESSION['checkboxlist'];
            } else {
                $checkboxlist = grab_array_var($inargs, "checkboxlist", "");
            }

            if ($credsfilepath == "") {
                $credsfilepath = "/usr/local/nagiosxi/etc/.aws/credentials";
            }

            if ($configfilepath == "") {
                $configfilepath = "/usr/local/nagiosxi/etc/.aws/config";
            }

            $linuxdistro = grab_array_var($inargs, "linuxdistro", "");

            $output = '
            <h5 class="ul">'._('AWS Account Information').'</h5>
            <table class="table table-condensed table-no-border table-auto-width table-padded">
                <tr>
                    <td class="vt" style="width: 135px;">
                        <label>' . _('Access Key ID') . ':</label>
                    </td>
                    <td>
                        <input type="text" size="40" name="accesskeyid" id="accesskeyid" value="' . encode_form_val($accesskeyid) . '" class="textfield form-control credinput" ' . (checkbox_binary($staticcreds) ? "disabled" : "") . '>
                        <div class="subtext">' . _('The Access Key ID of the DynamoDB to be monitored') . '.</div>
                    </td>
                </tr>
                <tr>
                    <td class="vt" style="width: 135px;">
                        <label>' . _('Secret Access Key') . ':</label>
                    </td>
                    <td>
                        <input type="text" size="40" name="accesskey" id="accesskey" value="' . encode_form_val($accesskey) . '" class="textfield form-control credinput" ' . (checkbox_binary($staticcreds) ? "disabled" : "") . '>
                        <div class="subtext">' . _('The Secret Access Key of the DynamoDB to be monitored') . '.</div>
                    </td>
                </tr>
                <tr>
                    <td class="vt">
                        <label>' . _('AWS Region') . ':</label>
                    </td>
                    <td>
                        <select value="' . encode_form_val($region) . '" name="region" id="region" class="form-control">
                            <option value="us-east-1">US East (N. Virginia) | us-east-1</option>
                            <option value="us-east-2">US East (Ohio) | us-east-2</option>                            
                            <option value="us-west-1">US West (N. California) | us-west-1</option>
                            <option value="us-west-2">US West (Oregon) | us-west-2</option>
                            <option value="ap-northeast-1">Asia Pacific (Tokyo) | ap-northeast-1</option>
                            <option value="ap-northeast-2">Asia Pacific (Seoul) | ap-northeast-2</option>
                            <option value="ap-northeast-3">Asia Pacific (Osaka-Local) | ap-northeast-3</option>
                            <option value="ap-south-1">Asia Pacific (Mumbai) | ap-south-1</option>
                            <option value="ap-southeast-1">Asia Pacific (Singapore) | ap-southeast-1</option>
                            <option value="ap-southeast-2">Asia Pacific (Sydney)</option>
                            <option value="ca-central-1">Canada (Central)</option>
                            <option value="cn-north-1">China (Beijing)</option>
                            <option value="cn-northwest-1">China (Ningxia)</option>
                            <option value="eu-central-1">EU (Frankfurt)</option>
                            <option value="eu-west-1">EU (Ireland)</option>
                            <option value="eu-west-2">EU (London)</option>
                            <option value="eu-west-3">EU (Paris)</option>
                            <option value="sa-east-1">South America (São Paulo)</option>
                            <option value="us-gov-west-1">AWS GovCloud (US)</option>                            
                        </select>                        
                    </td>

                </tr>
            </table>            
            ';          

            $output .='

            <script type="text/javascript">

            $(function(){
                $(".configtooltip").popover({ html: true });
            });

            document.addEventListener("DOMContentLoaded", function() {
                var configtable = document.getElementById("configfiletable");
                var configinputs = document.getElementsByClassName("configinputs");

                document.querySelector("#staticconfig").addEventListener("change", function() {

                    for (var i = 0; i < configinputs.length; i++) {
                        if (configinputs[i].disabled === true) {
                            configinputs[i].disabled = false;
                        } else {
                            configinputs[i].disabled = true;
                        }
                    }

                    if (document.getElementById("configfilepath").disabled === true) {
                        document.getElementById("configfilepath").disabled = false;
                    } else {
                        document.getElementById("configfilepath").disabled = true;
                    }

                });
            });

            </script>';
            }
            break;

        case CONFIGWIZARD_MODE_VALIDATESTAGE1DATA:

            $accesskeyid = grab_array_var($inargs, "accesskeyid", "");
            $accesskey = grab_array_var($inargs, "accesskey", "");
            $dynamodb_name = grab_array_var($inargs, "dynamodb_name", "");
            $region = grab_array_var($inargs, "region", "");
 
// echo '<pre>',print_r($region,1),'</pre>'; //testing
// echo '<pre>',print_r($accesskeyid,1),'</pre>'; //testing
// echo '<pre>',print_r($accesskey,1),'</pre>'; //testing
// echo '<pre>',print_r($services,1),'</pre>'; //testing
// echo '<pre>',print_r($dynamodb_name,1),'</pre>'; //testing

            $outargs[CONFIGWIZARD_PASSBACK_DATA]['checkboxlist'] = $checkboxlist;

            if ($_SESSION['checkboxlist'] != NULL) {
                $checkboxlist = $_SESSION['checkboxlist'];
            } else {
                $checkboxlist = grab_array_var($inargs, "checkboxlist", "");
            }

            $_SESSION['checkboxlist'] = $checkboxlist;

            $staticcreds = grab_array_var($inargs, "staticcreds", "off");
            $staticconfig = grab_array_var($inargs, "staticconfig", "off");


            $cmd = "/usr/local/nagios/libexec/check_dynamodb.py --changemode 'getmetrics' --accesskeyid '" . $accesskeyid . "' --secretaccesskey '" . $accesskey . "' --bucketname '" . $value . "' --region '" . $regionlist[$key] . "'";
            $cmd = escapeshellcmd($cmd);

            $returnedmetrics = shell_exec($cmd);
            $decodedmetrics = json_decode($returnedmetrics, true);

            $mergedarray = array();

            foreach($decodedmetrics as $i => $v) {
                $mergedarray[$i] = $v;                        
                $mergedarray['Region'] = $regionlist[$key];
            }

            $metriclist[$value] = $mergedarray;

            $outargs[CONFIGWIZARD_PASSBACK_DATA]["metriclist"] = $metriclist;

            // Check for errors
            $errors = 0;
            $errmsg = array();

            if (empty($accesskeyid)) {
                $errmsg[$errors++] = "Please specify an Access Key ID.";
            }

            if (empty($accesskey)) {
                $errmsg[$errors++] = "Please specify a Secret Access Key.";
            }

            if (empty($region)) {
                $errmsg[$errors++] = "Please specify a region.";
            }          

            if ($errors > 0) {
                $outargs[CONFIGWIZARD_ERROR_MESSAGES] = $errmsg;
                $result = 1;
            }
            break;

        case CONFIGWIZARD_MODE_GETSTAGE2HTML:

            // Get variables that were passed to us
            $accesskeyid = grab_array_var($inargs, "accesskeyid", "");
            $accesskey = grab_array_var($inargs, "accesskey", "");
            $dynamodb_name = grab_array_var($inargs, "dynamodb_name", "");
            $region = grab_array_var($inargs, "region", "");

            $outargs[CONFIGWIZARD_PASSBACK_DATA]['checkboxlist'] = $checkboxlist;

            if ($_SESSION['metriclist'] == NULL) {
                $metriclist = grab_array_var($inargs, "metriclist", "");
                $_SESSION['metriclist'] = $metriclist;
            } else {
                $metriclist = $_SESSION['metriclist'];
            }

            $ha = @gethostbyaddr($address);
            if (empty($ha)) {
                $ha = $address;
            }

            $services = "";
            $services_serial = grab_array_var($inargs, "services_serial", "");
            if ($services_serial != "") {
                $services = unserialize(base64_decode($services_serial));
            }
            if (!is_array($services)) {
                $services_default = array(
                    "ConditionalCheckFailedRequests" => 0,
                    "ConsumedReadCapacityUnits" => 0,
                    "ConsumedWriteCapacityUnits" => 0, 
                    "OnlineIndexConsumedWriteCapacity" => 0,
                    "OnlineIndexPercentageProgress" => 0, 
                    "OnlineIndexThrottleEvents" => 0, 
                    "PendingReplicationCount" => 0, 
                    "ProvisionedReadCapacityUnits" => 0, 
                    "ProvisionedWriteCapacityUnits" => 0,
                    "ReadThrottleEvents" => 0, 
                    "ReplicationLatency" => 0,
                    "ReturnedBytes" => 0,
                    "ReturnedItemCount" => 0, 
                    "ReturnedRecordsCount" => 0,
                    "SuccessfulRequestLatency" => 0,
                    "SystemErrors" => 0,
                    "TimeToLiveDeletedItemCount" => 0,
                    "ThrottledRequests" => 0,
                    "UserErrors" => 0, 
                    "WriteThrottleEvents" => 0,
                );

                $services_default["servicestate"][0] = "on";
                $services_default["servicestate"][1] = "on";
                $services = grab_array_var($inargs, "services", $services_default);
            }

            $serviceargs = "";
            $serviceargs_serial = grab_array_var($inargs, "serviceargs_serial", "");
            
            $serviceargs_default = array(

                "ConditionalCheckFailedRequests_warning" => 1,
                "ConditionalCheckFailedRequests_critical" => 2,

                "ConsumedReadCapacityUnits_warning" => 1,
                "ConsumedReadCapacityUnits_critical" => 2,
                
                "ConsumedWriteCapacityUnits_warning" => 1,
                "ConsumedWriteCapacityUnits_critical" => 2,
                
                "OnlineIndexConsumedWriteCapacity_warning" => 1,
                "OnlineIndexConsumedWriteCapacity_critical" => 2,
                
                "OnlineIndexPercentageProgress_warning" => 1,
                "OnlineIndexPercentageProgress_critical" => 2,

                "OnlineIndexThrottleEvents_warning" => 1,
                "OnlineIndexThrottleEvents_critical" => 2,

                "PendingReplicationCount_warning" => 1,
                "PendingReplicationCount_critical" => 2,

                "ProvisionedReadCapacityUnits_warning" => 1,
                "ProvisionedReadCapacityUnits_critical" => 2,

                "ProvisionedWriteCapacityUnits_warning" => 1,
                "ProvisionedWriteCapacityUnits_critical" => 2,

                "ReadThrottleEvents_warning" => 1,
                "ReadThrottleEvents_critical" => 2,

                "ReplicationLatency_warning" => 1,
                "ReplicationLatency_critical" => 2,

                "ReturnedBytes_warning" => 1,
                "ReturnedBytes_critical" => 2,

                "ReturnedItemCount_warning" => 1,
                "ReturnedItemCount_critical" => 2,

                "ReturnedRecordsCount_warning" => 1,
                "ReturnedRecordsCount_critical" => 2,

                "SuccessfulRequestLatency_warning" => 1,
                "SuccessfulRequestLatency_critical" => 2,

                "SystemErrors_warning" => 1,
                "SystemErrors_critical" => 2,

                "TimeToLiveDeletedItemCount_warning" => 1,
                "TimeToLiveDeletedItemCount_critical" => 2,

                "ThrottledRequests_warning" => 1,
                "ThrottledRequests_critical" => 2,

                "UserErrors_warning" => 1,
                "UserErrors_critical" => 2,

                "WriteThrottleEvents_warning" => 1,
                "WriteThrottleEvents_critical" => 2,                
            );

            $serviceargs = grab_array_var($inargs, "serviceargs", $serviceargs_default);

            $output = '
            <input type="hidden" name="address" value="' . encode_form_val($address) . '">
            <input type="hidden" name="hostnames" value="' . encode_form_val($hostnames) . '">
            <input type="hidden" name="accesskeyid" value="' . encode_form_val($accesskeyid) . '">
            <input type="hidden" name="accesskey" value="' . encode_form_val($accesskey) . '">
            <input type="hidden" name="region" value="' . encode_form_val($region) . '">
            <input type="hidden" name="staticcreds" value="' . encode_form_val($staticcreds) . '">
            <input type="hidden" name="staticconfig" value="' . encode_form_val($staticconfig) . '">
            <input type="hidden" name="credsfilepath" value="' . encode_form_val($credsfilepath) . '">

            <input type="hidden" name="dynamodb_name" value="' . encode_form_val($dynamodb_name) . '">


            <input type="hidden" name="configfilepath" value="' . encode_form_val($configfilepath) . '">';

            
            $output .= '
            </table>

            <h5 class="ul">' . _('DynamoDB Details') . '</h5>
            <table class="table table-condensed table-no-border table-auto-width">        
                <tr class="specifiedcredentials vt">
                    <td style="width: 170px;">
                        <label>' . _('Access Key ID') . ':</label>
                    </td>
                    <td>
                        <input type="text" size="40" name="accesskeyid" id="accesskeyid" value="' . encode_form_val($accesskeyid) . '" class="textfield form-control credsinput" disabled>
                    </td>
                </tr>
                <tr class="specifiedcredentials vt">
                    <td style="width: 180px;">
                        <label>' . _('Secret Access Key') . ':</label>
                    </td>
                    <td>
                        <input type="text" size="40" name="accesskey" id="accesskey" value="' . encode_form_val($accesskey) . '" class="textfield form-control credsinput" disabled>
                    </td>
                </tr>
                <tr class="specifiedcredentials vt">
                    <td style="width: 180px;">
                        <label>' . _('AWS Region') . ':</label>
                    </td>
                    <td>
                        <input type="text" size="40" name="region" id="region" value="' . encode_form_val($region) . '" class="textfield form-control credsinput" disabled>
                    </td>
                </tr>
                <tr class="specifiedcredentials vt">
                    <td style="width: 180px;">
                        <label>' . _('DynamoDB Name') . ':</label>
                    </td>
                    <td>
                        <input type="text" size="40" name="dynamodb_name" id="dynamodb_name" value="' . encode_form_val($dynamodb_name) . '" class="textfield form-control credsinput">
                        <div class="subtext">' . _("The name you would like to have associated with this DynamoDB.") . '</div>
                    </td>                    
                </tr>
            </table>

            <h5 class="ul">' . _('DynamoDB Metrics') . '</h5>
            <p>' . _("Specify which metrics you would like to monitor for the DynamoDB") . '.</p>
            <table class="table table-no-border table-auto-width table-padded">
                <tr>
                    <td class="vt">
                        <input type="checkbox" class="checkbox" id="ConditionalCheckFailedRequests" name="services[ConditionalCheckFailedRequests]"  ' . is_checked(checkbox_binary($services["ConditionalCheckFailedRequests"]), "0") . '>
                    </td>
                    <td>
                        <label for="ConditionalCheckFailedRequests" class="select-cf-option">' . _('Conditional Check Failed Requests') . '</label><br>
                        ' . _('The number of failed attempts to perform conditional writes.') . '
                        <div class="pad-t5">
                            <label><img src="'.theme_image('error.png').'" class="tt-bind" title="'._('Warning Threshold').'"></label> <input type="text" size="2" name="serviceargs[ConditionalCheckFailedRequests_warning]" value="' . $serviceargs["ConditionalCheckFailedRequests_warning"] . '" class="textfield form-control condensed">  &nbsp; <label><img src="'.theme_image('critical_small.png').'" class="tt-bind" title="'._('Critical Threshold').'"></label> <input type="text" size="2" name="serviceargs[ConditionalCheckFailedRequests_critical]" value="' . $serviceargs["ConditionalCheckFailedRequests_critical"] . '" class="textfield form-control condensed"> 
                        </div>
                    </td>
                </tr>

                <tr>
                    <td class="vt">
                        <input type="checkbox" class="checkbox" id="ConsumedReadCapacityUnits" name="services[ConsumedReadCapacityUnits]"  ' . is_checked(checkbox_binary($services["ConsumedReadCapacityUnits"]), "0") . '>
                    </td>
                    <td>
                        <label for="ConsumedReadCapacityUnits" class="select-cf-option">' . _('Consumed Read Capacity Units') . '</label><br>
                        ' . _('The number of read capacity units consumed over the specified time period.') . '
                        <div class="pad-t5">
                            <label><img src="'.theme_image('error.png').'" class="tt-bind" title="'._('Warning Threshold').'"></label> <input type="text" size="2" name="serviceargs[ConsumedReadCapacityUnits_warning]" value="' . $serviceargs["ConsumedReadCapacityUnits_warning"] . '" class="textfield form-control condensed">  &nbsp; <label><img src="'.theme_image('critical_small.png').'" class="tt-bind" title="'._('Critical Threshold').'"></label> <input type="text" size="2" name="serviceargs[ConsumedReadCapacityUnits_critical]" value="' . $serviceargs["ConsumedReadCapacityUnits_critical"] . '" class="textfield form-control condensed"> 
                        </div>
                    </td>
                </tr>
                
                <tr>
                    <td class="vt">
                        <input type="checkbox" class="checkbox" id="ConsumedWriteCapacityUnits" name="services[ConsumedWriteCapacityUnits]"  ' . is_checked(checkbox_binary($services["ConsumedWriteCapacityUnits"]), "0") . '>
                    </td>
                    <td>
                        <label for="ConsumedWriteCapacityUnits" class="select-cf-option">' . _('Consumed Write Capacity Units') . '</label><br>
                        ' . _('The number of write capacity units consumed over the specified time period.') . '
                        <div class="pad-t5">
                            <label><img src="'.theme_image('error.png').'" class="tt-bind" title="'._('Warning Threshold').'"></label> <input type="text" size="2" name="serviceargs[ConsumedWriteCapacityUnits_warning]" value="' . $serviceargs["ConsumedWriteCapacityUnits_warning"] . '" class="textfield form-control condensed">  &nbsp; <label><img src="'.theme_image('critical_small.png').'" class="tt-bind" title="'._('Critical Threshold').'"></label> <input type="text" size="2" name="serviceargs[ConsumedWriteCapacityUnits_critical]" value="' . $serviceargs["ConsumedWriteCapacityUnits_critical"] . '" class="textfield form-control condensed"> 
                        </div>
                    </td>
                </tr>

                <tr>
                    <td class="vt">
                        <input type="checkbox" class="checkbox" id="OnlineIndexConsumedWriteCapacity" name="services[OnlineIndexConsumedWriteCapacity]"  ' . is_checked(checkbox_binary($services["OnlineIndexConsumedWriteCapacity"]), "1") . '>
                    </td>
                    <td>
                        <label for="OnlineIndexConsumedWriteCapacity" class="select-cf-option">' . _('Online Index Consumed Write Capacity') . '</label><br>
                        ' . _('The number of write capacity units consumed when adding a new global secondary index to a table.') . '
                        <div class="pad-t5">
                            <label><img src="'.theme_image('error.png').'" class="tt-bind" title="'._('Warning Threshold').'"></label> <input type="text" size="2" name="serviceargs[OnlineIndexConsumedWriteCapacity_warning]" value="' . $serviceargs["OnlineIndexConsumedWriteCapacity_warning"] . '" class="textfield form-control condensed">  &nbsp; <label><img src="'.theme_image('critical_small.png').'" class="tt-bind" title="'._('Critical Threshold').'"></label> <input type="text" size="2" name="serviceargs[OnlineIndexConsumedWriteCapacity_critical]" value="' . $serviceargs["OnlineIndexConsumedWriteCapacity_critical"] . '" class="textfield form-control condensed"> 
                        </div>
                    </td>
                </tr>

                <tr>
                    <td class="vt">
                        <input type="checkbox" class="checkbox" id="OnlineIndexPercentageProgress" name="services[OnlineIndexPercentageProgress]"  ' . is_checked(checkbox_binary($services["OnlineIndexPercentageProgress"]), "1") . '>
                    </td>
                    <td>
                        <label for="OnlineIndexPercentageProgress" class="select-cf-option">' . _('Online Index Percentage Progress') . '</label><br>
                        ' . _('The percentage of completion when a new global secondary index is being added to a table.') . '
                        <div class="pad-t5">
                            <label><img src="'.theme_image('error.png').'" class="tt-bind" title="'._('Warning Threshold').'"></label> <input type="text" size="2" name="serviceargs[OnlineIndexPercentageProgress_warning]" value="' . $serviceargs["OnlineIndexPercentageProgress_warning"] . '" class="textfield form-control condensed">  &nbsp; <label><img src="'.theme_image('critical_small.png').'" class="tt-bind" title="'._('Critical Threshold').'"></label> <input type="text" size="2" name="serviceargs[OnlineIndexPercentageProgress_critical]" value="' . $serviceargs["OnlineIndexPercentageProgress_critical"] . '" class="textfield form-control condensed"> 
                        </div>
                    </td>
                </tr>

                 <tr>
                    <td class="vt">
                        <input type="checkbox" class="checkbox" id="OnlineIndexThrottleEvents" name="services[OnlineIndexThrottleEvents]"  ' . is_checked(checkbox_binary($services["OnlineIndexThrottleEvents"]), "1") . '>
                    </td>
                    <td>
                        <label for="OnlineIndexThrottleEvents" class="select-cf-option">' . _('Online Index Throttle Events') . '</label><br>
                        ' . _('The number of write throttle events that occur when adding a new global secondary index to a table.') . '
                        <div class="pad-t5">
                            <label><img src="'.theme_image('error.png').'" class="tt-bind" title="'._('Warning Threshold').'"></label> <input type="text" size="2" name="serviceargs[OnlineIndexThrottleEvents_warning]" value="' . $serviceargs["OnlineIndexThrottleEvents_warning"] . '" class="textfield form-control condensed">  &nbsp; <label><img src="'.theme_image('critical_small.png').'" class="tt-bind" title="'._('Critical Threshold').'"></label> <input type="text" size="2" name="serviceargs[OnlineIndexThrottleEvents_critical]" value="' . $serviceargs["OnlineIndexThrottleEvents_critical"] . '" class="textfield form-control condensed"> 
                        </div>
                    </td>
                </tr>     

                <tr>
                    <td class="vt">
                        <input type="checkbox" class="checkbox" id="PendingReplicationCount" name="services[PendingReplicationCount]"  ' . is_checked(checkbox_binary($services["PendingReplicationCount"]), "1") . '>
                    </td>
                    <td>
                        <label for="PendingReplicationCount" class="select-cf-option">' . _('Pending Replication Count') . '</label><br>
                        ' . _('The number of item updates that are written to one replica table, but that have not yet been written to another replica in the global table.') . '
                        <div class="pad-t5">
                            <label><img src="'.theme_image('error.png').'" class="tt-bind" title="'._('Warning Threshold').'"></label> <input type="text" size="2" name="serviceargs[PendingReplicationCount_warning]" value="' . $serviceargs["PendingReplicationCount_warning"] . '" class="textfield form-control condensed">  &nbsp; <label><img src="'.theme_image('critical_small.png').'" class="tt-bind" title="'._('Critical Threshold').'"></label> <input type="text" size="2" name="serviceargs[PendingReplicationCount_critical]" value="' . $serviceargs["PendingReplicationCount_critical"] . '" class="textfield form-control condensed"> 
                        </div>
                    </td>
                </tr>

                <tr>
                    <td class="vt">
                        <input type="checkbox" class="checkbox" id="ProvisionedReadCapacityUnits" name="services[ProvisionedReadCapacityUnits]"  ' . is_checked(checkbox_binary($services["ProvisionedReadCapacityUnits"]), "1") . '>
                    </td>
                    <td>
                        <label for="ProvisionedReadCapacityUnits" class="select-cf-option">' . _('Provisioned Read Capacity Units') . '</label><br>
                        ' . _('The number of provisioned read capacity units for a table or a global secondary index.') . '
                        <div class="pad-t5">
                            <label><img src="'.theme_image('error.png').'" class="tt-bind" title="'._('Warning Threshold').'"></label> <input type="text" size="2" name="serviceargs[ProvisionedReadCapacityUnits_warning]" value="' . $serviceargs["ProvisionedReadCapacityUnits_warning"] . '" class="textfield form-control condensed">  &nbsp; <label><img src="'.theme_image('critical_small.png').'" class="tt-bind" title="'._('Critical Threshold').'"></label> <input type="text" size="2" name="serviceargs[ProvisionedReadCapacityUnits_critical]" value="' . $serviceargs["ProvisionedReadCapacityUnits_critical"] . '" class="textfield form-control condensed"> 
                        </div>
                    </td>
                </tr>

                <tr>
                    <td class="vt">
                        <input type="checkbox" class="checkbox" id="ProvisionedWriteCapacityUnits" name="services[ProvisionedWriteCapacityUnits]"  ' . is_checked(checkbox_binary($services["ProvisionedWriteCapacityUnits"]), "1") . '>
                    </td>
                    <td>
                        <label for="ProvisionedWriteCapacityUnits" class="select-cf-option">' . _('Provisioned Write Capacity Units') . '</label><br>
                        ' . _('The number of provisioned write capacity units for a table or a global secondary index.') . '
                        <div class="pad-t5">
                            <label><img src="'.theme_image('error.png').'" class="tt-bind" title="'._('Warning Threshold').'"></label> <input type="text" size="2" name="serviceargs[ProvisionedWriteCapacityUnits_warning]" value="' . $serviceargs["ProvisionedWriteCapacityUnits_warning"] . '" class="textfield form-control condensed">  &nbsp; <label><img src="'.theme_image('critical_small.png').'" class="tt-bind" title="'._('Critical Threshold').'"></label> <input type="text" size="2" name="serviceargs[ProvisionedWriteCapacityUnits_critical]" value="' . $serviceargs["ProvisionedWriteCapacityUnits_critical"] . '" class="textfield form-control condensed"> 
                        </div>
                    </td>
                </tr>

                <tr>
                    <td class="vt">
                        <input type="checkbox" class="checkbox" id="ReadThrottleEvents" name="services[ReadThrottleEvents]"  ' . is_checked(checkbox_binary($services["ReadThrottleEvents"]), "1") . '>
                    </td>
                    <td>
                        <label for="ReadThrottleEvents" class="select-cf-option">' . _('Read Throttle Events') . '</label><br>
                        ' . _('Requests to DynamoDB that exceed the provisioned read capacity units for a table or a global secondary index.') . '
                        <div class="pad-t5">
                            <label><img src="'.theme_image('error.png').'" class="tt-bind" title="'._('Warning Threshold').'"></label> <input type="text" size="2" name="serviceargs[ReadThrottleEvents_warning]" value="' . $serviceargs["ReadThrottleEvents_warning"] . '" class="textfield form-control condensed">  &nbsp; <label><img src="'.theme_image('critical_small.png').'" class="tt-bind" title="'._('Critical Threshold').'"></label> <input type="text" size="2" name="serviceargs[ReadThrottleEvents_critical]" value="' . $serviceargs["ReadThrottleEvents_critical"] . '" class="textfield form-control condensed"> 
                        </div>
                    </td>
                </tr>

                <tr>
                    <td class="vt">
                        <input type="checkbox" class="checkbox" id="ReplicationLatency" name="services[ReplicationLatency]"  ' . is_checked(checkbox_binary($services["ReplicationLatency"]), "1") . '>
                    </td>
                    <td>
                        <label for="ReplicationLatency" class="select-cf-option">' . _('Replication Latency') . '</label><br>
                        ' . _('The elapsed time between an updated item appearing in the DynamoDB stream for one replica table, and that item appearing in another replica in the global table.') . '
                        <div class="pad-t5">
                            <label><img src="'.theme_image('error.png').'" class="tt-bind" title="'._('Warning Threshold').'"></label> <input type="text" size="2" name="serviceargs[ReplicationLatency_warning]" value="' . $serviceargs["ReplicationLatency_warning"] . '" class="textfield form-control condensed">  &nbsp; <label><img src="'.theme_image('critical_small.png').'" class="tt-bind" title="'._('Critical Threshold').'"></label> <input type="text" size="2" name="serviceargs[ReplicationLatency_critical]" value="' . $serviceargs["ReplicationLatency_critical"] . '" class="textfield form-control condensed"> 
                        </div>
                    </td>
                </tr>

                <tr>
                    <td class="vt">
                        <input type="checkbox" class="checkbox" id="ReturnedBytes" name="services[ReturnedBytes]"  ' . is_checked(checkbox_binary($services["ReturnedBytes"]), "1") . '>
                    </td>
                    <td>
                        <label for="ReturnedBytes" class="select-cf-option">' . _('Returned Bytes') . '</label><br>
                        ' . _('The number of bytes returned by GetRecords operations during the specified time period.') . '
                        <div class="pad-t5">
                            <label><img src="'.theme_image('error.png').'" class="tt-bind" title="'._('Warning Threshold').'"></label> <input type="text" size="2" name="serviceargs[ReturnedBytes_warning]" value="' . $serviceargs["ReturnedBytes_warning"] . '" class="textfield form-control condensed">  &nbsp; <label><img src="'.theme_image('critical_small.png').'" class="tt-bind" title="'._('Critical Threshold').'"></label> <input type="text" size="2" name="serviceargs[ReturnedBytes_critical]" value="' . $serviceargs["ReturnedBytes_critical"] . '" class="textfield form-control condensed"> 
                        </div>
                    </td>
                </tr>

                <tr>
                    <td class="vt">
                        <input type="checkbox" class="checkbox" id="ReturnedItemCount" name="services[ReturnedItemCount]"  ' . is_checked(checkbox_binary($services["ReturnedItemCount"]), "1") . '>
                    </td>
                    <td>
                        <label for="ReturnedItemCount" class="select-cf-option">' . _('Returned Item Count') . '</label><br>
                        ' . _('The number of items returned by Query or Scan operations during the specified time period.') . '
                        <div class="pad-t5">
                            <label><img src="'.theme_image('error.png').'" class="tt-bind" title="'._('Warning Threshold').'"></label> <input type="text" size="2" name="serviceargs[ReturnedItemCount_warning]" value="' . $serviceargs["ReturnedItemCount_warning"] . '" class="textfield form-control condensed">  &nbsp; <label><img src="'.theme_image('critical_small.png').'" class="tt-bind" title="'._('Critical Threshold').'"></label> <input type="text" size="2" name="serviceargs[ReturnedItemCount_critical]" value="' . $serviceargs["ReturnedItemCount_critical"] . '" class="textfield form-control condensed"> 
                        </div>
                    </td>
                </tr>

                <tr>
                    <td class="vt">
                        <input type="checkbox" class="checkbox" id="ReturnedRecordsCount" name="services[ReturnedRecordsCount]"  ' . is_checked(checkbox_binary($services["ReturnedRecordsCount"]), "1") . '>
                    </td>
                    <td>
                        <label for="ReturnedRecordsCount" class="select-cf-option">' . _('Returned Records Count') . '</label><br>
                        ' . _('The number of stream records returned by GetRecords operations during the specified time period.') . '
                        <div class="pad-t5">
                            <label><img src="'.theme_image('error.png').'" class="tt-bind" title="'._('Warning Threshold').'"></label> <input type="text" size="2" name="serviceargs[ReturnedRecordsCount_warning]" value="' . $serviceargs["ReturnedRecordsCount_warning"] . '" class="textfield form-control condensed">  &nbsp; <label><img src="'.theme_image('critical_small.png').'" class="tt-bind" title="'._('Critical Threshold').'"></label> <input type="text" size="2" name="serviceargs[ReturnedRecordsCount_critical]" value="' . $serviceargs["ReturnedRecordsCount_critical"] . '" class="textfield form-control condensed"> 
                        </div>
                    </td>
                </tr>

                <tr>
                    <td class="vt">
                        <input type="checkbox" class="checkbox" id="SuccessfulRequestLatency" name="services[SuccessfulRequestLatency]"  ' . is_checked(checkbox_binary($services["SuccessfulRequestLatency"]), "1") . '>
                    </td>
                    <td>
                        <label for="SuccessfulRequestLatency" class="select-cf-option">' . _('Successful Request Latency') . '</label><br>
                        ' . _('Successful requests to DynamoDB or Amazon DynamoDB Streams during the specified time period.') . '
                        <div class="pad-t5">
                            <label><img src="'.theme_image('error.png').'" class="tt-bind" title="'._('Warning Threshold').'"></label> <input type="text" size="2" name="serviceargs[SuccessfulRequestLatency_warning]" value="' . $serviceargs["SuccessfulRequestLatency_warning"] . '" class="textfield form-control condensed">  &nbsp; <label><img src="'.theme_image('critical_small.png').'" class="tt-bind" title="'._('Critical Threshold').'"></label> <input type="text" size="2" name="serviceargs[SuccessfulRequestLatency_critical]" value="' . $serviceargs["SuccessfulRequestLatency_critical"] . '" class="textfield form-control condensed"> 
                        </div>
                    </td>
                </tr>

                <tr>
                    <td class="vt">
                        <input type="checkbox" class="checkbox" id="SystemErrors" name="services[SystemErrors]"  ' . is_checked(checkbox_binary($services["SystemErrors"]), "1") . '>
                    </td>
                    <td>
                        <label for="SystemErrors" class="select-cf-option">' . _('System Errors') . '</label><br>
                        ' . _('Requests to DynamoDB or Amazon DynamoDB Streams that generate an HTTP 500 status code during the specified time period.') . '
                        <div class="pad-t5">
                            <label><img src="'.theme_image('error.png').'" class="tt-bind" title="'._('Warning Threshold').'"></label> <input type="text" size="2" name="serviceargs[SystemErrors_warning]" value="' . $serviceargs["SystemErrors_warning"] . '" class="textfield form-control condensed">  &nbsp; <label><img src="'.theme_image('critical_small.png').'" class="tt-bind" title="'._('Critical Threshold').'"></label> <input type="text" size="2" name="serviceargs[SystemErrors_critical]" value="' . $serviceargs["SystemErrors_critical"] . '" class="textfield form-control condensed"> 
                        </div>
                    </td>
                </tr>

                <tr>
                    <td class="vt">
                        <input type="checkbox" class="checkbox" id="TimeToLiveDeletedItemCount" name="services[TimeToLiveDeletedItemCount]"  ' . is_checked(checkbox_binary($services["TimeToLiveDeletedItemCount"]), "1") . '>
                    </td>
                    <td>
                        <label for="TimeToLiveDeletedItemCount" class="select-cf-option">' . _('Time To Live Deleted Item Count') . '</label><br>
                        ' . _('The number of items deleted by Time To Live (TTL) during the specified time period.') . '
                        <div class="pad-t5">
                            <label><img src="'.theme_image('error.png').'" class="tt-bind" title="'._('Warning Threshold').'"></label> <input type="text" size="2" name="serviceargs[TimeToLiveDeletedItemCount_warning]" value="' . $serviceargs["TimeToLiveDeletedItemCount_warning"] . '" class="textfield form-control condensed">  &nbsp; <label><img src="'.theme_image('critical_small.png').'" class="tt-bind" title="'._('Critical Threshold').'"></label> <input type="text" size="2" name="serviceargs[TimeToLiveDeletedItemCount_critical]" value="' . $serviceargs["TimeToLiveDeletedItemCount_critical"] . '" class="textfield form-control condensed"> 
                        </div>
                    </td>
                </tr>

                <tr>
                    <td class="vt">
                        <input type="checkbox" class="checkbox" id="ThrottledRequests" name="services[ThrottledRequests]"  ' . is_checked(checkbox_binary($services["ThrottledRequests"]), "1") . '>
                    </td>
                    <td>
                        <label for="ThrottledRequests" class="select-cf-option">' . _('Throttled Requests') . '</label><br>
                        ' . _('Requests to DynamoDB that exceed the provisioned throughput limits on a resource.') . '
                        <div class="pad-t5">
                            <label><img src="'.theme_image('error.png').'" class="tt-bind" title="'._('Warning Threshold').'"></label> <input type="text" size="2" name="serviceargs[ThrottledRequests_warning]" value="' . $serviceargs["ThrottledRequests_warning"] . '" class="textfield form-control condensed">  &nbsp; <label><img src="'.theme_image('critical_small.png').'" class="tt-bind" title="'._('Critical Threshold').'"></label> <input type="text" size="2" name="serviceargs[ThrottledRequests_critical]" value="' . $serviceargs["ThrottledRequests_critical"] . '" class="textfield form-control condensed"> 
                        </div>
                    </td>
                </tr> 

                <tr>
                    <td class="vt">
                        <input type="checkbox" class="checkbox" id="UserErrors" name="services[UserErrors]"  ' . is_checked(checkbox_binary($services["UserErrors"]), "1") . '>
                    </td>
                    <td>
                        <label for="UserErrors" class="select-cf-option">' . _('User Errors') . '</label><br>
                        ' . _('Requests to DynamoDB or Amazon DynamoDB Streams that generate an HTTP 400 status code during the specified time period.') . '
                        <div class="pad-t5">
                            <label><img src="'.theme_image('error.png').'" class="tt-bind" title="'._('Warning Threshold').'"></label> <input type="text" size="2" name="serviceargs[UserErrors_warning]" value="' . $serviceargs["UserErrors_warning"] . '" class="textfield form-control condensed">  &nbsp; <label><img src="'.theme_image('critical_small.png').'" class="tt-bind" title="'._('Critical Threshold').'"></label> <input type="text" size="2" name="serviceargs[UserErrors_critical]" value="' . $serviceargs["UserErrors_critical"] . '" class="textfield form-control condensed"> 
                        </div>
                    </td>
                </tr>  

                <tr>
                    <td class="vt">
                        <input type="checkbox" class="checkbox" id="WriteThrottleEvents" name="services[WriteThrottleEvents]"  ' . is_checked(checkbox_binary($services["WriteThrottleEvents"]), "1") . '>
                    </td>
                    <td>
                        <label for="WriteThrottleEvents" class="select-cf-option">' . _('Write Throttle Events') . '</label><br>
                        ' . _('Requests to DynamoDB that exceed the provisioned write capacity units for a table or a global secondary index.') . '
                        <div class="pad-t5">
                            <label><img src="'.theme_image('error.png').'" class="tt-bind" title="'._('Warning Threshold').'"></label> <input type="text" size="2" name="serviceargs[WriteThrottleEvents_warning]" value="' . $serviceargs["WriteThrottleEvents_warning"] . '" class="textfield form-control condensed">  &nbsp; <label><img src="'.theme_image('critical_small.png').'" class="tt-bind" title="'._('Critical Threshold').'"></label> <input type="text" size="2" name="serviceargs[WriteThrottleEvents_critical]" value="' . $serviceargs["WriteThrottleEvents_critical"] . '" class="textfield form-control condensed"> 
                        </div>
                    </td>
                </tr>
            </table>';
                            break;

            $output .= '

            <div style="height: 20px;"></div>
            <script>
                $("#ToggleAllAdvanced").click(function() {
                    if ($(this).prop("checked") ==  true) {
                        $(".advanced-checkbox").prop("checked", true);
                    } else {
                        $(".advanced-checkbox").prop("checked", false);
                    }
                });
            </script>';
            break;

        case CONFIGWIZARD_MODE_VALIDATESTAGE2DATA:

            // Get variables that were passed to us            
            $accesskeyid = grab_array_var($inargs, "accesskeyid", "");            
            $accesskey = grab_array_var($inargs, "accesskey", "");           
            $region = grab_array_var($inargs, "region", "");
            $dynamodb_name = grab_array_var($inargs, "dynamodb_name", "");

            $outargs[CONFIGWIZARD_PASSBACK_DATA]['checkboxlist'] = $checkboxlist;

            $serviceargs = grab_array_var($inargs, "serviceargs", "");

            // Check for errors
            $errors = 0;
            $errmsg = array();

            if ($errors > 0) {
                $outargs[CONFIGWIZARD_ERROR_MESSAGES] = $errmsg;
                $result = 1;
            }
            break;


        case CONFIGWIZARD_MODE_GETSTAGE3HTML:

            // Get variables that were passed to us            
            $accesskeyid = grab_array_var($inargs, "accesskeyid", "");
            $accesskey = grab_array_var($inargs, "accesskey", "");           
            $region = grab_array_var($inargs, "region", "");
            $dynamodb_name = grab_array_var($inargs, "dynamodb_name", "");

            $checkboxlist = grab_array_var($inargs, "checkboxlist", "");

            $outargs[CONFIGWIZARD_PASSBACK_DATA]['checkboxlist'] = $checkboxlist;

            $services = "";
            $services_serial = grab_array_var($inargs, "services_serial");

            if ($services_serial != "") {
                $services = unserialize(base64_decode($services_serial));
            } else {
                $services = grab_array_var($inargs, "services");
            }

// echo '<pre>',print_r($region,1),'</pre>'; //testing
// echo '<pre>',print_r($accesskeyid,1),'</pre>'; //testing
// echo '<pre>',print_r($accesskey,1),'</pre>'; //testing
// echo '<pre>',print_r($services,1),'</pre>'; //testing
// echo '<pre>',print_r($dynamodb_name,1),'</pre>'; //testing

            $serviceargs = "";
            $serviceargs_serial = grab_array_var($inargs, "serviceargs_serial");
            if ($serviceargs_serial != "") {
                $serviceargs = unserialize(base64_decode($serviceargs_serial));
            } else {
                $serviceargs = grab_array_var($inargs, "serviceargs");
            }

            $output = '          
            <input type="hidden" name="accesskeyid" value="' . encode_form_val($accesskeyid) . '">
            <input type="hidden" name="accesskey" value="' . encode_form_val($accesskey) . '">
            <input type="hidden" name="region" value="' . encode_form_val($region) . '">
            <input type="hidden" name="dynamodb_name" value="' . encode_form_val($dynamodb_name) . '">

            <input type="hidden" name="services_serial" value="' . base64_encode(serialize($services)) . '">
            <input type="hidden" name="serviceargs_serial" value="' . base64_encode(serialize($serviceargs)) . '">';
            break;

        case CONFIGWIZARD_MODE_VALIDATESTAGE3DATA:

            $check_interval = grab_array_var($inargs, "check_interval", "");
            $hostnames_serial = grab_array_var($inargs, "hostnames", "");

            $hostnames = unserialize(base64_decode($hostnames_serial));

            // Check for errors
            $errors = 0;
            $errmsg = array();
            if ($check_interval < 5) {
                $errmsg[$errors++] = "Check interval cannot be less than five minutes. This is because AWS sends CloudWatch data every five minutes. Querying between the time the last data was received and now - if less than five minutes - will result in an empty response from CloudWatch.";
            }
            if ($errors > 0) {
                $outargs[CONFIGWIZARD_ERROR_MESSAGES] = $errmsg;
                $result = 1;
            }
            break;

            break;

        case CONFIGWIZARD_MODE_GETFINALSTAGEHTML:

            $output = '
            ';
            break;

        case CONFIGWIZARD_MODE_GETOBJECTS:

            // Get variables that were passed to us
            $accesskeyid = grab_array_var($inargs, "accesskeyid", "");
            $accesskey = grab_array_var($inargs, "accesskey", "");          
            $region = grab_array_var($inargs, "region", "");
            $dynamodb_name = grab_array_var($dynamodb_name, "dynamodb_name", "");

            $services_serial = grab_array_var($inargs, "services_serial", "");
            $serviceargs_serial = grab_array_var($inargs, "serviceargs_serial", "");

            $services = unserialize(base64_decode($services_serial));
            $serviceargs = unserialize(base64_decode($serviceargs_serial));

            // Save data for later use in re-entrance
            $meta_arr = array();
            $meta_arr["accesskeyid"] = $accesskeyid;
            $meta_arr["accesskey"] = $accesskey;
            $meta_arr["dynamodb_name"] = $dynamodb_name;
            $meta_arr["region"] = $region;
            $meta_arr["services"] = $services;
            $meta_arr["serivceargs"] = $serviceargs;

            save_configwizard_object_meta($wizard_name, $hostnames, "", $meta_arr);

            $objs = array();          

            $service_name_dict = array(
                "ConditionalCheckFailedRequests" => "Conditional Check Failed Requests",
                "ConsumedReadCapacityUnits" => "Consumed Read Capacity Units",
                "ConsumedWriteCapacityUnits" => "Consumed Write Capacity Units", 
                "OnlineIndexConsumedWriteCapacity" => "Online Index Consumed Write Capacity",
                "OnlineIndexPercentageProgress" => "Online Index Percentage Progress", 
                "OnlineIndexThrottleEvents" => "Online Index Throttle Events", 
                "PendingReplicationCount" => "Pending Replication Count", 
                "ProvisionedReadCapacityUnits" => "Provisioned Read Capacity Units", 
                "ProvisionedWriteCapacityUnits" => "Provisioned Write Capacity Units",
                "ReadThrottleEvents" => "Read Throttle Events", 
                "ReplicationLatency" => "Replication Latency",
                "ReturnedBytes" => "Returned Bytes",
                "ReturnedItemCount" => "Returned Item Count", 
                "ReturnedRecordsCount" => "Returned Records Count",
                "SuccessfulRequestLatency" => "Successful Request Latency",
                "SystemErrors" => "System Errors",
                "TimeToLiveDeletedItemCount" => "Time To Live Deleted Item Count",
                "ThrottledRequests" => "Throttled Requests",
                "UserErrors" => "User Errors", 
                "WriteThrottleEvents" => "Write Throttle Events",
            );

            // See which services we should monitor
            foreach ($services as $svc => $metric) {

                switch ($svc) {

                case "ConditionalCheckFailedRequests":
                    $objs[] = array(
                        "type" => OBJECTTYPE_SERVICE,
                        "host_name" => $metric["Hostname"],
                        "service_description" => "Conditional Check Failed Requests",
                        "_xiwizard" => $wizard_name,
                        "check_command" => "check_dynamodb!" . "--period " . $check_interval . "--metricname ConditionalCheckFailedRequests" . "--region " . $region . "--warning " . $values[$metricname . "_warning"] . "--critical " . $values[$metricname . "_critical"]                     
                    );
                    break;

                case "ConsumedReadCapacityUnits":
                    $objs[] = array(
                        "type" => OBJECTTYPE_SERVICE,
                        "host_name" => $metric["Hostname"],
                        "service_description" => "Consumed Read Capacity Units",
                        "_xiwizard" => $wizard_name,
                        "check_command" => "check_dynamodb!" . "--period " . $check_interval . "--metricname ConsumedReadCapacityUnits" . "--region " . $region . "--warning " . $values[$metricname . "_warning"] . "--critical " . $values[$metricname . "_critical"]                     
                    );
                    break;

                case "ConsumedWriteCapacityUnits":
                    $objs[] = array(
                        "type" => OBJECTTYPE_SERVICE,
                        "host_name" => $metric["Hostname"],
                        "service_description" => "Consumed Write Capacity Units",
                        "_xiwizard" => $wizard_name,
                        "check_command" => "check_dynamodb!" . "--period " . $check_interval . "--metricname ConsumedWriteCapacityUnits" . "--region " . $region . "--warning " . $values[$metricname . "_warning"] . "--critical " . $values[$metricname . "_critical"]                     
                    );
                    break;

                case "OnlineIndexConsumedWriteCapacity":
                    $objs[] = array(
                        "type" => OBJECTTYPE_SERVICE,
                        "host_name" => $metric["Hostname"],
                        "service_description" => "Online Index Consumed Write Capacity",
                        "_xiwizard" => $wizard_name,
                        "check_command" => "check_dynamodb!" . "--period " . $check_interval . "--metricname OnlineIndexConsumedWriteCapacity" . "--region " . $region . "--warning " . $values[$metricname . "_warning"] . "--critical " . $values[$metricname . "_critical"]                     
                    );
                    break;

                case "OnlineIndexPercentageProgress":
                    $objs[] = array(
                        "type" => OBJECTTYPE_SERVICE,
                        "host_name" => $metric["Hostname"],
                        "service_description" => "Online Index Percentage Progress",
                        "_xiwizard" => $wizard_name,
                        "check_command" => "check_dynamodb!" . "--period " . $check_interval . "--metricname OnlineIndexPercentageProgress" . "--region " . $region . "--warning " . $values[$metricname . "_warning"] . "--critical " . $values[$metricname . "_critical"]                     
                    );
                    break;

                case "OnlineIndexThrottleEvents":
                    $objs[] = array(
                        "type" => OBJECTTYPE_SERVICE,
                        "host_name" => $metric["Hostname"],
                        "service_description" => "Online Index Throttle Events",
                        "_xiwizard" => $wizard_name,
                        "check_command" => "check_dynamodb!" . "--period " . $check_interval . "--metricname OnlineIndexThrottleEvents" . "--region " . $region . "--warning " . $values[$metricname . "_warning"] . "--critical " . $values[$metricname . "_critical"]                     
                    );
                    break;

                case "PendingReplicationCount":
                    $objs[] = array(
                        "type" => OBJECTTYPE_SERVICE,
                        "host_name" => $metric["Hostname"],
                        "service_description" => "Pending Replication Count",
                        "_xiwizard" => $wizard_name,
                        "check_command" => "check_dynamodb!" . "--period " . $check_interval . "--metricname PendingReplicationCount" . "--region " . $region . "--warning " . $values[$metricname . "_warning"] . "--critical " . $values[$metricname . "_critical"]                     
                    );
                    break;

                case "ProvisionedReadCapacityUnits":
                    $objs[] = array(
                        "type" => OBJECTTYPE_SERVICE,
                        "host_name" => $metric["Hostname"],
                        "service_description" => "Provisioned Read Capacity Units",
                        "_xiwizard" => $wizard_name,
                        "check_command" => "check_dynamodb!" . "--period " . $check_interval . "--metricname ProvisionedReadCapacityUnits" . "--region " . $region . "--warning " . $values[$metricname . "_warning"] . "--critical " . $values[$metricname . "_critical"]                     
                    );
                    break;

                case "ReadThrottleEvents":
                    $objs[] = array(
                        "type" => OBJECTTYPE_SERVICE,
                        "host_name" => $metric["Hostname"],
                        "service_description" => "Read Throttle Events",
                        "_xiwizard" => $wizard_name,
                        "check_command" => "check_dynamodb!" . "--period " . $check_interval . "--metricname ReadThrottleEvents" . "--region " . $region . "--warning " . $values[$metricname . "_warning"] . "--critical " . $values[$metricname . "_critical"]                     
                    );
                    break;

                case "ReplicationLatency":
                    $objs[] = array(
                        "type" => OBJECTTYPE_SERVICE,
                        "host_name" => $metric["Hostname"],
                        "service_description" => "Replication Latency",
                        "_xiwizard" => $wizard_name,
                        "check_command" => "check_dynamodb!" . "--period " . $check_interval . "--metricname ReplicationLatency" . "--region " . $region . "--warning " . $values[$metricname . "_warning"] . "--critical " . $values[$metricname . "_critical"]                     
                    );
                    break;

                case "ReturnedBytes":
                    $objs[] = array(
                        "type" => OBJECTTYPE_SERVICE,
                        "host_name" => $metric["Hostname"],
                        "service_description" => "Returned Bytes",
                        "_xiwizard" => $wizard_name,
                        "check_command" => "check_dynamodb!" . "--period " . $check_interval . "--metricname ReturnedBytes" . "--region " . $region . "--warning " . $values[$metricname . "_warning"] . "--critical " . $values[$metricname . "_critical"]                     
                    );
                    break;

                case "ReturnedItemCount":
                    $objs[] = array(
                        "type" => OBJECTTYPE_SERVICE,
                        "host_name" => $metric["Hostname"],
                        "service_description" => "Returned Item Count",
                        "_xiwizard" => $wizard_name,
                        "check_command" => "check_dynamodb!" . "--period " . $check_interval . "--metricname ReturnedItemCount" . "--region " . $region . "--warning " . $values[$metricname . "_warning"] . "--critical " . $values[$metricname . "_critical"]                     
                    );
                    break;

                case "ReturnedRecordsCount":
                    $objs[] = array(
                        "type" => OBJECTTYPE_SERVICE,
                        "host_name" => $metric["Hostname"],
                        "service_description" => "Returned Records Count",
                        "_xiwizard" => $wizard_name,
                        "check_command" => "check_dynamodb!" . "--period " . $check_interval . "--metricname ReturnedRecordsCount" . "--region " . $region . "--warning " . $values[$metricname . "_warning"] . "--critical " . $values[$metricname . "_critical"]                     
                    );
                    break;

                case "SuccessfulRequestLatency":
                    $objs[] = array(
                        "type" => OBJECTTYPE_SERVICE,
                        "host_name" => $metric["Hostname"],
                        "service_description" => "Successful Request Latency",
                        "_xiwizard" => $wizard_name,
                        "check_command" => "check_dynamodb!" . "--period " . $check_interval . "--metricname SuccessfulRequestLatency" . "--region " . $region . "--warning " . $values[$metricname . "_warning"] . "--critical " . $values[$metricname . "_critical"]                     
                    );
                    break;

                case "SystemErrors":
                    $objs[] = array(
                        "type" => OBJECTTYPE_SERVICE,
                        "host_name" => $metric["Hostname"],
                        "service_description" => "System Errors",
                        "_xiwizard" => $wizard_name,
                        "check_command" => "check_dynamodb!" . "--period " . $check_interval . "--metricname SystemErrors" . "--region " . $region . "--warning " . $values[$metricname . "_warning"] . "--critical " . $values[$metricname . "_critical"]                     
                    );
                    break;

                case "TimeToLiveDeletedItemCount":
                    $objs[] = array(
                        "type" => OBJECTTYPE_SERVICE,
                        "host_name" => $metric["Hostname"],
                        "service_description" => "Time To Live Deleted Item Count",
                        "_xiwizard" => $wizard_name,
                        "check_command" => "check_dynamodb!" . "--period " . $check_interval . "--metricname TimeToLiveDeletedItemCount" . "--region " . $region . "--warning " . $values[$metricname . "_warning"] . "--critical " . $values[$metricname . "_critical"]                     
                    );
                    break;

                case "ThrottledRequests":
                    $objs[] = array(
                        "type" => OBJECTTYPE_SERVICE,
                        "host_name" => $metric["Hostname"],
                        "service_description" => "Throttled Requests",
                        "_xiwizard" => $wizard_name,
                        "check_command" => "check_dynamodb!" . "--period " . $check_interval . "--metricname ThrottledRequests" . "--region " . $region . "--warning " . $values[$metricname . "_warning"] . "--critical " . $values[$metricname . "_critical"]                     
                    );
                    break;

                case "UserErrors":
                    $objs[] = array(
                        "type" => OBJECTTYPE_SERVICE,
                        "host_name" => $metric["Hostname"],
                        "service_description" => "User Errors",
                        "_xiwizard" => $wizard_name,
                        "check_command" => "check_dynamodb!" . "--period " . $check_interval . "--metricname UserErrors" . "--region " . $region . "--warning " . $values[$metricname . "_warning"] . "--critical " . $values[$metricname . "_critical"]                     
                    );
                    break;

                case "WriteThrottleEvents":
                    $objs[] = array(
                        "type" => OBJECTTYPE_SERVICE,
                        "host_name" => $metric["Hostname"],
                        "service_description" => "Write Throttle Events",
                        "_xiwizard" => $wizard_name,
                        "check_command" => "check_dynamodb!" . "--period " . $check_interval . "--metricname WriteThrottleEvents" . "--region " . $region . "--warning " . $values[$metricname . "_warning"] . "--critical " . $values[$metricname . "_critical"]                     
                    );
                    break;
                }
            }

            // Return the object definitions to the wizard
            $outargs[CONFIGWIZARD_NAGIOS_OBJECTS] = $objs;

            break;

        default:
            break;
    }

    return $output;
}

/**
 *
 * @return string
 */
