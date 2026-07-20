<?php

namespace Drupal\Tests\s3fs\Unit;

use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\s3fs\File\MimeType\ExtensionMimeTypeGuesser;
use Drupal\Tests\UnitTestCase;

/**
 * Tests filename mimetype detection.
 *
 * @group s3fs
 *
 * @covers \Drupal\s3fs\File\MimeType\ExtensionMimeTypeGuesser
 */
class S3fsExtensionMimeTypeGuesserTest extends UnitTestCase {

  /**
   * Tests mapping of mimetypes from filenames.
   *
   * @dataProvider providerTestFileMimeTypeDetection
   */
  public function testFileMimeTypeDetection(string $filename, ?string $expected_mime_type): void {
    $uses_new_guesser = Semver::satisfies(\Drupal::VERSION, '>=9.1');
    $use_new_assert = InstalledVersions::satisfies(new VersionParser(), 'phpunit/phpunit', '>=11.5');

    $module_handler_mock = $this->createMock(ModuleHandler::class);
    $module_handler_mock
      ->expects($this->once())
      ->method('alter')
      ->with(
        self::equalTo('file_mimetype_mapping'),
        // @phpstan-ignore-next-line
        $use_new_assert ? self::isArray() : self::isType('array'),
      );

    $s3fs_guesser = new ExtensionMimeTypeGuesser($module_handler_mock);

    if ($uses_new_guesser) {
      $this->assertTrue(method_exists($s3fs_guesser, 'guessMimeType'));
      $s3fs_output = $s3fs_guesser->guessMimeType('public://' . $filename);
    }
    else {
      $this->assertTrue(method_exists($s3fs_guesser, 'guess'));
      $s3fs_output = $s3fs_guesser->guess('public://' . $filename);
    }
    if ($expected_mime_type == NULL && version_compare(\Drupal::VERSION, '10.0.0', '<')) {
      $expected_mime_type = 'application/octet-stream';
    }
    $this->assertSame($expected_mime_type, $s3fs_output);

  }

  /**
   * DataProvider for testFileMimeTypeDetection().
   */
  public static function providerTestFileMimeTypeDetection(): \Generator {

    yield 'Java Archive' => [
      'test.jar',
      'application/java-archive',
    ];

    yield 'JPEG Image' => [
      'test.jpeg',
      'image/jpeg',
    ];

    yield 'Capitalized extension' => [
      'test.JPEG',
      'image/jpeg',
    ];

    yield 'Shorter extension same mime type as JPEG image' => [
      'test.jpg',
      'image/jpeg',
    ];

    yield 'Mixed extension, JPEG' => [
      'test.jar.jpg',
      'image/jpeg',
    ];

    yield 'Mixed extension, JAR' => [
      'test.jpg.jar',
      'application/java-archive',
    ];

    yield 'filename.pcf.Z (dual extension) is a font' => [
      'test.pcf.Z',
      'application/x-font',
    ];

    yield 'pcf.Z (dual extension) is not detected without filename' => [
      'pcf.Z',
      NULL,
    ];

    yield 'No extension returns NULL' => [
      'jar',
      NULL,
    ];

    yield 'Unknown extension is NULL' => [
      'some.junk',
      NULL,
    ];

  }

  /**
   * Test the extension guesser by passing in a custom mapping.
   */
  public function testCustomMapping(): void {

    $uses_new_guesser = Semver::satisfies(\Drupal::VERSION, '>=9.1');

    $mapping = [
      'mimetypes' => [
        0 => 's3fs/test-type',
      ],
      'extensions' => [
        's3fs' => 0,
      ],
    ];

    $module_handler_mock = $this->createMock(ModuleHandler::class);
    $module_handler_mock->expects($this->never())->method('alter');

    $s3fs_extension_guesser = new ExtensionMimeTypeGuesser($module_handler_mock);

    $s3fs_extension_guesser->setMapping($mapping);
    if ($uses_new_guesser) {
      $this->assertTrue(method_exists($s3fs_extension_guesser, 'guessMimeType'));
      $this->assertSame('s3fs/test-type', $s3fs_extension_guesser->guessMimeType('test.s3fs'));
    }
    else {
      $this->assertTrue(method_exists($s3fs_extension_guesser, 'guess'));
      $this->assertSame('s3fs/test-type', $s3fs_extension_guesser->guess('test.s3fs'));
    }
  }

  /**
   * Test alter hook usage.
   */
  public function testAlterHook(): void {

    $uses_new_guesser = Semver::satisfies(\Drupal::VERSION, '>=9.1');

    $module_handler_mock = $this->getMockBuilder(ModuleHandler::class)
      ->disableOriginalConstructor()
      ->getMock();
    $module_handler_mock
      ->expects($this->once())
      ->method('alter')
      ->with(
        self::equalTo('file_mimetype_mapping'),
        self::anything(),
      )
      ->willReturnCallback(
        function (string $alter_hook_name, array &$alter_map) {
          $alter_map['mimetypes']['999'] = 's3fs/test-type';
          $alter_map['extensions']['s3fs'] = '999';
        }
      );

    $s3fs_extension_guesser = new ExtensionMimeTypeGuesser($module_handler_mock);

    if ($uses_new_guesser) {
      $this->assertTrue(method_exists($s3fs_extension_guesser, 'guessMimeType'));
      $this->assertSame('s3fs/test-type', $s3fs_extension_guesser->guessMimeType('test.s3fs'));
    }
    else {
      $this->assertTrue(method_exists($s3fs_extension_guesser, 'guess'));
      $this->assertSame('s3fs/test-type', $s3fs_extension_guesser->guess('test.s3fs'));
    }
  }

}
