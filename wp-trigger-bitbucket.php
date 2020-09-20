<?php

/**
 * @package WPTriggerBitbucket
 */
/*
Plugin Name: WP Trigger Bitbucket
Plugin URI: https://github.com/gglukmann/wp-trigger-bitbucket
Description: Trigger Bitbucket pipeline from a widget on dashboard.
Version: 1.0.0
Author: Gert Glükmann
Author URI: https://github.com/gglukmann
License: GNU General Public License v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text-Domain: wp-trigger-bitbucket
 */

if (!defined('ABSPATH')) {
  die;
}

class WPTriggerBitbucket
{
  function __construct()
  {
    add_action('admin_init', [$this, 'generalSettingsSection']);
    // add_action('save_post', [$this, 'runHook'], 10, 3);
    add_action('wp_dashboard_setup', [$this, 'buildDashboardWidget']);

    add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
    add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
    add_action('wp_ajax_wp_trigger_bitbucket_deployments_manual_trigger', [$this, 'runHook']);
  }

  public function activate()
  {
    flush_rewrite_rules();
    $this->general_settings_section();
  }

  public function deactivate()
  {
    flush_rewrite_rules();
  }

  public static function enqueueScripts()
  {
    wp_enqueue_script(
      'wp-trigger-bitbucket-deployments-widget',
      plugins_url('/script.js', __FILE__),
      ['jquery']
    );

    $button_nonce = wp_create_nonce('wp-trigger-bitbucket-deployments-button-nonce');

    wp_localize_script('wp-trigger-bitbucket-deployments-widget', 'wpjd', [
      'ajaxurl' => admin_url('admin-ajax.php'),
      'deployment_button_nonce' => $button_nonce,
    ]);
  }

  function runHook($post_id)
  {
    // if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    // if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    check_ajax_referer('wp-trigger-bitbucket-deployments-button-nonce', 'security');

    $username = get_option('bb_option_username');
    $password = get_option('bb_option_password');
    $repo = get_option('bb_option_repo');

    if ($username && $password && $repo) {
      $url = 'https://api.bitbucket.org/2.0/repositories/' . $username . '/' . $repo . '/pipelines/';
      $args = array(
        'method'  => 'POST',
        'body'    => json_encode(array(
          'target' => array(
            'ref_type' => 'branch',
            'type' => 'pipeline_ref_target',
            'ref_name' => 'master'
          )
        )),
        'headers' => array(
          'Content-Type' => 'application/json',
          'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
        ),
      );

      wp_remote_post($url, $args);
    }
  }

  function generalSettingsSection()
  {
    add_settings_section(
      'bb_general_settings_section',
      'WP Trigger Bitbucket Settings',
      [$this, 'mySectionOptionsCallback'],
      'general'
    );
    add_settings_field(
      'bb_option_username',
      'Bitbucket Username',
      [$this, 'myTextboxCallback'],
      'general',
      'bb_general_settings_section',
      ['bb_option_username']
    );
    add_settings_field(
      'bb_option_password',
      'Bitbucket Password',
      [$this, 'myPasswordCallback'],
      'general',
      'bb_general_settings_section',
      ['bb_option_password']
    );
    add_settings_field(
      'bb_option_repo',
      'Repository Name',
      [$this, 'myTextboxCallback'],
      'general',
      'bb_general_settings_section',
      ['bb_option_repo']
    );

    register_setting('general', 'bb_option_username', 'esc_attr');
    register_setting('general', 'bb_option_password', 'esc_attr');
    register_setting('general', 'bb_option_repo', 'esc_attr');
  }

  function mySectionOptionsCallback()
  {
    echo '<p>Add bitbucket username, password and repository name</p>';
  }

  function myTextboxCallback($args)
  {
    $option = get_option($args[0]);
    echo '<input type="text" id="' . $args[0] . '" name="' . $args[0] . '" value="' . $option . '" />';
  }

  function myPasswordCallback($args)
  {
    $option = get_option($args[0]);
    echo '<input type="password" id="' . $args[0] . '" name="' . $args[0] . '" value="' . $option . '" />';
  }

  /**
   * Create Dashboard Widget for Bitbucket pipeline deploy status
   */
  function buildDashboardWidget()
  {
    global $wp_meta_boxes;

    wp_add_dashboard_widget('bitbucket_pipeline_dashboard_status', 'Deploy Status', [$this, 'buildDashboardWidgetContent']);
  }

  function buildDashboardWidgetContent()
  {
    $username = get_option('bb_option_username');
    $repo = get_option('bb_option_repo');

    $markup = '<img src="https://img.shields.io/bitbucket/pipelines/' . $username . '/' . $repo . '/master" alt="Bitbucket Pipeline Status" />';

    $markup .= '<style>.wp-trigger-bitbucket-deployments-button:disabled { opacity: 0.4; } </style>';
    $markup .= '<div style="margin-top: 1em">';
    $markup .= '<button type="button" class="button button-primary wp-trigger-bitbucket-deployments-button">Deploy to live</button>';
    $markup .= '</div>';

    echo $markup;
  }
}


if (class_exists('WPTriggerBitbucket')) {
  $WPTriggerBitbucket = new WPTriggerBitbucket();
}

// activation
register_activation_hook(__FILE__, array($WPTriggerBitbucket, 'activate'));

// deactivate
register_deactivation_hook(__FILE__, array($WPTriggerBitbucket, 'deactivate'));
