<?php
$php_minimum = '5.1'; // User's PHP must be equal or newer to this version.

if ( version_compare( PHP_VERSION, $php_minimum ) < 0 ) {
	die( 'ERROR #9013. See <a href="http://ithemes.com/codex/page/BackupBuddy:_Error_Codes#9013">this codex page for details</a>. Sorry! PHP version ' . $php_minimum . ' or newer is required for BackupBuddy to properly run. You are running PHP version ' . PHP_VERSION . '.' );
}

$abspath = dirname( __FILE__ ) . '/';
if ( '//' == $abspath ) { // User path caged to appear at /. ABSPATH normally would not have trailing slash so change this to empty string ''.
	$abspath = '/';
}
define( 'ABSPATH', $abspath );
define( 'PB_BB_VERSION', '#VERSION#' );
define( 'PB_PASSWORD', '#PASSWORD#' );


@date_default_timezone_set( @date_default_timezone_get() ); // Prevents date() from throwing a warning if the default timezone has not been set. Run prior to any file_exists()!


// Try to put an index.htm file in place during import to help prevent against file browsing. Only do if not a defined step OR a non-numeric defined step OR a defined numeric step < 5.
if (
	( ! isset( $_GET['step'] ) )
	||
	( isset( $_GET['step'] ) && !is_numeric( $_GET['step'] ) )
	||
	( isset( $_GET['step'] ) && is_numeric( $_GET['step'] ) && ( $_GET['step'] < 5 ) )
	)
{
	if ( ! file_exists( ABSPATH . 'index.htm' ) ) {
		@file_put_contents( ABSPATH . 'index.htm', '<html></html>' );
	}
}


// Unpack importbuddy files into importbuddy directory.
if ( !file_exists( ABSPATH . 'importbuddy' ) || ( ( count( $_GET ) == 0 ) && ( count( $_POST ) == 0 ) ) ) {
	$unpack_importbuddy = true;
	if ( file_exists( ABSPATH . 'importbuddy' ) ) { // ImportBuddy directory already exists. We may need to re-unpack it if this file has been updated since.
		$signature = @file_get_contents( ABSPATH . 'importbuddy/_signature.php' );
		$signature = trim( str_replace( '<?php die(); ?>', '', $signature ) );
		if ( md5( PB_BB_VERSION . PB_PASSWORD ) != $signature ) { // Signature mismatch. We will need to delete and unpack again to update.
			echo '<!-- unlinking existing importbuddy directory. -->';
			recursive_unlink( ABSPATH . 'importbuddy' );
		} else {
			$unpack_importbuddy = false;
		}
	}
	if ( true === $unpack_importbuddy ) {
		unpack_importbuddy();
		@file_put_contents( ABSPATH . 'importbuddy/_signature.php', '<?php die(); ?>' . md5( PB_BB_VERSION . PB_PASSWORD ) ); // Create a hash of this ImportBuddy version & password. On accessing importbuddy.php's authentication page all importbuddy files will be freshly unpacked if the importbuddy.php version and/or password mismatches to allow users to just replace importbuddy.php to upgrade ImportBuddy or password.
	}
}



// Fake $wpdb object for holding null db identifier.
class pb_backupbuddy_wpdb_fake_object {
	public $dbh = NULL;
}
global $wpdb;
$wpdb = new pb_backupbuddy_wpdb_fake_object();



if ( isset( $_GET['api'] ) && ( $_GET['api'] != '' ) ) { // API ACCESS
	if ( $_GET['api'] == 'ping' ) {
		die( 'pong' );
	} else {
		die( 'Unknown API access action.' );
	}
} else { // NORMAL ACCESS.
	if ( !file_exists( ABSPATH . 'importbuddy/init.php' ) ) {
		die( 'Error: Unable to find file `' . ABSPATH . 'importbuddy/init.php`. Make sure that you downloaded this script from within BackupBuddy. Copying importbuddy files from inside the plugin directory is not sufficient as many file additions are made on demand.' );
	} else {
		require_once( ABSPATH . 'importbuddy/init.php' );
	}
}



function recursive_unlink( $path ) {
  return is_file($path)?
    @unlink($path):
array_map('recursive_unlink',glob($path.'/*'))==@rmdir($path);
}



/**
*	unpack_importbuddy()
*
*	Unpacks required files encoded in importbuddy.php into stand-alone files.
*
*	@return		null
*/
function unpack_importbuddy() {
	if ( !is_writable( ABSPATH ) ) {
		echo 'Error #224834. This directory is not write enabled. Please verify write permissions to continue.';
		die();
	} else {
		$unpack_file = '';
		
		// Make sure the file is complete and contains all the packed data to the end.
		if ( false === strpos( file_get_contents( ABSPATH . 'importbuddy.php' ), '###PACKDATA' . ',END' ) ) { // Concat here so we don't false positive on this line when searching.
			die( 'ERROR: It appears your importbuddy.php file is incomplete.  It may have not finished downloading or uploading completely.  Please try re-downloading the script from within BackupBuddy in WordPress (do not just copy the file from the plugin directory) and re-uploading it.' );
		}
		
		$handle = @fopen( ABSPATH . 'importbuddy.php', 'r' );
		if ( $handle ) {
			while ( ( $buffer = fgets( $handle ) ) !== false ) {
				if ( substr( $buffer, 0, 11 ) == '###PACKDATA' ) {
					$packdata_commands = explode( ',', trim( $buffer ) );
					array_shift( $packdata_commands );
					
					if ( $packdata_commands[0] == 'BEGIN' ) {
						// Start packed data.
					} elseif ( $packdata_commands[0] == 'FILE_START' ) {
						$unpack_file = $packdata_commands[2];
					} elseif ( $packdata_commands[0] == 'FILE_END' ) {
						$unpack_file = '';
					} elseif ( $packdata_commands[0] == 'END' ) {
						return;
					}
				} else {
					if ( $unpack_file != '' ) {
						if ( !is_dir( dirname( ABSPATH . $unpack_file ) ) ) {
							$mkdir_result = mkdir( dirname( ABSPATH . $unpack_file ), 0777, true ); // second param makes recursive.
							if ( $mkdir_result === false ) {
								echo 'Error #54455. Unable to mkdir `' . dirname( ABSPATH . $unpack_file ) . '`<br>';
							}
						}
						$fileput_result = file_put_contents( ABSPATH . $unpack_file, base64_decode( $buffer ) );
						if ( $fileput_result === false ) {
							echo 'Error #65656. Unable to put file contents to `' . ABSPATH . $unpack_file . '`.<br>';
						}
					}
				}
			}
			if ( !feof( $handle ) ) {
				echo "Error: unexpected fgets() fail.<br>";
			}
			fclose( $handle );
		} else {
			echo 'ERROR #54455: Unable to open importbuddy.php file for reading in packaged data.<br>';
		}
	}
}
die();
?>