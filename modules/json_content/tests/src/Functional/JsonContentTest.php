<?php

namespace Drupal\Tests\json_content\Functional;

use Drupal\Core\Url;
use Drupal\media\Entity\Media;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\workflows\Entity\Workflow;

/**
 * @group headless
 * @group json_content
 */
class JsonContentTest extends BrowserTestBase {

  use ContentModerationTestTrait;
  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'content_moderation',
    'json_content',
    'media',
    'media_test_source',
    'node',
    'toolbar',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->createMediaType('test', ['id' => 'test']);
  }

  public function test() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->config('system.site')->set('page.front', '/frontpage')->save();
    $this->config('lightning_api.settings')
      ->set('entity_json', TRUE)
      ->save();
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalCreateContentType(['type' => 'page']);
    $this->createEditorialWorkflow();

    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = Workflow::load('editorial');
    /** @var \Drupal\content_moderation\Plugin\WorkflowType\ContentModerationInterface $plugin */
    $plugin = $workflow->getTypePlugin();
    $plugin->addEntityTypeAndBundle('node', 'page');
    $workflow->save();

    // Anonymous users see login form on homepage.
    $this->drupalGet('<front>');
    $assert_session->statusCodeEquals(200);
    $form = $assert_session->elementExists('css', '#user-login-form');
    $assert_session->fieldExists('Username', $form);
    $assert_session->fieldExists('Password', $form);

    // Authenticated users without the "access content overview" permission are
    // not redirected from the homepage.
    $account = $this->drupalCreateUser();
    $this->drupalLogin($account);
    $this->drupalGet('<front>');
    $assert_session->pageTextContains('This site has no homepage content');
    $assert_session->addressEquals('/');

    // Authenticated users with the "access content overview" permission are
    // redirected to the /admin/content page.
    $account = $this->drupalCreateUser(['access content overview']);
    $this->drupalLogin($account);
    $this->drupalGet('<front>');
    $assert_session->addressEquals('/admin/content');

    // The "Back to site" link does not appear in the toolbar when on an admin
    // page.
    $account = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($account);
    $this->drupalGet('/admin');
    $assert_session->elementExists('css', 'nav#toolbar-bar');
    $assert_session->linkNotExists('Back to site');

    // Ensure that the "latest version" tab is suppressed.
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'moderation_state' => 'published',
    ]);
    $this->drupalGet('/admin/content');
    $assert_session->statusCodeEquals(200);
    $page->clickLink('Edit ' . $node->getTitle());
    $assert_session->statusCodeEquals(200);
    $assert_session->linkExists('View JSON');
    $assert_session->linkNotExists('Latest version');
    $page->selectFieldOption('moderation_state[0][state]', 'Draft');
    $page->pressButton('Save');
    $assert_session->statusCodeEquals(200);
    $page->clickLink('Edit ' . $node->getTitle());
    $assert_session->statusCodeEquals(200);
    $assert_session->linkExists('View JSON');
    $assert_session->linkNotExists('Latest version');

    Media::create([
      'bundle' => 'test',
      'name' => 'Testing',
      'field_media_test' => $this->randomString(),
    ])->save();
    $this->drupalGet(Url::fromRoute('entity.media.collection'));
    $assert_session->statusCodeEquals(200);
    $page->clickLink('Edit Testing');
    $assert_session->statusCodeEquals(200);
    $assert_session->linkNotExists('View JSON');
    $assert_session->linkExists('Edit');

    $this->config('media.settings')->set('standalone_url', TRUE)->save();
    $this->getSession()->reload();
    $assert_session->statusCodeEquals(200);
    $assert_session->linkExists('View JSON');
    $assert_session->linkExists('Edit');
  }

}
