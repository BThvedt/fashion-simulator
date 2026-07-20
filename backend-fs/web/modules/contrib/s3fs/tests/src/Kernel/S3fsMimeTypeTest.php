<?php

namespace Drupal\Tests\s3fs\Kernel;

use Composer\Semver\Semver;
use Drupal\KernelTests\Core\File\FileTestBase;

/**
 * Tests filename mimetype detection.
 *
 * @group File
 */
class S3fsMimeTypeTest extends FileTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['file_test', 's3fs'];

  /**
   * Tests mapping of mimetypes from filenames.
   */
  public function testFileMimeTypeDetection(): void {
    $uses_new_guesser = Semver::satisfies(\Drupal::VERSION, '>=9.1');
    $s3fs_guesser = $this->container->get('s3fs.mime_type.guesser');

    $test_case = [
      'test.jar' => 'application/java-archive',
      'test.jpeg' => 'image/jpeg',
      'test.JPEG' => 'image/jpeg',
      'test.jpg' => 'image/jpeg',
      'test.jar.jpg' => 'image/jpeg',
      'test.jpg.jar' => 'application/java-archive',
      'test.pcf.Z' => 'application/x-font',
      'jar' => 'application/octet-stream',
      'some.junk' => 'application/octet-stream',
      'test.ogg' => 'audio/ogg',
    ];

    foreach ($test_case as $filename => $expected_mime_type) {
      if ($uses_new_guesser) {
        $this->assertTrue(method_exists($s3fs_guesser, 'guessMimeType'));
        $s3fs_output = $s3fs_guesser->guessMimeType('public://' . $filename);
      }
      else {
        $this->assertTrue(method_exists($s3fs_guesser, 'guess'));
        $s3fs_output = $s3fs_guesser->guess('public://' . $filename);
      }
      $this->assertSame($expected_mime_type, $s3fs_output, "Expected mime type not match for $filename");
    }
  }

}
