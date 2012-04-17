<?php
	
/**
 * AgileBill - Open Billing Software
 *
 * This body of work is free software; you can redistribute it and/or
 * modify it under the terms of the Open AgileBill License
 * License as published at http://www.agileco.com/agilebill/license1-4.txt
 * 
 * For questions, help, comments, discussion, etc., please join the
 * Agileco community forums at http://forum.agileco.com/ 
 *
 * @link http://www.agileco.com/
 * @copyright 2004-2008 Agileco, LLC.
 * @license http://www.agileco.com/agilebill/license1-4.txt
 * @author Tony Landis <tony@agileco.com> 
 * @package AgileBill
 * @version 1.4.93
 */
	
# Remote update retrieval
if(empty($VAR))
{
    include_once('../../config.inc.php');
    require_once(PATH_ADODB  . 'adodb.inc.php');
    require_once(PATH_CORE   . 'database.inc.php');
    require_once(PATH_CORE   . 'setup.inc.php');
    require_once(PATH_CORE   . 'vars.inc.php');
    require_once(PATH_CORE   . 'xml.inc.php');

    $C_debug    = new CORE_debugger;
    $C_vars     = new CORE_vars;
    $VAR        = $C_vars->f;
    $C_db       = &DB();
    $C_setup    = new CORE_setup;

    $plg = new plgn_prov_WEBMIN_PRO();
    $plg->p_all($VAR);
}


# Main Class
class plgn_prov_WEBMIN_PRO
{
    function plgn_prov_WEBMIN_PRO()
    {
        $this->name             = 'WEBMIN_PRO';
        $this->task_based       = true;
        $this->remote_based     = false;
        $this->fallback_manual  = true;
        $this->nl 				= '; '; 
        $this->options 			= Array('dir','unix','dns','web','webalizer','logrotate','mysql','status','webmin');
    }

    # add new service
    function p_new()
    {
        # get the common server class and set login details
        include_once(PATH_MODULES.'host_server/host_server.inc.php');
        $host = new host_server;
        if($this->service['host_username'] == '' || $this->service['host_password'] == '')
        {
            $this->login = $host->generate_login($this->service, $this->account, 4, 4, false);
        } else {
            $this->login['username'] = $this->service['host_username'];
            $this->login['password'] = $this->service['host_password'];
        }

        $cmd =  "./create-domain.pl "   .
	        " --template "          . $this->plugin_data['template'] .
	        " --domain "  			. $this->service['domain_name']. "." . $this->service['domain_tld'] .
	        " --user "        		. $this->login['username'] . 
	        " --pass "              . $this->login['password'] .
	        " --email "             . $this->account['email'];
	    
        ### Get the op tions
        foreach( $this->options as $s )  
        	if(empty($this->plugin_data["$s"]))   
        		$cmd .= " --{$s}";  
                 
        # get ip address
        if ($this->plugin_data['ipinfo_namebased'] == '1')
            $this->ip = $host->useipaddress($this->service, $this->server);
        else
            $this->ip = $this->server['name_based_ip'];

        # ip based?
        if ($this->plugin_data['ipinfo_namebased'] == '0' && $this->ip != false) {
			$cmd .= " --ip " . $this->ip;
        } 
               
        return $cmd; 
    }

    # edit service
    function p_edit()
    {
        // go through each option...
        
        $disable = false;
        $enable = false;
        $cmd = '';
        
        foreach( $this->options as $s ) {
        	if(empty($this->plugin_data["$s"]))  
        		$disable .= " --{$s}"; 
        	else 
        		$enable .= " --{$s}";
        }
        
        if($disable)  
        	$cmd .=  "./disable-feature.pl --domain {$this->service['domain_name']}.{$this->service['domain_tld']} {$disable}" . $this->nl;
          
		if($enable)   
			$cmd .=  "./enable-feature.pl --domain {$this->service['domain_name']}.{$this->service['domain_tld']} {$enable}" . $this->nl;        	
			
        $cmd .=  "./modify-domain.pl "   .
	        " --domain "  			. $this->service['domain_name']. "." . $this->service['domain_tld'] .
	        " --user "        		. $this->service['host_username'] . 
	        " --pass "              . $this->service['host_password'] .
	        " --email "             . $this->account['email'];        
                 
        return $cmd;
    }

    # activate service
    function p_inactive()
    {
        return "./disable-domain.pl --domain " . strtolower($this->service['domain_name']. "." . $this->service['domain_tld']);   
    }

    # deactivate service
    function p_active()
    {
        return "./enable-domain.pl --domain " . strtolower($this->service['domain_name']. "." . $this->service['domain_tld']);  
    }

    # delete service
    function p_delete()
    {
		# recycle the IP if ip_based: 
        if ($this->plugin_data['ipinfo_namebased'] == '0') {			 
		    include_once(PATH_MODULES.'host_server/host_server.inc.php');
		    $host = new host_server;					
			$this->ip = $host->unuseipaddress($this->server, $this->service['host_ip']);	
        }  
        	
        return "./delete-domain.pl --domain " . strtolower($this->service['domain_name']. "." . $this->service['domain_tld']); 
    }


