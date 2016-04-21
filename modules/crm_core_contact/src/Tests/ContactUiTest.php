<?php
/**
 * @file
 * Contains \Drupal\crm_core_contact\Tests\ContactUiTest.
 */

namespace Drupal\crm_core_contact\Tests;

use Drupal\crm_core_contact\Entity\Contact;
use Drupal\simpletest\WebTestBase;

/**
 * Tests the UI for Contact CRUD operations.
 *
 * @group crm_core
 */
class ContactUiTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'crm_core_contact',
    'crm_core_activity',
    'crm_core_tests',
    'block'
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Place local actions and local task blocks.
    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('local_tasks_block');
  }

  /**
   * Tests the contact operations.
   *
   * User with permissions 'administer crm_core_contact entities'
   * should be able to create/edit/delete contacts of any contact type.
   *
   * @todo Test with name field once that is available again.
   *   Code that is name field specific was left in as comment so it can be
   *   easily but back in place.
   */
  public function testContactOperations() {
    $this->drupalGet('crm-core');
    $this->assertResponse(403);

    $user = $this->drupalCreateUser([
      'view any crm_core_contact entity',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('crm-core');
    $this->assertLink('CRM Contacts');
    $this->assertNoLink('CRM Activities');

    $user = $this->drupalCreateUser([
      'view any crm_core_activity entity',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('crm-core');
    $this->assertNoLink('CRM Contacts');
    $this->assertLink('CRM Activities');

    // Create user and login.
    $user = $this->drupalCreateUser([
      'create crm_core_contact entities of bundle household',
      'create crm_core_contact entities of bundle organization',
      'view any crm_core_contact entity',
      'view any crm_core_activity entity',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('crm-core');

    $this->assertTitle(t('CRM Core | Drupal'));

    $this->assertLink(t('CRM Activities'));
    $this->assertLink(t('CRM Contacts'));
    $this->clickLink(t('CRM Contacts'));
    // There should be no contacts available after fresh installation and there
    // is a link to create new contacts.
    $this->assertText(t('There are no contacts available.'), 'No contacts available after fresh installation.');
    $this->assertLink(t('Add a contact'));

    // Assert "Household" and "Organization" contact types are available.
    $this->drupalGet('crm-core/contact/add');
    $this->assertLink(t('Household'));
    $this->assertLink(t('Organization'));
    $this->assertNoLinkByHref('crm-core/contact/add/individual', 'User has no permission to create Individual contacts.');
    $this->drupalGet('crm-core/contact/add/individual');
    $this->assertResponse(403);

    // Create Household contact.
    // @todo update these values when contact is split into different entities.
    $household_node = [
      'name[0][title]' => '',
      'name[0][given]' => 'Gregory',
      'name[0][middle]' => '',
      'name[0][family]' => 'House',
      'name[0][generational]' => '',
      'name[0][credentials]' => '',
    ];
    $this->drupalPostForm('crm-core/contact/add/household', $household_node, 'Save Household');

    // Assert we were redirected back to the list of contacts.
    $this->assertUrl('crm-core/contact');

    $this->assertText('Gregory House', 0, 'Newly created contact title listed.');
    $this->assertText(t('Household'), 'Newly created contact type listed.');

    // Create individual contact.
    $user = $this->drupalCreateUser(array('administer crm_core_contact entities', 'administer contact types', 'view any crm_core_contact entity'));
    $this->drupalLogin($user);
    $individual_node = [
      'name[0][title]' => 'Mr.',
      'name[0][given]' => 'John',
      'name[0][middle]' => 'Emanuel',
      'name[0][family]' => 'Smith',
      'name[0][generational]' => 'IV',
      'name[0][credentials]' => '',
    ];
    $this->drupalPostForm('crm-core/contact/add/individual', $individual_node, 'Save Individual');

    // Assert we were redirected back to the list of contacts.
    $this->assertUrl('crm-core/contact');

    $this->assertText('John Smith', 0, 'Newly created contact title listed.');
    $this->assertText(t('Individual'), 'Newly created contact type listed.');

    // Assert all view headers are available.
    $this->assertLink('Name');
    $this->assertLink('Contact Type');
    $this->assertLink('Updated');
    $this->assertText('Operations links');

    $count = $this->xpath('//form[@class="views-exposed-form"]/div/div/label[text()="Name (given)"]');
    $this->assertTrue($count, 1, 'Name given is an exposed filter.');

    $count = $this->xpath('//form[@class="views-exposed-form"]/div/div/label[text()="Name (family)"]');
    $this->assertTrue($count, 1, 'Name given is an exposed filter.');

    $count = $this->xpath('//form[@class="views-exposed-form"]/div/div/label[text()="Type"]');
    $this->assertTrue($count, 1, 'Contact type is an exposed filter.');

    $contacts = \Drupal::entityTypeManager()->getStorage('crm_core_contact')->loadByProperties(['name__given' => 'John', 'name__family' => 'Smith']);
    $contact = current($contacts);

    $this->assertRaw('crm-core/contact/' . $contact->id() . '/edit', 'Edit link is available.');
    $this->assertRaw('crm-core/contact/' . $contact->id() . '/delete', 'Delete link is available.');

    $this->assertText($this->container->get('date.formatter')->format($contact->get('changed')->value, 'medium'), 'Contact updated date is available.');

    // Create Organization contact.
    // @todo update these values when contact is split into different entities.
    $organization_node = [
      'name[0][title]' => '',
      'name[0][given]' => 'Example',
      'name[0][middle]' => '',
      'name[0][family]' => 'ltd',
      'name[0][generational]' => '',
      'name[0][credentials]' => '',
    ];
    $this->drupalPostForm('crm-core/contact/add/organization', $organization_node, 'Save Organization');

    // Assert we were redirected back to the list of contacts.
    $this->assertUrl('crm-core/contact');

    $this->assertText('Example ltd', 0, 'Newly created contact title listed.');
    $this->assertText(t('Organization'), 'Newly created contact type listed.');

    // Edit operations.
    // We know that created nodes household is id 1, individual is no 2,
    // organization is no 3. But we should have better API to find contact by
    // name.
    // @todo update these values when contact is split into different entities.
    $household_node = [
      'name[0][title]' => '',
      'name[0][given]' => 'Fam.',
      'name[0][middle]' => '',
      'name[0][family]' => 'Johnson',
      'name[0][generational]' => '',
      'name[0][credentials]' => '',
    ];
    $this->drupalPostForm('crm-core/contact/1/edit', $household_node, 'Save Household');

    // Assert we were redirected back to the list of contacts.
    $this->assertUrl('crm-core/contact/1');
    $this->assertText('Fam. Johnson', 0, 'Contact updated.');

    // Assert contact template has been used.
    $this->assertRaw('Fam. Johnson</div>');

    $this->drupalGet('crm-core/contact/1/edit');
    $this->assertRaw('data-drupal-link-system-path="crm-core/contact/1/delete"', 'Local task "Delete" is available.');
    $this->assertRaw('crm-core/contact/1/delete" class="button button--danger" data-drupal-selector="edit-delete" id="edit-delete"', 'Delete link is available.');

    // Get test view data page.
    $this->drupalGet('contact-view-data');
    $this->assertText('Fam. Johnson');

    // Check listing page.
    $this->drupalGet('crm-core/contact');
    $this->assertText('Fam. Johnson', 0, 'Updated contact title listed.');

    // Delete household contact.
    $this->drupalPostForm('crm-core/contact/1/delete', array(), t('Delete'));
    $this->assertUrl('crm-core/contact');
    $this->assertNoLink('Fam. Johnson', 0, 'Deleted contact title no more listed.');

    // Edit individual contact.
    $individual_node = [
      'name[0][title]' => 'Mr.',
      'name[0][given]' => 'Maynard',
      'name[0][middle]' => 'James',
      'name[0][family]' => 'Keenan',
      'name[0][generational]' => 'I',
      'name[0][credentials]' => 'MJK',
    ];
    $this->drupalPostForm('crm-core/contact/2/edit', $individual_node, 'Save Individual');

    // Assert we were redirected back to the list of contacts.
    $this->assertUrl('crm-core/contact/2');

    // Check listing page.
    $this->drupalGet('crm-core/contact');
    $this->assertText('Maynard Keenan', 0, 'Updated individual contact title listed.');

    // Delete individual contact.
    $this->drupalPostForm('crm-core/contact/2/delete', array(), t('Delete'));
    $this->assertUrl('crm-core/contact');
    $this->assertNoLink('Johnson', 0, 'Deleted individual contact title no more listed.');

    // Edit organization contact.
    // @todo update this values when contact is split into entities.
    $organization_node = [
      'name[0][title]' => '',
      'name[0][given]' => 'Another Example',
      'name[0][middle]' => '',
      'name[0][family]' => 'ltd',
      'name[0][generational]' => '',
      'name[0][credentials]' => '',
    ];
    $this->drupalPostForm('crm-core/contact/3/edit', $organization_node, 'Save Organization');

    // Assert we were redirected back to the list of contacts.
    $this->assertUrl('crm-core/contact/3');
    $this->assertText('Another Example ltd', 0, 'Contact updated.');

    // Check listing page.
    $this->drupalGet('crm-core/contact');
    $this->assertText('Another Example ltd', 0, 'Updated contact title listed.');

    // Delete organization contact.
    $this->drupalPostForm('crm-core/contact/3/delete', array(), t('Delete'));
    $this->assertUrl('crm-core/contact');
    $this->assertNoLink('Another Example ltd', 0, 'Deleted contact title no more listed.');

    // Assert that there are no contacts left.
    $this->assertText(t('There are no contacts available.'), 'No contacts available after fresh installation.');
  }

  /**
   * Tests the contact type operations.
   *
   * User with permissions 'administer contact types' should be able to
   * create/edit/delete contact types.
   */
  public function testContactTypeOperations() {
    // Given I am logged in as a user with permission 'administer contact types'
    $user = $this->drupalCreateUser(array('administer contact types'));
    $this->drupalLogin($user);

    // When I visit the contact type admin page.
    $this->drupalGet('admin/structure/crm-core/contact-types');

    // Then I should see edit, enable, delete but no enable links for existing
    // contacts.
    $this->assertContactTypeLink('household', 'Edit link for household.');
    $this->assertContactTypeLink('household/disable', 'Disable link for household.');
    $this->assertNoContactTypeLink('household/enable', 'No enable link for household.');
    $this->assertContactTypeLink('household/delete', 'Delete link for household.');

    $this->assertcontacttypelink('individual', 'Edit link for individual.');
    $this->assertcontacttypelink('individual/disable', 'Disable link for individual.');
    $this->assertNoContacttypelink('individual/enable', 'No enable link for individual.');
    $this->assertcontacttypelink('individual/delete', 'Delete link for individual.');

    $this->assertcontacttypelink('organization', 'Edit link for organization.');
    $this->assertcontacttypelink('organization/disable', 'Disable link for organization.');
    $this->assertNoContacttypelink('organization/enable', 'No enable link for organization.');
    $this->assertcontacttypelink('organization/delete', 'Delete link for organization.');

    // Given the 'household' contact type is disabled.
    $this->drupalPostForm('admin/structure/crm-core/contact-types/household/disable', array(), 'Disable');

    // When I visit the contact type admin page.
    $this->drupalGet('admin/structure/crm-core/contact-types');

    // Then I should see an enable link.
    $this->assertContactTypeLink('household/enable', 'Enable link for household.');
    // And I should not see a disable link.
    $this->assertNoContactTypeLink('household/disable', 'No disable link for household.');

    // When I enable 'household'.
    $this->drupalPostForm('admin/structure/crm-core/contact-types/household/enable', array(), 'Enable');

    // Then I should see a disable link.
    $this->assertContactTypeLink('household/disable', 'Disable link for household.');

    // Given there is a contact of type 'individual.'.
    Contact::create(array('type' => 'individual'))->save();

    // When I visit the contact type admin page.
    $this->drupalGet('admin/structure/crm-core/contact-types');

    // Then I should not see a delete link.
    $this->assertNoContactTypeLink('individual/delete', 'No delete link for individual.');
    $this->drupalGet('admin/structure/crm-core/contact-types/individual/delete');
    $this->assertResponse(403);

    // When I edit the organization type.
    $this->drupalGet('admin/structure/crm-core/contact-types/organization');

    // Then I should see a delete link.
    $this->assertContactTypeLink('organization/delete', 'Delete link on organization type form.');

    // When I edit the individual type.
    $this->drupalGet('admin/structure/crm-core/contact-types/individual');

    // @todo Assert for a positive fact to ensure being on the correct page.
    // Then I should not see a delete link.
    $this->assertNoContactTypeLink('individual/delete', 'No delete link on individual type form.');
  }

  /**
   * Test if the field UI is displayed on contact bundle.
   */
  public function testFieldsUi() {
    $user = $this->drupalCreateUser([
      'administer crm_core_contact display',
      'administer crm_core_contact form display',
      'administer crm_core_contact fields',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('admin/structure/crm-core/contact-types/household/fields');
    $this->assertText(t('Manage fields'), 'Manage fields local task in available.');
    $this->assertText(t('Manage form display'), 'Manage form display local task in available.');
    $this->assertText(t('Manage display'), 'Manage display local task in available.');

    $this->drupalGet('admin/structure/crm-core/contact-types/household/form-display');
    $this->assertText(t('Name'), 'Name field is available on form display.');

    $this->drupalGet('admin/structure/crm-core/contact-types/household/display');
    $this->assertText(t('Name'), 'Name field is available on manage display.');
  }

  /**
   * Asserts a contact type link.
   *
   * The path 'admin/structure/crm-core/contact-types/' gets prepended to the
   * path provided.
   *
   * @see WebTestBase::assertLinkByHref()
   */
  public function assertContactTypeLink($href, $message = '') {
    $this->assertLinkByHref('admin/structure/crm-core/contact-types/' . $href, 0, $message);
  }

  /**
   * Asserts no contact type link.
   *
   * The path 'admin/structure/crm-core/contact-types/' gets prepended to the
   * path provided.
   *
   * @see WebTestBase::assertNoLinkByHref()
   */
  public function assertNoContactTypeLink($href, $message = '') {
    $this->assertNoLinkByHref('admin/structure/crm-core/contact-types/' . $href, $message);
  }

}
