<?php

declare(strict_types=1);

namespace Drupal\Tests\media_entity_download\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Site\Settings;
use Drupal\Tests\linkit\Kernel\LinkitKernelTestBase;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;

/**
 * Tests the substitution plugins.
 *
 * @group media_entity_download
 */
class LinkitSubstitutionPluginTest extends LinkitKernelTestBase {

  /**
   * The substitution manager.
   *
   * @var \Drupal\linkit\SubstitutionManagerInterface
   */
  protected $substitutionManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Additional modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'file',
    'media',
    'media_test_source',
    'image',
    'field',
    'media_entity_download',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->substitutionManager = $this->container->get('plugin.manager.linkit.substitution');
    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['media']);
    \Drupal::entityTypeManager()->clearCachedDefinitions();

    unset($GLOBALS['config']['system.file']);
    \Drupal::configFactory()->getEditable('system.file')->set('default_scheme', 'public')->save();
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    $container->register('stream_wrapper.public', 'Drupal\Core\StreamWrapper\PublicStream')
      ->addTag('stream_wrapper', ['scheme' => 'public']);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpFilesystem() {
    $public_file_directory = $this->siteDirectory . '/files';

    mkdir($this->siteDirectory, 0775);
    mkdir($this->siteDirectory . '/files', 0775);
    mkdir($this->siteDirectory . '/files/config/' . Settings::get('config_sync_directory'), 0775, TRUE);

    $this->setSetting('file_public_path', $public_file_directory);

    $GLOBALS['config_directories'] = [
      Settings::get('config_sync_directory') => $this->siteDirectory . '/files/config/sync',
    ];
  }

  /**
   * Test the media substitution.
   */
  public function testMediaSubstitution() {
    // Set up media bundle and fields.
    $media_type = MediaType::create([
      'label' => 'test',
      'id' => 'test',
      'description' => 'Test type.',
      'source' => 'file',
    ]);
    $media_type->save();
    $source_field = $media_type->getSource()->createSourceField($media_type);
    $source_field->getFieldStorageDefinition()->save();
    $source_field->save();
    $media_type->set('source_configuration', [
      'source_field' => $source_field->getName(),
    ])->save();

    $file = File::create([
      'uid' => 1,
      'filename' => 'druplicon.txt',
      'uri' => 'public://druplicon.txt',
      'filemime' => 'text/plain',
      'status' => FileInterface::STATUS_PERMANENT,
    ]);
    $file->save();

    $media = Media::create([
      'bundle' => 'test',
      $source_field->getName() => ['target_id' => $file->id()],
    ]);
    $media->save();

    $media_substitution = $this->substitutionManager->createInstance('media_download');
    $expected = '/media/' . $media->id() . '/download?attachment';
    $this->assertEquals($expected, $media_substitution->getUrl($media)->toString());

    $media_substitution = $this->substitutionManager->createInstance('media_download_inline');
    $expected = '/media/' . $media->id() . '/download?inline';
    $this->assertEquals($expected, $media_substitution->getUrl($media)->toString());
  }

}
