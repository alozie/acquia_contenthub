<?php
/**
 * @file
 * Contains Drupal\acquia_contenthub\Form\EntityConfigSettingsForm.
 */

namespace Drupal\acquia_contenthub\Form;

use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityDisplayRepository;
use Drupal\Core\Cache\CacheTagsInvalidator;

/**
 * Defines the form to configure the entity types and bundles to be exported.
 */
class EntityConfigSettingsForm extends ConfigFormBase {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfoManager;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepository
   */
  protected $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_contenthub.entity_config';
  }

  /**
   * Constructs an IndexAddFieldsForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info_manager
   *   The entity bundle info interface.
   * @param \Drupal\Core\Entity\EntityDisplayRepository $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Cache\CacheTagsInvalidator $cache_tags_invalidator
   *   A cache tag invalidator.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info_manager, EntityDisplayRepository $entity_display_repository, CacheTagsInvalidator $cache_tags_invalidator) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfoManager = $entity_type_bundle_info_manager;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_type_bundle_info_manager = $container->get('entity_type.bundle.info');
    $entity_type_manager = $container->get('entity_type.manager');
    $entity_display_repository = $container->get('entity_display.repository');
    $cache_tags_invalidator = $container->get('cache_tags.invalidator');
    return new static($entity_type_manager, $entity_type_bundle_info_manager, $entity_display_repository, $cache_tags_invalidator);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['acquia_contenthub.entity_config'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = array(
      '#type' => 'item',
      '#description' => t('Select the bundles of the entity types you would like to publish to Acquia Content Hub. <br/><br/><strong>Optional</strong><br/>Choose a view mode for each of the selected bundles to be rendered before sending to Acquia Content Hub. <br/>You can choose the view modes to use for rendering the items of different datasources and bundles. We recommend using a dedicated view mode to make sure that only relevant data (especially no field labels) will be transferred to Content Hub.'),
    );

    $form['entity_config']['entities'] = $this->buildEntitiesForm();
    $form['entity_config']['user_role'] = $this->buildUserRoleForm();

    return parent::buildForm($form, $form_state);
  }

  /**
   * Build entities form.
   *
   * @return array
   *   Entities form.
   */
  private function buildEntitiesForm() {
    $form = array(
      '#type' => 'fieldgroup',
      '#title' => t('Entities'),
      '#tree' => TRUE,
    );
    $entity_types = $this->getEntityTypes();
    foreach ($entity_types as $type => $bundle) {
      // @todo Fix this. Total hack to only support explicit content types.
      if ($type != 'node') {
        continue;
      }
      $form[$type] = array(
        '#title' => $type,
        '#type' => 'details',
        '#tree' => TRUE,
        '#description' => "Select the content types that you would like to publish to Content Hub.",
        '#open' => TRUE,
      );
      $form[$type] += $this->buildEntitiesBundleForm($type, $bundle);
    }
    return $form;
  }

  /**
   * Build entities bundle form.
   *
   * @param array $type
   *   Type.
   * @param array $bundle
   *   Bundle.
   *
   * @return array
   *   Entities bundle form.
   */
  private function buildEntitiesBundleForm($type, $bundle) {
    $entities = $this->config('acquia_contenthub.entity_config')->get('entities');
    $form = array();
    foreach ($bundle as $bundle_id => $bundle_name) {
      $view_modes = $this->entityDisplayRepository->getViewModeOptionsByBundle($type, $bundle_id);
      $entity_type_label = $this->entityTypeManager->getDefinition($type)->getLabel();
      $form[$bundle_id] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('%entity_type_label » %bundle_name', array('%entity_type_label' => $entity_type_label, '%bundle_name' => $bundle_name)),
        '#collapsible' => TRUE,
      );
      $form[$bundle_id]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Publish'),
        '#disabled' => empty($view_modes),
        '#default_value' => empty($view_modes) ? FALSE : $entities[$type][$bundle_id]['enabled'],
        '#description' => empty($view_modes) ? $this->t('is disabled because there is no available view modes.') : NULL,
      ];

      $rendering = $entities[$type][$bundle_id]['rendering'];
      $title = empty($view_modes) ? NULL : $this->t('Do you want to include the result of any of the following view mode(s)?');
      $default_value = (empty($view_modes) || empty($rendering)) ? array() : $rendering;
      $form[$bundle_id]['rendering'] = array(
        '#type' => 'select',
        '#options' => $view_modes,
        '#multiple' => TRUE,
        '#title' => $title,
        '#default_value' => $default_value,
        '#states' => [
          'visible' => [
            ':input[name="entities[' . $type . '][' . $bundle_id . '][enabled]"]' => ['checked' => TRUE],
          ],
        ],
        '#description' => $this->t('You can hold ctrl (or cmd) key to select multiple view mode(s). Including any of these view modes is usually done in combination with Acquia Lift. Please read the documentation for more information.'),
      );
    }
    return $form;
  }

  /**
   * Build user role form.
   *
   * @return array
   *   User role form.
   */
  private function buildUserRoleForm() {
    $user_role = $this->config('acquia_contenthub.entity_config')->get('user_role');
    $user_role_names = user_role_names();
    $form = array(
      '#type' => 'select',
      '#title' => $this->t('User Role'),
      '#description' => $this->t('Your item will be rendered as seen by a user with the selected role. We recommend to just use "@anonymous" here to prevent data leaking out to unauthorized roles.', array('@anonymous' => $user_role_names[AccountInterface::ANONYMOUS_ROLE])),
      '#options' => $user_role_names,
      '#multiple' => FALSE,
      '#default_value' => $user_role ?: AccountInterface::ANONYMOUS_ROLE,
      '#required' => TRUE,
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $values = $form_state->getValues();
    $config = $this->config('acquia_contenthub.entity_config');
    $config->set('entities', $values['entities']);
    $config->set('user_role', $values['user_role']);
    $config->save();
  }

  /**
   * Obtains the list of entity types.
   */
  public function getEntityTypes() {
    $types = $this->entityTypeManager->getDefinitions();

    $entity_types = array();
    foreach ($types as $type => $entity) {
      // Only support ContentEntityTypes.
      if ($entity instanceof ContentEntityType) {
        $bundles = $this->entityTypeBundleInfoManager->getBundleInfo($type);

        // Here we need to load all the different bundles?
        if (isset($bundles) && count($bundles) > 0) {
          foreach ($bundles as $key => $bundle) {
            $entity_types[$type][$key] = $bundle['label'];
          }
        }
        else {
          // In cases where there are no bundles, but the entity can be
          // selected.
          $entity_types[$type][$type] = $entity->getLabel();
        }
      }
    }
    return $entity_types;
  }

}
