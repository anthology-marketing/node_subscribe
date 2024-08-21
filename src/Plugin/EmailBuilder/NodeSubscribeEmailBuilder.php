<?php

namespace Drupal\node_subscribe\Plugin\EmailBuilder;

use Drupal\symfony_mailer\Address;
use Drupal\symfony_mailer\BaseEmailTrait;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;
use Drupal\symfony_mailer\Processor\TokenProcessorTrait;

/**
 * Defines the Email Builder plug-in for test mails.
 *
 * @EmailBuilder(
 *   id = "node_subscribe",
 *   sub_types = { "page_added" = @Translation("Paged added") },
 *   common_adjusters = {"email_subject", "email_body", "email_to"},
 * )
 */
class NodeSubscribeEmailBuilder extends EmailBuilderBase {

  use BaseEmailTrait;
  use TokenProcessorTrait;

  /**
   * Saves the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   * @param mixed|null $params
   *   (Optional) Array with params.
   */
  public function createParams(EmailInterface $email, ?array $params = NULL) {
    if (!is_null($params)) {
      foreach ($params as $key => $param) {
        $email->setParam($key, $param);
        $email->setVariable($key, $param);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fromArray(EmailFactoryInterface $factory, array $message) {
    return $factory->newTypedEmail($message['module'], $message['key'], $message['params']);
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    parent::build($email);

    $variables = $email->getVariables();

    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $address = new Address($variables['to'], NULL, $langcode);
    $email->setTo($address);
    $email->setSubject($variables['subject'], TRUE);
  }

}
