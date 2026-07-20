<?php
// phpcs:ignoreFile

/**
 * This file was generated via php core/scripts/generate-proxy-class.php 'Drupal\s3fs\File\MimeType\ExtensionMimeTypeGuesser' "modules/custom/s3fs/src".
 *
 * Modified for phpstan.
 */

namespace Drupal\s3fs\ProxyClass\File\MimeType {

    /**
     * Provides a proxy class for \Drupal\s3fs\File\MimeType\ExtensionMimeTypeGuesser.
     *
     * @see \Drupal\Component\ProxyBuilder
     */
    class ExtensionMimeTypeGuesser implements \Symfony\Component\Mime\MimeTypeGuesserInterface
    {

        use \Drupal\Core\DependencyInjection\DependencySerializationTrait;

        /**
         * The id of the original proxied service.
         *
         * @var string
         */
        protected $drupalProxyOriginalServiceId;

        /**
         * The real proxied service, after it was lazy loaded.
         *
         * @var ?\Drupal\s3fs\File\MimeType\ExtensionMimeTypeGuesser
         */
        protected $service;

        /**
         * The service container.
         *
         * @var \Symfony\Component\DependencyInjection\ContainerInterface
         */
        protected $container;

        /**
         * Constructs a ProxyClass Drupal proxy object.
         *
         * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
         *   The container.
         * @param string $drupal_proxy_original_service_id
         *   The service ID of the original service.
         */
        public function __construct(\Symfony\Component\DependencyInjection\ContainerInterface $container, $drupal_proxy_original_service_id)
        {
            $this->container = $container;
            $this->drupalProxyOriginalServiceId = $drupal_proxy_original_service_id;
        }

        /**
         * Lazy loads the real service from the container.
         *
         * @return \Drupal\s3fs\File\MimeType\ExtensionMimeTypeGuesser
         *   Returns the constructed real service.
         */
        protected function lazyLoadItself()
        {
            if (!isset($this->service)) {
                $this->service = $this->container->get($this->drupalProxyOriginalServiceId);
            }

            return $this->service;
        }

        /**
         * {@inheritdoc}
         *
         * @phpstan-param string $path
         */
        public function guessMimeType($path): ?string
        {
            return $this->lazyLoadItself()->guessMimeType($path);
        }

        /**
         * Deprecated guesser method.
         *
         * @param string $path
         *   Pathname to guess mimetype from extension.
         *
         * @return ?string
         *   Mimetype string.
         */
        public function guess($path)
        {
            return $this->lazyLoadItself()->guess($path);
        }

        /**
         * {@inheritdoc}
         */
        public function setMapping(?array $mapping = NULL): void
        {
            $this->lazyLoadItself()->setMapping($mapping);
        }

        /**
         * {@inheritdoc}
         */
        public function isGuesserSupported(): bool
        {
            return $this->lazyLoadItself()->isGuesserSupported();
        }

    }

}
