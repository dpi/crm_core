<?php

/**
 * @file
 * Contains \Drupal\crm_core_activity\Controller\ActivityController.
 */

namespace Drupal\crm_core_activity\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\crm_core_activity\Entity\Activity;
use Drupal\crm_core_activity\Entity\ActivityType;
use Drupal\crm_core_contact\Entity\Contact;

class ActivityController extends ControllerBase {

  /**
   * Provides the node submission form.
   *
   * @param \Drupal\crm_core_activity\Entity\ActivityType $crm_core_activity_type
   *   The activity type to add.
   * @param \Drupal\crm_core_contact\Entity\Contact $crm_core_contact
   *   (optional) The contact the activity will be assigned. If left blank, the
   *   Form will show a field to select a contact.
   *
   * @return array
   *   A node submission form.
   */
  public function add(ActivityType $crm_core_activity_type, Contact $crm_core_contact = NULL) {

    $values = array(
      'type' => $crm_core_activity_type->id(),
    );

    if ($crm_core_contact) {
      $values['activity_participants'] = array(
        array(
          'target_id' => $crm_core_contact->id(),
        ),
      );
    }

    $activity = Activity::create($values);

    $form = $this->entityFormBuilder()->getForm($activity);

    return $form;
  }

  /**
   * The title callback for the add activity form.
   *
   * @param \Drupal\crm_core_activity\Entity\ActivityType $crm_core_activity_type
   *   The activity type.
   *
   * @return string
   *   The page title.
   */
  public function addPageTitle(ActivityType $crm_core_activity_type) {
    return $this->t('Add new Activity @name', array('@name' => $crm_core_activity_type->label()));
  }

}
