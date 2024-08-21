<?php

namespace Drupal\node_subscribe\Subscriber;

/**
 * Mailer class for mail subscriber.
 */
class Mailer {

  /**
   * Class attribute.
   *
   * @var \Drupal\Core\Mail\MailManager
   */
  private $mailManager;

  /**
   * Class attribute.
   *
   * @var string
   */
  private $module;

  /**
   * Class attribute.
   *
   * @var string
   */
  private $key;

  /**
   * Class attribute.
   *
   * @var string
   */
  private $to;

  /**
   * Class attribute.
   *
   * @var array
   */
  private $params;

  /**
   * Class attribute.
   *
   * @var string
   */
  private $langcode;

  /**
   * Class attribute.
   *
   * @var bool
   */
  private $send;

  /**
   * Mailer constructor.
   *
   * @param string $message_name
   *   Message type name.
   * @param string $nid
   *   Nid that will be used to generate message body.
   * @param string $mail_to
   *   The email to be sent to.
   * @param array $extras
   *   Used in message body for other data required.
   */
  public function __construct($message_name, $nid, $mail_to, array $extras = []) {

    $mailer_massage = new MailerMessage($message_name, $nid, $mail_to, $extras);

    $theme_body = $this->buildTheme($mailer_massage->params);
    $theme_body['#mail_key'] = $mailer_massage->mailKey;
    $theme_body['#mail_to'] = $mail_to;
    $theme_body['#mail_title'] = $mailer_massage->mailTitle;
    $theme_body['#mail_body'] = $mailer_massage->mailBody;

    $mail_body = \Drupal::service('renderer')->render($theme_body);

    $this->mailManager = \Drupal::service('plugin.manager.mail');
    $this->module = "node_subscribe";
    $this->key = $mailer_massage->mailKey;
    $this->to = $mail_to;
    $this->params = [
      'title' => $mailer_massage->mailTitle,
      'message' => $mail_body,
    ];
    $this->langcode = \Drupal::currentUser()->getPreferredLangcode();
    $this->send = TRUE;
  }

  /**
   * {@inheritdoc}
   *
   * @todo put the theme_name into config
   */
  public function buildTheme($params, $theme_name = 'mail-template-2') {
    $site_config = \Drupal::config('system.site');

    $theme_body = [
      '#theme' => $theme_name,
    ];

    if (isset($params['nid'])) {
      $theme_body['#nid'] = $params['nid'];
    }
    if (isset($params['content_title'])) {
      $theme_body['#content_title'] = $params['content_title'];
    }
    if (isset($params['content_body'])) {
      $theme_body['#content_body'] = $params['content_body'];
    }
    if (isset($params['page_title'])) {
      $theme_body['#page_title'] = $params['page_title'];
    }
    if (isset($params['update_message'])) {
      $theme_body['#update_message'] = $params['update_message'];
    }
    if (isset($params['page_url'])) {
      $theme_body['#page_url'] = $params['page_url'];
    }
    if (isset($params['show_manage_modal_button'])) {
      $theme_body['#show_manage_modal_button'] = $params['show_manage_modal_button'];
    }
    if (isset($params['actions'])) {
      $theme_body['#actions'] = $params['actions'];
    }
    if ($site_config->get('siteinfo_site_privacy_link')) {
      $theme_body['#privacy_url'] = $site_config->get('siteinfo_site_privacy_link');
    }

    return $theme_body;

  }

  /**
   * {@inheritdoc}
   */
  public function sendMail() {

    $result = $this->mailManager->mail(
      $this->module,
      $this->key,
      $this->to,
      $this->langcode,
      $this->params,
      NULL,
      $this->send
    );

    if ($result['result'] !== TRUE) {
      return ([
        "error" => TRUE,
        "message" => "Error sending email",
      ]);
    }
    else {
      return ([
        "success" => TRUE,
        "message" => "Email sent",
      ]);
    }
    // Test rename.
  }

}
