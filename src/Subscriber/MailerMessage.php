<?php

namespace Drupal\node_subscribe\Subscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;

/**
 * Mailer message class.
 */
class MailerMessage {

  use StringTranslationTrait;

  /**
   * Class attribute.
   *
   * @var string
   */
  private $nid;

  /**
   * Class attribute.
   *
   * @var string
   */
  private $mailTo;

  /**
   * Class attribute.
   *
   * @var string
   */
  private $extras;

  /**
   * Class attribute.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * Class attribute.
   *
   * @var string
   */
  public $mailKey;

  /**
   * Class attribute.
   *
   * @var string
   */
  public $mailTitle;

  /**
   * Class attribute.
   *
   * @var string
   */
  public $mailBody;

  /**
   * Class attribute.
   *
   * @var string
   */
  public $params;

  /**
   * {@inheritdoc}
   */
  public function __construct($messageName, $nid, $mail_to, $extras = []) {
    $this->nid = $nid;
    $this->mailTo = $mail_to;
    $this->extras = $extras;
    $this->config = \Drupal::config('node_subscribe.settings');

    $message = $this->getMessageByName($messageName);
    $this->mailKey = $message['mail_key'];
    $this->mailTitle = $message['mail_title'];
    $this->mailBody = $message['mail_body'];
    $this->params = $message['mail_params'];
  }

