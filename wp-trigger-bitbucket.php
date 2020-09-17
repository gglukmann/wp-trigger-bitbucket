<?php

/**
 * @package WPTriggerBitbucket
 */
/*
Plugin Name: WP Trigger Bitbucket
Plugin URI: https://github.com/gglukmann/wp-trigger-bitbucket
Description: Save or update action triggers Bitbucket pipeline
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
    add_action('admin_init', array($this, 'general_settings_section'));
    add_action('save_post', array($this, 'run_hook'), 10, 3);
    add_action('wp_dashboard_setup', array($this, 'build_dashboard_widget'));
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

  function run_hook($post_id)
  {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;

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

  function general_settings_section()
  {
    add_settings_section(
      'general_settings_section',
      'WP Trigger Bitbucket Settings',
      array($this, 'my_section_options_callback'),
      'general'
    );
    add_settings_field(
      'bb_option_username',
      'Bitbucket Username',
      array($this, 'my_textbox_callback'),
      'general',
      'general_settings_section',
      array(
        'bb_option_username'
      )
    );
    add_settings_field(
      'bb_option_password',
      'Bitbucket Password',
      array($this, 'my_password_callback'),
      'general',
      'general_settings_section',
      array(
        'bb_option_password'
      )
    );
    add_settings_field(
      'bb_option_repo',
      'Repository Name',
      array($this, 'my_textbox_callback'),
      'general',
      'general_settings_section',
      array(
        'bb_option_repo'
      )
    );

    register_setting('general', 'bb_option_username', 'esc_attr');
    register_setting('general', 'bb_option_password', 'esc_attr');
    register_setting('general', 'bb_option_repo', 'esc_attr');
  }

  function my_section_options_callback()
  {
    echo '<p>Add bitbucket username, password and repository name</p>';
  }

  function my_textbox_callback($args)
  {
    $option = get_option($args[0]);
    echo '<input type="text" id="' . $args[0] . '" name="' . $args[0] . '" value="' . $option . '" />';
  }

  function my_password_callback($args)
  {
    $option = get_option($args[0]);
    echo '<input type="password" id="' . $args[0] . '" name="' . $args[0] . '" value="' . $option . '" />';
  }

  /**
   * Create Dashboard Widget for Bitbucket pipeline deploy status
   */
  function build_dashboard_widget()
  {
    global $wp_meta_boxes;

    wp_add_dashboard_widget('bitbucket_pipeline_dashboard_status', 'Deploy Status', array($this, 'build_dashboard_status'));
  }

  function build_dashboard_status()
  {
    $username = get_option('bb_option_username');
    $repo = get_option('bb_option_repo');

    $markup = '<img src="https://img.shields.io/bitbucket/pipelines/' . $username . '/' . $repo . '/master" alt="Bitbucket Pipeline Status" />';

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
