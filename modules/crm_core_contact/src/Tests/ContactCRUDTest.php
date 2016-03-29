<?php
/**
 * @file
 * Contains \Drupal\crm_core_contact\Tests\ContactCRUDTest.
 */

namespace Drupal\crm_core_contact\Tests;

use Drupal\crm_core_activity\Entity\Activity;
use Drupal\crm_core_activity\Entity\ActivityType;
use Drupal\crm_core_contact\Entity\Contact;
use Drupal\crm_core_contact\Entity\ContactType;
use Drupal\simpletest\KernelTestBase;

/**
 * Tests CRUD operations for the CRM Core Contact entity.
 *
 * @group crm_core
 */
class ContactCRUDTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array(
    'field',
    'text',
    'user',
    'crm_core',
    'crm_core_contact',
    'crm_core_activity',
    'datetime',
  );

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig(array('field'));
    $this->installEntitySchema('crm_core_contact');
    $this->installEntitySchema('crm_core_activity');
  }

  /**
   * Tests CRUD of contact types.
   */
  public function testContactType() {
    $type = 'dog';

    // Create.
    $contact_type = ContactType::create(array('type' => $type));
    $this->assertTrue(isset($contact_type->type) && $contact_type->type == $type, 'New contact type type exists.');
    // @todo Check if this still must be the case.
//    $this->assertTrue($contact_type->locked, t('New contact type has locked set to TRUE.'));
    $contact_type->name = $this->randomMachineName();
    $contact_type->description = $this->randomString();
    $contact_type->primary_fields = [];
    $this->assertEqual(SAVED_NEW, $contact_type->save(), 'Contact type saved.');

    // Load.
    $contact_type_load = ContactType::load($type);
    $this->assertEqual($contact_type->type, $contact_type_load->type, 'Loaded contact type has same type.');
    $this->assertEqual($contact_type->name, $contact_type_load->name, 'Loaded contact type has same name.');
    $this->assertEqual($contact_type->description, $contact_type_load->description, 'Loaded contact type has same description.');
    $uuid = $contact_type_load->uuid();
    $this->assertTrue(!empty($uuid), 'Loaded contact type has uuid.');

    // Test ContactType::getNames().
    $contact_type_labels = ContactType::getNames();
    $this->assertTrue($contact_type->name == $contact_type_labels[$contact_type->type]);

    // Delete.
    $contact_type_load->delete();
    $contact_type_load = ContactType::load($type);
    $this->assertNull($contact_type_load, 'Contact type deleted.');
  }

  /**
   * Tests CRUD of contacts.
   *
   * @todo Check if working once https://drupal.org/node/2239969 got committed.
   */
  public function testContact() {
    $this->installEntitySchema('user');

    $type = ContactType::create(array('type' => 'test'));
    $type->primary_fields = [];
    $type->save();

    // Create.
    $contact = Contact::create(array('type' => $type->type));
    $this->assertEqual(SAVED_NEW, $contact->save(), 'Contact saved.');

    // Create second contact.
    $contact_one = Contact::create(array('type' => $type->type));
    $this->assertEqual(SAVED_NEW, $contact_one->save(), 'Contact saved.');

    // Load.
    $contact_load = Contact::load($contact->id());
    $uuid = $contact_load->uuid();
    $this->assertTrue(!empty($uuid), 'Loaded contact has uuid.');

    $activity_type = ActivityType::create(array('type' => 'activity_test'));
    $activity_type->save();

    // Create activity and add participants contact.
    $activity = Activity::create(array('type' => $activity_type->type));
    $activity->get('activity_participants')->appendItem($contact);
    $activity->get('activity_participants')->appendItem($contact_one);
    $this->assertEqual(SAVED_NEW, $activity->save(), 'Activity saved.');

    // Load activity.
    $activity_load = Activity::load($activity->id());
    $this->assertTrue(!empty($activity_load->uuid()), 'Loaded activity has uuid.');

    // Delete first contact, activity should'n be deleted
    // because it's related to second contact.
    $contact->delete();
    $contact_load = Contact::load($contact->id());
    $this->assertNull($contact_load, 'Contact deleted.');
    $activity_load = Activity::load($activity->id());
    $this->assertNotNull($activity_load, 'Activity not deleted.');

    // Delete second contact and now activity should be deleted too.
    $contact_one->delete();
    $contact_load = Contact::load($contact_one->id());
    $this->assertNull($contact_load, 'Contact deleted.');
    $activity_load = Activity::load($activity->id());
    $this->assertNull($activity_load, 'Activity deleted.');
  }

}
