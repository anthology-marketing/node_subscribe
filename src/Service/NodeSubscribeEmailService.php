<?php

namespace Drupal\node_subscribe\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class to help to send formatted emails.
 */
class NodeSubscribeEmailService {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManager
   */
  protected $mailManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * Mailing constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The file storage backend.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\path_alias\AliasManagerInterface $aliasManager
   *   The alias manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    MailManagerInterface $mailManager,
    LoggerChannelFactoryInterface $loggerFactory,
    RequestStack $requestStack,
    AliasManagerInterface $aliasManager,
    LanguageManagerInterface $languageManager,
    ConfigFactoryInterface $configFactory
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->mailManager = $mailManager;
    $this->logger = $loggerFactory->get('node_subscribe');
    $this->requestStack = $requestStack;
    $this->aliasManager = $aliasManager;
    $this->languageManager = $languageManager;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.mail'),
      $container->get('logger.factory'),
      $container->get('request_stack'),
      $container->get('path_alias.manager'),
      $container->get('language_manager'),
      $container->get('config.factory'),
    );
  }

  /**
   * Function to send email after added a page to node subscribe.
   */
  public function pageAdded(string $to, int|string $nid) {
    $key = 'page_added';
    $extras = [
      'nid' => $nid,
      'subject' => $this->t('Anthology.com Content Subscription - Page Added'),
      'to' => $to,
    ];

    $data = $this->getMessageData($key, $extras);
    $result = $this->mailManager->mail('node_subscribe', $key, $to, 'en', $data);
    return $result;
  }

  /**
   * Function to send email after removed a page to node subscribe.
   */
  public function pageRemoved(string $to, int|string $nid) {
    $key = 'page_removed';
    $extras = [
      'nid' => $nid,
      'subject' => $this->t('Anthology.com Content Subscription - Page Removed'),
      'to' => $to,
    ];

    $data = $this->getMessageData($key, $extras);
    $result = $this->mailManager->mail('node_subscribe', $key, $to, 'en', $data);
    return $result;
  }

  /**
   * Function to send email after removed confirm a page to node subscribe.
   */
  public function pageRemovedConfirm(string $to, int|string $nid, array $extras = []) {

    $key = 'page_removed_confirm';
    $extras['nid'] = $nid;
    $extras['subject'] = $this->t('Anthology.com Content Subscription - Page Removed Confirmation');
    $extras['to'] = $to;

    $data = $this->getMessageData($key, $extras);
    $result = $this->mailManager->mail('node_subscribe', $key, $to, 'en', $data);
    return $result;
  }

  /**
   * Function to send email after added a new device to node subscribe.
   */
  public function newDevice(string $to, int|string $nid, array $extras = []) {

    $key = 'new_device';
    $extras['nid'] = $nid;
    $extras['subject'] = $this->t('Anthology.com Content Subscription - New Device Added');
    $extras['to'] = $to;

    $data = $this->getMessageData($key, $extras);
    $result = $this->mailManager->mail('node_subscribe', $key, $to, 'en', $data);
    return $result;
  }

  /**
   * Function to send email after added a new user to node subscribe.
   */
  public function newUser(string $to, int|string $nid, array $extras = []) {
    $key = 'new_user';
    $extras['nid'] = $nid;
    $extras['subject'] = $this->t('Anthology.com Content Subscription - New User Added');
    $extras['to'] = $to;

    $data = $this->getMessageData($key, $extras);
    $result = $this->mailManager->mail('node_subscribe', $key, $to, 'en', $data);
    return $result;
  }

  /**
   * Function to notify user about node change.
   */
  public function notifySubscriberOnNodeUpdate(string $to, int|string $nid, array $extras = []) {
    $key = 'page_updated';
    $extras['nid'] = $nid;
    $extras['subject'] = $this->t('Anthology.com Content Subscription - Subscribed Content Updated');
    $extras['to'] = $to;

    $data = $this->getMessageData($key, $extras);
    $result = $this->mailManager->mail('node_subscribe', $key, $to, 'en', $data);
    return $result;
  }

  /**
   * Function to get message data.
   */
  public function getMessageData(string $key, array $extras) {
    $language = $this->languageManager->getCurrentLanguage()->getId();
    $configObject = $this->configFactory->get('node_subscribe.settings');

    // @todo: get proper language.
    $language = 'en';

    $result = [];
    switch ($key) {

      case 'page_added':
        $result = [
          'mail_key' => 'subscribed',
          'mail_title' => $configObject->get('subscribe_email_template.page_added_email.title', $language, 'en'),
          'mail_body' => $configObject->get('subscribe_email_template.page_added_email.body', $language, 'en'),
          'mail_params' => [
            'content_title' => $configObject->get('subscribe_email_template.page_added_email.title', $language, 'en'),
            'content_body' => $configObject->get('subscribe_email_template.page_added_email.body', $language, 'en'),
            'page_title' => $this->getNodeTitle($extras['nid']),
            'page_url' => $this->getAbsoluteLink($extras['nid']),
            'show_manage_modal_button' => TRUE,
            'actions' => [
              'cancel_alert' => [
                'label' => $configObject->get('subscribe_email_template.page_added_email.button', $language, 'en'),
                'link' => $this->getAbsoluteLink($extras['nid']) . '?unsubscribe=true&email=' . $extras['to'],
                'type' => 'btn-link',
              ],
            ],
          ],
        ];
        break;

      case 'new_user':
        $result = [
          'mail_key' => 'new_user',
          'mail_title' => $configObject->get('subscribe_email_template.confirmation_email.title', $language, 'en'),
          'mail_body' => $configObject->get('subscribe_email_template.confirmation_email.body', $language, 'en'),
          'mail_params' => [
            'content_title' => $configObject->get('subscribe_email_template.confirmation_email.title', $language, 'en'),
            'content_body' => $configObject->get('subscribe_email_template.confirmation_email.body', $language, 'en'),
            'page_title' => $this->getNodeTitle($extras['nid']),
            'page_url' => $this->getAbsoluteLink($extras['nid']),
            'show_manage_modal_button' => FALSE,
            'actions' => [
              'confirm_sign_up' => [
                'label' => $configObject->get('subscribe_email_template.confirmation_email.button', $language, 'en'),
                'link' => $extras['verification_link'],
                'type' => 'btn-button',
              ],
            ],
          ],
        ];
        break;

      case 'new_device':
        $result = [
          'mail_key' => 'new_device',
          'mail_title' => $configObject->get('subscribe_email_template.new_device_email.title', $language, 'en'),
          'mail_body' => $configObject->get('subscribe_email_template.new_device_email.body', $language, 'en'),
          'mail_params' => [
            'content_title' => $configObject->get('subscribe_email_template.new_device_email.title', $language, 'en'),
            'content_body' => $configObject->get('subscribe_email_template.new_device_email.body', $language, 'en'),
            'page_title' => $this->getNodeTitle($extras['nid']),
            'page_url' => $this->getAbsoluteLink($extras['nid']),
            'show_manage_modal_button' => FALSE,
            'actions' => [
              'confirm_device' => [
                'label' => $configObject->get('subscribe_email_template.new_device_email.button', $language, 'en'),
                'link' => $this->getAbsoluteLink($extras['nid']) . "?subscriber=" . $extras['new_secret'],
                'type' => 'btn-link',
              ],
              'cancel_device' => [
                'label' => $this->t('Cancel'),
                // @todo should token be sent?
                'link' => $this->getAbsoluteLink($extras['nid']) . "?subscriber=" . $extras['new_secret'] . '&token=' . $extras['new_token'] . '&unsubscribe=true',
                'type' => 'btn-link',
              ],
            ],
          ],
        ];
        break;

      case 'page_removed':
        $result = [
          'mail_key' => 'unsubscribed',
          'mail_title' => $configObject->get('subscribe_email_template.page_removed_email.title', $language, 'en'),
          'mail_body' => $configObject->get('subscribe_email_template.page_removed_email.body', $language, 'en'),
          'mail_params' => [
            'content_title' => $configObject->get('subscribe_email_template.page_removed_email.title', $language, 'en'),
            'content_body' => $configObject->get('subscribe_email_template.page_removed_email.body', $language, 'en'),
            'page_title' => $this->getNodeTitle($extras['nid']),
            'page_url' => $this->getAbsoluteLink($extras['nid']),
            'show_manage_modal_button' => TRUE,
            'actions' => [
              'subscribe' => [
                'label' => $configObject->get('subscribe_email_template.page_removed_email.button', $language, 'en'),
                'link' => $this->getAbsoluteLink($extras['nid']) . '#node-subscribe-form',
                'type' => 'btn-button',
              ],
            ],
          ],
        ];
        break;

      case 'page_remove_confirm':
        $result = [
          'mail_key' => 'unsubscribed',
          'mail_title' => $configObject->get('subscribe_email_template.page_removed_confirmation_email.title', $language, 'en'),
          'mail_body' => $configObject->get('subscribe_email_template.page_removed_confirmation_email.body', $language, 'en'),
          'mail_params' => [
            'content_title' => $configObject->get('subscribe_email_template.page_removed_confirmation_email.title', $language, 'en'),
            'content_body' => $configObject->get('subscribe_email_template.page_removed_confirmation_email.body', $language, 'en'),
            'page_title' => $this->getNodeTitle($extras['nid']),
            'page_url' => $this->getAbsoluteLink($extras['nid']),
            'show_manage_modal_button' => FALSE,
            'actions' => [
              'subscribe' => [
                'label' => $configObject->get('subscribe_email_template.page_removed_confirmation_email.button', $language, 'en'),
                'link' => $this->getAbsoluteLink($extras['nid']) . '?subscriber=' . $extras['new_secret'] . '&token=' . $extras['new_token'] . '&unsubscribe=true',
                'type' => 'btn-button',
              ],
            ],
          ],
        ];
        break;

      case 'page_updated':
        $result = [
          'mail_key' => 'subscribed',
          'mail_title' => $configObject->get('subscribe_email_template.page_updated_email.title', $language, 'en'),
          'mail_body' => $configObject->get('subscribe_email_template.page_updated_email.body', $language, 'en'),
          'mail_params' => [
            'content_title' => $configObject->get('subscribe_email_template.page_updated_email.title', $language, 'en'),
            'content_body' => $configObject->get('subscribe_email_template.page_updated_email.body', $language, 'en'),
            'page_title' => $this->getNodeTitle($extras['nid']),
            'page_url' => $this->getAbsoluteLink($extras['nid']),
            'show_manage_modal_button' => TRUE,
            'update_message' => $extras['update_message'] ?? '',
            'actions' => [
              'unsubscribe' => [
                'label' => $configObject->get('subscribe_email_template.page_updated_email.button', $language, 'en'),
                'link' => $this->getAbsoluteLink($extras['nid']) . '?unsubscribe=true',
                'type' => 'btn-link',
              ],
            ],
          ],
        ];
        break;

      case 'page_updated_development_emails':
        $result = [
          'mail_key' => 'subscribed',
          'mail_title' => $configObject->get('subscribe_email_template.page_updated_email.title', $language, 'en'),
          'mail_body' => $configObject->get('subscribe_email_template.page_updated_email.body', $language, 'en'),
          'mail_params' => [
            'content_title' => '(In Development Mode)' . $configObject->get('subscribe_email_template.page_updated_email.title', $language, 'en'),
            'content_body' => $configObject->get('subscribe_email_template.page_updated_email.body', $language, 'en') . $this->renderEmailList($extras['email_list']),
            'page_title' => $extras['page_title'],
            'page_url' => $extras['page_url'],
            'show_manage_modal_button' => FALSE,
            'update_message' => $extras['update_message'],
            'actions' => [
              'unsubscribe' => [
                'label' => $configObject->get('subscribe_email_template.page_updated_email.button', $language, 'en'),
                'link' => $extras['page_url'] . '?unsubscribe=true',
                'type' => 'btn-link',
              ],
            ],
          ],
        ];

      default:
        $result = [];
        break;
    }

    foreach ($extras as $key => $item) {
      $result[$key] = $item;
    }

    return $result;
  }

  /**
   * Helper methods.
   */
  private function getNodeTitle(int|string $nid) {
    /** @var \Drupal\node\NodeStorageInterface $nodeStorage */
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $node = $nodeStorage->load($nid);
    if (is_null($node)) {
      return 'Untitled';
    }
    return $node->title->value;
  }

  /**
   * {@inheritdoc}
   */
  private function getAlias(int|string $nid) {
    $alias = $this->aliasManager->getAliasByPath('/node/' . $nid);
    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  private function getAbsoluteLink(int|string $nid) {
    if (is_null($this->requestStack)) {
      return $this->refineUrl('http://default' . $this->getAlias($nid));
    }
    return $this->refineUrl($this->requestStack->getCurrentRequest()->getSchemeAndHttpHost() . $this->getAlias($nid));
  }

  /**
   * {@inheritdoc}
   */
  private function renderEmailList($email_list) {
    $html = 'The following list of emails should be getting this email.';
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

  private function refineUrl(String $url) {
    $configObject = $this->configFactory->get('node_subscribe.settings');
    $website = $configObject->get('website');
    if (str_contains($url, 'http://default/')) {
      return str_replace('http://default/', $website, $url);
    }
    return $url;
  }

}
