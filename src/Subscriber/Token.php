<?php

namespace Drupal\node_subscribe\Subscriber;

/**
 * File for Token.
 */
class Token {

  /**
   * {@inheritdoc}
   */
  public static function generateToken() {
    $token = bin2hex(openssl_random_pseudo_bytes(16));
    return $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function getHashedToken($token) {
    // $hash = password_hash($token, PASSWORD_DEFAULT);
    $hash = hash('sha256', $token, FALSE);
    return $hash;
  }

  /**
   * Not used.
   */
  public static function verifyToken($token, $hash) {
    $match = FALSE;
    if (password_verify($token, $hash)) {
      $match = TRUE;
    }
    return $match;
  }

}
