<?php

/**
 * @file
 * Contains \Drupal\crm_core_activity\ActivityTypeAccessControlHandler.
 */

namespace Drupal\crm_core_activity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\crm_core_activity\Entity\ActivityType;

class ActivityTypeAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    // First check drupal permission.
    if (parent::checkAccess($entity, $operation, $account)->isForbidden()) {
      return AccessResult::forbidden();
    }

    switch ($operation) {
      case 'enable':
        // Only disabled activity type can be enabled.
        return AccessResult::allowedIf(!$entity->status());

      case 'disable':
        return AccessResult::allowedIf($entity->status());

      case 'delete':
        // If activity instance of this activity type exist, you can't delete it.
        $count = \Drupal::entityQuery('crm_core_activity')
          ->condition('type', $entity->id())
          ->count()
          ->execute();

        return AccessResult::allowedIf($count == 0);

      case 'update':
        return AccessResult::allowed();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $activity_type_is_active = empty($entity_bundle);

    // Load the activity type entity.
    if (!empty($entity_bundle)) {
      /* @var \Drupal\crm_core_activity\Entity\ActivityType $activity_type_entity */
      $activity_type_entity = ActivityType::load($entity_bundle);
      $activity_type_is_active = $activity_type_entity->status();
    }

    return AccessResult::allowedIf($activity_type_is_active)
      ->andIf(AccessResult::allowedIfHasPermissions($account, [
        'administer crm_core_activity entities',
        'create crm_core_activity entities',
        'create crm_core_activity entities of bundle ' . $entity_bundle,
      ], 'OR'));
  }

}
