<?php
/**
 * @file
 * Contains \Drupal\crm_core_contact\Entity\ContactType.
 */

namespace Drupal\crm_core_contact\Entity;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldInstanceConfig;

/**
 * CRM Contact Type Entity Class.
 *
 * @ConfigEntityType(
 *   id = "crm_core_contact_type",
 *   label = @Translation("CRM Core Contact type"),
 *   bundle_of = "crm_core_contact",
 *   config_prefix = "type",
 *   controllers = {
 *     "form" = {
 *       "default" = "Drupal\crm_core_contact\Form\ContactTypeForm",
 *       "delete" = "Drupal\crm_core_contact\Form\ContactTypeDeleteForm",
 *       "toggle" = "Drupal\crm_core_contact\Form\ContactTypeToggleForm",
 *     },
 *     "list_builder" = "Drupal\crm_core_contact\ContactTypeListBuilder",
 *   },
 *   admin_permission = "administer contact types",
 *   entity_keys = {
 *     "id" = "type",
 *     "label" = "name",
 *     "status" = "disabled",
 *   },
 *   links = {
 *     "canonical" = "crm_core_contact.type_edit",
 *     "add-form" = "crm_core_contact.type_add",
 *     "edit-form" = "crm_core_contact.type_edit",
 *     "delete-form" = "crm_core_contact.type_delete",
 *     "enable" = "crm_core_contact.type_enable",
 *     "disable" = "crm_core_contact.type_disable",
 *   }
 * )
 */
class ContactType extends ConfigEntityBase {

  /**
   * The machine-readable name of this type.
   *
   * @var string
   */
  public $type;

  /**
   * The human-readable name of this type.
   *
   * @var string
   */
  public $name;

  /**
   * A brief description of this type.
   *
   * @var string
   */
  public $description;

  /**
   * Whether or not this type was defined by a module or added via form.
   *
   * A boolean indicating whether this type is defined by a module (FALSE) or
   * by a user via Add content type (TRUE).
   * @var boolean
   */
  public $custom;

  /**
   * Whether or not this type is disabled.
   *
   * A boolean indicating whether this type is disabled or not, disabled contact
   * type contacts will not show up in the list of contacts.
   *
   * @var boolean
   */
  public $disabled;

  /**
   * Whether or not this type is locked.
   *
   * A boolean indicating whether this type is locked or not, locked contact
   * type cannot be edited or disabled/deleted.
   *
   * @var boolean
   */
  public $locked;

  public $weight = 0;

  /**
   * Primary fields.
   *
   * An array of key-value pairs, where key is the primary field type and value
   * is real field name used for this type.
   *
   * @var array
   */
  public $primary_fields;

  /**
   * Indicates whether a name field should be created for this contact type.
   *
   * This property affects entity creation only. It allows default configuration
   * of modules and installation profiles to specify whether a name field should
   * be created for this bundle.
   *
   * @var bool
   *
   * @see \Drupal\crm_core_contact\Entity\ContactType::$create_body_label
   */
  protected $create_name_field = TRUE;

  /**
   * The label to use for the name field upon entity creation.
   *
   * @see \Drupal\crm_core_contact\Entity\create_name_field::$create_body
   *
   * @var string
   */
  protected $create_name_field_label = 'Name';

  /**
   * Overrides Entity::__construct().
   */
  public function __construct($values = array()) {
    parent::__construct($values, 'crm_core_contact_type');
  }

  /**
   * Overrides Entity::id().
   */
  public function id() {
    return $this->type;
  }

  /**
   * Gets the lock status.
   *
   * The entity is considered locked if the entity is enabled an not new.
   *
   * @return bool
   *   TRUE if locked, FALSE otherwise.
   */
  public function isLocked() {
    return isset($this->status) && !$this->isNew();
  }

  /**
   * Constructs a new contact type object, without saving it.
   *
   * @param array $values
   *   (optional) An array of values to set, keyed by property name. If the
   *   entity type has bundles, the bundle key has to be specified.
   *
   * @return \Drupal\crm_core_contact\Entity\ContactType
   *   The entity object.
   *
   * @todo Review once https://drupal.org/node/2096899 got committed.
   */
  public static function create(array $values = array()) {
    return \Drupal::entityManager()->getStorage('crm_core_contact_type')->create($values);
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    parent::preCreate($storage, $values);

    // Ensure default values are set.
    $values = NestedArray::mergeDeep(array(
      'locked' => TRUE,
    ), $values);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    if (!$update) {
      // Create a name field if the create_body property is true and we're not
      // in the syncing process.
      if ($this->get('create_name_field') && !$this->isSyncing()) {
        $label = $this->get('create_name_field_label');
        $this->addContactNameField($label);
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @todo This does not scale.
   *
   * Deleting a contact type with thousands of contact records associated will
   * run into execution timeout.
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);

    $ids = array_map(function(EntityInterface $entity){
      return $entity->id();
    }, $entities);

    // Delete all instances of the given type.
    $results = \Drupal::entityQuery('crm_core_contact')
      ->condition('type', $ids, 'IN')
      ->execute();

    if (!empty($results)) {
      $contacts = Contact::loadMultiple($results);
      \Drupal::entityManager()->getStorage('crm_core_contact')->delete($contacts);
      watchdog('crm_core_contact', 'Delete !count contacts due to deletion of contact type.', array('!count' => count($results)), WATCHDOG_INFO);
    }
  }

  /**
   * Adds the default name field to a contact type.
   *
   * @param string $label
   *   (optional) The label for the name instance.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Name field instance.
   */
  protected function addContactNameField($label = 'Name') {
    // Add or remove the body field, as needed.
    $field = FieldConfig::loadByName('crm_core_contact', 'contact_name');
    $instance = FieldInstanceConfig::loadByName('crm_core_contact', $this->id(), 'contact_name');
    if (empty($field)) {
      $field = entity_create('field_config', array(
        'name' => 'contact_name',
        'entity_type' => 'crm_core_contact',
        'type' => 'text',
      ));
      $field->save();
    }
    if (empty($instance)) {
      $instance = entity_create('field_instance_config', array(
        'field_name' => 'contact_name',
        'entity_type' => 'crm_core_contact',
        'bundle' => $this->id(),
        'label' => $label,
        'settings' => array('display_summary' => TRUE),
      ));
      $instance->save();

      // Assign widget settings for the 'default' form mode.
      entity_get_form_display('crm_core_contact', $this->id(), 'default')
        ->setComponent('contact_name', array(
          'type' => 'text_textfield',
        ))
        ->save();

      // Assign display settings for the 'default' and 'teaser' view modes.
      entity_get_display('crm_core_contact', $this->id(), 'default')
        ->setComponent('contact_name', array(
          'label' => 'hidden',
          'type' => 'text_default',
        ))
        ->save();
    }

    return $instance;
  }
}