  /**
   * {@inheritdoc}
   */
  public function getMessageByName($messageName) {

    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();

    // @todo: get proper language.
    $language = 'en';

    $result = [];
    switch ($messageName) {

      case 'send_page_added_email':
        $result = [
          'mail_key' => 'subscribed',
          'mail_title' => $this->getConfigValue('subscribe_email_template.page_added_email.title', $language, 'en'),
          'mail_body' => $this->getConfigValue('subscribe_email_template.page_added_email.body', $language, 'en'),
          'mail_params' => [
            'content_title' => $this->getConfigValue('subscribe_email_template.page_added_email.title', $language, 'en'),
            'content_body' => $this->getConfigValue('subscribe_email_template.page_added_email.body', $language, 'en'),
            'page_title' => $this->getNodeTitle(),
            'page_url' => $this->getAbsoluteLink(),
            'show_manage_modal_button' => TRUE,
            'actions' => [
              'cancel_alert' => [
                'label' => $this->getConfigValue('subscribe_email_template.page_added_email.button', $language, 'en'),
                'link' => $this->getAbsoluteLink() . '?unsubscribe=true&email=' . $this->mailTo,
                'type' => 'btn-link',
              ],
            ],
          ],
        ];
        break;

      case 'send_new_user_email':
        $result = [
          'mail_key' => 'new_user',
          'mail_title' => $this->getConfigValue('subscribe_email_template.confirmation_email.title', $language, 'en'),
          'mail_body' => $this->getConfigValue('subscribe_email_template.confirmation_email.body', $language, 'en'),
          'mail_params' => [
            'content_title' => $this->getConfigValue('subscribe_email_template.confirmation_email.title', $language, 'en'),
            'content_body' => $this->getConfigValue('subscribe_email_template.confirmation_email.body', $language, 'en'),
            'page_title' => $this->getNodeTitle(),
            'page_url' => $this->getAbsoluteLink(),
            'show_manage_modal_button' => FALSE,
            'actions' => [
              'confirm_sign_up' => [
                'label' => $this->getConfigValue('subscribe_email_template.confirmation_email.button', $language, 'en'),
                'link' => $this->extras['verification_link'],
                'type' => 'btn-button',
              ],
            ],
          ],
        ];
        break;

      case 'send_new_device_email':
        $result = [
          'mail_key' => 'new_device',
          'mail_title' => $this->getConfigValue('subscribe_email_template.new_device_email.title', $language, 'en'),
          'mail_body' => $this->getConfigValue('subscribe_email_template.new_device_email.body', $language, 'en'),
          'mail_params' => [
            'content_title' => $this->getConfigValue('subscribe_email_template.new_device_email.title', $language, 'en'),
            'content_body' => $this->getConfigValue('subscribe_email_template.new_device_email.body', $language, 'en'),
            'page_title' => $this->getNodeTitle(),
            'page_url' => $this->getAbsoluteLink(),
            'show_manage_modal_button' => FALSE,
            'actions' => [
              'confirm_device' => [
                'label' => $this->getConfigValue('subscribe_email_template.new_device_email.button', $language, 'en'),
                'link' => $this->getAbsoluteLink() . "?subscriber=" . $this->extras['new_secret'],
                'type' => 'btn-link',
              ],
              'cancel_device' => [
                'label' => $this->t('Cancel'),
              // @todo should token be sent?
                'link' => $this->getAbsoluteLink() . "?subscriber=" . $this->extras['new_secret'] . '&token=' . $this->extras['new_token'] . '&unsubscribe=true',
                'type' => 'btn-link',
              ],
            ],
          ],
        ];
        break;

      case 'send_page_removed_email':
        $result = [
          'mail_key' => 'unsubscribed',
          'mail_title' => $this->getConfigValue('subscribe_email_template.page_removed_email.title', $language, 'en'),
          'mail_body' => $this->getConfigValue('subscribe_email_template.page_removed_email.body', $language, 'en'),
          'mail_params' => [
            'content_title' => $this->getConfigValue('subscribe_email_template.page_removed_email.title', $language, 'en'),
            'content_body' => $this->getConfigValue('subscribe_email_template.page_removed_email.body', $language, 'en'),
            'page_title' => $this->getNodeTitle(),
            'page_url' => $this->getAbsoluteLink(),
            'show_manage_modal_button' => TRUE,
            'actions' => [
              'subscribe' => [
                'label' => $this->getConfigValue('subscribe_email_template.page_removed_email.button', $language, 'en'),
                'link' => $this->getAbsoluteLink() . '#node-subscribe-form',
                'type' => 'btn-button',
              ],
            ],
          ],
        ];
        break;

      case 'send_page_remove_confirmation_email':
        $result = [
          'mail_key' => 'unsubscribed',
          'mail_title' => $this->getConfigValue('subscribe_email_template.page_removed_confirmation_email.title', $language, 'en'),
          'mail_body' => $this->getConfigValue('subscribe_email_template.page_removed_confirmation_email.body', $language, 'en'),
          'mail_params' => [
            'content_title' => $this->getConfigValue('subscribe_email_template.page_removed_confirmation_email.title', $language, 'en'),
            'content_body' => $this->getConfigValue('subscribe_email_template.page_removed_confirmation_email.body', $language, 'en'),
            'page_title' => $this->getNodeTitle(),
            'page_url' => $this->getAbsoluteLink(),
            'show_manage_modal_button' => FALSE,
            'actions' => [
              'subscribe' => [
                'label' => $this->getConfigValue('subscribe_email_template.page_removed_confirmation_email.button', $language, 'en'),
                'link' => $this->getAbsoluteLink() . '?subscriber=' . $this->extras['new_secret'] . '&token=' . $this->extras['new_token'] . '&unsubscribe=true',
                'type' => 'btn-button',
              ],
            ],
          ],
        ];
        break;

      case 'send_page_updated_emails':
        $result = [
          'mail_key' => 'subscribed',
          'mail_title' => $this->getConfigValue('subscribe_email_template.page_updated_email.title', $language, 'en'),
          'mail_body' => $this->getConfigValue('subscribe_email_template.page_updated_email.body', $language, 'en'),
          'mail_params' => [
            'content_title' => $this->getConfigValue('subscribe_email_template.page_updated_email.title', $language, 'en'),
            'content_body' => $this->getConfigValue('subscribe_email_template.page_updated_email.body', $language, 'en'),
            'page_title' => $this->extras['page_title'],
            'page_url' => $this->extras['page_url'],
            'show_manage_modal_button' => TRUE,
            'update_message' => $this->extras['update_message'],
            'actions' => [
              'unsubscribe' => [
                'label' => $this->getConfigValue('subscribe_email_template.page_updated_email.button', $language, 'en'),
                'link' => $this->extras['page_url'] . '?unsubscribe=true',
                'type' => 'btn-link',
              ],
            ],
          ],
        ];
        break;

      case 'send_page_updated_development_emails':
        $result = [
          'mail_key' => 'subscribed',
          'mail_title' => $this->getConfigValue('subscribe_email_template.page_updated_email.title', $language, 'en'),
          'mail_body' => $this->getConfigValue('subscribe_email_template.page_updated_email.body', $language, 'en'),
          'mail_params' => [
            'content_title' => '(In Development Mode)' . $this->getConfigValue('subscribe_email_template.page_updated_email.title', $language, 'en'),
            'content_body' => $this->getConfigValue('subscribe_email_template.page_updated_email.body', $language, 'en') . $this->renderEmailList($this->extras['email_list']),
            'page_title' => $this->extras['page_title'],
            'page_url' => $this->extras['page_url'],
            'show_manage_modal_button' => FALSE,
            'update_message' => $this->extras['update_message'],
            'actions' => [
              'unsubscribe' => [
                'label' => $this->getConfigValue('subscribe_email_template.page_updated_email.button', $language, 'en'),
                'link' => $this->extras['page_url'] . '?unsubscribe=true',
                'type' => 'btn-link',
              ],
            ],
          ],
        ];

      default:
        $result = [];
        break;
    }
    return $result;
  }

  /**
   * Helper methods.
   */
  private function getNodeTitle() {
    $node = Node::load($this->nid);
    return $node->title->value;
  }

  /**
   * {@inheritdoc}
   */
  private function getAlias() {
    $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $this->nid);
    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  private function getAbsoluteLink() {
    return \Drupal::request()->getSchemeAndHttpHost() . $this->getAlias();
  }

  /**
   * {@inheritdoc}
   */
  private function renderEmailList($email_list) {
    $html = ' The following list of emails should be getting this email.';
    if (count($email_list) >= 1) {
      foreach ($email_list as $value) {
        $html .= $value->email . ', ';
      }
    }
    else {
      $html .= 'No one is following this page.';
    }

    return $html;
  }

  /**
   * Function to get a config string with fallback.
   */
  private function getConfigValue($config, $language, $fallback) {
    $result = $this->config->get($config);
    // $result = $this->config->get($language . '.' . $config);
    // if (empty($result)) {
    //   $result = $this->config->get($fallback . '.' . $config);
    // }
    return $result;
  }

}
