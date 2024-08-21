<?php

namespace Drupal\node_subscribe\Form;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure example settings for this site.
 */
class NodeSubscribeSettingsForm extends ConfigFormBase {

  /**
   * A config factory instance.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * A config factory instance.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * ModalFormContactController constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory instance.
   * @param \Drupal\Core\Extension\ModuleHandler $moduleHandler
   *   A module handler.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ModuleHandler $moduleHandler,
    LanguageManagerInterface $languageManager
  ) {
    $this->configFactory = $config_factory;
    $this->moduleHandler = $moduleHandler;
    $this->languageManager = $languageManager;
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
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'node_subscribe_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'node_subscribe.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig $config */
    $config = $this->config('node_subscribe.settings');

    $module_path = $this->moduleHandler->getModule('node_subscribe')->getPath();
    $base_settings_data = Yaml::decode(file_get_contents($module_path . '/assets/yaml/form/base.settings.yml'));

    array_walk_recursive($base_settings_data, function (&$item, $key) use ($config) {
      if ($key == '#default_value') {
        $item = $config->get($item);
      }
    });

    foreach ($base_settings_data as $key => $field) {
      $form[$key] = $field;
    }

    $form['tabs'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'en',
    ];

    $ui_settings_data = Yaml::decode(file_get_contents($module_path . '/assets/yaml/form/ui-elements.settings.yml'));

    foreach ($ui_settings_data as $key => $value) {
      $form[$key] = $value;
    }

    $ui_settings_data_copy = [];
    $this->arrayWalkReplaceKeysRecursive($ui_settings_data, $ui_settings_data_copy);
    array_walk_recursive($ui_settings_data_copy, function (&$item, $key) use ($config) {
      if ($key == '#default_value') {
        $item = $config->get($item);
      }
    });

    foreach ($ui_settings_data_copy as $key => $field) {
      $form[$key] = $field;
    }

    // /** @var \Drupal\Core\Language\LanguageInterface[] $languages */
    // $languages = $this->languageManager->getLanguages();
    // foreach ($languages as $lang_key => $language) {

    //   $form[$lang_key] = [
    //     '#type' => 'details',
    //     '#title' => $language->getName(),
    //     '#group' => 'tabs',
    //   ];

    //   $ui_settings_data_copy = [];
    //   $this->arrayWalkReplaceKeysRecursive($ui_settings_data, $ui_settings_data_copy, $lang_key);

    //   array_walk_recursive($ui_settings_data_copy, function (&$item, $key) use ($config, $lang_key) {
    //     if ($key == '#default_value') {
    //       $item = $config->get($lang_key . '.' . $item);
    //     }
    //   });

    //   foreach ($ui_settings_data_copy as $key => $field) {
    //     $form[$lang_key][$key] = $field;
    //   }
    // }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Function to replace keys trought an array.
   */
  private function arrayWalkReplaceKeysRecursive(array &$origin, array &$modified, string $prefix = NULL) {

    if (is_null($prefix)) {
      array_walk($origin, function ($item, $key) use (&$modified) {
        $new_key = $key;

        if (is_array($item) && isset($item['#default_value'])) {
          $new_key = $key;
          $modified[$new_key] = $item;
        }
        elseif (is_array($item)) {
          $modified[$key] = [];
          $this->arrayWalkReplaceKeysRecursive($item, $modified[$key]);
        }
        else {
          $modified[$key] = $item;
        }
      });
    }
    else {
      array_walk($origin, function ($item, $key) use (&$modified, $prefix) {
        $new_key = $key;

        if (is_array($item) && isset($item['#default_value'])) {
          $new_key = $prefix . '_' . $key;
          $modified[$new_key] = $item;
        }
        elseif (is_array($item)) {
          $modified[$key] = [];
          $this->arrayWalkReplaceKeysRecursive($item, $modified[$key], $prefix);
        }
        else {
          $modified[$key] = $item;
        }
      });
    }
  }

  /**
   * Function to move trought an array.
   */
  private function arrayWalkKeyRecursive(object|array &$array, callable $callback) {
    array_walk($array, function ($item, $key) use ($callback) {
      if (is_array($item)) {
        $this->arrayWalkKeyRecursive($item, $callback);
      }
      call_user_func($callback, $item, $key);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    /** @var \Drupal\Core\Config\Config $config */
    $config = $this->configFactory()->getEditable('node_subscribe.settings');

    $module_path = $this->moduleHandler->getModule('node_subscribe')->getPath();

    $base_settings_data = Yaml::decode(file_get_contents($module_path . '/assets/yaml/form/base.settings.yml'));
    $this->arrayWalkKeyRecursive($base_settings_data, function ($item, $key) use ($config, $form_state) {
      if (isset($item['#default_value'])) {
        $config->set($item['#default_value'], $form_state->getValue($key));
      }
    });

    $ui_settings_data = Yaml::decode(file_get_contents($module_path . '/assets/yaml/form/ui-elements.settings.yml'));
    $this->arrayWalkKeyRecursive($ui_settings_data, function ($item, $key) use ($config, $form_state) {
      if (isset($item['#default_value'])) {
        $config->set($item['#default_value'], $form_state->getValue($key));
      }
    });
    // /** @var \Drupal\Core\Language\LanguageInterface[] $languages */
    // $languages = $this->languageManager->getLanguages();
    // foreach ($languages as $lang_key => $language) {
    //   $this->arrayWalkKeyRecursive($ui_settings_data, function ($item, $key) use ($config, $form_state, $lang_key) {
    //     if (isset($item['#default_value'])) {
    //       $config->set($lang_key . '.' . $item['#default_value'], $form_state->getValue($lang_key . '_' . $key));
    //     }
    //   });
    // }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
