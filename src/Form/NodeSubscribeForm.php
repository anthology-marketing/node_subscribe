<?php

namespace Drupal\node_subscribe\form;

use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Subscription Sign Up Form.
 */
class NodeSubscribeForm extends FormBase {

  /**
   * The email validator.
   *
   * @var \Drupal\Component\Utility\EmailValidatorInterface
   */
  protected $emailValidator;

  /**
   * The email validator.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EmailValidatorInterface $email_validator,
    RouteMatchInterface $route_match
  ) {
    $this->emailValidator = $email_validator;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('email.validator'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'node_subscribe_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $extra = NULL) {
    $node = $this->routeMatch->getParameter('node');
    $nid = $node->nid->value;
    $this->subscriber = $extra;

    $form['foo']['email'] = [
      '#title' => $this->t('Email Address'),
      '#type' => 'textfield',
      '#size' => 25,
      '#required' => TRUE,
    ];

    $form['foo'] = [
      '#tree' => TRUE,
      '#type' => 'container',
      '#prefix' => '<div id="subscribe-form-wrapper">',
      '#suffix' => '</div>',
      '#markup' => $this->t('Subscribe to get notification when this page updates.'),
    ];

    $form['foo']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Subscribe'),
      '#submit' => [$this, 'ajaxSubscribe'],
      '#ajax' => [
        'callback' => [$this, 'ajaxSignup'],
        'wrapper' => 'subscribe-form-wrapper',
        'effect' => 'fade',
      ],
    ];

    if ($form_state->getValue('test')) {
      $form['bar']['name'] = [
        '#type' => 'textfield',
      ];
    }

    if ($this->subscriber->hasToken() && $this->subscriber->isValidToken()) {
      unset($form['foo']['email']);
      $form['foo']['submit']['#ajax'] = [
        'callback' => [$this, 'ajaxSubscribe'],
        'wrapper' => 'subscribe-form-wrapper',
        'effect' => 'fade',
      ];
      if ($this->subscriber->isSubscribed()) {
        // Set action to unsubscribe.
        $form['foo']['#markup'] = $this->t('You are subscribed to this page.');
        $form['foo']['submit']['#ajax'] = [
          'callback' => [$this, 'ajaxUnsubscribe'],
          'wrapper' => 'subscribe-form-wrapper',
          'effect' => 'fade',
        ];
      }
      elseif ($this->subscriber->isSubscribedPending()) {
        // Set action to unsubscribe.
        $form['foo']['#markup'] = $this->t('Your subscription to this page is pending.');
        $form['foo']['submit']['#ajax'] = [
          'callback' => [$this, 'ajaxCancel'],
          'wrapper' => 'subscribe-form-wrapper',
          'effect' => 'fade',
        ];
      }
    }

    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => $nid,
    ];

    $form['smid'] = [
      '#type' => 'hidden',
      '#value' => $this->subscriber->getSmidByToken($this->subscriber->getToken()),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    if (!$this->subscriber->hasToken() ||
      !$this->subscriber->isValidToken()) {
      $value = $form_state->getValue('email');
      if ($value == !$this->emailValidator->isValid($value)) {
        $form_state->setErrorByName('email',
          $this->t('The email address %mail is not valid.', ['%mail' => $value]));
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // If($form_state->getValue('submit') == 'Unsubscribe'){
    // \Drupal::messenger()->addWarning(
    // t('You have been unsubsribed from this page.'));
    // }elseif($form_state->getValue('submit') == 'Cancel'){
    // \Drupal::messenger()->addWarning(
    // t('Your subscription to this page has been cancelled.'));
    // }elseif($form_state->getValue('submit') == 'Subscribe'){
    // \Drupal::messenger()->addWarning(
    // t('Please confirm your subscription in your email.'));
    // }else{
    // \Drupal::messenger()->addWarning(
    // t('Invalid action!') . $form_state->getValue('submit'));
    // }.
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxProcess(array &$form, FormStateInterface $form_state) {
    $action = $form_state->getValue(['actions', 'button']);
    $nid = $form_state->getValue('nid');
    $smid = $form_state->getValue('smid');
    if ($action == "Subscribe") {
      $this->subscriber->subscribeBySmid($smid, $nid);
      return ($this->ajaxHtml($this->t('You have been added to the subscription for this page.')));
    }
    elseif ($action == "Unsubscribe") {
      $this->subscriber->unsubscribeBySmid($smid, $nid);
      return ($this->ajaxHtml($this->t('You have been removed from the subscription for this page.')));
    }
    elseif ($action == "Cancel") {
      $this->subscriber->unsubscribeBySmid($smid, $nid);
      return ($this->ajaxHtml($this->t('Your subscription for this page has been cancelled.')));
    }
    else {
      return ($this->ajaxHtml($this->t('Invalid Action!')));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxSignup(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');
    $nid = $form_state->getValue('nid');
    $this->subscriber->subscribeByEmail($email, $nid);
    $form_state->setValue('subscribed', TRUE);
    $form_state->setRebuild(TRUE);
    $form['foo']['#markup'] = $this->t('You are subscribed to this page.');
    $form['foo']['submit']['#value'] = $this->t('Unsubscribe');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxSubscribe(array &$form, FormStateInterface $form_state) {
    $smid = $form_state->getValue('smid');
    $nid = $form_state->getValue('nid');
    $this->subscriber->subscribeBySmid($smid, $nid);
    $form_state->setValue('subscribed', TRUE);
    $form_state->setRebuild(TRUE);
    $form['foo']['#markup'] = $this->t('You are subscribed to this page.');
    $form['foo']['submit']['#value'] = $this->t('Unsubscribe');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxUnsubscribe(array &$form, FormStateInterface $form_state) {
    $smid = $form_state->getValue('smid');
    $nid = $form_state->getValue('nid');
    $this->subscriber->unsubscribeBySmid($smid, $nid);
    $form_state->setValue('subscribed', FALSE);
    $form_state->setRebuild(TRUE);
    $form['foo']['#markup'] = $this->t('You are unsubscribed from this page.');
    $form['foo']['submit']['#value'] = $this->t('Subscribe');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxHtml($message) {
    $elem['#tree'] = TRUE;
    $elem['#prefix'] = '<div id="subscribe-form-wrapper">';
    $elem['#suffix'] = '</div>';
    $elem['#markup'] = '<p>' . $message . '</p>';
    return ($elem);
  }

}
