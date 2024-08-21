<?php

namespace Drupal\node_subscribe\Controller;

use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node_subscribe\Subscriber\Subscriptions;
use Drupal\Component\Render\FormattableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Node subscription view subscriber controller class file.
 */
class NodeSubscribeViewSubscriberController extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * ModalFormContactController constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(RequestStack $requestStack) {
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view($smid = NULL) {

    if ($smid) {
      $markup = Subscriptions::getSubscriberSummary($smid);
    }
    else {
      $markup = $this->t('<p>@message</p>', ['@message' => 'Subscriber does not exist.']);
    }

    $currentRequest = $this->requestStack->getCurrentRequest();

    $return_destination = $currentRequest->get('destination');
    $suffix_markup = '<a href=":cancel_url" class="button">@cancel</a>
                      <a href=":delete_url" class="button button--danger">@delete</a>';
    $suffix_args = [
      ':cancel_url' => $return_destination,
      '@cancel' => $this->t('Cancel'),
      ':delete_url' => Url::fromRoute('node_subscribe.manage_subscribers_form_delete', ['smid' => $smid],
        [
          'query' => [
            'destination' => $return_destination,
            'cancel_destination' => $currentRequest->getRequestUri(),
          ],
        ])
        ->toString(),
      '@delete' => $this->t('Delete Subscriber'),
    ];
    $output_suffix = new FormattableMarkup($suffix_markup, $suffix_args);

    return [
      '#markup' => $markup,
      '#suffix' => $output_suffix,
    ];
  }

}
