<?php

namespace Hiraeth\Volumes;

use Elazar\Flystream\StripProtocolPathNormalizer;
use Hiraeth;
use League\Flysystem;
use jgivoni\Flysystem\Cache\CacheAdapter;

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
		$schemes  = array();
		$defaults = [
			'class'    => NULL,
			'scheme'   => 'vol',
			'disabled' => FALSE,
		];

		foreach ($app->getConfig('*', 'volume', $defaults) as $path => $config) {
			if (!$config['class'] || $config['disabled']) {
				continue;
			}

			$name       = basename($path, '.jin');
			$options    = $app->getConfig($path, 'volume.options', array());
			$adapter    = $app->get($config['class'], $options);

			if ($app->getEnvironment('CACHING', TRUE) && !empty($config['cache'])) {
				$pools   = $app->get(Hiraeth\Caching\PoolManager::class);
				$adapter = new CacheAdapter($adapter, $pools->get($config['cache']));
			}

			if (!in_array($config['scheme'], $schemes)) {
				$schemes[] = $config['scheme'];
			}

			StreamWrapper::register($config['scheme'], $name, new Flysystem\Filesystem(
				$adapter,
				$config['config'] ?? array()
			));
		}

		return $instance;
	}
}
