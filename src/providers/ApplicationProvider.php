<?php

namespace Hiraeth\Volumes;

use Hiraeth;
use League\Flysystem;

/**
 *
 */
class ApplicationProvider implements Hiraeth\Provider
{
	/**
	 *
	 */
	const CACHE_PATH = 'storage/cache/volumes/';


	/**
	 * {@inheritDoc}
	 */
	static public function getInterfaces(): array
	{
		return [
			Hiraeth\Application::class
		];
	}


	/**
	 * {@inheritDoc}
	 */
	public function __invoke($state, Hiraeth\Application $app): object
	{
		$defaults = [
			'class'    => NULL,
			'disabled' => FALSE
		];

		foreach ($app->getConfig('*', 'volume', $defaults) as $path => $config) {
			if (!$config['class'] || $config['disabled']) {
				continue;
			}

			$args    = array();
			$name    = basename($path, '.jin');
			$options = $app->getConfig($path, 'volume.options', $this->getDefaultOptions());

			foreach ($options as $key => $value) {
				$args[':' . $key] = $value;
			}

			$adapter = $app->get($config['class'], $args);

			if ($app->getEnvironment('CACHING', TRUE) && !empty($config['caching']['ttl'])) {
				$path    = $config['path'] ?? $app->getDirectory(static::CACHE_PATH . $name);
				$local   = new Flysystem\Adapter\Local($path);
				$cache   = new Flysystem\Cached\Storage\Adapter($local, 'file', $caching['ttl']);
				$adapter = new Flysystem\Cached\Storage\CachedAdapter($adapter, $cache);
			}

			StreamWrapper::register($name, new Flysystem\Filesystem($adapter), $options);
		}

		StreamWrapper::setup($app->getConfig('packages/volumes', 'volumes.scheme', 'vol'));

		return $state;
	}


	/**
	 *
	 */
	protected function getDefaultOptions()
	{
		return [
			'metadata'    => ['timestamp', 'size', 'visibility'],
			'public_mask' => 0004,
			'permissions' => [
				'file' => [
					'public'  => 0666 ^ umask(),
					'private' => 0660 ^ umask()
				],
				'dir' => [
					'public'  => 0777 ^ umask(),
					'private' => 0770 ^ umask()
				]
			]
		];
	}
}
