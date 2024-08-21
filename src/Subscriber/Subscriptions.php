<?php

namespace Drupal\node_subscribe\Subscriber;

use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Xss;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class subscription for subscriptions.
 */
class Subscriptions {

  use StringTranslationTrait;

  /**
   * Module table name constants.
   */
  const SUBSCRIBER_TABLE = 'node_subscription_manager';
  const SUBSCRIPTION_TABLE = 'node_subscription';
  const TOKEN_TABLE = 'node_subscription_tokens';

  /**
   * Class attribute.
   *
   * @var string
   */
  private $smid = NULL;

  /**
   * Class attribute.
   *
   * @var string
   */
  private $email = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct($smid) {
    $this->smid = $smid;
    $subscriber_details = self::getSubscriberDetailsBySmid($smid);
    if ($subscriber_details) {
      $this->email = $subscriber_details[0]->email;
    }
  }

  /**
   * Returns the total number of subscribers.
   */
  public static function subscriberTotal() {

    $subscriberTable = self::SUBSCRIBER_TABLE;

    $connection = \Drupal::database();
    $result = $connection
      ->query("SELECT * FROM {$subscriberTable}")
      ->fetchAll();

    return count($result);
  }

  /**
   * Returns the total number of subscribed pages.
   */
  public static function subscribedPageTotal() {

    $subscriptionTable = self::SUBSCRIPTION_TABLE;

    $connection = \Drupal::database();
    $result = $connection
      ->query("SELECT DISTINCT nid FROM {$subscriptionTable}")
      ->fetchAll();

    return count($result);
  }

  /**
   * Returns an array of subscribed count by pages.
   */
  public static function subscribedCountByPages($by_product = NULL) {

    $subscriptionTable = self::SUBSCRIPTION_TABLE;

    $connection = \Drupal::database();
    $nid_distinct_list = $connection
      ->query("SELECT DISTINCT nid FROM {$subscriptionTable}")
      ->fetchAll();

    $nid_distinct_list_count = $connection
      ->query("SELECT COUNT (DISTINCT nid) as counter FROM {$subscriptionTable}")
      ->fetchAll();

    $total_enabled = 0;
    $total_disabled = 0;
    $total_pending = 0;

    $nodes = [];
    foreach ($nid_distinct_list as $value) {
      $nid = $value->nid;
      // Test.
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()
        ->getStorage('node')->load($nid);
      $product = 'null';
      if (is_null($node))  {
        continue;
      }
      if ($node->hasField('field_product')) {
        if ($node->get('field_product')->getValue()) {
          $product_term = Term::load($node->get('field_product')->getValue()[0]['target_id']);
          if ($product_term) {
            $product = $product_term->getName();
          }
        }
      }
      $status = $node->get('status')->getValue()[0]['value'];

      $subscription_count_by_page_enabled = $connection
        // @codingStandardsIgnoreLine
        ->query("SELECT COUNT (nid) as page_count FROM {$subscriptionTable} WHERE nid = :nid && status = :status", [':nid' => $nid, ':status' => 'enabled'])
        ->fetchAll()[0]->page_count;

      $subscription_count_by_page_disabled = $connection
        // @codingStandardsIgnoreLine
        ->query("SELECT COUNT (nid) as page_count FROM {$subscriptionTable} WHERE nid = :nid && status = :status", [':nid' => $nid, ':status' => 'disabled'])
        ->fetchAll()[0]->page_count;

      $subscription_count_by_page_pending = $connection
        // @codingStandardsIgnoreLine
        ->query("SELECT COUNT (nid) as page_count FROM {$subscriptionTable} WHERE nid = :nid && status = :status", [':nid' => $nid, ':status' => 'pending'])
        ->fetchAll()[0]->page_count;

      $node_data = [
        'title' => $node->getTitle(),
        'product' => $product,
        'status' => $status,
        'subscription_count' => $subscription_count_by_page_enabled,
        'subscription_count_disabled' => $subscription_count_by_page_disabled,
        'subscription_count_pending' => $subscription_count_by_page_pending,
      ];

      // If has by product filter.
      if ($by_product) {
        if ($product == $by_product) {
          $nodes['nodes'][$nid] = $node_data;
          $total_enabled += $subscription_count_by_page_enabled;
          $total_disabled += $subscription_count_by_page_disabled;
          $total_pending += $subscription_count_by_page_pending;
        }
      }
      else {
        $nodes['nodes'][$nid] = $node_data;
        $total_enabled += $subscription_count_by_page_enabled;
        $total_disabled += $subscription_count_by_page_disabled;
        $total_pending += $subscription_count_by_page_pending;
      }
    }
    $nodes['total'] = [
      'enabled' => $total_enabled,
      'disabled' => $total_disabled,
      'pending' => $total_pending,
    ];
    $nodes['counter'] = $nid_distinct_list_count[0]->counter;

    return $nodes;
  }

