<?php

namespace Drupal\node_subscribe\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node_subscribe\Subscriber\Subscriptions;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Node subscribe analytics class file.
 */
class NodeSubscribeAnalytics extends FormBase {

  /**
   * The form builder.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * ModalFormContactController constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
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
  public function getFormId() {
    return 'node_subscribe_analytics';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $product_filter = $this->requestStack->getCurrentRequest()->query->get('product');

    $subscriber_total = Subscriptions::subscriberTotal();
    $subscribed_page_total = Subscriptions::subscribedPageTotal();
    $subscribedCountByPages = Subscriptions::subscribedCountByPages($product_filter);
    $subscribedCountByProduct = Subscriptions::subscribedCountByProduct();
    $allSubscribers = $this->buildTableAllSubscribers();

    $form['#theme'] = 'analytics_template';
    $form['#subscriber_total'] = $subscriber_total;
    $form['#subscribed_page_total'] = $subscribed_page_total;
    $form['#subscribedCountByProduct'] = $subscribedCountByProduct;
    $form['#product_filter'] = $product_filter;
    $form['#subscribedCountByPages'] = $subscribedCountByPages;
    $form['#allSubscribers'] = $allSubscribers;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  private function buildTableAllSubscribers() {
    $header = [
      [
        'data' => $this->t('SMID'),
        'field' => 'smid',
        'sort' => 'asc',
      ],
      [
        'data' => $this->t('Email'),
        'field' => 'email',
        'sort' => 'asc',
      ],
      [
        'data' => $this->t('Device/Token'),
        'field' => 'token',
        'sort' => 'asc',
      ],
      [
        'data' => $this->t('Verified?'),
        'field' => 'verified',
        'sort' => 'asc',
      ],
      [
        'data' => $this->t('Actions'),
      ],
    ];
    $subscribers = Subscriptions::allSubscribersTokens($header);
    $build['all_subscribers_table'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $subscribers['rows'],
      '#attributes' => [
        'id' => 'all-subscribers-table',
      ],
    ];
    $build['pager'] = [
      '#type' => 'pager',
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @todo Implement submitForm() method.
  }

}
