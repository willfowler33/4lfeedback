<?php
/**
 * Plugin Name: 4L Feedback System
 * Plugin URI:  https://example.com/4lfeedback
 * Description: A structured 4Ls retrospective feedback system (Loved, Loathed, Longed for, Learned). Provides shortcodes for the feedback form, admin responses, and feedback breadcrumbs. Includes email notifications and an admin dashboard for managing submissions and responses.
 * Version:     1.1.0
 * Author:      The Concrete Protector
 * Author URI:  https://theconcreteprotector.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: 4lfeedback
 *
 * @package FourLFeedback
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FOURL_FEEDBACK_VERSION', '1.1.0' );
define( 'FOURL_FEEDBACK_FILE', __FILE__ );
define( 'FOURL_FEEDBACK_DIR', plugin_dir_path( __FILE__ ) );
define( 'FOURL_FEEDBACK_URL', plugin_dir_url( __FILE__ ) );
define( 'FOURL_FEEDBACK_BASENAME', plugin_basename( __FILE__ ) );

require_once FOURL_FEEDBACK_DIR . 'includes/class-fourl-feedback-db.php';
require_once FOURL_FEEDBACK_DIR . 'includes/class-fourl-feedback-shortcodes.php';
require_once FOURL_FEEDBACK_DIR . 'includes/class-fourl-feedback-ajax.php';
require_once FOURL_FEEDBACK_DIR . 'includes/class-fourl-feedback-admin.php';
require_once FOURL_FEEDBACK_DIR . 'includes/class-fourl-feedback-plugin.php';

register_activation_hook( __FILE__, array( 'FourL_Feedback_DB', 'install' ) );
register_uninstall_hook( __FILE__, array( 'FourL_Feedback_DB', 'uninstall' ) );

add_action( 'plugins_loaded', array( 'FourL_Feedback_Plugin', 'instance' ) );
