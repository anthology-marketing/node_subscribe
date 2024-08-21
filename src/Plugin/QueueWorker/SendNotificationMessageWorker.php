<?php

namespace Drupal\node_subscribe\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node_subscribe\Service\NodeSubscribeEmailService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes Tasks for Learning.
 *
 * @QueueWorker(
 *   id = "node_subscribe_message_queue",
 *   title = @Translation("Send email message task worker: send a email message via services queue"),
 *   cron = {"time" = 30}
 * )
 */
class SendNotificationMessageWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The node subscribe email service.
   *
   * @var \Drupal\node_subscribe\Service\NodeSubscribeEmailService
   */
  protected $emailService;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerChannelFactoryInterface $logger_interface,
    NodeSubscribeEmailService $email_service,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->logger = $logger_interface->get('node_susbcribe');
    $this->emailService = $email_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // Instantiates this class.
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('node_subscribe.email.service'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {

    if (isset($data['email']) && isset($data['nid'])) {
      $mailer_extras = [
        'page_title' => $data['page_title'],
        'page_url' => $data['page_url'],
        'email_list' => $data['result'],
        'email_count' => $data['count'],
        'update_message' => $data['update_message'],
      ];
      $this->emailService->notifySubscriberOnNodeUpdate($data['email'], $data['nid'], $mailer_extras);
    }
  }
}
