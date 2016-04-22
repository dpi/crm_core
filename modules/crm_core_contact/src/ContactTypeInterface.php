<?php

namespace Drupal\crm_core_contact;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines methods for CRM Contact Type entities.
 */
interface ContactTypeInterface extends ConfigEntityInterface {

  /**
   * Returns the human readable name of any or all contact types.
   *
   * @return array
   *   An array containing all human readable names keyed on the machine type.
   */
  public static function getNames();

  /**
   * Get the name of the field for a primary field.
   *
   * @return string|NULL
   *   Name of the primary field, or NULL.
   */
  public function getPrimaryField($field);

}
