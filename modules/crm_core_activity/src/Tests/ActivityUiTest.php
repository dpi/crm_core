<?php

namespace Drupal\crm_core_activity\Tests;

use Drupal\crm_core_contact\Entity\Contact;
use Drupal\simpletest\WebTestBase;

/**
 * Tests the UI for Activity CRUD operations.
 *
 * @group crm_core
 */
class ActivityUiTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array(
    'crm_core_contact',
    'crm_core_activity',
    'crm_core_tests',
    'block',
  );

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Place local actions blocks.
    $this->drupalPlaceBlock('local_actions_block');

    $this->drupalPlaceBlock('system_breadcrumb_block');
  }

  /**
   * Test basic UI operations with Activities.
   *
   * Create a contact.
   * Add activity of every type to contact.
   * Assert activities listed on Activities tab listing page.
   * Edit every activity. Assert activities changed from listing page.
   * Delete every activity. Assert they disappeared from listing page.
   */
  public function testActivityOperations() {
    // Create and login user. User should be able to create contacts and
    // activities.
    $user = $this->drupalCreateUser(array(
      'view crm overview',
      'administer crm_core_contact entities',
      'view any crm_core_contact entity',
      'administer crm_core_activity entities',
      'administer activity types',
    ));
    $this->drupalLogin($user);

    // Create Household contact.
    $household = Contact::create(array(
      'name' => 'Fam. Smith',
      'type' => 'household',
    ));
    $household->save();

    $this->drupalGet('crm-core/activity');
    $this->assertText(t('There are no activities available.'), 'No activities available.');

    $this->assertLink(t('Add an activity'));
    $this->drupalGet('crm-core/activity/add');

    $this->assertLink(t('Meeting'));
    $this->assertLink(t('Phone call'));

    // Create Meeting activity. Ensure it is listed.
    $this->drupalGet('crm-core/activity/add/meeting');
    $this->assertText(t('Format: @date', ['@date' => date('Y-m-d H:i')]));
    $meeting_activity = array(
      'title[0][value]' => 'Pellentesque',
      'activity_date[0][value][date]' => $this->randomDate(),
      'activity_date[0][value][time]' => $this->randomTime(),
      'activity_notes[0][value]' => $this->randomString(),
      'activity_participants[0][target_id]' => $household->label() . ' (' . $household->id() . ')',
    );

    // Assert the breadcrumb.
    $this->assertLink(t('Home'));
    $this->assertLink(t('CRM Core'));
    $this->assertLink(t('Activities'));

    $this->drupalPostForm(NULL, $meeting_activity, 'Save Activity');
    $this->assertText('Activity Pellentesque created.', 'No errors after adding new activity.');

    // Create Meeting activity. Ensure it it listed.
    $phonecall_activity = array(
      'title[0][value]' => 'Mollis',
      'activity_date[0][value][date]' => $this->randomDate(),
      'activity_date[0][value][time]' => $this->randomTime(),
      'activity_notes[0][value]' => $this->randomString(),
      'activity_participants[0][target_id]' => $household->label() . ' (' . $household->id() . ')',
    );
    $this->drupalPostForm('crm-core/activity/add/phone_call', $phonecall_activity, 'Save Activity');
    $this->assertText('Activity Mollis created.', 'No errors after adding new activity.');

    // Update activity and assert its title changed on the list.
    $meeting_activity = array(
      'title[0][value]' => 'Vestibulum',
      'activity_notes[0][value]' => 'Pellentesque egestas neque sit',
    );
    $this->drupalPostForm('crm-core/activity/1/edit', $meeting_activity, 'Save Activity');
    $this->assertText('Vestibulum', 'Activity updated.');
    $this->drupalGet('crm-core/activity');
    $this->assertLink('Vestibulum', 0, 'Updated activity listed properly.');

    // Get test view page and check fields data.
    $this->drupalGet('activity-view-data');
    $this->assertText('Vestibulum');
    $this->assertText('Pellentesque egestas neque sit');

    // Test that empty activity_participants field is not allowed.
    $empty_participant = array(
      'activity_participants[0][target_id]' => '',
    );
    $this->drupalPostForm('crm-core/activity/1/edit', $empty_participant, 'Save Activity');
    $this->assertText('Participants (value 1) field is required.', 'Empty activity participant not allowed.');

    // Update phone call activity and assert its title changed on the list.
    $phonecall_activity = array(
      'title[0][value]' => 'Commodo',
    );
    $this->drupalPostForm('crm-core/activity/2/edit', $phonecall_activity, 'Save Activity');
    $this->assertText('Commodo', 'Activity updated.');
    $this->drupalGet('crm-core/activity');
    $this->assertLink('Commodo', 0, 'Updated activity listed properly.');

    // Delete Meeting activity.
    $this->drupalPostForm('crm-core/activity/1/delete', array(), 'Delete');
    $this->assertText('Meeting Vestibulum has been deleted.', 'No errors after deleting activity.');
    $this->drupalGet('crm-core/activity');
    $this->assertNoLink('Vestibulum', 'Deleted activity is no more listed.');

    // Delete Phone call activity.
    $this->drupalPostForm('crm-core/activity/2/delete', array(), 'Delete');
    $this->assertText('Phone call Commodo has been deleted.', 'No errors after deleting activity.');
    $this->drupalGet('crm-core/activity');
    $this->assertNoLink('Commodo', 'Deleted activity is no more listed.');

    // Assert there is no activities left.
    $this->drupalGet('crm-core/activity');
    $this->assertText(t('There are no activities available.'), 'No activities listed.');

    // Test activity type operations.
    $this->drupalGet('admin/structure/crm-core/activity-types');

    // Add new activity type.
    $this->clickLink('Add activity type');
    $new_activity_type = array(
      'name' => 'New activity type',
      'type' => 'new_activity_type',
      'description' => 'New activity type description',
    );
    $this->drupalPostForm(NULL, $new_activity_type, 'Save activity type');

    // Check that new activity type is displayed in activity types overview.
    $this->drupalGet('admin/structure/crm-core/activity-types');
    $this->assertText($new_activity_type['name']);

    // Edit activity type.
    $this->clickLink('Edit', 1);
    $edit = array(
      'name' => 'Edited activity type',
    );
    $this->drupalPostForm(NULL, $edit, 'Save activity type');
    $this->drupalGet('admin/structure/crm-core/activity-types');
    $this->assertText($edit['name']);

    // Test activity type delete operation.
    $this->drupalGet('admin/structure/crm-core/activity-types');
    $this->clickLink('Delete');
    $this->drupalPostForm(NULL, array(), 'Delete');
    $this->assertText(t('The crm core activity type @type has been deleted.', ['@type' => $edit['name']]));
    $this->drupalGet('admin/structure/crm-core/activity-types');
    $this->assertNoText($edit['name']);
  }

  /**
   * Generate random Date for form element input.
   */
  protected function randomDate() {
    return format_date(REQUEST_TIME + rand(0, 100000), 'custom', 'Y-m-d');
  }

  /**
   * Generate random Time for form element input.
   */
  protected function randomTime() {
    return format_date(REQUEST_TIME + rand(0, 100000), 'custom', 'H:m:s');
  }

}
