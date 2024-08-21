<?php

namespace Drupal\node_subscribe\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\node_subscribe\Service\NodeSubscribeEmailService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\node_subscribe\Subscriber\Subscriber;
use Drupal\node_subscribe\Subscriber\Token;
use Drupal\path_alias\AliasManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Node subscribe controller class file.
 */
class NodeSubscribeController extends ControllerBase {

  /**
   * Class attribute.
   *
   * @var string
   */
  private $alias;

  /**
   * Class attribute.
   *
   * @var string
   */
  private $token;

  /**
   * Class attribute.
   *
   * @var string
   */
  private $email;

  /**
   * Class attribute.
   *
   * @var string
   */
  private $path;

  /**
   * Class attribute.
   *
   * @var string
   */
  private $nid;

  /**
   * Class attribute.
   *
   * @var string
   */
  private $captchaValue;

  /**
   * Class attribute.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * The form builder.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The http  client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $pathAliasManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A config factory instance.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The language string.
   *
   * @var string
   */
  protected $language;

  /**
   * The mail service.
   *
   * @var \Drupal\node_subscribe\Service\NodeSubscribeEmailService
   */
  protected $mailService;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * ModalFormContactController constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \GuzzleHttp\Client $httpClient
   *   The request stack.
   * @param \Drupal\path_alias\AliasManagerInterface $path_alias
   *   Path alias service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory instance.
   * @param \Drupal\node_subscribe\Service\NodeSubscribeEmailService $mailService
   *   The mail manager instance.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    RequestStack $requestStack,
    Client $httpClient,
    AliasManagerInterface $path_alias,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    NodeSubscribeEmailService $mailService,
    LanguageManagerInterface $languageManager
  ) {
    $this->requestStack = $requestStack;
    $this->httpClient = $httpClient;
    $this->pathAliasManager = $path_alias;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->mailService = $mailService;
    $this->languageManager = $languageManager;
    $this->language = $this->languageManager->getCurrentLanguage()->getId();

    $this->config = $this->configFactory->get('node_subscribe.settings');
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
      $container->get('request_stack'),
      $container->get('http_client'),
      $container->get('path_alias.manager'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('node_subscribe.email.service'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  private function initialize(Request $request) {

    $language = $request->query->get('lang');
    if (!empty($language)) {
      $this->language = $language;
    }

    if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
      $data = json_decode($request->getContent(), TRUE);
      $request->request->replace(is_array($data) ? $data : []);
    }

    $this->alias = $request->get('alias');
    $this->captchaValue = $request->get('captcha_value');

    if ($request->get('email')) {
      $this->email = $request->get('email');
    }
    if ($request->get('token')) {
      $this->token = Token::getHashedToken($request->get('token'));
    }
    if ($this->alias) {
      $this->path = $this->pathAliasManager
        ->getPathByAlias($this->alias);
      $this->nid = $this->getNid($this->alias);
    }

    $subscriber = new Subscriber($this->nid, $this->token);

    if ($this->token && $subscriber->isVerifiedToken($this->token)) {
      $this->email = $subscriber->getEmailByToken($this->token);
    }

    return $subscriber;
  }

  /**
   * {@inheritdoc}
   */
  public function init(Request $request) {
    $this->initialize($request);

    $app_init = [
      'development_mode' => $this->config->get('development_mode'),
      'privacy_url' => $this->configFactory->get('system.site')->get('siteinfo_site_privacy_link'),
      'captcha' => [
        'captcha_required' => $this->config->get('recaptcha_required'),
        'captcha_key' => $this->config->get('recaptcha_key'),
        'captcha_theme' => 'dark',
      ],
      'subscribe_bar_text' => $this->config->get('subscribe_bar_text'),
      'subscribe_button_text' => $this->config->get('subscribe_button_text'),
      'account_manage_button_label_text' => $this->config->get('account_manage_button_label_text'),
      'account_manage_button_text' => $this->config->get('account_manage_button_text'),
      'theme' => $this->config->get('theme'),
      'modal_messages' => [
        'new_subscriber_email_sent' => $this->config->get('subscribe_modal_text.confirm'),
        'subscription_pending_message' => $this->config->get('subscribe_modal_text.pending'),
        'email_validation_error' => $this->config->get('subscribe_modal_text.invalid_email'),
        'manage_modal' => $this->config->get('subscribe_modal_text.manage_modal'),
      ],
      'account_delete_message' => $this->config->get('account_manage_modal_text.account_delete_message'),
    ];

    return new JsonResponse($app_init);
  }

  /**
   * {@inheritdoc}
   */
  public function subscriptionStatus(Request $request) {

    $subscriber = $this->initialize($request);
    $subscription_status = $subscriber->subscriptionStatus();
    $user_status = $subscriber->getUserStatusName($subscriber->getUserStatus($this->token));

    $token_authorized = $subscriber->isAuthorized($this->token);

    // Only if token is verified, value = 1.
    if ($token_authorized['authorized']) {
      $response = [
        'status' => isset($subscription_status[0]) ? $subscription_status[0]->status : NULL,
        'user_status' => $user_status,
        'email' => $subscriber->getEmailByToken($this->token),
      ];
    }
    else {
      // All other status will be handled here.
      switch ($token_authorized['status']) {
        case $subscriber::TOKEN_EXPIRED:
          $response = [
            'user_status' => $user_status,
            'token_status' => 'expired',
            'status' => isset($subscription_status[0]) ? $subscription_status[0]->status : NULL,
            'message' => [
              'title' => "Followed pages session expired",
              'body' => "Please login again using by entering your email and verifying your email.",
            ],
            'actions' => [
              [
                'type' => "btn-button",
                'label' => "OK",
                'callbacks' => ['scroll_to_subscribe_banner', 'logout'],
              ],
            ],
          ];

          break;

        default:
          $response = [
            'user_status' => $user_status,
            'status' => isset($subscription_status[0]) ? $subscription_status[0]->status : NULL,
          ];
      }
    }

    return new JsonResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  public function verification(Request $request) {
    $subscriber = $this->initialize($request);
    $verification_token = Token::getHashedToken($request->get('subscriber'));

    $response = [];

    if ($verification_token) {
      $verification_result = $subscriber->verification($verification_token);
      if ($verification_result) {

        if (!isset($verification_result['error'])) {
          $response['verified'] = TRUE;
          // $response['subscription_token'] = $verification_result;
          // @todo Do not send token back, but this is a hashed token anyway.
          $response['message'] = [
            'title' => $this->config->get('subscribe_modal_text.confirm_success.title'),
            'body' => $this->config->get('subscribe_modal_text.confirm_success.body'),
            'help_text' => $this->config->get('subscribe_modal_text.confirm_success.help_text'),
          ];
          $response['actions'][] = [
            'type' => 'btn-button',
            'label' => $this->config->get('subscribe_modal_text.confirm_success.button'),
            'callbacks' => ['scroll_to_subscribe_banner', 'refresh_status'],
          ];
          // Make all pending subscriptions of this subscriber to enabled.
          $confirm_pending_pages_result = $subscriber->enableAllPendingSubscriptionByToken($verification_result);
          if ($confirm_pending_pages_result && $confirm_pending_pages_result['error']) {
            $response['error'] = $confirm_pending_pages_result['message'];
          }
        }
        else {
          // This happens if user is marked as delete, an error is returned
          // or if it is really an error.
          $response['error'] = $verification_result['error'];
          if ($verification_result['error'] && $verification_result['type'] == 'user_deleted') {
            $response['message'] = [
              'title' => $this->config->get('subscribe_modal_text.confirm_failed_delete.title'),
              'body' => $this->config->get('subscribe_modal_text.confirm_failed_delete.body'),
              'help_text' => $this->config->get('subscribe_modal_text.confirm_failed_delete.help_text'),
            ];
          }
          $response['actions'][] = [
            'type' => 'btn-button',
            'label' => $this->config->get('subscribe_modal_text.confirm_failed.button'),
            'callbacks' => ['logout'],
          ];
        }
      }
      else {
        $response['error'] = TRUE;
        $response['message'] = [
          'title' => $this->config->get('subscribe_modal_text.confirm_failed.title'),
          'body' => $this->config->get('subscribe_modal_text.confirm_failed.body'),
          'help_text' => $this->config->get('subscribe_modal_text.confirm_failed.help_text'),
        ];
        $response['actions'][] = [
          'type' => 'btn-button',
          'label' => $this->config->get('subscribe_modal_text.confirm_failed.button'),
          'callbacks' => ['after_validation'],
        ];
      }
    }

    return new JsonResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  public function subscribe(Request $request) {
    // @todo check what should happen if an email exists but has no token at all (somehow deleted)
    $subscriber = $this->initialize($request);
    // Already ran through hashing.
    $token = $this->token;
    $email = $this->email;
    $nid = $this->nid;

    $response = [];

    // If has token in api payload.
    if ($token) {
      // If the token belongs to a verified email.
      if ($subscriber->isVerifiedToken($token)) {
        $status = $subscriber->subscriptionStatus($this->captchaValue);
        // If user has not subscribed to a page before or
        // request is submitted with a valid captcha value.
        if (empty($status) || $this->reCaptchaIsValid($this->captchaValue)) {
          $subscribe = $subscriber->subscribeBySmid($nid, $token);
          if (!isset($subscribe['error'])) {

            if ($this->config->get('email_settings.send_page_added_email') === 1) {
              // $mailer = new Mailer('send_page_added_email', $nid,
              // $subscriber->getEmailByToken($token));
              // $mailer->sendMail();
              $this->mailService->pageAdded($subscriber->getEmailByToken($token), $nid);
            }

            $response['subscribed'] = TRUE;
            $response['subscription_status'] = $subscribe['status'];
            // Currently not shown anywhere in the UI.
            $response['message'] = $this->t('Thank you, you are subscribed to this page.');
          }
          else {
            $response['subscribed'] = FALSE;
            $response['error'] = TRUE;
            $response['message'] = [
              'title' => $this->config->get('subscribe_modal_text.subscribe_failed.title'),
              'body' => $this->config->get('subscribe_modal_text.subscribe_failed.body'),
              'help_text' => $this->config->get('subscribe_modal_text.subscribe_failed.help_text'),
            ];
            $response['actions'][] = [
              'type' => 'btn-button',
              'label' => $this->config->get('subscribe_modal_text.subscribe_failed.button'),
              'callbacks' => ['close'],
            ];
          }
          // Failed to validate a captcha value with google.
        }
        else {
          $response['error'] = TRUE;
          $response['message'] = [
            'title' => $this->config->get('subscribe_modal_text.captcha_failed.title'),
            'body' => $this->config->get('subscribe_modal_text.captcha_failed.body'),
            'help_text' => $this->config->get('subscribe_modal_text.captcha_failed.help_text'),
          ];
          $response['actions'][] = [
            'type' => 'btn-button',
            'label' => $this->config->get('subscribe_modal_text.captcha_failed.button'),
            'callbacks' => ['close'],
          ];
        }
        // If the token belongs to a unverified email or token (device)
      }
      else {
        $response['verification_required'] = TRUE;
        $response['email'] = $subscriber->getEmailByToken($token);
        $response['message'] = [
          'title' => $this->config->get('subscribe_modal_text.confirm.title'),
          'body' => $this->config->get('subscribe_modal_text.confirm.body'),
          'help_text' => $this->config->get('subscribe_modal_text.confirm.help_text'),
        ];
        $response['actions'][] = [
          'type' => 'btn-button',
          'label' => $this->config->get('subscribe_modal_text.confirm.button'),
          'callbacks' => [],
        ];
        $response['actions'][] = [
          'type' => 'btn-link',
        // @todo fix this email value being false
          'label' => $this->t('Not @email? Use a different email.', ['@email' => $subscriber->getEmailByToken($token)]),
          'callbacks' => ['forget', 'do_not_save', 'remove_subscription_status'],
        ];
      }
      // If called api without a token payload.
    }
    elseif ($email) {
      if ($this->reCaptchaIsValid($this->captchaValue)) {
        // If email exists, existing user.
        if ($subscriber->emailExists($email)) {
          // If email has a verified token, ie. new device.
          if ($subscriber->getTokenByEmail($email, TRUE)) {
            $new_device = $subscriber->createTokenByEmail($email);
            $new_token = $new_device['new_token'];
            $new_secret = $new_device['new_secret'];

            // @todo check to see if it's buggy, might turn an enabled page into
            // pending.
            // Added to subscribe new device user as pending status for the
            // page.
            $subscribe = $subscriber->subscribeBySmid($nid, Token::getHashedToken($new_token), 'pending');

            if ($this->config->get('email_settings.send_new_device_email') === 1) {
              $mailer_extras = [
                'new_secret' => $new_secret,
                'new_token' => $new_token,
              ];
              // $mailer = new Mailer('send_new_device_email', $nid,
              // $email, $mailer_extras);
              // $mailer->sendMail();
              $this->mailService->newDevice($subscriber->getEmailByToken($token), $nid, $mailer_extras);
            }

            $response['subscription_token'] = $new_token;
            $response['new_device'] = TRUE;
            $response['subscription_status'] = $subscribe['status'];
            $response['message'] = [
              'title' => $this->config->get('subscribe_modal_text.new_device.title'),
              'body' => $this->config->get('subscribe_modal_text.new_device.body'),
              'help_text' => $this->config->get('subscribe_modal_text.new_device.help_text'),
            ];
            $response['actions'][] = [
              'type' => 'btn-button',
              'label' => $this->config->get('subscribe_modal_text.new_device.save_button_text'),
              'callbacks' => ['save_cookie'],
            ];
            $response['actions'][] = [
              'type' => 'btn-button',
              'label' => $this->config->get('subscribe_modal_text.new_device.do_not_save_button_text'),
              'callbacks' => [
                'forget',
                'do_not_save',
                'remove_subscription_status',
                'new_subscriber_email_sent_message',
              ],
            ];
            $response['actions'][] = [
              'type' => 'btn-link',
              'label' => $this->t('Not @email? Use a different email.', ['@email' => $email]),
              'callbacks' => [
                'forget',
                'do_not_save',
                'remove_subscription_status',
              ],
            ];
            // Email is never verified.
          }
          else {
            $response['verification_required'] = TRUE;
            $response['email'] = $email;
            $response['message'] = [
              'title' => $this->config->get('subscribe_modal_text.never_verified.title'),
              'body' => $this->config->get('subscribe_modal_text.never_verified.body'),
              'help_text' => $this->config->get('subscribe_modal_text.never_verified.help_text'),
            ];
            $response['actions'][] = [
              'type' => 'btn-button',
              'label' => $this->config->get('subscribe_modal_text.never_verified.button'),
              'callbacks' => [],
            ];
            $response['actions'][] = [
              'type' => 'btn-link',
              'label' => $this->t('Not @email? Use a different email.', ['@email' => $email]),
              'callbacks' => [
                'forget',
                'do_not_save',
                'remove_subscription_status',
              ],
            ];
          }
          // New user.
        }
        else {
          $subscribe = $subscriber->subscribeByEmail($nid, $email);
          if (!isset($subscribe['error'])) {

            if (boolval($this->config->get('email_settings.send_new_user_email')) === TRUE) {
              $secret = $subscribe['subscription_secret'];
              $verification_link = $this->getAbsoluteLink() . '?subscriber=' . $secret;
              $mailer_extras = ['verification_link' => $verification_link];
              $this->mailService->newUser($email, $nid, $mailer_extras);
            }

            $response['subscribed'] = TRUE;
            $response['subscription_status'] = $subscribe['status'];
            $response['subscription_token'] = $subscribe['subscription_token'];
            $response['verification_required'] = TRUE;
            $response['subscription_pending'] = TRUE;

            if (isset($mail_result['error'])) {
              $response['message'] = [
                'title' => $this->config->get('subscribe_modal_text.email_send_failed.title'),
                'body' => $this->config->get('subscribe_modal_text.email_send_failed.body'),
                'help_text' => $this->config->get('subscribe_modal_text.email_send_failed.help_text'),
              ];
              $response['actions'][] = [
                'type' => 'btn-button',
                'label' => $this->config->get('subscribe_modal_text.email_send_failed.button'),
                'callbacks' => [
                  'forget',
                  'do_not_save',
                  'remove_subscription_status',
                ],
              ];
            }
            else {
              $response['message'] = [
                'title' => $this->config->get('subscribe_modal_text.subscribed.title'),
                'body' => $this->config->get('subscribe_modal_text.subscribed.body'),
                'help_text' => $this->config->get('subscribe_modal_text.subscribed.help_text'),
              ];
              $response['actions'][] = [
                'type' => 'btn-button',
                'label' => $this->config->get('subscribe_modal_text.subscribed.save_button_text'),
                'callbacks' => [
                  'save_cookie',
                  'new_subscriber_email_sent_message',
                ],
              ];
              $response['actions'][] = [
                'type' => 'btn-button',
                'label' => $this->config->get('subscribe_modal_text.subscribed.do_not_save_button_text'),
                'callbacks' => [
                  'forget',
                  'do_not_save',
                  'remove_subscription_status',
                  'new_subscriber_email_sent_message',
                ],
              ];
              $response['actions'][] = [
                'type' => 'btn-link',
                'label' => $this->t('Not @email? Use a different email.', ['@email' => $email]),
                'callbacks' => [
                  'forget',
                  'do_not_save',
                  'remove_subscription_status',
                ],
              ];
            }

          }
          else {
            $response['error'] = TRUE;
            // $response['message'] = $this->t('Error adding your email as
            // subscriber, please contact administrator for assistance.');
            $response['message'] = [
              'title' => $this->config->get('subscribe_modal_text.subscribe_failed.title'),
              'body' => $this->config->get('subscribe_modal_text.subscribe_failed.body'),
              'help_text' => $this->config->get('subscribe_modal_text.subscribe_failed.help_text'),
            ];
            $response['actions'][] = [
              'type' => 'btn-button',
              'label' => $this->config->get('subscribe_modal_text.subscribe_failed.button'),
              'callbacks' => ['close'],
            ];
          }
        } // End new user
      }
      else {
        $response['error'] = TRUE;
        // $response['message'] = $this->t('Invalid reCAPTCHA value!');
        $response['message'] = [
          'title' => $this->config->get('subscribe_modal_text.captcha_failed.title'),
          'body' => $this->config->get('subscribe_modal_text.captcha_failed.body'),
          'help_text' => $this->config->get('subscribe_modal_text.captcha_failed.help_text'),
        ];
        $response['actions'][] = [
          'type' => 'btn-button',
          'label' => $this->config->get('subscribe_modal_text.captcha_failed.button'),
          'callbacks' => ['close'],
        ];
      } //end if reCAPTCHA is valid
      // If no token AND no email.
    }
    else {
      $response['error'] = TRUE;
    }

    return new JsonResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  public function unsubscribe(Request $request) {
    $subscriber = $this->initialize($request);
    $email = $this->email;
    $token = $this->token;
    $nid = $this->nid;

    if ($token) {
      if ($subscriber->isVerifiedToken($token)) {
        $unsubscribe = $subscriber->unsubscribeBySmid($nid, $token);

        // @todo might want to check and see if it did unsubscribe successfully
        if ($this->config->get('email_settings.send_page_removed_email') === 1) {
          // $mailer = new Mailer('send_page_removed_email', $nid, $email);
          // $mailer->sendMail();
          $this->mailService->pageRemoved($email, $nid);
        }

        $response['data'] = [
          'subscribe' => $unsubscribe,
          'subscription_status' => 'disabled',
          // @todo assumed success, make sure it is actually disabled.
        ];

        // Token is not verified.
      }
      else {
        $response['message'] = [
          'title' => $this->config->get('subscribe_modal_text.invalid_token.title'),
          'body' => $this->config->get('subscribe_modal_text.invalid_token.body'),
          'help_text' => $this->config->get('subscribe_modal_text.invalid_token.help_text'),
        ];
        $response['actions'][] = [
          'type' => 'btn-button',
          'label' => $this->config->get('subscribe_modal_text.invalid_token.button'),
          'callbacks' => ['close_message_modal', 'after_unsubscribe', 'forget'],
        ];
      }
    }
    else {
      $response['message'] = [
        'title' => $this->config->get('subscribe_modal_text.unsubscribe_failed.title'),
        'body' => $this->config->get('subscribe_modal_text.unsubscribe_failed.body'),
        'help_text' => $this->config->get('subscribe_modal_text.unsubscribe_failed.help_text'),
      ];
      $response['actions'][] = [
        'type' => 'btn-button',
        'label' => $this->config->get('subscribe_modal_text.unsubscribe_failed.button'),
        'callbacks' => ['close_message_modal', 'after_unsubscribe'],
      ];
    }

    return new JsonResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  public function unsubscribeFromEmail(Request $request) {
    $subscriber = $this->initialize($request);
    $token = $request->get('token');
    $secret = Token::getHashedToken($request->get('subscriber'));
    $email = $this->email;
    $nid = $this->nid;

    if ($token) {
      $email = $subscriber->getEmailByToken(Token::getHashedToken($token));

      if ($subscriber->isValidToken($token)) {
        $subscriber->verification($secret, -1);
      }

      if ($subscriber->isVerifiedToken(Token::getHashedToken($token))) {

        // @todo fix this, click cancel in verification email should unsubscribe the pending page, and remove the token
        $unsubscribe = $subscriber->unsubscribeBySmid($nid, Token::getHashedToken($token));
        // $removeOneTimeToken = $subscriber
        // ->removeOneTimeToken(Token::getHashedToken($token));
        // @todo might want to check and see if it did unsubscribe successfully
        if ($this->config->get('email_settings.send_page_removed_email') === 1) {
          // $mailer = new Mailer('send_page_removed_email', $nid, $email);
          // $mailer->sendMail();
          $this->mailService->pageRemoved($email, $nid);
        }

        $response['data'] = [
          'subscribe' => $unsubscribe,
          'subscription_status' => 'disabled',
          // @todo this assumes success, make sure it is actually disabled.
        ];

        $response['message'] = [
          'title' => $this->config->get('subscribe_modal_text.email_unsubscribe_success.title'),
          'body' => $this->config->get('subscribe_modal_text.email_unsubscribe_success.body'),
          'help_text' => $this->config->get('subscribe_modal_text.email_unsubscribe_success.help_text'),
        ];
        $response['actions'][] = [
          'type' => 'btn-button',
          'label' => $this->config->get('subscribe_modal_text.email_unsubscribe_success.button'),
          'callbacks' => ['close_message_modal', 'after_unsubscribe'],
        ];

        // Token is not verified.
      }
      else {
        /*
         * todo: also check to see if the user is already unsubscribed from the
         * page, and display an already unsubscribed message instead.
         * todo: if the token is not valid, it could mean the user is on a
         * shared computer with other people's subscribe token in cookie.
         * Should send email for verification instead of just error out.
         */
        $response['message'] = [
          'title' => $this->config->get('subscribe_modal_text.email_unsubscribe_failed.title'),
          'body' => $this->config->get('subscribe_modal_text.email_unsubscribe_failed.body'),
          'help_text' => $this->config->get('subscribe_modal_text.email_unsubscribe_failed.help_text'),
        ];
        $response['actions'][] = [
          'type' => 'btn-button',
          'label' => $this->config->get('subscribe_modal_text.email_unsubscribe_failed.button'),
          'callbacks' => ['close_message_modal', 'after_unsubscribe'],
        ];
      }
    }
    elseif ($email) {

      $new_device = $subscriber->createTokenByEmail($email);
      $new_token = $new_device['new_token'];
      $new_secret = $new_device['new_secret'];

      if ($this->config->get('email_settings.send_page_remove_confirmation_email') === 1) {
        $mailer_extras = [
          'new_secret' => $new_secret,
          'new_token' => $new_token,
        ];
        // $mailer = new Mailer('send_page_remove_confirmation_email',
        // $nid, $email, $mailer_extras);
        // $mailer->sendMail();
        $this->mailService->pageRemovedConfirm($email, $nid, $mailer_extras);
      }

      // Tell the user we sent an email, and have the user verify the action.
      $response['message'] = [
        'title' => $this->config->get('subscribe_modal_text.email_unsubscribe_from_unverified_device.title'),
        'body' => $this->config->get('subscribe_modal_text.email_unsubscribe_from_unverified_device.body'),
        'help_text' => $this->config->get('subscribe_modal_text.email_unsubscribe_from_unverified_device.help_text'),
      ];
      $response['actions'][] = [
        'type' => 'btn-button',
        'label' => $this->config->get('subscribe_modal_text.email_unsubscribe_from_unverified_device.button'),
        'callbacks' => ['close_message_modal', 'after_unsubscribe'],
      ];

    }
    else {
      $response['message'] = [
        'title' => $this->config->get('subscribe_modal_text.email_unsubscribe_failed.title'),
        'body' => $this->config->get('subscribe_modal_text.email_unsubscribe_failed.body'),
        'help_text' => $this->config->get('subscribe_modal_text.email_unsubscribe_failed.help_text'),
      ];
      $response['actions'][] = [
        'type' => 'btn-button',
        'label' => $this->config->get('subscribe_modal_text.email_unsubscribe_failed.button'),
        'callbacks' => ['close_message_modal', 'after_unsubscribe'],
      ];
    }

    return new JsonResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  public function mySubscriptions(Request $request) {
    $subscriber = $this->initialize($request);
    $subscriptions = $subscriber->getSubscriptions();

    // Get and set alias for each page in subscriptions.
    foreach ($subscriptions as $node) {
      $node->title = $this->getNodeTitle($node->nid);
      $node->alias = $this->getAlias($node->nid);
    }

    $response['subscriptions'] = $subscriptions;

    return new JsonResponse($response);
  }

  /**
   * {@inheritdoc}
   *
   * @todo add configuration settings for all text
   */
  public function accountDelete(Request $request) {
    $subscriber = $this->initialize($request);

    $result = $subscriber->accountDelete();
    if ($result && !isset($result['error'])) {
      $response['message'] = [
        'title' => $this->config->get('account_manage_modal_text.account_delete_requested.title'),
        'body' => $this->config->get('account_manage_modal_text.account_delete_requested.body'),
      ];
    }
    else {
      if (isset($result['error'])) {
        $response = $result['error'];
      }
      else {
        $response['error'] = [
          'title' => $this->config->get('account_manage_modal_text.account_delete_failed.title'),
          'body' => $this->config->get('account_manage_modal_text.account_delete_failed.body'),
        ];
      }
    }

    return new JsonResponse($response);
  }

  /**
   * {@inheritdoc}
   *
   * @todo add configuration setting for all text
   */
  public function accountSuspend(Request $request) {
    $subscriber = $this->initialize($request);

    $action_suspend = $request->get('suspend');
    if (!$request->get('suspend')) {
      $action_suspend = FALSE;
    }

    $result = $subscriber->accountSuspend($action_suspend);
    if ($result && $result['user_status'] === 'suspended') {
      $response['user_status'] = $result['user_status'];
      $response['message'] = [
        'title' => $this->config->get('account_manage_modal_text.account_suspend_requested.title'),
        'body' => $this->config->get('account_manage_modal_text.account_suspend_requested.body'),
      ];
    }
    elseif ($result && $result['user_status'] === 'active') {
      $response['user_status'] = $result['user_status'];
      $response['message'] = [
        'title' => $this->config->get('account_manage_modal_text.account_suspend_removed.title'),
        'body' => $this->config->get('account_manage_modal_text.account_suspend_removed.body'),
      ];
    }
    else {
      if (isset($result['error'])) {
        $response['error'] = [
          'title' => 'Your Request Has Failed',
          'body' => $result['message'],
        ];
      }
      else {
        $response['error'] = [
          'title' => $this->config->get('account_manage_modal_text.account_suspend_failed.title'),
          'body' => $this->config->get('account_manage_modal_text.account_suspend_failed.body'),
        ];
      }
    }

    return new JsonResponse($response);
  }

  /**
   * Helper methods.
   */
  private function getNid($alias) {
    $path = $this->pathAliasManager->getPathByAlias($alias);
    if (preg_match('/node\/(\d+)/', $path, $matches)) {
      $node = $this->entityTypeManager()->getStorage('node')->load($matches[1]);
    }
    if (isset($node)) {
      $nid = $node->nid->value;
      return $nid;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  private function getAlias($nid) {
    $alias = $this->pathAliasManager->getAliasByPath('/node/' . $nid);
    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  private function getNodeTitle($nid) {
    $node = $this->entityTypeManager()->getStorage('node')->load($nid);
    return $node->title->value;
  }

  /**
   * {@inheritdoc}
   */
  private function getAbsoluteLink() {
    // Return \Drupal::request()->getHost() . $this->alias;.
    return $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost() . $this->alias;
  }

  /**
   * {@inheritdoc}
   */
  private function reCaptchaIsValid($reCaptcha_token) {

    $reCaptcha_enabled = $this->config->get('recaptcha_required');
    $reCaptcha_secret = $this->config->get('recaptcha_secret');
    $google_validation_url = 'https://www.google.com/recaptcha/api/siteverify';
    if ($reCaptcha_enabled) {
      try {
        $response = $this->httpClient->post(
          $google_validation_url,
          [
            'verify' => FALSE,
            'headers' => [
              'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => [
              'secret' => $reCaptcha_secret,
              'response' => $reCaptcha_token,
            ],
          ]
        );
        $data = (string) $response->getBody();
        if (empty($data)) {
          return FALSE;
        }
        else {
          $decoded_data = json_decode((string) $response->getBody());
          // print_r($decoded_data);
          if ($decoded_data->success) {
            return TRUE;
          }
          else {
            return FALSE;
          }
        }
      }
      catch (RequestException $e) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
