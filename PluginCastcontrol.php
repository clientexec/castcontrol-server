<?php
require_once 'library/CE/NE_MailGateway.php';
require_once 'modules/admin/models/ServerPlugin.php';
/**
* @package Plugins
*/
class PluginCastcontrol extends ServerPlugin
{
    public $features = array(
        'packageName' => false,
        'testConnection' => false,
        'showNameservers' => false
    );
    var $apierror='';

    /*****************************************************************/
    // function getVariables - required function
    /*****************************************************************/
    function getVariables(){
        /* Specification
              itemkey     - used to identify variable in your other functions
              type        - text,textarea,yesno,password
              description - description of the variable, displayed in ClientExec
        */
        $variables = array(
            /*T*/"Name"/*/T*/ => array(
                "type"        => "hidden",
                "description" => "Used By CE to show plugin - must match how you call the action function names",
                "value"       => "Castcontrol"
            ),
            /*T*/"Description"/*/T*/ => array(
                "type"        => "hidden",
                "description" => /*T*/"Description viewable by admin in server settings"/*/T*/,
                "value"       => /*T*/"Cast-Control server integration.  Note: The custom field settings are used to hold information about the clients server.  Please create these fields in admin->custom fields->packages first.  The package name on server fields for each package hold the slot count.  Suspending a server sets the slot count to 0."/*/T*/
            ),
            /*T*/"HTTP SSL"/*/T*/ => array(
                "type"        => "yesno",
                "description" => /*T*/"Must also specify appropriate HTTP Port, usually 443"/*/T*/,
                "value"       => ""
            ),
            /*T*/"HTTP Host"/*/T*/ => array(
                "type"        => "text",
                "description" => /*T*/"DOMAIN/HOST, DO NOT INCLUDE http:// PREFIX (EG: mydomain.com)"/*/T*/,
                "value"       => ""
            ),
            /*T*/"HTTP Port"/*/T*/ => array(
                "type"        => "text",
                "description" => /*T*/"SHOULD GENERALLY BE 80"/*/T*/,
                "value"       => "80"
            ),
            /*T*/"HTTP Path"/*/T*/ => array(
                "type"        => "text",
                "description" => /*T*/"PATH TO THE CAST-CONTROL ROOT DIRECTORY (EG: /shoutcast/)   *MUST END IN FORWARD SLASH"/*/T*/,
                "value"       => "/"
            ),
            /*T*/"API Key"/*/T*/ => array(
                "type"        => "text",
                "description" => /*T*/"CAST-CONTROL SECURITY HASH"/*/T*/,
                "value"       => ""
            ),
            /*T*/"Portbase Custom Field"/*/T*/ => array(
                "type"        => "text",
                "description" => /*T*/"Package Custom Field Name"/*/T*/,
                "value"       => ""
            ),
            /*T*/"Username Custom Field"/*/T*/ => array(
                "type"        => "text",
                "description" => /*T*/"Package Custom Field Name"/*/T*/,
                "value"       => ""
            ),
            /*T*/"Password Custom Field"/*/T*/ => array(
                "type"        => "text",
                "description" => /*T*/"Package Custom Field Name"/*/T*/,
                "value"       => ""
            ),
            /*T*/"Actions"/*/T*/ => array(
                "type"        => "hidden",
                "description" => /*T*/"Current actions that are active for this plugin per server"/*/T*/,
                "value"       => "Create,Delete,Suspend,UnSuspend"
            ),
            /*T*/'package_vars_values'/*/T*/ => array(
                'type'        => 'hidden',
                'description' => /*T*/'Hosting account parameters'/*/T*/,
                'value'       => array(
                    'BitRate' => array(
                        'type'        => 'text',
                        'size'        => '5',
                        'description' => 'Bitrate',
                        'value'       => '24',
                        //'options'     => '8,16,20,24,32,40,48,56,64,80,96,112,128,160,192,224,256,320,384,512,768,1024'
                    ),
                    'MaxUsers' => array(
                        'type'        => 'text',
                        'size'        => '5',
                        'description' => /*T*/'Listener Slots'/*/T*/,
                        'value'       => '25',
                    ),
                    'Bandwidth' => array(
                        'type'        => 'text',
                        'size'        => '5',
                        'description' => /*T*/'Bandwidth'/*/T*/,
                        'value'       => '0',
                    ),
                    'AutoDJ' => array(
                        'type'        => 'yesno',
                        'size'        => '5',
                        'description' => /*T*/'Allow AutoDJ'/*/T*/,
                        'options'     => 'enabled,disabled',
                        'value'       => ''
                    ),
                    'AutoDJ Quota' => array(
                        'type'        => 'text',
                        'size'        => '5',
                        'description' => /*T*/'AutoDJ Quota'/*/T*/,
                        'value'       => '25',
                    ),
                    'Trial' => array(
                        'type'        => 'text',
                        'size'        => '15',
                        'description' => /*T*/'Set Trial Period for example: +5 days'/*/T*/,
                        'options'     => '',
                        'value'       => ''
                    ),
                    'SystemID' => array(
                        'type'        => 'text',
                        'size'        => '5',
                        'description' => /*T*/'SystemID'/*/T*/,
                        'value'       => '25',
                    )
                )
            )
        );
        return $variables;
    }

