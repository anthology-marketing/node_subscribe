<?php

namespace Drupal\node_subscribe\Subscriber;

use Drupal\Driver\Exception\Exception;
use Drupal\node_subscribe\Subscriber\Token;

/**
 * File for subscriber.
 */
class Subscriber {

  // Status for a user's account.
  // User account that is active.
  const ACTIVE = 1;
  // User account that is pending - no verified token.
  const PENDING = 0;
  // User account that is suspended.
  const SUSPENDED = -1;
  // User account that is marked for deletion.
  const DELETE = -2;

  // Verified for a user's account.
  // A user is verified when it has 1 or more verified tokens.
  const ACC_VERIFIED = 1;
  // A user is not verified when it has 0 verified tokens.
  const ACC_NOT_VERIFIED = 0;

  // Status for a user's device token.
  // Token that is verified by going through emailed link.
  const TOKEN_VERIFIED = 1;
  // Token that is new or not verified yet.
  const TOKEN_UNVERIFIED = 0;
  // Token that is not saved in browser.
  const TOKEN_ONETIME = -1;
  // Token that is marked for deletion.
  const TOKEN_DELETE = -2;
  // Token that is marked as expired.
  const TOKEN_EXPIRED = -3;

  // Status for a user's subscription to a node.
  // Status when a user unsubscribes from a node.
  const PAGE_DISABLED = 'disabled';
  // Status when a user subscribes to a node.
  const PAGE_ENABLED = 'enabled';
  // Status when a user uses an unverified token to subscribe to a node.
  const PAGE_PENDING = 'pending';

  const SUBSCRIBERS_TABLE = 'node_subscription_manager';
  const SUBSCRIPTIONS_TABLE = 'node_subscription';
  const TOKENS_TABLE = 'node_subscription_tokens';


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
  private $nid;

  /**
   * Class attribute.
   *
   * @var string
   */
  private $smid;

  /**
   * Class attribute.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $connection;

  /**
   * Class attribute.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * {@inheritdoc}
   */
  public function __construct($nid, $token = NULL, $smid = NULL) {
    $this->config = \Drupal::config('node_subscribe.settings');
    $this->token = $token;
    $this->nid = $nid;
    $this->smid = $smid;
    $this->connection = $connection = \Drupal::database();
  }

  /**
   * {@inheritdoc}
   */
  public function getNid() {
    return $this->nid;
  }

  /**
   * {@inheritdoc}
   */
  public function hasToken() {
    return isset($this->token);
  }

  /**
   * {@inheritdoc}
   */
  public function getToken() {
    return $this->token;
  }

  /**
   * {@inheritdoc}
   */
  public function isValidToken($token = NULL) {
    $connection = \Drupal::database();

    if ($token === NULL) {
      $token = $this->token;
    }
    $select = $connection->select('node_subscription_tokens', 'tokens');
    $select->addField('tokens', 'token');
    $select->condition('tokens.token', Token::getHashedToken($token));
    $result = $select->execute()->fetchAll();

    return count($result);
  }

  /**
   * Create new token for users.
   */
  public function generateToken() {
    do {
      // $new_token = md5(uniqid(rand(), true));
      $new_token = Token::generateToken();
    } while ($this->isValidToken($new_token) > 0);

    return $new_token;
  }

  /**
   * {@inheritdoc}
   */
  public function emailExists($email) {
    $connection = \Drupal::database();

    $select = $connection->select('node_subscription_manager', 'subscribers');
    $select->addField('subscribers', 'email');
    $select->condition('subscribers.email', $email);
    $result = $select->execute()->fetchAll();

    return count($result);
  }

