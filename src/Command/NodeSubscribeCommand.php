<?php

namespace Drupal\node_subscribe\Command;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node_subscribe\Service\NodeSubscribeEmailService;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A drush command file.
 *
 * @package Drupal\node_subscribe\Command
 */
class NodeSubscribeCommand extends DrushCommands {

  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The mail manager interface.
   *
   * @var \Drupal\node_subscribe\Service\NodeSubscribeEmailService
   */
  protected $emailService;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    AccountProxyInterface $currentUser,
    NodeSubscribeEmailService $emailService
  ) {
    $this->currentUser = $currentUser;
    $this->emailService = $emailService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('node_subscribe.email.service'),
    );
  }

  /**
   * Drush command to send emails.
   *
   * @param string $to
   *   The target email.
   * @param string $nid
   *   The message.
   * @param mixed $options
   *   The options.
   *
   * @command node-subscribe:page-added
   * @aliases nse:pa
   * @option key
   *   The email policy key.
   * @usage node-subscribe:page-added "user@emailprovider.com" "15956"
   */
  public function pageAdded(
    $to = 'email@email.com',
    $nid = '1',
    $options = [
      'key' => '*',
    ]
  ) {

    $this->emailService->pageAdded($to, $nid);
  }

  /**
   * Drush command to send emails.
   *
   * @param string $to
   *   The target email.
   * @param string $nid
   *   The message.
   * @param mixed $options
   *   The options.
   *
   * @command node-subscribe:page-removed
   * @aliases nse:pr
   * @option key
   *   The email policy key.
   * @usage node-subscribe:page-removed "user@emailprovider.com" "15956"
   */
  public function pageRemoved(
    $to = 'email@email.com',
    $nid = '1',
    $options = [
      'key' => '*',
    ]
  ) {
    $this->emailService->pageRemoved($to, $nid);
  }

  /**
   * Drush command to send emails.
   *
   * @param string $to
   *   The target email.
   * @param string $nid
   *   The message.
   * @param mixed $options
   *   The options.
   *
   * @command node-subscribe:page-removed-confirm
   * @aliases nse:prc
   * @option key
   *   The email policy key.
   * @usage node-subscribe:page-removed-confirm "user@emailprovider.com" "15956"
   */
  public function pageRemovedConfirm(
    $to = 'email@email.com',
    $nid = '1',
    $options = [
      'key' => '*',
    ]
  ) {
    $extras = [
      'new_secret' => '',
      'new_token' => '',
    ];
    $this->emailService->pageRemovedConfirm($to, $nid, $extras);
  }

  /**
   * Drush command to send emails.
   *
   * @param string $to
   *   The target email.
   * @param string $nid
   *   The message.
   * @param mixed $options
   *   The options.
   *
   * @command node-subscribe:new-device
   * @aliases nse:nd
   * @option key
   *   The email policy key.
   * @usage node-subscribe:new-device "user@emailprovider.com" "15956"
   */
  public function newDevice(
    $to = 'email@email.com',
    $nid = '1',
    $options = [
      'key' => '*',
    ]
  ) {
    $extras = [
      'new_secret' => '',
      'new_token' => '',
    ];
    $this->emailService->newDevice($to, $nid, $extras);
  }

  /**
   * Drush command to send emails.
   *
   * @param string $to
   *   The target email.
   * @param string $nid
   *   The message.
   * @param mixed $options
   *   The options.
   *
   * @command node-subscribe:new-user
   * @aliases nse:nu
   * @option key
   *   The email policy key.
   * @usage node-subscribe:new-user "user@emailprovider.com" "15956"
   */
  public function newUser(
    $to = 'email@email.com',
    $nid = '1',
    $options = [
      'key' => '*',
    ]
  ) {
    $secret = '';
    $verification_link = 'https://newthology.lndo.site?subscriber=' . $secret;
    $extras = [
      'verification_link' => $verification_link,
    ];
    $this->emailService->newUser($to, $nid, $extras);
  }

  /**
   * Drush command to send email to user on node update.
   *
   * @param string $to
   *   The target email.
   * @param string $nid
   *   The node ID.
   * @param mixed $options
   *   The options.
   *
   * @command node-subscribe:notify-subscriber
   * @aliases nse:ns
   * @option key
   *   The email policy key.
   * @usage node-subscribe:notify-subscriber "user@emailprovider.com" "15956"
   */
  public function nodeUpdated(
    $to = 'email@email.com',
    $nid = '1',
    $options = [
      'key' => '*',
    ]
  ) {
    $secret = '';
    $verification_link = 'https://newthology.lndo.site?subscriber=' . $secret;
    $extras = [
      'verification_link' => $verification_link,
    ];
    $this->emailService->notifySubscriberOnNodeUpdate($to, $nid, $extras);
  }

}