  /**
   * {@inheritdoc}
   */
  public static function subscribedCountByProduct() {

    $nodes_list = self::loadSubscribedNodes();

    $product_list_by_page = [];
    foreach ($nodes_list as $node) {

      $product = 'null';
      if (!is_null($node['node']) && $node['node']->hasField('field_product') && $node['node']->get('field_product')->getValue()) {
        $product_term = Term::load($node['node']->get('field_product')->getValue()[0]['target_id']);
        $status = $node['extra']->status;
        if ($product_term) {
          $product = $product_term->getName();
        }
      }
      $product_list_by_page[] = [
        'product' => $product,
        'status' => $status,
      ];
    }

    $list_by_product = self::arrayGroupBy($product_list_by_page, 'product');
    $result = [];
    $enabled_total = 0;
    $disabled_total = 0;
    $pending_total = 0;
    foreach ($list_by_product as $product => $status) {
      $status_by_product = self::arrayGroupBy($status, 'status');
      $count_enabled = isset($status_by_product['enabled']) ? count($status_by_product['enabled']) : 0;
      $count_disabled = isset($status_by_product['disabled']) ? count($status_by_product['disabled']) : 0;
      $count_pending = isset($status_by_product['pending']) ? count($status_by_product['pending']) : 0;
      $result[$product] = [
        'enabled' => $count_enabled,
        'disabled' => $count_disabled,
        'pending' => $count_pending,
      ];
      $enabled_total += $count_enabled;
      $disabled_total += $count_disabled;
      $pending_total += $count_pending;
    }
    $result['Total'] = [
      'enabled' => $enabled_total,
      'disabled' => $disabled_total,
      'pending' => $pending_total,
    ];

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public static function subscribedPagesByProduct() {

  }

  /**
   * {@inheritdoc}
   */
  public static function allSubscribersTokens($header) {

    $subscriberTable = self::SUBSCRIBER_TABLE;
    $tokenTable = self::TOKEN_TABLE;

    $connection = \Drupal::database();
    $query = $connection->select($subscriberTable, 'subscribers');
    $query->fields('subscribers', ['smid', 'email']);
    $query->fields('tokens', ['verified', 'token']);
    $query->join($tokenTable, 'tokens', 'subscribers.smid = tokens.smid');
    $table_sort = $query->extend('\Drupal\Core\Database\Query\TableSortExtender')->orderByHeader($header);
    $pager = $table_sort->extend('\Drupal\Core\Database\Query\PagerSelectExtender')->limit(20);
    $result = $pager->execute()->fetchAll();

    $result_array = [];
    foreach ($result as $row) {

      $token_string = (str_repeat('*', 30) . substr($row->token, mb_strlen($row->token) * 0.8, mb_strlen($row->token)));
      // -1 is one-time token
      $verified_string = ($row->verified == 1 ? 'Yes' : ($row->verified == 0 ? 'No' : 'No - one-time token'));
      // <a class="button button--danger" href=":delete">@delete</a>
      $action_buttons_markup = '<a class="button" href=":view">@view</a>';
      $args = [
        ':view' => Url::fromRoute('node_subscribe.view_subscriber_details', ['smid' => $row->smid],
          ['query' => ['destination' => \Drupal::request()->getRequestUri() . '#all-subscribers-table']])->toString(),
        '@view' => t('View'),
      ];
      $action_buttons_html = new FormattableMarkup($action_buttons_markup, $args);

      $result_array[$row->smid] = [
        'smid' => $row->smid,
        'email' => $row->email,
        'token' => $token_string,
        'verified' => $verified_string,
        'delete' => $action_buttons_html,
      ];
    }
    $subscribers['rows'] = $result_array;
    $subscribers['pager'] = $pager;

    return $subscribers;
  }

  /**
   * {@inheritdoc}
   */
  public static function allSubscribers($header) {

    $subscriberTable = self::SUBSCRIBER_TABLE;

    $connection = \Drupal::database();
    $query = $connection->select($subscriberTable, 'subscribers');
    $query->fields('subscribers', [
      'smid',
      'email',
      'changed',
      'created',
      'verified',
      'status',
      'langcode',
    ]);
    $table_sort = $query->extend('\Drupal\Core\Database\Query\TableSortExtender')->orderByHeader($header);
    $pager = $table_sort->extend('\Drupal\Core\Database\Query\PagerSelectExtender')->limit(20);
    $result = $pager->execute()->fetchAll();

    $result_array = [];
    foreach ($result as $row) {

      // -1 is one-time toke
      $verified_string = ($row->verified == 1 ? t('Yes') : t('No'));
      switch ($row->status) {
        case Subscriber::DELETE:
          $status_string = 'Delete';
          break;

        case Subscriber::SUSPENDED:
          $status_string = 'Suspended';
          break;

        case Subscriber::PENDING:
          $status_string = 'Pending';
          break;

        case Subscriber::ACTIVE:
          $status_string = 'Active';
          break;

        default:
          $status_string = 'Pending';
          break;
      }

      $action_buttons_markup = '<a class="button" href=":view">@view</a><a class="button button--danger" href=":delete">@delete</a>';
      $args = [
        ':view' => Url::fromRoute('node_subscribe.view_subscriber_details', ['smid' => $row->smid],
          ['query' => ['destination' => \Drupal::request()->getRequestUri() . '#all-subscribers-table']])->toString(),
        '@view' => t('View'),
        ':delete' => Url::fromRoute('node_subscribe.manage_subscribers_form_delete', ['smid' => $row->smid],
          ['query' => ['destination' => \Drupal::request()->getRequestUri() . '#all-subscribers-table']])->toString(),
        '@delete' => t('Delete'),
      ];
      $action_buttons_html = new FormattableMarkup($action_buttons_markup, $args);

      $result_array[$row->smid] = [
        'smid' => $row->smid,
        'email' => $row->email,
        'changed' => $row->changed == 0 ? 'never' : \Drupal::service('date.formatter')->format($row->changed, 'date_text'),
        'created' => \Drupal::service('date.formatter')->format($row->created, 'date_text'),
        'verified' => $verified_string,
        'status' => $status_string,
        'delete' => $action_buttons_html,
      ];
    }
    $subscribers['rows'] = $result_array;
    $subscribers['pager'] = $pager;

    return $subscribers;
  }

  /**
   * {@inheritdoc}
   */
  public static function toBeDeleteSubscribers($header) {

    $subscriberTable = self::SUBSCRIBER_TABLE;

    $connection = \Drupal::database();
    $query = $connection->select($subscriberTable, 'subscribers');
    $query->fields('subscribers', [
      'smid',
      'email',
      'changed',
      'created',
      'verified',
      'status',
    ]);
    $query->condition('status', Subscriber::DELETE, '=');
    $table_sort = $query->extend('\Drupal\Core\Database\Query\TableSortExtender')->orderByHeader($header);
    $pager = $table_sort->extend('\Drupal\Core\Database\Query\PagerSelectExtender')->limit(20);
    $result = $pager->execute()->fetchAll();

    $result_array = [];
    foreach ($result as $row) {

      // -1 is one-time toke
      $verified_string = ($row->verified == 1 ? t('Yes') : t('No'));
      switch ($row->status) {
        case Subscriber::DELETE:
          $status_string = 'Delete';
          break;

        case Subscriber::SUSPENDED:
          $status_string = 'Suspended';
          break;

        case Subscriber::PENDING:
          $status_string = 'Pending';
          break;

        case Subscriber::ACTIVE:
          $status_string = 'Active';
          break;

        default:
          $status_string = 'Pending';
          break;
      }

      $action_buttons_markup = '<a class="button" href=":view">@view</a><a class="button button--danger" href=":delete">@delete</a>';
      $args = [
        ':view' => Url::fromRoute('node_subscribe.view_subscriber_details', ['smid' => $row->smid],
          ['query' => ['destination' => \Drupal::request()->getRequestUri() . '#all-subscribers-table']])->toString(),
        '@view' => t('View'),
        ':delete' => Url::fromRoute('node_subscribe.manage_subscribers_form_delete', ['smid' => $row->smid],
          ['query' => ['destination' => \Drupal::request()->getRequestUri() . '#all-subscribers-table']])->toString(),
        '@delete' => t('Delete'),
      ];
      $action_buttons_html = new FormattableMarkup($action_buttons_markup, $args);

      $result_array[$row->smid] = [
        'smid' => $row->smid,
        'email' => $row->email,
        'changed' => $row->changed == 0 ? 'never' : \Drupal::service('date.formatter')->format($row->changed, 'date_text'),
        'created' => \Drupal::service('date.formatter')->format($row->created, 'date_text'),
        'verified' => $verified_string,
        'status' => $status_string,
        'delete' => $action_buttons_html,
      ];
    }
    $subscribers['rows'] = $result_array;
    $subscribers['pager'] = $pager;

    return $subscribers;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscriberDetailsBySmid($smid) {
    $subscriberTable = self::SUBSCRIBER_TABLE;
    $tokenTable = self::TOKEN_TABLE;

    $connection = \Drupal::database();
    $query = $connection->select($subscriberTable, 'subscribers');
    $query->fields('subscribers', ['smid', 'email']);
    $query->condition('subscribers.smid', $smid, '=');
    $query->fields('tokens', ['verified', 'token', 'changed', 'created']);
    $query->join($tokenTable, 'tokens', 'subscribers.smid = tokens.smid');
    $result = $query->execute()->fetchAll();

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscriberSubscriptionsBySmid($smid) {
    $subscriptionTable = self::SUBSCRIPTION_TABLE;

    $connection = \Drupal::database();
    $query = $connection->select($subscriptionTable, 'subscriptions');
    $query->fields('subscriptions', ['smid', 'nid', 'status']);
    $query->condition('subscriptions.smid', $smid, '=');
    $result = $query->execute()->fetchAll();

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteSubscriberBySmid() {
    $smid = $this->smid;
    $subscriberTable = self::SUBSCRIBER_TABLE;
    $tokenTable = self::TOKEN_TABLE;
    $subscriptionTable = self::SUBSCRIPTION_TABLE;

    $connection = \Drupal::database();

    $txn = $connection->startTransaction();
    try {
      $result['subscriber'] = $connection->delete($subscriberTable)
        ->condition('smid', $smid)
        ->execute();
      $result['tokens'] = $connection->delete($tokenTable)
        ->condition('smid', $smid)
        ->execute();
      $result['subscriptions'] = $connection->delete($subscriptionTable)
        ->condition('smid', $smid)
        ->execute();

      \Drupal::messenger()->addStatus(
        $this->t(
          'Subscriber @smid, with @email has been deleted.',
          ['@smid' => $smid, '@email' => $this->email]
        )
      );
      return $result;
    }
    catch (\Exception $e) {
      $txn->rollBack();
      \Drupal::messenger()->addStatus(
        $this->t(
          'Error trying to delete @smid, with @email.',
          ['@smid' => $smid, '@email' => $this->email]
        )
      );
      \Drupal::logger('type')->error($e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscriberSummary($smid) {
    $subscriber_details = self::getSubscriberDetailsBySmid($smid);
    $subscriber_subscriptions = self::getSubscriberSubscriptionsBySmid($smid);
    $markup = '';
    if ($subscriber_details) {
      $markup .= '<table><thead><tr><th>Device/Token</th><th>Verified?</th></tr></thead><tbody>';
      foreach ($subscriber_details as $token_detail) {
        $args['@token'] = (str_repeat('*', 30) . substr($token_detail->token, mb_strlen($token_detail->token) * 0.8, mb_strlen($token_detail->token)));
        $args['@verified'] = ($token_detail->verified == 1 ? "Yes" : ($token_detail->verified == -1 ? "One-time token" : "No"));
        $args['@smid'] = $smid;
        $token_row = new FormattableMarkup('<tr>
                        <td>@token</td>
                        <td>@verified</td>
                    </tr>', $args);
        $markup .= $token_row;
      }

      $markup .= '</tbody></table>';
      if ($subscriber_subscriptions) {
        $markup .= '<table><thead><tr><th>Node ID</th><th>Page Title</th><th>Status</th><th>Action</th></tr></thead><tbody>';
        foreach ($subscriber_subscriptions as $subscription_detail) {
          $args['@nid'] = $subscription_detail->nid;
          $args[':url'] = self::getUrlByNid($subscription_detail->nid);
          $args['@title'] = self::getNodeTitle($subscription_detail->nid);
          $args['@status'] = $subscription_detail->status;
          $args['@disableButton'] = $args['@status'] == 'enabled' ? '<a href="@smid/@nid/disable">disable</a>' : '--';
          $pages_row = new FormattableMarkup('<tr>
                        <td>@nid</td>
                        <td><a target="_blank" href=":url">@title</a></td>
                        <td>@status</td>
                        <td>' . $args['@disableButton'] . '</td>
                    </tr>', $args);
          $markup .= $pages_row;
        }
        $markup .= '</tbody></table>';
      }
    }
    $output = Xss::filterAdmin($markup);
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  private static function loadSubscribedNodes($distinct = FALSE) {

    $subscriptionTable = self::SUBSCRIPTION_TABLE;

    $connection = \Drupal::database();
    if ($distinct) {
      $nid_distinct_list = $connection
        ->query("SELECT DISTINCT * FROM {$subscriptionTable}")
        ->fetchAll();
    }
    else {
      $nid_distinct_list = $connection
        ->query("SELECT * FROM {$subscriptionTable}")
        ->fetchAll();
    }

    $nodes = [];
    foreach ($nid_distinct_list as $value) {
      $nid = $value->nid;
      $node = \Drupal::entityTypeManager()
        ->getStorage('node')->load($nid);

      $nodes[$value->sid]['node'] = $node;
      $nodes[$value->sid]['extra'] = $value;
    }

    return $nodes;
  }

  /**
   * {@inheritdoc}
   */
  private static function arrayGroupBy(array $array, $key) {
    if (!is_string($key) && !is_int($key) && !is_float($key) && !is_callable($key)) {
      trigger_error('arrayGroupBy(): The key should be a string, an integer, or a callback', E_USER_ERROR);
      return NULL;
    }
    $func = (!is_string($key) && is_callable($key) ? $key : NULL);
    $_key = $key;
    // Load the new array, splitting by the target key.
    $grouped = [];
    foreach ($array as $value) {
      $key = NULL;
      if (is_callable($func)) {
        $key = call_user_func($func, $value);
      }
      elseif (is_object($value) && isset($value->{$_key})) {
        $key = $value->{$_key};
      }
      elseif (isset($value[$_key])) {
        $key = $value[$_key];
      }
      if ($key === NULL) {
        continue;
      }
      $grouped[$key][] = $value;
    }
    // Recursively build a nested grouping if more parameters are supplied
    // Each grouped array value is grouped according to the next sequential key.
    if (func_num_args() > 2) {
      $args = func_get_args();
      foreach ($grouped as $key => $value) {
        $params = array_merge([$value], array_slice($args, 2, func_num_args()));
        $grouped[$key] = call_user_func_array('arrayGroupBy', $params);
      }
    }
    return $grouped;
  }

  /**
   * {@inheritdoc}
   */
  private static function getNodeTitle($nid) {
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load($nid);
    return ($node) ? $node->get('title')->value : '[Node no longer exists]';
  }

  /**
   * {@inheritdoc}
   */
  private static function getUrlByNid($nid) {
    return \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $nid);
  }

}
