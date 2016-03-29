<?php

/**
 * @file
 * Hooks provided by the CRM Core module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Use a custom label for a contact of bundle CONTACT_BUNDLE.
 */
function crm_core_contact_CONTACT_BUNDLE_label($entity) {
  // No example.
}

/**
 * Respond to CRM Core contacts being merged.
 *
 * @param \Drupal\crm_core_contact\Entity\Contact $master_contact
 *   Contact to which data being merged.
 * @param array $merged_contacts
 *   Keyed by contact ID array of contacts being merged.
 *
 * @see crm_core_contact_merge_contacts_action()
 */
function hook_crm_core_contact_merge_contacts(Drupal\crm_core_contact\Entity\Contact $master_contact, array $merged_contacts) {

}

/**
 * Provides possibility to change default fields that will be added to the
 * recently created bundle of activity.
 *
 * @param $fields
 *   Array with fields that are going to be added to the activity bundle.
 * @param CRMActivityType $activity_type
 *   Bundle of activity entity that was recently created.
 *
 * @see field_create_field()
 * @see _crm_core_activity_type_default_fields()
 */
function hook_crm_core_activity_type_add_fields_alter(&$fields, CRMActivityType $activity_type) {
  // Prevent field_activity_date from creation.
  foreach ($fields as $key => $field) {
    if ($field['field_name'] == 'field_activity_date') {
      unset($fields[$key]);
    }
  }
}

/**
 * Provides possibility to change default field instances that will be added to
 * the recently created bundle of activity.
 *
 * @param $instances
 *   Array with field instances that are going to be added to the activity
 *   bundle.
 * @param CRMActivityType $activity_type
 *   Bundle of activity entity that was recently created.
 *
 * @see field_create_instance()
 * @see _crm_core_activity_type_default_field_instances()
 */
function hook_crm_core_activity_type_add_field_instances_alter(&$instances, CRMActivityType $activity_type) {
  // Prevent field_activity_date from adding to an activity bundle.
  foreach ($instances as $key => $instance) {
    if ($instance['field_name'] == 'field_activity_date') {
      unset($instances[$key]);
    }
  }
}

/**
 * @} End of "addtogroup hooks".
 */
