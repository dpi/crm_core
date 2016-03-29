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
 * @} End of "addtogroup hooks".
 */
