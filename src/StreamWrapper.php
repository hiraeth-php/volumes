<?php

namespace Hiraeth\Volumes;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use M2MTech\FlysystemStreamWrapper\FlysystemStreamWrapper;

/**
 *
 */
class StreamWrapper extends FlysystemStreamWrapper
{
	/**
	 * @var Flysystem\Filesystem
	 */
	protected static $null;


	/**
	 * @var FilesystemOperator
	 */
	protected $filesystem;


	/**
	 * @var string
	 */
	protected $uri;


	/**
	 * Set up the stream wrapper in PHP
	 */
	public static function setup(string $scheme, int $flags = 0): bool
	{
		static::$null = new Filesystem(new InMemoryFilesystemAdapter());

		return stream_wrapper_register($scheme, __CLASS__, $flags);
	}


	/**
	 * {@inheritDoc}
	 */
	public static function register(string $protocol, FilesystemOperator $filesystem, ?array $configuration = array(), int $flags = 0): bool
	{
		static::$config[$protocol]      = $configuration;
		static::$filesystems[$protocol] = $filesystem;

		return parent::register($protocol, $filesystem);
	}


	/**
	 * {@inheritDoc}
	 */
	public static function unregister($name): bool
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
	public static function unregisterAll(): void
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
