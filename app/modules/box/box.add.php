<?php

/**
 * LICENSE:
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
 * @package		Bright Game Panel V2
 * @version		0.1
 * @category	Systems Administration
 * @author		warhawk3407 <warhawk3407@gmail.com> @NOSPAM
 * @copyright	Copyleft 2015, Nikita Rousseau
 * @license		GNU General Public License version 3.0 (GPLv3)
 * @link		http://www.bgpanel.net/
 */

/**
 * Load Plugin
 */

require( MODS_DIR . '/' . basename(__DIR__) . '/box.class.php' );

$module = new BGP_Module_Box_Add( 'add' );

/**
 * Call GUI Builder
 */
$gui = new Core_GUI( $module );

/**
 * Javascript Generator
 */
$js = new Core_GUI_JS( $module );

/**
 * Build Page Header
 */
$gui->getHeader();

// ===========================================================================================================

// Os list as json

$dbh = Core_Database_Service::getDBH(); // Get Database Handle

$sth = $dbh->prepare("
	SELECT *
	FROM os
	;");

$sth->execute();

$os = $sth->fetchAll( PDO::FETCH_ASSOC );

$osMap = '[';
foreach ($os as $key => $value) {
	$osMap .= '{' . 'value: "' . $value['os_id'] . '", name: "' . $value['operating_system'] . '"}' . ',';
}
$osMap = substr($osMap, 0, -1);
$osMap .= ']';

// RSA Keys list as json

$handle = opendir( RSA_KEYS_DIR );

$rsaKeysMap = '[';

if ($handle) {

	// Foreach modules
	while (false !== ($entry = readdir($handle))) {

		// Dump specific directories
		if ($entry != "." && $entry != ".." && $entry != ".htaccess" && $entry != "bgp_rsa.pub")
		{
			$rsaKeysMap .=  '{' . 'value: "' . $entry . '", name: "' . $entry . '"}' . ',';
		}
	}

	closedir($handle);
}
unset($handle, $entry);

$rsaKeysMap = substr($rsaKeysMap, 0, -1);
$rsaKeysMap .= ']';

/**
 * PAGE BODY
 */
//------------------------------------------------------------------------------------------------------------+
?>
					<!-- CONTENTS -->
					<div class="row">
						<div class="col-md-8 col-md-offset-2">
							<div class="panel panel-default">
								<div class="panel-heading">
									<h3 class="panel-title"><?php echo T_('Add A New Box'); ?></h3>
								</div>

								<div class="panel-body">
									<form name="thisForm" ng-submit="onSubmit(thisForm)">
										<div sf-schema="schema" sf-form="form" sf-model="model"></div>

										<div class="text-center">
											<button class="btn btn-primary" type="submit" ng-disabled="thisForm.$invalid && !thisForm.$submitted"><?php echo T_('Add'); ?></button>
											<button class="btn btn-default" type="reset"><?php echo T_('Cancel Changes'); ?></button>
										</div>
									</form>
								</div>
							</div>
						</div>
					</div>
					<!-- END: CONTENTS -->

					<!-- SCRIPT -->
<?php

/**
 * Generate AngularJS Code
 *
 * @param 	String 	$task
 * @param 	String 	$schema
 * @param 	String 	$form
 * @param 	String 	$model
 * @param 	String 	$redirect
 */

// Schema Definition
$schema = "
{
	type: 'object',
	properties: {
		name: {
			title: '" . T_('Remote Machine Name') . "',
			type: 'string'
		},
		os: {
			title: '" . T_('Operating System') . "',
			type: 'string'
		},
		com: {
			title: '" . T_('Communications Protocol') . "',
			type: 'string'
		},
		ip: {
			title: '" . T_('IP Address') . "',
			type: 'string'
		},
		port: {
			title: '" . T_('Port') . "',
			type: 'string'
		},
		login: {
			title: '" . T_('Login') . "',
			type: 'string'
		},
		password: {
			title: '" . T_('Password') . "',
			type: 'string'
		},
		privatekey: {
			title: '" . T_('RSA Private Key') . "',
			type: 'string'
		},
		remoteUserHome: {
			title: '" . T_('Remote User Home Path') . "',
			type: 'string'
		},
		steamcmd: {
			title: '" . T_('SteamCMD Binary Path') . "',
			type: 'string'
		},
		notes: {
			title: '" . T_('Notes') . "',
			type: 'string'
		}
	},
	'required': [
		'name',
		'ip',
		'com',
		'login',
		'privatekey',
		'port'
	]
}";

// Form Definition
$form = "
[
	{
		key: 'name',
		type: 'text',
		fieldAddonLeft: '<span class=\"glyphicon glyphicon-font\"></span>'
	},
	{
		key: 'os',
		type: 'select',
		titleMap: " . $osMap . "
	},
	{
		key: 'com',
		type: 'text',
		readonly: true,
		disableSuccessState: true,
	},
	{
		key: 'ip',
		type: 'text',
		fieldAddonLeft: '<span class=\"glyphicon glyphicon-globe\"></span>'
	},
	{
		key: 'port',
		type: 'text',
		placeholder: '22',
		fieldAddonLeft: '<span class=\"glyphicon glyphicon-log-in\"></span>'
	},
	{
		key: 'login',
		type: 'text',
		fieldAddonLeft: '<span class=\"glyphicon glyphicon-user\"></span>'
	},
	{
		key: 'password',
		type: 'password',
		fieldAddonLeft: '<span class=\"glyphicon glyphicon-lock\"></span>',
		description: '" . T_('Leave this field empty if you are using an authorized RSA key.') . "'
	},
	{
		key: 'privatekey',
		type: 'select',
		description: '" . T_('RSA Keys are stored in') . ' ' . htmlspecialchars(RSA_KEYS_DIR, ENT_QUOTES ) . "',
		titleMap: " . $rsaKeysMap . "
	},
	{
		key: 'remoteUserHome',
		type: 'text',
		placeholder: '/home/{user}/',
		fieldAddonLeft: 'Optional',
		disableSuccessState: true
	},
	{
		key: 'steamcmd',
		type: 'text',
		placeholder: '/home/{user}/steamcmd.sh',
		fieldAddonLeft: 'Optional',
		disableSuccessState: true
	},
	{
		key: 'notes',
		type: 'textarea',
		disableSuccessState: true
	},
]";

$model = json_encode( array(
		'os'			=> '1',
		'com' 			=> 'ssh2',
		'privatekey' 	=> 'bgp_rsa'
	), JSON_FORCE_OBJECT );

$js->getAngularCode( 'postBox', $schema, $form, $model, './box' );

?>
					<!-- END: SCRIPT -->

<?php
//------------------------------------------------------------------------------------------------------------+
/**
 * END: PAGE BODY
 */

/**
 * Build Page Footer
 */
$gui->getFooter();

?>
