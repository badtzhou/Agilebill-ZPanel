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
	
################################################################################
### Database Map for: Mantis 0.18.rc1
### Last Update: 12-11-2003
################################################################################

class map_MANTIS_18RC1
{

    ############################################################################
    ### Define the settings for this database map
    ############################################################################

    function map_MANTIS_18RC1 ()
    {
        $this->map =
            Array (
                'map'           => 'Mantis_18rc1',
                'db_type'       => 'mysql',
                'notes'         => 'This is for Mantis 0.18.rc1',
                'group_type'    => 'status',    // db, status, none


                ### Define the account mapping properties
                'account_map_field' => '_user_table',
                'account_status_field' => 'access_level',
                'account_default_status' => '25',

                'account_sync_field'=>
                    Array
                    (
                        'add'       => 'username,email,password,enabled,protected,access_level',
                        'edit'      => 'username,email,password,enabled,protected,access_level',
                        'import'    => 'username,email,password,enabled,protected,access_level',
                        'export'    => 'username,email,password,enabled,protected,access_level',
                        'delete'    => '1'
                    ),

                'account_fields'    =>
                    Array
                    (
                        'id'        =>
                            Array
                            (
                                'map_field'      => 'id'
                            ),
                        'date_orig'     =>
                            Array
                            (
                                'map_field'      => false
                            ),

                        'date_last'     =>
                            Array
                            (
                                'map_field'      => false
                            ),

                        'date_expire'   =>
                            Array
                            (
                                'map_field'      => false
                            ),

                        'language_id'   =>
                            Array
                            (
                                'map_field'      => false
                            ),

                        'country_id'    =>
                            Array
                            (
                                'map_field'      => false
                            ),

                        'username'      =>
                            Array
                            (
                                'map_field'      => 'username'
                            ),

                        'password'      =>
                            Array
                            (
                                'map_field'      => 'password'
                            ),

                        'misc'          =>
                            Array
                            (
                                'map_field'      => false
                            ),

                        'first_name'    =>
                            Array
                            (
                                'map_field'      => false,
                            ),

                        'last_name'     =>
                            Array
                            (
                                'map_field'      => false,
                            ),

                        'middle_name'   =>
                            Array
                            (
                                'map_field'      => false
                            ),

                        'company'       =>
                            Array
                            (
                                'map_field'      => false
                            ),

                        'status'         =>
                            Array
                            (
                                'map_field'      => false
                            ),

                        'email'         =>
                            Array
                            (
                                'map_field'      => 'email'
                            ),

                        'email_type'    =>
                            Array
                            (
                                'map_field'      => false
                            )
                        ),

                    ### Define any extra fields for account table in the target db:
                    'extra_field' =>
                        Array (
                                Array
                                (
                                    'name'  => 'enabled',
                                    'value' => '1',
                                    'add'   => 1,
                                    'edit'  => 0
                                ),

                                Array
                                (
                                    'name'  => 'protected',
                                    'value' => '0',
                                    'add'   => 1,
                                    'edit'  => 0
                                ),

                                Array
                                (
                                    'name'  => 'access_level',
                                    'value' => '25',
                                    'add'   => 1,
                                    'edit'  => 0
                                ),

                                Array
                                (
                                    'name'  => 'cookie_string',
                                    'value' => 'random|64',
                                    'add'   => 1,
                                    'edit'  => 0
                                )
                        	)
                    );

        }

        ########################################################################
        ### Syncronize all accounts & groups
        ########################################################################

        function sync($id, $file)
        {
            $db_map = new db_mapping;
            $this   = $db_map->MAP_sync ($id, $file, $this);
        }

        ########################################################################
        ### Create a new account in the target DB
        ########################################################################

        function account_add($account_id)
        {
            $db_map = new db_mapping;
            $db_map->MAP_account_add ($account_id, $this);

            ### Sync the groups for this account:
            if( $this->map['group_type'] != 'none' &&
                $this->map['group_type'] != 'add_remove' )
            $this->account_group_sync( $account_id );
        }

        ########################################################################
        ### Edit an existing account in the target DB
        ########################################################################

        function account_edit($account_id, $old_username)
        {
            $db_map = new db_mapping;
            $db_map->MAP_account_edit ($account_id, $old_username, $this);

            ### Update the groups in the remote db
            if( $this->map['group_type'] != 'none' &&
                $this->map['group_type'] != 'add_remove' )
            $this->account_group_sync($account_id);
        }

        ########################################################################
        ### Delete an existing account from the target DB
        ########################################################################

        function account_delete($account_id, $username)
        {
            $db_map = new db_mapping;
            $db_map->MAP_account_delete ($account_id, $username, $this);
        }

        ########################################################################
        ### Export / Update all accounts / groups to the target DB
        ########################################################################

        function account_import($remote_account_id)
        {
            $db_map = new db_mapping;
            $db_map->MAP_account_import ($remote_account_id, $this);
        }

        ########################################################################
        ### Create the cookie/session for login sync
        ########################################################################

        function login($account_id, $cookie_name)
        {
        	### Get the username login/account creation:
        	global $VAR;
        	@$username = $VAR['_username'];        	
        	if(empty($username))
        		@$username = $VAR['account_username'];

			### Get the cookie-string value from Mantis:
			$dbm   = new db_mapping;
			$db    = $dbm->DB_connect(false, $this->map['map']);
			eval ( '@$db_prefix = DB2_PREFIX'. strtoupper($this->map['map']) .';' );
			$sql = 'SELECT cookie_string FROM ' . $db_prefix . '_user_table WHERE
	        			username ='.$db->qstr($username);
			$result = $db->Execute($sql);
			
			### error reporting:
			if ($result === false)
			{
				global $C_debug;
				$C_debug->error('db_mapping.inc.php','Map_account_logout_add_account_session', $db->ErrorMsg());
				$smarty->assign('db_mapping_result', $db->ErrorMsg());
				return;
			}

			# Create/Update the cookie 
			$string = $result->fields['cookie_string'];
			return setcookie($cookie_name, $string, 0, '/');  
        }

        ########################################################################
        ### Delete the cookie/session on account logout
        ########################################################################

        function logout($account_id, $cookie_name)
        {
        	return setcookie( $cookie_name, '', -1, '/');
        }

        ########################################################################
        ### Syncronize the groups for a specific account in the remote DB
        ########################################################################

        function account_group_sync($account_id)
        {
            if ( $this->map['group_type'] == 'db')
            {
                $db_map = new db_mapping;
                $db_map->MAP_account_group_sync_db ($account_id, $this);
            }
            elseif  ( $this->map['group_type'] == 'status')
            {
                $db_map = new db_mapping;
                $db_map->MAP_account_group_sync_status ($account_id, $this);
            }
            else
            {
                return false;
            }
        }
}
?>