<?php

namespace Hiraeth\Volumes;

use Twistor;
use League\Flysystem;

/**
 *
 */
class StreamWrapper extends Twistor\FlysystemStreamWrapper
{
	/**
	 * @var Flysystem\Filesystem
	 */
	protected static $null;


	/**
	 * Set up the stream wrapper in PHP
	 */
	public static function setup(string $scheme, int $flags = 0): bool
	{
		static::$null = new Flysystem\Filesystem(new Flysystem\Adapter\NullAdapter());

		return stream_wrapper_register($scheme, __CLASS__, $flags);
	}


	/**
	 * Register an individual volume at a specific name
	 *
	 * @param string $name
	 * @param array<string, mixed> $config
	 * @param int $flags
	 * @return bool
	 */
	public static function register($name, Flysystem\FilesystemInterface $filesystem, ?array $config = null, $flags = 0)
	{
		static::$config[$name]      = $config;
		static::$filesystems[$name] = $filesystem;

		return static::registerPlugins($name, $filesystem);
	}


	/**
	 * Unregister an individual volume at a specific name
	 *
	 * @return bool
	 */
	public static function unregister($name)
	{
		unset(static::$filesystems[$name]);
		unset(static::$config[$name]);

		return TRUE;
	}


	/**
	 * Unregister all volumes
	 *
	 * @return bool
	 */
	public static function unregisterAll()
	{
		foreach (array_keys(static::$filesystems) as $name) {
			static::unregister($name);
		}

		return TRUE;
	}


	/**
	 * Get the filesystem for this wrapper
	 */
	protected function getFilesystem()
	{
		$this->filesystem = static::$filesystems[$this->getVolume()] ?? static::$null;

		return $this->filesystem;
	}


	/**
	 * Determine the target from the URI's path component <scheme>://<volume>/<target>
	 */
	protected function getTarget($uri = NULL)
	{
		if (!isset($uri)) {
			$uri = $this->uri;
		}

		return ltrim(parse_url($uri, PHP_URL_PATH) ?: '', '/');
	}


	/**
	 * @return array<string, mixed>
	 */
	protected function getConfiguration($key = null)
	{
		return $key
			? static::$config[$this->getVolume()][$key]
			: static::$config[$this->getVolume()]
		;
	}


	/**
	 * Determine the volume from the URI's host component <scheme>://<volume>/<target>
	 */
	public function getVolume(): string
	{
		return parse_url($this->uri, PHP_URL_HOST) ?: '';
	}
}
