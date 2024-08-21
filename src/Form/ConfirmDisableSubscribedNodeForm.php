<?php
namespace Drupal\node_subscribe\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node_subscribe\Subscriber\Subscriber;
use Drupal\node_subscribe\Subscriber\Subscriptions;
use Drupal\Component\Render\FormattableMarkup;
use Symfony\Component\HttpFoundation\Request;

class ConfirmDisableSubscribedNodeForm extends ConfirmFormBase{

  /**
   * smid of the subscriber
   * nid of the node to disable for the smid
   * @var int
   */
  protected $smid;
  protected $nid;

  /**
   * @return string
   */
  public function getFormId() {
    return 'node_subscribe_confirm_disable_subscribed_node_form';
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function buildForm(array $form, FormStateInterface $form_state, $smid = NULL, $nid = NULL) {
    $this->smid = $smid;
    $this->nid = $nid;

    $form['smid'] = array(
      '#type' => 'hidden',
      '#value' => $smid,
    );
    $form['nid'] = array(
      '#type' => 'hidden',
      '#value' => $nid,
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
    /* do the disable actions */
    $subscriber = new Subscriber($form_state->getValue('nid'), null, $form_state->getValue('smid'));
    $subscriber->unsubscribeBySmid($form_state->getValue('nid'));
    $form_state->setRedirect('node_subscribe.view_subscriber_details', array('smid' => $this->smid));
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

  /*
   * Displays details of the user and ask to confirm deletions
   * 1) displays all tokens owned by the user
   * 2) displays all pages currently subscribed by the user
   * 3) Ask to confirm deletion
   */
  public function getDescription() {

    $subscriber_details = Subscriptions::getSubscriberDetailsBySmid($this->smid);
    $markup = '';

    if($subscriber_details) {
      $markup = '<h2>' . $this->t('Are you sure you want to disable %nid for %smid?', ['%smid' => $this->smid, '%nid' => $this->nid]) . '</h2>' . $markup;

      $markup .= '</br><p>' . $this->t('Are you sure you want to disable %nid for %smid?', ['%smid' => $this->smid, '%nid' => $this->nid]).'</p>';
    }
    else {
      $markup = '<h2>@message</h2>';
      $args = array('@message' => $this->t('This subscriber does not exist'));
      $markup = new FormattableMarkup($markup, $args);
    }

    return $markup;
  }

  private function getNodeTitle($nid){
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $node = $node_storage->load($nid);

    return $node->get('title')->value;
  }

  private function getURLByNid($nid){
    return \Drupal::service('path_alias.manager')->getAliasByPath('/node/'.$nid);
  }

}