    # construct echo all updates
    function p_all($VAR)
    {
        global $C_debug;

        # Error checking
        if( empty($VAR['key']) ) {
            echo 'ECHO Server Key Missing!';
            exit;
        }

        # Get the server details
        $db     = &DB();
        $sql    = 'SELECT * FROM ' . AGILE_DB_PREFIX . 'host_server WHERE
                   keycode      =  ' . $db->qstr( $VAR['key'] ) . ' AND
                   site_id      =  ' . $db->qstr(DEFAULT_SITE);
        $rs = $db->Execute($sql);
        if (@$rs->RecordCount() <= 0) {
            echo 'ECHO Server ID does not exist';
            exit;
        } else {
            $this->server = $rs->fields;
            @$this->server_cnfg = unserialize($rs->fields['provision_plugin_data']);
        }

        # Check that this server is using this plugin
        if($this->server['provision_plugin'] != @$this->name)
        {
            echo 'ECHO Wrong plugin for this server ';
            exit;
        }

        # Check the auth for this post
        if (!empty($VAR['key']) && $VAR['key'] == $this->server['keycode'])
        {
            # authorized!
        } else {
            echo 'ECHO Unauthorized Key! ';
            exit;
        }

        # Get the service details
        $db     = &DB();
        $sql    = 'SELECT * FROM ' . AGILE_DB_PREFIX . 'service WHERE
                   type             =  ' . $db->qstr( 'host' ) . ' OR
                   type             =  ' . $db->qstr( 'host_group' ) . ' AND
                   host_server_id   =  ' . $db->qstr( $this->server['id'] ) . ' AND
                   queue           !=  ' . $db->qstr( 'none' ) . ' AND
                   site_id          =  ' . $db->qstr(DEFAULT_SITE);
        $rs = $db->Execute($sql);
        if($rs->RecordCount() == 0) {
            echo 'ECHO No Records To Add/Update ';
            exit;
        }
        $i=0;
        while(!$rs->EOF)
        {

            # set details
            $this->service = $rs->fields;
            $this->service_id = $rs->fields['id'];

            # Get the hosting plan plugin data for this product
            $this->plugin_data = unserialize($this->service['host_provision_plugin_data']);

            # Get the account details
            $sql    = 'SELECT * FROM ' . AGILE_DB_PREFIX . 'account WHERE
                       id           =  ' . $db->qstr( $this->service['account_id'] ) . ' AND
                       site_id      =  ' . $db->qstr(DEFAULT_SITE);
            $acct = $db->Execute($sql);
            $this->account = $acct->fields;

            # determine the correct action
            switch ($this->service['queue'])
            {
                # new
                case 'new':
                    $result = $this->p_new();
                    # send the user the details
                    include_once(PATH_MODULES.'email_template/email_template.inc.php');
                    $email = new email_template;
                    $email->send('host_new_user', $this->account['id'], $rs->fields['id'], '', '');
                break;

                # active
                case 'active':
                    $result = $this->p_active();
                    # send the user the details
                    include_once(PATH_MODULES.'email_template/email_template.inc.php');
                    $email = new email_template;
                    $email->send('host_edit_user', $this->account['id'], $rs->fields['id'], '', '');
                break;

                # inactive
                case 'inactive':
                    $result = $this->p_inactive();
                break;

                # edit
                case 'edit':
                    $result = $this->p_edit();
               break;

               # delete
               case 'delete':
                    $result = $this->p_delete();
               break;
            }

            # formatting
            if (@$result != false)
            {
                if($i  > 0)
                echo $this->nl;
                echo $result;
                $i++;
            }

            # check if we need to update the status after an edit
            if ($this->service['queue'] == 'edit') {
                if ($this->service['active'] == 1  )
                    echo $this->nl . $this->p_active();
                else
                    echo $this->nl . $this->p_inactive();
            }

            # update service record
            if(@$result != false)
            {
				if($this->service['queue'] == "delete")
				{	
					# delete
	                $sql    = 'DELETE FROM ' . AGILE_DB_PREFIX . 'service WHERE
	                           id           =  ' . $db->qstr( $rs->fields['id'] ) . ' AND
	                           site_id      =  ' . $db->qstr(DEFAULT_SITE);
	                $upd = $db->Execute($sql);				
				} else { 
		            # update
					$db     	= &DB();
		            $sql   = 'UPDATE ' . AGILE_DB_PREFIX . 'service SET
		                        queue        =  ' . $db->qstr( 'none' ) . ',
		                        date_last    =  ' . $db->qstr( time() ) . '
		                        WHERE
		                        id           =  ' . $db->qstr( $this->service_id ) . ' AND
		                        site_id      =  ' . $db->qstr(DEFAULT_SITE);
		            $upd = $db->Execute($sql);
				} 
            } else {
                # error log
                $C_debug->error($this->name.'php', $this->service['queue'], @$result);
            }
            $rs->MoveNext();
        }
    }
}
?>