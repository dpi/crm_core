<?php
/**
 * @file
 * Contains \Drupal\crm_core_contact_ui\Tests\ContactUiTest;
 */

namespace Drupal\crm_core_contact_ui\Tests;

use Drupal\crm_core_contact\Entity\Contact;
use Drupal\simpletest\WebTestBase;

/**
 * Class ContactUiTest
 */
class ContactUiTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array(
    'entity',
    'text',
    'crm_core_contact',
    'crm_core_contact_ui',
  );

  public static function getInfo() {
    return array(
      'name' => t('Contact UI'),
      'description' => t('Test create/edit/delete contacts.'),
      'group' => t('CRM Core'),
    );
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
    // Create user and login.
    $user = $this->drupalCreateUser(array('administer crm_core_contact entities', 'view any crm_core_contact entity'));
    $this->drupalLogin($user);

    // There should be no contacts available after fresh installation and
    // there is link to create new contacts.
    $this->drupalGet('crm-core/contact');
    $this->assertText(t('There are no contacts available. Add one now.'), t('No contacts available after fresh installation.'));
    $this->assertLink(t('Add a contact'));

    // Open page crm-core/contact/add and assert standard contact types available.
    $this->drupalGet('crm-core/contact/add');
    $this->assertLink(t('Add Household'));
    $this->assertLink(t('Add Individual'));
    $this->assertLink(t('Add Organization'));

    // Create Household contact.
    $household_node = array(
      'contact_name[0][value]' => $this->randomName(),
    );
    $this->drupalPostForm('crm-core/contact/add/household', $household_node, 'Save Household');

    // Assert we were redirected back to the list of contacts.
    $this->assertUrl('crm-core/contact');

    $this->assertLink($household_node['contact_name[0][value]'], 0, t('Newly created contact title listed.'));
    $this->assertText(t('Household'), t('Newly created contact type listed.'));

    // Create individual contact.
    $individual_node = array(
      'contact_name[0][value]' => $this->randomName(),
//      'contact_name[und][0][title]' => 'Mr.',
//      'contact_name[und][0][given]' => $this->randomName(),
//      'contact_name[und][0][middle]' => $this->randomName(),
//      'contact_name[und][0][family]' => $this->randomName(),
//      'contact_name[und][0][generational]' => 'IV',
//      'contact_name[und][0][credentials]' => $this->randomName(),
    );
    $this->drupalPostForm('crm-core/contact/add/individual', $individual_node, 'Save Individual');

    // Assert we were redirected back to the list of contacts.
    $this->assertUrl('crm-core/contact');

    $link_label = $this->getIndividualContactTitle($individual_node);
    $this->assertLink($link_label, 0, t('Newly created contact title listed.'));
    $this->assertText(t('Individual'), t('Newly created contact type listed.'));

    // Create Organization contact.
    $organization_node = array(
      'contact_name[0][value]' => $this->randomName(),
    );
    $this->drupalPostForm('crm-core/contact/add/organization', $organization_node, t('Save Organization'));

    // Assert we were redirected back to the list of contacts.
    $this->assertUrl('crm-core/contact');

    $this->assertLink($organization_node['contact_name[0][value]'], 0, t('Newly created contact title listed.'));
    $this->assertText(t('Organization'), t('Newly created contact type listed.'));

    // Edit operations.
    // We know that created nodes household is id 1, individual is no 2,
    // organization is no 3. But we should have better API to find contact by
    // name.
    $household_node = $this->householdContactValues();
    $this->drupalPostForm('crm-core/contact/1/edit', $household_node, t('Save Household'));

    // Assert we were redirected back to the list of contacts.
    $this->assertUrl('crm-core/contact/1');
    $this->assertText($household_node['contact_name[0][value]'], 0, t('Contact updated.'));

    // Check listing page.
    $this->drupalGet('crm-core/contact');
    $this->assertLink($household_node['contact_name[0][value]'], 0, t('Updated contact title listed.'));

    // Delete household contact.
    $this->drupalPostForm('crm-core/contact/1/delete', array(), t('Yes'));
    $this->assertUrl('crm-core/contact');
    $this->assertNoLink($household_node['contact_name[0][value]'], 0, t('Deleted contact title no more listed.'));

    // Edit individual contact.
    $individual_node = $this->individualContactValues();
    $this->drupalPostForm('crm-core/contact/2/edit', $individual_node, t('Save Individual'));

    // Assert we were redirected back to the list of contacts.
    $this->assertUrl('crm-core/contact/2');

    // Check listing page.
    $this->drupalGet('crm-core/contact');
    $link_label = $this->getIndividualContactTitle($individual_node);
    $this->assertLink($link_label, 0, t('Updated individual contact title listed.'));

    // Delete individual contact.
    $this->drupalPostForm('crm-core/contact/2/delete', array(), t('Yes'));
    $this->assertUrl('crm-core/contact');
    $this->assertNoLink($link_label, 0, t('Deleted individual contact title no more listed.'));

    // Edit organization contact.
    $organization_node = $this->organizationContactValues();
    $this->drupalPostForm('crm-core/contact/3/edit', $organization_node, t('Save Organization'));

    // Assert we were redirected back to the list of contacts.
    $this->assertUrl('crm-core/contact/3');
    $this->assertText($organization_node['contact_name[0][value]'], 0, t('Contact updated.'));

    // Check listing page.
    $this->drupalGet('crm-core/contact');
    $this->assertLink($organization_node['contact_name[0][value]'], 0, t('Updated contact title listed.'));

    // Delete organization contact.
    $this->drupalPostForm('crm-core/contact/3/delete', array(), t('Yes'));
    $this->assertUrl('crm-core/contact');
    $this->assertNoLink($organization_node['contact_name[0][value]'], 0, t('Deleted contact title no more listed.'));

    // Assert that there are no contacts left.
    $this->assertText(t('There are no contacts available. Add one now.'), t('No contacts available after fresh installation.'));
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
    $this->assertContactTypeLink('household', t('Edit link for household.'));
    $this->assertContactTypeLink('household/disable', t('Disable link for household.'));
    $this->assertNoContactTypeLink('household/enable', t('No enable link for household.'));
    $this->assertContactTypeLink('household/delete', t('Delete link for household.'));

    $this->assertcontacttypelink('individual', t('Edit link for individual.'));
    $this->assertcontacttypelink('individual/disable', t('Disable link for individual.'));
    $this->assertNoContacttypelink('individual/enable', t('No enable link for individual.'));
    $this->assertcontacttypelink('individual/delete', t('Delete link for individual.'));

    $this->assertcontacttypelink('organization', t('Edit link for organization.'));
    $this->assertcontacttypelink('organization/disable', t('Disable link for organization.'));
    $this->assertNoContacttypelink('organization/enable', t('No enable link for organization.'));
    $this->assertcontacttypelink('organization/delete', t('Delete link for organization.'));

    // Given the 'household' contact type is disabled.
    $this->drupalPostForm('admin/structure/crm-core/contact-types/household/disable', array(), t('Disable'));

    // When I visit the contact type admin page.
    $this->drupalGet('admin/structure/crm-core/contact-types');

    // Then I should see an enable link.
    $this->assertContactTypeLink('household/enable', t('Enable link for household.'));
    // And I should not see a disable link.
    $this->assertNoContactTypeLink('household/disable', t('No disable link for household.'));

    // When I enable 'household'
    $this->drupalPostForm('admin/structure/crm-core/contact-types/household/enable', array(), t('Enable'));

    // Then I should see a disable link.
    $this->assertContactTypeLink('household/disable', t('Disable link for household.'));

    // Given there is a contact of type 'individual.'.
    Contact::create(array('type' => 'individual'))->save();

    // When I visit the contact type admin page.
    $this->drupalGet('admin/structure/crm-core/contact-types');

    // Then I should not see a delete link.
    $this->assertNoContactTypeLink('individual/delete', t('No delete link for individual.'));

    // When I edit the organization type.
    $this->drupalGet('admin/structure/crm-core/contact-types/organization');

    // Then I should see a delete link.
    $this->assertContactTypeLink('organization/delete', t('Delete link on organization type form.'));

    // When I edit the individual type.
    $this->drupalGet('admin/structure/crm-core/contact-types/organization');

    // Then I should not see a delete link.
    $this->assertNoContactTypeLink('individual/delete', t('No delete link on individual type form.'));
  }

  /**
   * Returns the title of an individual contact.
   */
  public static function getIndividualContactTitle($post_array) {
    return $post_array['contact_name[0][value]'];
//    return $post_array['contact_name[und][0][title]'] . ' ' . $post_array['contact_name[und][0][given]'] . ' '
//         . $post_array['contact_name[und][0][middle]'] . ' ' . $post_array['contact_name[und][0][family]'] . ' '
//         . $post_array['contact_name[und][0][generational]'] . ', ' . $post_array['contact_name[und][0][credentials]'];
  }

  /**
   * Returns random post form data for an individual contact.
   */
  public function individualContactValues() {
    return array(
      'contact_name[0][value]' => $this->randomName(),
//      'contact_name[und][0][title]' => 'Ms.',
//      'contact_name[und][0][given]' => DrupalTestCase::randomName(),
//      'contact_name[und][0][middle]' => DrupalTestCase::randomName(),
//      'contact_name[und][0][family]' => DrupalTestCase::randomName(),
//      'contact_name[und][0][generational]' => 'Jr.',
//      'contact_name[und][0][credentials]' => DrupalTestCase::randomName(),
    );
  }

  /**
   * Returns random post form data for a household contact.
   */
  public function householdContactValues() {
    return array(
      'contact_name[0][value]' => $this->randomName(),
//      'contact_name[und][0][given]' => $this->randomName(),
    );
  }

  /**
   * Returns the title of an organization contact.
   */
  public function getOrganizationContactTitle($organization_values) {
    return $organization_values['contact_name[0][value]'];
//    return $organization_values['contact_name[und][0][given]'];
  }


  /**
   * Returns random post form data for an organization contact.
   */
  public function organizationContactValues() {
    return array(
      'contact_name[0][value]' => $this->randomName(),
//      'contact_name[und][0][given]' => $this->randomName(),
    );
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