  /**
   * {@inheritdoc}
   */
  public function isVerifiedToken($hashedToken) {
    $connection = \Drupal::database();

    $select = $connection->select('node_subscription_tokens', 'tokens');
    $select->addField('tokens', 'verified');
    $select->condition('tokens.token', $hashedToken);
    $result = $select->execute()->fetchAll();
    if (count($result) && $result[0]->verified != 0) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isAuthorized($hashedToken) {
    $connection = \Drupal::database();

    $select = $connection->select('node_subscription_tokens', 'tokens');
    $select->addField('tokens', 'verified');
    $select->addField('tokens', 'created');
    $select->condition('tokens.token', $hashedToken);
    $result = $select->execute()->fetchAll();

    $authorized = [
      'authorized' => FALSE,
    ];

    if ($result) {
      if ($result[0]->created && $this->isExpired($result[0]->created)) {
        $update = $connection->update('node_subscription_tokens');
        $update->fields(['verified' => self::TOKEN_EXPIRED])
          ->condition('token', $hashedToken)->execute();
        $authorized['reason'] = 'token expired';
        $authorized['status'] = self::TOKEN_EXPIRED;
      }
      else {
        switch ($result[0]->verified) {
          case self::TOKEN_ONETIME:
            $authorized['reason'] = 'one time token used';
            $authorized['status'] = self::TOKEN_ONETIME;
            break;

          case self::TOKEN_DELETE:
            $authorized['reason'] = 'token as been invalidated';
            $authorized['status'] = self::TOKEN_DELETE;
            break;

          case self::TOKEN_EXPIRED:
            $authorized['reason'] = 'token expired';
            $authorized['status'] = self::TOKEN_EXPIRED;
            break;

          case self::TOKEN_UNVERIFIED:
            $authorized['reason'] = 'token not verified';
            $authorized['status'] = self::TOKEN_UNVERIFIED;
            break;

          case self::TOKEN_VERIFIED:
            $authorized['authorized'] = TRUE;
            break;

          default:
            $authorized['reason'] = 'token not found';
            $authorized['status'] = NULL;
            break;
        }
      }
    }

    return $authorized;
  }

  /**
   * {@inheritdoc}
   */
  private function isExpired($timestamp) {
    $current_time = time();
    $expires_after = $this->config->get('tokens_expire_after');
    if ($expires_after > 0) {
      // 1.577e+7
      if (($current_time - $timestamp) > $expires_after) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function subscriptionStatus($email = NULL) {
    $connection = \Drupal::database();

    if ($this->hasToken()) {
      if ($this->isVerifiedToken($this->token)) {
        $select = $connection->select('node_subscription', 'subscriptions');
        $select->join('node_subscription_manager', 'subscribers',
          'subscriptions.smid = subscribers.smid');
        $select->join('node_subscription_tokens', 'tokens',
          'subscriptions.smid = tokens.smid');
        $select->addField('subscriptions', 'smid');
        $select->addField('subscriptions', 'nid');
        $select->addField('subscriptions', 'status');
        $select->addField('subscribers', 'email');
        $select->addField('subscribers', 'status', 'user_status');
        $select->addField('tokens', 'token');
        $select->condition('subscriptions.nid', $this->nid);
        $select->condition('tokens.token', $this->token);

        $result = $select->execute()->fetchAll();
        return $result;
        // Token not verified.
      }
      else {

        $select = $connection->select('node_subscription', 'subscriptions');
        $select->join('node_subscription_manager', 'subscribers', 'subscriptions.smid = subscribers.smid');
        $select->join('node_subscription_tokens', 'tokens', 'subscriptions.smid = tokens.smid');
        $select->addField('subscriptions', 'smid');
        $select->addField('subscriptions', 'nid');
        $select->addField('subscriptions', 'status');
        $select->addField('subscribers', 'email');
        $select->addField('tokens', 'token');
        $select->condition('subscriptions.status', 'pending');
        $select->condition('subscriptions.nid', $this->nid);
        $select->condition('tokens.token', $this->token);

        $result = $select->execute()->fetchAll();
        return $result;
        // $email = $this->getEmailByToken($this->token);
        // return $email;
      }
    }
  }

  /**
   * Create and save new token for users.
   */
  public function createTokenByEmail($email, $client_data = NULL) {
    $connection = \Drupal::database();
    $txn = $this->connection->startTransaction();

    $new_token = $this->generateToken();
    $new_secret = $this->generateToken();
    $smid = $this->getSmidByEmail($email);

    try {
      $connection->insert('node_subscription_tokens')
        ->fields([
          'smid' => $smid,
          // 'verified' => time(),
          'token' => Token::getHashedToken($new_token),
          'secret' => Token::getHashedToken($new_secret),
          'changed' => time(),
          'created' => time(),
        ])->execute();
      // 'device_finger_print' => '38239232',
      // 'device' => 'Mac2',
      // 'operating_system' => 'Mac OS',
      // 'browser' => 'Chrome',
      // 'user_agent' => 'Something',
      // 'ip' => '111.111.111',
      return ([
        'new_token' => $new_token,
        'new_secret' => $new_secret,
      ]);
    }
    catch (\Exception $e) {
      $txn->rollBack();
      return ([
        'error' => TRUE,
        'message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Verification function.
   *
   * $verification_token must be a hashed secret token status:
   * 0 = unverified
   * 1 = verified
   * -1 = temporary (one-time use)
   * -2 = to be deleted
   * returns a hashed device token.
   */
  public function verification($verification_token, $status = 1) {
    $connection = \Drupal::database();
    $txn = $this->connection->startTransaction();

    $smid = $this->getSmidBySecret($verification_token);
    try {

      // Prevents subscribers who marked their user to be deleted from verifying
      // new tokens.
      if ($this->getSubscriberStatus(NULL, $verification_token) >= self::SUSPENDED) {
        $verification_success = $connection->update('node_subscription_tokens')
          ->fields([
            'verified' => $status,
          ])
          ->condition('secret', $verification_token)
          ->execute();

        if ($verification_success && $status == 1) {
          $connection->update('node_subscription_manager')
            ->fields([
              'verified' => self::ACC_VERIFIED,
              'status' => self::ACTIVE,
              'changed' => time(),
            ])
            ->condition('status', self::SUSPENDED, '>=')
            ->condition('smid', $smid, '=')
            ->execute();
        }

        $select = $connection->select('node_subscription_tokens', 'tokens');
        $select->fields('tokens', ['token', 'verified']);
        $select->condition('tokens.secret', $verification_token);
        $select->condition('tokens.verified', 1);
        $result = $select->execute()->fetchAll();
      }
      else {
        throw new \Exception("ACC_ALREADY_DELETED");
      }

      if (count($result)) {
        return $result[0]->token;
      }
      return FALSE;

    }
    catch (\Exception $e) {
      $txn->rollBack();
      return ([
        'error' => TRUE,
        'type' => 'user_deleted',
        'message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSubscriberStatus($smid = NULL, $token = NULL) {
    $connection = \Drupal::database();

    $result = NULL;
    if ($smid) {
      $query = $connection->select('node_subscription_manager', 's');
      $query->fields('s', ['status']);
      $query->condition('s.smid', $smid, '=');
      $result = $query->execute()->fetchAll();
    }
    elseif ($token) {
      $smid = $this->getSmidBySecret($token);
      $query = $connection->select('node_subscription_manager', 's');
      $query->fields('s', ['status']);
      $query->condition('s.smid', $smid, '=');
      $result = $query->execute()->fetchAll();
    }

    if (isset($result[0]->status)) {
      return $result[0]->status;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function enableAllPendingSubscriptionByToken($token) {
    $connection = \Drupal::database();
    $txn = $this->connection->startTransaction();

    $smid = $this->getSmidByToken($token);
    try {

      $result = $connection->update('node_subscription')
        ->fields(['status' => 'enabled'])
        ->condition('smid', $smid, "=")
        ->condition('status', self::PAGE_PENDING, "=")
        ->execute();

      if ($result) {
        return TRUE;
      }

      throw new \Exception("Error enabling pending pages created by this token: " . $token);

    }
    catch (\Exception $e) {
      $txn->rollBack();
      return ([
        'error' => TRUE,
        'message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Token, $token - must be hashed token.
   */
  public function subscribeBySmid($nid, $token, $status = 'enabled') {
    $connection = \Drupal::database();
    $txn = $this->connection->startTransaction();

    $smid = $this->getSmidByToken($token);
    $email = $this->getEmailByToken($token);
    $tid = $this->getTokenIdByToken($token);

    try {
      $result = $connection->merge('node_subscription')
        ->keys(['nid' => $nid, 'smid' => $smid])
        ->insertFields([
          'smid' => $smid,
          'nid' => $nid,
          'tid' => $tid,
          'status' => $status,
          'created' => time(),
        ])
        ->updateFields([
          'status' => $status,
          'tid' => $tid,
          'changed' => time(),
        ])
        ->execute();

      // @todo make sure this is the way to check error on db_update
      if (!$result) {
        $error = 'Error subscribing to' . $nid . ' for ' . $email;
        throw new \Exception($error);
      }

      return([
        "nid" => $nid,
        "status" => $status,
      ]);

    }
    catch (\Exception $e) {
      $txn->rollBack();
      \Drupal::logger('node_subscribe')->error($e->getMessage());
      return ([
        'error' => TRUE,
        'message' => $e->getMessage(),
      ]);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function subscribeByEmail($nid, $email) {
    $connection = \Drupal::database();
    $txn = $this->connection->startTransaction();

    // If user (email) already in system.
    if (!$this->emailExists($email)) {
      try {
        $connection->insert('node_subscription_manager')
          ->fields([
            'email' => $email,
            'created' => time(),
            'langcode' => $this->getLanguage(),
          ])
          ->execute();

        $new_device = $this->createTokenByEmail($email);
        $subscribe = $this->subscribeBySmid($nid, Token::getHashedToken($new_device['new_token']), 'pending');

        // @todo null check $subscribe
        return ([
          'new_user' => TRUE,
          'new_browser' => TRUE,
          'status' => $subscribe['status'],
          'subscription_token' => $new_device['new_token'],
          'subscription_secret' => $new_device['new_secret'],
        ]);
      }
      catch (Exception $e) {
        $txn->rollBack();
        \Drupal::logger('node_subscribe')->error($e->getMessage());
        return ([
          'error' => TRUE,
          'message' => $e->getMessage(),
        ]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function unsubscribeBySmid($nid, $token = NULL) {
    $connection = \Drupal::database();
    $txn = $this->connection->startTransaction();
    $smid = NULL;
    if ($token) {
      $smid = $this->getSmidByToken($token);
    }
    else {
      $smid = $this->smid;
    }

    try {
      $connection->update('node_subscription')
        ->fields([
          'status' => 'disabled',
        ])
        ->condition('smid', $smid)
        ->condition('$nid', $nid)
        ->execute();
    }
    catch (Exception $e) {
      $txn->rollBack();
      \Drupal::logger('node_subscribe')->error($e->getMessage());
      return ([
        'error' => TRUE,
        'message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function unsubscribeByEmail($email, $nid) {

  }

  /**
   * {@inheritdoc}
   */
  public function getSubscriptions() {
    $connection = \Drupal::database();

    $token = $this->token;
    $smid = $this->getSmidByToken($token);

    if ($this->isVerifiedToken($token)) {
      $select = $connection->select('node_subscription', 'subscriptions');
      $select->addField('subscriptions', 'nid');
      $select->addField('subscriptions', 'status');
      $select->condition('subscriptions.smid', $smid);
      $select->condition('subscriptions.status', 'enabled');
      $result = $select->execute()->fetchAll();

      if (count($result)) {
        return $result;
      }
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function accountDelete() {
    $connection = \Drupal::database();
    $txn = $connection->startTransaction();

    $token = $this->token;
    $smid = $this->getSmidByToken($token);

    if ($this->isVerifiedToken($token)) {
      try {
        $result = $connection->update('node_subscription_manager')
          ->fields([
            'status' => self::DELETE,
            'changed' => time(),
          ])
          ->condition('smid', $smid)
          ->condition('status', self::DELETE, '!=')
          ->execute();

        $delete_tokens = $connection->update('node_subscription_tokens')
          ->fields([
            'verified' => self::TOKEN_DELETE,
            'changed' => time(),
          ])
          ->condition('smid', $smid)
          ->condition('verified', self::TOKEN_DELETE, '!=')
          ->execute();

        $disable_pages = $connection->update('node_subscription')
          ->fields([
            'status' => self::PAGE_DISABLED,
            'changed' => time(),
          ])
          ->condition('smid', $smid)
          ->condition('status', self::PAGE_DISABLED, '!=')
          ->execute();

        if ($result) {
          return [
            'account' => $result,
            'tokens' => $delete_tokens,
            'pages' => $disable_pages,
          ];
        }

        throw new \Exception("Something went wrong while trying to delete user '$smid'");

      }
      catch (\Exception $e) {

        $txn->rollBack();
        \Drupal::logger('node_subscribe')->error($e->getMessage());
        return ([
          'error' => TRUE,
          'message' => $e->getMessage(),
        ]);
      }
    }

    return ([
      'error' => TRUE,
      'error_type' => 'Invalid Token',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function accountSuspend($suspend = TRUE) {
    $connection = \Drupal::database();
    $txn = $connection->startTransaction();

    $token = $this->token;
    $smid = $this->getSmidByToken($token);

    try {
      $result = $connection->update('node_subscription_manager')
        ->fields([
          'status' => $suspend === TRUE ? self::SUSPENDED : self::ACTIVE,
          'changed' => time(),
        ])
        ->condition('smid', $smid)
        ->condition('status', self::PENDING, '!=')
        ->condition('status', self::DELETE, '!=')
        ->execute();

      if ($result) {
        return [
          'user_status' => $this->getUserStatusName($this->getUserStatus($this->token)),
        ];
      }

      throw new \Exception("Something went wrong while trying to suspend user notifications.");

    }
    catch (\Exception $e) {

      $txn->rollBack();
      \Drupal::logger('node_subscribe')->error($e->getMessage());
      return ([
        'error' => TRUE,
        'message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getForm() {

    $form = \Drupal::formBuilder()
      ->getForm('\Drupal\node_subscribe\Form\NodeSubscribeForm', $this);
    $form['#cache'] = ['max-age' => 0];

    return $form;

  }

  /**
   * Cleaners.
   */
  public function removeOneTimeToken($token) {
    $query = \Drupal::database()->delete('node_subscription_tokens');
    $query->condition('token', $token, '=');
    $query->condition('verified', -1, '=');
    $query->execute();
  }

  /**
   * Getters.
   */
  public function getTokenIdByToken($token) {
    $connection = \Drupal::database();

    $select = $connection->select('node_subscription_tokens', 'tokens');
    $select->addField('tokens', 'token_id');
    $select->condition('tokens.token', $token, '=');
    $result = $select->execute()->fetchAll();

    if (count($result) > 0) {
      return $result[0]->token_id;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSmidByToken($token) {
    $connection = \Drupal::database();

    $select = $connection->select('node_subscription_tokens', 'tokens');
    $select->addField('tokens', 'smid');
    $select->condition('tokens.token', $token);
    $result = $select->execute()->fetchAll();

    if (count($result) > 0) {
      return $result[0]->smid;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSmidBySecret($token) {
    $connection = \Drupal::database();

    $select = $connection->select('node_subscription_tokens', 'tokens');
    $select->addField('tokens', 'smid');
    $select->condition('tokens.secret', $token);
    $result = $select->execute()->fetchAll();

    if (count($result) > 0) {
      return $result[0]->smid;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSmidByEmail($email) {
    $connection = \Drupal::database();

    $select = $connection->select('node_subscription_manager', 'subscribers');
    $select->addField('subscribers', 'smid');
    $select->condition('subscribers.email', $email);
    $result = $select->execute()->fetchAll();

    if (count($result)) {
      return $result[0]->smid;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenByEmail($email, $verified = FALSE) {
    $connection = \Drupal::database();

    $select = $connection->select('node_subscription_manager', 'subscribers');
    $select->join('node_subscription_tokens', 'tokens',
      'subscribers.smid = tokens.smid');
    $select->addField('subscribers', 'smid');
    $select->addField('tokens', 'token');
    $select->condition('subscribers.email', $email);
    if ($verified) {
      $select->condition('tokens.verified', 0, '!=');
    }
    $result = $select->execute()->fetchAll();

    if (count($result)) {
      return $result[0]->token;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getEmailByToken($token) {
    $connection = \Drupal::database();

    $select = $connection->select('node_subscription_tokens', 'tokens');
    $select->join('node_subscription_manager', 'subscribers',
      'tokens.smid = subscribers.smid');
    $select->addField('subscribers', 'email');
    $select->condition('tokens.token', $token);
    $result = $select->execute()->fetchAll();

    if (count($result)) {
      return $result[0]->email;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserStatus($token) {
    $connection = \Drupal::database();

    $select = $connection->select('node_subscription_tokens', 'tokens');
    $select->join('node_subscription_manager', 'subscribers',
      'tokens.smid = subscribers.smid');
    $select->addField('subscribers', 'status', 'user_status');
    $select->condition('tokens.token', $token);
    $result = $select->execute()->fetchAll();

    if (count($result)) {
      return $result[0]->user_status;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserStatusName($status_value) {
    $status = 'unknown';
    switch ($status_value) {
      case self::DELETE:
        $status = 'delete';
        break;

      case self::SUSPENDED:
        $status = 'suspended';
        break;

      case self::PENDING:
        $status = 'pending';
        break;

      case self::ACTIVE:
        $status = 'active';
        break;
    }
    return $status;
  }

  /**
   * Function to get the language from the request.
   */
  private function getLanguage() {
    $language = \Drupal::request()->request->get('lang');
    if (empty($language)) {
      $language = \Drupal::languageManager()->getDefaultLanguage()->getId();
    }

    return $language;
  }

}
