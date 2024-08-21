<?php
namespace Drupal\node_subscribe\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node_subscribe\Subscriber\Subscriptions;
use Drupal\Component\Render\FormattableMarkup;

class ConfirmDeleteSubscriberForm extends ConfirmFormBase{

  /**
   * ID of the item to delete.
   *
   * @var int
   */
  protected $smid;

  /**
   * @return string
   */
  public function getFormId() {
    return 'node_subscribe_confirm_delete_subscriber_form';
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function buildForm(array $form, FormStateInterface $form_state, $smid = NULL) {
    $this->smid = $smid;

    $form['smid'] = array(
      '#type' => 'hidden',
      '#value' => $smid,
    );

    //todo: doesn't work - trying to set the button to go to a specific page (previous page if it is a view subscriber page)
    $cancel_destination = \Drupal::request()->get('cancel_destination');
    if($cancel_destination){
      $form['actions']['cancel'] = array(
        '#type' => 'link',
        '#title' => 'Cancel',
        '#attributes' => ['class' => ['button']],
        '#url' => $cancel_destination,
        '#cache' => [
          'contexts' => [
            'url.query_args:cancel_destination',
          ],
        ],
      );
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /* do the deletion */
    $subscriber = new Subscriptions($form_state->getValue('smid'));
    $subscriber->deleteSubscriberBySmid();
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $previousUrl = \Drupal::request()->server->get('HTTP_REFERER');
    return Url::fromUri($previousUrl.'#all-subscribers-table');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Do you want to delete %smid?', ['%smid' => $this->smid]);
  }

  /*
   * Displays details of the user and ask to confirm deletion
   * 1) displays all tokens owned by the user
   * 2) displays all pages currently subscribed by the user
   * 3) Ask to confirm deletion
   */
  public function getDescription() {

    $subscriber_details = Subscriptions::getSubscriberDetailsBySmid($this->smid);
    $subscriber_subscriptions = Subscriptions::getSubscriberSubscriptionsBySmid($this->smid);
    $markup = Subscriptions::getSubscriberSummary($this->smid);

    if($subscriber_details) {
      $markup = '<h2>' . $this->t('Are you sure you want to delete %email?', array('%email' => $subscriber_details[0]->email)) . '</h2>' . $markup;

      $markup .= '</br><p><b>' . $this->t('Are you sure you want to delete %email and its subscriiptions?</b>
      <ul>
        <li>The email record: %email</li>
        <li>All device/token owned by this email.</li>
        <li>All subscription owned by this email.</li>
      </ul>
      <p>Will be deleted and cannot be undone.</p>', array('%email' => $subscriber_details[0]->email)).'</p>';
    }else{
      $markup = '<h2>@message</h2>';
      $args = array('@message' => $this->t('This subscriber does not exist'));
      $markup = new FormattableMarkup($markup, $args);
    }

    return $markup;
  }

  private function getNodeTitle($nid){
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load($nid);
    return $node->get('title')->value;
  }
  private function getURLByNid($nid){
    return \Drupal::service('path_alias.manager')->getAliasByPath('/node/'.$nid);
  }

}
