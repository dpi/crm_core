<?php

namespace Drupal\crm_core_match\Plugin\crm_core_match\field;

use Drupal\crm_core_contact\ContactInterface;

/**
 * Class for evaluating unsupported fields.
 *
 * @CrmCoreMatchFieldHandler (
 *   id = "unsupported"
 * )
 */
class Unsupported extends FieldHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function getOperators($property = 'value') {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function match(ContactInterface $contact, $property = 'value') {
    return array();
  }

}
