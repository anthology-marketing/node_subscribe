<?php

namespace Drupal\node_subscribe\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node_subscribe\Subscriber\Subscriptions;

/**
 * Node subscribe manager subscribers form.
 */
class NodeSubscribeManageSubscribersForm extends FormBase {

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

    $allSubscribers = $this->buildTableAllSubscribers();

    $form['table'] = $allSubscribers;
    $form['#actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete Selected'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    foreach (($form_state->getValue('table')) as $smid) {
      if ($smid) {
        $subscriber = new Subscriptions($smid);
        $subscriber->deleteSubscriberBySmid();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  private function buildTableAllSubscribers() {
    $header = [
      'smid' => [
        'data' => $this->t('SMID'),
        'field' => 'smid',
        'sort' => 'asc',
      ],
      'email' => [
        'data' => $this->t('Email'),
        'field' => 'email',
        'sort' => 'asc',
      ],
      'verified' => [
        'data' => $this->t('Verified?'),
        'field' => 'verified',
        'sort' => 'asc',
      ],
      'status' => [
        'data' => $this->t('User Status'),
        'field' => 'status',
        'sort' => 'asc',
      ],
      'changed' => [
        'data' => $this->t('Last Updated'),
        'field' => 'changed',
        'sort' => 'asc',
      ],
      'created' => [
        'data' => $this->t('Sign up Date'),
        'field' => 'created',
        'sort' => 'asc',
      ],
      'delete' => [
        'data' => $this->t('Actions'),
      ],
    ];
    $subscribers = Subscriptions::allSubscribers($header);
    $build['table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $subscribers['rows'],
      '#attributes' => [
        'id' => 'all-subscribers-table',
      ],
      '#empty' => $this->t('No users found'),
    ];
    $build['pager'] = [
      '#type' => 'pager',
    ];
    return $build;
  }

}
