<?php

namespace Drupal\node_subscribe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for exporting subscription data to CSV.
 */
class SubscriptionExportController extends ControllerBase {

  const NODE_SUBSCRIPTION = 'node_subscription';
  const NODE_SUBSCRIPTION_SUBSCRIBERS = 'node_subscription_manager';
  const NODE_SUBSCRIPTION_TOKENS = 'node_subscription_tokens';

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a SubscriptionExportController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Exports node_subscription table to CSV.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The CSV file response.
   */
  protected function exportNodeSubscriptionTable(string $table_name) {

    if (
      $table_name !== self::NODE_SUBSCRIPTION &&
      $table_name !== self::NODE_SUBSCRIPTION_SUBSCRIBERS &&
      $table_name !== self::NODE_SUBSCRIPTION_TOKENS) {
      return new Response('Invalid table name.');
    }



    $query = $this->database->select($table_name, 'nst');
    $query->fields('nst');

    if ($table_name === self::NODE_SUBSCRIPTION) {
      $query->join('node_field_data', 'nfd', 'nst.nid = nfd.nid');
      $query->fields('nfd', ['title']);
    }

    $results = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $this->generateCsvResponse($results, $table_name . '.csv');
  }

  /**
   * Exports node_subscription table to CSV.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The CSV file response.
   */
  public function exportNodeSubscription() {
    return $this->exportNodeSubscriptionTable(self::NODE_SUBSCRIPTION);
  }

  /**
   * Exports node_subscription_manager table to CSV.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The CSV file response.
   */
  public function exportNodeSubscriptionSubscribers() {
    return $this->exportNodeSubscriptionTable(self::NODE_SUBSCRIPTION_SUBSCRIBERS);
  }

  /**
   * Exports node_subscription_tokens table to CSV.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The CSV file response.
   */
  public function exportNodeSubscriptionTokens() {
    return $this->exportNodeSubscriptionTable(self::NODE_SUBSCRIPTION_TOKENS);
  }

  /**
   * Writes table data to CSV output stream.
   *
   * @param resource $output
   *   The output stream.
   * @param string $table_name
   *   The table name.
   * @param string $alias
   *   The table alias.
   */
  protected function writeTableData($output, $table_name, $alias) {
    $query = $this->database->select($table_name, $alias)
      ->fields($alias);
    $results = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

    if (!empty($results)) {
      // Write headers.
      fputcsv($output, array_keys($results[0]));

      // Write data rows.
      foreach ($results as $row) {
        fputcsv($output, $row);
      }
    }
  }

  /**
   * Generates a CSV response from data array.
   *
   * @param array $data
   *   The data to export.
   * @param string $filename
   *   The filename for the download.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The CSV file response.
   */
  protected function generateCsvResponse(array $data, $filename) {
    $output = fopen('php://temp', 'r+');

    if (!empty($data)) {
      // Write CSV headers.
      fputcsv($output, array_keys($data[0]));

      // Write data rows.
      foreach ($data as $row) {
        fputcsv($output, $row);
      }
    }

    rewind($output);
    $csv_data = stream_get_contents($output);
    fclose($output);

    $response = new Response($csv_data);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

    return $response;
  }

}
