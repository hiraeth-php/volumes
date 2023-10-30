<?php

namespace Hiraeth\Volumes;

use Hiraeth;
use League\Flysystem;
use jgivoni\Flysystem\Cache\CacheAdapter;
use RuntimeException;

/**
 *
 */
class ApplicationProvider implements Hiraeth\Provider
{
	/**
	 * @var string
	 */
	const CACHE_PATH = 'storage/cache/volumes/';

	/**
	 * @var string[]
	 */
	static protected $schemes = array();


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
	 *
	 * @param Hiraeth\State $instance
	 */
	public function __invoke(object $instance, Hiraeth\Application $app): object
	{
		$defaults = [
			'class'    => NULL,
			'scheme'   => 'vol',
			'disabled' => FALSE,
		];

		foreach ($app->getConfig('*', 'volume', $defaults) as $path => $config) {
			if (!$config['class'] || $config['disabled']) {
				continue;
			}

			$name    = basename($path, '.jin');
			$options = $app->getConfig($path, 'volume.options', $this->getDefaultOptions());
			$adapter = $app->get($config['class'], $options);

			if ($app->getEnvironment('CACHING', TRUE) && !empty($config['cache'])) {
				$pools   = $app->get(Hiraeth\Caching\PoolManager::class);
				$adapter = new CacheAdapter($adapter, $pools->get($config['cache']));
			}

			StreamWrapper::register($config['scheme'], $name, new Flysystem\Filesystem($adapter), $options);
		}

		return $instance;
	}


	/**
	 * @return array<string, mixed>
	 */
	protected function getDefaultOptions(): array
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