    function doCreate($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $this->create($this->buildParams($userPackage));
        return $userPackage->getCustomField("Domain Name") . ' has been created.';
    }

    function create($params)
    {
        $package = new UserPackage($params['package']['id'], $this->user);
        $username = $package->getCustomField($params['server']['variables']['plugin_castcontrol_Username_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        $password = $package->getCustomField($params['server']['variables']['plugin_castcontrol_Password_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);

        if(empty($username) || empty($password)){
            $errormsg = "The username or password contains no value";
            CE_Lib::log(4, "plugin_castcontrol::create::error: ".$errormsg);
            throw new CE_Exception($errormsg);
        }

        // Firstly we need to create the user account, if one already exists we will skip
        $api['auth']          = $params['server']['variables']["plugin_castcontrol_API_Key"];
        $api['http_ssl']      = $params['server']['variables']["plugin_castcontrol_HTTP_SSL"];
        $api['http_host']     = $params['server']['variables']["plugin_castcontrol_HTTP_Host"];
        $api['http_path']     = $params['server']['variables']["plugin_castcontrol_HTTP_Path"];
        $api['http_port']     = $params['server']['variables']["plugin_castcontrol_HTTP_Port"];
        $api['cmd']           = 'newuser';
        $api['username']      = $username;
        $api['password']      = $password;
        $api['email_address'] = $params['customer']['email'];

        if($response = api($api)){
            list($status, $msg) = explode(",", $response);

            if($status != 'Success' && strstr($response, 'incorrect_password')){
                $errormsg = "This username already exists or you have entered incorrect credentials. :: ".$response;
                CE_Lib::log(4, "plugin_castcontrol::create::error: ".$errormsg);
                throw new CE_Exception($errormsg);
            }

            if($status != 'Success' && $msg != 'User already exists'){
                $errormsg = "There was a problem creating the user account in cast-control :: $response";
                CE_Lib::log(4, "plugin_castcontrol::create::error: ".$errormsg);
                throw new CE_Exception($errormsg);
            }
        }elseif($this->apierror == 'auth_failed'){
            $errormsg = "Cast-Control API Authentication error, please check API Key";
            CE_Lib::log(4, "plugin_castcontrol::create::error: ".$errormsg);
            throw new CE_Exception($errormsg);
        }else{
            $errormsg = "Could not communicate with the Cast-Control API";
            CE_Lib::log(4, "plugin_castcontrol::create::error: ".$errormsg);
            throw new CE_Exception($errormsg);
        }

        unset($api);

        // Once User is created, we can now create the server
        $api['auth']          = $params['server']['variables']["plugin_castcontrol_API_Key"];
        $api['http_ssl']      = $params['server']['variables']["plugin_castcontrol_HTTP_SSL"];
        $api['http_host']     = $params['server']['variables']["plugin_castcontrol_HTTP_Host"];
        $api['http_path']     = $params['server']['variables']["plugin_castcontrol_HTTP_Path"];
        $api['http_port']     = $params['server']['variables']["plugin_castcontrol_HTTP_Port"];
        $api['cmd']           = 'newserver';
        $api['plan']          = false;
        $api['username']      = $username;
        $api['srv_password']  = $password;
        $api['slots']         = $params["package"]['variables']["MaxUsers"];
        $api['bitrate']       = $params["package"]['variables']["BitRate"];
        $api['bandwidth']     = $params["package"]['variables']["Bandwidth"];
        $api['autodj']        = (isset($params["package"]['variables']["AutoDJ"]) && $params['package_vars']['AutoDJ']==1)? 'Enabled' : 'Disabled';
        $api['autodj_upload'] = $params["package"]['variables']["AutoDJ_Quota"];

        if(isset($params["package"]['variables']["Trial"])
          && $params["package"]['variables']["Trial"] != ''
          &&  $params["package"]['variables']["Trial"] != 'disabled'){
            $api['expire'] =$params["package"]['variables']["Trial"];
        }
        if(!empty($params["package"]['variables']["SystemID"])){
            $api['system'] = $params["package"]['variables']["SystemID"];
        }
        if(isset($params["package"]['addons']['MaxUsers'])){
            $api['slots'] = $params["package"]['addons']['MaxUsers'];
        }
        if(isset($params["package"]['addons']['BitRate'])){
            $api['bitrate'] = $params["package"]['addons']['BitRate'];
        }
        if(isset($params["package"]['addons']['Bandwidth'])){
            $api['bandwidth'] = $params["package"]['addons']['Bandwidth'];
        }
        if(isset($params["package"]['addons']['AutoDJ'])){
            $api['autodj'] = $params["package"]['addons']['AutoDJ'];
        }
        if(isset($params["package"]['addons']['AutoDJQuota'])){
            $api['autodj_upload'] = $params["package"]['addons']['AutoDJQuota'];
        }
        if(isset($params["package"]['addons']['System'])){
            $api['SystemID'] = $params["package"]['addons']['System'];
        }

        // Check the API response
        if($response = api($api)){
            // Here we have successfully connected and authenticated to the cast-control API
            list($status, $portbase, $msg) = explode(",", $response);

            // Check for anything other than success
            if($status != 'Success'){
                $errormsg = "There was a problem creating the server in cast-control:: '$status' :: {$response}";
                CE_Lib::log(4, "plugin_castcontrol::create::error: ".$errormsg);
                throw new CE_Exception($errormsg);
            }

            $package = new UserPackage($params['package']['id'], $this->user);
            $package->setCustomField($params['server']['variables']['plugin_castcontrol_Portbase_Custom_Field'], $portbase);
        }elseif($this->apierror == 'auth_failed'){
            $errormsg = "Cast-Control API Authentication error, please check API Key";
            CE_Lib::log(4, "plugin_castcontrol::create::error: ".$errormsg);
            throw new CE_Exception($errormsg);
        }else{
            // API FAILED
            // Ussually either no connection has been established
            // or  the API key is incorrect.
            $errormsg = "User created, Could not communicate with the Cast-Control API";
            CE_Lib::log(4, "plugin_castcontrol::create::error: ".$errormsg);
            throw new CE_Exception($errormsg);
        }

        // Reach here, Success so return nothing
        return;
    }

    function doDelete($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $this->delete($this->buildParams($userPackage));
        return $userPackage->getCustomField("Domain Name") . ' has been deleted.';
    }

    function delete($params)
    {
        $package = new UserPackage($params['package']['id'], $this->user);
        $portbase = $package->getCustomField($params['server']['variables']['plugin_castcontrol_Portbase_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);

        // If protbase is empty, ignore the process as no server was assigned
        if(empty($portbase)){
            return;
        }

        $api['auth']      = $params['server']['variables']["plugin_castcontrol_API_Key"];
        $api['http_ssl']  = $params['server']['variables']["plugin_castcontrol_HTTP_SSL"];
        $api['http_host'] = $params['server']['variables']["plugin_castcontrol_HTTP_Host"];
        $api['http_path'] = $params['server']['variables']["plugin_castcontrol_HTTP_Path"];
        $api['http_port'] = $params['server']['variables']["plugin_castcontrol_HTTP_Port"];
        $api['cmd']       = 'delserver';
        $api['port']      = $portbase;

        // Check API response
        if($response = api($api)){
            // Here we have successfully connected and authenticated to the cast-control API
            list($status, $msg) = explode(",", $response);

            // Check for anything other than success
            if($status != 'Success'){
                $errormsg = "There was a problem terminating the server in cast-control:: {$response}";
                CE_Lib::log(4, "plugin_castcontrol::delete::error: ".$errormsg);
                throw new CE_Exception($errormsg);
            }

            $package = new UserPackage($params['package']['id'], $this->user);
            $package->setCustomField($params['server']['variables']['plugin_castcontrol_Portbase_Custom_Field'], '');
        }elseif($this->apierror == 'auth_failed'){
            $errormsg = "Cast-Control API Authentication error, please check API Key";
            CE_Lib::log(4, "plugin_castcontrol::delete::error: ".$errormsg);
            throw new CE_Exception($errormsg);
        }else{
            // API FAILED
            // Ussually either no connection has been established
            // or  the API key is incorrect.
            $errormsg = "User created, Could not communicate with the Cast-Control API";
            CE_Lib::log(4, "plugin_castcontrol::delete::error: ".$errormsg);
            throw new CE_Exception($errormsg);
        }

        //Reach here, Success so return nothing
        return;
    }

    function update($params)
    {
        // No Action required here
        return true;
    }

    function doSuspend($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $this->suspend($this->buildParams($userPackage));
        return $userPackage->getCustomField("Domain Name") . ' has been suspended.';
    }

    function suspend($params)
    {
        $package = new UserPackage($params['package']['id'], $this->user);
        $portbase = $package->getCustomField($params['server']['variables']['plugin_castcontrol_Portbase_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);

        // If protbase is empty, ignore the process as no server was assigned
        if(empty($portbase)){
            return;
        }

        $api['auth']      = $params['server']['variables']["plugin_castcontrol_API_Key"];
        $api['http_ssl']  = $params['server']['variables']["plugin_castcontrol_HTTP_SSL"];
        $api['http_host'] = $params['server']['variables']["plugin_castcontrol_HTTP_Host"];
        $api['http_path'] = $params['server']['variables']["plugin_castcontrol_HTTP_Path"];
        $api['http_port'] = $params['server']['variables']["plugin_castcontrol_HTTP_Port"];
        $api['cmd']       = 'suspend';
        $api['port']      = $portbase;
        $api['reason']    = 'Suspended by ClientExec';
        $api['days']      = 9999999999999; // indefinite

        // Check for an API response
        if($response = api($api)){
            // Here we have successfully connected and authenticated to the cast-control API
            list($status, $msg) = explode(",", $response);

            // Check for anything other than a success
            if($status != 'Success'){
                $errormsg = "There was a problem suspending the server:: {$response}";
                CE_Lib::log(4, "plugin_castcontrol::suspend::error: ".$errormsg);
                throw new CE_Exception($errormsg);
            }
        }elseif($this->apierror == 'auth_failed'){
            $errormsg = "Cast-Control API Authentication error, please check API Key";
            CE_Lib::log(4, "plugin_castcontrol::suspend::error: ".$errormsg);
            throw new CE_Exception($errormsg);
        }else{
            // API FAILED
            // Ussually either no connection has been established
            // or  the API key is incorrect.
            $errormsg = "Could not communicate with the Cast-Control API";
            CE_Lib::log(4, "plugin_castcontrol::suspend::error: ".$errormsg);
            throw new CE_Exception($errormsg);
        }

        // Reach here, Success so return nothing
        return;
    }

    function doUnSuspend($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $this->unsuspend($this->buildParams($userPackage));
        return $userPackage->getCustomField("Domain Name") . ' has been unsuspended.';
    }

    function unsuspend($params)
    {
        $package = new UserPackage($params['package']['id'], $this->user);
        $portbase = $package->getCustomField($params['server']['variables']['plugin_castcontrol_Portbase_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);

        // If protbase is empty, ignore the process as no server was assigned
        if(empty($portbase)){
            return;
        }

        $api['auth']      = $params['server']['variables']["plugin_castcontrol_API_Key"];
        $api['http_ssl']  = $params['server']['variables']["plugin_castcontrol_HTTP_SSL"];
        $api['http_host'] = $params['server']['variables']["plugin_castcontrol_HTTP_Host"];
        $api['http_path'] = $params['server']['variables']["plugin_castcontrol_HTTP_Path"];
        $api['http_port'] = $params['server']['variables']["plugin_castcontrol_HTTP_Port"];
        $api['cmd']       = 'unsuspend';
        $api['port']      = $portbase;
        $api['start']     = true;

        if($response = api($api)){
            // Here we have successfully connected and authenticated to the cast-control API
            list($status, $msg) = explode(",", $response);

            // Check for anything other than success
            if($status != 'Success'){
                $errormsg = "There was a problem unsuspending the server:: {$response}";
                CE_Lib::log(4, "plugin_castcontrol::unsuspend::error: ".$errormsg);
                throw new CE_Exception($errormsg);
            }
        }elseif($this->apierror == 'auth_failed'){
            $errormsg = "Cast-Control API Authentication error, please check API Key";
            CE_Lib::log(4, "plugin_castcontrol::unsuspend::error: ".$errormsg);
            throw new CE_Exception($errormsg);
        }else{
            // API FAILED
            // Ussually either no connection has been established
            // or  the API key is incorrect.
            $errormsg = "Could not communicate with the Cast-Control API";
            CE_Lib::log(4, "plugin_castcontrol::unsuspend::error: ".$errormsg);
            throw new CE_Exception($errormsg);
        }

        // Reach here, Success
        return;
    }

    function doCheckUserName($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        return $this->checkUserName($this->buildParams($userPackage));
    }

    function checkUserName($args)
    {
    }

    function getAvailableActions($userPackage)
    {
        $actions = array();

        $actions[] = 'Create';
        $actions[] = 'Suspend';
        $actions[] = 'UnSuspend';
        $actions[] = 'Delete';

        return $actions;
    }
}

/*  _---------------------------------------  */
function api($api)
{
    // Establish connection
    $scheme = (isset($api['http_ssl']) && $api['http_ssl']==1)?'ssl://':'';
    $connection = fsockopen($scheme.$api['http_host'], $api['http_port'], $errno, $errstr, 8);

    // If no connection then fail
    if(!$connection){
        $this->apierror = 'no_connection';
        return false;
    }else{
        $send = '';
        foreach($api as $option => $setting){
            if(strstr($option, 'http_')){
                continue;
            }
            if(is_array($setting)){
                $setting = serialize( $setting );
            }

            $send .= $option .'='. urlencode($setting) .'&';
        }

        $request = $send;

        $headers  = "POST ".  $api['http_path']  ."/castcontrol/api.php HTTP/1.0\r\n";
        $headers .= "Host: ". $api['http_host'] ."\r\n";
        $headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $headers .= "User-Agent: Cast-control (http://cast-control.net)\r\n";
        $headers .= "Content-Length: " . strlen($request) . "\r\n";
        $headers .= "Connection: close\r\n\r\n";
        $headers .= $request."\r\n";

        // Send headers to the open connection
        fputs( $connection, $headers);

        // Return feedback
        $res = '';
        while(!feof($connection)){
            $res .= fgets ($connection, 1024);
        }

        fclose($connection);

        // If gets to here, we are successfully verified
        if(strstr($res, "Error,Your API Key is not valid")){
            $this->apierror = 'auth_failed';
            return false;
        }

        return urldecode($res);
    }
}
?>
