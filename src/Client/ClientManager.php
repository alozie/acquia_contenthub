<?php

/**
 * @file
 * Contains \Drupal\acquia_contenthub\Client\ClientManager.
 */

namespace Drupal\acquia_contenthub\Client;

use Acquia\ContentHubClient\ContentHub;
use Drupal\acquia_contenthub\Cipher;
use Drupal\acquia_contenthub\ContentHubException;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Config\ConfigFactory;

/**
 * Provides a service for managing pending server tasks.
 */
class ClientManager implements ClientManagerInterface {

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * ClientManager constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   */
  public function __construct(LoggerChannelFactory $logger_factory, ConfigFactory $config_factory) {
    $this->loggerFactory = $logger_factory;
    $this->configFactory = $config_factory;
  }

  /**
   * Function returns content hub client.
   *
   * @param array $config
   *   Configuration array.
   *
   * @return \Acquia\ContentHubClient\ContentHub
   *   Returns the Content Hub Client
   *
   * @throws \Drupal\acquia_contenthub\ContentHubException
   *   Throws exception when cannot connect to Content Hub.
   */
  public function getClient($config = []) {
    // @todo Make sure this injects using proper service injection methods.
    $config_drupal = $this->configFactory->get('acquia_contenthub.admin_settings');

    // Override configuration.
    $config = array_merge(['base_url' => $config_drupal->get('hostname')], $config);

    // Get API information.
    $api = $config_drupal->get('api_key');
    $origin = $config_drupal->get('origin');
    $encryption = (bool) $config_drupal->get('encryption_key_file');

    if ($encryption) {
      $secret = $config_drupal->get('secret_key');
      $secret = $this->cipher()->decrypt($secret);
    }
    else {
      $secret = $config_drupal->get('secret_key');
    }
    if (!$api || !$secret || !$origin || !$config) {
      $message = t('Could not create an Acquia Content Hub connection due to missing credentials. Please check your settings.');
      throw new ContentHubException($message);
    }

    $client = new ContentHub($api, $secret, $origin, $config);
    return $client;
  }

  /**
   * Returns a cipher class for encrypting and decrypting text.
   *
   * @Todo Make this work!
   *
   * @return \Drupal\acquia_contenthub\CipherInterface
   *   The Cipher object to use for encrypting the data.
   */
  public function cipher() {
    // @todo Make sure this injects using proper service injection methods.
    $config = $this->configFactory->get('acquia_contenthub.admin_settings');
    $filepath = $config->get('encryption_key_file');
    $cipher = new Cipher($filepath);
    return $cipher;
  }

}
