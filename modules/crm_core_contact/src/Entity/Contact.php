<?php
/**
 * @file
 * Contains Drupal\crm_core_contact\Entity\Contact.
 */

namespace Drupal\crm_core_contact\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\crm_core_activity\Entity\Activity;
use Drupal\crm_core_contact\ContactInterface;

/**
 * CRM Contact Entity Class.
 *
 * @ContentEntityType(
 *   id = "crm_core_contact",
 *   label = @Translation("CRM Core Contact"),
 *   bundle_label = @Translation("Contact type"),
 *   handlers = {
 *     "access" = "Drupal\crm_core_contact\ContactAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\crm_core_contact\Form\ContactForm",
 *       "delete" = "Drupal\crm_core_contact\Form\ContactDeleteForm",
 *     },
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\crm_core_contact\ContactListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "crm_core_contact",
 *   revision_table = "crm_core_contact_revision",
 *   entity_keys = {
 *     "id" = "contact_id",
 *     "revision" = "revision_id",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *   },
 *   bundle_entity_type = "crm_core_contact_type",
 *   field_ui_base_route = "entity.crm_core_contact_type.edit_form",
 *   permission_granularity = "bundle",
 *   permission_labels = {
 *     "singular" = @Translation("Contact"),
 *     "plural" = @Translation("Contacts"),
 *   },
 *   links = {
 *     "add-page" = "/crm-core/contact/add",
 *     "add-form" = "/crm-core/contact/add/{crm_core_contact_type}",
 *     "canonical" = "/crm-core/contact/{crm_core_contact}",
 *     "collection" = "/crm-core/contact",
 *     "edit-form" = "/crm-core/contact/{crm_core_contact}/edit",
 *     "delete-form" = "/crm-core/contact/{crm_core_contact}/delete"
 *   }
 * )
 */
class Contact extends ContentEntityBase implements ContactInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the contact was created.'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', array(
        'type' => 'datetime_timestamp',
        'weight' => -5,
      ))
      ->setDisplayConfigurable('form', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the contact was last edited.'))
      ->setRevisionable(TRUE);

    // @todo Update once https://drupal.org/node/1979260 is done.
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Owner'))
      ->setDescription(t('The user that is the contact owner.'))
      ->setRevisionable(TRUE)
      ->setSettings(array(
        'target_type' => 'user',
        'default_value' => 0,
      ));

    // @todo Make this a name field once it gets available.
    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDisplayOptions('form', array(
        'type' => 'text_textfield',
        'weight' => 0,
      ))
      ->setDisplayOptions('view', array(
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 0,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['revision_log'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Revision log message'))
      ->setDescription(t('The log entry explaining the changes in this revision.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', array(
        'type' => 'text_textarea',
        'weight' => 25,
        'settings' => array(
          'rows' => 4,
        ),
      ));

    return $fields;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Remove once https://drupal.org/node/1979260 is done.
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    parent::preCreate($storage, $values);

    $account = \Drupal::currentUser();

    // Set user id of contact owner.
    $values += array(
      'uid' => $account->id(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function preSaveRevision(EntityStorageInterface $storage, \stdClass $record) {
    parent::preSaveRevision($storage, $record);

    if (!isset($record->revision_log)) {
      $record->revision_log = '';
    }

    $account = \Drupal::currentUser();
    $record->uid = $account->id();
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);

    $query = \Drupal::entityQuery('crm_core_activity');
    $activity_ids = $query
      ->condition('activity_participants.target_id', array_keys($entities), 'IN')
      ->execute();
    if (empty($activity_ids)) {
      // No related Activities.
      return;
    }
    // Load fully populated Activity objects to analyze/update.
    $crm_core_activities = Activity::loadMultiple($activity_ids);

    $activities_to_remove = array();

    foreach ($crm_core_activities as $crm_core_activity) {
      /** @var \Drupal\crm_core_activity\Entity\Activity $crm_core_activity */
      $participants = $crm_core_activity->get('activity_participants')->getValue();
      // Remove Contact from participants array.
      $participants = array_diff(array_column($participants, 'target_id'), array_keys($entities));

      if (empty($participants)) {
        // Last main participant was deleted, so we should kill entire activity.
        $activities_to_remove[] = $crm_core_activity->id();
      }
      else {
        // Save Activity with renewed list.
        $crm_core_activity->set('activity_participants', $participants);
        $crm_core_activity->save();
      }
    }

    if (!empty($activities_to_remove)) {
      $crm_core_activity_storage = \Drupal::entityTypeManager()->getStorage('crm_core_activity');
      $activities = $crm_core_activity_storage->loadMultiple($activities_to_remove);
      $ids = array_keys($entities);
      \Drupal::logger('crm_core_activity')->info('Deleted @count activities due to deleting contact id=%contact_id.', [
        '@count' => count($activities_to_remove),
        '%contact_id' => reset($ids),
      ]);
      $crm_core_activity_storage->delete($activities);
    }
  }

  /**
   * Gets the primary address.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface|\Drupal\Core\TypedData\TypedDataInterface
   *   The address property object.
   */
  public function getPrimaryAddress() {
    return $this->getPrimaryField('address');
  }

  /**
   * Gets the primary email.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface|\Drupal\Core\TypedData\TypedDataInterface
   *   The email property object.
   */
  public function getPrimaryEmail() {
    return $this->getPrimaryField('email');
  }

  /**
   * Gets the primary phone.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface|\Drupal\Core\TypedData\TypedDataInterface
   *   The phone property object.
   */
  public function getPrimaryPhone() {
    return $this->getPrimaryField('phone');
  }

  /**
   * Gets the primary field.
   *
   * @param string $field
   *   The primary field name.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface|\Drupal\Core\TypedData\TypedDataInterface
   *   The primary field property object.
   *
   * @throws \InvalidArgumentException
   *   If no primary field is configured.
   *   If the configured primary field does not exist.
   */
  public function getPrimaryField($field) {
    $type = $this->get('type')->entity;
    $name = empty($type->primary_fields[$field]) ? '' : $type->primary_fields[$field];
    return $this->get($name);
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    // @todo Replace with the value of the contact_name field, when name module will be available.
    $label = $this->get('name')->value;
    if (empty($label)) {
      $label = t('Nameless #@id', ['@id' => $this->id()]);
    }
    \Drupal::moduleHandler()->alter('crm_core_contact_label', $label, $entity);

    return $label;
  }

}
