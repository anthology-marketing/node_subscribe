<?php

namespace Drupal\node_subscribe\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node_subscribe\Subscriber\Subscriber;
use Drupal\node_subscribe\Subscriber\Subscriptions;

/**
 * Class for confirm disable subscribed node form.
 */
class ConfirmDisableSubscribedNodeForm extends ConfirmFormBase {

  /**
   * Smid of the subscriber.
   *
   * @var int
   */
  protected $smid;

  /**
   * The node id.
   *
   * @var int
   */
  protected $nid;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'node_subscribe_confirm_disable_subscribed_node_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $smid = NULL, $nid = NULL) {
    $this->smid = $smid;
    $this->nid = $nid;

    $form['smid'] = [
      '#type' => 'hidden',
      '#value' => $smid,
    ];
    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => $nid,
    ];

    // @todo doesn't work - trying to set the button to go to a specific page (previous page if it is a view subscriber page)
    $cancel_destination = \Drupal::request()->get('cancel_destination');
    if ($cancel_destination) {
      $form['actions']['cancel'] = [
        '#type' => 'link',
        '#title' => 'Cancel',
        '#attributes' => ['class' => ['button']],
        '#url' => $cancel_destination,
        '#cache' => [
          'contexts' => [
            'url.query_args:cancel_destination',
          ],
        ],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /* do the disable actions */
    $subscriber = new Subscriber($form_state->getValue('nid'), NULL, $form_state->getValue('smid'));
    $subscriber->unsubscribeBySmid($form_state->getValue('nid'));
    $form_state->setRedirect('node_subscribe.view_subscriber_details', ['smid' => $this->smid]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $previousUrl = \Drupal::request()->server->get('HTTP_REFERER');
    return Url::fromUri($previousUrl);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Do you want to disable %nid for %smid?', ['%smid' => $this->smid, '%nid' => $this->nid]);
  }

  /**
   * Displays details of the user and ask to confirm deletions.
   *
   * 1) displays all tokens owned by the user.
   * 2) displays all pages currently subscribed by the user.
   * 3) Ask to confirm deletion.
   */
  public function getDescription() {

    $subscriber_details = Subscriptions::getSubscriberDetailsBySmid($this->smid);
    $markup = '';

    if ($subscriber_details) {
      $markup .= '<h2>';
      $markup .= $this->t('Are you sure you want to disable %nid for %smid?', [
        '%smid' => $this->smid,
        '%nid' => $this->nid,
      ]);
      $markup .= '</h2>';

      $markup .= '</br><p>';
      $markup .= $this->t('Are you sure you want to disable %nid for %smid?', [
        '%smid' => $this->smid,
        '%nid' => $this->nid,
      ]);
      $markup .= '</p>';
    }
    else {
      $markup = '<h2>@message</h2>';
      $args = ['@message' => $this->t('This subscriber does not exist')];
      $markup = new FormattableMarkup($markup, $args);
    }

    return $markup;
  }

}
