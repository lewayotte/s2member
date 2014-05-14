<?php
/**
 * Access CAP Times.
 *
 * Copyright: © 2009-2011
 * {@link http://www.websharks-inc.com/ WebSharks, Inc.}
 * (coded in the USA)
 *
 * Released under the terms of the GNU General Public License.
 * You should have received a copy of the GNU General Public License,
 * along with this software. In the main directory, see: /licensing/
 * If not, see: {@link http://www.gnu.org/licenses/}.
 *
 * @package s2Member\CCAPS
 * @since 140514
 */
if(realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME']))
	exit ('Do not access this file directly.');

if(!class_exists('c_ws_plugin__s2member_access_cap_times'))
{
	/**
	 * Access CAP Times.
	 *
	 * @package s2Member\CCAPS
	 * @since 140514
	 */
	class c_ws_plugin__s2member_access_cap_times
	{
		/**
		 * @var array Previous array of user CAPS.
		 *    For internal use only.
		 */
		protected static $prev_caps_by_user = array();

		/**
		 * Get user caps before udpate.
		 *
		 * @package s2Member\CCAPS
		 * @since 140514
		 *
		 * @attaches-to ``add_action('update_user_meta')``
		 *
		 * @param integer $meta_id Meta row ID in database.
		 * @param integer $object_id User ID.
		 * @param string  $meta_key Meta key.
		 * @param mixed   $meta_value Meta value.
		 */
		public static function get_user_caps_before_update($meta_id, $object_id, $meta_key, $meta_value)
		{
			$wpdb = $GLOBALS['wpdb'];
			/** @var $wpdb \wpdb For IDEs. */

			if(strpos($meta_key, 'capabilities') === FALSE || $meta_key !== $wpdb->get_blog_prefix().'capabilities')
				return; // Not updating caps.

			$user_id = $object_id;
			$user    = new WP_User($user_id);
			if(!$user->ID || !$user->exists())
				return; // Not a valid user.

			self::$prev_caps_by_user[$user_id] = $user->caps;
		}

		/**
		 * Logs access capability times.
		 *
		 * @package s2Member\CCAPS
		 * @since 140514
		 *
		 * @attaches-to ``add_action('updated_user_meta')``
		 *
		 * @param integer $meta_id Meta row ID in database.
		 * @param integer $object_id User ID.
		 * @param string  $meta_key Meta key.
		 * @param mixed   $meta_value Meta value.
		 */
		public static function log_access_cap_time($meta_id, $object_id, $meta_key, $meta_value)
		{
			$wpdb = $GLOBALS['wpdb'];
			/** @var $wpdb \wpdb For IDEs. */

			if(strpos($meta_key, 'capabilities') === FALSE || $meta_key !== $wpdb->get_blog_prefix().'capabilities')
				return; // Not updating caps.

			$user_id = $object_id;
			$user    = new WP_User($user_id);
			if(!$user->ID || !$user->exists())
				return; // Not a valid user.

			$caps['prev']            = !empty(self::$prev_caps_by_user[$user_id]) ? self::$prev_caps_by_user[$user_id] : array();
			self::$prev_caps_by_user = array();
			$caps['now']             = is_array($meta_value) ? $meta_value : array();
			$role_objects            = $GLOBALS['wp_roles']->role_objects;

			foreach($caps as &$_caps)
			{
				foreach(array_intersect($_caps, array_keys($role_objects)) as $_role)
					$_caps = array_merge($_caps, array_keys($role_objects[$_role]->capabilities));
				$_caps = array_unique($_caps);

				foreach($_caps as $_k => $_cap)
					if(strpos($_cap, 'access_s2member_') === 0)
						$_caps[$_k] = substr($_caps[$_k], 16);
					else
						unset($_caps[$_k]);
			}
			unset($_caps, $_role, $_k, $_cap);

			$ac_times = get_user_option('s2member_access_cap_times', $user_id);
			$time     = (float)time();

			foreach($caps['prev'] as $_cap_removed => $_enabled)
				if(!array_key_exists($_cap_removed, $caps['now']) || (!$caps['now'][$_cap_removed] && $_enabled))
					$ac_times[(string)($time += .0001)] = '-'.$_cap_removed;
			unset($_cap_removed, $_enabled);

			foreach($caps['now'] as $_cap_added => $_enabled)
				if($_enabled && (!array_key_exists($_cap_added, $caps['prev']) || !$caps['prev'][$_cap_added]))
					$ac_times[(string)($time += .0001)] = $_cap_added;
			unset($_cap_added, $_enabled);

			update_user_option($user_id, 's2member_access_cap_times', $ac_times);
		}

		/**
		 * Gets access capability times.
		 *
		 * @package s2Member\CCAPS
		 * @since 140514
		 *
		 * @param integer $user_id WP User ID.
		 * @param array   $access_caps Optional. If not passed, this returns all times for all caps.
		 *    If passed, please pass an array of specific access capabilities to get the times for.
		 *    If removal times are desired, you should add a `-` prefix.
		 *    e.g. `array('ccap_music','level2','-ccap_video')`
		 *
		 * @return array An array of all access capability times.
		 *    Keys are UTC timestamps (w/ microtime precision), values are the capabilities (including `-` prefixed removals).
		 *    e.g. `array('1234567890.0001' => 'ccap_music', '1234567890.0002' => 'level2', '1234567890.0003' => '-ccap_video')`
		 */
		public static function get_access_cap_times($user_id, $access_caps = array())
		{
			if(($user_id = (integer)$user_id))
			{
				$ac_times = get_user_option('s2member_access_cap_times', $user_id);

				if(!is_array($ac_times))
					$ac_times = array();

				else if($access_caps)
					$ac_times = array_intersect($ac_times, (array)$access_caps);

				ksort($ac_times, SORT_NUMERIC);
			}
			else $ac_times = array();

			return apply_filters('ws_plugin__s2member_get_access_cap_times', $ac_times, get_defined_vars());
		}
	}
}