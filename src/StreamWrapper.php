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
	 *
	 */
	protected static $null = NULL;


	/**
	 * Set up the stream wrapper in PHP
	 */
	public static function setup($scheme, $flags = 0)
	{
		static::$null = new Flysystem\Filesystem(new Flysystem\Adapter\NullAdapter());

		return stream_wrapper_register($scheme, __CLASS__, $flags);
	}


	/**
	 * Register an individual volume at a specific name
	 */
	public static function register($name, Flysystem\FilesystemInterface $filesystem, array $config = null, $flags = 0)
	{
		static::$config[$name]      = $config;
		static::$filesystems[$name] = $filesystem;

		static::registerPlugins($name, $filesystem);
	}


	/**
	 * Unregister an individual volume at a specific name
	 */
	public static function unregister($name)
	{
		unset(static::$filesystems[$name]);
		unset(static::$config[$name]);
	}


	/**
	 * Unregister all volumes
	 */
	public static function unregisterAll()
	{
		foreach (array_keys(static::$filesystems) as $name) {
			static::unregister($name);
		}
	}


	/**
	 * Get the filesystem for this wrapper
	 */
	protected function getFilesystem()
	{
		if (!isset($this->filesystem)) {
			$this->filesystem = static::$filesystems[$this->getVolume()] ?? static::$null;
		}

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

		return ltrim(parse_url($uri, PHP_URL_PATH), '/');
	}


	/**
	 *
	 */
	protected function getConfiguration($key = null)
	{
		return $key ? static::$config[$this->getVolume()][$key] : static::$config[$this->getVolume()];
	}


	/**
	 * Determine the volume from the URI's host component <scheme>://<volume>/<target>
	 */
	public function getVolume()
	{
		return parse_url($this->uri, PHP_URL_HOST);
	}
}
