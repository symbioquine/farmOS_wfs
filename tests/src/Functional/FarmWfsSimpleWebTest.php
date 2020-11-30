<?php

namespace Drupal\Tests\farmos_wfs\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests farmOS WFS basic handlers.
 *
 * @group farmos_wfs
 */
class FarmWfsSimpleWebTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stable';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['farmos_wfs'];

  /**
   * Tests GetCapabilities 'capability'.
   */
  public function testGetCapabilities() {
    $account = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($account);

    $this->drupalGet('wfs', ['SERVICE' => 'WFS', 'REQUEST' => 'GetCapabilities', 'VERSION' => '1.1.0']);
    $this->assertSession()->statusCodeEquals(200);
  }

}
